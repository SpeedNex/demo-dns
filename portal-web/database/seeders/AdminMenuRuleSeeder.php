<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminMenuRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 后台菜单数据完全由管理员在 /admin/menu-config 页面维护，Seeder 不再预置任何默认菜单。
    }
}
