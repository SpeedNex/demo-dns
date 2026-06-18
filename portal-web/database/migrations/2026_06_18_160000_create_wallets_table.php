<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UI.md #54: 钱包系统
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 30)->unique();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('balance')->default(0)->comment('单位：分');
            $table->bigInteger('frozen')->default(0)->comment('单位：分');
            $table->bigInteger('version')->default(0)->comment('乐观锁版本号');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 一次性数据迁移：把 users.balance_minor 复制到 wallets.balance
        // 注意：users.balance_minor 字段保留为只读缓存，UI.md #54 已废弃。
        if (Schema::hasColumn('users', 'balance_minor')) {
            $rows = DB::table('users')->whereNotNull('balance_minor')->select(['id', 'balance_minor', 'currency'])->get();
            foreach ($rows as $r) {
                DB::table('wallets')->updateOrInsert(
                    ['user_id' => $r->id],
                    [
                        'balance' => (int) $r->balance_minor,
                        'currency' => $r->currency ?? 'CNY',
                        'frozen' => 0,
                        'version' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
