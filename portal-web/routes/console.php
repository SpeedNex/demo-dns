<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('portal:about', function (): void {
    $this->comment('portal-web command scaffold ready');
});

Schedule::command(\App\Console\Commands\PublishCleanupCommand::class, ['--minutes=30', '--keep=5'])
    ->everySixHours()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — UI.md 闭环调度
|--------------------------------------------------------------------------
| 服务器需配置 `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
*/

// UI.md #78 — 财务对账：每日 03:15
Schedule::command('finance:verify')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// UI.md #67 — Usage 聚合（5 分钟一窗）
Schedule::command('usage:aggregate')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// UI.md #70 — 账单生成：每日 00:30
Schedule::command('billing:generate')
    ->dailyAt('00:30')
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// P0: 计费配额闭环 — Free 套餐超额检测（5 分钟一窗）
Schedule::command('quota:check')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// SaaS 订阅生命周期 — 到期降级 / 宽限期暂停
Schedule::command('subs:grace-sweep')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

