<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

class WecomLog extends Model
{
    use SoftDeletes;

    protected $casts = [
        'customer_id' => 'integer',
        'loan_id'     => 'integer',
        'sent_at'     => 'datetime',
    ];

    protected $fillable = [
        'customer_id',
        'loan_id',
        'sent_at',
        'contact_name',
        'wechat_id',
        'content',
    ];
}
