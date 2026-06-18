// Package logging 实现 DNS 查询日志的本地缓冲与批量上报。
// 凭据完全来自 dns-resolver 启动时由 console 预签发的 APIKey / Secret，
// 不再从磁盘 identity 文件读取任何信息。
package logging

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"
)

type LogEntry struct {
	ProfileUID     string `json:"profile_id"`
	DeviceUID      string `json:"device_id"`
	Domain         string `json:"query_name"`
	Action         string `json:"action"`
	Reason         string `json:"reason"`
	Category       string `json:"category"`
	ClientIP       string `json:"client_ip"`
	QueryType      string `json:"query_type"`
	ResponseCode   int    `json:"rcode"`
	ResponseTimeMs int64  `json:"latency_ms"`
	QueriedAt      int64  `json:"queried_at"`
}

// Credentials 是 console 预签发凭据在日志上报场景下的最小投影。
type Credentials struct {
	NodeID string
	APIKey string
	Secret string
}

type Buffer struct {
	mu       sync.Mutex
	entries  []LogEntry
	maxSize  int
	bufPath  string
	cpURL    string
	client   *http.Client
	flushInt time.Duration
	cred     Credentials
	onFlush  func(time.Time)
	direct   DirectWriter // UI.md #46 — optional ClickHouse direct writer
	usage    []UsageEvent // UI.md #47 — independent usage-event queue
}

// NewBuffer 构造一个日志缓冲器，调用方必须传入已校验的控制面凭据。
// 任何凭据字段为空都会返回 nil，调用方应直接拒绝启动。
func NewBuffer(bufPath, cpURL string, maxSize int, flushInterval time.Duration, cred Credentials, onFlush func(time.Time)) *Buffer {
	if cred.NodeID == "" || cred.APIKey == "" || cred.Secret == "" {
		log.Printf("log buffer disabled: control plane credentials are missing")
		return nil
	}

	b := &Buffer{
		entries:  make([]LogEntry, 0, 1000),
		maxSize:  maxSize,
		bufPath:  bufPath,
		cpURL:    cpURL,
		flushInt: flushInterval,
		cred:     cred,
		onFlush:  onFlush,
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}

	b.replayBuffer()

	return b
}

func (b *Buffer) Append(entry LogEntry) {
	if b == nil {
		return
	}
	b.mu.Lock()
	defer b.mu.Unlock()

	b.entries = append(b.entries, entry)
	if len(b.entries) >= b.maxSize {
		go b.Flush()
	}
}

func (b *Buffer) StartFlusher(ctx context.Context) {
	if b == nil {
		return
	}
	ticker := time.NewTicker(b.flushInt)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			b.Flush()
		case <-ctx.Done():
			b.Flush()
			return
		}
	}
}

func (b *Buffer) Flush() {
	if b == nil {
		return
	}
	b.mu.Lock()
	if len(b.entries) == 0 {
		b.mu.Unlock()
		return
	}

	batch := append([]LogEntry(nil), b.entries...)
	b.entries = make([]LogEntry, 0, 1000)
	b.mu.Unlock()

	if err := b.sendBatch(batch); err != nil {
		log.Printf("Failed to send log batch: %v (writing to local buffer)", err)
		b.writeToDisk(batch)
		return
	}

	if b.onFlush != nil {
		b.onFlush(time.Now().UTC())
	}
}

func (b *Buffer) sendBatch(batch []LogEntry) error {
	payload := map[string]any{
		"batch_id": fmt.Sprintf("batch_%d", time.Now().UnixNano()),
		"node_id":  b.cred.NodeID,
		"sent_at":  time.Now().UTC().Format(time.RFC3339),
		"items":    batch,
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("marshal log batch: %w", err)
	}

	req, err := http.NewRequest(http.MethodPost, b.cpURL, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+b.cred.APIKey)
	req.Header.Set("X-Hmac-Key", b.cred.Secret)

	ts := fmt.Sprintf("%d", time.Now().Unix())
	bodyHash := sha256.Sum256(body)
	canonical := ts + "\n" + req.Method + "\n" + req.URL.Path + "\n" + hex.EncodeToString(bodyHash[:])
	mac := hmac.New(sha256.New, []byte(b.cred.Secret))
	mac.Write([]byte(canonical))
	req.Header.Set("X-Signature", hex.EncodeToString(mac.Sum(nil)))
	req.Header.Set("X-Timestamp", ts)

	nonce := make([]byte, 16)
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return fmt.Errorf("read nonce: %w", err)
	}
	req.Header.Set("X-Nonce", hex.EncodeToString(nonce))

	resp, err := b.client.Do(req)
	if err != nil {
		return fmt.Errorf("http post: %w", err)
	}
	defer resp.Body.Close()

	_, _ = io.Copy(io.Discard, resp.Body)
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("http status %d", resp.StatusCode)
	}

	return nil
}

