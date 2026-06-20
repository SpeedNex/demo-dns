<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id', 36);
            $table->string('domain', 255);
            $table->timestamps();

            $table->unique(['source_id', 'domain'], 'uniq_rule_items_source_domain');
            $table->index('source_id', 'idx_rule_items_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_items');
    }
};