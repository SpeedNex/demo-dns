package node

import (
	"bytes"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// HeartbeatClient 上报节点心跳到 portal-web /api/v1/nodes/heartbeat
// 签名规范：与 dns-resolver/internal/agent 一致 (HMAC-SHA256)
type HeartbeatClient struct {
	APIEndpoint string
	Bearer      string
	HMACSecret  string
	HTTPClient  *http.Client
}

func NewHeartbeatClient(apiEndpoint, bearer, secret string, timeout time.Duration) *HeartbeatClient {
	return &HeartbeatClient{
		APIEndpoint: strings.TrimSuffix(apiEndpoint, "/"),
		Bearer:      bearer,
		HMACSecret:  secret,
		HTTPClient:  &http.Client{Timeout: timeout},
	}
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

	url := c.APIEndpoint + "/api/v1/node/nodes/heartbeat"
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(body))
	if err != nil {
		return err
	}

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	bodyHash := sha256.Sum256(body)
	canonical := ts + "\n" + strings.ToUpper(req.Method) + "\n" + req.URL.Path + "\n" + hex.EncodeToString(bodyHash[:])
	mac := hmac.New(sha256.New, []byte(c.HMACSecret))
	mac.Write([]byte(canonical))

	nonceBytes := make([]byte, 16)
	if _, err := io.ReadFull(rand.Reader, nonceBytes); err != nil {
		return fmt.Errorf("nonce: %w", err)
	}

	req.Header.Set("Authorization", "Bearer "+c.Bearer)
	req.Header.Set("X-Hmac-Key", c.HMACSecret)
	req.Header.Set("X-Signature", hex.EncodeToString(mac.Sum(nil)))
	req.Header.Set("X-Timestamp", ts)
	req.Header.Set("X-Nonce", hex.EncodeToString(nonceBytes))
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
