package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"gopkg.in/yaml.v3"

	"ocer-dns/dns-resolver/internal/config"
)

// installOptions 保存 `resolver install` 子命令的入参
type installOptions struct {
	Console    string
	NodeID     string
	Token      string
	APIKey     string
	ConfigPath string
	// 以下为可选覆盖项，便于在同一控制台下批量安装同构节点
	Region   string
	Country  string
	City     string
	Provider string
	Name     string
	Force    bool
}

// runInstall 实现 `resolver install` 子命令：
//
//	resolver install \
//	    --server=http://console.example.com \
//	    --token=ak_xxx \
//	    --node-id=hk-01
//
// 行为：把 console 端预签发的 APIKey 写入控制面配置，
// resolver 启动后将跳过自助注册，直接使用预发凭据鉴权。
// 如果传了 --token，会自动调用控制面 verify 接口换取 api_key。
func runInstall(args []string) error {
	fs := flag.NewFlagSet("install", flag.ExitOnError)

	var opts installOptions
	fs.StringVar(&opts.Console, "server", "", "portal-web admin Base URL, e.g. https://console.ocerlink.com (alias: --console)")
	fs.StringVar(&opts.Console, "console", "", "")
	fs.StringVar(&opts.NodeID, "node-id", "", "Node ID assigned by console, e.g. hk-01")
	fs.StringVar(&opts.Token, "token", "", "Node token issued by console (ak_xxx); exchanged for api_key via verify API")
	fs.StringVar(&opts.APIKey, "api-key", "", "Node API key issued by console (ak_xxx); used when --token is not provided")
	fs.StringVar(&opts.ConfigPath, "config", "configs/server.yaml", "Output resolver config.yaml path")
	fs.StringVar(&opts.Name, "name", "", "Optional human-friendly node name override")
	fs.StringVar(&opts.Region, "region", "", "Optional region code, e.g. ap-northeast-1")
	fs.StringVar(&opts.Country, "country", "", "Optional country code, e.g. JP")
	fs.StringVar(&opts.City, "city", "", "Optional city, e.g. Tokyo")
	fs.StringVar(&opts.Provider, "provider", "", "Optional provider tag, e.g. AWS")
	// 2026-06-22: --force 已废弃（install 始终覆盖配置），保留以兼容旧脚本，忽略即可。
	fs.BoolVar(&opts.Force, "force", false, "Deprecated: install now always overwrites")

	if err := fs.Parse(args); err != nil {
		return err
	}

	// 如果传了 --token，用 token 换取 api_key
	if strings.TrimSpace(opts.Token) != "" {
		apiKey, err := exchangeToken(opts.Console, opts.Token)
		if err != nil {
			return fmt.Errorf("token exchange failed: %w", err)
		}
		opts.APIKey = apiKey
	}

	if err := validateInstallOptions(&opts); err != nil {
		fs.Usage()
		return err
	}

	cfg := buildInstalledConfig(&opts)

	// 2026-06-22: 一机一实例守卫 — install 时探测监听端口是否已被占用
	// 同机已有 resolver 进程时直接拒绝，强制 1 物理/虚拟主机仅部署 1 个 dns-resolver
	if err := checkPortConflicts(cfg); err != nil {
		return fmt.Errorf("port check failed: %w", err)
	}

	if err := writeConfigAtomic(opts.ConfigPath, cfg, opts.Force); err != nil {
		return fmt.Errorf("write config failed: %w", err)
	}

	// 2026-06-22: 预创建 log buffer 目录，避免 systemd-tmpfiles 清理 /tmp
	// 默认路径 /var/lib/ocer-dns/log-buffer，macOS 需在 yaml 改写
	if cfg.Logging.BufferPath != "" {
		if err := os.MkdirAll(cfg.Logging.BufferPath, 0o755); err != nil {
			return fmt.Errorf("create log buffer dir %s: %w (hint: run as root or override buffer_path in yaml)", cfg.Logging.BufferPath, err)
		}
	}

	fmt.Printf("✔ config written to %s\n", opts.ConfigPath)
	fmt.Printf("  console   = %s\n", cfg.ControlPlane.Endpoint)
	fmt.Printf("  node_id   = %s\n", cfg.ControlPlane.NodeID)
	fmt.Printf("  api_key   = %s\n", maskCredential(cfg.ControlPlane.APIKey))
	fmt.Printf("  log_buf   = %s\n", cfg.Logging.BufferPath)

	// 2026-06-22: install 完成后调用控制面 register API，告知 console 节点已注册。
	// register 失败不阻塞 install（配置已写入），仅打印警告。
	if regErr := registerNodeToConsole(cfg); regErr != nil {
		fmt.Printf("⚠ console register failed: %v (config was still written, run `resolver` to start)\n", regErr)
	} else {
		fmt.Println("✔ console register: success")
	}
	fmt.Println("Next: run `resolver` to start the node.")
	return nil
}

