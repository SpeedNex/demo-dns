<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('team_id', 36);
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->string('user_id', 36);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'user_id'], 'uniq_team_members_team_user')
                ->whereNull('deleted_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
