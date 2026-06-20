<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE dns_orders ALTER COLUMN user_id TYPE varchar(64)');
            if ($this->columnExists('dns_orders', 'idempotency_key')) {
                DB::statement('ALTER TABLE dns_orders ALTER COLUMN idempotency_key TYPE varchar(120)');
            }

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `dns_orders` MODIFY COLUMN `user_id` varchar(64)');
            if ($this->columnExists('dns_orders', 'idempotency_key')) {
                DB::statement('ALTER TABLE `dns_orders` MODIFY COLUMN `idempotency_key` varchar(120) NULL');
            }
        }
    }

    public function down(): void
    {
    }

    private function columnExists(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'pgsql') {
            return DB::selectOne(
                'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            ) !== null;
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $column]
        ) !== null;
    }
};
