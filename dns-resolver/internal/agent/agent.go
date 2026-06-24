// Package agent 实现 dns-resolver 节点生命周期管理：
//   - 心跳上报到 portal-web admin
//   - Global Config 拉取（启动 + 定时刷新）
//   - Profile 按需拉取 + 二级缓存（Memory LRU + Disk CacheEnvelope）
//   - 版本检查 + LRU 淘汰
//
// 鉴权完全基于 console 预签发的 APIKey，统一使用 Bearer Token 鉴权。
package agent

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/matching"
	"ocer-dns/dns-resolver/internal/metrics"
)

// Credentials 是 console 预签发凭据的内存表示，来源于 configs/server.yaml
// 中 control_plane.api_key / control_plane.node_id。
type Credentials struct {
	NodeID string
	APIKey string
}

type Agent struct {
	cfg     *config.Config
	engine  *matching.Engine
	metrics *metrics.Metrics
	client  *http.Client
	pCache  *cache.ProfileCache

	mu                   sync.RWMutex
	cred                 Credentials
	localProfiles        map[string]int64
	currentConfigVersion int64
	currentChecksum      string
	lastConfigPullAt     string
	lastLogFlushAt       string
	globalVersion        int64
}

type heartbeatRequest struct {
	NodeID               string `json:"node_id"`
	Status               string `json:"status"`
	UptimeSeconds        int64  `json:"uptime_seconds"`
	Version              string `json:"version"`
	CurrentConfigVersion int64  `json:"current_config_version"`
	ProfilesLoaded       int    `json:"profiles_loaded"`
	LastConfigPullAt     string `json:"last_config_pull_at,omitempty"`
	LastLogFlushAt       string `json:"last_log_flush_at,omitempty"`
}

type heartbeatEnvelope struct {
	Data struct {
		OK                        bool   `json:"ok"`
		ServerTime                string `json:"server_time"`
		NodeStatus                string `json:"node_status"`
		LatestConfigVersion       int64  `json:"latest_config_version"`
		ShouldPullConfig          bool   `json:"should_pull_config"`
		ConfigEndpoint            string `json:"config_endpoint"`
		NextHeartbeatAfterSeconds int    `json:"next_heartbeat_after_seconds"`
	} `json:"data"`
}

// New 使用 console 预签发的 APIKey / Secret 构造 Agent。
// 调用方必须先确保 cfg 已通过 config.Validate() 校验。
func New(cfg *config.Config, engine *matching.Engine, collector *metrics.Metrics) *Agent {
	timeout := 5 * time.Second
	if cfg.ControlPlane.RequestTimeoutSec > 0 {
		timeout = time.Duration(cfg.ControlPlane.RequestTimeoutSec) * time.Second
	}

	cacheDir := cfg.ControlPlane.ProfilesCacheDir
	if cacheDir == "" {
		cacheDir = cfg.ControlPlane.ProfilesPath
	}
	pc := cache.NewProfileCache(
		cacheDir,
		cfg.ControlPlane.ProfileCacheMemory,
		cfg.ControlPlane.ProfileCacheDisk,
		time.Duration(cfg.ControlPlane.ProfileEvictTTLMin)*time.Minute,
		time.Duration(cfg.ControlPlane.ProfileDiskTTLDays)*24*time.Hour,
	)

	return &Agent{
		cfg:     cfg,
		engine:  engine,
		metrics: collector,
		pCache:  pc,
		cred: Credentials{
			NodeID: strings.TrimSpace(cfg.ControlPlane.NodeID),
		},
		localProfiles: make(map[string]int64),
		client: &http.Client{
			Timeout: timeout,
		},
	}
}

func (a *Agent) StartHeartbeat(ctx context.Context) {
	ticker := time.NewTicker(time.Duration(a.heartbeatInterval()) * time.Second)
	defer ticker.Stop()

	a.sendHeartbeat()

	for {
		select {
		case <-ticker.C:
			a.sendHeartbeat()
		case <-ctx.Done():
			log.Println("Heartbeat stopped")
			return
		}
	}
}