func (b *Buffer) writeToDisk(batch []LogEntry) {
	if err := os.MkdirAll(b.bufPath, 0o755); err != nil {
		log.Printf("Failed to create log buffer dir: %v", err)
		return
	}

	filename := filepath.Join(b.bufPath, fmt.Sprintf("query-log-%d.jsonl", time.Now().UnixNano()))
	file, err := os.OpenFile(filename, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		log.Printf("Failed to open log buffer file: %v", err)
		return
	}
	defer file.Close()

	encoder := json.NewEncoder(file)
	for _, entry := range batch {
		if err := encoder.Encode(entry); err != nil {
			log.Printf("Failed to write log entry to disk: %v", err)
		}
	}
}

func (b *Buffer) replayBuffer() {
	files, err := filepath.Glob(filepath.Join(b.bufPath, "query-log-*.jsonl"))
	if err != nil {
		return
	}

	for _, file := range files {
		data, err := os.ReadFile(file)
		if err != nil {
			log.Printf("Failed to read buffer file %s: %v", file, err)
			continue
		}

		var entries []LogEntry
		for _, line := range bytes.Split(bytes.TrimSpace(data), []byte("\n")) {
			if len(line) == 0 {
				continue
			}
			var entry LogEntry
			if err := json.Unmarshal(line, &entry); err != nil {
				continue
			}
			entries = append(entries, entry)
		}

		if len(entries) == 0 {
			_ = os.Remove(file)
			continue
		}

		if err := b.sendBatch(entries); err != nil {
			log.Printf("Failed to replay buffer file %s: %v (will retry)", file, err)
			return
		}

		_ = os.Remove(file)
		if b.onFlush != nil {
			b.onFlush(time.Now().UTC())
		}
	}
}

// ----------------------------------------------------------------------------
// UI.md #46 / #47: ClickHouse direct write + independent UsageEvent
// ----------------------------------------------------------------------------

// CHInserter is the subset of the real ClickHouse client that the
// logging package can call.  Decoupling it from the clickhouse.Client
// concrete type keeps this package dependency-free.
type CHInserter interface {
	BatchInsertUsage(ctx context.Context, rows []UsageEvent) error
}

// DirectWriter is the contract the log buffer needs from a ClickHouse
// client.  The real implementation lives in internal/clickhouse/client.go;
// tests can stub it.  The two methods are intentionally tiny so wiring
// the existing log path does not require any rewrite.
type DirectWriter interface {
	BatchInsert(ctx context.Context, entries []DirectLogEntry) error
	BatchInsertUsage(ctx context.Context, events []UsageEvent) error
}

// DirectLogEntry is a stable, minimal subset of the existing LogEntry
// shape that the ClickHouse writer understands.
type DirectLogEntry struct {
	Timestamp      time.Time
	ProfileID      string
	DeviceID       string
	Domain         string
	QueryType      string
	Action         string
	Reason         string
	Category       string
	ResponseTimeMs int64
	Rcode          int
}

// UsageEvent is written to ClickHouse independently of the dedup
// window on the query-log path (UI.md #47).  Each domain hit produces
// exactly one usage event regardless of retransmits.
type UsageEvent struct {
	EventID    string
	ProfileID  string
	UserID     string
	DeviceID   string
	Domain     string
	BytesIn    int64
	BytesOut   int64
	OccurredAt time.Time
}

func (b *Buffer) SetDirectWriter(w DirectWriter) { b.direct = w }

// FlushDirect is the additive CH write path.  The existing Flush() /
// sendBatch() flow is untouched; this hook is for callers that want
// the lower-latency direct insert (UI.md #46).
func (b *Buffer) FlushDirect(entries []LogEntry) error {
	if b == nil || b.direct == nil {
		return nil
	}
	out := make([]DirectLogEntry, 0, len(entries))
	for _, e := range entries {
		out = append(out, DirectLogEntry{
			Timestamp:      time.Unix(e.QueriedAt, 0).UTC(),
			ProfileID:      e.ProfileUID,
			DeviceID:       e.DeviceUID,
			Domain:         e.Domain,
			QueryType:      e.QueryType,
			Action:         e.Action,
			Reason:         e.Reason,
			Category:       e.Category,
			ResponseTimeMs: e.ResponseTimeMs,
			Rcode:          e.ResponseCode,
		})
	}
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	return b.direct.BatchInsert(ctx, out)
}

// RecordUsage appends a single usage event to the in-memory queue and
// flushes when full.  The queue is intentionally separate from the
// query-log dedup path so that 5-second retransmits are still counted
// (UI.md #47).
func (b *Buffer) RecordUsage(ev UsageEvent) {
	if b == nil {
		return
	}
	b.mu.Lock()
	b.usage = append(b.usage, ev)
	needFlush := len(b.usage) >= b.maxSize
	b.mu.Unlock()
	if needFlush {
		go b.FlushUsage()
	}
}

func (b *Buffer) FlushUsage() {
	if b == nil || b.direct == nil {
		return
	}
	b.mu.Lock()
	if len(b.usage) == 0 {
		b.mu.Unlock()
		return
	}
	batch := append([]UsageEvent(nil), b.usage...)
	b.usage = b.usage[:0]
	b.mu.Unlock()
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := b.direct.BatchInsertUsage(ctx, batch); err != nil {
		log.Printf("usage flush failed: %v", err)
	}
}
