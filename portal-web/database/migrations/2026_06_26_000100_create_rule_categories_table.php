<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('rule_categories')) {
            return;
        }

        Schema::create('rule_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('name_en', 100);
            $table->string('description', 500)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('parent_code', 40)->nullable();
            $table->enum('group', ['threat','privacy','family','custom'])->default('threat');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_categories');
    }
};
