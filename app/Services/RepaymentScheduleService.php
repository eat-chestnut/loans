<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 还款计划服务
 */
class RepaymentScheduleService
{
    /**
     * 等额本息还款计划
     * @param float $loanAmount 本金
     * @param float $monthlyRate 月利率（小数）
     * @param int   $teamMonths 期数（月）
     * @return Collection
     */
    function generateSchedule(Loan $loan)
    {
        $loanAmount = (float)$loan->amount;
        $termMonths = (int)$loan->term_months;
        $totalInterest = (float)$loan->total_interest;
        $startDate = $loan->disbursed_at ? Carbon::parse($loan->disbursed_at) : Carbon::now();

        // 计算月还款额（等额本息）
        $monthlyRate = $this->inferMonthlyRateFromTotalInterest($loanAmount, $totalInterest, $termMonths);

        // 月供 A
        if ($monthlyRate <= 0) {
            $amount = $loanAmount / $termMonths;
        } else {
            $pow = pow(1 + $monthlyRate, $termMonths);
            $amount = $loanAmount * ($monthlyRate * $pow) / ($pow - 1);
        }

        $amount = round($amount, 2);

        $balance = $loanAmount;
        $schedule = [];

        $isPaid = 0;
        $paidAt = null;
        for ($period = 1; $period <= $termMonths; $period++) {
            $interest = $this->numberFormat($balance * $monthlyRate);
            $principal = $this->numberFormat($amount - $interest);


            // 计算还款日期（放款日期 + 期数个月 - 1天）
            $dueDate = $startDate->copy()->addMonths($period)->subDay();
            // 最后一期修正：把剩余本金一次性还清，避免误差
            if ($period === $termMonths) {
                $principal = $this->numberFormat($balance);
                $lastAmount = $this->numberFormat($principal + $interest);
                $balance = 0.0;

                $schedule[] = [
                    'loan_id' => $loan->id,
                    'period' => $period,
                    'due_date' => $dueDate,
                    'amount' => $lastAmount,
                    'principal' => $principal,
                    'interest' => $interest,
                    'remaining_principal' => $balance,
                    'is_paid' => $isPaid,
                    'paid_at' => $paidAt,
                    'remark' => null,
                ];
                break;
            }

            $balance = round($balance - $principal, 2);

            $schedule[] = [
                'loan_id' => $loan->id,
                'period' => $period,
                'due_date' => $dueDate,
                'amount' => $amount,
                'principal' => $principal,
                'interest' => $interest,
                'remaining_principal' => $balance,
                'is_paid' => $isPaid,
                'paid_at' => $paidAt,
                'remark' => null,
            ];
        }

        return collect($schedule);
    }


    function numberFormat($x): float {
        return (float) number_format((float)$x, 2, '.', '');
    }

    /**
     * 保存还款计划到数据库
     *
     * @param Loan $loan
     * @return Collection
     */
    public function saveSchedule(Loan $loan, $schedules=[]): Collection
    {
        if (blank($schedules)) {
            // 生成新的还款计划
            $schedules = $this->generateSchedule($loan);
        }

        // 批量保存
        foreach ($schedules as $scheduleData) {
            $schedule = RepaymentSchedule::where('loan_id', $loan->id)->where('period', $scheduleData['period'])->first();
            if ($schedule) {
                $schedule->amount = $scheduleData['amount'];
                $schedule->principal = $scheduleData['principal'];
                $schedule->interest = $scheduleData['interest'];
                $schedule->remaining_principal = $scheduleData['remaining_principal'];
            }else{
                $schedule = new RepaymentSchedule();
                $schedule->fill($scheduleData);
            }
            $schedule->save();
        }
        // 删除现有还款计划
        RepaymentSchedule::where('loan_id', $loan->id)->where('period', '>', $scheduleData['period'])->delete();

        return $schedules;
    }

