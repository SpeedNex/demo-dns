<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sync-all 批量创建任务时尚无确定版本，允许 profile_version_id 为空
        Schema::table('publish_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_version_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('publish_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_version_id')->nullable(false)->change();
        });
    }
};
