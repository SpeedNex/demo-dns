<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // V2.3 收口：补回 dns_profile_rules 模型层使用的字段
        // 旧结构有 match_type / normalized_domain / enabled / category / created_by
        // dns_profile_rules 是从零创建，仅保留基础列
        Schema::table('profile_rules', function (Blueprint $table): void {
            $table->string('match_type', 20)->default('exact')->after('list_type');
            $table->string('normalized_domain', 255)->nullable()->after('domain');
            $table->boolean('enabled')->default(true)->after('action');
            $table->string('category', 64)->nullable()->after('enabled');
            $table->string('created_by', 32)->nullable()->after('category');
            $table->index(['profile_id', 'list_type', 'match_type', 'normalized_domain'], 'idx_profile_rules_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('profile_rules', function (Blueprint $table): void {
            $table->dropIndex('idx_profile_rules_lookup');
            $table->dropColumn(['match_type', 'normalized_domain', 'enabled', 'category', 'created_by']);
        });
    }
};
