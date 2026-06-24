-- ClickHouse Migration for OcerDNS
-- 用于已运行 init.sql 但与当前 PHP 代码字段不一致的实例。
-- 所有语句都是 idempotent,可以重复执行。
--
-- 用法:clickhouse-client < migrate.sql

-- 主表
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS event_id   String;
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS event_time DateTime64(3);
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS timestamp  DateTime64(3);
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS user_id    String;
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS device_id  String;
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS query_name String;
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS action     LowCardinality(String);
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS reason     LowCardinality(String);
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS protocol   LowCardinality(String);
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS rcode      UInt16;
ALTER TABLE ocer_dns.dns_logs ADD COLUMN IF NOT EXISTS latency_ms Float32;

-- 索引
ALTER TABLE ocer_dns.dns_logs ADD INDEX IF NOT EXISTS idx_query_name query_name TYPE bloom_filter(0.01) GRANULARITY 2;
ALTER TABLE ocer_dns.dns_logs ADD INDEX IF NOT EXISTS idx_client_ip  client_ip  TYPE bloom_filter(0.01) GRANULARITY 2;
