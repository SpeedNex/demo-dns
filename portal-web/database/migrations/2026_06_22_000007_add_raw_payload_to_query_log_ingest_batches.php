<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22 — ClickHouse 切换为唯一日志源后，dns_query_log_ingest_batches 必须
 * 自身保留本次上报的原始 items JSON，作为 clickhouse:retry-failed-batches 的
 * 重试数据源（不再依赖 dns_query_log_entries 表）。
 *
 * 最小修改：
 *   1. 增加 raw_payload JSON NULL 列
 *   2. 不删除/重建表，向后兼容历史数据
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('query_log_ingest_batches', function (Blueprint $table): void {
            $table->json('raw_payload')->nullable()->after('forwarded_to_clickhouse');
        });
    }

    public function down(): void
    {
        Schema::table('query_log_ingest_batches', function (Blueprint $table): void {
            $table->dropColumn('raw_payload');
        });
    }
};
