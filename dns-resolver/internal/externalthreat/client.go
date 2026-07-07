// Package externalthreat 实现外部威胁检测 API 调用。
//
// 2026-07-06: 新增威胁检测模块，支持：
//   - Google Safe Browsing API
//   - WhoisXML 新注册域名检测
//   - 停放域名列表拉取
//   - AI 威胁检测 API
//
// 所有 API Key 从 portal-web Global Config 的 threat_detection 字段获取。
package externalthreat

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"sync"
	"time"
)

// Config 威胁检测 API 配置，从 portal-web Global Config 的 threat_detection 字段解析。
type Config struct {
	GoogleSafebrowsingAPIKey string `json:"google_safebrowsing_api_key"`
	WhoisxmlAPIKey           string `json:"whoisxml_api_key"`
	NewlyRegisteredDays      int    `json:"newly_registered_days"`
	ParkedDomainListURL      string `json:"parked_domain_list_url"`
	AiThreatAPIURL           string `json:"ai_threat_api_url"`
	AiThreatAPIKey           string `json:"ai_threat_api_key"`
}

// Client 外部威胁检测 API 客户端，统一管理四个外部 API 的调用和缓存。
type Client struct {
	cfg    *Config
	client *http.Client

	// 缓存：域名威胁检测结果
	// Key: domain, Value: ThreatResult, TTL: 1 hour
	threatCache map[string]*ThreatResult
	cacheMutex  sync.RWMutex

	// 新注册域名列表（从 WhoisXML 同步）
	newlyRegisteredDomains map[string]bool
	newlyRegisteredMutex   sync.RWMutex

	// 停放域名列表（从 URL 拉取）
	parkedDomains    map[string]bool
	parkedDomainsMutex sync.RWMutex
}

// ThreatResult 威胁检测结果，表示域名是否为恶意、新注册、停放等。
type ThreatResult struct {
	Domain        string    `json:"domain"`
	IsMalicious   bool      `json:"is_malicious"`   // Google Safe Browsing 检测结果
	IsNewlyReg    bool      `json:"is_newly_reg"`   // 新注册域名
	IsParked      bool      `json:"is_parked"`      // 停放域名
	IsAIThreat    bool      `json:"is_ai_threat"`   // AI 威胁检测
	DetectedAt    time.Time `json:"detected_at"`
	CachedAt      time.Time `json:"cached_at"`
}

// NewClient 创建外部威胁检测客户端。
func NewClient(cfg *Config) *Client {
	return &Client{
		cfg:    cfg,
		client: &http.Client{Timeout: 5 * time.Second},
		threatCache:            make(map[string]*ThreatResult),
		newlyRegisteredDomains: make(map[string]bool),
		parkedDomains:          make(map[string]bool),
	}
}

// CheckThreat 综合威胁检测，检查域名是否为恶意、新注册、停放或 AI 检测的威胁。
//
// 返回 ThreatResult，包含所有检测结果。
// 使用缓存策略，避免频繁调用外部 API。
func (c *Client) CheckThreat(ctx context.Context, domain string) *ThreatResult {
	// 1. 检查缓存
	c.cacheMutex.RLock()
	if cached, ok := c.threatCache[domain]; ok {
		// 缓存 TTL: 1 hour
		if time.Since(cached.CachedAt) < 1*time.Hour {
			c.cacheMutex.RUnlock()
			return cached
		}
	}
	c.cacheMutex.RUnlock()

	// 2. 新建检测结果
	result := &ThreatResult{
		Domain:     domain,
		DetectedAt: time.Now(),
		CachedAt:   time.Now(),
	}

	// 3. Google Safe Browsing 检测（仅当 API Key 配置时）
	if c.cfg.GoogleSafebrowsingAPIKey != "" {
		result.IsMalicious = c.checkGoogleSafeBrowsing(ctx, domain)
	}

	// 4. 新注册域名检测（本地缓存匹配）
	c.newlyRegisteredMutex.RLock()
	result.IsNewlyReg = c.newlyRegisteredDomains[domain]
	c.newlyRegisteredMutex.RUnlock()

	// 5. 停放域名检测（本地缓存匹配）
	c.parkedDomainsMutex.RLock()
	result.IsParked = c.parkedDomains[domain]
	c.parkedDomainsMutex.RUnlock()

	// 6. AI 威胁检测（仅当 API URL 配置时）
	if c.cfg.AiThreatAPIURL != "" && c.cfg.AiThreatAPIKey != "" {
		result.IsAIThreat = c.checkAIThreat(ctx, domain)
	}

	// 7. 存入缓存
	c.cacheMutex.Lock()
	c.threatCache[domain] = result
	c.cacheMutex.Unlock()

	return result
}

