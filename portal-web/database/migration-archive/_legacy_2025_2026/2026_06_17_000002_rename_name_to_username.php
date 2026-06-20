<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 统一用户名字段：name -> username
     *
     * 影响范围：
     * - dns_admins.name -> dns_admins.username
     * - dns_users.name -> dns_users.username
     */
    public function up(): void
    {
        if (Schema::hasColumn('admins', 'name')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->renameColumn('name', 'username');
            });
        }

        if (Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('name', 'username');
            });
        }
    }

    public function down(): void
    {
        // 回滚：username -> name
        if (Schema::hasColumn('admins', 'username')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->renameColumn('username', 'name');
            });
        }

        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->renameColumn('username', 'name');
            });
        }
    }
};
