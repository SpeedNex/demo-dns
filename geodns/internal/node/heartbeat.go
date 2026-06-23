package node

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"
)

// HeartbeatClient 上报节点心跳到 portal-web /api/v1/node/{heartbeat_path}
// 2026-06-23 新增：HeartbeatPath 字段，默认 /geodns/heartbeat，允许不同服务自定义路径。
// 2026-06-22 改造：统一为纯 Token 鉴权，删除 HMAC 签名。
// 2026-06-21 改造：
//   - URL 从 /api/v1/node/nodes/heartbeat 改为 /api/v1/node/heartbeat
//   - 优先用 api_key 文件鉴权，fallback 到 Bearer token
type HeartbeatClient struct {
	APIEndpoint   string
	Bearer        string
	APIKeyPath    string // 2026-06-21: register 时签发的 api_key 缓存文件路径
	HeartbeatPath string // 2026-06-23: 路径后缀，如 "geodns/heartbeat"
	HTTPClient    *http.Client
}

// NewHeartbeatClient 创建心跳客户端，hbPath 如 "geodns/heartbeat"。
func NewHeartbeatClient(apiEndpoint, bearer, hbPath string, timeout time.Duration) *HeartbeatClient {
	return &HeartbeatClient{
		APIEndpoint:   strings.TrimSuffix(apiEndpoint, "/"),
		Bearer:        bearer,
		HeartbeatPath: strings.Trim(hbPath, "/"),
		HTTPClient:    &http.Client{Timeout: timeout},
	}
}

// NewHeartbeatClientWithAPIKeyPath 2026-06-21: 创建带 api_key 路径的 client
func NewHeartbeatClientWithAPIKeyPath(apiEndpoint, bearer, apiKeyPath, hbPath string, timeout time.Duration) *HeartbeatClient {
	c := NewHeartbeatClient(apiEndpoint, bearer, hbPath, timeout)
	c.APIKeyPath = apiKeyPath
	return c
}

// loadBearer 优先从 api_key 文件读，fallback 到 Bearer
func (c *HeartbeatClient) loadBearer() string {
	if c.APIKeyPath != "" {
		if data, err := os.ReadFile(c.APIKeyPath); err == nil {
			key := strings.TrimSpace(string(data))
			if key != "" {
				return key
			}
		}
	}
	return c.Bearer
}

type HeartbeatPayload struct {
	Status               string `json:"status"`
	UptimeSeconds        int    `json:"uptime_seconds,omitempty"`
	Version              string `json:"version,omitempty"`
	CurrentConfigVersion int    `json:"current_config_version,omitempty"`
	ProfilesLoaded       int    `json:"profiles_loaded,omitempty"`
	LastConfigPullAt     string `json:"last_config_pull_at,omitempty"`
}

// Report 上报一次心跳
func (c *HeartbeatClient) Report(payload HeartbeatPayload) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("marshal heartbeat: %w", err)
	}

	// 2026-06-23: 路径改为 /api/v1/node/{heartbeat_path}，默认 "geodns/heartbeat"
	path := c.HeartbeatPath
	if path == "" {
		path = "geodns/heartbeat"
	}
	url := c.APIEndpoint + "/api/v1/node/" + path
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(body))
	if err != nil {
		return err
	}

	// 2026-06-21: 优先用 api_key 文件，fallback 到 Bearer token
	if bearer := c.loadBearer(); bearer != "" {
		req.Header.Set("Authorization", "Bearer "+bearer)
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := c.HTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 300 {
		respBody, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("heartbeat %d: %s", resp.StatusCode, string(respBody))
	}
	return nil
}

// RunSchedule 按 interval 周期上报心跳
func (c *HeartbeatClient) RunSchedule(start time.Time, version string, interval time.Duration, onSuccess func(CurrentConfigVersion int)) {
	c.ReportWithStart(start, version, 0, onSuccess)
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for range ticker.C {
		c.ReportWithStart(start, version, 0, onSuccess)
	}
}

func (c *HeartbeatClient) ReportWithStart(start time.Time, version string, profilesLoaded int, onSuccess func(currentConfigVersion int)) {
	uptime := int(time.Since(start).Seconds())
	if err := c.Report(HeartbeatPayload{
		Status:               "online",
		UptimeSeconds:        uptime,
		Version:              version,
		CurrentConfigVersion: 0,
		ProfilesLoaded:       profilesLoaded,
	}); err != nil {
		log.Printf("geodns: heartbeat report failed: %v", err)
		return
	}
	if onSuccess != nil {
		onSuccess(0)
	}
}