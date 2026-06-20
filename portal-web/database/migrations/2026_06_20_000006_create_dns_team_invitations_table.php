<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('inviter_id');
            $table->string('email', 190);
            $table->string('role_key', 40)->default('member');
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique('token_hash', 'uniq_team_invite_token');
            $table->index('email', 'idx_team_invite_email');
            $table->foreign('team_id', 'fk_team_invitations_team')->references('id')->on('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('inviter_id', 'fk_team_invitations_inviter')->references('uid')->on('users')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
