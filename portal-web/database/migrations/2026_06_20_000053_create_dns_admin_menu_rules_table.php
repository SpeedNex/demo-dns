<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // V2.3: 把 legacy admin_menu_rule 表迁到 V2 schema，保持原表名（Seeder/Model 引用不变）
        Schema::create('admin_menu_rule', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('menu_key', 80)->unique();
            $table->string('parent_key', 80)->nullable();
            $table->string('title_key', 200);
            $table->string('path', 300);
            $table->string('icon', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->string('permission_code', 80)->nullable();
            $table->string('group_key', 50)->nullable();
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
