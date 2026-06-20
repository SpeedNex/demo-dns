<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('plan_code', 30)->default('free')->after('status');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->json('security_settings')->nullable()->after('security_enabled');
            $table->json('privacy_settings')->nullable()->after('privacy_enabled');
            $table->json('parental_settings')->nullable()->after('parental_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['security_settings', 'privacy_settings', 'parental_settings']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('plan_code');
        });
    }
};
