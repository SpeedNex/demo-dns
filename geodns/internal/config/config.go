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
}

// NodeConfig 节点鉴权配置。
// 2026-06-22 改造：删除 HMACSecret 字段，统一使用 Token 鉴权。
type NodeConfig struct {
	Token       string `yaml:"token"`
	APIEndpoint string `yaml:"api_endpoint"`
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
