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
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"

	"gopkg.in/yaml.v3"

	"ocer-dns/dns-resolver/internal/config"
)

// 终端颜色支持（非终端输出时静默降级为空字符串）
var (
	redFg    string
	greenFg  string
	yellowFg string
	resetSty string
)

func init() {
	if fi, _ := os.Stdout.Stat(); fi != nil && fi.Mode()&os.ModeCharDevice != 0 {
		redFg = "\033[0;31m"
		greenFg = "\033[0;32m"
		yellowFg = "\033[0;33m"
		resetSty = "\033[0m"
	}
}

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
	// 2026-06-22: 安装完成后是否自动启动节点。
	// 优先级: --start=true > --no-start > --start=false
	// 默认 false(保持向后兼容);dns-resolver-install.sh 包装层默认开 --start。
	Start bool
	// 2026-06-22: 自定义 systemd unit 路径,留空使用默认 /etc/systemd/system/dns-resolver.service
	SystemdUnit string
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
	fs.StringVar(&opts.ConfigPath, "config", "/usr/local/etc/dns-resolver/server.yaml", "Output resolver config.yaml path")
	fs.StringVar(&opts.Name, "name", "", "Optional human-friendly node name override")
	fs.StringVar(&opts.Region, "region", "", "Optional region code, e.g. ap-northeast-1")
	fs.StringVar(&opts.Country, "country", "", "Optional country code, e.g. JP")
	fs.StringVar(&opts.City, "city", "", "Optional city, e.g. Tokyo")
	fs.StringVar(&opts.Provider, "provider", "", "Optional provider tag, e.g. AWS")
	// 2026-06-22 NEW: --start/--no-start 控制安装完成后是否自动拉起节点。
	// systemd 优先(systemd 不存在/无权限写 unit 时降级为 nohup 后台进程)。
	// 三态解析:先看 --start 显式值,再看 --no-start,最后回退到 false(向后兼容)。
	startFlag := fs.Bool("start", false, "After install, automatically start the node (systemd preferred, fallback to nohup)")
	noStartFlag := fs.Bool("no-start", false, "Disable auto-start even if --start is set (alias for safety in scripts)")
	fs.StringVar(&opts.SystemdUnit, "systemd-unit", "", "systemd unit file path (default: /etc/systemd/system/dns-resolver.service)")

	if err := fs.Parse(args); err != nil {
		return err
	}

	// 2026-06-22: 解析 --start/--no-start,显式 --no-start 永远关闭
	if *noStartFlag {
		opts.Start = false
	} else {
		opts.Start = *startFlag
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

	if err := writeConfigAtomic(opts.ConfigPath, cfg); err != nil {
		return fmt.Errorf("write config failed: %w", err)
	}

	// 2026-06-26: install 时清理 profile 缓存目录，确保从 portal 拉取全新配置。
	// 场景：数据库重装后版本号重置，旧缓存版本号可能高于服务端，导致配置不更新。
	// install 是节点"重新开始"的信号，此时清缓存是最合理的安全边界。
	cacheDir := cfg.ControlPlane.ProfilesCacheDir
	if cacheDir == "" {
		cacheDir = cfg.ControlPlane.ProfilesPath
	}
	if cacheDir != "" {
		if err := os.RemoveAll(cacheDir); err != nil {
			fmt.Printf("⚠ clear cache dir %s: %v (continuing anyway)\n", cacheDir, err)
		} else {
			fmt.Printf("✔ cache cleared: %s\n", cacheDir)
		}
		if err := os.MkdirAll(cacheDir, 0o755); err != nil {
			fmt.Printf("⚠ create cache dir %s: %v (continuing anyway)\n", cacheDir, err)
		}
	}

	// 2026-06-22: 预创建 log buffer 目录，避免 systemd-tmpfiles 清理 /tmp
	// 默认路径 /var/lib/ocer-dns/log-buffer，macOS 需在 yaml 改写
	if cfg.Logging.BufferPath != "" {
		if err := os.MkdirAll(cfg.Logging.BufferPath, 0o755); err != nil {
			return fmt.Errorf("create log buffer dir %s: %w (hint: run as root or override buffer_path in yaml)", cfg.Logging.BufferPath, err)
		}
	}

	fmt.Printf("✔ config written to %s\n", opts.ConfigPath)
	fmt.Printf("  console     = %s\n", cfg.ControlPlane.Endpoint)
	fmt.Printf("  node_id     = %s\n", cfg.ControlPlane.NodeID)
	fmt.Printf("  api_key     = (kept in %s, not in yaml)\n", cfg.ControlPlane.APIKeyPath)
	fmt.Printf("  log_buf     = %s\n", cfg.Logging.BufferPath)

	// 2026-06-22: install 完成后调用控制面 register API，告知 console 节点已注册。
	// register 失败不阻塞 install（配置已写入），仅打印警告。
	// register 成功时可能返回 dns_domain，用于 certbot 自动配置 TLS 证书。
	dnsDomain, regErr := registerNodeToConsole(cfg)
	if regErr != nil {
		fmt.Printf("⚠ console register failed: %v (config was still written, run `resolver` to start)\n", regErr)
	} else if dnsDomain != "" {
		fmt.Printf("✔ console register: success (dns_domain=%s)\n", dnsDomain)

		// 更新配置中的 dns_domain
		cfg.ControlPlane.DNSDomain = dnsDomain
		if err := writeConfigAtomic(opts.ConfigPath, cfg); err != nil {
			fmt.Printf("⚠ update config with dns_domain failed: %v\n", err)
		}

		// 自动安装 certbot 并申请 Let's Encrypt 证书，dns-resolver 直连 443
		if certErr := provisionCertbot(cfg, opts.ConfigPath, dnsDomain); certErr != nil {
			fmt.Printf("%s⚠ 证书自动配置失败%s: %v（DoH/DoT/DoQ 将使用自签名证书兜底）\n", redFg, resetSty, certErr)
		} else {
			ensureCertbotRenewHook()
			fmt.Println("✔ certbot: Let's Encrypt certificate configured")
		}
	} else {
		fmt.Println("✔ console register: success (no dns_domain, skip certbot auto-setup)")
	}

	// 2026-06-29: 安装结束后，始终将证书路径指向 Let's Encrypt 标准位置。
	// 即使本次 certbot 自动配置失败，只要之前签发过证书，仍能正确加载。
	// 避免每次重装后 tls_cert_file/tls_key_file 被默认空值覆盖导致自签名证书。
	if dnsDomain != "" {
		certDir := fmt.Sprintf("/etc/letsencrypt/live/%s", dnsDomain)
		if _, statErr := os.Stat(certDir + "/fullchain.pem"); statErr == nil {
			cfg.Listen.DoH = 443
			cfg.Listen.TLSCertFile = certDir + "/fullchain.pem"
			cfg.Listen.TLSKeyFile = certDir + "/privkey.pem"
			if err := writeConfigAtomic(opts.ConfigPath, cfg); err != nil {
				fmt.Printf("%s⚠ update tls cert paths failed: %v%s\n", redFg, err, resetSty)
			} else {
				fmt.Printf("✔ tls: certificate paths set to %s\n", certDir)
			}
		} else {
			fmt.Printf("⚠ cert files not found at %s, run certbot manually\n", certDir)
		}
	}

	// 2026-06-22 NEW: --start 开启时自动拉起节点。
	// 顺序:systemd(写 unit + daemon-reload + enable --now) → 失败降级 nohup 后台进程。
	// 任一方式成功都打印 ✔;完全失败时打 ⚠ 保留手动启动指引。
	if opts.Start {
		if startErr := startService(&opts); startErr != nil {
			fmt.Printf("⚠ auto-start failed: %v\n", startErr)
		}
	} else {
		fmt.Println("Next: run `resolver` to start the node (or re-run with --start).")
	}
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

// buildInstalledConfig 在 config.Default() 的基础上覆盖控制面凭据和节点标识。
// 2026-06-24: api_key 不再写进 yaml,只指向 api_key_path 文件 — 凭据单一来源,
// 避免双源不一致导致 401。
func buildInstalledConfig(opts *installOptions) *config.Config {
	cfg := config.Default()

	cfg.ControlPlane.Endpoint = strings.TrimRight(opts.Console, "/")
	cfg.ControlPlane.APIKey = "" // 留空,凭据走 api_key_path 文件
	cfg.ControlPlane.NodeID = strings.TrimSpace(opts.NodeID)

	// 2026-06-22: 把 api_key 缓存路径固定为 config 同目录下的绝对路径,
	// 避免 systemd / nohup 启动时 CWD=/ 找不到 CWD-相对的 "configs/api_key"
	// 而 fallback 到 yaml 中旧格式的 ocnd_ token (被服务端 401 拒掉)。
	if cfg.ControlPlane.APIKeyPath == "" {
		cfgDir := filepath.Dir(opts.ConfigPath)
		if !filepath.IsAbs(cfgDir) {
			if wd, err := os.Getwd(); err == nil {
				cfgDir = filepath.Join(wd, cfgDir)
			}
		}
		cfg.ControlPlane.APIKeyPath = filepath.Join(cfgDir, "api_key")
	}

	// 2026-06-24: 把用户传入的 --api-key / --token 换出来的 key
	// 立即写入 api_key_path 文件 — install 完成后节点启动即可使用。
	// register 接口若返回新 key,会再次覆盖。
	if k := strings.TrimSpace(opts.APIKey); k != "" {
		if err := writeAPIKeyFile(cfg.ControlPlane.APIKeyPath, k); err != nil {
			// 写入失败不阻塞 install,让 register 阶段补救
			fmt.Printf("⚠ write api_key to %s failed: %v\n", cfg.ControlPlane.APIKeyPath, err)
		}
	}

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
// 2026-06-22: install 始终覆盖,无需 force 参数。
func writeConfigAtomic(path string, cfg *config.Config) error {
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
// 2026-06-21: register 接口会**同时返回明文 api_key**，本函数负责把 api_key
// 缓存到独立文件 configs/api_key（权限 0600），节点启动时优先从这里读取鉴权。
// 2026-06-23: 返回值扩充为 (dnsDomain, error)，dns_domain 用于 certbot 自动 TLS 配置。
func registerNodeToConsole(cfg *config.Config) (dnsDomain string, err error) {
	endpoint := strings.TrimRight(cfg.ControlPlane.Endpoint, "/")
	if endpoint == "" {
		return "", nil
	}
	url := endpoint + "/api/v1/node/dns-resolver/register"

	payload := map[string]any{
		"node_id":      cfg.ControlPlane.NodeID,
		"installed_at": time.Now().UTC().Format(time.RFC3339),
	}
	body, _ := json.Marshal(payload)

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, strings.NewReader(string(body)))
	if err != nil {
		return "", err
	}
	req.Header.Set("Content-Type", "application/json")
	// 2026-06-24: 凭据已从 yaml 移到 api_key_path 文件,直接从文件读 token。
	if t := readAPIKeyFile(cfg.ControlPlane.APIKeyPath); t != "" {
		req.Header.Set("Authorization", "Bearer "+t)
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 300 {
		respBody, _ := io.ReadAll(resp.Body)
		return "", fmt.Errorf("register API returned %d: %s", resp.StatusCode, string(respBody))
	}

	// 2026-06-21: 解析 register 返回的 api_key 并缓存。
	// 老版本服务端（迁移未完成）不会返回 api_key 字段，此时跳过缓存。
	// 2026-06-23: 同时读取 dns_domain，用于 certbot 自动 TLS 配置。
	var result struct {
		Data struct {
			APIKey     string `json:"api_key"`
			APIKeyPath string `json:"api_key_path"`
			DNSDomain  string `json:"dns_domain"`
		} `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		// 解析失败不算致命错误（可能是老服务端），仅警告
		fmt.Printf("⚠ could not parse register response for api_key: %v\n", err)
		return "", nil
	}
	if result.Data.APIKey == "" {
		// 老服务端未签发 api_key，跳过缓存（节点继续用 token 鉴权）
		return result.Data.DNSDomain, nil
	}

	// 2026-06-24: 始终用 cfg.ControlPlane.APIKeyPath (绝对路径) 写 api_key,
	// 与 buildInstalledConfig 走同一份 writeAPIKeyFile() — 路径解析和权限一致。
	if err := writeAPIKeyFile(cfg.ControlPlane.APIKeyPath, result.Data.APIKey); err != nil {
		return result.Data.DNSDomain, fmt.Errorf("write api_key: %w", err)
	}
	fmt.Printf("✔ api_key cached to %s\n", cfg.ControlPlane.APIKeyPath)
	return result.Data.DNSDomain, nil
}

// writeAPIKeyFile 2026-06-24: 唯一写入 api_key 的入口。
// 解析为绝对路径,创建目录,0600 权限。install 阶段所有 api_key 写入必须走这里,
// 保证 yaml 和文件不会出现两份内容不一致的 token。
func writeAPIKeyFile(path, key string) error {
	if strings.TrimSpace(key) == "" {
		return fmt.Errorf("api_key is empty")
	}
	if p := strings.TrimSpace(path); p != "" {
		path = p
	} else {
		path = "configs/api_key"
	}
	if !filepath.IsAbs(path) {
		if abs, err := filepath.Abs(path); err == nil {
			path = abs
		}
	}
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return fmt.Errorf("create dir: %w", err)
	}
	return os.WriteFile(path, []byte(strings.TrimSpace(key)), 0o600)
}

// readAPIKeyFile 2026-06-24: install 阶段从 api_key_path 文件读 token 用于
// console register 等 install 期内的鉴权调用。运行时由 agent.LoadBearer() 负责,
// 不重复实现。
func readAPIKeyFile(path string) string {
	if p := strings.TrimSpace(path); p != "" {
		if data, err := os.ReadFile(p); err == nil {
			return strings.TrimSpace(string(data))
		}
	}
	return ""
}

// =============================================================================
//  2026-06-23 NEW: Certbot 自动配置（Let's Encrypt 证书 + DoH 443 直连）
// =============================================================================

// provisionCertbot 安装 certbot 并用 standalone 模式申请 Let's Encrypt 证书。
// 成功后将 dns-resolver 配置更新为 doh:443 + tls 证书路径，并停用 Caddy（如存在）。
// certbot 临时使用 :80 完成 http-01 挑战，完成后立即释放端口。
// 错误直接返回，调用方负责红字打印并终止 install。
func provisionCertbot(cfg *config.Config, configPath, domain string) error {
	if domain == "" {
		return nil
	}

	// 1. 安装 certbot
	if _, lookErr := exec.LookPath("certbot"); lookErr != nil {
		fmt.Println("  certbot not found, installing via apt...")
		installCmd := exec.Command("sh", "-c", "apt update && apt install -y certbot")
		installCmd.Stdout = os.Stdout
		installCmd.Stderr = os.Stderr
		if err := installCmd.Run(); err != nil {
			return fmt.Errorf("%scertbot 安装失败%s: %w", redFg, resetSty, err)
		}
		fmt.Println("  ✔ certbot installed")
	}

	// 2. 停止 Caddy（如运行中）释放 :80 / :443
	if _, sysErr := exec.LookPath("systemctl"); sysErr == nil {
		_ = exec.Command("systemctl", "stop", "caddy").Run()
		_ = exec.Command("systemctl", "disable", "caddy").Run()
	}

	// 3. 申请证书（失败直接返回，不兜底自签名）
	fmt.Printf("  certbot: requesting certificate for %s...\n", domain)
	certCmd := exec.Command("certbot", "certonly", "--standalone",
		"-d", domain,
		"--non-interactive",
		"--agree-tos",
		"--register-unsafely-without-email",
	)
	certCmd.Stdout = os.Stdout
	certCmd.Stderr = os.Stderr
	if err := certCmd.Run(); err != nil {
		return fmt.Errorf("%scertbot 证书申请失败: %v%s\n"+
			"  ⚠ 请确认域名 %s 的 DNS A 记录已指向本机公网 IP\n"+
			"  ⚠ 本机 :80 端口未被占用", redFg, err, resetSty, domain)
	}

	// 4. 获取证书路径
	certDir := fmt.Sprintf("/etc/letsencrypt/live/%s", domain)
	if _, statErr := os.Stat(certDir); statErr != nil {
		return fmt.Errorf("%scertbot 证书目录 %s 未找到%s: %w", redFg, certDir, resetSty, statErr)
	}

	// 5. 更新配置：doh → 443，设置 TLS 路径
	cfg.Listen.DoH = 443
	cfg.Listen.TLSCertFile = certDir + "/fullchain.pem"
	cfg.Listen.TLSKeyFile = certDir + "/privkey.pem"

	if err := writeConfigAtomic(configPath, cfg); err != nil {
		return fmt.Errorf("%s更新配置失败%s: %w", redFg, resetSty, err)
	}

	fmt.Printf("  ✔ doh: 443 (direct TLS via dns-resolver)\n")
	fmt.Printf("  ✔ tls: %s\n", cfg.Listen.TLSCertFile)
	fmt.Println("  ✔ certbot: Let's Encrypt certificate obtained")
	return nil
}

// certbotRenewHookPath 生成 certbot 续期后重启 dns-resolver 的 hook 脚本路径。
// 后续 dns-resolver 支持 GetCertificate 热加载后可移除。
const certbotRenewHookPath = "/etc/letsencrypt/renewal-hooks/post/dns-resolver-restart.sh"

// ensureCertbotRenewHook 安装 certbot 续期后重启 dns-resolver 的 hook，
// 并确保 certbot.timer 已启用（自动续期的调度器）。
func ensureCertbotRenewHook() {
	hookContent := `#!/bin/sh
# Auto-generated by dns-resolver install
# Restart dns-resolver after certificate renewal
systemctl try-restart dns-resolver || true
`
	if err := os.MkdirAll(filepath.Dir(certbotRenewHookPath), 0o755); err != nil {
		return
	}
	if err := os.WriteFile(certbotRenewHookPath, []byte(hookContent), 0o755); err != nil {
		return
	}

	// 确保 certbot 自动续期 timer 已启用（万一被 disable 了）
	if _, sysErr := exec.LookPath("systemctl"); sysErr == nil {
		_ = exec.Command("systemctl", "enable", "--now", "certbot.timer").Run()
	}
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
  --console URL     portal-web admin Base URL, e.g. https://console.ocerlink.com
  --node-id ID      Node ID assigned by console, e.g. hk-01
  --api-key KEY     Node API key issued by console (ak_xxx)
  --config PATH     Output config path (default: configs/server.yaml)
  --name NAME       Optional human-friendly node name
  --region CODE     Optional region, e.g. ap-northeast-1
  --country CODE    Optional country code, e.g. JP
  --city NAME       Optional city
  --provider TAG    Optional provider tag, e.g. AWS
  --start           After install, auto-start the node (systemd preferred, fallback nohup)
  --no-start        Skip auto-start (overrides --start; default for raw 'resolver install')
  --systemd-unit P  Custom systemd unit path (default: /etc/systemd/system/dns-resolver.service)

Example:
  # Use a custom config path
  resolver --config=/etc/dns-resolver/server.yaml

  # Or via env var
  RESOLVER_CONFIG=/etc/dns-resolver/server.yaml resolver

  # Provision a node
  resolver install \
    --console=https://console.ocerlink.com \
    --node-id=hk-01 \
    --api-key=ak_xxx

  # Provision + auto-start via systemd (or nohup fallback)
  resolver install --start \
    --console=https://console.ocerlink.com \
    --node-id=hk-01 \
    --api-key=ak_xxx`)
}

// =============================================================================
//  2026-06-22 NEW: 安装完成后自动启动节点(systemd 优先 + nohup 降级)
// =============================================================================

// resolverSystemdUnitTemplate 渲染生成的 dns-resolver.service。
// 关键点:
//   - Type=simple 配合 Restart=on-failure,崩了会自动拉起
//   - ReadWritePaths 限定只能写 configs/ 目录,避免 sandbox 把日志/缓冲写到只读
//   - LimitNOFILE=65536 保证 DNS 高并发场景不爆 fd
const resolverSystemdUnitTemplate = `[Unit]
Description=OcerDNS DNS Resolver Node ({{.NodeID}})
Documentation=https://test-dns.ocerlinkdata.com
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
# 2026-06-22: WorkingDirectory 固定为 config 同目录，
# 这样 loadBearer() fallback 的 CWD-相对路径 "configs/api_key"
# 也能命中同目录下的 api_key 文件 (api_key_path 优先)。
WorkingDirectory={{.ConfigDir}}
ExecStart={{.Binary}} --config={{.Config}}
Restart=on-failure
RestartSec=5s
LimitNOFILE=65536

# sandbox: 禁止提权,只放开 configs 目录
NoNewPrivileges=true
ProtectSystem=strict
PrivateTmp=true
ReadWritePaths={{.ConfigDir}}

[Install]
WantedBy=multi-user.target
`

// startService 安装完成后拉起节点。
// 策略:systemd 优先(写 unit + daemon-reload + enable --now)→
//
//	systemd 不可用/无权限时降级为 nohup 后台进程(写 PID 文件)。
//
// 返回 error 时说明两种方式都失败,调用方应打 ⚠ 提示用户手动启动。
func startService(opts *installOptions) error {
	if err := tryResolverStartViaSystemd(opts); err != nil {
		fmt.Printf("⚠ systemd path unavailable: %v, falling back to nohup\n", err)
		return startResolverViaNohup(opts)
	}
	return nil
}

// tryResolverStartViaSystemd 尝试把节点装成 systemd 服务并 enable --now。
// 失败场景(不视为 fatal):
//   - systemctl 不存在 (macOS / 容器里无 systemd)
//   - /etc/systemd/system 不可写 (非 root / 容器 readonly)
//   - systemctl 子命令失败(systemd 用户实例未启动等)
func tryResolverStartViaSystemd(opts *installOptions) error {
	if _, err := exec.LookPath("systemctl"); err != nil {
		return fmt.Errorf("systemctl not found: %w", err)
	}

	unitPath := opts.SystemdUnit
	if unitPath == "" {
		unitPath = "/etc/systemd/system/dns-resolver.service"
	}
	// 2026-06-22: 将配置路径转为绝对路径，避免 systemd 工作目录为 / 时找不到配置文件
	absConfig := opts.ConfigPath
	if !filepath.IsAbs(absConfig) {
		if wd, err := os.Getwd(); err == nil {
			absConfig = filepath.Join(wd, absConfig)
		}
	}
	// 2026-06-23: configDir 也从绝对路径派生，避免 systemd WorkingDirectory 要求绝对路径
	configDir := filepath.Dir(absConfig)

	rendered := strings.NewReplacer(
		"{{.NodeID}}", opts.NodeID,
		"{{.Binary}}", "/usr/local/bin/dns-resolver",
		"{{.Config}}", absConfig,
		"{{.ConfigDir}}", configDir,
	).Replace(resolverSystemdUnitTemplate)

	// 先 dry-write 到 /tmp,确认模板渲染没问题再写正式路径
	tmpUnit := filepath.Join(os.TempDir(), "dns-resolver.service.tmp")
	if err := os.WriteFile(tmpUnit, []byte(rendered), 0o644); err != nil {
		return fmt.Errorf("render unit template: %w", err)
	}
	if err := copyResolverFile(unitPath, tmpUnit, 0o644); err != nil {
		return fmt.Errorf("write unit %s: %w (need root)", unitPath, err)
	}
	_ = os.Remove(tmpUnit)

	// daemon-reload + enable --now。任何一步失败都放弃 systemd 路径。
	for _, args := range [][]string{
		{"daemon-reload"},
		{"enable", "dns-resolver.service"},
		{"start", "dns-resolver.service"},
	} {
		cmd := exec.Command("systemctl", args...)
		out, err := cmd.CombinedOutput()
		if err != nil {
			return fmt.Errorf("systemctl %s failed: %w (%s)", strings.Join(args, " "), err, strings.TrimSpace(string(out)))
		}
	}

	fmt.Printf("✔ systemd: dns-resolver.service installed and started\n")
	fmt.Printf("    systemctl status dns-resolver    # check status\n")
	fmt.Printf("    journalctl -u dns-resolver -f -o cat    # tail logs (compact)\n")
	return nil
}

// startResolverViaNohup 没有 systemd 时的降级方案。
// 把 dns-resolver 用 setsid 拉成后台进程,写 PID 文件方便后续 stop。
func startResolverViaNohup(opts *installOptions) error {
	binary, err := os.Executable()
	if err != nil {
		return fmt.Errorf("locate self binary: %w", err)
	}

	// 2026-06-23: 始终使用绝对路径，避免 nohup 进程 CWD 漂移导致找不到配置
	absConfig := opts.ConfigPath
	if !filepath.IsAbs(absConfig) {
		if wd, err := os.Getwd(); err == nil {
			absConfig = filepath.Join(wd, absConfig)
		}
	}
	configDir := filepath.Dir(absConfig)

	pidFile := filepath.Join(configDir, "dns-resolver.pid")
	logFile := filepath.Join(configDir, "dns-resolver.log")

	// 避免重复启动:已有 PID 文件且进程活着就直接复用
	if pid, perr := readResolverPIDFile(pidFile); perr == nil && processResolverAlive(pid) {
		fmt.Printf("✔ dns-resolver already running (pid=%d)\n", pid)
		return nil
	}

	cmd := exec.Command(binary, "--config="+absConfig)
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	out, err := os.OpenFile(logFile, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0o640)
	if err != nil {
		return fmt.Errorf("open log file %s: %w", logFile, err)
	}
	defer out.Close()
	cmd.Stdout = out
	cmd.Stderr = out

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("spawn dns-resolver: %w", err)
	}
	_ = cmd.Process.Release()

	if err := os.WriteFile(pidFile, []byte(fmt.Sprintf("%d", cmd.Process.Pid)), 0o644); err != nil {
		// PID 写不进去不影响服务运行,只警告
		fmt.Printf("⚠ write pid file %s failed: %v\n", pidFile, err)
	}

	// 简单探活 1s,确保进程没秒崩
	time.Sleep(1 * time.Second)
	if !processResolverAlive(cmd.Process.Pid) {
		return fmt.Errorf("dns-resolver exited immediately, see log: %s", logFile)
	}

	fmt.Printf("✔ dns-resolver started in background (pid=%d)\n", cmd.Process.Pid)
	fmt.Printf("    log:    %s\n", logFile)
	fmt.Printf("    pid:    %s\n", pidFile)
	fmt.Printf("    stop:   kill $(cat %s)\n", pidFile)
	return nil
}

// readResolverPIDFile 从 PID 文件读取整数 PID,失败返回 0 + error
func readResolverPIDFile(path string) (int, error) {
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

// processResolverAlive 探活(Linux: Signal 0 不真发信号,仅做存在性检查)
func processResolverAlive(pid int) bool {
	if pid <= 0 {
		return false
	}
	proc, err := os.FindProcess(pid)
	if err != nil {
		return false
	}
	return proc.Signal(syscall.Signal(0)) == nil
}

// copyResolverFile 简单文件复制(不走 io.Copy 是因为要控制 mode)
func copyResolverFile(dst, src string, mode os.FileMode) error {
	data, err := os.ReadFile(src)
	if err != nil {
		return err
	}
	return os.WriteFile(dst, data, mode)
}
