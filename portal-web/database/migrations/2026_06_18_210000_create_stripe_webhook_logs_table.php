<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #75 — Stripe Webhook 日志。
 *
 * 唯一约束 event_id → 幂等保证。
 * 支付成功但订单未开通时，可基于此表排查。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('event_type', 50);
            $table->json('payload');
            $table->string('status', 20)->default('received')
                ->comment('received / processed / failed / ignored');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_logs');
    }
};