// checkGoogleSafeBrowsing 调用 Google Safe Browsing API 检测域名是否为恶意。
//
// API 文档：https://cloud.google.com/safe-browsing
// 免费额度：每日 10,000 次查询
func (c *Client) checkGoogleSafeBrowsing(ctx context.Context, domain string) bool {
	if c.cfg.GoogleSafebrowsingAPIKey == "" {
		return false
	}

	url := fmt.Sprintf("https://safebrowsing.googleapis.com/v4/threatMatches:find?key=%s", c.cfg.GoogleSafebrowsingAPIKey)

	body := map[string]interface{}{
		"client": map[string]string{
			"clientId":      "ocer-dns",
			"clientVersion": "1.0",
		},
		"threatInfo": map[string]interface{}{
			"threatTypes": []string{
				"MALWARE",
				"SOCIAL_ENGINEERING",
				"UNWANTED_SOFTWARE",
				"POTENTIALLY_HARMFUL_APPLICATION",
			},
			"platformTypes":      []string{"ANY_PLATFORM"},
			"threatEntryTypes":   []string{"URL"},
			"threatEntries": []map[string]string{
				{"url": domain},
			},
		},
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		log.Printf("[GoogleSafeBrowsing] marshal error: %v", err)
		return false
	}

	req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		log.Printf("[GoogleSafeBrowsing] create request error: %v", err)
		return false
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := c.client.Do(req)
	if err != nil {
		log.Printf("[GoogleSafeBrowsing] request error: %v", err)
		return false
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		log.Printf("[GoogleSafeBrowsing] read response error: %v", err)
		return false
	}

	// 解析响应：如果 matches 不为空，说明域名有威胁
	var result struct {
		Matches []struct {
			ThreatType string `json:"threatType"`
		} `json:"matches"`
	}

	if err := json.Unmarshal(respBody, &result); err != nil {
		log.Printf("[GoogleSafeBrowsing] unmarshal error: %v", err)
		return false
	}

	if len(result.Matches) > 0 {
		log.Printf("[GoogleSafeBrowsing] domain %s is malicious: %v", domain, result.Matches[0].ThreatType)
		return true
	}

	return false
}

