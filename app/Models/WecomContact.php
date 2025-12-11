<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

class WecomContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'wechat_id',
        'mobile',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
