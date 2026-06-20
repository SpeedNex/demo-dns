<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->bigIncrements('admin_id');
            $table->string('username', 100);
            $table->string('email', 190);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->enum('status', ['active','suspended','closed'])->default('active');
            $table->boolean('is_super')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('locale', 10)->default('zh-CN');
            $table->rememberToken();
            $table->timestamps();

            $table->unique('username', 'uniq_admins_username');
            $table->unique('email', 'uniq_admins_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
