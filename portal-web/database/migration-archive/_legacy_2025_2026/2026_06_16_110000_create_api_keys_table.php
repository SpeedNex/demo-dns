<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 30);
            $table->string('name', 100);
            $table->string('key_hash', 64);
            $table->string('key_prefix', 20);
            $table->string('status', 20)->default('active');
            $table->json('scopes');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_api_keys_user_id');
            $table->index('key_prefix', 'idx_api_keys_key_prefix');
            $table->index('status', 'idx_api_keys_status');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
