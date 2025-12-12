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
        'content' => 'json'
    ];

    protected $fillable = [
        'customer_id',
        'loan_id',
        'sent_at',
        'contact_name',
        'wechat_id',
        'content',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function wecom()
    {
        return $this->belongsTo(WecomContact::class, 'wechat_id');
    }
}
