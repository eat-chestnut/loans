<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

/**
 * 客户表
 */
class Customer extends Model
{
    use SoftDeletes;

    public const RISK_LOW    = 1;
    public const RISK_MEDIUM = 2;
    public const RISK_HIGH   = 3;

    protected $fillable = [
        'name',
        'id_card',
        'phone',
        'address',
        'risk_level',
        'credit_score',
        'co_borrower',
        'type'
    ];

    protected $casts = [
        'risk_level'   => 'integer',
        'credit_score' => 'integer',
        'co_borrower' => 'json'
    ];

    protected $appends = [
        'risk_level_label',
        'collateral_no',
        'loan_statistics',
        'now_loans_total',
        'loans_total',
        'loans_repayment_total',
        'next_loans_repayment_date',
        'last_loans_repayment_date',
        'due_repayment_no'
    ];

    public static function riskLevelOptions(): array
    {
        return [
            0                 => '未评级',
            self::RISK_LOW    => '低',
            self::RISK_MEDIUM => '中',
            self::RISK_HIGH   => '高',
        ];
    }

    public function collaterals()
    {
        return $this->hasMany(Collateral::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function wecomContact()
    {
        return $this->hasOne(WecomContact::class);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class);
    }

    public function getRiskLevelLabelAttribute()
    {
        return self::riskLevelOptions()[$this->risk_level] ?? '未评级';
    }

    public function getCreditScoreAttribute()
    {
        // 如果数据库中有存储的信用分且需要手动更新，返回存储值
        // 否则动态计算
        if (isset($this->attributes['credit_score']) && request()->routeIs('admin.customers.index') === false) {
            return (int) $this->attributes['credit_score'];
        }

        return $this->calculateCreditScore();
    }

    /**
     * 动态计算客户信用分
     * 基础分100，根据还款历史调整
     */
    public function calculateCreditScore()
    {
        $score = 100;
        $graceDays = 3; // 3天宽限期

        // 获取所有相关的还款计划
        $repaymentSchedules = RepaymentSchedule::whereHas('loan', function($query) {
            $query->where('customer_id', $this->id);
        })->get();

        foreach ($repaymentSchedules as $schedule) {
            if ($schedule->is_paid) {
                // 已还款
                if ($schedule->paid_at) {
                    $dueDate = \Carbon\Carbon::parse($schedule->due_date);
                    $paidDate = \Carbon\Carbon::parse($schedule->paid_at);
                    $daysLate = $dueDate->diffInDays($paidDate);

                    if ($daysLate <= $graceDays) {
                        // 宽限期内或提前还款，加2分
                        $score += 2;
                    } else {
                        // 逾期还款
                        $score -= 5; // 每次逾期扣5分
                        $score -= ($daysLate - $graceDays) * 0.5; // 每逾期一天扣0.5分
                    }
                }
            } else {
                // 未还款
                if (now()->gt($schedule->due_date)) {
                    $dueDate = \Carbon\Carbon::parse($schedule->due_date);
                    $daysLate = $dueDate->diffInDays(now());

                    if ($daysLate > $graceDays) {
                        // 已逾期
                        $score -= 5; // 每次逾期扣5分
                        $score -= ($daysLate - $graceDays) * 0.5; // 每逾期一天扣0.5分
                    }
                }
            }
        }

        // 确保分数在0-100之间
        return max(0, min(100, round($score)));
    }

    /**
     * 根据信用分获取风险等级
     */
    public function getComputedRiskLevel()
    {
        $score = $this->calculateCreditScore();

        if ($score >= 80) {
            return self::RISK_LOW;
        } elseif ($score >= 60) {
            return self::RISK_MEDIUM;
        } elseif ($score >= 40) {
            return self::RISK_HIGH;
        } else {
            return 4; // 极高风险
        }
    }

    /**
     * 批量计算多个客户的信用分
     * 用于优化列表页性能
     */
    public static function batchCalculateCreditScores(array $customerIds)
    {
        $scores = [];

        // 获取所有相关还款计划
        $repaymentSchedules = RepaymentSchedule::whereHas('loan', function($query) use ($customerIds) {
            $query->whereIn('customer_id', $customerIds);
        })->with('loan')->get();

        // 按客户分组
        $schedulesByCustomer = [];
        foreach ($repaymentSchedules as $schedule) {
            $customerId = $schedule->loan->customer_id;
            if (!isset($schedulesByCustomer[$customerId])) {
                $schedulesByCustomer[$customerId] = [];
            }
            $schedulesByCustomer[$customerId][] = $schedule;
        }

        // 计算每个客户的信用分
        $graceDays = 3;
        foreach ($customerIds as $customerId) {
            $score = 100;
            $schedules = $schedulesByCustomer[$customerId] ?? [];

            foreach ($schedules as $schedule) {
                if ($schedule->is_paid && $schedule->paid_at) {
                    $dueDate = \Carbon\Carbon::parse($schedule->due_date);
                    $paidDate = \Carbon\Carbon::parse($schedule->paid_at);
                    $daysLate = $dueDate->diffInDays($paidDate);

                    if ($daysLate <= $graceDays) {
                        $score += 2;
                    } else {
                        $score -= 5;
                        $score -= ($daysLate - $graceDays) * 0.5;
                    }
                } elseif (!$schedule->is_paid && now()->gt($schedule->due_date)) {
                    $dueDate = \Carbon\Carbon::parse($schedule->due_date);
                    $daysLate = $dueDate->diffInDays(now());

                    if ($daysLate > $graceDays) {
                        $score -= 5;
                        $score -= ($daysLate - $graceDays) * 0.5;
                    }
                }
            }

            $scores[$customerId] = max(0, min(100, round($score)));
        }

        return $scores;
    }

    public function getCollateralNoAttribute()
    {
        return $this->collaterals()->count();
    }

    public function getLoanStatisticsAttribute()
    {
        return $this->loans()->where('state', Loan::STATE_CLOSED)->count() .' / '. $this->loans()->count();
    }

    public function getNowLoansTotalAttribute()
    {
        return $this->loans()->where('state', Loan::STATE_NEW)->pluck('amount')->sum();
    }

    public function getLoansTotalAttribute()
    {
        return $this->loans()->pluck('amount')->sum();
    }

    public function getLoansRepaymentTotalAttribute()
    {
        return $this->loans->pluck('paid_amount')->sum();
    }

    public function getNextLoansRepaymentDateAttribute()
    {
        return $this->loans->pluck('repaymentSchedules')->flatten()->where('is_paid', 0)->min('due_date');
    }

    public function getLastLoansRepaymentDateAttribute()
    {
        return $this->loans->pluck('repaymentSchedules')->flatten()->where('is_paid', 0)->max('due_date');
    }

    public function getDueRepaymentNoAttribute()
    {
        $no =  $this->loans->pluck('repaymentSchedules')->flatten()->where('is_overdue', 1)->count();
        return $no + 0;
    }

}
