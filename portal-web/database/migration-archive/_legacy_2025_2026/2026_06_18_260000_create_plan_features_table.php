<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #73 — 会员功能中心。
 *
 * plan_code → features map。
 * Resolver 只认 subscription + plan_features。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code', 30)->unique();
            $table->json('features')->comment('ad_block / parental_control / query_log / encrypted_dns ...');
            $table->unsignedInteger('monthly_query_limit')->nullable()
                ->comment('null = unlimited');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
