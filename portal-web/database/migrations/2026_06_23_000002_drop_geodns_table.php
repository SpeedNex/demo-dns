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
        // 删除 geodns 表（GeoDnsMapping 模型使用的表）
        if (Schema::hasTable('geodns')) {
            Schema::dropIfExists('geodns');
        }
    }

    public function down(): void
    {
        // 无需还原，因为这是按用户要求彻底删除的功能
    }
};
