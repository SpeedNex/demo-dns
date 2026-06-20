<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-3 修复：billing_items 金额字段统一为 amount_minor / unit_price_minor。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        if (Schema::hasColumn('billing_items', 'amount') && ! Schema::hasColumn('billing_items', 'amount_minor')) {
            DB::statement('ALTER TABLE ' . $prefix . 'billing_items RENAME COLUMN amount TO amount_minor');
        }
        if (Schema::hasColumn('billing_items', 'unit_price') && ! Schema::hasColumn('billing_items', 'unit_price_minor')) {
            DB::statement('ALTER TABLE ' . $prefix . 'billing_items RENAME COLUMN unit_price TO unit_price_minor');
        }
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        if (Schema::hasColumn('billing_items', 'amount_minor')) {
            DB::statement('ALTER TABLE ' . $prefix . 'billing_items RENAME COLUMN amount_minor TO amount');
        }
        if (Schema::hasColumn('billing_items', 'unit_price_minor')) {
            DB::statement('ALTER TABLE ' . $prefix . 'billing_items RENAME COLUMN unit_price_minor TO unit_price');
        }
    }
};
