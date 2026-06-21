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

	"ocer-dns/geodns/internal/config"
)

// installOptions 保存 `geodns install` 子命令的入参
type installOptions struct {
	Server      string
	NodeID      string
	Token       string
	ConfigPath  string
	ListenAddr  string
	DNSAddr     string
	HealthToken string
	Force       bool
}

// runInstall 实现 `geodns install` 子命令：
//
//	geodns install \
//	    --server=https://console.ocerlink.com \
//	    --token=xxxxx \
//	    --node-id=xxxxx
//
// 行为：把 console 端预签发的 token 写入 geodns 配置文件
func runInstall(args []string) error {
	fs := flag.NewFlagSet("install", flag.ExitOnError)

	var opts installOptions
	fs.StringVar(&opts.Server, "server", "", "portal-web Base URL, e.g. https://console.ocerlink.com (alias: --console)")
	fs.StringVar(&opts.Server, "console", "", "")
	fs.StringVar(&opts.NodeID, "node-id", "", "Node ID assigned by console")
	fs.StringVar(&opts.Token, "token", "", "Node token issued by console")
	fs.StringVar(&opts.ConfigPath, "config", "configs/config.yaml", "Output geodns config.yaml path")
	fs.StringVar(&opts.ListenAddr, "listen-addr", ":5354", "HTTP listen address")
	fs.StringVar(&opts.DNSAddr, "dns-addr", ":53", "DNS listen address")
	fs.StringVar(&opts.HealthToken, "health-token", "", "Internal health-view token (shared with portal-web)")
	// 2026-06-22: --force 已废弃（install 始终覆盖配置），保留以兼容旧脚本，忽略即可。
	fs.BoolVar(&opts.Force, "force", false, "Deprecated: install now always overwrites")

	if err := fs.Parse(args); err != nil {
		return err
	}

	if err := validateGeodnsInstallOptions(&opts); err != nil {
		fs.Usage()
		return err
	}

	cfg := buildGeodnsConfig(&opts)

	// 2026-06-22: 一机一实例守卫 — install 时探测 HTTP/DNS 监听端口是否已被占用
	// 同机已有 geodns 进程时直接拒绝，强制 1 物理/虚拟主机仅部署 1 个 geodns
	if err := checkGeodnsPortConflicts(cfg); err != nil {
		return fmt.Errorf("port check failed: %w", err)
	}

	if err := writeGeodnsConfig(opts.ConfigPath, cfg, opts.Force); err != nil {
		return fmt.Errorf("write config failed: %w", err)
	}

	fmt.Printf("✔ config written to %s\n", opts.ConfigPath)
	fmt.Printf("  server        = %s\n", opts.Server)
	fmt.Printf("  node_id       = %s\n", opts.NodeID)
	fmt.Printf("  listen_addr   = %s\n", opts.ListenAddr)
	fmt.Printf("  dns_addr      = %s\n", opts.DNSAddr)

	// 2026-06-22: install 完成后调用控制面 register API，告知 console 节点已注册。
	// 即使 register 失败也不阻塞 install（配置已写入），但会在 stdout 标注警告。
	if regErr := registerNodeToConsole(cfg); regErr != nil {
		fmt.Printf("⚠ console register failed: %v (config was still written, run `geodns` to start)\n", regErr)
	} else {
		fmt.Println("✔ console register: success")
	}
	fmt.Println("Next: run `geodns` to start the node.")
	return nil
}

