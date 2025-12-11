<?php

namespace App\Services;

use App\Models\Collateral;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 抵押物
 *
 * @method Collateral getModel()
 * @method Collateral|\Illuminate\Database\Query\Builder query()
 */
class CollateralService extends AdminService
{
	protected string $modelName = Collateral::class;

    public static function options()
    {
        return self::make()
            ->query()
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->getKey() => "{$item->name}"];
            })
            ->toArray();
    }
}
