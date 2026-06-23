package main

import (
	"context"
	"crypto/x509"
	"encoding/json"
	"encoding/pem"
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
	// register 成功时可能返回 dns_domain，用于自动配置 Caddy 证书。
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

		// 自动安装并配置 Caddy（自动 HTTPS 反向代理到 :8443）
		if setupErr := setupCaddy(dnsDomain); setupErr != nil {
			fmt.Printf("%s⚠ Caddy 自动配置失败%s: %v（DoH 将无法通过 443 端口访问，需手动配置）\n", redFg, resetSty, setupErr)
		} else {
			fmt.Println("✔ caddy: auto-configured with Let's Encrypt TLS")
			// 将 Caddy 证书路径绑定到 dns-resolver 配置，使 DoT/DoQ 共用同一份证书
			if certErr := BindCaddyCertificate(cfg, opts.ConfigPath, dnsDomain); certErr != nil {
				fmt.Printf("  %s⚠ DoT/DoQ 证书绑定失败%s: %v（DoT/DoQ 将使用自签名或缓存证书）\n", yellowFg, resetSty, certErr)
			} else {
				fmt.Println("  ✔ DoT/DoQ: TLS certificates bound from Caddy")
			}
		}
	} else {
		fmt.Println("✔ console register: success (no dns_domain, skip Caddy auto-setup)")
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

// buildInstalledConfig 在 config.Default() 的基础上覆盖控制面凭据和节点标识
func buildInstalledConfig(opts *installOptions) *config.Config {
	cfg := config.Default()

	cfg.ControlPlane.Endpoint = strings.TrimRight(opts.Console, "/")
	cfg.ControlPlane.APIKey = strings.TrimSpace(opts.APIKey)
	cfg.ControlPlane.NodeID = strings.TrimSpace(opts.NodeID)

	// 2026-06-22: 把 api_key 缓存路径固定为 config 同目录下的绝对路径，
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
// 2026-06-23: 返回值扩充为 (dnsDomain, error)，dns_domain 用于 Caddy 自动 TLS 配置。
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
	if t := strings.TrimSpace(cfg.ControlPlane.APIKey); t != "" {
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
	// 2026-06-23: 同时读取 dns_domain，用于 Caddy 自动 TLS 配置。
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

	// 2026-06-22: 始终用 cfg.ControlPlane.APIKeyPath (绝对路径) 写 api_key，
	// 避免 CWD 不同时文件落到错误位置，并让运行时 loadBearer() 能直接读到。
	apiKeyPath := strings.TrimSpace(cfg.ControlPlane.APIKeyPath)
	if apiKeyPath == "" {
		apiKeyPath = "configs/api_key"
	}
	if !filepath.IsAbs(apiKeyPath) {
		if abs, err := filepath.Abs(apiKeyPath); err == nil {
			apiKeyPath = abs
		}
	}
	if err := os.MkdirAll(filepath.Dir(apiKeyPath), 0o755); err != nil {
		return result.Data.DNSDomain, fmt.Errorf("create api_key dir: %w", err)
	}
	if err := os.WriteFile(apiKeyPath, []byte(result.Data.APIKey), 0o600); err != nil {
		return result.Data.DNSDomain, fmt.Errorf("write api_key to %s: %w", apiKeyPath, err)
	}
	fmt.Printf("✔ api_key cached to %s\n", apiKeyPath)
	return result.Data.DNSDomain, nil
}

// =============================================================================
//  2026-06-23 NEW: Caddy 自动配置（DoH HTTPS + 自动 Let's Encrypt 证书）
// =============================================================================

// setupCaddy 安装并配置 Caddy，将 DoH 路径转发到 dns-resolver :8443。
// - 如果 Caddy 未安装，通过 apt 自动安装
// - 写入 Caddyfile，配置自动 TLS + 反向代理
// - 通过 systemd 启动 Caddy
func setupCaddy(domain string) error {
	if domain == "" {
		return nil
	}

	// 1. 检查/安装 Caddy
	if _, lookErr := exec.LookPath("caddy"); lookErr != nil {
		fmt.Println("  caddy not found, installing via apt...")
		installCmd := exec.Command("apt-get", "install", "-y", "caddy")
		installCmd.Stdout = os.Stdout
		installCmd.Stderr = os.Stderr
		if err := installCmd.Run(); err != nil {
			return fmt.Errorf("%sCaddy apt 安装失败%s: %w", redFg, resetSty, err)
		}
	}

	// 2. 打印版本
	if verOut, verErr := exec.Command("caddy", "version").Output(); verErr == nil {
		fmt.Printf("  caddy: %s", strings.TrimSpace(string(verOut)))
	}

	// 3. 写入 Caddyfile
	// 使用标准 /etc/caddy/Caddyfile 路径（apt install caddy 自动创建该目录）
	caddyfile := fmt.Sprintf(`# Auto-generated by dns-resolver install — %s
# Do NOT edit this file manually unless you know what you're doing.
# resolver will overwrite it on re-install.

%s {
    # DoH paths — profile UID /dns-query /health
    @doh {
        path_regexp doh ^/[0-9a-f]{6}(/dns-query)?$
        path /dns-query /health
    }
    handle @doh {
        reverse_proxy localhost:8443
    }
    # Other paths — safe default
    handle {
        respond "OcerDNS Resolver — %s" 200
    }
}
`, time.Now().UTC().Format(time.RFC3339), domain, domain)

	if err := os.MkdirAll("/etc/caddy", 0o755); err != nil {
		return fmt.Errorf("%s创建 /etc/caddy 目录失败%s: %w", redFg, resetSty, err)
	}
	if err := os.WriteFile("/etc/caddy/Caddyfile", []byte(caddyfile), 0o644); err != nil {
		return fmt.Errorf("%s写入 Caddyfile 失败%s: %w", redFg, resetSty, err)
	}
	fmt.Println("  ✔ Caddyfile written to /etc/caddy/Caddyfile")

	// 4. 通过 systemd 启动 Caddy
	if _, sysErr := exec.LookPath("systemctl"); sysErr == nil {
		// 4a. 先停掉 apt 可能已启动的旧 Caddy 实例，避免配置冲突
		_ = exec.Command("systemctl", "stop", "caddy").Run()

		// 4b. daemon-reload 确保 systemd 加载最新 unit
		if out, err := exec.Command("systemctl", "daemon-reload").CombinedOutput(); err != nil {
			return fmt.Errorf("%sCaddy systemctl daemon-reload 失败%s: %w (%s)", redFg, resetSty, err, strings.TrimSpace(string(out)))
		}

		// 4c. enable（设置开机自启）
		if out, err := exec.Command("systemctl", "enable", "caddy").CombinedOutput(); err != nil {
			// enable 失败不阻塞启动，仅警告；可能容器环境不需要 enable
			fmt.Printf("  %s⚠ caddy enable 失败: %s%s\n", yellowFg, strings.TrimSpace(string(out)), resetSty)
		}

		// 4d. start（启动 Caddy 并等待就绪）
		if out, err := exec.Command("systemctl", "start", "caddy").CombinedOutput(); err != nil {
			return fmt.Errorf("%sCaddy 启动失败%s: %w（请手动排查：systemctl status caddy; journalctl -xeu caddy.service）\n  systemctl start caddy 输出: %s", redFg, resetSty, err, strings.TrimSpace(string(out)))
		}
		fmt.Printf("  ✔ caddy started (https://%s → localhost:8443)\n", domain)
	} else {
		// 无 systemd — 仅写入配置，提示用户手动启动
		fmt.Printf("  %s⚠ 未检测到 systemd，请手动启动 Caddy：%s\n", yellowFg, resetSty)
		fmt.Printf("    caddy run --config /etc/caddy/Caddyfile\n")
	}

	return nil
}

// FindCaddyCert 在 Caddy 证书存储中查找指定域名最合适的证书。
// Caddy 标准存储路径（Debian/Ubuntu apt）：
//
//	/var/lib/caddy/.local/share/caddy/certificates/<acme-endpoint>/<domain>/
//
// 当多个 ACME 端点（如 production + staging）同时存在时，
// 解析 x509 证书，选择 NotAfter 最大且未过期的证书。
// 返回 (certPath, keyPath, error)，不复制，直接引用 Caddy 原始文件。
func FindCaddyCert(domain string) (certFile, keyFile string, err error) {
	if domain == "" {
		return "", "", fmt.Errorf("domain is empty")
	}

	pattern := fmt.Sprintf(
		"/var/lib/caddy/.local/share/caddy/certificates/*/%s/%s.crt",
		domain, domain,
	)
	matches, _ := filepath.Glob(pattern)
	log.Printf("caddy cert: searching %q → %d matches", pattern, len(matches))

	if len(matches) == 0 {
		// 诊断：检查 Caddy 证书目录下有哪些域名
		if dirs, dirErr := filepath.Glob("/var/lib/caddy/.local/share/caddy/certificates/*/*/"); dirErr == nil && len(dirs) > 0 {
			log.Printf("caddy cert: available domains: %v", dirs)
		} else {
			log.Printf("caddy cert: no certificates found at all in /var/lib/caddy/.local/share/caddy/certificates/ (Caddy may not have obtained them yet)")
		}
		return "", "", fmt.Errorf("Caddy certificate not found for domain %q", domain)
	}

	// 多 ACME 端点时选择最佳证书
	if len(matches) > 1 {
		log.Printf("caddy cert: multiple ACME endpoints found, selecting best certificate")
		var bestCert string
		var bestNotAfter time.Time
		for _, m := range matches {
			data, readErr := os.ReadFile(m)
			if readErr != nil {
				log.Printf("caddy cert: skipping %s (unreadable: %v)", m, readErr)
				continue
			}
			block, _ := pem.Decode(data)
			if block == nil {
				log.Printf("caddy cert: skipping %s (not valid PEM)", m)
				continue
			}
			cert, parseErr := x509.ParseCertificate(block.Bytes)
			if parseErr != nil {
				log.Printf("caddy cert: skipping %s (parse error: %v)", m, parseErr)
				continue
			}
			if cert.NotAfter.After(time.Now()) && cert.NotAfter.After(bestNotAfter) {
				bestCert = m
				bestNotAfter = cert.NotAfter
				log.Printf("caddy cert: candidate %s (expires %s)", m, cert.NotAfter.Format(time.RFC3339))
			}
		}
		if bestCert == "" {
			return "", "", fmt.Errorf("no valid (non-expired) certificate found for domain %q", domain)
		}
		certFile = bestCert
		log.Printf("caddy cert: selected %s (expires %s)", certFile, bestNotAfter.Format(time.RFC3339))
	} else {
		certFile = matches[0]
	}

	keyFile = strings.Replace(certFile, ".crt", ".key", 1)

	// 权限检查：确认 resolver 进程能读取证书文件
	if _, statErr := os.Stat(keyFile); statErr != nil {
		return "", "", fmt.Errorf("Caddy key file not accessible: %s — check file permissions", keyFile)
	}
	certData, readErr := os.ReadFile(certFile)
	if readErr != nil {
		return "", "", fmt.Errorf("Caddy cert file not readable: %s — check file permissions: %w", certFile, readErr)
	}
	if len(certData) == 0 {
		return "", "", fmt.Errorf("Caddy cert file is empty: %s", certFile)
	}

	log.Printf("caddy cert: referencing cert=%s key=%s — no copy", certFile, keyFile)
	return certFile, keyFile, nil
}

// BindCaddyCertificate 将 Caddy 证书路径直接写入 dns-resolver 配置。
// 不再复制证书文件，直接引用 Caddy 存储中的原始文件。
// 通过 LoadTLSConfig 的 GetCertificate 回调实现热加载和原子缓存回退。
func BindCaddyCertificate(cfg *config.Config, configPath, domain string) error {
	certFile, keyFile, err := FindCaddyCert(domain)
	if err != nil {
		return err
	}

	cfg.Listen.TLSCertFile = certFile
	cfg.Listen.TLSKeyFile = keyFile

	if err := writeConfigAtomic(configPath, cfg); err != nil {
		return fmt.Errorf("update config with TLS cert paths: %w", err)
	}

	log.Printf("tls: config updated cert paths: cert=%s key=%s", certFile, keyFile)
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
	fmt.Printf("    journalctl -u dns-resolver -f    # tail logs\n")
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
