<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_menu_rule', function (Blueprint $table): void {
            $table->id();
            $table->string('menu_key', 80)->unique()->comment('菜单唯一标识');
            $table->string('parent_key', 80)->nullable()->comment('父菜单标识');
            $table->string('title_key', 200)->comment('i18n 标题 key');
            $table->string('path', 300)->comment('路由路径');
            $table->string('icon', 100)->nullable()->comment('图标名称');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->boolean('visible')->default(true)->comment('是否可见');
            $table->string('permission_code', 80)->nullable()->comment('权限代码');
            $table->string('group_key', 50)->nullable()->comment('分组标识：service/monitor/user/finance/settings');
            $table->timestamps();

            $table->index('parent_key', 'idx_admin_menu_rule_parent');
            $table->index('group_key', 'idx_admin_menu_rule_group');
            $table->index('sort_order', 'idx_admin_menu_rule_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_menu_rule');
    }
};
