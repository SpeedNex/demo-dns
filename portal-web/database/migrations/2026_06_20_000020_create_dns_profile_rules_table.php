<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profile_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('rule_source_id')->nullable();
            $table->string('domain', 255);
            $table->enum('list_type', ['allowlist','blocklist']);
            $table->enum('action', ['block','allow','rewrite'])->default('block');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['profile_id','list_type','domain'], 'uniq_profile_rule');
            $table->index('profile_id', 'idx_profile_rules_profile');
            $table->index('rule_source_id', 'idx_profile_rules_source');
            $table->foreign('profile_id', 'fk_profile_rules_profile')->references('id')->on('profiles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('rule_source_id', 'fk_profile_rules_source')->references('id')->on('rule_sources')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_rules');
    }
};
