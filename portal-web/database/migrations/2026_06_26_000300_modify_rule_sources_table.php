<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rule_sources', 'homepage')) {
            return;
        }

        // category 从 enum 改为 varchar(60)
        DB::statement("ALTER TABLE dns_rule_sources MODIFY category VARCHAR(60) DEFAULT 'custom'");

        Schema::table('rule_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('rule_sources', 'source_type')) {
                $table->string('source_type', 40)->default('threat_feed')->after('category');
            }
            if (! Schema::hasColumn('rule_sources', 'description')) {
                $table->string('description', 500)->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('rule_sources', 'homepage')) {
                $table->string('homepage', 500)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rule_sources', function (Blueprint $table) {
            if (Schema::hasColumn('rule_sources', 'homepage')) {
                $table->dropColumn('homepage');
            }
            if (Schema::hasColumn('rule_sources', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('rule_sources', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });

        // 恢复原始 enum 类型
        DB::statement("ALTER TABLE dns_rule_sources MODIFY category ENUM('security','privacy','parental','custom') DEFAULT 'custom'");
    }
};
