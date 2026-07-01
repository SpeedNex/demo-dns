package config

import (
	"os"
	"time"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Server  ServerConfig  `yaml:"server"`
	Routing RoutingConfig `yaml:"routing"`
	Node    NodeConfig    `yaml:"node"`
}

type ServerConfig struct {
	ListenAddr         string `yaml:"listen_addr"`
	ListenDNSAddr      string `yaml:"listen_dns_addr"`
	ConsoleHealthURL   string `yaml:"console_health_url"`
	ConsoleHealthToken string `yaml:"console_health_token"`
	RefreshInterval    string `yaml:"refresh_interval"`
	RequestTimeoutSec  int    `yaml:"request_timeout_seconds"`
}

type RoutingConfig struct {
	GlobalFallbackRegion string   `yaml:"global_fallback_region"`
	AllowedRegions      []string `yaml:"allowed_regions,omitempty"`
	OverloadThreshold   float64  `yaml:"overload_threshold,omitempty"`
	// ServeDomain 是 GeoDNS 服务的域名，对此域名的 A/AAAA 查询返回 resolver IP。
	// 为空则响应所有域名。
	ServeDomain         string   `yaml:"serve_domain,omitempty"`
}

// NodeConfig 节点鉴权配置。
// 2026-06-22 改造：删除 HMACSecret 字段，统一使用 Token 鉴权。
// 2026-06-22 改造：新增 NodeID 字段。install 时把 console 预签发的
// node-id（如 "phqval3wur"）写入此处，业务请求 (register/heartbeat) 时
// body 里的 node_id 字段直接读自这里，避免与 token 串值混淆。
// 2026-06-22 改造：新增 APIKeyPath 字段。install 时把 register 签发的
// api_key 缓存路径写入此处，server 启动时优先读这个路径的 api_key。
type NodeConfig struct {
	Token       string `yaml:"token"`
	NodeID      string `yaml:"node_id"`
	APIEndpoint string `yaml:"api_endpoint"`
	APIKeyPath  string `yaml:"api_key_path"`
}

func (c *Config) RefreshDuration() time.Duration {
	if c.Server.RefreshInterval == "" {
		return 30 * time.Second
	}
	d, err := time.ParseDuration(c.Server.RefreshInterval)
	if err != nil || d <= 0 {
		return 30 * time.Second
	}
	return d
}

func (c *Config) RequestTimeout() time.Duration {
	if c.Server.RequestTimeoutSec <= 0 {
		return 5 * time.Second
	}
	return time.Duration(c.Server.RequestTimeoutSec) * time.Second
}

func (c *Config) GlobalFallback() string {
	if c.Routing.GlobalFallbackRegion != "" {
		return c.Routing.GlobalFallbackRegion
	}
	return "global"
}

func (c *Config) HealthViewToken() string {
	if t := c.Server.ConsoleHealthToken; t != "" {
		return t
	}
	return os.Getenv("GEODNS_INTERNAL_TOKEN")
}

func (c *Config) ConsoleHealthURL() string {
	if u := c.Server.ConsoleHealthURL; u != "" {
		return u
	}
	return os.Getenv("GEODNS_CONSOLE_HEALTH_URL")
}

func (c *Config) NodeToken() string {
	if t := c.Node.Token; t != "" {
		return t
	}
	return os.Getenv("GEODNS_NODE_TOKEN")
}

// NodeID 返回 console 预签发的节点 code（如 "phqval3wur"）。
// 2026-06-22 新增：用于 register 端点 body 的 node_id 字段。
func (c *Config) NodeID() string {
	if id := c.Node.NodeID; id != "" {
		return id
	}
	return os.Getenv("GEODNS_NODE_ID")
}

// APIKeyPath 返回 register 签发的 api_key 缓存文件绝对路径。
// 2026-06-22 新增：避免 server 启动时基于 CWD 找 api_key。
func (c *Config) APIKeyPath() string {
	if p := c.Node.APIKeyPath; p != "" {
		return p
	}
	return os.Getenv("GEODNS_API_KEY_PATH")
}

func (c *Config) NodeAPIEndpoint() string {
	if e := c.Node.APIEndpoint; e != "" {
		return e
	}
	return os.Getenv("GEODNS_API_ENDPOINT")
}

func (c *Config) DNSListenAddr() string {
	if c.Server.ListenDNSAddr != "" {
		return c.Server.ListenDNSAddr
	}
	return ":53"
}

// ServeDomain 返回 GeoDNS 服务的域名。
// 例如 "dns.example.com"，对此域名的 A/AAAA 查询将返回 resolver IP。
// 为空则响应所有域名。
func (c *Config) ServeDomain() string {
	return c.Routing.ServeDomain
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	cfg := &Config{}
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, err
	}

	if cfg.Server.ListenAddr == "" {
		cfg.Server.ListenAddr = ":5354"
	}
	return cfg, nil
}
