<?php

namespace App\Admin\Controllers;

use App\Admin\Renderers\Amis;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\RepaymentScheduleService;
use App\Services\RepaymentScheduleAdminService;
use Slowlyo\OwlAdmin\Controllers\AdminController;

/**
 * 还款计划
 */
class RepaymentScheduleController extends AdminController
{
    protected string $serviceName = RepaymentScheduleAdminService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->filterTogglable()
            ->headerToolbar([
                ...$this->baseHeaderToolBar()
            ])
            ->columns([
                amis()->TableColumn('loan.loan_number', '贷款编号')->copyable(),
                amis()->TableColumn('loan.customer.name', '客户姓名'),
                amis()->TableColumn('period', '期数'),
                amis()->TableColumn('due_date', '还款日期'),
                amis()->TableColumn('amount', '应还金额')
                    ->set('valueTpl', '${amount|number:2}'),
                amis()->TableColumn('principal', '本金')
                    ->set('valueTpl', '${principal|number:2}'),
                amis()->TableColumn('interest', '利息')
                    ->set('valueTpl', '${interest|number:2}'),
                amis()->TableColumn('remaining_principal', '剩余本金')
                    ->set('valueTpl', '${remaining_principal|number:2}'),
                amis()->TableColumn('state', '状态'),
                amis()->TableColumn('paid_at', '还款时间'),
                $this->rowActions([
                    $this->rowShowButton(),
                    amis()->Action()->actionType('ajax')->label('标记还款')
                        ->api('post:/admin-api/repayment-schedules/${id}/mark-paid')
                        ->confirmText('确认标记该期已还款？'),
                ]),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->TextControl('loan.loan_number', '贷款编号')->clearable(),
                    amis()->TextControl('loan.customer.name', '客户姓名')->clearable(),
                    amis()->SelectControl('is_paid', '还款状态')
                        ->options([
                            ['label' => '全部', 'value' => ''],
                            ['label' => '已还款', 'value' => 1],
                            ['label' => '待还款', 'value' => 0],
                        ])
                        ->clearable(),
                    amis()->DateRangeControl('due_date', '还款日期')->clearable(),
                ])
            );

        return $this->baseList($crud);
    }

    public function show($id)
    {
        return $this->baseDetail()->body([
            amis()->FieldSetControl()->title('基础信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('loan.loan_number', '贷款编号')->static(),
                    amis()->TextControl('loan.customer.name', '客户姓名')->static(),
                    amis()->TextControl('period', '期数')->static(),
                    amis()->TextControl('due_date', '还款日期')->static(),
                ]),
                amis()->GroupControl()->body([
                    amis()->TextControl('amount', '应还金额')->static(),
                    amis()->TextControl('principal', '本金')->static(),
                    amis()->TextControl('interest', '利息')->static(),
                    amis()->TextControl('remaining_principal', '剩余本金')->static(),
                ]),
            ]),
            amis()->FieldSetControl()->title('还款状态')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('state', '状态')->static(),
                    amis()->TextControl('paid_at', '还款时间')->static(),
                ]),
                amis()->TextControl('remark', '备注')->static(),
            ]),
        ]);
    }

    /**
     * 标记还款
     */
    public function markPaid($id)
    {
        try {
            $service = new RepaymentScheduleService();
            $result = $service->markAsPaid($id);
            
            if ($result) {
                return $this->response()->success('标记成功');
            } else {
                return $this->response()->error('标记失败，请检查状态');
            }
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    /**
     * 获取贷款的还款统计
     */
    public function repaymentStats($loanId)
    {
        try {
            $service = new RepaymentScheduleService();
            $stats = $service->getRepaymentStats($loanId);
            
            return $this->response()->success($stats);
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    /**
     * 重新生成还款计划
     */
    public function regenerate($loanId)
    {
        try {
            $loan = Loan::find($loanId);
            if (!$loan) {
                return $this->response()->error('贷款不存在');
            }
            
            $service = new RepaymentScheduleService();
            $service->saveSchedule($loan);
            
            return $this->response()->success('还款计划重新生成成功');
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }
}
