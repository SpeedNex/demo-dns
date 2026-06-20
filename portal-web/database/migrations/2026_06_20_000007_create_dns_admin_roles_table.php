<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
            $table->unique('code', 'uniq_admin_roles_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};
