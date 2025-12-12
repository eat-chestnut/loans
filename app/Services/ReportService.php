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
 * 报表服务类 - 提供各种报表数据和图表配置
 */
class ReportService
{
    /**
     * 获取现金流数据（按月 应收 vs 实收）
     */
    public function getCashFlowData($months = 12)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();
        
        // 按月统计应收金额
        $receivableByMonth = RepaymentSchedule::selectRaw(
                'YEAR(due_date) as year, 
                 MONTH(due_date) as month, 
                 SUM(amount) as total'
            )
            ->where('due_date', '>=', $startDate)
            ->where('due_date', '<=', $endDate)
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->mapWithKeys(function($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->total];
            });
        
        // 按月统计实收金额
        $receivedByMonth = RepaymentSchedule::selectRaw(
                'YEAR(paid_at) as year, 
                 MONTH(paid_at) as month, 
                 SUM(amount) as total'
            )
            ->where('paid_at', '>=', $startDate)
            ->where('paid_at', '<=', $endDate)
            ->whereNotNull('paid_at')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->mapWithKeys(function($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->total];
            });
        
        // 填充所有月份
        $labels = [];
        $receivableData = [];
        $receivedData = [];
        
        for ($date = $startDate->copy(); $date <= $endDate; $date->addMonth()) {
            $key = $date->format('Y-m');
            $labels[] = $date->format('Y年m月');
            $receivableData[] = $receivableByMonth[$key] ?? 0;
            $receivedData[] = $receivedByMonth[$key] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => '应收金额',
                    'data' => $receivableData,
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => '实收金额',
                    'data' => $receivedData,
                    'borderColor' => '#48bb78',
                    'backgroundColor' => 'rgba(72, 187, 120, 0.1)',
                    'fill' => true,
                ]
            ]
        ];
    }
    
    /**
     * 获取风险等级分布数据
     */
    public function getRiskDistribution()
    {
        $distribution = Customer::selectRaw('risk_level, COUNT(*) as count')
            ->groupBy('risk_level')
            ->orderBy('risk_level')
            ->get()
            ->mapWithKeys(function($item) {
                $label = Customer::riskLevelOptions()[$item->risk_level] ?? '未知';
                return [$label => $item->count];
            });
        
        return [
            'labels' => array_keys($distribution->toArray()),
            'data' => array_values($distribution->toArray()),
            'backgroundColor' => [
                '#48bb78', // 低风险 - 绿色
                '#ed8936', // 中风险 - 橙色
                '#f56565', // 高风险 - 红色
                '#9f1239', // 极高风险 - 深红
            ]
        ];
    }
    
    /**
     * 获取净借还变化（按月）
     */
    public function getNetLoanChange($months = 12)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();
        
        // 按月统计放款金额
        $loanedByMonth = Loan::selectRaw(
                'YEAR(disbursed_at) as year, 
                 MONTH(disbursed_at) as month, 
                 SUM(amount) as total'
            )
            ->where('disbursed_at', '>=', $startDate)
            ->where('disbursed_at', '<=', $endDate)
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->mapWithKeys(function($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->total];
            });
        
        // 按月统计还款金额
        $repaidByMonth = RepaymentSchedule::selectRaw(
                'YEAR(paid_at) as year, 
                 MONTH(paid_at) as month, 
                 SUM(amount) as total'
            )
            ->where('paid_at', '>=', $startDate)
            ->where('paid_at', '<=', $endDate)
            ->whereNotNull('paid_at')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->mapWithKeys(function($item) {
                $key = $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
                return [$key => $item->total];
            });
        
        // 计算净借还
        $labels = [];
        $netData = [];
        
        for ($date = $startDate->copy(); $date <= $endDate; $date->addMonth()) {
            $key = $date->format('Y-m');
            $labels[] = $date->format('Y年m月');
            $loaned = $loanedByMonth[$key] ?? 0;
            $repaid = $repaidByMonth[$key] ?? 0;
            $netData[] = $loaned - $repaid;
        }
        
        return [
            'labels' => $labels,
            'data' => $netData,
            'borderColor' => '#805ad5',
            'backgroundColor' => 'rgba(128, 90, 213, 0.1)',
            'fill' => true,
        ];
    }
    
    /**
     * 获取提醒渠道占比（近7日）
     */
    public function getChannelDistribution()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        $smsCount = SmsLog::where('sent_at', '>=', $sevenDaysAgo)->count();
        $wecomCount = WecomLog::where('sent_at', '>=', $sevenDaysAgo)->count();
        
        return [
            'labels' => ['短信', '企微'],
            'data' => [$smsCount, $wecomCount],
            'backgroundColor' => [
                '#ed8936', // 短信 - 橙色
                '#38b2ac', // 企微 - 青色
            ]
        ];
    }
    
    /**
     * 获取资产质量数据（PAR/NPL/Roll）
     */
    public function getAssetQuality()
    {
        $now = Carbon::now();
        
        // 计算各逾期天数段的金额
        $par1 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 1 AND 30')
            ->sum('principal');
            
        $par2 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 31 AND 60')
            ->sum('principal');
            
        $par3 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 61 AND 90')
            ->sum('principal');
            
        $par4 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) > 90')
            ->sum('principal');
            
        $totalOutstanding = RepaymentSchedule::where('is_paid', 0)->sum('principal');
        
        // 计算占比
        $par1Rate = $totalOutstanding > 0 ? round(($par1 / $totalOutstanding) * 100, 2) : 0;
        $par2Rate = $totalOutstanding > 0 ? round(($par2 / $totalOutstanding) * 100, 2) : 0;
        $par3Rate = $totalOutstanding > 0 ? round(($par3 / $totalOutstanding) * 100, 2) : 0;
        $par4Rate = $totalOutstanding > 0 ? round(($par4 / $totalOutstanding) * 100, 2) : 0;
        
        // NPL通常指PAR3+PAR4
        $nplRate = $par3Rate + $par4Rate;
        
        return [
            'total_outstanding' => number_format($totalOutstanding, 2),
            'par' => [
                ['name' => 'PAR 1-30', 'amount' => number_format($par1, 2), 'rate' => $par1Rate . '%'],
                ['name' => 'PAR 31-60', 'amount' => number_format($par2, 2), 'rate' => $par2Rate . '%'],
                ['name' => 'PAR 61-90', 'amount' => number_format($par3, 2), 'rate' => $par3Rate . '%'],
                ['name' => 'PAR 90+', 'amount' => number_format($par4, 2), 'rate' => $par4Rate . '%'],
            ],
            'npl_rate' => $nplRate . '%',
        ];
    }
    
    /**
     * 获取逾期漏斗数据
     */
    public function getOverdueFunnel()
    {
        $now = Carbon::now();
        
        // 计算各阶段的逾期金额
        $totalDue = RepaymentSchedule::where('is_paid', 0)->sum('amount');
        $overdue1 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 1 AND 30')
            ->sum('amount');
        $overdue2 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 31 AND 60')
            ->sum('amount');
        $overdue3 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 61 AND 90')
            ->sum('amount');
        $overdue4 = RepaymentSchedule::where('due_date', '<', $now)
            ->where('is_paid', 0)
            ->whereRaw('DATEDIFF(NOW(), due_date) > 90')
            ->sum('amount');
        
        return [
            'labels' => ['待还款', '逾期1-30天', '逾期31-60天', '逾期61-90天', '逾期90天以上'],
            'data' => [$totalDue, $overdue1, $overdue2, $overdue3, $overdue4],
            'backgroundColor' => '#f56565',
        ];
    }
    
    /**
     * 获取Vintage分析数据
     */
    public function getVintageAnalysis($months = 12)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();
        
        $vintages = Loan::selectRaw(
                'DATE_FORMAT(disbursed_at, "%Y-%m") as vintage,
                 COUNT(*) as loan_count,
                 SUM(amount) as loan_amount,
                 SUM(CASE WHEN state = 0 THEN amount ELSE 0 END) as outstanding_amount'
            )
            ->where('disbursed_at', '>=', $startDate)
            ->groupBy('vintage')
            ->orderBy('vintage', 'desc')
            ->limit(6)
            ->get();
        
        $result = [];
        foreach ($vintages as $vintage) {
            // 计算该批次的逾期率
            $overdueAmount = RepaymentSchedule::join('loans', 'repayment_schedules.loan_id', '=', 'loans.id')
                ->where('loans.disbursed_at', 'like', $vintage->vintage . '%')
                ->where('repayment_schedules.due_date', '<', now())
                ->where('repayment_schedules.is_paid', 0)
                ->sum('repayment_schedules.amount');
            
            $overdueRate = $vintage->loan_amount > 0 ? round(($overdueAmount / $vintage->loan_amount) * 100, 2) : 0;
            
            $result[] = [
                'vintage' => $vintage->vintage,
                'loan_count' => $vintage->loan_count,
                'loan_amount' => number_format($vintage->loan_amount, 2),
                'outstanding' => number_format($vintage->outstanding_amount, 2),
                'overdue_rate' => $overdueRate . '%',
                'npl' => $overdueRate > 10 ? '是' : '否', // 简化的NPL判断
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取队列分析（Cohort Retention）
     */
    public function getCohortAnalysis($months = 6)
    {
        // 简化的队列分析实现
        $cohorts = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();
        
        for ($i = 0; $i < $months; $i++) {
            $cohortStart = $startDate->copy()->addMonths($i);
            $cohortEnd = $cohortStart->copy()->endOfMonth();
            
            // 获取该月新增的客户
            $newCustomers = Loan::whereBetween('disbursed_at', [$cohortStart, $cohortEnd])
                ->distinct('customer_id')
                ->count('customer_id');
            
            // 计算留存率（简化：以是否还有在贷作为留存）
            $retention30 = $this->calculateRetention($cohortStart, 30);
            $retention60 = $this->calculateRetention($cohortStart, 60);
            $retention90 = $this->calculateRetention($cohortStart, 90);
            
            $cohorts[] = [
                'cohort' => $cohortStart->format('Y-m'),
                'count' => $newCustomers,
                'retention_30' => $retention30 . '%',
                'retention_60' => $retention60 . '%',
                'retention_90' => $retention90 . '%',
            ];
        }
        
        return array_reverse($cohorts);
    }
    
    private function calculateRetention($cohortDate, $days)
    {
        $retained = Loan::where('disbursed_at', 'like', $cohortDate->format('Y-m') . '%')
            ->where('state', Loan::STATE_NEW) // 仍在贷
            ->count();
            
        $total = Loan::where('disbursed_at', 'like', $cohortDate->format('Y-m') . '%')
            ->count();
            
        return $total > 0 ? round(($retained / $total) * 100, 2) : 0;
    }
}