// checkAIThreat 调用第三方 AI 威胁检测 API。
//
// API 格式：POST {ai_threat_api_url}
// Body: {"domain": "example.com"}
// Header: Authorization: Bearer {ai_threat_api_key}
func (c *Client) checkAIThreat(ctx context.Context, domain string) bool {
	if c.cfg.AiThreatAPIURL == "" || c.cfg.AiThreatAPIKey == "" {
		return false
	}

	body := map[string]string{"domain": domain}
	jsonBody, err := json.Marshal(body)
	if err != nil {
		log.Printf("[AIThreat] marshal error: %v", err)
		return false
	}

	req, err := http.NewRequestWithContext(ctx, "POST", c.cfg.AiThreatAPIURL, bytes.NewReader(jsonBody))
	if err != nil {
		log.Printf("[AIThreat] create request error: %v", err)
		return false
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", c.cfg.AiThreatAPIKey))

	resp, err := c.client.Do(req)
	if err != nil {
		log.Printf("[AIThreat] request error: %v", err)
		return false
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		log.Printf("[AIThreat] read response error: %v", err)
		return false
	}

	// 解析响应：假设 API 返回 {"is_threat": true/false}
	var result struct {
		IsThreat bool `json:"is_threat"`
	}

	if err := json.Unmarshal(respBody, &result); err != nil {
		log.Printf("[AIThreat] unmarshal error: %v", err)
		return false
	}

	if result.IsThreat {
		log.Printf("[AIThreat] domain %s is threat", domain)
		return true
	}

	return false
}

// SyncNewlyRegisteredDomains 从 WhoisXML API 同步新注册域名列表。
//
// API 文档：https://www.whoisxmlapi.com
// 每日同步最近 N 天内注册的域名（N 由配置 newly_registered_days 指定）
func (c *Client) SyncNewlyRegisteredDomains(ctx context.Context) error {
	if c.cfg.WhoisxmlAPIKey == "" {
		return nil
	}

	// WhoisXML API URL（示例）
	url := fmt.Sprintf("https://www.whoisxmlapi.com/whoisserver/DNSServices?type=newlyregistered&apiKey=%s&days=%d", 
		c.cfg.WhoisxmlAPIKey, c.cfg.NewlyRegisteredDays)

	req, err := http.NewRequestWithContext(ctx, "GET", url, nil)
	if err != nil {
		return fmt.Errorf("create request error: %w", err)
	}

	resp, err := c.client.Do(req)
	if err != nil {
		return fmt.Errorf("request error: %w", err)
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("read response error: %w", err)
	}

	// 解析响应：假设返回 JSON 数组 { "domains": ["a.com", "b.net", ...] }
	var result struct {
		Domains []string `json:"domains"`
	}

	if err := json.Unmarshal(respBody, &result); err != nil {
		return fmt.Errorf("unmarshal error: %w", err)
	}

	// 更新本地缓存
	c.newlyRegisteredMutex.Lock()
	c.newlyRegisteredDomains = make(map[string]bool)
	for _, domain := range result.Domains {
		c.newlyRegisteredDomains[domain] = true
	}
	c.newlyRegisteredMutex.Unlock()

	log.Printf("[WhoisXML] synced %d newly registered domains", len(result.Domains))
	return nil
}

// SyncParkedDomains 从 URL 拉取停放域名列表。
//
// URL 格式：.txt 文件，每行一个域名
// 示例：https://example.com/parked-domains.txt
func (c *Client) SyncParkedDomains(ctx context.Context) error {
	if c.cfg.ParkedDomainListURL == "" {
		return nil
	}

	req, err := http.NewRequestWithContext(ctx, "GET", c.cfg.ParkedDomainListURL, nil)
	if err != nil {
		return fmt.Errorf("create request error: %w", err)
	}

	resp, err := c.client.Do(req)
	if err != nil {
		return fmt.Errorf("request error: %w", err)
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("read response error: %w", err)
	}

	// 解析 .txt 文件：每行一个域名
	lines := bytes.Split(respBody, []byte("\n"))
	
	c.parkedDomainsMutex.Lock()
	c.parkedDomains = make(map[string]bool)
	for _, line := range lines {
		domain := string(bytes.TrimSpace(line))
		if domain != "" && !bytes.HasPrefix(line, []byte("#")) {
			c.parkedDomains[domain] = true
		}
	}
	c.parkedDomainsMutex.Unlock()

	log.Printf("[ParkedDomains] synced %d parked domains", len(c.parkedDomains))
	return nil
}

// ClearCache 清除威胁检测缓存。
func (c *Client) ClearCache() {
	c.cacheMutex.Lock()
	c.threatCache = make(map[string]*ThreatResult)
	c.cacheMutex.Unlock()
}

// UpdateConfig 更新威胁检测配置。
func (c *Client) UpdateConfig(cfg *Config) {
	c.cfg = cfg
	c.ClearCache()
}