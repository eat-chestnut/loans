<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\Communication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard服务类 - 提供各种KPI指标统计
 */
class DashboardService
{
    /**
     * 获取核心KPI指标
     */
    public function getCoreMetrics()
    {
        $now = Carbon::now();
        $monthStart = $now->startOfMonth();
        
        // 在贷笔数
        $activeLoans = Loan::where('state', 1)->count();
        
        // 服务客户数
        $activeCustomers = Loan::where('state', 1)->distinct('customer_id')->count();
        
        // 本月放款金额
        $monthlyLoans = Loan::where('state', 1)
            ->where('disbursed_at', '>=', $monthStart)
            ->sum('amount');
        
        // 逾期率计算
        $overdueAmount = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->sum('amount');
        $totalDueAmount = RepaymentSchedule::where('is_paid', 0)->sum('amount');
        $overdueRate = $totalDueAmount > 0 ? round(($overdueAmount / $totalDueAmount) * 100, 2) : 0;
        
        // 待收款（本息）
        $dueAmount = RepaymentSchedule::where('is_paid', 0)->sum('amount');
        
        // 已收利息
        $paidInterest = RepaymentSchedule::where('is_paid', 1)->sum('interest');
        
        // 逾期金额
        $overdueAmount = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->sum('amount');
        
        // 高风险客户数
        $highRiskCustomers = Customer::whereIn('risk_level', [3, 4])->count();
        
        // 在贷余额
        $inLoanBalance = RepaymentSchedule::where('is_paid', 0)->sum('remaining_principal');
        
        // 近30天应收/实收
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $receivable30Days = RepaymentSchedule::where('due_date', '>=', $thirtyDaysAgo)
            ->where('due_date', '<=', $now)
            ->sum('amount');
        $received30Days = RepaymentSchedule::where('paid_at', '>=', $thirtyDaysAgo)
            ->sum('amount');
        
        // 回款率
        $collectionRate = $receivable30Days > 0 ? round(($received30Days / $receivable30Days) * 100, 2) : 0;
        
        // 提前结清率
        $completedLoans = Loan::where('state', 2)->count();
        $prepaidLoans = Loan::where('state', 2)
            ->where('closed_at', '<', DB::raw('DATE_ADD(disbursed_at, INTERVAL term_months MONTH)'))
            ->count();
        $prepayRate = $completedLoans > 0 ? round(($prepaidLoans / $completedLoans) * 100, 2) : 0;
        
        // 坏账率（DPD120+）
        $badDebtAmount = RepaymentSchedule::where('due_date', '<', $now->copy()->subDays(120))
            ->where('is_paid', 0)
            ->sum('amount');
        $totalLoanAmount = Loan::sum('amount');
        $badDebtRate = $totalLoanAmount > 0 ? round(($badDebtAmount / $totalLoanAmount) * 100, 2) : 0;
        
        // 企微绑定率
        $totalCustomers = Customer::count();
        $wecomCustomers = Customer::whereNotNull('wecom_userid')->count();
        $wecomRate = $totalCustomers > 0 ? round(($wecomCustomers / $totalCustomers) * 100, 2) : 0;
        
        // 代扣成功率（模拟数据）
        $autopaySuccess = 95.5;
        
        // 提醒发送量（近7日）
        $reminderCount = Communication::where('happened_at', '>=', $now->copy()->subDays(7))
            ->count();
        
        // 临期配置
        $dueDays = 3; // 从系统配置获取
        $dueFreq = 2; // 从系统配置获取
        
        return [
            'active_loans' => number_format($activeLoans),
            'active_customers' => number_format($activeCustomers),
            'monthly_loans' => number_format($monthlyLoans, 2),
            'overdue_rate' => $overdueRate . '%',
            'due_amount' => number_format($dueAmount, 2),
            'paid_interest' => number_format($paidInterest, 2),
            'overdue_amount' => number_format($overdueAmount, 2),
            'high_risk_customers' => number_format($highRiskCustomers),
            'inloan_balance' => number_format($inLoanBalance, 2),
            'receivable_30days' => number_format($receivable30Days, 2) . ' / ' . number_format($received30Days, 2),
            'collection_rate' => $collectionRate . '%',
            'prepay_rate' => $prepayRate . '%',
            'baddebt_rate' => $badDebtRate . '%',
            'wecom_rate' => $wecomRate . '%',
            'autopay_success' => $autopaySuccess . '%',
            'reminder_total' => number_format($reminderCount),
            'config' => $dueDays . '天 / ' . $dueFreq . '次',
        ];
    }
    
    /**
     * 获取高风险TOP5客户
     */
    public function getHighRiskTop5()
    {
        return Customer::whereIn('risk_level', [3, 4])
            ->with(['loans' => function($query) {
                $query->where('state', 1);
            }])
            ->orderBy('risk_level', 'desc')
            ->limit(5)
            ->get()
            ->map(function($customer) {
                return [
                    'name' => $customer->name,
                    'risk_level' => $customer->risk_level_label,
                    'loan_count' => $customer->loans->count(),
                    'total_amount' => number_format($customer->loans->sum('amount'), 2),
                ];
            });
    }
    
    /**
     * 获取提醒渠道统计（近7日）
     */
    public function getChannelStats()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        $stats = Communication::where('happened_at', '>=', $sevenDaysAgo)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->channel => $item->count
                ];
            });
        
        return [
            '电话' => $stats[1] ?? 0,
            '上门' => $stats[2] ?? 0,
            '微信' => $stats[3] ?? 0,
            '短信' => $stats[4] ?? 0,
            '其他' => $stats[9] ?? 0,
        ];
    }
}
