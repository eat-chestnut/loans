<?php

namespace App\Admin\Controllers;

use App\Admin\Renderers\Amis;
use App\Admin\Supports\Components;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\RepaymentScheduleService;
use App\Services\RepaymentScheduleAdminService;
use Illuminate\Support\Facades\DB;
use Slowlyo\OwlAdmin\Controllers\AdminController;

/**
 * 还款计划
 */
class RepaymentScheduleController extends AdminController
{
    protected string $serviceName = RepaymentScheduleAdminService::class;

    public function list()
    {
        $statistics = $this->service->query()
            ->select(DB::raw('COUNT(1) as total'), DB::raw('SUM(if (is_paid=1, 1, 0)) as repayment'))
            ->first();
        $statistics->wait_repayment = $this->service->query()->whereDate('due_date', '<=', now()->addMonth()->toDateString())->where('is_paid', 0)->count();

        $tabs = amis()->Tabs()
            ->tabsMode('card')
            ->tabs([
                [
                    'title' => "本期待还款({$statistics->wait_repayment})",
                    'tab' => $this->curdList(['is_paid' => 0, 'max_due_date' => now()->addMonth()->toDateString()], [
                        amis()->AjaxAction()->level('link')->className('text-success')->label('标记还款')
                            ->api('post:/repayment-schedules/${id}/mark-paid')
                            ->confirmText('确认标记该期已还款？')->hiddenOn('${is_paid=0}'),
                    ]),
                    'reload' => true,
                ],
                [
                    'title' => "已还款({$statistics->repayment})",
                    'tab' => $this->curdList(['is_paid' => 1]),
                    'reload' => true,
                ],
                [
                    'title' => "全部({$statistics->total})",
                    'tab' => $this->curdList(),
                    'reload' => true,
                ]
            ]);

        return $this->baseList($tabs);
    }

    public function curdList($params=[], $actions=[])
    {
        return $this->baseCRUD()
            ->filterTogglable()
            ->bulkActions()
            ->headerToolbar([
                ...$this->baseHeaderToolBar()
            ])
            ->defaultParams($params)
            ->columns([
                amis()->TableColumn('loan.customer.name', '客户姓名'),
                amis()->TableColumn('period', '期数')->type('tpl')->tpl('${period} / ${loan.term_months}'),
                amis()->TableColumn('due_date', '还款期限')->type('date'),
                Components::make()->tableNumberColumn('amount', '应还金额'),
                Components::make()->tableNumberColumn('principal', '本金'),
                Components::make()->tableNumberColumn('interest', '利息'),
                Components::make()->tableNumberColumn('remaining_principal', '剩余本金'),
                amis()->TableColumn('state', '状态')->type('status')->source([
                    0 => [
                        'label' => '待还款',
                        "icon" => "schedule"
                    ],
                    1 => [
                        'label' => '已还款',
                        "icon" => "success",
                        "color" => "#039403"
                    ],
                    -1 => [
                        'label' => '已逾期',
                        "icon" => "fail",
                        "color" => "#fail"
                    ]
                ]),
                amis()->TableColumn('paid_at', '还款时间'), // todo 逾期未还标 红色， 逾期已还标 粉色
                $this->rowActions($actions)->visible(filled($actions)),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->TextControl('loan.customer.name', '客户姓名')->clearable(),
                    amis()->HiddenControl('is_paid')
                ])
            );
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
                return $this->response()->successMessage('标记成功');
            } else {
                return $this->response()->fail('标记失败，请检查状态');
            }
        } catch (\Exception $e) {
            return $this->response()->fail($e->getMessage());
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
            return $this->response()->fail($e->getMessage());
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
                return $this->response()->fail('贷款不存在');
            }

            $service = new RepaymentScheduleService();
            $service->saveSchedule($loan);

            return $this->response()->success('还款计划重新生成成功');
        } catch (\Exception $e) {
            return $this->response()->fail($e->getMessage());
        }
    }
}
