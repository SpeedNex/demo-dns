// Package agent 实现 dns-resolver 节点生命周期管理：
//   - 心跳上报到 portal-web admin（2026-06-15: dns-console-web 已合并入 portal-web）
//   - config bundle 拉取、checksum 校验、原子写盘、热加载
//   - config ACK
//
// 鉴权完全基于 console 预签发的 APIKey，统一使用 Bearer Token 鉴权。
package agent

import (
	"bytes"
	"context"
	"crypto/sha256"
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

	mu                   sync.RWMutex
	cred                 Credentials
	localProfiles        map[string]int64
	currentConfigVersion int64
	currentChecksum      string
	lastConfigPullAt     string
	lastLogFlushAt       string
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

type resolverConfigBundle struct {
	Version     int64                   `json:"version"`
	Checksum    string                  `json:"checksum"`
	GeneratedAt string                  `json:"generated_at"`
	ExpiresAt   string                  `json:"expires_at"`
	Profiles    []resolverProfileConfig `json:"profiles"`
	Rulesets    []map[string]any        `json:"rulesets"`
	Upstreams   []map[string]any        `json:"upstreams"`
	Runtime     map[string]any          `json:"runtime"`
	Signature   any                     `json:"signature"`
}

type resolverProfileConfig struct {
	ProfileID     string           `json:"profile_id"`
	UserID        string           `json:"user_id"`
	Version       int64            `json:"version"`
	DefaultAction string           `json:"default_action"`
	BlockResponse string           `json:"block_response"`
	Security      map[string]any   `json:"security"`
	Adblock       map[string]any   `json:"adblock"`
	Privacy       map[string]any   `json:"privacy"`
	Parental      map[string]any   `json:"parental"`
	Devices       []map[string]any `json:"devices"`
	Rules         []resolverRule   `json:"rules"`
	Quota         map[string]any   `json:"quota"`
}

type resolverRule struct {
	RuleID           string `json:"rule_id"`
	ListType         string `json:"list_type"`
	MatchType        string `json:"match_type"`
	Domain           string `json:"domain"`
	NormalizedDomain string `json:"normalized_domain"`
	Action           string `json:"action"`
	Category         string `json:"category"`
	Enabled          bool   `json:"enabled"`
}

// New 使用 console 预签发的 APIKey / Secret 构造 Agent。
// 调用方必须先确保 cfg 已通过 config.Validate() 校验。
func New(cfg *config.Config, engine *matching.Engine, collector *metrics.Metrics) *Agent {
	timeout := 5 * time.Second
	if cfg.ControlPlane.RequestTimeoutSec > 0 {
		timeout = time.Duration(cfg.ControlPlane.RequestTimeoutSec) * time.Second
	}

	return &Agent{
		cfg:     cfg,
		engine:  engine,
		metrics: collector,
		cred: Credentials{
			NodeID: strings.TrimSpace(cfg.ControlPlane.NodeID),
			// 2026-06-24: APIKey 字段已 deprecated,凭据改由 LoadBearer() 从文件读取。
			// 留空防止任何意外 fallback 到 yaml 旧值。
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

	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	a.pullLatestConfig()

	for {
		select {
		case <-ticker.C:
			a.pullLatestConfig()
		case <-ctx.Done():
			log.Println("Config sync stopped")
			return
		}
	}
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
		a.pullLatestConfig()
	}
}

func (a *Agent) pullLatestConfig() {
	path := fmt.Sprintf("/api/v1/node/dns-resolver/config?node_id=%s&current_version=%d",
		a.cred.NodeID, a.currentVersion())
	resp, err := a.doNodeRequest(http.MethodGet, path, nil)
	if err != nil {
		log.Printf("Config pull failed: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusNoContent {
		return
	}
	if resp.StatusCode != http.StatusOK {
		payload, _ := io.ReadAll(resp.Body)
		log.Printf("Config pull returned status %d: %s", resp.StatusCode, string(payload))
		return
	}

	rawBody, err := io.ReadAll(resp.Body)
	if err != nil {
		log.Printf("Read config response failed: %v", err)
		return
	}

	var envelope struct {
		Data json.RawMessage `json:"data"`
	}
	if err := json.Unmarshal(rawBody, &envelope); err != nil {
		log.Printf("Decode config envelope failed: %v", err)
		return
	}

	var bundle resolverConfigBundle
	if err := json.Unmarshal(envelope.Data, &bundle); err != nil {
		log.Printf("Decode config bundle failed: %v", err)
		return
	}

	if err := verifyBundleChecksum(envelope.Data, bundle.Checksum); err != nil {
		log.Printf("Config checksum mismatch for version %d: %v", bundle.Version, err)
		a.sendConfigAck(bundle.Version, bundle.Checksum, "failed", "CHECKSUM_MISMATCH", err.Error())
		return
	}

	if err := a.storeBundle(bundle.Version, bundle.Checksum, envelope.Data); err != nil {
		log.Printf("Persist config bundle failed: %v", err)
		a.sendConfigAck(bundle.Version, bundle.Checksum, "failed", "STORE_FAILED", err.Error())
		return
	}

	if err := a.loadBundleIntoEngine(bundle); err != nil {
		log.Printf("Load config bundle failed: %v", err)
		a.sendConfigAck(bundle.Version, bundle.Checksum, "failed", "LOAD_FAILED", err.Error())
		return
	}

	a.mu.Lock()
	a.currentConfigVersion = bundle.Version
	a.currentChecksum = bundle.Checksum
	a.lastConfigPullAt = time.Now().UTC().Format(time.RFC3339)
	a.mu.Unlock()

	a.sendConfigAck(bundle.Version, bundle.Checksum, "applied", "", "")
	log.Printf("Config bundle applied version=%d checksum=%s", bundle.Version, bundle.Checksum)
}

func (a *Agent) sendConfigAck(version int64, checksum, status, errorCode, errorMessage string) {
	payload := map[string]any{
		"node_id":        a.cred.NodeID,
		"config_version": version,
		"checksum":       checksum,
		"status":         status,
		"applied_at":     time.Now().UTC().Format(time.RFC3339),
		"error_code":     nil,
		"error_message":  nil,
	}
	if errorCode != "" {
		payload["error_code"] = errorCode
		payload["error_message"] = errorMessage
	}

	body, _ := json.Marshal(payload)
	resp, err := a.doNodeRequest(http.MethodPost, "/api/v1/node/dns-resolver/config/ack", bytes.NewReader(body))
	if err != nil {
		log.Printf("Config ACK failed: %v", err)
		return
	}
	defer resp.Body.Close()
	io.Copy(io.Discard, resp.Body)
}

func (a *Agent) loadBundleIntoEngine(bundle resolverConfigBundle) error {
	if len(bundle.Profiles) == 0 {
		return fmt.Errorf("bundle contains no profiles")
	}

	// UI.md #38: load every profile into its own isolated engine slot.
	// Legacy single-profile fields are still populated from the first
	// profile so existing code paths keep working unchanged.
	first := bundle.Profiles[0]
	var allowExact []string
	var allowWildcard []string
	var denyExact []string
	var denyWildcard []string
	var adblockExact []string
	var adblockWildcard []string

	bucketByProfile := make(map[string]*profileBuckets, len(bundle.Profiles))
	for i := range bundle.Profiles {
		p := bundle.Profiles[i]
		if p.ProfileID == "" {
			continue
		}
		bucketByProfile[p.ProfileID] = &profileBuckets{
			security: make(map[string][]string),
			parental: make(map[string][]string),
		}
	}

	emitRule := func(p resolverProfileConfig, rule resolverRule) {
		if !rule.Enabled {
			return
		}
		domain := normalizeRuleDomain(rule)
		if domain == "" {
			return
		}
		bkt, ok := bucketByProfile[p.ProfileID]
		if !ok {
			return
		}

		// Categorize by list type / action / category.
		switch {
		case strings.EqualFold(rule.ListType, "allow") || strings.EqualFold(rule.Action, "allow"):
			if rule.MatchType == "suffix" || rule.MatchType == "wildcard" {
				bkt.allowWildcard = append(bkt.allowWildcard, domain)
			} else {
				bkt.allowExact = append(bkt.allowExact, domain)
			}
		case strings.EqualFold(rule.ListType, "deny") || strings.EqualFold(rule.Action, "block"):
			if rule.MatchType == "suffix" || rule.MatchType == "wildcard" {
				bkt.denyWildcard = append(bkt.denyWildcard, domain)
			} else {
				bkt.denyExact = append(bkt.denyExact, domain)
			}
		case strings.EqualFold(rule.Category, "ads") || strings.EqualFold(rule.Category, "adblock"):
			if rule.MatchType == "suffix" || rule.MatchType == "wildcard" {
				bkt.adblockWildcard = append(bkt.adblockWildcard, domain)
			} else {
				bkt.adblockExact = append(bkt.adblockExact, domain)
			}
		case rule.Category != "":
			// Category-based rule — push into security / parental buckets
			// using the profile.Security.Privacy flags to choose the family.
			if p.Parental != nil && boolFromMap(p.Parental, rule.Category) {
				bkt.parental[rule.Category] = append(bkt.parental[rule.Category], domain)
			} else {
				bkt.security[rule.Category] = append(bkt.security[rule.Category], domain)
			}
		}
	}

	for i := range bundle.Profiles {
		p := bundle.Profiles[i]
		for j := range p.Rules {
			emitRule(p, p.Rules[j])
		}
	}

	// First-profile legacy copy (so old code paths still see "something").
	for _, r := range first.Rules {
		if !r.Enabled {
			continue
		}
		domain := normalizeRuleDomain(r)
		if domain == "" {
			continue
		}
		switch {
		case strings.EqualFold(r.ListType, "deny") || strings.EqualFold(r.Action, "block"):
			if r.MatchType == "suffix" || r.MatchType == "wildcard" {
				denyWildcard = append(denyWildcard, domain)
			} else {
				denyExact = append(denyExact, domain)
			}
		case strings.EqualFold(r.Category, "ads") || strings.EqualFold(r.Category, "adblock"):
			if r.MatchType == "suffix" || r.MatchType == "wildcard" {
				adblockWildcard = append(adblockWildcard, domain)
			} else {
				adblockExact = append(adblockExact, domain)
			}
		default:
			if r.MatchType == "suffix" || r.MatchType == "wildcard" {
				allowWildcard = append(allowWildcard, domain)
			} else {
				allowExact = append(allowExact, domain)
			}
		}
	}

	a.engine.LoadAllowRules(allowExact, allowWildcard)
	a.engine.LoadDenyRules(denyExact, denyWildcard)
	a.engine.LoadAdBlockDomains(adblockExact, adblockWildcard)
	a.engine.LoadSecurityCategory("malware", nil)
	a.engine.LoadSecurityCategory("phishing", nil)
	a.engine.LoadParentalCategory("adult", nil)

	// Register every profile in its own slot, applying its own
	// Security/Parental/Privacy switches (UI.md #42-#45).
	profileVersions := make(map[string]int64, len(bundle.Profiles))
	for i := range bundle.Profiles {
		p := bundle.Profiles[i]
		if p.ProfileID == "" {
			continue
		}
		bkt, ok := bucketByProfile[p.ProfileID]
		if !ok {
			bkt = &profileBuckets{
				security: make(map[string][]string),
				parental: make(map[string][]string),
			}
		}

		// UI.md #42: drop security categories whose switch is off.
		bkt.security = filterCategoryMapBySwitch(bkt.security, p.Security)
		// UI.md #43: drop parental categories whose switch is off.
		bkt.parental = filterCategoryMapBySwitch(bkt.parental, p.Parental)
		// UI.md #45: privacy sub-categories are stored under the same
		// adblock bucket but gated by the privacy map's boolean flags.
		if !profileFlagEnabled(p.Privacy, "adblock_enabled") {
			bkt.adblockExact = nil
			bkt.adblockWildcard = nil
		}

		a.engine.LoadProfileRules(p.ProfileID,
			bkt.allowExact, bkt.allowWildcard,
			bkt.denyExact, bkt.denyWildcard,
			bkt.adblockExact, bkt.adblockWildcard,
			bkt.security, bkt.parental,
		)
		profileVersions[p.ProfileID] = p.Version
	}

	a.mu.Lock()
	if len(profileVersions) > 0 {
		a.localProfiles = profileVersions
	} else {
		a.localProfiles = map[string]int64{first.ProfileID: first.Version}
	}
	a.mu.Unlock()

	if a.metrics != nil {
		a.metrics.SetActiveProfiles(int64(len(bundle.Profiles)))
	}

	return nil
}

// profileBuckets collects per-profile rules during bundle ingestion.
type profileBuckets struct {
	allowExact      []string
	allowWildcard   []string
	denyExact       []string
	denyWildcard    []string
	adblockExact    []string
	adblockWildcard []string
	security        map[string][]string
	parental        map[string][]string
}

// boolFromMap reads a bool flag from a generic map (profile.Security.Privacy).
func boolFromMap(m map[string]any, key string) bool {
	if v, ok := m[key]; ok {
		if b, ok := v.(bool); ok {
			return b
		}
	}
	return false
}

// profileFlagEnabled returns true when the named flag is missing or
// explicitly true.  A missing entry defaults to enabled so older
// bundle payloads keep their previous behaviour.
func profileFlagEnabled(m map[string]any, key string) bool {
	if m == nil {
		return true
	}
	if v, ok := m[key]; ok {
		if b, ok := v.(bool); ok {
			return b
		}
	}
	return true
}

// filterCategoryMapBySwitch drops category buckets whose boolean
// switch is off in the profile config (UI.md #42/#43).
func filterCategoryMapBySwitch(cats map[string][]string, switchMap map[string]any) map[string][]string {
	if cats == nil {
		return nil
	}
	out := make(map[string][]string, len(cats))
	for k, v := range cats {
		if profileFlagEnabled(switchMap, k) {
			out[k] = v
		}
	}
	return out
}

func (a *Agent) storeBundle(version int64, checksum string, rawJSON []byte) error {
	configDir := a.profilesPath()
	if err := os.MkdirAll(configDir, 0o755); err != nil {
		return fmt.Errorf("create profiles dir: %w", err)
	}

	tmpPath := filepath.Join(configDir, fmt.Sprintf("active.v%d.tmp", version))
	finalPath := filepath.Join(configDir, "active.json")
	previousPath := filepath.Join(configDir, "previous.json")
	checksumPath := filepath.Join(configDir, "active.json.sha256")

	if _, err := os.Stat(finalPath); err == nil {
		_ = os.Rename(finalPath, previousPath)
	}

	if err := os.WriteFile(tmpPath, rawJSON, 0o644); err != nil {
		return fmt.Errorf("write temp config: %w", err)
	}
	if err := os.Rename(tmpPath, finalPath); err != nil {
		_ = os.Remove(tmpPath)
		return fmt.Errorf("activate config: %w", err)
	}

	if err := os.WriteFile(checksumPath, []byte(checksum+"\n"), 0o644); err != nil {
		return fmt.Errorf("write checksum file: %w", err)
	}

	return nil
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

func verifyBundleChecksum(rawData []byte, expected string) error {
	if !strings.HasPrefix(expected, "sha256:") {
		return fmt.Errorf("unexpected checksum format: %s", expected)
	}

	var payload map[string]any
	if err := json.Unmarshal(rawData, &payload); err != nil {
		return fmt.Errorf("decode bundle for checksum: %w", err)
	}

	delete(payload, "checksum")
	canonical, err := marshalCanonical(payload)
	if err != nil {
		return err
	}

	actual := fmt.Sprintf("sha256:%x", sha256.Sum256(canonical))
	if actual != expected {
		return fmt.Errorf("expected %s but got %s", expected, actual)
	}

	return nil
}

func marshalCanonical(value any) ([]byte, error) {
	normalized := normalizeCanonical(value)
	return json.Marshal(normalized)
}

func normalizeCanonical(value any) any {
	switch typed := value.(type) {
	case map[string]any:
		keys := make([]string, 0, len(typed))
		for key := range typed {
			keys = append(keys, key)
		}
		sortStrings(keys)

		normalized := make(map[string]any, len(typed))
		for _, key := range keys {
			normalized[key] = normalizeCanonical(typed[key])
		}
		return normalized
	case []any:
		out := make([]any, 0, len(typed))
		for _, item := range typed {
			out = append(out, normalizeCanonical(item))
		}
		return out
	default:
		return typed
	}
}

func sortStrings(values []string) {
	for i := 0; i < len(values); i++ {
		for j := i + 1; j < len(values); j++ {
			if values[j] < values[i] {
				values[i], values[j] = values[j], values[i]
			}
		}
	}
}

func normalizeRuleDomain(rule resolverRule) string {
	domain := strings.TrimSpace(rule.NormalizedDomain)
	if domain == "" {
		domain = strings.TrimSpace(rule.Domain)
	}
	domain = strings.TrimSuffix(strings.ToLower(domain), ".")
	domain = strings.TrimPrefix(domain, "*.")
	return domain
}
