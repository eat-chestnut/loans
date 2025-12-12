<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 测试数据主 Seeder
 * 一键创建所有测试数据
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('开始创建测试数据...');
        $this->command->info('========================================');

        // 1. 企微客户数据
        $this->command->info('');
        $this->command->info('1. 创建企微客户数据...');
        $this->call(WecomContactSeeder::class);

        // 2. 短信发送记录
        $this->command->info('');
        $this->command->info('2. 创建短信发送记录...');
        $this->call(SmsLogSeeder::class);

        // 3. 企微发送记录
        $this->command->info('');
        $this->command->info('3. 创建企微发送记录...');
        $this->call(WecomLogSeeder::class);

        // 4. 逾期数据
        $this->command->info('');
        $this->command->info('4. 创建逾期测试数据...');
        $this->call(OverdueDataSeeder::class);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('测试数据创建完成！');
        $this->command->info('========================================');
    }
}
