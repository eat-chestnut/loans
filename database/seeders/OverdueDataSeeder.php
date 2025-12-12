<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 逾期数据测试
 */
class OverdueDataSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::limit(3)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('没有找到客户数据，请先创建客户');
            return;
        }

        $createdLoans = 0;
        $createdSchedules = 0;

        foreach ($customers as $customer) {
            // 为每个客户创建1-2笔逾期贷款
            $loanCount = rand(1, 2);

            for ($i = 0; $i < $loanCount; $i++) {
                $loan = $this->createOverdueLoan($customer);
                if ($loan) {
                    $createdLoans++;
                    $scheduleCount = $this->createOverdueSchedules($loan);
                    $createdSchedules += $scheduleCount;
                }
            }
        }

        $this->command->info("已创建 {$createdLoans} 笔逾期贷款，{$createdSchedules} 条逾期还款计划");
    }

    /**
     * 创建逾期贷款
     */
    private function createOverdueLoan(Customer $customer): ?Loan
    {
        $amount = rand(5000, 50000);
        $termMonths = rand(3, 12);
        $monthlyRate = rand(10, 30) / 1000; // 1%-3%
        $disbursedDaysAgo = rand(60, 180);

        $loan = Loan::create([
            'loan_number' => Str::random(),
            'customer_id' => $customer->id,
            'ticket_no' => 'OD' . date('Ymd') . rand(1000, 9999),
            'city' => '测试城市',
            'amount' => $amount,
            'paid_amount' => 0,
            'profit_amount' => 0,
            'total_interest_amount' => $amount * $monthlyRate * $termMonths,
            'discount_ratio' => rand(60, 80),
            'month_profit_ratio' => $monthlyRate * 100,
            'term_months' => $termMonths,
//            'rate_month' => $monthlyRate * 100,
            'disbursed_at' => Carbon::now()->subDays($disbursedDaysAgo),
            'state' => Loan::STATE_NEW,
            'overdue_days' => 0,
            'overdue_count' => 0,
            'note' => '逾期测试数据',
            'created_at' => Carbon::now()->subDays($disbursedDaysAgo),
            'updated_at' => Carbon::now(),
        ]);

        return $loan;
    }

    /**
     * 创建逾期还款计划
     */
    private function createOverdueSchedules(Loan $loan): int
    {
        $termMonths = $loan->term_months;
        $monthlyAmount = ($loan->amount + $loan->total_interest_amount) / $termMonths;
        $monthlyInterest = $loan->total_interest_amount / $termMonths;
        $monthlyPrincipal = $loan->amount / $termMonths;

        $count = 0;
        $disbursedAt = Carbon::parse($loan->disbursed_at);

        for ($period = 1; $period <= $termMonths; $period++) {
            $dueDate = $disbursedAt->copy()->addMonths($period);
            $isOverdue = false;
            $isPaid = false;
            $state = '待还款';

            // 前几期设置为逾期
            if ($period <= rand(1, 3) && $dueDate->lt(Carbon::now())) {
                $isOverdue = true;
                $state = '逾期';

                // 部分逾期的已还款
                if (rand(0, 1) === 1) {
                    $isPaid = true;
                    $state = '已还款';
                    $isOverdue = false;
                }
            }

            // 已还款的期数
            if ($period < rand(1, max(1, $termMonths - 2)) && !$isOverdue) {
                $isPaid = true;
                $state = '已还款';
            }

            RepaymentSchedule::create([
                'loan_id' => $loan->id,
                'period' => $period,
                'due_date' => $dueDate,
                'amount' => round($monthlyAmount, 2),
                'principal' => round($monthlyPrincipal, 2),
                'interest' => round($monthlyInterest, 2),
                'is_paid' => $isPaid,
                'is_overdue' => $isOverdue,
                'paid_at' => $isPaid ? $dueDate->copy()->addDays(rand(0, 5)) : null,
                'created_at' => $disbursedAt,
                'updated_at' => Carbon::now(),
            ]);

            $count++;
        }

        // 更新贷款的逾期信息
        $overdueSchedules = RepaymentSchedule::where('loan_id', $loan->id)
            ->where('is_overdue', true)
            ->count();

        if ($overdueSchedules > 0) {
            $oldestOverdue = RepaymentSchedule::where('loan_id', $loan->id)
                ->where('is_overdue', true)
                ->orderBy('due_date')
                ->first();

            if ($oldestOverdue) {
                $overdueDays = Carbon::now()->diffInDays($oldestOverdue->due_date, true);
                $loan->overdue_days = $overdueDays;
                $loan->overdue_count = $overdueSchedules;
                $loan->save();
            }
        }

        return $count;
    }
}
