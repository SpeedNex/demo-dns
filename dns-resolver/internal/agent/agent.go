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
	"ocer-dns/dns-resolver/internal/resolver"
)

// deviceIndexEntry 记录设备 IP 到 Profile 的映射。
type deviceIndexEntry struct {
	ProfileID string
	DeviceID  string
}

// Credentials 是 console 预签发凭据的内存表示，来源于 configs/server.yaml
// 中 control_plane.api_key / control_plane.node_id。
type Credentials struct {
	NodeID string
	APIKey string
}

type Agent struct {
	cfg             *config.Config
	engine          *matching.Engine
	resolutionLayer *resolver.ProfileResolutionLayer
	metrics         *metrics.Metrics
	client          *http.Client
	pCache          *cache.ProfileCache

	mu             sync.RWMutex
	cred           Credentials
	localProfiles  map[string]int64
	lastLogFlushAt string
	globalVersion  int64
	deviceIndex    map[string]deviceIndexEntry // source_ip → {profileID, deviceID}

	lastProfileCheckAt time.Time // 上次 profile 版本检查时间，用于心跳触发的频率控制
}

type heartbeatRequest struct {
	NodeID         string `json:"node_id"`
	Status         string `json:"status"`
	UptimeSeconds  int64  `json:"uptime_seconds"`
	Version        string `json:"version"`
	ProfilesLoaded int    `json:"profiles_loaded"`
	LastLogFlushAt string `json:"last_log_flush_at,omitempty"`
}

type heartbeatEnvelope struct {
	Data struct {
		OK                        bool   `json:"ok"`
		ServerTime                string `json:"server_time"`
		NodeStatus                string `json:"node_status"`
		NextHeartbeatAfterSeconds int    `json:"next_heartbeat_after_seconds"`
		// 2026-06-26: 携带最新全局配置版本，触发快速拉取
		LatestConfigVersion       int64  `json:"latest_config_version"`
	} `json:"data"`
}

// New 使用 console 预签发的 APIKey / Secret 构造 Agent。
// 调用方必须先确保 cfg 已通过 config.Validate() 校验。
func New(cfg *config.Config, engine *matching.Engine, resolutionLayer *resolver.ProfileResolutionLayer, collector *metrics.Metrics) *Agent {
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
		cfg:             cfg,
		engine:          engine,
		resolutionLayer: resolutionLayer,
		metrics:         collector,
		pCache:          pc,
		cred: Credentials{
			NodeID: strings.TrimSpace(cfg.ControlPlane.NodeID),
		},
		localProfiles: make(map[string]int64),
		deviceIndex:   make(map[string]deviceIndexEntry),
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
		log.Println("[心跳] 心跳已停止")
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

	// 启动时：拉取 Global Config + 加载磁盘缓存到 Engine + 立即检查版本
	a.pullGlobalConfig()
	a.pCache.LoadFromDiskOnStartup()
	// 将磁盘缓存的 Profile 加载到 Engine，填充 deviceIndex
	for _, pid := range a.pCache.GetAllProfileIDs() {
		if err := a.FetchProfile(pid); err != nil {
			log.Printf("[配置] 启动加载 profile=%s err=%v", pid, err)
		}
	}
	// 启动后立即检查一次 Profile 版本，避免 5 分钟空窗期
	a.checkProfiles()

	// 启动 evictor（每 5 分钟）
	go a.evictLoop()

	for {
		select {
		case <-ticker.C:
			a.pullGlobalConfig()
		case <-checkTicker.C:
			a.checkProfiles()
		case <-ctx.Done():
			log.Println("[配置] 配置同步已停止")
			return
		}
	}
}

