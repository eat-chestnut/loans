<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

class SmsLog extends Model
{
    use SoftDeletes;

    public const TEMPLATE_REPAYMENT_REMINDER = 'repayment_due_reminder';

    protected $casts = [
        'customer_id' => 'integer',
        'loan_id'     => 'integer',
        'sent_at'     => 'datetime',
        'state'       => 'integer',
    ];

    protected $fillable = [
        'customer_id',
        'loan_id',
        'sent_at',
        'phone',
        'template_key',
        'state',
        'content',
    ];
}
