<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('team_id', 36);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('role', 30)->default('member');
            $table->string('token_hash', 255)->unique();
            $table->string('invited_by', 36);
            $table->foreign('invited_by')->references('id')->on('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
