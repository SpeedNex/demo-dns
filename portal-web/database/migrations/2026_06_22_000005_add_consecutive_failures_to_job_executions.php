<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 联调发现 NEW P0#N1 续: 2026_06_22_000004 已重命名 job_name → job_type，
 * 但 dns_job_executions 还缺 'consecutive_failures' 列，
 * JobExecution::$fillable + JobRunner.php:41 都读/写该列。
 *
 * 修复：补上 consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0。
 */
return new class extends Migration {
    private string $table = 'job_executions';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }
        if (! Schema::hasColumn($this->table, 'consecutive_failures')) {
            $prefix = DB::getTablePrefix();
            DB::statement("ALTER TABLE `{$prefix}{$this->table}` ADD COLUMN `consecutive_failures` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }
        if (Schema::hasColumn($this->table, 'consecutive_failures')) {
            $prefix = DB::getTablePrefix();
            DB::statement("ALTER TABLE `{$prefix}{$this->table}` DROP COLUMN `consecutive_failures`");
        }
    }
};
