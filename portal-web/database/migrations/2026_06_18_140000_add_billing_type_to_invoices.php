<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UI.md #52: Order↔Billing 关系明确
        //  - plan 账单: 1:1 对应一个 Order
        //  - usage 账单: 对应 Billing Period
        // 区分字段: billing_type
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_type', 20)->default('plan')->after('type')
                ->comment('plan / usage');
            $table->unsignedBigInteger('order_id')->nullable()->after('billing_type');
            $table->unsignedBigInteger('billing_period_id')->nullable()->after('order_id');

            $table->index('billing_type');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['billing_type']);
            $table->dropIndex(['order_id']);
            $table->dropColumn(['billing_type', 'order_id', 'billing_period_id']);
        });
    }
};
