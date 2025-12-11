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
     * 生成还款计划
     *
     * @param Loan $loan
     * @return Collection
     */
    public function generateSchedule(Loan $loan): Collection
    {
        $loanAmount = (float)$loan->amount;
        $termMonths = (int)$loan->term_months;
        $monthlyRate = (float)$loan->rate_month / 100; // 转换为小数
        $startDate = $loan->disbursed_at ? Carbon::parse($loan->disbursed_at) : Carbon::now();
        
        // 计算月还款额（等额本息）
        $monthlyPayment = $this->calculateMonthlyPayment($loanAmount, $monthlyRate, $termMonths);
        
        $schedules = collect();
        $remainingPrincipal = $loanAmount;
        
        for ($period = 1; $period <= $termMonths; $period++) {
            // 计算当期利息
            $interestPayment = $remainingPrincipal * $monthlyRate;
            
            // 计算当期本金
            $principalPayment = $monthlyPayment - $interestPayment;
            
            // 更新剩余本金
            $remainingPrincipal -= $principalPayment;
            
            // 最后一期处理精度问题
            if ($period === $termMonths) {
                $principalPayment += $remainingPrincipal;
                $remainingPrincipal = 0;
            }
            
            // 计算还款日期（放款日期 + 期数个月 - 1天）
            $dueDate = $startDate->copy()->addMonths($period)->subDay();
            
            // 判断是否为历史还款（已过期）
            $isPaid = $dueDate->lt(Carbon::now()->startOfDay());
            $paidAt = $isPaid ? $dueDate->copy()->startOfDay() : null;
            
            $scheduleData = [
                'loan_id' => $loan->id,
                'period' => $period,
                'due_date' => $dueDate->format('Y-m-d'),
                'amount' => round($monthlyPayment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'remaining_principal' => round(max(0, $remainingPrincipal), 2),
                'is_paid' => $isPaid,
                'paid_at' => $paidAt,
                'state' => $isPaid ? '已还款' : '待还款',
                'remark' => null,
            ];
            
            $schedules->push($scheduleData);
        }
        
        return $schedules;
    }
    
    /**
     * 保存还款计划到数据库
     *
     * @param Loan $loan
     * @return Collection
     */
    public function saveSchedule(Loan $loan): Collection
    {
        // 删除现有还款计划
        RepaymentSchedule::where('loan_id', $loan->id)->delete();
        
        // 生成新的还款计划
        $schedules = $this->generateSchedule($loan);
        
        // 批量保存
        foreach ($schedules as $scheduleData) {
            RepaymentSchedule::create($scheduleData);
        }
        
        return $schedules;
    }
    
    /**
     * 计算月还款额（等额本息公式）
     *
     * @param float $loanAmount 贷款本金
     * @param float $monthlyRate 月利率
     * @param int $termMonths 期数
     * @return float
     */
    private function calculateMonthlyPayment(float $loanAmount, float $monthlyRate, int $termMonths): float
    {
        if ($monthlyRate == 0) {
            return $loanAmount / $termMonths;
        }
        
        // 等额本息公式：月还款额 = 本金 × 月利率 × (1+月利率)^期数 / ((1+月利率)^期数 - 1)
        $pow = pow(1 + $monthlyRate, $termMonths);
        $monthlyPayment = $loanAmount * $monthlyRate * $pow / ($pow - 1);
        
        return $monthlyPayment;
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
        if (!$schedule || $schedule->is_paid) {
            return false;
        }
        
        $schedule->is_paid = true;
        $schedule->paid_at = Carbon::now();
        $schedule->state = '已还款';
        $schedule->save();
        
        // 检查是否所有期数都已还清
        $this->checkLoanCompletion($schedule->loan_id);
        
        return true;
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
