<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_webhook_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 120);
            $table->string('event_type', 80);
            $table->json('payload');
            $table->boolean('signature_ok')->default(false);
            $table->enum('status', ['received','processed','failed','ignored'])->default('received');
            $table->string('error_message', 500)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unique('event_id', 'uniq_stripe_event');
            $table->index('status', 'idx_stripe_status');
            $table->index('event_type', 'idx_stripe_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_logs');
    }
};
