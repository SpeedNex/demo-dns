package logging

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"sync/atomic"
	"testing"
	"time"
)

func TestFlushSendsQueryLogBatch(t *testing.T) {
	tempDir := t.TempDir()

	var flushed atomic.Bool
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// 2026-06-22 改造：统一 Token 鉴权，删除 HMAC 头检查。
		if got := r.Header.Get("Authorization"); got != "Bearer ak_test_01" {
			t.Fatalf("unexpected auth header: %s", got)
		}

		var payload map[string]any
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("decode payload: %v", err)
		}

		if payload["node_id"] != "hk-test-01" {
			t.Fatalf("unexpected node_id: %v", payload["node_id"])
		}

		items, ok := payload["items"].([]any)
		if !ok || len(items) != 1 {
			t.Fatalf("unexpected items payload: %#v", payload["items"])
		}

		w.Header().Set("Content-Type", "application/json")
		// 与生产 portal-web QueryLogIngestService::accept() 真实响应一致：
		// 必须含 accepted=true；resolver 严格校验 ACK，否则视为失败保留 buffer。
		_, _ = w.Write([]byte(`{"data":{"accepted":true,"batch_id":"batch_1","received_count":1,"duplicate":false,"content_sha256":"sha256:abc","ack":{"ack_id":"ack_1","stored_count":1,"checksum":"sha256:abc","confirmed_at":"2026-07-06T00:00:00Z"}}}`))
	}))
	defer server.Close()

	buffer := NewBuffer(
		filepath.Join(tempDir, "buffer"),
		server.URL,
		100,
		time.Second,
		Credentials{
			NodeID: "hk-test-01",
			APIKey: "ak_test_01",
		},
		func(time.Time) { flushed.Store(true) },
	)
	if buffer == nil {
		t.Fatal("expected buffer to be created with valid credentials")
	}

	buffer.Append(LogEntry{ProfileUID: "prf_01", Domain: "openai.com", Action: "ALLOW"})
	buffer.Flush()

	if !flushed.Load() {
		t.Fatal("expected flush callback to be invoked")
	}
}

func TestFlushWritesLocalBufferWhenUploadFails(t *testing.T) {
	tempDir := t.TempDir()
	bufferDir := filepath.Join(tempDir, "buffer")

	buffer := NewBuffer(
		bufferDir,
		"http://127.0.0.1:1",
		100,
		time.Second,
		Credentials{
			NodeID: "hk-test-01",
			APIKey: "ak_test_01",
		},
		nil,
	)
	if buffer == nil {
		t.Fatal("expected buffer to be created with valid credentials")
	}

	buffer.Append(LogEntry{ProfileUID: "prf_01", Domain: "blocked.example", Action: "BLOCK"})
	buffer.Flush()

	files, err := filepath.Glob(filepath.Join(bufferDir, "query-log-*.jsonl"))
	if err != nil {
		t.Fatalf("glob buffer files: %v", err)
	}
	if len(files) != 1 {
		t.Fatalf("expected one buffered file, got %d", len(files))
	}
}

func TestNewBufferReturnsNilWhenCredentialsMissing(t *testing.T) {
	tempDir := t.TempDir()

	if got := NewBuffer(
		filepath.Join(tempDir, "buffer"),
		"http://127.0.0.1:1",
		100,
		time.Second,
		Credentials{NodeID: "hk-test-01"}, // APIKey missing
		nil,
	); got != nil {
		t.Fatal("expected nil when api_key is missing")
	}

	if got := NewBuffer(
		filepath.Join(tempDir, "buffer"),
		"http://127.0.0.1:1",
		100,
		time.Second,
		Credentials{APIKey: "ak_test_01"}, // NodeID missing
		nil,
	); got != nil {
		t.Fatal("expected nil when node_id is missing")
	}
}
