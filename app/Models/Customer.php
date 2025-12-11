<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'risk_level'   => 'integer',
        'credit_score' => 'integer',
        'co_borrower' => 'json'
    ];

    protected $appends = [
        'risk_level_label',
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

    public function wecomContacts()
    {
        return $this->hasMany(WecomContact::class);
    }

    public function getRiskLevelLabelAttribute()
    {
        return self::riskLevelOptions()[$this->risk_level] ?? '未评级';
    }
}
