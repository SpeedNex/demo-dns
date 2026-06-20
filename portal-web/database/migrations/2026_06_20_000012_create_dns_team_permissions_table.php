<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 80);
            $table->string('resource', 80);
            $table->string('action', 80);
            $table->string('description', 300)->nullable();
            $table->timestamps();
            $table->unique('code', 'uniq_team_perm_code');
            $table->index(['resource','action'], 'idx_team_perm_resource_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_permissions');
    }
};
