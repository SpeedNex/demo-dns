// Package logging 实现 DNS 查询日志的本地缓冲与批量上报。
// 凭据完全来自 dns-resolver 启动时由 console 预签发的 APIKey，
// 统一使用 Bearer Token 鉴权，不再从磁盘 identity 文件读取任何信息。
package logging

import (
	"bytes"
	"context"
	"crypto/hmac"
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
	DeviceType     string `json:"device_type,omitempty"`
	Domain         string `json:"domain"`
	Action         string `json:"action"`
	Reason         string `json:"reason"`
	Category       string `json:"category"`
	ClientIP       string `json:"client_ip"`
	QueryType      string `json:"query_type"`
	ResponseCode   int    `json:"rcode"`
	ResponseTimeMs int64  `json:"latency_ms"`
	QueriedAt      int64  `json:"queried_at"`
	// 2026-06-22: 上报协议 doh/dot/udp/tcp，便于按协议分账/告警
	Protocol string `json:"protocol,omitempty"`
	// 2026-07-09: HMAC-SHA256 签名，防止设备指纹伪造
	// 签名内容：device_uid|device_type|client_ip|profile_id
	// 密钥：Node 的 api_key
	Signature string `json:"device_sig,omitempty"`
}

// Credentials 是 console 预签发凭据在日志上报场景下的最小投影。
// 2026-06-22 改造：删除 Secret 字段，统一 Token 鉴权。
type Credentials struct {
	NodeID        string
	APIKey        string
	SigningSecret string // 2026-07-09: HMAC 签名密钥，防设备指纹伪造
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
	// P3-13: 可选 metrics 回调，用于记录刷新失败
	onFlushFailed func()
}

// NewBuffer 构造一个日志缓冲器，调用方必须传入已校验的控制面凭据。
// 任何凭据字段为空都会返回 nil，调用方应直接拒绝启动。
func NewBuffer(bufPath, cpURL string, maxSize int, flushInterval time.Duration, cred Credentials, onFlush func(time.Time)) *Buffer {
	if cred.NodeID == "" || cred.APIKey == "" {
		log.Printf("[日志] 缓冲禁用 控制面凭据缺失")
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
		log.Printf("[日志] 追加调用在空缓冲上 domain=%s", entry.Domain)
		return
	}

	// P2-10 修复：截断超长字段，防止日志缓冲区膨胀
	// DNS 域名最大 255 字节，reason/category 限制 128 字节
	if len(entry.Domain) > 255 {
		entry.Domain = entry.Domain[:255]
	}
	if len(entry.Reason) > 128 {
		entry.Reason = entry.Reason[:128]
	}
	if len(entry.Category) > 128 {
		entry.Category = entry.Category[:128]
	}
	if len(entry.DeviceUID) > 64 {
		entry.DeviceUID = entry.DeviceUID[:64]
	}
	if len(entry.DeviceType) > 32 {
		entry.DeviceType = entry.DeviceType[:32]
	}

	// 2026-07-09: 计算设备数据 HMAC 签名，防止客户端伪造 device_uid
	// 签名内容：device_uid|device_type|client_ip|profile_id
	// 密钥：Node 的 signing_secret
	if b.cred.SigningSecret != "" {
		data := entry.DeviceUID + "|" + entry.DeviceType + "|" + entry.ClientIP + "|" + entry.ProfileUID
		h := hmac.New(sha256.New, []byte(b.cred.SigningSecret))
		h.Write([]byte(data))
		entry.Signature = hex.EncodeToString(h.Sum(nil))
	}

	b.mu.Lock()
	defer b.mu.Unlock()

	b.entries = append(b.entries, entry)
	log.Printf("[日志] 追加 domain=%s action=%s 队列=%d", entry.Domain, entry.Action, len(b.entries))
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
	log.Printf("[日志] 刷新发送 批次大小=%d", len(batch))

	if err := b.sendBatch(batch); err != nil {
		log.Printf("[日志] 发送失败 err=%v 写入本地缓冲", err)
		b.writeToDisk(batch)
		if b.onFlushFailed != nil {
			b.onFlushFailed()
		}
		return
	}
	log.Printf("[日志] 刷新发送成功 大小=%d", len(batch))

	if b.onFlush != nil {
		b.onFlush(time.Now().UTC())
	}
}

