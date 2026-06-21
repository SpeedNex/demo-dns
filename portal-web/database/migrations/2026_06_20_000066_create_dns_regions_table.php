<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique()->comment('区域编码，如 KR, JP');
            $table->string('name', 100)->comment('区域名称，如 Korea, Japan');
            $table->string('status', 20)->default('active')->comment('状态: active/disabled');
            $table->string('note', 255)->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};