// pullGlobalConfig 拉取 Global Config（upstreams / plans / rulesets / limits）。
func (a *Agent) pullGlobalConfig() {
	path := "/api/v1/node/dns-resolver/config"
	resp, err := a.doNodeRequest(http.MethodGet, path, nil)
	if err != nil {
		log.Printf("[配置] 拉取失败 err=%v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		log.Printf("[配置] 返回状态码 status=%d body=%s", resp.StatusCode, string(body))
		return
	}

	var envelope struct {
		Data json.RawMessage `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&envelope); err != nil {
		log.Printf("[配置] 解码失败 err=%v", err)
		return
	}

	var gc config.GlobalConfig
	if err := json.Unmarshal(envelope.Data, &gc); err != nil {
		log.Printf("[配置] 解析失败 err=%v", err)
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

	log.Printf("[配置] 已应用 version=%d upstreams=%d", gc.Version, len(gc.Upstreams))
}

// FetchProfile 按 Profile ID 拉取配置，经过 Memory → Disk → Portal 三级回源。
// 被三个协议 server 在查询未命中时调用。
func (a *Agent) FetchProfile(profileID string) error {
	profileID = strings.TrimSpace(profileID)
	if len(profileID) < 4 {
		return fmt.Errorf("invalid profile id: %s", profileID)
	}

	// 1. 检查内存缓存
	if data, version, ok := a.pCache.GetFromMemory(profileID); ok {
		return a.loadProfileIntoEngine(profileID, data, version)
	}

	// 2. 检查磁盘缓存
	if data, version, ok := a.pCache.GetFromDisk(profileID); ok {
		a.pCache.SetToMemory(profileID, data, version)
		return a.loadProfileIntoEngine(profileID, data, version)
	}

	// 3. 回源 Portal（SingleFlight 防击穿）
	var rawData json.RawMessage
	var fetchVersion int64
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
			log.Printf("[缓存] 写入磁盘 profile=%s err=%v", profileID, diskErr)
		}

		rawData = dataEnv.Data
		fetchVersion = profileMeta.Version
		return dataEnv.Data, profileMeta.Version, nil
	})
	if err != nil {
		return err
	}

	// 4. 从内存缓存读取（刚写入 DoOnce）并加载到引擎
	return a.loadProfileIntoEngine(profileID, rawData, fetchVersion)
}

// loadProfileIntoEngine 将缓存的 Profile 数据加载到引擎并记录版本。
func (a *Agent) loadProfileIntoEngine(profileID string, data json.RawMessage, version int64) error {
	var meta struct {
		Version int64 `json:"version"`
	}
	json.Unmarshal(data, &meta)

	// 加载到 engine：portal-web showProfile 直接返回单 profile 对象，按 list_type/match_type 分桶
	var p config.ProfileConfig
	if err := json.Unmarshal(data, &p); err == nil && p.ProfileID != "" {
		var allowExact, allowWild, blockExact, blockWild []string
		var adblockExact, adblockWild []string
		security := make(map[string][]string)
		parental := make(map[string][]string)
		for _, r := range p.Rules {
			if r.Action == "" {
				continue
			}
			d := r.NormalizedDomain
			if d == "" {
				d = r.Domain
			}
			// list_type 格式：allowlist / blocklist / category:<top>:<sub>
			// category top: security → security[<sub>]; parental → parental[<sub>];
			//              privacy → adblock[<sub>] (广告/跟踪器归入 adblock 桶)
			//              无 sub 的扁平分类（如 category:privacy）也路由到对应桶
			if strings.HasPrefix(r.ListType, "category:") {
				rest := strings.TrimPrefix(r.ListType, "category:")
				parts := strings.SplitN(rest, ":", 2)
				top := parts[0]
				sub := ""
				if len(parts) == 2 {
					sub = parts[1]
				}
				switch top {
				case "security":
					if sub != "" {
						security[sub] = append(security[sub], d)
					} else {
						security["default"] = append(security["default"], d)
					}
				case "privacy":
					adblockExact = append(adblockExact, d)
				case "parental":
					if sub != "" {
						parental[sub] = append(parental[sub], d)
					} else {
						parental["default"] = append(parental["default"], d)
					}
				}
				continue
			}
			switch r.ListType {
			case "allowlist":
				if r.MatchType == "wildcard" || r.MatchType == "suffix" {
					allowWild = append(allowWild, d)
				} else {
					allowExact = append(allowExact, d)
				}
			case "blocklist":
				if r.MatchType == "wildcard" || r.MatchType == "suffix" {
					blockWild = append(blockWild, d)
				} else {
					blockExact = append(blockExact, d)
				}
			}
		}
		// 加载 security_data 域名列表（来自 portal-web 后台 security-data 页面）
		// 这些域名由管理员在后台维护，通过发布包进入 resolver，无需硬编码。
		//
		// group → list_type 映射：
		//   dynamic-dns    → category:security:dynamic_dns
		//   parked-domains → category:security:parked
		//   tld-blacklist  → category:security:blocked_tld
		//   allow-lists    → allowlist (exact)
		//   block-lists    → blocklist (exact)
		//   未知 group     → blocklist (exact) 兜底
		// 解析 security_data（兼容 [] 和 {} 两种 JSON 格式）
		// 当 security_data_items 表为空时，数据库可能存储为 []（空数组）而非 {}（空对象）
		if len(p.SecurityData) > 0 && len(p.SecurityData) > 2 && p.SecurityData[0] == '{' {
			var sd map[string][]string
			if err := json.Unmarshal(p.SecurityData, &sd); err == nil {
				for group, domains := range sd {
					for _, domain := range domains {
						switch group {
						case "dynamic-dns":
							security["dynamic_dns"] = append(security["dynamic_dns"], domain)
						case "parked-domains":
							security["parked"] = append(security["parked"], domain)
						case "tld-blacklist":
							security["blocked_tld"] = append(security["blocked_tld"], domain)
						case "allow-lists":
							allowExact = append(allowExact, domain)
						case "block-lists":
							blockExact = append(blockExact, domain)
						default:
							blockExact = append(blockExact, domain)
						}
					}
				}
			}
		}

		a.engine.LoadProfileRules(p.ProfileID,
			allowExact, allowWild,
			blockExact, blockWild,
			adblockExact, adblockWild,
			security, parental)
		log.Printf("[引擎] profile=%s 放行=%d 放行通配=%d 拦截=%d 拦截通配=%d 广告拦截=%d 安全分类=%d 家长控制=%d",
			p.ProfileID, len(allowExact), len(allowWild), len(blockExact), len(blockWild), len(adblockExact), len(security), len(parental))

		// Load security algorithm config (IDN Homograph, DGA, Typosquatting, DNS Rebinding)
		if p.Security != nil {
			secMap := make(map[string]any, len(p.Security)+4)
			for k, v := range p.Security {
				secMap[k] = v
			}
			// Merge privacy settings for CNAME Tracker (disguised trackers)
			if p.Privacy != nil {
				if v, ok := p.Privacy["block_disguised_trackers"]; ok {
					secMap["block_disguised_trackers"] = v
				}
			}
			a.resolutionLayer.LoadSecurityConfig(p.ProfileID, secMap)
		}

		// 记录版本到 localProfiles（供心跳上报）
		a.mu.Lock()
		a.localProfiles[profileID] = version

		// 更新设备 IP → Profile 索引
		for _, dev := range p.Devices {
			if dev.SourceIP != "" {
				a.deviceIndex[dev.SourceIP] = deviceIndexEntry{
					ProfileID: profileID,
					DeviceID:  dev.DeviceID,
				}
			}
		}
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
		log.Printf("[配置] 版本检查失败 err=%v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		payload, _ := io.ReadAll(resp.Body)
		log.Printf("[配置] 版本检查返回 status=%d body=%s", resp.StatusCode, string(payload))
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
		log.Printf("[配置] profile=%s 新版本=%d 重新拉取", profileID, newVersion)
		// 先清除缓存，确保 FetchProfile 会回源 portal 而不是返回旧缓存
		a.pCache.RemoveFromMemory(profileID)
		a.pCache.RemoveFromDisk(profileID)
		a.engine.RemoveProfile(profileID)
		a.mu.Lock()
		delete(a.localProfiles, profileID)
		a.mu.Unlock()
		if err := a.FetchProfile(profileID); err != nil {
			log.Printf("[配置] 重新拉取 profile=%s err=%v", profileID, err)
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
	reqBody := heartbeatRequest{
		NodeID:         a.cred.NodeID,
		Status:         "online",
		UptimeSeconds:  0,
		Version:        a.cfg.Node.Version,
		ProfilesLoaded: len(a.profileVersions()),
		LastLogFlushAt: a.lastLogFlush(),
	}

	body, err := json.Marshal(reqBody)
	if err != nil {
		log.Printf("[心跳] 序列化失败 err=%v", err)
		return
	}

	resp, err := a.doNodeRequest(http.MethodPost, "/api/v1/node/heartbeat", bytes.NewReader(body))
	if err != nil {
		log.Printf("[心跳] 发送失败 err=%v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		payload, _ := io.ReadAll(resp.Body)
		log.Printf("[心跳] 返回状态码 status=%d body=%s", resp.StatusCode, string(payload))
		return
	}

	var envelope heartbeatEnvelope
	if err := json.NewDecoder(resp.Body).Decode(&envelope); err != nil {
		log.Printf("[心跳] 解码响应失败 err=%v", err)
		return
	}

	// 2026-06-26: 收到心跳响应后，检查全局配置版本是否有更新
	// 如果服务端返回的 latest_config_version > 本地缓存的 globalVersion，立即拉取
	if envelope.Data.LatestConfigVersion > a.globalVersion {
		log.Printf("[配置] 心跳检测到全局配置版本变化 %d -> %d 立即拉取",
			a.globalVersion, envelope.Data.LatestConfigVersion)
		a.globalVersion = envelope.Data.LatestConfigVersion
		go a.pullGlobalConfig() // 异步拉取，不阻塞心跳循环
	}

	// 2026-06-26: 心跳成功后触发 Profile 版本检查（频率控制：至少间隔 10 秒）
	// 确保用户发布配置变更后秒级生效，无需等待 5 分钟
	if time.Since(a.lastProfileCheckAt) > 10*time.Second {
		a.lastProfileCheckAt = time.Now()
		go a.checkProfiles()
	}
}

func (a *Agent) doNodeRequest(method, path string, body io.Reader) (*http.Response, error) {
	fullURL := a.controlPlaneURL(path)
	log.Printf("[调试] doNodeRequest method=%s url=%s", method, fullURL)
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

func (a *Agent) profileVersions() map[string]int64 {
	a.mu.RLock()
	defer a.mu.RUnlock()

	out := make(map[string]int64, len(a.localProfiles))
	for key, value := range a.localProfiles {
		out[key] = value
	}
	return out
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

// GetProfileConfig 从 ProfileCache 获取 Profile 配置（Memory → Disk → Portal 三级回源）。
func (a *Agent) GetProfileConfig(profileID string) (*config.ProfileConfig, error) {
	if err := a.FetchProfile(profileID); err != nil {
		return nil, fmt.Errorf("fetch profile %s: %w", profileID, err)
	}

	// 从内存缓存读取
	data, _, ok := a.pCache.GetFromMemory(profileID)
	if !ok {
		// 兜底从磁盘读取
		data, _, ok = a.pCache.GetFromDisk(profileID)
		if !ok {
			return nil, fmt.Errorf("profile %s not found in cache", profileID)
		}
	}

	var pc config.ProfileConfig
	if err := json.Unmarshal(data, &pc); err != nil {
		return nil, fmt.Errorf("unmarshal profile %s: %w", profileID, err)
	}
	return &pc, nil
}

// LookupDeviceByIP 根据客户端 IP 查询所属 Profile 和设备。
func (a *Agent) LookupDeviceByIP(sourceIP string) (profileID string, deviceID string, ok bool) {
	a.mu.RLock()
	defer a.mu.RUnlock()
	entry, found := a.deviceIndex[sourceIP]
	if !found {
		return "", "", false
	}
	return entry.ProfileID, entry.DeviceID, true
}
