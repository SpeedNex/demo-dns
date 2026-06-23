package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
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
	APIKeyPath  string
	ListenAddr  string
	DNSAddr     string
	HealthToken string
	Force       bool
	Verbose     bool
	// 2026-06-22: --install-dir 参数，用于决定 configs/api_key 落到哪里。
	// 默认基于 GEODNS_HOME > /usr/local/etc/geodns。
	InstallDir string
	// 2026-06-22: 安装完成后是否自动启动节点。
	// 优先级: --start=true > --no-start > --start=false
	// 默认 false(保持向后兼容);geodns-install.sh 包装层默认开 --start。
	Start bool
	// 2026-06-22: 自定义 systemd unit 路径,留空使用默认 /etc/systemd/system/geodns.service
	SystemdUnit string
}

// runInstall 实现 `geodns install` 子命令：
//
//	geodns install \
//	    --server=https://console.ocerlink.com \
//	    --token=xxxxx \
//	    --node-id=xxxxx
//
// 行为：把 console 端预签发的 token + node-id 写入 geodns 配置文件，
// 然后调用 console 的 register 端点签发 api_key（独立文件 configs/api_key）。
func runInstall(args []string) error {
	fs := flag.NewFlagSet("install", flag.ExitOnError)

	var opts installOptions
	fs.StringVar(&opts.Server, "server", "", "portal-web Base URL, e.g. https://console.ocerlink.com (alias: --console)")
	fs.StringVar(&opts.Server, "console", "", "")
	fs.StringVar(&opts.NodeID, "node-id", "", "Node ID assigned by console")
	fs.StringVar(&opts.Token, "token", "", "Node token issued by console")
	fs.StringVar(&opts.ConfigPath, "config", "", "Output geodns config.yaml path (default: <install-dir>/configs/config.yaml)")
	fs.StringVar(&opts.APIKeyPath, "api-key", "", "Output api_key file path (default: <install-dir>/configs/api_key)")
	fs.StringVar(&opts.ListenAddr, "listen-addr", ":5354", "HTTP listen address")
	fs.StringVar(&opts.DNSAddr, "dns-addr", ":53", "DNS listen address")
	fs.StringVar(&opts.HealthToken, "health-token", "", "Internal health-view token (shared with portal-web)")
	// 2026-06-22: --force 已废弃（install 始终覆盖配置），保留以兼容旧脚本，忽略即可。
	fs.BoolVar(&opts.Force, "force", false, "Deprecated: install now always overwrites")
	fs.BoolVar(&opts.Verbose, "verbose", false, "Enable verbose logging (request/response details)")
	// 2026-06-22 NEW: --install-dir 决定 configs/ 落点
	fs.StringVar(&opts.InstallDir, "install-dir", "", "Base directory for configs/ and api_key (default: $GEODNS_HOME or ~/.geodns)")
	// 2026-06-22 NEW: --start/--no-start 控制安装完成后是否自动拉起节点。
	// systemd 优先(systemd 不存在/无权限写 unit 时降级为 nohup 后台进程)。
	// 三态解析：先看 --start 显式值,再看 --no-start,最后回退到 false(向后兼容)。
	startFlag := fs.Bool("start", false, "After install, automatically start the node (systemd preferred, fallback to nohup)")
	noStartFlag := fs.Bool("no-start", false, "Disable auto-start even if --start is set (alias for safety in scripts)")
	fs.StringVar(&opts.SystemdUnit, "systemd-unit", "", "systemd unit file path (default: /etc/systemd/system/geodns.service)")

	if err := fs.Parse(args); err != nil {
		return err
	}

	// 2026-06-22: 解析 --start/--no-start,显式 --no-start 永远关闭
	if *noStartFlag {
		opts.Start = false
	} else {
		opts.Start = *startFlag
	}

	// 2026-06-22: 统一用 stderr 输出 install 日志，方便用户区分 stdout vs 错误
	log.SetOutput(os.Stderr)
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[install] ")

	log.Printf("starting install: server=%s node_id=%s listen=%s dns=%s",
		mask(opts.Server), mask(opts.NodeID), opts.ListenAddr, opts.DNSAddr)

	if err := validateGeodnsInstallOptions(&opts); err != nil {
		fs.Usage()
		return err
	}

	// 2026-06-22 fix: 派生绝对路径，避免 CWD 不同导致 configs 落到任意位置
	installDir := resolveInstallDir(opts.InstallDir)
	configPath := opts.ConfigPath
	if configPath == "" {
		configPath = filepath.Join(installDir, "configs", "config.yaml")
	}
	apiKeyPath := opts.APIKeyPath
	if apiKeyPath == "" {
		apiKeyPath = filepath.Join(installDir, "configs", "api_key")
	}
	opts.ConfigPath = configPath
	opts.APIKeyPath = apiKeyPath

	log.Printf("install_dir=%s config=%s api_key=%s", installDir, configPath, apiKeyPath)

	cfg := buildGeodnsConfig(&opts)

	// 2026-06-22: 一机一实例守卫 — install 时探测 HTTP/DNS 监听端口是否已被占用
	// 同机已有 geodns 进程时直接拒绝，强制 1 物理/虚拟主机仅部署 1 个 geodns
	if err := checkGeodnsPortConflicts(cfg); err != nil {
		return fmt.Errorf("port check failed: %w", err)
	}

	if err := writeGeodnsConfig(opts.ConfigPath, cfg, opts.Force); err != nil {
		return fmt.Errorf("write config failed: %w", err)
	}

	log.Printf("config written to %s", opts.ConfigPath)
	fmt.Printf("✔ config written to %s\n", opts.ConfigPath)
	fmt.Printf("  server        = %s\n", opts.Server)
	fmt.Printf("  node_id       = %s\n", opts.NodeID)
	fmt.Printf("  listen_addr   = %s\n", opts.ListenAddr)
	fmt.Printf("  dns_addr      = %s\n", opts.DNSAddr)

	// 2026-06-22: install 完成后调用控制面 register API：
	//   1) 告知 console 节点已注册
	//   2) **同时获取并缓存 api_key**（仅此一次返回明文）
	// register 失败不阻塞 install（配置已写入），仅打印警告。
	if regErr := registerNodeToConsole(cfg, opts.APIKeyPath, opts.Verbose); regErr != nil {
		log.Printf("console register FAILED: %v", regErr)
		fmt.Printf("⚠ console register failed: %v (config was still written, run `geodns` to start)\n", regErr)
	} else {
		log.Printf("console register: success")
		fmt.Println("✔ console register: success")
	}

	// 2026-06-22 NEW: --start 开启时自动拉起节点。
	// 顺序：systemd(写 unit + daemon-reload + enable --now) → 失败降级 nohup 后台进程。
	// 任一方式成功都打印 ✔ 并跳过后续提示;完全失败时打 ⚠ 保留手动启动指引。
	if opts.Start {
		if startErr := startService(&opts); startErr != nil {
			log.Printf("auto-start FAILED: %v", startErr)
			fmt.Printf("⚠ auto-start failed: %v\n", startErr)
		}
	} else {
		fmt.Println("Next: run `geodns` to start the node (or re-run with --start).")
	}
	return nil
}

