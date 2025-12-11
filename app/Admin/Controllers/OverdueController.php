<?php

namespace App\Admin\Controllers;

use App\Models\RepaymentSchedule;
use App\Services\RepaymentScheduleService;
use App\Services\OverdueService;
use Slowlyo\OwlAdmin\Controllers\AdminController;

/**
 * 逾期管理控制器
 */
class OverdueController extends AdminController
{
    protected string $serviceName = OverdueService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->filterTogglable(false)
            ->headerToolbar([
                $this->createButton(),
                amis()->Action()->actionType('ajax')->label('批量短信提醒')
                    ->api('post:/admin-api/overdue/bulk-sms')
                    ->confirmText('确认向所有逾期客户发送短信提醒？'),
                ...$this->baseHeaderToolBar()
            ])
            ->columns([
                amis()->TableColumn('loan.loan_number', '贷款编号')->copyable(),
                amis()->TableColumn('loan.customer.name', '客户姓名'),
                amis()->TableColumn('loan.customer.phone', '联系电话'),
                amis()->TableColumn('period', '期数'),
                amis()->TableColumn('due_date', '应还日期')->type('date'),
                amis()->TableColumn('overdue_days', '逾期天数')
                    ->set('valueTpl', '${overdueDays|days}'),
                amis()->TableColumn('amount', '应还金额')
                    ->set('valueTpl', '￥${amount|number:2}'),
                amis()->TableColumn('principal', '本金')
                    ->set('valueTpl', '￥${principal|number:2}'),
                amis()->TableColumn('interest', '利息')
                    ->set('valueTpl', '￥${interest|number:2}'),
                amis()->TableColumn('loan.customer.risk_level_label', '风险等级'),
                amis()->TableColumn('loan.customer.phone', '操作')
                    ->set('valueTpl', $this->getActionButtons()),
            ])
            ->api('/admin-api/overdue');

        return $this->baseList($crud);
    }

    protected function getActionButtons()
    {
        return '<div class="flex gap-2">
            <button class="cxd-Button cxd-Button--primary cxd-Button--size-sm" onclick="makeCall(\'${loan.customer.phone}\')">电话</button>
            <button class="cxd-Button cxd-Button--default cxd-Button--size-sm" onclick="sendSMS(\'${loan.customer.phone}\', \'${loan.customer.name}\', \'${amount}\')">短信</button>
            <button class="cxd-Button cxd-Button--default cxd-Button--size-sm" onclick="markAsPaid(\'${id}\')">标记还款</button>
        </div>';
    }

    public function bulkSMS()
    {
        // 获取所有逾期记录
        $overdueSchedules = RepaymentSchedule::with(['loan.customer'])
            ->where('due_date', '<', now())
            ->where('is_paid', 0)
            ->get();

        $count = 0;
        foreach ($overdueSchedules as $schedule) {
            // 模拟发送短信
            // 实际项目中这里会调用短信API
            $count++;
        }

        return $this->response()->success("已向 {$count} 位逾期客户发送短信提醒");
    }

    public function markAsPaid($id)
    {
        $service = new RepaymentScheduleService();
        $result = $service->markAsPaid($id);
        
        if ($result) {
            return $this->response()->success('标记成功');
        }
        
        return $this->response()->fail('标记失败');
    }
}
