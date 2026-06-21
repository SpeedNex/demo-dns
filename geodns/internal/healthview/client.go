package healthview

import (
	"context"
	"encoding/json"
	"net/http"
	"time"

	"ocer-dns/geodns/internal/signing"
)

type Node struct {
	NodeID             string   `json:"node_id"`
	Region             string   `json:"region"`
	Country            string   `json:"country"`
	City               string   `json:"city"`
	Status             string   `json:"status"`
	PublicIPv4         string   `json:"public_ipv4"`
	PublicIPv6         string   `json:"public_ipv6"`
	SupportedProtocols []string `json:"supported_protocols"`
	Weight             int      `json:"weight"`
	LastHeartbeatAt    string   `json:"last_heartbeat_at"`
}

// Client 用于 geodns → portal-web 拉取 health-view 数据。
// 2026-06-22 NEW P0#3 修复：原来仅设 X-Internal-Token，新版与 node.Client 统一
// 通过 HMAC 签名（Bearer + X-Hmac-Key + X-Signature + X-Timestamp + X-Nonce），
// 保持两套客户端认证体系一致。
type Client struct {
	BaseURL    string
	Token      string
	HMACSecret string // 2026-06-22 NEW P0#3: 可选 HMAC 密钥（默认回退到 Token）
	HTTPClient *http.Client
}

func (c Client) Fetch(ctx context.Context) (View, error) {
	client := c.HTTPClient
	if client == nil {
		client = &http.Client{Timeout: 5 * time.Second}
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, c.BaseURL, nil)
	if err != nil {
		return View{}, err
	}

	// 2026-06-22 NEW P0#3: 与 node.Client 对齐，使用统一 HMAC 签名。
	// 注：portal-web 的 /api/v1/internal/* 接口走 shared.token:internal 中间件，
	// 中间件优先认 X-Internal-Token / X-Api-Token；HMAC 头可被中间件忽略，
	// 但对路由是 5-headers 完整请求，符合未来切到 node.hmac 时的兼容预期。
	signing.AddHMACHeaders(req, c.Token, c.HMACSecret, nil)

	// 保留旧 X-Internal-Token 作为兜底，避免中间件改版后无法回退。
	// 新版 shared.token:internal 仍认这头。
	if c.Token != "" {
		req.Header.Set("X-Internal-Token", c.Token)
	}

	resp, err := client.Do(req)
	if err != nil {
		return View{}, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return View{}, &FetchError{Status: resp.StatusCode, URL: c.BaseURL}
	}

	var payload struct {
		Data View `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		return View{}, err
	}

	return payload.Data, nil
}

// FetchError 表示 health-view 拉取失败并保留了 HTTP 状态码，
// 方便日志和告警判断是鉴权失败（401/403）还是上游故障。
type FetchError struct {
	Status int
	URL    string
}

func (e *FetchError) Error() string {
	return "healthview: fetch " + e.URL + " returned status " + http.StatusText(e.Status)
}
