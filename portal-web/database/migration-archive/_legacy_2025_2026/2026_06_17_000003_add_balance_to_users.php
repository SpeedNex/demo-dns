<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->bigInteger('balance_minor')->default(0)->after('plan_code');
            $table->string('currency', 3)->default('CNY')->after('balance_minor');
            $table->timestamp('balance_updated_at')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['balance_minor', 'currency', 'balance_updated_at']);
        });
    }
};
