<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

/**
 * 放款表
 */
class Loan extends Model
{
    use SoftDeletes;

    public const STATE_NEW     = 1;
    public const STATE_RENEWAL = 2;
    public const STATE_CLOSED  = 3;

    protected $fillable = [
        'loan_number',
        'ticket_no',
        'customer_id',
        'co_borrower_snapshot',
        'collateral_total_value',
        'amount',
        'total_interest_amount',
        'term_months',
        'rate_month',
        'discount_ratio',
        'month_profit_ratio',
        'city',
        'disbursed_at',
        'state',
        'note',
        'admin_user_id',
        'start_date',
        'closed_at',
        'overdue_days',
    ];

    protected $casts = [
        'amount'            => 'float',
        'term_months'       => 'integer',
        'rate_month'        => 'float',
        'overdue_days'      => 'integer',
        'customer_id'       => 'integer',
        'admin_user_id'     => 'integer',
        'co_borrower_snapshot' => 'array',
        'collateral_total_value' => 'float',
        'total_interest_amount' => 'float',
        'discount_ratio'    => 'float',
        'month_profit_ratio'=> 'float',
        'start_date'        => 'date',
        'disbursed_at'      => 'date',
        'closed_at'         => 'date',
    ];

    protected $appends = [
        'state_label',
        'monthly_payment',
        'total_interest',
    ];

    public static function stateOptions(): array
    {
        return [
            self::STATE_NEW     => '新增',
            self::STATE_RENEWAL => '续借',
            self::STATE_CLOSED  => '结清',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function collaterals()
    {
        return $this->belongsToMany(Collateral::class, 'loan_collaterals');
    }

    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class);
    }

    public function getStateLabelAttribute()
    {
        return self::stateOptions()[$this->state] ?? '未知';
    }

    public function getTotalInterestAttribute()
    {
        if (!is_null($this->total_interest_amount)) {
            return $this->total_interest_amount;
        }

        $amount = (float)$this->amount;
        $rate   = (float)$this->rate_month / 100;
        $terms  = (int)$this->term_months;

        return round($amount * $rate * $terms, 2);
    }

    public function getMonthlyPaymentAttribute()
    {
        $terms = max(1, (int)$this->term_months);
        $amount = (float)$this->amount + $this->total_interest;

        return round($amount / $terms, 2);
    }
}
