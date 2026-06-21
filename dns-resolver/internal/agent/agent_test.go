package agent

import (
	"crypto/sha256"
	"encoding/json"
	"fmt"
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

	var ackStatus atomic.Value
	var heartbeatSeen atomic.Bool
	var configPulled atomic.Bool

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
			if got := r.URL.Query().Get("node_id"); got != "hk-test-01" {
				t.Fatalf("unexpected node_id: %s", got)
			}
			if got := r.URL.Query().Get("current_version"); got != "0" {
				t.Fatalf("unexpected current_version: %s", got)
			}
			configPulled.Store(true)
			bundle := map[string]any{
				"version":      1,
				"generated_at": "2026-06-14T00:00:00Z",
				"expires_at":   "2026-06-14T00:10:00Z",
				"profiles": []any{
					map[string]any{
						"profile_id":     "prf_01",
						"user_id":        "usr_01",
						"version":        1,
						"default_action": "allow",
						"block_response": "nxdomain",
						"security":       map[string]any{"enabled": true},
						"adblock":        map[string]any{"enabled": false},
						"privacy":        map[string]any{"enabled": true},
						"parental":       map[string]any{"enabled": false},
						"rules": []any{
							map[string]any{
								"rule_id":           "rule_01",
								"list_type":         "deny",
								"match_type":        "exact",
								"domain":            "blocked.example",
								"normalized_domain": "blocked.example",
								"action":            "block",
								"category":          "custom",
								"enabled":           true,
							},
						},
						"quota": map[string]any{},
					},
				},
				"rulesets":  []any{},
				"upstreams": []any{map[string]any{"address": "1.1.1.1:53", "protocol": "udp"}},
				"runtime":   map[string]any{"dnssec_validate": false},
				"signature": nil,
			}
			canonical, err := marshalCanonical(bundle)
			if err != nil {
				t.Fatalf("marshal canonical: %v", err)
			}
			bundle["checksum"] = "sha256:" + sha256Hex(canonical)
			_ = json.NewEncoder(w).Encode(map[string]any{"data": bundle})
		case "/api/v1/node/dns-resolver/config/ack":
			var payload map[string]any
			_ = json.NewDecoder(r.Body).Decode(&payload)
			ackStatus.Store(payload["status"])
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write([]byte(`{"data":{"status":"ok"}}`))
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
	if !configPulled.Load() {
		t.Fatal("expected config to be pulled as part of heartbeat")
	}
	if got := ackStatus.Load(); got != "applied" {
		t.Fatalf("expected applied ack, got %v", got)
	}
	if got := agt.currentVersion(); got != 1 {
		t.Fatalf("expected current version 1, got %d", got)
	}
	if decision := engine.Match("blocked.example"); decision.Action != "BLOCK" {
		t.Fatalf("expected blocked.example to be blocked, got %s", decision.Action)
	}

	activeConfig := filepath.Join(cfg.ControlPlane.ProfilesPath, "active.json")
	if _, err := os.Stat(activeConfig); err != nil {
		t.Fatalf("expected active config file: %v", err)
	}
}

func TestChecksumMismatchDoesNotApplyConfig(t *testing.T) {
	t.Helper()

	var ackStatus atomic.Value

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/v1/node/heartbeat":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"ok":                           true,
					"latest_config_version":        2,
					"should_pull_config":           true,
					"config_endpoint":              "/api/v1/node/dns-resolver/config",
					"next_heartbeat_after_seconds": 30,
				},
			})
		case "/api/v1/node/dns-resolver/config":
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"version":      2,
					"checksum":     "sha256:deadbeef",
					"generated_at": "2026-06-14T00:00:00Z",
					"expires_at":   "2026-06-14T00:10:00Z",
					"profiles": []any{
						map[string]any{
							"profile_id":     "prf_02",
							"user_id":        "usr_02",
							"version":        2,
							"default_action": "allow",
							"block_response": "nxdomain",
							"security":       map[string]any{"enabled": true},
							"adblock":        map[string]any{"enabled": false},
							"privacy":        map[string]any{"enabled": true},
							"parental":       map[string]any{"enabled": false},
							"rules": []any{
								map[string]any{
									"rule_id":           "rule_02",
									"list_type":         "deny",
									"match_type":        "exact",
									"domain":            "malicious.example",
									"normalized_domain": "malicious.example",
									"action":            "block",
									"category":          "custom",
									"enabled":           true,
								},
							},
							"quota": map[string]any{},
						},
					},
					"rulesets":  []any{},
					"upstreams": []any{},
					"runtime":   map[string]any{},
					"signature": nil,
				},
			})
		case "/api/v1/node/dns-resolver/config/ack":
			var payload map[string]any
			_ = json.NewDecoder(r.Body).Decode(&payload)
			ackStatus.Store(payload["status"])
			_, _ = w.Write([]byte(`{"data":{"status":"ok"}}`))
		default:
			_, _ = w.Write([]byte(`{"data":{"ok":true}}`))
		}
	}))
	defer server.Close()

	cfg := testConfig(t, server.URL)
	engine := matching.NewEngine()
	agt := New(cfg, engine, metrics.New())

	agt.pullLatestConfig()

	if got := ackStatus.Load(); got != "failed" {
		t.Fatalf("expected failed ack, got %v", got)
	}
	if decision := engine.Match("malicious.example"); decision.Action != "ALLOW" {
		t.Fatalf("checksum mismatch should not apply rules, got %s", decision.Action)
	}
}

func testConfig(t *testing.T, endpoint string) *config.Config {
	t.Helper()

	tempDir := t.TempDir()
	cfg := config.Default()
	cfg.ControlPlane.Endpoint = endpoint
	cfg.ControlPlane.NodeID = "hk-test-01"
	cfg.ControlPlane.APIKey = "ak_test_01"
	cfg.ControlPlane.ProfilesPath = filepath.Join(tempDir, "profiles")
	cfg.Logging.BufferPath = filepath.Join(tempDir, "buffer")
	cfg.Node.Name = "resolver-test-01"
	cfg.Node.Region = "ap-east-1"
	cfg.Node.Country = "HK"
	cfg.Node.SupportedProtocols = []string{"udp", "doh"}

	return cfg
}

func sha256Hex(data []byte) string {
	hash := sha256.New()
	_, _ = hash.Write(data)
	return fmt.Sprintf("%x", hash.Sum(nil))
}
