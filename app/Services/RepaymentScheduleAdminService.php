<?php

namespace App\Services;

use App\Models\RepaymentSchedule;
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
        parent::searchable($query);
        $query->when($this->request->get('max_due_date'), function ($query, $date) {
            $query->whereDate('due_date', '<=', $date);
        });
    }
}
