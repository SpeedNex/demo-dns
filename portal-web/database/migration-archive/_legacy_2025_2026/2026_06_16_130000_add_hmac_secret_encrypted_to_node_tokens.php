<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('node_tokens', function (Blueprint $table): void {
            $table->text('hmac_secret_encrypted')->nullable()->after('hmac_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('node_tokens', function (Blueprint $table): void {
            $table->dropColumn('hmac_secret_encrypted');
        });
    }
};
