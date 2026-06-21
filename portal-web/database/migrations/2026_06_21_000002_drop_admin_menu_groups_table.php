<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_menu_groups');
    }

    public function down(): void
    {
        Schema::create('admin_menu_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key', 50)->unique()->comment('分组标识，如 service、monitor');
            $table->string('title_key', 200)->comment('分组标题的 i18n key');
            $table->string('icon', 100)->nullable()->comment('分组图标');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });
    }
};
