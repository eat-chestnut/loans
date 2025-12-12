<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 每日任务：更新还款明细的逾期状态和放款表的逾期次数
 */
class UpdateOverdueStatus extends Command
{
    protected $signature = 'loan:update-overdue-status';

    protected $description = '更新还款明细的逾期状态和放款表的逾期次数';

    public function handle()
    {
        $this->info('开始更新逾期状态...');

        $today = Carbon::today();
        $updatedSchedules = 0;
        $updatedLoans = 0;

        RepaymentSchedule::where('is_paid', false)
            ->where('due_date', '<', $today)
            ->where('is_overdue', false)
            ->chunkById(100, function ($schedules) use (&$updatedSchedules) {
                foreach ($schedules as $schedule) {
                    $schedule->is_overdue = true;
                    $schedule->save();
                    $updatedSchedules++;
                }
            });

        $this->info("已更新 {$updatedSchedules} 条还款明细为逾期状态");

        $loanIds = RepaymentSchedule::where('is_overdue', true)
            ->distinct()
            ->pluck('loan_id');

        foreach ($loanIds as $loanId) {
            $loan = Loan::find($loanId);
            if (!$loan) {
                continue;
            }

            $overdueCount = RepaymentSchedule::where('loan_id', $loanId)
                ->where('is_overdue', true)
                ->count();

            if ($loan->overdue_count !== $overdueCount) {
                $loan->overdue_count = $overdueCount;
                $loan->save();
                $updatedLoans++;
            }
        }

        $this->info("已更新 {$updatedLoans} 个放款记录的逾期次数");
        $this->info('逾期状态更新完成！');

        return 0;
    }
}
