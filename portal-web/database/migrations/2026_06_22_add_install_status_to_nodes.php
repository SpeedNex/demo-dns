<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22: 节点 install 状态记录
 *
 * 字段：
 *   install_status      - 'pending' | 'installed' | 'failed'
 *   last_installed_at   - 最近一次 install 完成时间
 *   last_listen_addr    - 最近一次 install 报告的监听地址
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->string('install_status', 20)->default('pending')->after('status');
            $table->timestamp('last_installed_at')->nullable()->after('install_status');
            $table->string('last_listen_addr', 80)->nullable()->after('last_installed_at');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropColumn(['install_status', 'last_installed_at', 'last_listen_addr']);
        });
    }
};
