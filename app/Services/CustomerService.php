<?php

namespace App\Services;

use App\Models\Customer;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 客户表
 *
 * @method Customer getModel()
 * @method Customer|\Illuminate\Database\Query\Builder query()
 */
class CustomerService extends AdminService
{
	protected string $modelName = Customer::class;

    public function addRelations($query, string $scene = 'list')
    {
        $query->with(['wecomContact', 'loans.repaymentSchedules', 'collaterals', 'communications.adminUser', 'communications.loan']);
    }

    public function options()
    {
        return self::make()
            ->query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($item) {
                $suffix = $item->phone ? " ({$item->phone})" : '';

                return [$item->getKey() => "{$item->name}{$suffix}"];
            })
            ->toArray();
    }
}
