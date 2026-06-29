package main

import (
	"context"
	"crypto/tls"
	"flag"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"ocer-dns/dns-resolver/internal/agent"
	"ocer-dns/dns-resolver/internal/cache"
	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/dnscache"
	"ocer-dns/dns-resolver/internal/dnsserver"
	"ocer-dns/dns-resolver/internal/doh"
	"ocer-dns/dns-resolver/internal/doq"
	"ocer-dns/dns-resolver/internal/logging"
	"ocer-dns/dns-resolver/internal/matching"
	"ocer-dns/dns-resolver/internal/metrics"
	"ocer-dns/dns-resolver/internal/resolver"
)

// bracketLogWriter 包装 os.Stderr，将 Go 默认日志时间戳 "2006/01/02 15:04:05"
// 改为 "[2006/01/02 15:04:05]" 格式，便于日志解析。
type bracketLogWriter struct {
	w io.Writer
}

func (w *bracketLogWriter) Write(p []byte) (int, error) {
	s := string(p)
	// LstdFlags 格式：前 19 个字符为 "2006/01/02 15:04:05"
	if len(s) >= 20 && s[4] == '/' && s[7] == '/' && s[13] == ':' && s[16] == ':' {
		s = "[" + s[:19] + "]" + s[19:]
		return w.w.Write([]byte(s))
	}
	return w.w.Write(p)
}

// defaultConfigPath 是 resolver 启动时寻找 server.yaml 的兜底路径。
// 部署在容器 / systemd 时通常会通过 --config 或 RESOLVER_CONFIG 改写。
const defaultConfigPath = "/usr/local/etc/dns-resolver/server.yaml"

// 版本信息（编译时通过 -ldflags 注入）
var (
	version   string
	buildTime string
)

// envConfigKey 是允许通过环境变量覆盖配置路径的 key，
// 命名参考社区惯例（CONSUL_CONFIG_PATH 等），便于与 CI / K8s 集成。
const envConfigKey = "RESOLVER_CONFIG"

// resolveConfigPath 决定本次启动要读取的配置文件路径。
// 优先级：--config 命令行参数 > RESOLVER_CONFIG 环境变量 > defaultConfigPath。
// 返回值允许为 ""（如果用户显式传了空字符串），由 config.Load 报错。
//
// 注意：故意不注册 -h / --help，flag 库对这两个标志有内置 ErrHelp 处理，
// 会与 main() 顶部手动调用的 printUsage() 冲突。help 由 main() 自己处理。
func resolveConfigPath(args []string) (string, error) {
	// 1) 命令行参数 --config=<path> / --config <path>
	fs := flag.NewFlagSet("resolver", flag.ContinueOnError)
	// 抑制 flag 库在 parse error / unknown 时输出到 stderr；由我们统一处理
	fs.SetOutput(devNullWriter{})
	configPath := fs.String("config", "", "Path to server.yaml (overrides RESOLVER_CONFIG)")
	if err := fs.Parse(args); err != nil {
		// ContinueOnError + SetOutput 抑制后，unknown flag 也不会 panic
		return "", err
	}
	if v := strings.TrimSpace(*configPath); v != "" {
		return v, nil
	}

	// 2) 环境变量 RESOLVER_CONFIG
	if v := strings.TrimSpace(os.Getenv(envConfigKey)); v != "" {
		return v, nil
	}

	// 3) 兜底
	return defaultConfigPath, nil
}

// devNullWriter 用来把 flag 库的诊断输出丢进黑洞，避免污染 resolver 自己的日志格式
type devNullWriter struct{}

func (devNullWriter) Write(p []byte) (int, error) { return len(p), nil }

