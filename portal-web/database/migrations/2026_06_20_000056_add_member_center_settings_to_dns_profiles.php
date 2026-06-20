<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // V2.3 收口：补回 Member-center 安全/隐私/家长设置 JSON 字段
        // 旧 `_legacy_2025_2026/2026_06_12_120000_add_member_center_settings` 引用的是无前缀 `profiles`
        // 新 dns_profiles 是从零创建，未包含这 3 个 JSON 列；Service 仍按字段写入
        Schema::table('profiles', function (Blueprint $table): void {
            $table->json('security_settings')->nullable()->after('security_enabled');
            $table->json('privacy_settings')->nullable()->after('privacy_enabled');
            $table->json('parental_settings')->nullable()->after('parental_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['security_settings', 'privacy_settings', 'parental_settings']);
        });
    }
};
