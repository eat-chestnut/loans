<?php

namespace Database\Seeders;

use App\Models\WecomContact;
use Illuminate\Database\Seeder;

/**
 * 企微客户模拟数据
 */
class WecomContactSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            [
                'name' => '张三',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0001',
                'mobile' => '13800138001',
                'customer_id' => null,
            ],
            [
                'name' => '李四',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0002',
                'mobile' => '13800138002',
                'customer_id' => null,
            ],
            [
                'name' => '王五',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0003',
                'mobile' => '13800138003',
                'customer_id' => null,
            ],
            [
                'name' => '赵六',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0004',
                'mobile' => '13800138004',
                'customer_id' => null,
            ],
            [
                'name' => '孙七',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0005',
                'mobile' => '13800138005',
                'customer_id' => null,
            ],
            [
                'name' => '周八',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0006',
                'mobile' => '13800138006',
                'customer_id' => null,
            ],
            [
                'name' => '吴九',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0007',
                'mobile' => '13800138007',
                'customer_id' => null,
            ],
            [
                'name' => '郑十',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0008',
                'mobile' => '13800138008',
                'customer_id' => null,
            ],
            [
                'name' => '陈十一',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0009',
                'mobile' => '13800138009',
                'customer_id' => null,
            ],
            [
                'name' => '刘十二',
                'wechat_id' => 'wmEQKCDwAAqR8KqLqLqLqLqLqLqL0010',
                'mobile' => '13800138010',
                'customer_id' => null,
            ],
        ];

        foreach ($contacts as $contact) {
            WecomContact::updateOrCreate(
                ['wechat_id' => $contact['wechat_id']],
                $contact
            );
        }

        $this->command->info('已创建 ' . count($contacts) . ' 个模拟企微客户');
    }
}
