<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 联调发现 NEW P0#N1: 迁移声明列 job_name (varchar 80)，但
 *   app/Domain/Jobs/JobRunner.php:21 写入 job_type
 *   app/Models/JobExecution.php $fillable = ['job_type', ...]
 * 导致 php artisan usage:aggregate / billing:generate 直接报
 *   SQLSTATE[42S22]: Column not found: job_type
 *
 * 修复：重命名列 + 索引，与代码约定保持一致。
 * 不依赖 doctrine/dbal（项目未安装），全部用原生 MySQL ALTER 实现。
 * 注意：DB::statement 不走 Schema 编织器，需要用带 prefix 的全名。
 */
return new class extends Migration {
    private string $table;     // 不带 prefix 的迁移名
    private string $fullTable;  // 带 prefix 的真名

    public function __construct()
    {
        $this->table = 'job_executions';
        $this->fullTable = DB::getTablePrefix() . $this->table;
    }

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        // 1) 重命名列 job_name → job_type
        if (Schema::hasColumn($this->table, 'job_name') && ! Schema::hasColumn($this->table, 'job_type')) {
            DB::statement("ALTER TABLE `{$this->fullTable}` CHANGE COLUMN `job_name` `job_type` VARCHAR(80) NOT NULL");
        }

        // 2) 重命名索引 idx_job_exec_name → idx_job_exec_type
        $idxExists = collect(DB::select("SHOW INDEX FROM `{$this->fullTable}` WHERE Key_name = 'idx_job_exec_name'"))->isNotEmpty();
        $newExists = collect(DB::select("SHOW INDEX FROM `{$this->fullTable}` WHERE Key_name = 'idx_job_exec_type'"))->isNotEmpty();
        if ($idxExists && ! $newExists) {
            DB::statement("ALTER TABLE `{$this->fullTable}` RENAME INDEX `idx_job_exec_name` TO `idx_job_exec_type`");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $newExists = collect(DB::select("SHOW INDEX FROM `{$this->fullTable}` WHERE Key_name = 'idx_job_exec_type'"))->isNotEmpty();
        $oldExists = collect(DB::select("SHOW INDEX FROM `{$this->fullTable}` WHERE Key_name = 'idx_job_exec_name'"))->isNotEmpty();
        if ($newExists && ! $oldExists) {
            DB::statement("ALTER TABLE `{$this->fullTable}` RENAME INDEX `idx_job_exec_type` TO `idx_job_exec_name`");
        }

        if (Schema::hasColumn($this->table, 'job_type') && ! Schema::hasColumn($this->table, 'job_name')) {
            DB::statement("ALTER TABLE `{$this->fullTable}` CHANGE COLUMN `job_type` `job_name` VARCHAR(80) NOT NULL");
        }
    }
};
