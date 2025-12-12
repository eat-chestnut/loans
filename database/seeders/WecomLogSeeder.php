<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\WecomContact;
use App\Models\WecomLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 企微发送记录测试数据
 */
class WecomLogSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = WecomContact::limit(5)->get();
        $loans = Loan::limit(5)->get();

        if ($contacts->isEmpty()) {
            $this->command->warn('没有找到企微客户数据，请先运行 WecomContactSeeder');
            return;
        }

        $logs = [];
        $count = 0;

        foreach ($contacts as $index => $contact) {
            $loan = $loans->get($index % max(1, $loans->count()));

            // 为每个企微客户创建多条发送记录
            for ($i = 0; $i < 4; $i++) {
                $daysAgo = rand(1, 30);
                $messageType = ['reminder', 'overdue', 'notification', 'greeting'][rand(0, 3)];

                $content = $this->generateWecomContent($messageType, $contact, $loan);

                $logs[] = [
                    'customer_id' => $contact->customer_id,
                    'loan_id' => $loan ? $loan->id : null,
                    'contact_name' => $contact->name,
                    'wechat_id' => $contact->wechat_id,
                    'content' => json_encode([
                        'msgtype' => 'text',
                        'text' => ['content' => $content],
                        'external_userid' => [$contact->wechat_id],
                        'result' => [
                            'errcode' => rand(0, 1) === 0 ? 0 : 40001,
                            'errmsg' => rand(0, 1) === 0 ? 'ok' : 'invalid credential',
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'sent_at' => Carbon::now()->subDays($daysAgo)->subHours(rand(0, 23)),
                    'created_at' => Carbon::now()->subDays($daysAgo),
                    'updated_at' => Carbon::now()->subDays($daysAgo),
                ];
                $count++;
            }
        }

        // 批量插入
        foreach (array_chunk($logs, 100) as $chunk) {
            WecomLog::insert($chunk);
        }

        $this->command->info("已创建 {$count} 条企微发送记录");
    }

    /**
     * 生成企微消息内容
     */
    private function generateWecomContent(string $type, WecomContact $contact, ?Loan $loan): string
    {
        $name = $contact->name;
        $amount = $loan ? number_format($loan->amount, 2) : '0.00';

        switch ($type) {
            case 'reminder':
                return "您好，{$name}！您有一笔金额为 {$amount} 元的贷款即将到期，请及时还款。如有疑问请联系客服。";
            case 'overdue':
                return "您好，{$name}！您有一笔金额为 {$amount} 元的贷款已逾期，请尽快还款以避免影响信用。感谢您的配合！";
            case 'notification':
                return "您好，{$name}！您的贷款申请已通过审核，金额 {$amount} 元将于今日发放。请注意查收。";
            case 'greeting':
                return "您好，{$name}！感谢您一直以来的信任与支持。如有任何问题，随时联系我们。";
            default:
                return "您好，{$name}！这是一条测试消息。";
        }
    }
}