func (a *Agent) StartConfigSync(ctx context.Context, interval time.Duration) {
	if a.cfg.ControlPlane.ConfigPollInterval > 0 {
		interval = time.Duration(a.cfg.ControlPlane.ConfigPollInterval) * time.Second
	}
	if interval <= 0 {
		interval = 30 * time.Second
	}

	// 版本检查间隔
	checkInterval := time.Duration(a.cfg.ControlPlane.VersionCheckMinutes) * time.Minute
	if checkInterval <= 0 {
		checkInterval = 5 * time.Minute
	}

	ticker := time.NewTicker(interval)
	checkTicker := time.NewTicker(checkInterval)
	defer ticker.Stop()
	defer checkTicker.Stop()

	// 启动时：拉取 Global Config + 加载磁盘缓存
	a.pullGlobalConfig()
	a.pCache.LoadFromDiskOnStartup()

	// 启动 evictor（每 5 分钟）
	go a.evictLoop()

	for {
		select {
		case <-ticker.C:
			a.pullGlobalConfig()
		case <-checkTicker.C:
			a.checkProfiles()
		case <-ctx.Done():
			log.Println("Config sync stopped")
			return
		}
	}
}

// pullGlobalConfig 拉取 Global Config（upstreams / plans / rulesets / limits）。
func (a *Agent) pullGlobalConfig() {
	path := "/api/v1/node/dns-resolver/config"
	resp, err := a.doNodeRequest(http.MethodGet, path, nil)
	if err != nil {
		log.Printf("Global config pull failed: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return
	}

	var envelope struct {
		Data json.RawMessage `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&envelope); err != nil {
		log.Printf("Decode global config failed: %v", err)
		return
	}

	var gc config.GlobalConfig
	if err := json.Unmarshal(envelope.Data, &gc); err != nil {
		log.Printf("Parse global config failed: %v", err)
		return
	}

	// 写盘
	globalPath := filepath.Join(a.cfg.ControlPlane.ProfilesPath, "global.json")
	os.MkdirAll(filepath.Dir(globalPath), 0755)
	tmpPath := globalPath + ".tmp"
	os.WriteFile(tmpPath, envelope.Data, 0644)
	os.Rename(tmpPath, globalPath)

	// 更新全局版本
	a.mu.Lock()
	a.globalVersion = gc.Version
	a.mu.Unlock()

	log.Printf("Global config applied version=%d upstreams=%d", gc.Version, len(gc.Upstreams))
}

// FetchProfile 按 Profile ID 拉取配置，经过 Memory → Disk → Portal 三级回源。
// 被三个协议 server 在查询未命中时调用。
func (a *Agent) FetchProfile(profileID string) error {
	profileID = strings.TrimSpace(profileID)
	if len(profileID) < 4 {
		return fmt.Errorf("invalid profile id: %s", profileID)
	}

	// 1. 检查内存缓存
	if _, _, ok := a.pCache.GetFromMemory(profileID); ok {
		return nil
	}

	// 2. 检查磁盘缓存
	if data, version, ok := a.pCache.GetFromDisk(profileID); ok {
		a.pCache.SetToMemory(profileID, data, version)
		return nil
	}

	// 3. 回源 Portal（SingleFlight 防击穿）
	_, _, err := a.pCache.DoOnce(profileID, func() (json.RawMessage, int64, error) {
		path := fmt.Sprintf("/api/v1/node/dns-resolver/profiles/%s", profileID)
		resp, fetchErr := a.doNodeRequest(http.MethodGet, path, nil)
		if fetchErr != nil {
			return nil, 0, fmt.Errorf("fetch profile %s: %w", profileID, fetchErr)
		}
		defer resp.Body.Close()

		if resp.StatusCode == http.StatusNotFound {
			return nil, 0, fmt.Errorf("profile %s not found on portal", profileID)
		}
		if resp.StatusCode != http.StatusOK {
			return nil, 0, fmt.Errorf("fetch profile %s returned status %d", profileID, resp.StatusCode)
		}

		body, readErr := io.ReadAll(resp.Body)
		if readErr != nil {
			return nil, 0, fmt.Errorf("read profile %s: %w", profileID, readErr)
		}

		// 解析响应获取 version
		var dataEnv struct {
			Data json.RawMessage `json:"data"`
		}
		if err := json.Unmarshal(body, &dataEnv); err != nil {
			return nil, 0, fmt.Errorf("parse profile %s: %w", profileID, err)
		}

		// 从 profile config 中提取 version
		var profileMeta struct {
			Version int64 `json:"version"`
		}
		json.Unmarshal(dataEnv.Data, &profileMeta)

		// 写入磁盘缓存
		if diskErr := a.pCache.SetToDisk(profileID, dataEnv.Data, profileMeta.Version); diskErr != nil {
			log.Printf("Write profile %s to disk cache failed: %v", profileID, diskErr)
		}

		return dataEnv.Data, profileMeta.Version, nil
	})
	if err != nil {
		return err
	}

	// 4. 从内存缓存读取（刚写入）并加载到引擎
	if data, version, ok := a.pCache.GetFromMemory(profileID); ok {
		a.pCache.SetToMemory(profileID, data, version)

		// 解析 ProfileConfig 并加载到 engine
		var bundle config.ResolverConfig
		if err := json.Unmarshal(data, &bundle); err == nil && len(bundle.Profiles) > 0 {
			p := bundle.Profiles[0]
			a.engine.LoadProfileRules(p.ProfileID, nil, nil, nil, nil, nil, nil, nil, nil)
			log.Printf("Lazy loaded profile: %s (version=%d)", profileID, version)
		}

		// 记录版本到 localProfiles（供心跳上报）
		a.mu.Lock()
		a.localProfiles[profileID] = version
		a.mu.Unlock()
	}

	return nil
}

// checkProfiles 检查所有内存缓存的 Profile 是否有新版本。
func (a *Agent) checkProfiles() {
	a.mu.RLock()
	var profileIDs []string
	for id := range a.localProfiles {
		profileIDs = append(profileIDs, id)
	}
	a.mu.RUnlock()

	if len(profileIDs) == 0 {
		return
	}
	if len(profileIDs) > 500 {
		profileIDs = profileIDs[:500]
	}

	payload := map[string]any{
		"profiles": func() map[string]int64 {
			result := make(map[string]int64)
			for _, id := range profileIDs {
				a.mu.RLock()
				result[id] = a.localProfiles[id]
				a.mu.RUnlock()
			}
			return result
		}(),
	}

	body, _ := json.Marshal(payload)
	resp, err := a.doNodeRequest(http.MethodPost, "/api/v1/node/dns-resolver/profiles/check", bytes.NewReader(body))
	if err != nil {
		log.Printf("Profile version check failed: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return
	}

	var result struct {
		Data struct {
			Updated map[string]int64 `json:"updated"`
		} `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return
	}

	for profileID, newVersion := range result.Data.Updated {
		log.Printf("Profile %s has new version: %d (local: %d), re-fetching", profileID, newVersion, a.localProfiles[profileID])
		if err := a.FetchProfile(profileID); err != nil {
			log.Printf("Re-fetch profile %s failed: %v", profileID, err)
		}
	}
}

// evictLoop 定期清理过期 Profile（内存 LRU 由 cache 内部处理，此函数清理磁盘孤儿）
func (a *Agent) evictLoop() {
	ticker := time.NewTicker(5 * time.Minute)
	defer ticker.Stop()
	for range ticker.C {
		a.pCache.LoadFromDiskOnStartup() // 重新扫描清理过期文件
	}
}

// ProfileVersions 返回当前缓存的所有 Profile 版本。
func (a *Agent) ProfileVersions() map[string]int64 {
	a.mu.RLock()
	defer a.mu.RUnlock()
	result := make(map[string]int64, len(a.localProfiles))
	for k, v := range a.localProfiles {
		result[k] = v
	}
	return result
}

func (a *Agent) sendHeartbeat() {
	// 心跳只表达"节点是否在岗 + 持有配置版本"
	// 不再带 qps/cpu/mem/disk/error 任何"健康"信息：
	//   节点是否健康由控制面在 (now - last_heartbeat_at) <= 阈值 的简单超时判断
	reqBody := heartbeatRequest{
		NodeID:               a.cred.NodeID,
		Status:               "online",
		UptimeSeconds:        0,
		Version:              a.cfg.Node.Version,
		CurrentConfigVersion: a.currentVersion(),
		ProfilesLoaded:       len(a.profileVersions()),
		LastConfigPullAt:     a.lastConfigPull(),
		LastLogFlushAt:       a.lastLogFlush(),
	}

	body, err := json.Marshal(reqBody)
	if err != nil {
		log.Printf("Failed to marshal heartbeat: %v", err)
		return
	}

	resp, err := a.doNodeRequest(http.MethodPost, "/api/v1/node/heartbeat", bytes.NewReader(body))
	if err != nil {
		log.Printf("Heartbeat failed: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		payload, _ := io.ReadAll(resp.Body)
		log.Printf("Heartbeat returned status %d: %s", resp.StatusCode, string(payload))
		return
	}

	var envelope heartbeatEnvelope
	if err := json.NewDecoder(resp.Body).Decode(&envelope); err != nil {
		log.Printf("Failed to decode heartbeat response: %v", err)
		return
	}

	if envelope.Data.ShouldPullConfig {
		a.pullGlobalConfig()
	}
}

func (a *Agent) profilesPath() string {
	if p := strings.TrimSpace(a.cfg.ControlPlane.ProfilesPath); p != "" {
		return p
	}
	return "./data/profiles"
}

func (a *Agent) doNodeRequest(method, path string, body io.Reader) (*http.Response, error) {
	fullURL := a.controlPlaneURL(path)
	log.Printf("DEBUG doNodeRequest: method=%s url=%s", method, fullURL)
	req, err := http.NewRequest(method, fullURL, body)
	if err != nil {
		return nil, err
	}

	// 2026-06-22 改造：统一为纯 Token 鉴权，删除 HMAC 签名。
	// 2026-06-21 改造：优先从 api_key 文件读取鉴权，fallback 到 APIKey。
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if bearer := a.LoadBearer(); bearer != "" {
		req.Header.Set("Authorization", "Bearer "+bearer)
	}

	return a.client.Do(req)
}

// LoadBearer 2026-06-24: 唯一从 control_plane.api_key_path 指向的文件读取鉴权 token。
// 不再 fallback 到 "configs/api_key" (CWD-相对) 或 yaml 的 APIKey 字段 —
// 安装阶段只把凭据写到单一文件 (api_key_path)，任何其他来源都是过期或无效 token。
// 找不到文件时返回空字符串，让请求以无凭据形式发出，由 server 端拒绝。
func (a *Agent) LoadBearer() string {
	if p := strings.TrimSpace(a.cfg.ControlPlane.APIKeyPath); p != "" {
		if data, err := os.ReadFile(p); err == nil {
			key := strings.TrimSpace(string(data))
			if key != "" {
				return key
			}
		}
	}
	return ""
}

func (a *Agent) controlPlaneURL(path string) string {
	base := strings.TrimRight(a.cfg.ControlPlane.Endpoint, "/")
	return base + path
}

func (a *Agent) heartbeatInterval() int {
	if a.cfg.ControlPlane.HeartbeatInterval > 0 {
		return a.cfg.ControlPlane.HeartbeatInterval
	}
	return 30
}

func (a *Agent) currentVersion() int64 {
	a.mu.RLock()
	defer a.mu.RUnlock()
	return a.currentConfigVersion
}

func (a *Agent) profileVersions() map[string]int64 {
	a.mu.RLock()
	defer a.mu.RUnlock()

	out := make(map[string]int64, len(a.localProfiles))
	for key, value := range a.localProfiles {
		out[key] = value
	}
	return out
}

func (a *Agent) lastConfigPull() string {
	a.mu.RLock()
	defer a.mu.RUnlock()
	return a.lastConfigPullAt
}

func (a *Agent) lastLogFlush() string {
	a.mu.RLock()
	defer a.mu.RUnlock()
	return a.lastLogFlushAt
}

func (a *Agent) MarkLogFlush(ts time.Time) {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.lastLogFlushAt = ts.UTC().Format(time.RFC3339)
}
