<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 删除 node_type 列
        if (Schema::hasColumn('nodes', 'node_type')) {
            Schema::table('nodes', function ($table): void {
                $table->dropColumn('node_type');
            });
        }
    }

    public function down(): void
    {
        // 还原 node_type 列
        if (! Schema::hasColumn('nodes', 'node_type')) {
            Schema::table('nodes', function ($table): void {
                $table->string('node_type', 20)->default('resolver')->after('node_code');
            });
        }
    }
};
