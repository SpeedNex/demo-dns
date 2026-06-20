<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('role', 30);
            $table->string('permission_code', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['role', 'permission_code'], 'uniq_role_permission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
