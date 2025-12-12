<?php

namespace App\Services;

use App\Models\RepaymentSchedule;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 逾期管理服务类
 */
class OverdueService extends AdminService
{
    protected string $modelName = RepaymentSchedule::class;

    public function searchable($query)
    {
        parent::searchable($query);
        $query->whereDate('due_date', '<', now()->toDateString())->where('is_paid', 0);
    }

    public function sortColumn()
    {
        return 'due_date';
    }
}