func validateInstallOptions(opts *installOptions) error {
	missing := make([]string, 0, 4)
	if strings.TrimSpace(opts.Console) == "" {
		missing = append(missing, "--server")
	}
	if strings.TrimSpace(opts.NodeID) == "" {
		missing = append(missing, "--node-id")
	}
	// token 或 api-key 二选一
	hasToken := strings.TrimSpace(opts.Token) != ""
	hasAPIKey := strings.TrimSpace(opts.APIKey) != ""
	if !hasToken && !hasAPIKey {
		missing = append(missing, "--token or --api-key")
	}
	if len(missing) > 0 {
		return fmt.Errorf("missing required flags: %s", strings.Join(missing, ", "))
	}

	// Endpoint 必须是 http(s) URL
	lower := strings.ToLower(strings.TrimSpace(opts.Console))
	if !strings.HasPrefix(lower, "http://") && !strings.HasPrefix(lower, "https://") {
		return fmt.Errorf("--server must be a http(s) URL, got %q", opts.Console)
	}
	return nil
}

// exchangeToken 使用 --token 调用控制面 verify API 换取 api_key
func exchangeToken(server, token string) (apiKey string, err error) {
	server = strings.TrimRight(server, "/")
	url := server + "/api/v1/node/tokens/verify"

	reqBody := fmt.Sprintf(`{"token":"%s"}`, token)
	resp, err := http.Post(url, "application/json", strings.NewReader(reqBody))
	if err != nil {
		return "", fmt.Errorf("request verify API: %w", err)
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != 200 {
		return "", fmt.Errorf("verify API returned %d: %s", resp.StatusCode, string(body))
	}

	var result struct {
		Data struct {
			NodeID string `json:"node_id"`
			APIKey string `json:"api_key"`
		} `json:"data"`
	}
	if err := json.Unmarshal(body, &result); err != nil {
		return "", fmt.Errorf("parse verify response: %w", err)
	}
	if result.Data.APIKey == "" {
		return "", fmt.Errorf("verify API returned empty api_key")
	}
	return result.Data.APIKey, nil
}

// buildInstalledConfig 在 config.Default() 的基础上覆盖控制面凭据和节点标识
func buildInstalledConfig(opts *installOptions) *config.Config {
	cfg := config.Default()

	cfg.ControlPlane.Endpoint = strings.TrimRight(opts.Console, "/")
	cfg.ControlPlane.APIKey = strings.TrimSpace(opts.APIKey)
	cfg.ControlPlane.NodeID = strings.TrimSpace(opts.NodeID)

	// 同步节点标识，便于 console 在 Node 表上做匹配
	cfg.Node.NodeUID = cfg.ControlPlane.NodeID
	if name := strings.TrimSpace(opts.Name); name != "" {
		cfg.Node.Name = name
	} else {
		cfg.Node.Name = "node-" + cfg.ControlPlane.NodeID
	}
	if v := strings.TrimSpace(opts.Region); v != "" {
		cfg.Node.Region = v
		cfg.Region.Code = v
	}
	if v := strings.TrimSpace(opts.Country); v != "" {
		cfg.Node.Country = v
	}
	if v := strings.TrimSpace(opts.City); v != "" {
		cfg.Node.City = v
	}
	if v := strings.TrimSpace(opts.Provider); v != "" {
		cfg.Node.Provider = v
	}

	// 启动前立即校验：缺少任何凭据直接报错，不允许写入残缺配置
	if err := cfg.Validate(); err != nil {
		panic(fmt.Sprintf("internal: install built invalid config: %v", err))
	}
	return cfg
}

// writeConfigAtomic 原子写入配置文件，避免半写导致 resolver 启动读坏文件
func writeConfigAtomic(path string, cfg *config.Config, force bool) error {
	// 已有配置且未传 --force 时直接拒绝，避免误覆盖
	if !force {
		if _, err := os.Stat(path); err == nil {
			return fmt.Errorf("config already exists at %s; pass --force to overwrite", path)
		}
	}

	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return fmt.Errorf("create config dir: %w", err)
	}

	data, err := yaml.Marshal(cfg)
	if err != nil {
		return fmt.Errorf("marshal config: %w", err)
	}

	tmp := path + ".tmp"
	if err := os.WriteFile(tmp, data, 0o600); err != nil {
		return fmt.Errorf("write temp config: %w", err)
	}
	if err := os.Rename(tmp, path); err != nil {
		_ = os.Remove(tmp)
		return fmt.Errorf("activate config: %w", err)
	}
	return nil
}

