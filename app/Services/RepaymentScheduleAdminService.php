<?php

namespace App\Services;

use App\Models\RepaymentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 还款计划管理服务
 */
class RepaymentScheduleAdminService extends AdminService
{
    protected string $modelName = RepaymentSchedule::class;

    public function addRelations($query, string $scene = 'list')
    {
        $query->with(['loan', 'loan.customer']);
    }

    public function searchable($query)
    {
        $params = $this->request->all();
        $query->when(isset($params['is_paid']), function ($query) use ($params) {
            $query->where('is_paid', $params['is_paid']);
        })->when(data_get($params, 'due_date'), function ($query, $date) {
            $query->whereDate('due_date', '<=', $date);
        })->when(data_get($params, 'loan.customer.name'), function (Builder $query, $customerName) {
            $query->whereHas('loan.customer', function (Builder $query) use ($customerName) {
                $query->where('name', 'like', '%' . $customerName . '%');
            });
        });


    }

    public function sortable($query)
    {
        $query->orderBy('due_date');
    }
}
