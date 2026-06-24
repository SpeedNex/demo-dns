package agent

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"sync/atomic"
	"testing"

	"ocer-dns/dns-resolver/internal/config"
	"ocer-dns/dns-resolver/internal/matching"
	"ocer-dns/dns-resolver/internal/metrics"
)

func TestPreissuedCredentialsFlow(t *testing.T) {
	t.Helper()

	var heartbeatSeen atomic.Bool

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/v1/node/heartbeat":
			if got := r.Header.Get("Authorization"); got != "Bearer ak_test_01" {
				t.Fatalf("unexpected heartbeat auth header: %s", got)
			}
			heartbeatSeen.Store(true)
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"ok":                           true,
					"node_status":                  "online",
					"latest_config_version":        1,
					"should_pull_config":           true,
					"config_endpoint":              "/api/v1/node/dns-resolver/config",
					"next_heartbeat_after_seconds": 30,
				},
			})
		case "/api/v1/node/dns-resolver/config":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"version":   1,
					"upstreams": []any{map[string]any{"address": "1.1.1.1:53", "protocol": "udp"}},
					"plans":     map[string]any{},
					"rulesets":  map[string]any{},
					"limits":    map[string]any{"max_qps": 1000},
				},
			})
		default:
			http.NotFound(w, r)
		}
	}))
	defer server.Close()

	cfg := testConfig(t, server.URL)
	engine := matching.NewEngine()
	collector := metrics.New()
	agt := New(cfg, engine, collector)

	agt.sendHeartbeat()

	if !heartbeatSeen.Load() {
		t.Fatal("expected heartbeat to be sent")
	}

	globalConfig := filepath.Join(cfg.ControlPlane.ProfilesPath, "global.json")
	if _, err := os.Stat(globalConfig); err != nil {
		t.Fatalf("expected global config file: %v", err)
	}
}

func testConfig(t *testing.T, endpoint string) *config.Config {
	t.Helper()

	tempDir := t.TempDir()
	cfg := config.Default()
	cfg.ControlPlane.Endpoint = endpoint
	cfg.ControlPlane.NodeID = "hk-test-01"
	cfg.ControlPlane.APIKeyPath = filepath.Join(tempDir, "api_key")
	cfg.ControlPlane.ProfilesPath = filepath.Join(tempDir, "profiles")
	cfg.Logging.BufferPath = filepath.Join(tempDir, "buffer")
	cfg.Node.Name = "resolver-test-01"
	cfg.Node.Region = "ap-east-1"
	cfg.Node.Country = "HK"
	cfg.Node.SupportedProtocols = []string{"udp", "doh"}

	// 写入 api_key 文件供 LoadBearer 读取
	os.WriteFile(cfg.ControlPlane.APIKeyPath, []byte("ak_test_01"), 0644)

	return cfg
}
