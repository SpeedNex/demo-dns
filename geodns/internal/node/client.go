package node

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strings"
	"time"

	"ocer-dns/geodns/internal/signing"
)

type Client struct {
	Token       string
	HMACSecret  string // 2026-06-22 NEW P0#3: 可选 HMAC 密钥（默认回退到 Token）
	APIEndpoint string
	client      *http.Client
}

type ResolverNode struct {
	NodeCode   string `json:"node_code"`
	Region     string `json:"region"`
	Country    string `json:"country"`
	City       string `json:"city"`
	PublicIPv4 string `json:"public_ipv4"`
	PublicIPv6 string `json:"public_ipv6"`
	Weight     int    `json:"weight"`
}

type GeoDNSConfig struct {
	Resolvers   []ResolverNode `json:"resolvers"`
	Domains     []string       `json:"domains"`
	GeneratedAt string         `json:"generated_at"`
	TTLSeconds  int            `json:"ttl_seconds"`
}

type ConfigResponse struct {
	Data GeoDNSConfig `json:"data"`
}

func NewClient(token, endpoint string) *Client {
	return &Client{
		Token:       token,
		APIEndpoint: strings.TrimSuffix(endpoint, "/"),
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

// SetHMACSecret 注入 HMAC 密钥。空字符串表示回退到 Token（向后兼容）。
func (c *Client) SetHMACSecret(secret string) { c.HMACSecret = secret }

func (c *Client) GetConfig() (*GeoDNSConfig, error) {
	url := c.APIEndpoint + "/node/geodns/config"
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}

	c.signRequest(req)

	resp, err := c.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		return nil, fmt.Errorf("config request failed: %d %s", resp.StatusCode, string(body))
	}

	var result ConfigResponse
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return &result.Data, nil
}

func (c *Client) signRequest(req *http.Request) {
	// 2026-06-22 NEW P0#3: 旧版只设了 Bearer + Timestamp + Nonce，未计算 X-Signature，
	// 会被 portal-web VerifyRequestSignature 中间件以 missing_auth_headers 拒绝。
	// 改为调用统一 signing 工具，添加完整 5 个头。
	signing.AddHMACHeaders(req, c.Token, c.HMACSecret, nil)
}

func (c *Client) RunConfigRefresh(ctx chan<- *GeoDNSConfig, refreshInterval time.Duration) {
	ticker := time.NewTicker(refreshInterval)
	defer ticker.Stop()

	c.fetchAndSend(ctx)

	for {
		select {
		case <-ticker.C:
			c.fetchAndSend(ctx)
		}
	}
}

func (c *Client) fetchAndSend(ctx chan<- *GeoDNSConfig) {
	config, err := c.GetConfig()
	if err != nil {
		log.Printf("geodns: failed to fetch config: %v", err)
		return
	}
	ctx <- config
	log.Printf("geodns: config refreshed: %d resolvers, %d domains", len(config.Resolvers), len(config.Domains))
}
