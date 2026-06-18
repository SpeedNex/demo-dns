// Package clickhouse provides a client for writing DNS logs to ClickHouse.
package clickhouse

import (
	"context"
	"fmt"
	"log"
	"time"

	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/ClickHouse/clickhouse-go/v2/lib/driver"
)

// Client wraps a ClickHouse connection for DNS log writes.
type Client struct {
	conn driver.Conn
}

// NewClient creates a new ClickHouse client.
func NewClient(endpoint string) (*Client, error) {
	conn, err := clickhouse.Open(&clickhouse.Options{
		Addr: []string{endpoint},
		Auth: clickhouse.Auth{
			Database: "default",
			Username: "default",
			Password: "",
		},
		Settings: clickhouse.Settings{
			"max_execution_time": 60,
		},
		Compression: &clickhouse.Compression{
			Method: clickhouse.CompressionLZ4,
		},
		DialTimeout:  10 * time.Second,
		MaxOpenConns: 5,
	})
	if err != nil {
		return nil, fmt.Errorf("clickhouse open: %w", err)
	}

	ctx := context.Background()
	if err := conn.Ping(ctx); err != nil {
		return nil, fmt.Errorf("clickhouse ping: %w", err)
	}

	log.Printf("Connected to ClickHouse at %s", endpoint)
	return &Client{conn: conn}, nil
}

// DDL returns the CREATE TABLE statements for the DNS log schema.
func DDL() []string {
	return []string{
		`CREATE TABLE IF NOT EXISTS dns_logs (
			timestamp          DateTime,
			profile_id         String,
			user_id            UInt64,
			device_id          String,
			domain             String,
			query_type         String,
			action             String,
			reason             String,
			category           String,
			node_id            String,
			node_country       String,
			client_ip_hash     String,
			response_time_ms   UInt16,
			rcode              String,
			profile_version    UInt32,
			rule_id            String
		) ENGINE = MergeTree()
		PARTITION BY toDate(timestamp)
		ORDER BY (timestamp, profile_id, domain)
		TTL timestamp + INTERVAL 30 DAY
		SETTINGS index_granularity = 8192`,

		// UI.md #47: independent usage event table — never deduped.
		`CREATE TABLE IF NOT EXISTS usage_events (
			event_id    String,
			profile_id  String,
			user_id     String,
			device_id   String,
			domain      String,
			bytes_in    UInt64,
			bytes_out   UInt64,
			occurred_at DateTime
		) ENGINE = MergeTree()
		PARTITION BY toDate(occurred_at)
		ORDER BY (occurred_at, profile_id)
		TTL occurred_at + INTERVAL 90 DAY
		SETTINGS index_granularity = 8192`,

		`CREATE MATERIALIZED VIEW IF NOT EXISTS dns_hourly_stats
		ENGINE = SummingMergeTree()
		ORDER BY (timestamp, profile_id)
		POPULATE
		AS SELECT
			toStartOfHour(timestamp) AS timestamp,
			profile_id,
			count()                          AS query_count,
			countIf(action = 'BLOCK')        AS blocked_count,
			countIf(action = 'ALLOW')        AS allowed_count,
			avg(response_time_ms)            AS avg_response_ms,
			quantile(0.95)(response_time_ms) AS p95_response_ms
		FROM dns_logs
		GROUP BY timestamp, profile_id`,

		`CREATE MATERIALIZED VIEW IF NOT EXISTS dns_daily_stats
		ENGINE = SummingMergeTree()
		ORDER BY (timestamp, profile_id)
		POPULATE
		AS SELECT
			toDate(timestamp) AS timestamp,
			profile_id,
			count()                          AS query_count,
			countIf(action = 'BLOCK')        AS blocked_count,
			uniq(domain)                     AS unique_domains
		FROM dns_logs
		GROUP BY timestamp, profile_id`,
	}
}

// InitSchema creates the ClickHouse tables and materialized views.
func (c *Client) InitSchema(ctx context.Context) error {
	for _, ddl := range DDL() {
		if err := c.conn.Exec(ctx, ddl); err != nil {
			return fmt.Errorf("exec ddl: %w", err)
		}
	}
	log.Println("ClickHouse schema initialized")
	return nil
}

// LogEntry matches the NATS dns.logs event payload.
type LogEntry struct {
	Timestamp      time.Time `ch:"timestamp"`
	ProfileID      string    `ch:"profile_id"`
	UserID         uint64    `ch:"user_id"`
	DeviceID       string    `ch:"device_id"`
	Domain         string    `ch:"domain"`
	QueryType      string    `ch:"query_type"`
	Action         string    `ch:"action"`
	Reason         string    `ch:"reason"`
	Category       string    `ch:"category"`
	NodeID         string    `ch:"node_id"`
	NodeCountry    string    `ch:"node_country"`
	ClientIPHash   string    `ch:"client_ip_hash"`
	ResponseTimeMs uint16    `ch:"response_time_ms"`
	Rcode          string    `ch:"rcode"`
	ProfileVersion uint32    `ch:"profile_version"`
	RuleID         string    `ch:"rule_id"`
}

// BatchInsert inserts a batch of DNS log entries.
func (c *Client) BatchInsert(ctx context.Context, entries []LogEntry) error {
	if len(entries) == 0 {
		return nil
	}

	batch, err := c.conn.PrepareBatch(ctx, "INSERT INTO dns_logs")
	if err != nil {
		return fmt.Errorf("prepare batch: %w", err)
	}

	for _, e := range entries {
		if err := batch.AppendStruct(&e); err != nil {
			return fmt.Errorf("append struct: %w", err)
		}
	}

	return batch.Send()
}

// UsageEventRow matches the usage_events table DDL above.
type UsageEventRow struct {
	EventID    string    `ch:"event_id"`
	ProfileID  string    `ch:"profile_id"`
	UserID     string    `ch:"user_id"`
	DeviceID   string    `ch:"device_id"`
	Domain     string    `ch:"domain"`
	BytesIn    uint64    `ch:"bytes_in"`
	BytesOut   uint64    `ch:"bytes_out"`
	OccurredAt time.Time `ch:"occurred_at"`
}

// BatchInsertUsage writes a batch of usage events (UI.md #47).  This
// call is independent of the query-log dedup path.
func (c *Client) BatchInsertUsage(ctx context.Context, rows []UsageEventRow) error {
	if len(rows) == 0 {
		return nil
	}
	batch, err := c.conn.PrepareBatch(ctx, "INSERT INTO usage_events")
	if err != nil {
		return fmt.Errorf("prepare usage batch: %w", err)
	}
	for i := range rows {
		if err := batch.AppendStruct(&rows[i]); err != nil {
			return fmt.Errorf("append usage struct: %w", err)
		}
	}
	return batch.Send()
}

// Close closes the ClickHouse connection.
func (c *Client) Close() error {
	return c.conn.Close()
}
