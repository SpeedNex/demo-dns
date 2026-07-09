<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', static function (Blueprint $table): void {
            $table->string('signing_secret', 64)->nullable()->after('api_key_hash')
                ->comment('HMAC 签名密钥，用于验证设备数据防伪造');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', static function (Blueprint $table): void {
            $table->dropColumn('signing_secret');
        });
    }
};
