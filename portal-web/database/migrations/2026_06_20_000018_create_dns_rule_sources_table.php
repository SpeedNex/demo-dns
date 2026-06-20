<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rule_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('url', 500)->nullable();
            $table->enum('format', ['domains','hosts','adblock','json'])->default('domains');
            $table->enum('category', ['security','privacy','parental','custom'])->default('custom');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('last_sync_status', ['pending','ok','failed'])->default('pending');
            $table->string('last_sync_message', 500)->nullable();
            $table->unsignedBigInteger('item_count')->default(0);
            $table->timestamps();
            $table->unique('code', 'uniq_rule_sources_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_sources');
    }
};
