<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_user_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('team_role_id');
            $table->timestamps();
            $table->unique(['team_id','user_id','team_role_id'], 'uniq_team_user_role');
            $table->index('team_role_id', 'idx_team_user_role_role');
            $table->foreign('team_id', 'fk_team_user_roles_team')->references('id')->on('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'fk_team_user_roles_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('team_role_id', 'fk_team_user_roles_role')->references('id')->on('team_roles')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user_roles');
    }
};
