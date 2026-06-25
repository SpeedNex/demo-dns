package agent

import (
	"path/filepath"
	"testing"

	"ocer-dns/dns-resolver/internal/config"
)

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
