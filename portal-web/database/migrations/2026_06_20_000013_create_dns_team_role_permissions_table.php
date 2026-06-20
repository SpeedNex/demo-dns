<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_role_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('team_role_id');
            $table->unsignedBigInteger('team_permission_id');
            $table->timestamps();
            $table->unique(['team_role_id','team_permission_id'], 'uniq_team_role_perm');
            $table->index('team_permission_id', 'idx_team_role_perm_perm');
            $table->foreign('team_role_id', 'fk_team_role_perm_role')->references('id')->on('team_roles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('team_permission_id', 'fk_team_role_perm_perm')->references('id')->on('team_permissions')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_role_permissions');
    }
};
