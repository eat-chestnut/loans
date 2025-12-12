<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\SmsLog;
use App\Models\WecomLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard服务类 - 提供各种KPI指标统计
 */
class DashboardService
{
    /**
     * 获取时间范围
     */
    protected function getDateRange(string $period = 'month'): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'week':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                    'label' => '本周',
                ];
            case 'quarter':
                return [
                    'start' => $now->copy()->startOfQuarter(),
                    'end' => $now->copy()->endOfQuarter(),
                    'label' => '本季度',
                ];
            case 'year':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear(),
                    'label' => '本年度',
                ];
            case 'month':
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                    'label' => '本月',
                ];
        }
    }
    /**
     * 获取核心KPI指标
     */
    public function getCoreMetrics(string $period = 'month')
    {
        $now = Carbon::now();
        $dateRange = $this->getDateRange($period);
        $periodStart = $dateRange['start'];
        $periodEnd = $dateRange['end'];
        $periodLabel = $dateRange['label'];

        // 在贷笔数（总数）
        $activeLoans = Loan::whereIn('state', [Loan::STATE_NEW])->count();

        // 服务客户数（总数）
        $activeCustomers = Loan::whereIn('state', [Loan::STATE_NEW])
            ->distinct('customer_id')
            ->count('customer_id');

        // 本期放款金额
        $periodLoans = Loan::whereBetween('disbursed_at', [$periodStart, $periodEnd])
            ->sum('amount');

        // 本期放款笔数
        $periodLoanCount = Loan::whereBetween('disbursed_at', [$periodStart, $periodEnd])->count();

        // 逾期率计算（总体）
        $overdueSchedules = RepaymentSchedule::where('is_overdue', true)->count();
        $totalSchedules = RepaymentSchedule::where('is_paid', false)->count();
        $overdueRate = $totalSchedules > 0 ? round(($overdueSchedules / $totalSchedules) * 100, 2) : 0;

        // 待收款（本息）- 总体
        $dueAmount = RepaymentSchedule::where('is_paid', false)->sum('amount');

        // 本期已收利息
        $paidInterest = RepaymentSchedule::where('is_paid', true)
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->sum('interest');

        // 本期已收本金
        $paidPrincipal = RepaymentSchedule::where('is_paid', true)
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->sum('principal');

        // 逾期金额 - 总体
        $overdueAmount = RepaymentSchedule::where('is_overdue', true)
            ->where('is_paid', false)
            ->sum('amount');

        // 高风险客户数 - 总体
        $highRiskCustomers = Customer::whereIn('risk_level', [3, 4])->count();

        // 在贷余额 - 总体（未还本金）
        $inLoanBalance = RepaymentSchedule::where('is_paid', false)->sum('principal');

        // 本期应收/实收
        $periodReceivable = RepaymentSchedule::whereBetween('due_date', [$periodStart, $periodEnd])
            ->sum('amount');
        $periodReceived = RepaymentSchedule::where('is_paid', true)
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->sum('amount');

        // 本期回款率
        $collectionRate = $periodReceivable > 0 ? round(($periodReceived / $periodReceivable) * 100, 2) : 0;

        // 提前结清率 - 总体
        $completedLoans = Loan::where('state', Loan::STATE_COMPLETED)->count();
        $prepaidLoans = Loan::where('state', Loan::STATE_COMPLETED)
            ->whereRaw('closed_at < DATE_ADD(disbursed_at, INTERVAL term_months MONTH)')
            ->count();
        $prepayRate = $completedLoans > 0 ? round(($prepaidLoans / $completedLoans) * 100, 2) : 0;

        // 坏账率（DPD120+）- 总体
        $badDebtAmount = RepaymentSchedule::where('due_date', '<', $now->copy()->subDays(120))
            ->where('is_paid', false)
            ->sum('amount');
        $totalLoanAmount = Loan::sum('amount');
        $badDebtRate = $totalLoanAmount > 0 ? round(($badDebtAmount / $totalLoanAmount) * 100, 2) : 0;

        // 企微绑定率 - 总体
        $totalCustomers = Customer::count();
        $wecomBoundCustomers = Customer::whereHas('wecomContact')->count();
        $wecomRate = $totalCustomers > 0 ? round(($wecomBoundCustomers / $totalCustomers) * 100, 2) : 0;

        // 本期短信发送量
        $smsSent = SmsLog::whereBetween('sent_at', [$periodStart, $periodEnd])->count();
        $smsSuccess = SmsLog::whereBetween('sent_at', [$periodStart, $periodEnd])
            ->where('state', 1)
            ->count();

        // 本期企微发送量
        $wecomSent = WecomLog::whereBetween('sent_at', [$periodStart, $periodEnd])->count();

        // 本期提醒总量
        $reminderTotal = $smsSent + $wecomSent;

        // 本期逾期笔数
        $periodOverdueCount = RepaymentSchedule::where('is_overdue', true)
            ->whereBetween('due_date', [$periodStart, $periodEnd])
            ->count();

        return [
            // 基础指标（总体）
            'active_loans' => number_format($activeLoans),
            'active_customers' => number_format($activeCustomers),
            'overdue_rate' => $overdueRate . '%',
            'due_amount' => '¥' . number_format($dueAmount, 2),
            'overdue_amount' => '¥' . number_format($overdueAmount, 2),
            'high_risk_customers' => number_format($highRiskCustomers),
            'inloan_balance' => '¥' . number_format($inLoanBalance, 2),
            'prepay_rate' => $prepayRate . '%',
            'baddebt_rate' => $badDebtRate . '%',
            'wecom_rate' => $wecomRate . '%',

            // 本期指标
            'period_label' => $periodLabel,
            'period_loans' => '¥' . number_format($periodLoans, 2),
            'period_loan_count' => number_format($periodLoanCount) . '笔',
            'period_paid_interest' => '¥' . number_format($paidInterest, 2),
            'period_paid_principal' => '¥' . number_format($paidPrincipal, 2),
            'period_receivable' => '¥' . number_format($periodReceivable, 2),
            'period_received' => '¥' . number_format($periodReceived, 2),
            'period_collection_rate' => $collectionRate . '%',
            'period_sms_sent' => number_format($smsSent),
            'period_sms_success' => number_format($smsSuccess),
            'period_wecom_sent' => number_format($wecomSent),
            'period_reminder_total' => number_format($reminderTotal),
            'period_overdue_count' => number_format($periodOverdueCount),

            // 兼容旧字段
            'monthly_loans' => '¥' . number_format($periodLoans, 2),
            'paid_interest' => '¥' . number_format($paidInterest, 2),
            'receivable_30days' => '¥' . number_format($periodReceivable, 2) . ' / ¥' . number_format($periodReceived, 2),
            'collection_rate' => $collectionRate . '%',
            'reminder_total' => number_format($reminderTotal),
            'config' => settings()->get('reminder.rules') ? count(settings()->get('reminder.rules')) . '条规则' : '未配置',
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
     * 获取提醒渠道统计
     */
    public function getChannelStats(string $period = 'month')
    {
        $dateRange = $this->getDateRange($period);
        $periodStart = $dateRange['start'];
        $periodEnd = $dateRange['end'];

        $smsCount = SmsLog::whereBetween('sent_at', [$periodStart, $periodEnd])->count();
        $wecomCount = WecomLog::whereBetween('sent_at', [$periodStart, $periodEnd])->count();

        return [
            '短信' => $smsCount,
            '企微' => $wecomCount,
            '合计' => $smsCount + $wecomCount,
        ];
    }
}
