<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // V2.3 收口：补回 dns_plans 模型层使用的字段
        // 旧 PlanCatalogService 期望 sort_order / is_featured / badge / features / limits
        // 新 dns_plans 是从零创建，仅保留基础列
        Schema::table('plans', function (Blueprint $table): void {
            $table->integer('sort_order')->default(0)->after('log_retention_days');
            $table->boolean('is_featured')->default(false)->after('sort_order');
            $table->string('badge', 60)->nullable()->after('is_featured');
            $table->json('features')->nullable()->after('badge');
            $table->json('limits')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['sort_order', 'is_featured', 'badge', 'features', 'limits']);
        });
    }
};
