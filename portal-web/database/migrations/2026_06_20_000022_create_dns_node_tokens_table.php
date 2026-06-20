<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('node_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('node_id');
            $table->string('token_prefix', 20);
            $table->char('token_hash', 64);
            $table->string('hmac_key_hash', 128)->nullable();
            $table->text('hmac_secret_encrypted')->nullable();
            $table->json('scopes')->nullable();
            $table->enum('status', ['active','revoked','expired'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
            $table->unique('token_prefix', 'uniq_node_tokens_prefix');
            $table->unique('token_hash', 'uniq_node_tokens_hash');
            $table->index('node_id', 'idx_node_tokens_node');
            $table->foreign('node_id', 'fk_node_tokens_node')->references('id')->on('nodes')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_admin_id', 'fk_node_tokens_creator')->references('admin_id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_tokens');
    }
};
