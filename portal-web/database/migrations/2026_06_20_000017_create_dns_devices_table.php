<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('device_uid', 40);
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 120)->nullable();
            $table->string('fingerprint', 255);
            $table->enum('source', ['auto','manual'])->default('auto');
            $table->enum('protocol', ['doh','dot','doq','udp','tcp'])->default('doh');
            $table->text('user_agent')->nullable();
            $table->string('sni', 255)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('country', 8)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_query_at')->nullable();
            $table->unsignedBigInteger('query_count')->default(0);
            $table->enum('status', ['active','blocked','removed'])->default('active');
            $table->timestamps();
            $table->unique(['profile_id','fingerprint'], 'uniq_devices_profile_fingerprint');
            $table->unique('device_uid', 'uniq_devices_uid');
            $table->index('user_id', 'idx_devices_user');
            $table->foreign('profile_id', 'fk_devices_profile')->references('id')->on('profiles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'fk_devices_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
