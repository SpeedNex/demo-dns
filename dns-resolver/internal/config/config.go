package config

import (
	"fmt"
	"os"
	"strings"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Node         NodeConfig         `yaml:"node"`
	Listen       ListenConfig       `yaml:"listen"`
	ControlPlane ControlPlaneConfig `yaml:"control_plane"`
	NATS         NATSConfig         `yaml:"nats"`
	Redis        RedisConfig        `yaml:"redis"`
	Logging      LoggingConfig      `yaml:"logging"`
	Upstream     []string           `yaml:"upstream"`
	Region       RegionConfig       `yaml:"region"`
	GeoDNS       GeoDNSConfig       `yaml:"geodns"`
	Cache        DNSCacheConfig     `yaml:"cache"`
}

type GeoDNSConfig struct {
	Endpoint          string `yaml:"endpoint"`
	RequestTimeoutSec int    `yaml:"request_timeout_seconds"`
}

type NodeConfig struct {
	NodeUID            string   `yaml:"node_uid"`
	Name               string   `yaml:"name"`
	Version            string   `yaml:"version"`
	Region             string   `yaml:"region"`
	Country            string   `yaml:"country"`
	City               string   `yaml:"city"`
	Provider           string   `yaml:"provider"`
	PublicIP           string   `yaml:"public_ip"`
	PublicIPv4         string   `yaml:"public_ipv4"`
	PublicIPv6         string   `yaml:"public_ipv6"`
	SupportedProtocols []string `yaml:"supported_protocols"`
}

type ListenConfig struct {
	DoH int `yaml:"doh"`
	DoT int `yaml:"dot"`
	DoQ int `yaml:"doq"`
	TCP int `yaml:"tcp"`
	UDP int `yaml:"udp"`
	// TLS 证书配置 — DoT / DoQ 必需；为空时自动生成自签名证书（开发用）
	TLSCertFile string `yaml:"tls_cert_file"`
	TLSKeyFile  string `yaml:"tls_key_file"`
}

type ControlPlaneConfig struct {
	// Endpoint 是 portal-web admin 的 Base URL，例如 https://console.ocerlink.com
	// 2026-06-15: dns-console-web 合并入 portal-web，所有 admin / agent 接口
	// 都在同一 host 上提供，因此 resolver 只需要一个 base URL。
	Endpoint string `yaml:"endpoint"`
	// DNSDomain 是该节点对外服务的 DoH 域名（如 dns.ocerlinkdata.com）。
	// 由 portal-web register 接口返回，install 阶段写入配置；
	// Caddy 自动 TLS 使用此域名申请 Let's Encrypt 证书。
	DNSDomain string `yaml:"dns_domain"`
	// APIKey 是 portal-web 后台预创建节点时签发的凭据，
	// 由 `resolver install --console=... --node-id=... --api-key=...` 写入
	// 节点启动后直接使用此凭据鉴权，不再走任何自助注册/兜底流程
	// 2026-06-24 deprecated: 不再使用,凭据统一走 APIKeyPath 指向的文件。
	// 保留字段仅为兼容旧 yaml 文件,运行时由 agent.LoadBearer() 直接读文件,
	// 不再回退到此字段。
	APIKey string `yaml:"api_key"`
	// NodeID 是 console 预签发的节点标识，必须与 control_plane.api_key 配对使用
	NodeID string `yaml:"node_id"`
	// HeartbeatInterval / ConfigPollInterval 单位为秒
	HeartbeatInterval  int `yaml:"heartbeat_interval"`
	ConfigPollInterval int `yaml:"config_poll_interval"`
	RequestTimeoutSec  int `yaml:"request_timeout_seconds"`
	// ProfilesPath 是本地 config bundle 持久化根目录
	// active.json / previous.json / active.json.sha256 都放在此目录下
	ProfilesPath string `yaml:"profiles_path"`
	// 2026-06-22: APIKeyPath 是 register 签发的明文 api_key 缓存文件路径。
	// install 阶段会写入绝对路径，避免 systemd / nohup 启动时 CWD 不固定
	// 导致 CWD-相对路径（configs/api_key）解析不到而 fallback 到 yaml 中
	// 旧格式的 ocnd_/ntk_ token，被服务端 401 "invalid api_key format" 拒掉。
	// 留空时回退到 "configs/api_key"（与历史行为兼容）。
	APIKeyPath string `yaml:"api_key_path"`
}

