<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 之前 admin 端的 RBAC 表（navigation_catalogs / admin_permissions /
    // admin_roles / admin_role_permissions / admin_role_nav_rules /
    // admin_user_roles）只在模型里写死 `dns_` 前缀，从来没有对应的迁移。
    // 全部表名走 config/database.php 的 `prefix`，这里只写裸名。
    public function up(): void
    {
        Schema::create('navigation_catalogs', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('parent_key', 80)->nullable();
            $table->string('title', 200);
            $table->string('path', 300);
            $table->string('icon', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('permission_code', 80)->nullable();
            $table->timestamps();

            $table->index('parent_key', 'idx_navigation_catalogs_parent_key');
            $table->index('permission_code', 'idx_navigation_catalogs_permission_code');
        });

        Schema::create('admin_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('resource', 80);
            $table->string('action', 80);
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->index(['resource', 'action'], 'idx_admin_permissions_resource_action');
        });

        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);

            $table->foreign('permission_id')->references('id')->on('admin_permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
        });

        Schema::create('admin_role_nav_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->string('nav_key', 80);
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['role_id', 'nav_key'], 'uniq_admin_role_nav_rules_role_nav');
            $table->foreign('role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
            $table->foreign('nav_key')->references('key')->on('navigation_catalogs')->cascadeOnDelete();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->string('admin_id', 36);
            $table->unsignedBigInteger('role_id');
            $table->string('assigned_by', 36)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->primary(['admin_id', 'role_id']);

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('admin_roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_role_nav_rules');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('navigation_catalogs');
    }
};
