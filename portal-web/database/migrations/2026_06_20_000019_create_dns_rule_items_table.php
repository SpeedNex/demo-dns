<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rule_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rule_source_id');
            $table->string('domain', 255);
            $table->string('category', 60)->default('default');
            $table->enum('action', ['block','allow','rewrite','safe_search'])->default('block');
            $table->timestamp('created_at')->nullable();
            $table->index('rule_source_id', 'idx_rule_items_source');
            $table->index('domain', 'idx_rule_items_domain');
            $table->foreign('rule_source_id', 'fk_rule_items_source')->references('id')->on('rule_sources')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_items');
    }
};