type NATSConfig struct {
	Endpoints []string `yaml:"endpoints"`
	Enabled   bool     `yaml:"enabled"`
}

type RedisConfig struct {
	Addr     string `yaml:"addr"`
	Password string `yaml:"password"`
	DB       int    `yaml:"db"`
	Enabled  bool   `yaml:"enabled"`
}

// DNSCacheConfig 配置 DNS 响应本地缓存
type DNSCacheConfig struct {
	Enabled bool `yaml:"enabled"`
	MaxSize int  `yaml:"max_size"` // 最大缓存条目数
	MaxTTL  int  `yaml:"max_ttl"`  // 最大 TTL（秒）
}

type LoggingConfig struct {
	Level         string `yaml:"level"`
	BufferPath    string `yaml:"buffer_path"`
	MaxBufferSize int    `yaml:"max_buffer_size_mb"`
}

type RegionConfig struct {
	Code      string   `yaml:"code"`
	Latitude  float64  `yaml:"latitude"`
	Longitude float64  `yaml:"longitude"`
	Tags      []string `yaml:"tags"`
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	// 以 Default() 为基底，再用 YAML 覆盖 — 确保未配置的字段也有合理的标准值
	cfg := Default()
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, err
	}

	return cfg, nil
}

// Validate 校验启动所必需的控制面凭据：api_key/secret/node_id 必须由
// `resolver install` 写入，不允许空值，禁止任何自助注册兜底。
func (c *Config) Validate() error {
	if strings.TrimSpace(c.ControlPlane.Endpoint) == "" {
		return fmt.Errorf("control_plane.endpoint is required")
	}
	if strings.TrimSpace(c.ControlPlane.APIKey) == "" {
		return fmt.Errorf("control_plane.api_key is required (run `resolver install` to provision)")
	}
	if strings.TrimSpace(c.ControlPlane.NodeID) == "" {
		return fmt.Errorf("control_plane.node_id is required (run `resolver install` to provision)")
	}
	if c.Listen.DoH == 0 && c.Listen.UDP == 0 && c.Listen.DoT == 0 && c.Listen.TCP == 0 && c.Listen.DoQ == 0 {
		return fmt.Errorf("at least one listen port must be configured")
	}
	return nil
}

func Default() *Config {
	return &Config{
		Node: NodeConfig{
			NodeUID:            "dev-node-01",
			Name:               "Dev Node",
			Version:            "1.0.0",
			Region:             "local",
			Country:            "US",
			Provider:           "local",
			PublicIPv4:         "127.0.0.1",
			SupportedProtocols: []string{"udp", "tcp", "doh", "dot", "doq"},
		},
		Listen: ListenConfig{
			UDP: 53,
			TCP: 53,
			DoH: 8443, // Caddy 443 → dns-resolver 8443（DoH 内部端口）
			DoT: 853,
			DoQ: 853,
		},
		ControlPlane: ControlPlaneConfig{
			Endpoint:           "http://localhost:8000",
			HeartbeatInterval:  30,
			ConfigPollInterval: 30,
			RequestTimeoutSec:  5,
			ProfilesPath:       "./data/profiles",
			APIKey:             "",
			NodeID:             "",
		},
		NATS: NATSConfig{
			Endpoints: []string{"nats://localhost:4222"},
			Enabled:   false,
		},
		Redis: RedisConfig{
			Addr:     "localhost:6379",
			Password: "",
			DB:       0,
			Enabled:  false,
		},
		Logging: LoggingConfig{
			Level: "info",
			// 2026-06-22: 改用 /var/lib/ocer-dns/log-buffer，避免 systemd-tmpfiles
			// 清理 /tmp 造成断网恢复期的日志丢失。macOS 开发机可通过 yaml 覆盖
			// 写到 ~/Library/Application Support/ocer-dns/log-buffer。
			BufferPath:    "/var/lib/ocer-dns/log-buffer",
			MaxBufferSize: 512,
		},
		Upstream: []string{"1.1.1.1", "8.8.8.8"},
		GeoDNS: GeoDNSConfig{
			Endpoint:          "http://127.0.0.1:5354",
			RequestTimeoutSec: 3,
		},
		Cache: DNSCacheConfig{
			Enabled: true,
			MaxSize: 10000,
			MaxTTL:  300,
		},
	}
}