func maskCredential(v string) string {
	v = strings.TrimSpace(v)
	if len(v) <= 8 {
		return "***"
	}
	return v[:4] + "***" + v[len(v)-4:]
}

// checkPortConflicts 通过短时 bind 探测监听端口是否已被占用
// 探测 IPv4 (0.0.0.0:port) + IPv6 ([::]:port) 两个地址族，任一被占即拒绝。
// 实现要点：使用 net.ListenConfig 不开 SO_REUSEADDR，bind 后立刻 Close 释放。
// 业务语义：1 物理/虚拟主机仅允许部署 1 个 dns-resolver。
// macOS/Linux 行为差异：IPv4 与 IPv6 通配在多数 BSD 内核是不同地址族，两个进程可分别占
// 一个地址族（一个 IPv4 一个 IPv6），所以必须两族都探测才能避免漏报。
func checkPortConflicts(cfg *config.Config) error {
	type portBinding struct {
		name string
		port int
	}
	bindings := []portBinding{
		{"DoH", cfg.Listen.DoH},
		{"DoT", cfg.Listen.DoT},
		{"TCP", cfg.Listen.TCP},
		{"UDP", cfg.Listen.UDP},
	}

	lc := net.ListenConfig{}
	probe := func(addr string) error {
		ln, err := lc.Listen(context.TODO(), "tcp", addr)
		if err != nil {
			return err
		}
		_ = ln.Close()
		return nil
	}

	var conflicts []string
	for _, b := range bindings {
		if b.port == 0 {
			continue
		}
		// IPv4 通配
		if err := probe(fmt.Sprintf("0.0.0.0:%d", b.port)); err != nil {
			conflicts = append(conflicts, fmt.Sprintf("%s[v4]=:%d (%v)", b.name, b.port, err))
		}
		// IPv6 通配（main.go 实际监听用 ":port"，macOS 解析为 IPv6 dual-stack）
		if err := probe(fmt.Sprintf("[::]:%d", b.port)); err != nil {
			conflicts = append(conflicts, fmt.Sprintf("%s[v6]=:%d (%v)", b.name, b.port, err))
		}
	}
	if len(conflicts) > 0 {
		return fmt.Errorf("one resolver per host, ports already in use: %s", strings.Join(conflicts, "; "))
	}
	return nil
}

// registerNodeToConsole 把本次 install 行为上报给 console，
// 触发控制台「已注册」状态展示（不阻塞 install，失败仅打印警告）。
func registerNodeToConsole(cfg *config.Config) error {
	endpoint := strings.TrimRight(cfg.ControlPlane.Endpoint, "/")
	if endpoint == "" {
		return nil
	}
	url := endpoint + "/api/v1/node/nodes/register"

	payload := map[string]any{
		"node_id":      cfg.ControlPlane.NodeID,
		"installed_at": time.Now().UTC().Format(time.RFC3339),
	}
	body, _ := json.Marshal(payload)

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, strings.NewReader(string(body)))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	if t := strings.TrimSpace(cfg.ControlPlane.APIKey); t != "" {
		req.Header.Set("Authorization", "Bearer "+t)
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 300 {
		respBody, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("register API returned %d: %s", resp.StatusCode, string(respBody))
	}
	return nil
}

func printUsage() {
	fmt.Println(`Usage:
  resolver [--config PATH]            Start the dns-resolver daemon
  resolver run [--config PATH]        Same as above
  resolver install [flags]            Write a config.yaml with pre-issued console credentials

Run flags:
  --config PATH    Path to server.yaml (default: configs/server.yaml).
                   Overrides $RESOLVER_CONFIG if both are set.

Environment:
  RESOLVER_CONFIG  Equivalent to --config; used when no flag is given.
                   Useful for Docker / Kubernetes / systemd where flags are awkward.

install flags:
  --console URL   portal-web admin Base URL, e.g. https://console.ocerlink.com
  --node-id ID    Node ID assigned by console, e.g. hk-01
  --api-key KEY   Node API key issued by console (ak_xxx)
  --config PATH   Output config path (default: configs/server.yaml)
  --name NAME     Optional human-friendly node name
  --region CODE   Optional region, e.g. ap-northeast-1
  --country CODE  Optional country code, e.g. JP
  --city NAME     Optional city
  --provider TAG  Optional provider tag, e.g. AWS
  --force         Overwrite existing config file

Example:
  # Use a custom config path
  resolver --config=/etc/dns-resolver/server.yaml

  # Or via env var
  RESOLVER_CONFIG=/etc/dns-resolver/server.yaml resolver

  # Provision a node
  resolver install \
    --console=https://console.ocerlink.com \
    --node-id=hk-01 \
    --api-key=ak_xxx`)
}
