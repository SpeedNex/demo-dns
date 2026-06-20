<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 160);
            $table->string('slug', 120);
            $table->string('description', 500)->nullable();
            $table->enum('status', ['active','archived'])->default('active');
            $table->timestamps();
            $table->unique('slug', 'uniq_teams_slug');
            $table->index('owner_id', 'idx_teams_owner');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('owner_id', 'fk_teams_owner')
                ->references('uid')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        // V2.3: 补建 users.current_team_id -> teams.id 外键
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_team_id', 'fk_users_team')
                ->references('id')->on('teams')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
