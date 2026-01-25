<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

class RepaymentSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_id',
        'period',
        'due_date',
        'amount',
        'interest',
        'principal',
        'remaining_principal',
        'is_paid',
        'is_overdue',
        'paid_at',
        'reminder_sent_at',
        'reminder_times',
        'wecom_reminder_sent_at',
        'wecom_reminder_times',
        'remark',
    ];

    protected $casts = [
        'loan_id'             => 'integer',
        'period'              => 'integer',
        'amount'              => 'float',
        'interest'            => 'float',
        'principal'           => 'float',
        'remaining_principal' => 'float',
        'is_paid'             => 'boolean',
        'is_overdue'          => 'boolean',
        'reminder_sent_at'    => 'datetime',
        'reminder_times'      => 'integer',
        'wecom_reminder_sent_at' => 'datetime',
        'wecom_reminder_times'   => 'integer',
    ];

    protected $dates = [
        'due_date',
        'paid_at',
        'reminder_sent_at',
        'wecom_reminder_sent_at',
    ];

    protected $appends = ['over_due_day', 'state'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function getOverDueDayAttribute()
    {
        return round(now()->diffInDays($this->due_date, true)).'天';
    }


    public function getStateAttribute()
    {
        return $this->is_paid ?: (now()->diffInDays($this->due_date) < 0 ? -1 : 0);
    }
}
