<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_key', 40)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id','user_id'], 'uniq_team_member');
            $table->index('user_id', 'idx_team_members_user');
            $table->foreign('team_id', 'fk_team_members_team')->references('id')->on('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'fk_team_members_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
