<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

/**
 * 抵押物
 */
class Collateral extends Model
{
    use SoftDeletes;

    public const TYPE_PROPERTY = 1;
    public const TYPE_VEHICLE  = 2;
    public const TYPE_EQUITY   = 3;
    public const TYPE_OTHER    = 9;

    protected $fillable = [
        'customer_id',
        'name',
        'type',
        'city',
        'discount_rate',
        'pledge_value',
        'certificate_no',
        'area',
        'note',
    ];

    protected $casts = [
        'discount_rate' => 'float',
        'pledge_value'  => 'float',
        'area'          => 'float',
        'customer_id'   => 'integer',
        'type'          => 'integer',
    ];

    protected $appends = [
        'type_label',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_PROPERTY => '房产',
            self::TYPE_VEHICLE  => '车辆',
            self::TYPE_EQUITY   => '股权',
            self::TYPE_OTHER    => '其他',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function getTypeLabelAttribute()
    {
        return self::typeOptions()[$this->type] ?? self::typeOptions()[self::TYPE_OTHER];
    }
}