    /**
     * 从 本金P、总利息I、期数n（月） 反推 等额本息月利率 r
     *
     * @return float r（月利率，小数，如 0.015 表示 1.5%/月）
     */
    function inferMonthlyRateFromTotalInterest(float $P, float $I, int $n): float
    {
        if ($P <= 0 || $n <= 0) return 0.0;

        // 每月固定还款额
        $A = ($P + $I) / $n;

        // 若总利息为0：月利率=0
        if (abs($I) < 1e-12) return 0.0;

        // f(r) = A*(1-(1+r)^-n)/r - P
        $f = function (float $r) use ($A, $P, $n): float {
            if ($r <= 0) {
                // r->0 时极限：PV = A*n
                return $A * $n - $P;
            }
            $pv = $A * (1 - pow(1 + $r, -$n)) / $r;
            return $pv - $P;
        };

        // 二分法：r>=0。f(0)=I>0；r很大时 f(r)<0
        $lo = 0.0;
        $hi = 1.0; // 先给 100%/月 上限
        while ($f($hi) > 0) {       // PV还大于P，说明r还不够大
            $hi *= 2;
            if ($hi > 1000) break; // 极端保护
        }

        for ($i = 0; $i < 100; $i++) {
            $mid = ($lo + $hi) / 2;
            if ($f($mid) > 0) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return ($lo + $hi) / 2;
    }

    /**
     * 标记某期已还款
     *
     * @param int $scheduleId
     * @return bool
     */
    public function markAsPaid(int $scheduleId): bool
    {
        $schedule = RepaymentSchedule::find($scheduleId);
        if (!$schedule) {
            return false;
        }
        if ($schedule->is_paid) {
            return true;
        }

        $schedule->is_paid = true;
        $schedule->paid_at = Carbon::now();
        $schedule->save();

        $this->updateLoanPaymentStats($schedule->loan_id);

        $this->checkLoanCompletion($schedule->loan_id);

        return true;
    }

    /**
     * 更新放款表的已还金额和盈利金额
     *
     * @param int $loanId
     */
    private function updateLoanPaymentStats(int $loanId): void
    {
        $loan = Loan::find($loanId);
        if (!$loan) {
            return;
        }

        $paidSchedules = RepaymentSchedule::where('loan_id', $loanId)
            ->where('is_paid', true)
            ->get();

        $paidAmount = $paidSchedules->sum('amount');
        $profitAmount = $paidSchedules->sum('interest');

        $loan->paid_amount = round($paidAmount, 2);
        $loan->profit_amount = round($profitAmount, 2);
        $loan->save();
    }

    /**
     * 检查贷款是否已全部还清
     *
     * @param int $loanId
     */
    private function checkLoanCompletion(int $loanId): void
    {
        $unpaidCount = RepaymentSchedule::where('loan_id', $loanId)
            ->where('is_paid', false)
            ->count();

        if ($unpaidCount === 0) {
            $loan = Loan::find($loanId);
            if ($loan) {
                $loan->state = Loan::STATE_CLOSED;
                $loan->closed_at = Carbon::now();
                $loan->save();
            }
        }
    }

    /**
     * 获取贷款的还款统计
     *
     * @param int $loanId
     * @return array
     */
    public function getRepaymentStats(int $loanId): array
    {
        $schedules = RepaymentSchedule::where('loan_id', $loanId)->get();

        $totalAmount = $schedules->sum('amount');
        $paidAmount = $schedules->where('is_paid', true)->sum('amount');
        $unpaidAmount = $totalAmount - $paidAmount;
        $paidCount = $schedules->where('is_paid', true)->count();
        $totalCount = $schedules->count();

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'paid_count' => $paidCount,
            'unpaid_count' => $totalCount - $paidCount,
            'total_count' => $totalCount,
            'progress_percentage' => $totalCount > 0 ? round($paidCount / $totalCount * 100, 2) : 0,
        ];
    }
}