// registerNodeToConsole 把本次 install 行为上报给 console，
// 触发控制台「已注册」状态展示（不阻塞 install，失败仅打印警告）。
func registerNodeToConsole(cfg *config.Config) error {
	server := strings.TrimRight(cfg.NodeAPIEndpoint(), "/")
	if server == "" {
		return nil // 无控制面时跳过
	}
	url := server + "/api/v1/node/nodes/register"

	payload := map[string]any{
		"node_id":     cfg.NodeToken(), // 仅作标识
		"installed_at": time.Now().UTC().Format(time.RFC3339),
		"listen_addr":  cfg.Server.ListenAddr,
	}
	body, _ := json.Marshal(payload)

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, strings.NewReader(string(body)))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	if t := cfg.NodeToken(); t != "" {
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

func validateGeodnsInstallOptions(opts *installOptions) error {
	missing := make([]string, 0, 4)
	if strings.TrimSpace(opts.Server) == "" {
		missing = append(missing, "--server")
	}
	if strings.TrimSpace(opts.NodeID) == "" {
		missing = append(missing, "--node-id")
	}
	if strings.TrimSpace(opts.Token) == "" {
		missing = append(missing, "--token")
	}
	if len(missing) > 0 {
		return fmt.Errorf("missing required flags: %s", strings.Join(missing, ", "))
	}

	lower := strings.ToLower(strings.TrimSpace(opts.Server))
	if !strings.HasPrefix(lower, "http://") && !strings.HasPrefix(lower, "https://") {
		return fmt.Errorf("--server must be a http(s) URL, got %q", opts.Server)
	}
	return nil
}

func buildGeodnsConfig(opts *installOptions) *config.Config {
	server := strings.TrimRight(opts.Server, "/")

	// 2026-06-22 P0#3: 与 portal-web 约定 node token 同时作为 health-view token。
	// 1) install 时不传 --health-token 时，回退到 --token，保证 healthview.Client
	//    请求 health-view 时能通过 shared.token:internal 中间件。
	// 2) HMAC 签名密钥：与 node.Client 一致，healthview.Client 也会回退到 token。
	healthToken := strings.TrimSpace(opts.HealthToken)
	if healthToken == "" {
		healthToken = strings.TrimSpace(opts.Token)
	}

	return &config.Config{
		Server: config.ServerConfig{
			ListenAddr:         opts.ListenAddr,
			ListenDNSAddr:      opts.DNSAddr,
			ConsoleHealthURL:   server + "/api/v1/internal/geodns/health-view",
			ConsoleHealthToken: healthToken,
			RefreshInterval:    "15s",
			RequestTimeoutSec:  5,
		},
		Routing: config.RoutingConfig{
			GlobalFallbackRegion: "global",
		},
		Node: config.NodeConfig{
			Token:       opts.Token,
			APIEndpoint: server + "/api/v1/internal",
		},
	}
}

func writeGeodnsConfig(path string, cfg *config.Config, force bool) error {
	_ = force // 2026-06-22: 取消"配置已存在则拒绝"检测，install 始终覆盖。

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

// checkGeodnsPortConflicts 通过短时 bind 探测 HTTP 监听端口是否已被占用
// 探测 IPv4 + IPv6 两个地址族，任一被占即拒绝。
// 业务语义：1 物理/虚拟主机仅允许部署 1 个 geodns。
//
// 注意：cfg.Server.ListenDNSAddr (UDP/TCP :53) 暂不探测 — 当前 geodns server.go
// 未实际启动 DNS server（ListenDNSAddr 字段保留但未读取），且 53 是特权端口，
// install 探测时会与系统 DNS 服务 / mDNSResponder 等冲突，导致误报。
func checkGeodnsPortConflicts(cfg *config.Config) error {
	type portBinding struct {
		name string
		addr string
	}
	bindings := []portBinding{
		{"HTTP", cfg.Server.ListenAddr},
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

	// 把 ":5354" / "0.0.0.0:5354" / "[::]:5354" 形式展开为 IPv4/IPv6 多个具体地址
	expandAddrs := func(addr string) []string {
		switch {
		case strings.HasPrefix(addr, ":") && !strings.HasPrefix(addr, "[::]"):
			port := strings.TrimPrefix(addr, ":")
			return []string{"0.0.0.0:" + port, "[::]:" + port}
		case addr == "0.0.0.0" || addr == "0.0.0.0:0":
			return []string{"0.0.0.0"}
		default:
			return []string{addr}
		}
	}

	var conflicts []string
	for _, b := range bindings {
		if b.addr == "" {
			continue
		}
		for _, a := range expandAddrs(b.addr) {
			if err := probe(a); err != nil {
				conflicts = append(conflicts, fmt.Sprintf("%s=%s (%v)", b.name, a, err))
			}
		}
	}
	if len(conflicts) > 0 {
		return fmt.Errorf("one geodns per host, ports already in use: %s", strings.Join(conflicts, "; "))
	}
	return nil
}