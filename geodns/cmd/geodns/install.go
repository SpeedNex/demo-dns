package main

import (
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"strings"

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
	fs.BoolVar(&opts.Force, "force", false, "Overwrite existing config file")

	if err := fs.Parse(args); err != nil {
		return err
	}

	if err := validateGeodnsInstallOptions(&opts); err != nil {
		fs.Usage()
		return err
	}

	cfg := buildGeodnsConfig(&opts)

	if err := writeGeodnsConfig(opts.ConfigPath, cfg, opts.Force); err != nil {
		return fmt.Errorf("write config failed: %w", err)
	}

	fmt.Printf("✔ config written to %s\n", opts.ConfigPath)
	fmt.Printf("  server        = %s\n", opts.Server)
	fmt.Printf("  node_id       = %s\n", opts.NodeID)
	fmt.Printf("  listen_addr   = %s\n", opts.ListenAddr)
	fmt.Printf("  dns_addr      = %s\n", opts.DNSAddr)
	fmt.Println("Next: run `geodns` to start the node.")
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

	return &config.Config{
		Server: config.ServerConfig{
			ListenAddr:         opts.ListenAddr,
			ListenDNSAddr:      opts.DNSAddr,
			ConsoleHealthURL:   server + "/api/v1/internal/geodns/health-view",
			ConsoleHealthToken: opts.HealthToken,
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