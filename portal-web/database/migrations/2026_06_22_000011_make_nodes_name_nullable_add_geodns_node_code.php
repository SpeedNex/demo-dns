<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 变更说明：
 * 1. dns_nodes.name 改为 nullable —— 与 node_code/node_alias 语义重叠，保留列不删，
 *    设为 nullable 避免 GeoDnsNode::create() 时不传 name 报错。
 * 2. dns_geodns 新增 node_code —— 字符串节点标识，便于直接识别。
 */
return new class extends Migration
{
    public function up(): void
    {
        // =========================================================
        // 1. dns_nodes.name → nullable
        // =========================================================
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}nodes` MODIFY COLUMN `name` varchar(120) NULL");

        // =========================================================
        // 2. dns_geodns 新增 node_code
        // =========================================================
        if (! Schema::hasColumn('geodns', 'node_code')) {
            Schema::table('geodns', function (Blueprint $table): void {
                $table->string('node_code', 64)->nullable()->after('id');
                $table->index('node_code', 'idx_geodns_node_code');
            });
        }
    }

    public function down(): void
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}nodes` MODIFY COLUMN `name` varchar(120) NOT NULL");

        if (Schema::hasColumn('geodns', 'node_code')) {
            Schema::table('geodns', function (Blueprint $table): void {
                $table->dropIndex('idx_geodns_node_code');
                $table->dropColumn('node_code');
            });
        }
    }
};