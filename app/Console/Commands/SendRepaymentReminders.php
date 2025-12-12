<?php

namespace App\Console\Commands;

use App\Models\RepaymentSchedule;
use App\Models\SmsLog;
use App\Services\Sms\TencentSmsService;
use App\Services\Wecom\WecomService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRepaymentReminders extends Command
{
    protected $signature = 'loans:send-reminders {--dry-run : 仅输出待提醒列表，不真实发送消息}';

    protected $description = 'Send reminders before repayment due date (SMS + WeCom).';

    public function handle(TencentSmsService $smsService, WecomService $wecomService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $smsSummary = $this->processSmsReminders($smsService, $dryRun);
        $wecomSummary = $this->processWecomReminders($wecomService, $dryRun);

        $this->info(sprintf(
            'Reminder finished. SMS(%d/%d) WeCom(%d/%d)',
            $smsSummary['sent'],
            $smsSummary['total'],
            $wecomSummary['sent'],
            $wecomSummary['total']
        ));

        return Command::SUCCESS;
    }

    protected function processSmsReminders(TencentSmsService $smsService, bool $dryRun): array
    {
        $targetDate = Carbon::today()->addDays(2);
        $this->info(sprintf('SMS: scanning due on %s', $targetDate->toDateString()));

        $query = RepaymentSchedule::query()
            ->with(['loan.customer'])
            ->whereDate('due_date', $targetDate)
            ->where('is_paid', false)
            ->whereNull('reminder_sent_at');

        $total = 0;
        $sent = 0;
        $skipped = 0;

        $query->chunkById(50, function ($schedules) use (&$total, &$sent, &$skipped, $dryRun, $smsService) {
            foreach ($schedules as $schedule) {
                $total++;
                $loan = $schedule->loan;
                $customer = $loan?->customer;

                if (!$loan || !$customer || blank($customer->phone)) {
                    $skipped++;
                    Log::warning('SMS reminder skipped, missing phone', [
                        'schedule_id' => $schedule->id,
                        'loan_id'     => $loan?->id,
                    ]);
                    continue;
                }

                $templateId = config('services.tencent_sms.reminder_tpl') ?: config('services.tencent_sms.template_id');
                $params = [
                    $customer->name,
                    $schedule->due_date?->format('m月d日'),
                    number_format($schedule->amount, 2),
                ];

                if ($dryRun) {
                    $skipped++;
                    $this->line(sprintf('[SMS][DRY] #%d %s %s %s元', $schedule->id, $customer->name, $schedule->due_date?->toDateString(), $schedule->amount));
                    continue;
                }

                $result = $smsService->send($customer->phone, $params, $templateId);
                $success = strcasecmp($result['status'] ?? '', 'Ok') === 0;

                if ($success) {
                    $schedule->reminder_sent_at = now();
                }

                $schedule->reminder_times = (int) $schedule->reminder_times + 1;
                $schedule->save();

                SmsLog::create([
                    'customer_id'  => $customer->getKey(),
                    'loan_id'      => $loan->getKey(),
                    'sent_at'      => now(),
                    'phone'        => $customer->phone,
                    'template_key' => SmsLog::TEMPLATE_REPAYMENT_REMINDER,
                    'state'        => $success ? 1 : 0,
                    'content'      => json_encode([
                        'schedule_id' => $schedule->id,
                        'result'      => $result,
                        'params'      => $params,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                if ($success) {
                    $sent++;
                } else {
                    $skipped++;
                    Log::warning('SMS reminder failed', [
                        'schedule_id' => $schedule->id,
                        'result'      => $result,
                    ]);
                }
            }
        });

        return compact('total', 'sent', 'skipped');
    }

    protected function processWecomReminders(WecomService $wecomService, bool $dryRun): array
    {
        if (!$wecomService->enabled()) {
            $this->info('WeCom disabled, skip.');
            return ['total' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $targetDate = Carbon::today()->addDays(3);
        $this->info(sprintf('WeCom: scanning due on %s', $targetDate->toDateString()));

        $query = RepaymentSchedule::query()
            ->with(['loan.customer.wecomContact'])
            ->whereDate('due_date', $targetDate)
            ->where('is_paid', false)
            ->whereNull('wecom_reminder_sent_at')
            ->whereHas('loan.customer.wecomContacts');

        $total = 0;
        $sent = 0;
        $skipped = 0;

        $query->chunkById(50, function ($schedules) use (&$total, &$sent, &$skipped, $dryRun, $wecomService) {
            foreach ($schedules as $schedule) {
                $total++;
                $loan = $schedule->loan;
                $customer = $loan?->customer;

                if (!$loan || !$customer) {
                    $skipped++;
                    continue;
                }

                $contacts = $customer->wecomContacts;

                if ($contacts->isEmpty()) {
                    $skipped++;
                    continue;
                }

                $message = sprintf(
                    "【%s】%s您好，您的贷款[%s]将于%s到期，应还金额%s元，请提前准备资金。",
                    config('app.name', '贷款系统'),
                    $customer->name,
                    $loan->loan_number ?? $loan->id,
                    $schedule->due_date?->format('m月d日'),
                    number_format($schedule->amount, 2)
                );

                if ($dryRun) {
                    $skipped++;
                    $this->line(sprintf('[WeCom][DRY] #%d %s => %s', $schedule->id, $customer->name, $message));
                    continue;
                }

                foreach ($contacts as $contact) {
                    $result = $wecomService->sendText($contact, $message, $loan->getKey());
                    if (($result['errcode'] ?? 0) === 0) {
                        $sent++;
                    } else {
                        $skipped++;
                    }
                }

                $schedule->wecom_reminder_sent_at = now();
                $schedule->wecom_reminder_times = (int) $schedule->wecom_reminder_times + 1;
                $schedule->save();
            }
        });

        return compact('total', 'sent', 'skipped');
    }
}