// registerNodeToConsole 把本次 install 行为上报给 console，
// 触发控制台「已注册」状态展示（不阻塞 install，失败仅打印警告）。
// 2026-06-21: register 接口会**同时返回明文 api_key**，本函数负责把 api_key
// 缓存到独立文件（权限 0600），节点启动时优先从这里读取鉴权。
// 2026-06-22: 新增 apiKeyPath 参数 + 详细 debug 日志 + body node_id 用 cfg.NodeID()。
func registerNodeToConsole(cfg *config.Config, apiKeyPath string, verbose bool) error {
	server := resolveConsoleBaseURL(cfg.ConsoleHealthURL())
	if server == "" {
		log.Printf("register: no console URL, skipping")
		return nil // 无控制面时跳过
	}
	url := server + "/api/v1/node/geodns/register"

	nodeID := cfg.NodeID()
	if nodeID == "" {
		// 2026-06-22 fix: 旧版 install 不会把 opts.NodeID 写入 cfg（旧 cfg 没 NodeID 字段），
		// 此处 fallback 用 token（兼容老格式 config），新 config 必有 NodeID。
		nodeID = cfg.NodeToken()
		log.Printf("register: cfg.NodeID() empty, fallback to token (legacy config?)")
	}

	payload := map[string]any{
		"node_id":      nodeID,
		"installed_at": time.Now().UTC().Format(time.RFC3339),
		"listen_addr":  cfg.Server.ListenAddr,
	}
	body, _ := json.Marshal(payload)

	log.Printf("register: POST %s body=%s", url, previewBody(body, 200))
	if verbose {
		fmt.Printf("  → register: POST %s\n", url)
		fmt.Printf("    body: %s\n", string(body))
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, strings.NewReader(string(body)))
	if err != nil {
		log.Printf("register: build request failed: %v", err)
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	if t := cfg.NodeToken(); t != "" {
		// 2026-06-22 fix: 必须发原 token 给服务端，
		// 之前写成 mask(t) 会把 token 脱敏后塞进 header，导致 401。
		req.Header.Set("Authorization", "Bearer "+t)
	}

	start := time.Now()
	resp, err := http.DefaultClient.Do(req)
	latency := time.Since(start)
	if err != nil {
		log.Printf("register: HTTP error after %s: %v", latency, err)
		return fmt.Errorf("HTTP error: %w", err)
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)
	log.Printf("register: HTTP %d in %s body=%s", resp.StatusCode, latency, previewBody(respBody, 200))
	if verbose {
		fmt.Printf("  ← register: HTTP %d in %s\n", resp.StatusCode, latency)
		fmt.Printf("    body: %s\n", string(respBody))
	}

	if resp.StatusCode >= 300 {
		return fmt.Errorf("register API returned %d: %s", resp.StatusCode, previewBody(respBody, 300))
	}

	// 2026-06-21: 解析 register 返回的 api_key 并缓存。
	// 老版本服务端（迁移未完成）不会返回 api_key 字段，此时跳过缓存。
	var result struct {
		Data struct {
			APIKey     string `json:"api_key"`
			APIKeyPath string `json:"api_key_path"`
		} `json:"data"`
	}
	if err := json.Unmarshal(respBody, &result); err != nil {
		// 解析失败不算致命错误（可能是老服务端），仅警告
		log.Printf("register: parse response failed (non-fatal): %v", err)
		return nil
	}
	if result.Data.APIKey == "" {
		// 老服务端未签发 api_key，跳过缓存（节点继续用 token 鉴权）
		log.Printf("register: no api_key in response (legacy server), skip caching")
		return nil
	}

	// 2026-06-22 fix: 用绝对路径写 api_key，避免 CWD 漂移
	finalPath := apiKeyPath
	if finalPath == "" {
		finalPath = "configs/api_key"
	}
	if !filepath.IsAbs(finalPath) {
		abs, _ := filepath.Abs(finalPath)
		finalPath = abs
	}
	dir := filepath.Dir(finalPath)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		log.Printf("register: mkdir %s failed: %v", dir, err)
		return fmt.Errorf("create api_key dir: %w", err)
	}
	if err := os.WriteFile(finalPath, []byte(result.Data.APIKey), 0o600); err != nil {
		log.Printf("register: write %s failed: %v", finalPath, err)
		return fmt.Errorf("write api_key to %s: %w", finalPath, err)
	}
	log.Printf("register: api_key cached to %s (size=%d bytes)", finalPath, len(result.Data.APIKey))
	fmt.Printf("✔ api_key cached to %s\n", finalPath)
	return nil
}

