<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_amount_minor')->nullable()->after('amount_minor');
            $table->string('status', 20)->default('active')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->dropColumn(['original_amount_minor', 'status']);
        });
    }
};
