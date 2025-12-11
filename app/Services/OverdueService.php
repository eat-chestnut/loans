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

    public function list()
    {
        $query = $this->model::with(['loan', 'loan.customer'])
            ->where('due_date', '<', now())
            ->where('is_paid', 0)
            ->orderBy('due_date', 'asc');

        // 计算逾期天数
        $query->selectRaw('*, DATEDIFF(NOW(), due_date) as overdue_days');

        $list = $query->paginate(request()->input('perPage', 20));

        return $this->response()->success($list);
    }
}
