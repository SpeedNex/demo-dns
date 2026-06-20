<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->enum('category', ['free','pro','business','education','enterprise'])->default('free');
            $table->enum('status', ['active','archived'])->default('active');
            $table->unsignedBigInteger('monthly_query_limit')->nullable();
            $table->integer('profiles_limit')->nullable();
            $table->integer('devices_limit')->nullable();
            $table->integer('log_retention_days')->default(24);
            $table->timestamps();
            $table->unique('code', 'uniq_plans_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
