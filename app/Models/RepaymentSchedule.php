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
        'paid_at',
        'reminder_sent_at',
        'reminder_times',
        'wecom_reminder_sent_at',
        'wecom_reminder_times',
        'state',
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
        'due_date'            => 'date',
        'paid_at'             => 'datetime',
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

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
