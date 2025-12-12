<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 短信发送记录测试数据
 */
class SmsLogSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::limit(5)->get();
        $loans = Loan::limit(5)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('没有找到客户数据，请先创建客户');
            return;
        }

        $templates = [
            'repayment_due_reminder' => '临期提醒',
            'repayment_overdue' => '逾期提醒',
            'loan_approved' => '放款通知',
        ];

        $states = [
            0 => '发送失败',
            1 => '发送成功',
        ];

        $logs = [];
        $count = 0;

        foreach ($customers as $index => $customer) {
            $loan = $loans->get($index % $loans->count());

            // 为每个客户创建多条短信记录
            for ($i = 0; $i < 3; $i++) {
                $templateKey = array_rand($templates);
                $state = rand(0, 1);
                $daysAgo = rand(1, 30);

                $content = $this->generateSmsContent($templateKey, $customer, $loan);

                $logs[] = [
                    'customer_id' => $customer->id,
                    'loan_id' => $loan ? $loan->id : null,
                    'phone' => $customer->phone,
                    'template_key' => $templateKey,
                    'state' => $state,
                    'content' => $content,
                    'sent_at' => Carbon::now()->subDays($daysAgo)->subHours(rand(0, 23)),
                    'created_at' => Carbon::now()->subDays($daysAgo),
                    'updated_at' => Carbon::now()->subDays($daysAgo),
                ];
                $count++;
            }
        }

        // 批量插入
        foreach (array_chunk($logs, 100) as $chunk) {
            SmsLog::insert($chunk);
        }

        $this->command->info("已创建 {$count} 条短信发送记录");
    }

    /**
     * 生成短信内容
     */
    private function generateSmsContent(string $templateKey, Customer $customer, ?Loan $loan): string
    {
        $name = $customer->name;
        $amount = $loan ? number_format($loan->amount, 2) : '0.00';

        switch ($templateKey) {
            case 'repayment_due_reminder':
                return "【还款提醒】尊敬的{$name}，您有一笔金额为{$amount}元的贷款即将到期，请及时还款。";
            case 'repayment_overdue':
                return "【逾期提醒】尊敬的{$name}，您有一笔金额为{$amount}元的贷款已逾期，请尽快还款以避免产生额外费用。";
            case 'loan_approved':
                return "【放款通知】尊敬的{$name}，您的贷款申请已通过，金额{$amount}元已发放至您的账户。";
            default:
                return "【系统通知】尊敬的{$name}，这是一条测试短信。";
        }
    }
}
