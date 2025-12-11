<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Slowlyo\OwlAdmin\Models\BaseModel as Model;

/**
 * 沟通记录
 */
class Communication extends Model
{
    use SoftDeletes;

    public const CHANNEL_PHONE = 1;
    public const CHANNEL_VISIT = 2;
    public const CHANNEL_WECHAT = 3;
    public const MESSAGE = 4;
    public const OTHER = 9;

    protected $fillable = [
        'customer_id',
        'loan_id',
        'admin_user_id',
        'channel',
        'content',
        'happened_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'loan_id' => 'integer',
        'admin_user_id' => 'integer',
        'channel' => 'integer',
        'happened_at' => 'datetime',
    ];

    protected $dates = [
        'happened_at',
    ];

    protected $appends = [
        'channel_label',
    ];

    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_PHONE => '电话',
            self::CHANNEL_VISIT => '上门',
            self::CHANNEL_WECHAT => '微信',
            self::MESSAGE => '短信',
            self::OTHER => '其他',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(\Slowlyo\OwlAdmin\Models\AdminUser::class);
    }

    public function getChannelLabelAttribute()
    {
        return self::channelOptions()[$this->channel] ?? self::channelOptions()[self::OTHER];
    }
}
