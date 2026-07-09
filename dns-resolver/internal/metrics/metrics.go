package metrics

import (
	"fmt"
	"net/http"
	"sync/atomic"
)

// Metrics collects and exposes node-level metrics for Prometheus scraping.
type Metrics struct {
	queriesTotal    atomic.Int64
	blockedTotal    atomic.Int64
	allowedTotal    atomic.Int64
	errorsTotal     atomic.Int64
	activeProfiles  atomic.Int64
	currentQPS      atomic.Int64
	// P3-13 新增：日志缓冲区刷新失败计数（ClickHouse 写入失败）
	logFlushFailed  atomic.Int64
	// P3-13 新增：配额 blocklist 命中计数
	quotaBlockHits  atomic.Int64
}

// New creates a new Metrics collector.
func New() *Metrics {
	return &Metrics{}
}

// IncQueries increments the total query counter.
func (m *Metrics) IncQueries() {
	m.queriesTotal.Add(1)
}

// IncBlocked increments the blocked query counter.
func (m *Metrics) IncBlocked() {
	m.blockedTotal.Add(1)
}

// IncAllowed increments the allowed query counter.
func (m *Metrics) IncAllowed() {
	m.allowedTotal.Add(1)
}

// IncErrors increments the error counter.
func (m *Metrics) IncErrors() {
	m.errorsTotal.Add(1)
}

// SetActiveProfiles sets the number of active profiles.
func (m *Metrics) SetActiveProfiles(n int64) {
	m.activeProfiles.Store(n)
}

// SetQPS sets the current QPS value.
func (m *Metrics) SetQPS(qps int64) {
	m.currentQPS.Store(qps)
}

// IncLogFlushFailed increments the log flush failure counter.
func (m *Metrics) IncLogFlushFailed() {
	m.logFlushFailed.Add(1)
}

// IncQuotaBlockHits increments the quota blocklist hit counter.
func (m *Metrics) IncQuotaBlockHits() {
	m.quotaBlockHits.Add(1)
}

// Snapshot returns a point-in-time snapshot of all metrics.
func (m *Metrics) Snapshot() map[string]int64 {
	return map[string]int64{
		"queries_total":    m.queriesTotal.Load(),
		"blocked_total":    m.blockedTotal.Load(),
		"allowed_total":    m.allowedTotal.Load(),
		"errors_total":     m.errorsTotal.Load(),
		"active_profiles":  m.activeProfiles.Load(),
		"current_qps":      m.currentQPS.Load(),
		"log_flush_failed": m.logFlushFailed.Load(),
		"quota_block_hits": m.quotaBlockHits.Load(),
	}
}

// PrometheusHandler returns an HTTP handler for Prometheus scraping.
func (m *Metrics) PrometheusHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		snap := m.Snapshot()
		w.Header().Set("Content-Type", "text/plain; version=0.0.4")

		fmt.Fprintln(w, "# HELP dns_queries_total Total DNS queries processed")
		fmt.Fprintln(w, "# TYPE dns_queries_total counter")
		fmt.Fprintf(w, "dns_queries_total %d\n", snap["queries_total"])

		fmt.Fprintln(w, "# HELP dns_blocked_total Total DNS queries blocked")
		fmt.Fprintln(w, "# TYPE dns_blocked_total counter")
		fmt.Fprintf(w, "dns_blocked_total %d\n", snap["blocked_total"])

		fmt.Fprintln(w, "# HELP dns_allowed_total Total DNS queries allowed")
		fmt.Fprintln(w, "# TYPE dns_allowed_total counter")
		fmt.Fprintf(w, "dns_allowed_total %d\n", snap["allowed_total"])

		fmt.Fprintln(w, "# HELP dns_errors_total Total DNS resolution errors")
		fmt.Fprintln(w, "# TYPE dns_errors_total counter")
		fmt.Fprintf(w, "dns_errors_total %d\n", snap["errors_total"])

		fmt.Fprintln(w, "# HELP dns_active_profiles Number of active loaded profiles")
		fmt.Fprintln(w, "# TYPE dns_active_profiles gauge")
		fmt.Fprintf(w, "dns_active_profiles %d\n", snap["active_profiles"])

		fmt.Fprintln(w, "# HELP dns_current_qps Current queries per second")
		fmt.Fprintln(w, "# TYPE dns_current_qps gauge")
		fmt.Fprintf(w, "dns_current_qps %d\n", snap["current_qps"])

		fmt.Fprintln(w, "# HELP dns_log_flush_failed Total log flush failures")
		fmt.Fprintln(w, "# TYPE dns_log_flush_failed counter")
		fmt.Fprintf(w, "dns_log_flush_failed %d\n", snap["log_flush_failed"])

		fmt.Fprintln(w, "# HELP dns_quota_block_hits Total quota blocklist hits")
		fmt.Fprintln(w, "# TYPE dns_quota_block_hits counter")
		fmt.Fprintf(w, "dns_quota_block_hits %d\n", snap["quota_block_hits"])
	}
}