// resolveConsoleBaseURL 从 console_health_url 提取 base url。
// console_health_url 形如 "https://console.ocerlink.com/api/v1/internal/geodns/health-view"，
// 需要截断到 "/api/v1" 之前，只保留 scheme + host。
func resolveConsoleBaseURL(healthURL string) string {
	healthURL = strings.TrimRight(healthURL, "/")
	if healthURL == "" {
		return ""
	}
	if idx := strings.Index(healthURL, "/api/v1/"); idx > 0 {
		return healthURL[:idx]
	}
	return healthURL
}

// resolveInstallDir 决定 configs/ 落点。优先级：
//
//  1. --install-dir 显式传
//  2. $GEODNS_HOME 环境变量
//  3. /usr/local/etc/geodns （默认安装位置）
//
// 2026-06-22: 不再用 CWD 相对路径，避免 CWD 漂移导致文件落错位置。
func resolveInstallDir(explicit string) string {
	if explicit != "" {
		return absPath(explicit)
	}
	if env := os.Getenv("GEODNS_HOME"); env != "" {
		return absPath(env)
	}
	return "/usr/local/etc/geodns"
}

func absPath(p string) string {
	if filepath.IsAbs(p) {
		return p
	}
	abs, err := filepath.Abs(p)
	if err != nil {
		return p
	}
	return abs
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
			Token:  opts.Token,
			NodeID: opts.NodeID, // 2026-06-22: 把 console 预签发的 node-id 写入 cfg
			// 2026-06-22 fix: APIEndpoint 改为 base URL（不带 /api/v1 前缀）。
			// 业务 client（HeartbeatClient / Config Client）自身拼接 /api/v1/...，
			// 之前用 "/api/v1/internal" 会导致 .../internal/api/v1/node/heartbeat 双重前缀 404。
			APIEndpoint: server,
			APIKeyPath:  opts.APIKeyPath, // 2026-06-22: 把绝对路径写入 cfg，server 启动用
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

// mask 脱敏敏感字段（server URL 保留前 30 字符 + ...；token 只保留前后 4 字符）。
func mask(s string) string {
	if s == "" {
		return "<empty>"
	}
	if len(s) <= 12 {
		return s[:4] + "***"
	}
	return s[:4] + "***" + s[len(s)-4:]
}

// previewBody 截断 body 用于日志。
func previewBody(b []byte, max int) string {
	if len(b) <= max {
		return string(b)
	}
	return string(b[:max]) + "...(truncated " + fmt.Sprint(len(b)-max) + " bytes)"
}

// =============================================================================
//  2026-06-22 NEW: 安装完成后自动启动节点
// =============================================================================

// systemdUnitTemplate 渲染生成的 geodns.service。
// 关键点：
//   - Type=simple 配合 Restart=on-failure,崩了会自动拉起
//   - ReadWritePaths 限定只能写 configs/ 目录,避免 sandbox 把日志/缓冲写到只读
//   - LimitNOFILE=65536 保证 DNS 高并发场景不爆 fd
const systemdUnitTemplate = `[Unit]
Description=OcerDNS GeoDNS Node ({{.NodeID}})
Documentation=https://test-dns.ocerlinkdata.com
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart={{.Binary}} --config={{.Config}}
Restart=on-failure
RestartSec=5s
LimitNOFILE=65536

# sandbox: 禁止提权,只放开 configs 目录
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true
ReadWritePaths={{.ConfigDir}}

[Install]
WantedBy=multi-user.target
`

// systemdUnitData 渲染模板需要的数据
type systemdUnitData struct {
	NodeID    string
	Binary    string
	Config    string
	ConfigDir string
}

// startService 安装完成后拉起节点。
// 策略：systemd 优先(写 unit + daemon-reload + enable --now)→
//
//	systemd 不可用/无权限时降级为 nohup 后台进程(写 PID 文件)。
//
// 返回 error 时说明两种方式都失败,调用方应打 ⚠ 提示用户手动启动。
func startService(opts *installOptions) error {
	// 1) 尝试 systemd
	if err := tryStartViaSystemd(opts); err != nil {
		log.Printf("systemd path unavailable: %v, falling back to nohup", err)
		return startViaNohup(opts)
	}
	return nil
}

// tryStartViaSystemd 尝试把节点装成 systemd 服务并 enable --now。
// 失败场景（不视为 fatal）:
//   - systemctl 不存在 (macOS / 容器里无 systemd)
//   - /etc/systemd/system 不可写 (非 root / 容器 readonly)
//   - systemd-run 失败（systemd 用户实例未启动等）
func tryStartViaSystemd(opts *installOptions) error {
	if _, err := exec.LookPath("systemctl"); err != nil {
		return fmt.Errorf("systemctl not found: %w", err)
	}

	unitPath := opts.SystemdUnit
	if unitPath == "" {
		unitPath = "/etc/systemd/system/geodns.service"
	}
	// ReadWritePaths 模板里要的是 configs 目录的父目录
	configDir := filepath.Dir(opts.ConfigPath)

	tpl := systemdUnitTemplate
	rendered := strings.NewReplacer(
		"{{.NodeID}}", opts.NodeID,
		"{{.Binary}}", "/usr/local/bin/geodns",
		"{{.Config}}", opts.ConfigPath,
		"{{.ConfigDir}}", configDir,
	).Replace(tpl)

	// 先 dry-write 到 /tmp,确认模板渲染没问题再写正式路径
	tmpUnit := filepath.Join(os.TempDir(), "geodns.service.tmp")
	if err := os.WriteFile(tmpUnit, []byte(rendered), 0o644); err != nil {
		return fmt.Errorf("render unit template: %w", err)
	}

	// 尝试写正式 unit。失败（非 root / 只读 fs）就走降级路径。
	if err := copyFile(unitPath, tmpUnit, 0o644); err != nil {
		return fmt.Errorf("write unit %s: %w (need root)", unitPath, err)
	}
	_ = os.Remove(tmpUnit)

	// daemon-reload + enable --now。任何一步失败都放弃 systemd 路径。
	for _, args := range [][]string{
		{"daemon-reload"},
		{"enable", "geodns.service"},
		{"start", "geodns.service"},
	} {
		cmd := exec.Command("systemctl", args...)
		out, err := cmd.CombinedOutput()
		if err != nil {
			return fmt.Errorf("systemctl %s failed: %w (%s)", strings.Join(args, " "), err, strings.TrimSpace(string(out)))
		}
	}

	log.Printf("systemd: unit written to %s, geodns.service enabled and started", unitPath)
	fmt.Printf("✔ systemd: geodns.service installed and started\n")
	fmt.Printf("    systemctl status geodns    # check status\n")
	fmt.Printf("    journalctl -u geodns -f    # tail logs\n")
	return nil
}

// startViaNohup 没有 systemd 时的降级方案。
// 把 geodns 用 setsid+nohup 拉成后台进程,写 PID 文件方便后续 stop。
// 不再返回 error 后让用户手动启动,降级路径尽可能兜住。
func startViaNohup(opts *installOptions) error {
	binary, err := os.Executable()
	if err != nil {
		return fmt.Errorf("locate self binary: %w", err)
	}

	pidFile := filepath.Join(filepath.Dir(opts.ConfigPath), "geodns.pid")
	logFile := filepath.Join(filepath.Dir(opts.ConfigPath), "geodns.log")

	// 避免重复启动:已有 PID 文件且进程活着就直接复用
	if pid, perr := readPIDFile(pidFile); perr == nil && processAlive(pid) {
		log.Printf("nohup: existing geodns pid=%d alive, skip", pid)
		fmt.Printf("✔ geodns already running (pid=%d)\n", pid)
		return nil
	}

	cmd := exec.Command(binary, "--config="+opts.ConfigPath)
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	out, err := os.OpenFile(logFile, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0o640)
	if err != nil {
		return fmt.Errorf("open log file %s: %w", logFile, err)
	}
	defer out.Close()
	cmd.Stdout = out
	cmd.Stderr = out

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("spawn geodns: %w", err)
	}
	// 父进程立即释放,让 systemd-style init / 用户 shell 接管孤儿进程
	_ = cmd.Process.Release()

	if err := os.WriteFile(pidFile, []byte(fmt.Sprintf("%d", cmd.Process.Pid)), 0o644); err != nil {
		// PID 写不进去不影响服务运行,只警告
		log.Printf("nohup: write pid file %s failed: %v", pidFile, err)
	}

	// 简单探活 1s,确保进程没秒崩
	time.Sleep(1 * time.Second)
	if !processAlive(cmd.Process.Pid) {
		return fmt.Errorf("geodns exited immediately, see log: %s", logFile)
	}

	log.Printf("nohup: geodns started pid=%d log=%s", cmd.Process.Pid, logFile)
	fmt.Printf("✔ geodns started in background (pid=%d)\n", cmd.Process.Pid)
	fmt.Printf("    log:    %s\n", logFile)
	fmt.Printf("    pid:    %s\n", pidFile)
	fmt.Printf("    stop:   kill $(cat %s)\n", pidFile)
	return nil
}

// readPIDFile 从 PID 文件读取整数 PID,失败返回 0 + error
func readPIDFile(path string) (int, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return 0, err
	}
	pid, err := strconv.Atoi(strings.TrimSpace(string(data)))
	if err != nil {
		return 0, err
	}
	return pid, nil
}

// processAlive 探活 /proc/<pid>。Linux only;macOS 也兼容（/proc 不存在会返回 false）。
func processAlive(pid int) bool {
	if pid <= 0 {
		return false
	}
	proc, err := os.FindProcess(pid)
	if err != nil {
		return false
	}
	// Unix: Signal 0 不会真发信号,仅做权限/存在性检查
	return proc.Signal(syscall.Signal(0)) == nil
}

// copyFile 简单文件复制(不走 io.Copy 是因为要控制 mode)
func copyFile(dst, src string, mode os.FileMode) error {
	data, err := os.ReadFile(src)
	if err != nil {
		return err
	}
	return os.WriteFile(dst, data, mode)
}
