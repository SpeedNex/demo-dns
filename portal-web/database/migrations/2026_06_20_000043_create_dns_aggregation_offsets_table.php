<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aggregation_offsets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('topic', 80);
            $table->dateTime('window_start');
            $table->dateTime('processed_at');
            $table->unsignedBigInteger('record_count')->default(0);
            $table->enum('status', ['pending','processing','done','failed'])->default('pending');
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
            $table->unique(['topic','window_start'], 'uniq_agg_topic_window');
            $table->index('status', 'idx_agg_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregation_offsets');
    }
};