// AckResponse 是 portal-web 返回的显式 ACK 回执，用于确认 buffer 可安全删除。
type AckResponse struct {
	Accepted      bool   `json:"accepted"`
	BatchID       string `json:"batch_id"`
	ReceivedCount int    `json:"received_count"`
	Ack           struct {
		AckID       string `json:"ack_id"`
		StoredCount int    `json:"stored_count"`
		Checksum    string `json:"checksum"`
		ConfirmedAt string `json:"confirmed_at"`
	} `json:"ack"`
}

// dataEnvelope 是 portal-web 一些接口使用的 `{data: {...}}` 包装结构。
// 修复 2026-07-06 #13：portal-web QueryLogController 当前返回 `{data:{accepted:true,...}}`，
// 而非 buffer.go 期望的顶层结构。需要同时兼容两种形态，并要求 ACK accepted=true 才视为成功。
type dataEnvelope struct {
	Data AckResponse `json:"data"`
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
	// 只记录 batch_id 和数量，不打印查询明细以保护用户隐私
	log.Printf("[日志] 发送批次 batch_id=%s 条目数=%d", payload["batch_id"], len(batch))

	req, err := http.NewRequest(http.MethodPost, b.cpURL, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}
	req.Header.Set("Content-Type", "application/json")
	// 2026-06-22 改造：统一 Token 鉴权，删除 HMAC 头。
	if b.cred.APIKey != "" {
		req.Header.Set("Authorization", "Bearer "+b.cred.APIKey)
	}

	resp, err := b.client.Do(req)
	if err != nil {
		return fmt.Errorf("http post: %w", err)
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)
	// 只记录 URL、状态码和响应长度，不打印响应体以保护隐私
	log.Printf("[日志] 发送批次 url=%s status=%d 响应长度=%d", b.cpURL, resp.StatusCode, len(respBody))
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("http status %d", resp.StatusCode)
	}

	// P0 修复: 解析显式 ACK 回执，确认 buffer 可安全删除
	// 修复 2026-07-06 #13：必须确认 accepted=true；并兼容 `{data:{...}}` 包装结构。
	// 原代码即使未解析到 ACK，只要 HTTP 200 就认为成功，导致本地 buffer 被错误删除、日志丢失。
	var ack AckResponse
	if err := json.Unmarshal(respBody, &ack); err != nil || !ack.Accepted {
		// 回退尝试 `{data:{...}}` 包装结构
		var wrapped dataEnvelope
		if err2 := json.Unmarshal(respBody, &wrapped); err2 != nil || !wrapped.Data.Accepted {
			return fmt.Errorf("ack not accepted: raw=%s", string(respBody))
		}
		ack = wrapped.Data
	}
	log.Printf("[日志] ACK 确认 ack_id=%s stored=%d checksum=%s",
		ack.Ack.AckID, ack.Ack.StoredCount, ack.Ack.Checksum)

	return nil
}

func (b *Buffer) writeToDisk(batch []LogEntry) {
	if err := os.MkdirAll(b.bufPath, 0o755); err != nil {
		log.Printf("[日志] 创建缓冲目录失败 err=%v", err)
		return
	}

	filename := filepath.Join(b.bufPath, fmt.Sprintf("query-log-%d.jsonl", time.Now().UnixNano()))
	file, err := os.OpenFile(filename, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		log.Printf("[日志] 打开缓冲文件失败 err=%v", err)
		return
	}
	defer file.Close()

	encoder := json.NewEncoder(file)
	for _, entry := range batch {
		if err := encoder.Encode(entry); err != nil {
			log.Printf("[日志] 写入条目到磁盘失败 err=%v", err)
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
			log.Printf("[日志] 读取缓冲文件失败 path=%s err=%v", file, err)
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
			log.Printf("[日志] 重放缓冲文件失败 path=%s err=%v 将重试", file, err)
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
	// 2026-06-22: 协议透传，CH 列名 protocol
	Protocol string
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

// SetOnFlushFailed 2026-07-09: 设置刷新失败回调，用于监控指标上报。
// 回调在 sendBatch 失败且已写入本地磁盘后由 Flush() 调用。
func (b *Buffer) SetOnFlushFailed(fn func()) { b.onFlushFailed = fn }

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
			Protocol:       e.Protocol,
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
		log.Printf("[日志] usage刷新失败 err=%v", err)
	}
}
