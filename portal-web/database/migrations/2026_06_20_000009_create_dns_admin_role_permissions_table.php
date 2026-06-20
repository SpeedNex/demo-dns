<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('admin_role_id');
            $table->unsignedBigInteger('admin_permission_id');
            $table->timestamps();
            $table->unique(['admin_role_id','admin_permission_id'], 'uniq_admin_role_perm');
            $table->index('admin_permission_id', 'idx_admin_role_perm_perm');
            $table->foreign('admin_role_id', 'fk_admin_role_perm_role')->references('id')->on('admin_roles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('admin_permission_id', 'fk_admin_role_perm_perm')->references('id')->on('admin_permissions')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
    }
};
