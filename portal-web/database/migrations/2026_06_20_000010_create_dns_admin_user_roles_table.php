<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('admin_role_id');
            $table->timestamps();
            $table->unique(['admin_id','admin_role_id'], 'uniq_admin_user_role');
            $table->index('admin_role_id', 'idx_admin_user_role_role');
            $table->foreign('admin_id', 'fk_admin_user_roles_admin')->references('admin_id')->on('admins')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('admin_role_id', 'fk_admin_user_roles_role')->references('id')->on('admin_roles')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
    }
};
