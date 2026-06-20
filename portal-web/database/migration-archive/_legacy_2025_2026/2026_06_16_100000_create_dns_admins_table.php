<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 表名不再写死 `dns_` 前缀；全局前缀由 config/database.php
    // 的 `prefix` 字段提供（默认 `dns_`，可通过 DB_TABLE_PREFIX 覆盖）。
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->string('password_hash', 255);
            $table->string('role', 30)->default('admin');
            $table->string('status', 30)->default('active');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique('email', 'uniq_admins_email');
            $table->index('status', 'idx_admins_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
