package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"strings"
	"syscall"

	"ocer-dns/geodns/internal/config"
	"ocer-dns/geodns/internal/server"
)

func main() {
	log.SetFlags(log.LstdFlags | log.Lshortfile)

	// 子命令分发：`geodns install ...` 用于把 console 预发凭据写入配置文件
	if len(os.Args) > 1 && !strings.HasPrefix(os.Args[1], "-") {
		switch os.Args[1] {
		case "install":
			if err := runInstall(os.Args[2:]); err != nil {
				log.Fatalf("geodns install failed: %v", err)
			}
			return
		case "help", "-h", "--help":
			printGeodnsUsage()
			return
		default:
			log.Printf("unknown subcommand %q", os.Args[1])
			printGeodnsUsage()
			os.Exit(2)
		}
	}

	// -h / --help as flag
	for _, a := range os.Args[1:] {
		if a == "-h" || a == "--help" {
			printGeodnsUsage()
			return
		}
	}

	configPath := flag.String("config", defaultConfigPath(), "path to geodns config yaml")
	flag.Parse()

	cfg, err := config.Load(*configPath)
	if err != nil {
		log.Fatalf("geodns: failed to load config %s: %v", *configPath, err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	log.Print("starting geodns")
	svc := server.New(cfg)
	if err := svc.Run(ctx); err != nil && !errors.Is(err, context.Canceled) {
		log.Fatal(err)
	}
}

func defaultConfigPath() string {
	if path := os.Getenv("GEODNS_CONFIG"); path != "" {
		return path
	}
	return "configs/config.yaml"
}

func printGeodnsUsage() {
	fmt.Println(`Usage:
  geodns [--config PATH]              Start the geodns daemon
  geodns install [flags]              Write a config.yaml with pre-issued console credentials
  geodns help                         Show this help

Flags:
  --config PATH    Path to config.yaml (default: configs/config.yaml).
                   Overrides GEODNS_CONFIG if both are set.

Environment:
  GEODNS_CONFIG            Equivalent to --config.

install flags:
  --server URL           portal-web Base URL, e.g. https://console.ocerlink.com
  --token TOKEN          Node token issued by console
  --node-id ID           Node ID assigned by console
  --config PATH          Output config path (default: configs/config.yaml)
  --listen-addr ADDR     HTTP listen address (default: :5354)
  --dns-addr ADDR        DNS listen address (default: :53)
  --health-token TOKEN   Internal health-view token (shared with portal-web)
  --force                Overwrite existing config file

Example:
  # Provision a geodns node
  geodns install \
    --server=https://console.ocerlink.com \
    --token=xxxxx \
    --node-id=xxxxx

  # Start the daemon
  geodns --config=configs/config.yaml`)
}