func main() {
	log.SetFlags(log.LstdFlags)
	log.SetOutput(&bracketLogWriter{w: os.Stderr})

	// 子命令分发：`resolver install ...` 用于把 console 预发凭据写入配置文件
	// 没有子命令或显式 `resolver run` 时进入原 daemon 主流程
	if len(os.Args) > 1 && !strings.HasPrefix(os.Args[1], "-") {
		switch os.Args[1] {
		case "install":
			if err := runInstall(os.Args[2:]); err != nil {
				log.Fatalf("resolver install failed: %v", err)
			}
			return
		case "run":
			// fall through to default run
			os.Args = append([]string{os.Args[0]}, os.Args[2:]...)
		case "help", "-h", "--help":
			printUsage()
			return
		default:
			log.Printf("unknown subcommand %q", os.Args[1])
			printUsage()
			os.Exit(2)
		}
	}

	// 当用户以 flag 形式传 -h / --help 时（首项以 "-" 开头，不会进上面的 switch），
	// 直接打 usage 后退出，避免走到 resolveConfigPath 被 flag 库翻译成 ErrHelp 报错。
	for _, a := range os.Args[1:] {
		if a == "-h" || a == "--help" {
			printUsage()
			return
		}
	}

	configPath, err := resolveConfigPath(os.Args[1:])
	if err != nil {
		log.Fatalf("Failed to parse --config: %v", err)
	}
	cfg, err := config.Load(configPath)
	if err != nil {
		log.Fatalf("Failed to load config %s: %v", configPath, err)
	}
	log.Printf("dns-resolver using config: %s", configPath)

	// 启动前强校验：api_key/secret/node_id 必须由 `resolver install` 写入
	// 任何兜底/兜底注册路径都不存在，缺凭据直接拒绝启动
	if err := cfg.Validate(); err != nil {
		log.Fatalf("Invalid config: %v", err)
	}

	// Initialize rule engine (8-level policy engine)
	engine := matching.NewEngine()

	// Initialize Profile Resolution Layer
	resolutionLayer := resolver.New(engine)

	// Initialize metrics collector
	metricsCollector := metrics.New()

	// Initialize agent with console-issued credentials (no registration flow)
	agt := agent.New(cfg, engine, resolutionLayer, metricsCollector)

	// 2026-06-24: 启动前强校验 — 凭据文件必须可读且非空。
	// api_key 已移出 yaml,改读 api_key_path 文件;文件缺失/空直接拒绝启动,
	// 不让节点以空 token 跑起来再被 server 端 401 拒掉。
	if bearer := agt.LoadBearer(); strings.TrimSpace(bearer) == "" {
		log.Fatalf("Invalid config: api_key file %q is missing or empty (run `resolver install` to provision)", cfg.ControlPlane.APIKeyPath)
	}

	// Initialize reliable log buffer (reads credentials via agt.LoadBearer() —
	// the same path the agent uses for heartbeat / config poll, so yaml and
	// the api_key file can never disagree about which token to send).
	logBuffer := logging.NewBuffer(
		cfg.Logging.BufferPath,
		fmt.Sprintf("%s/api/v1/node/dns-resolver/query-logs", cfg.ControlPlane.Endpoint),
		cfg.Logging.MaxBufferSize,
		10*time.Second,
		logging.Credentials{
			NodeID: cfg.ControlPlane.NodeID,
			APIKey: agt.LoadBearer(),
		},
		agt.MarkLogFlush,
	)

	// Initialize Redis cache (no-op when redis.enabled is false). The cache
	// is shared by the DoH and the DNS servers for query-count dedup. We
	// only close it at process shutdown — the resolver's runtime contract
	// is that the cache is always safe to use.
	queryCache := cache.New(cfg.Redis)
	defer func() {
		if err := queryCache.Close(); err != nil {
			log.Printf("cache: close error: %v", err)
		}
	}()

	// Initialize local DNS response cache
	dnsCache := dnscache.New(cfg.Cache.MaxSize, time.Duration(cfg.Cache.MaxTTL)*time.Second)
	if !cfg.Cache.Enabled {
		dnsCache.SetEnabled(false)
		log.Printf("dns_cache: disabled by config")
	} else {
		log.Printf("dns_cache: enabled (max_size=%d, max_ttl=%ds)", cfg.Cache.MaxSize, cfg.Cache.MaxTTL)
	}

	// Context for background goroutines
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start agent heartbeat loop
	go agt.StartHeartbeat(ctx)

	// Start config sync loop (every 60s)
	go agt.StartConfigSync(ctx, time.Duration(cfg.ControlPlane.ConfigPollInterval)*time.Second)

	// Start reliable log flusher
	go logBuffer.StartFlusher(ctx)

	// 节点健康由 (now - last_heartbeat_at) <= 阈值 的简单超时判断
	// 不再在 resolver 启动时拉取 geodns 健康视图；geodns 自身从控制面获取
	_ = ctx

	// Initialize and start DoH server with resolver and metrics
	dohServer := doh.NewServer(cfg, engine, resolutionLayer, logBuffer, metricsCollector, queryCache, agt.FetchProfile)
	dohServer.SetProfileConfigLoader(agt.GetProfileConfig)

	// Create shared resolution handler for DNS and DoQ servers
	resolverHandler := resolver.NewHandler(cfg, resolutionLayer, logBuffer, metricsCollector, queryCache, dnsCache)

	// Initialize DNS server (UDP, TCP, DoT) with shared handler
	dnsServer := dnsserver.New(cfg, resolverHandler, metricsCollector, agt.FetchProfile)
	dnsServer.SetProfileConfigLoader(agt.GetProfileConfig)
	dnsServer.SetDeviceResolver(agt.LookupDeviceByIP)

	// Create main mux and attach DoH handler
	mainMux := http.NewServeMux()

	// Copy all routes from doh server by wrapping it
	dohHandler := dohServer.Handler()
	mainMux.Handle("/dns-query", dohHandler)
	mainMux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		dohHandler.ServeHTTP(w, r)
	})

	// Add Prometheus metrics endpoint
	mainMux.Handle("/metrics", metricsCollector.PrometheusHandler())

	// Add health endpoint
	mainMux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok","version":"1.0.0"}`))
	})

	// Start QPS measurement
	go func() {
		var lastTotal int64
		ticker := time.NewTicker(1 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ticker.C:
				snap := metricsCollector.Snapshot()
				qps := snap["queries_total"] - lastTotal
				metricsCollector.SetQPS(qps)
				lastTotal = snap["queries_total"]
			case <-ctx.Done():
				return
			}
		}
	}()

	httpServer := &http.Server{
		Addr:    fmt.Sprintf(":%d", cfg.Listen.DoH),
		Handler: mainMux,
	}

	// DoH 直接使用 TLS（certbot 证书），不再依赖上游反向代理
	dohTLS, err := dnsserver.LoadTLSConfig(cfg.Listen.TLSCertFile, cfg.Listen.TLSKeyFile, cfg.ControlPlane.DNSDomain)
	if err != nil {
		log.Fatalf("doh: failed to load TLS config: %v", err)
	}

	go func() {
		ln, err := net.Listen("tcp", httpServer.Addr)
		if err != nil {
			log.Fatalf("doh: failed to listen on %s: %v", httpServer.Addr, err)
		}
		tlsLn := tls.NewListener(ln, dohTLS)
		log.Printf("dns-resolver DoH listening on https://0.0.0.0:%d (direct TLS)", cfg.Listen.DoH)
		log.Printf("Node: %s (%s v%s)", cfg.Node.Name, cfg.Node.NodeUID, cfg.Node.Version)
		log.Printf("Upstream DNS: %v", cfg.Upstream)
		if err := httpServer.Serve(tlsLn); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Server error: %v", err)
		}
	}()

	go func() {
		if err := dnsServer.Run(ctx); err != nil {
			log.Fatalf("DNS server error: %v", err)
		}
	}()

	// Start DoQ (DNS over QUIC) server if port configured
	var doqServer *doq.Server
	if cfg.Listen.DoQ > 0 {
		tlsCfg, err := dnsserver.LoadTLSConfig(cfg.Listen.TLSCertFile, cfg.Listen.TLSKeyFile, cfg.ControlPlane.DNSDomain)
		if err != nil {
			log.Printf("doq: failed to load TLS config: %v (DoQ not started)", err)
		} else {
			doqServer = doq.New(cfg, resolverHandler, logBuffer, metricsCollector, agt.FetchProfile)
			doqServer.SetProfileConfigLoader(agt.GetProfileConfig)
			go func() {
				if err := doqServer.Run(ctx, tlsCfg); err != nil {
					log.Fatalf("DoQ server error: %v", err)
				}
			}()
		}
	}

	// Wait for shutdown signal
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Println("Shutting down server...")

	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer shutdownCancel()

	if err := httpServer.Shutdown(shutdownCtx); err != nil {
		log.Fatalf("Server forced to shutdown: %v", err)
	}

	log.Println("Server exited gracefully")
}
