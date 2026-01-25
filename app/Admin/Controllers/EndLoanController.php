<?php

namespace App\Admin\Controllers;

use App\Enums\CollateralCityType;
use App\Enums\CollateralType;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\CollateralService;
use App\Services\CustomerService;
use App\Services\LoanService;
use App\Services\RepaymentScheduleService;
use Illuminate\Http\Request;

/**
 * 放款表
 *
 * @property LoanService $service
 */
class EndLoanController extends AdminController
{
    protected string $serviceName = LoanService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->filterTogglable()
            ->bulkActions([])
            ->headerToolbar([
                ...$this->baseHeaderToolBar()
            ])
            ->defaultParams([
                'is_end' => 1
            ])
            ->columns([
                amis()->TableColumn('customer.name', '客户姓名')->fixed('left'),
                amis()->TableColumn('loan_type_text', '类型'),
                amis()->TableColumn('info1', '贷款信息')->type('tpl')->tpl('
                    <span>借款金额: ${amount}</span><br/>
                    <span>总利息: ${total_interest}</span><br/>
                    <span>期数(月): ${term_months}</span>
                '),
                amis()->TableColumn('info', '综合信息')->type('tpl')->tpl('
                    <span>抵押物价值: ${collateral_total_value}</span><br/>
                    <span>折当率: ${discount_ratio}%</span><br/>
                    <span>月综合利润: ${month_profit_ratio}%</span>
                '),
                amis()->TableColumn('profit_amount', '盈利金额'),
                amis()->TableColumn('info3', '综合信息')->type('tpl')->tpl('
                    <span>借款时间: ${disbursed_at}</span><br/>
                    <span>预计结清时间: ${expected_date}</span><br/>
                    <span>实际结清时间: ${closed_at}</span>
                '),
                amis()->TableColumn('overdue_count', '逾期次数'),
                amis()->TableColumn('state_label', '贷款状态'),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->SelectControl('customer_id', '关联客户')
                        ->clearable()
                        ->searchable()
                        ->options($this->customerOptions()),
                    amis()->SelectControl('collateral_id', '关联抵押物')
                        ->clearable()
                        ->searchable()
                        ->options($this->collateralOptions()),
                    amis()->SelectControl('state', '贷款状态')
                        ->clearable()
                        ->searchable()
                        ->options($this->loanStateOptions()),
                    amis()->DateControl('disbursed_at', '借款日期')->clearable(),
                    amis()->TextControl('note', '备注关键字')->clearable(),
                ])
            );

        return $this->baseList($crud);
    }

    public function endButton()
    {
        $noPayFreightForm = amis()->Form()->title()->body([
            amis()->DateControl('paid_at', '还款日期')->required(),
            amis()->NumberControl('remaining_principal', '还款本金')->required(),
            amis()->NumberControl('remaining_interest', '还款利息'),
        ])->api('put:/loans/${id}/end')->onEvent([
            'submitSucc' => [
                'actions' => [
                    [
                        'componentId' => '11',
                        'actionType' => 'reload',
                    ],
                ],
            ],
        ]);
        return amis()->DialogAction()->dialog(
            amis()->Dialog()->title(' [${customer.name}] 提前结清')->body($noPayFreightForm)->size('md')
        )->label('提前结清')->level('link')->className('ml-2');
    }

    public function form($isEdit = false)
    {
        return $this->baseForm()->body([
            amis()->FieldSetControl()->collapsable()->title('客户信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('customer.name', '姓名'),
                    amis()->TextControl('customer.id_card', '身份证号'),
                    amis()->TextControl('customer.phone', '电话')
                ]),
                amis()->GroupControl()->body([
                    amis()->TextControl('customer.type', '来源渠道'),
                    amis()->TextControl('customer.address', '家庭住址'),
                ]),
                amis()->FieldSetControl()->title('共同借款人')->body([
                    amis()->TableControl('co_borrower_snapshot', false)
                        ->columnsTogglable(false)
                        ->columns([
                            amis()->TextControl('name', '姓名'),
                            amis()->TextControl('id_card', '身份证号'),
                            amis()->TextControl('phone', '电话')
                        ])
                        ->needConfirm(false)
                        ->addable()
                        ->removable(),
                ]),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->title('抵押物')->body([
                amis()->TableControl('collaterals', false)
                    ->columnsTogglable(false)
                    ->columns([
                        amis()->TextControl('name', '抵押物'),
                        amis()->SelectControl('type', '类型')->options(CollateralType::asSelectArray()),
                        amis()->TextControl('area', '面积'),
                        amis()->TextControl('certificate_no', '产权证'),
                    ])
                    ->needConfirm(false)
                    ->addable()
                    ->removable(),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->title('贷款信息')->collapsable()->body([
                amis()->ListControl('loan_type', '类型')->options([
                    1 => '等额本息',
                    2 => '先息后本'
                ])->value(1),
                amis()->GroupControl()->body([
                    amis()->NumberControl('collateral_total_value', '抵押物价值')->kilobitSeparator()->prefix('￥'),
                    amis()->NumberControl('amount', '借款金额')->kilobitSeparator()->prefix('￥'),
                    amis()->NumberControl('total_interest_amount', '总利息')->kilobitSeparator()->prefix('￥')
                ]),
                amis()->GroupControl()->body([
                    amis()->DateControl('disbursed_at', '借款日期')->format('YYYY-MM-DD')->value(null),
                    amis()->NumberControl('term_months', '借款期数')->precision(0)->hiddenOn('${type==3}'),
                    amis()->TextControl('ticket_no', '当票号')
                ]),
                amis()->GroupControl()->body([
                    amis()->SelectControl('city', '归属地')->options(CollateralCityType::asSelectArray())->selectFirst()->clearable(),
                    amis()->NumberControl('month_profit_ratio', '月综合利润(%)')->precision(2),
                    amis()->NumberControl('discount_ratio', '折当率(%)')->precision(2)
                ]),
                amis()->TextareaControl('note', '备注'),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->title('还款计划')->body([
                amis()->TableControl('repayment_schedules', false)
                    ->columnsTogglable(false)
                    ->columns([
                        amis()->DateControl('due_date', '还款期限'),
                        amis()->NumberControl('principal', '本金')->precision(2)->value(0),
                        amis()->NumberControl('interest', '利息')->precision(2)->value(0),
                    ])
                    ->needConfirm(false)
                    ->addable()
                    ->removable(),
            ])->hidden($isEdit),
        ]);
    }

    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->FieldSetControl()->title('基础信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('loan_number', '当票号')->static(),
                    amis()->TextControl('ticket_no', '票号')->static(),
                    amis()->TextControl('city', '归属地')->static(),
                ]),
            ]),
            amis()->FieldSetControl()->title('客户信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('customer.name', '姓名')->static(),
                    amis()->TextControl('customer.id_card', '身份证')->static(),
                    amis()->TextControl('customer.phone', '电话')->static(),
                ]),
                amis()->TextControl('customer.address', '家庭住址')->static(),
                amis()->FieldSetControl()->title('共同借款人')->body([
                    amis()->GroupControl()->body([
                        amis()->TextControl('co_borrower_snapshot.name', '姓名')->static(),
                        amis()->TextControl('co_borrower_snapshot.id_card', '身份证')->static(),
                        amis()->TextControl('co_borrower_snapshot.phone', '电话')->static(),
                    ]),
                ]),
            ]),
            amis()->FieldSetControl()->title('抵押物')->body([
                amis()->TableControl('collaterals', '抵押物列表')
                    ->columns([
                        amis()->TableColumn('name', '抵押物'),
                        amis()->TableColumn('type_label', '类型'),
                        amis()->TableColumn('area', '面积'),
                        amis()->TableColumn('certificate_no', '产权证'),
                        amis()->TableColumn('valuation', '估价'),
                        amis()->TableColumn('value', '房屋价值'),
                        amis()->TableColumn('note', '备注'),
                    ])
                    ->static(true),
            ]),
            amis()->FieldSetControl()->title('贷款信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('collateral_total_value', '抵押物价值')->static(),
                    amis()->TextControl('amount', '借款金额')->static(),
                    amis()->TextControl('total_interest', '总利息')->static(),
                ]),
                amis()->GroupControl()->body([
                    amis()->TextControl('disbursed_at', '借款时间')->static(),
                    amis()->TextControl('term_months', '期数(个月)')->static(),
                    amis()->TextControl('rate_month', '月利率(%)')->static(),
                ]),
                amis()->TextControl('note', '备注')->static(),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->title('还款计划')->collapsable(false)->body([
                amis()->CRUDTable()
                    ->api('get:/admin-api/repayment-schedules?loan_id=${id}')
                    ->columns([
                        amis()->TableColumn('period', '期数')->width(80),
                        amis()->TableColumn('due_date', '还款日期')->width(120),
                        amis()->TableColumn('amount', '应还金额')->width(120)
                            ->set('valueTpl', '￥${amount|number:2}'),
                        amis()->TableColumn('principal', '本金')->width(120)
                            ->set('valueTpl', '￥${principal|number:2}'),
                        amis()->TableColumn('interest', '利息')->width(120)
                            ->set('valueTpl', '￥${interest|number:2}'),
                        amis()->TableColumn('remaining_principal', '剩余本金')->width(120)
                            ->set('valueTpl', '￥${remaining_principal|number:2}'),
                        amis()->TableColumn('state', '状态')->width(100)
                            ->set('type', 'mapping')
                            ->set('map', [
                                ['label' => '已还款', 'value' => '已还款'],
                                ['label' => '待还款', 'value' => '待还款'],
                            ]),
                        amis()->TableColumn('paid_at', '还款时间')->width(150),
                        amis()->OperationColumn('label', '操作')->width(100)
                            ->buttons([
                                amis()->Button()
                                    ->label('标记还款')
                                    ->actionType('ajax')
                                    ->api('post:/admin-api/repayment-schedules/${id}/mark-paid')
                                    ->hidden('${is_paid}')
                                    ->confirmText('确认标记该期已还款？'),
                            ]),
                    ])
                    ->features(['filter' => false])
                    ->headerToolbar([
                        amis()->Button()
                            ->label('重新生成计划')
                            ->actionType('ajax')
                            ->api('post:/admin-api/repayment-schedules/regenerate/${id}')
                            ->confirmText('确认重新生成还款计划？这将删除现有计划并生成新的计划。'),
                    ]),
            ]),
        ]);
    }

    protected function customerOptions(): array
    {
        return collect(CustomerService::make()->options())
            ->map(fn($label, $value) => ['label' => $label, 'value' => (int)$value])
            ->values()
            ->toArray();
    }

    protected function collateralOptions(): array
    {
        return collect(CollateralService::options())
            ->map(fn($label, $value) => ['label' => $label, 'value' => (int)$value])
            ->values()
            ->toArray();
    }

    protected function loanStateOptions(): array
    {
        return collect(Loan::stateOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => (int)$value])
            ->values()
            ->toArray();
    }

    public function end($id, Request $request)
    {
        $loan = $this->service->query()->find($id);
        $repaymentSchedule = RepaymentSchedule::query()->where('loan_id', $id)->where('is_paid', 0)->orderBy('due_date')->first();
        if (!$repaymentSchedule) {
            return admin_abort('没找到待还款计划');
        }

        $repaymentSchedule->principal = $request->remaining_principal;
        $repaymentSchedule->interest = $request->remaining_interest;
        $repaymentSchedule->remaining_principal = 0;
        $repaymentSchedule->amount = $repaymentSchedule->principal + $repaymentSchedule->interest;
        $repaymentSchedule->is_paid = 1;
        $repaymentSchedule->paid_at = date('Y-m-d 00:00:00', $request->paid_at);
        $repaymentSchedule->save();
        RepaymentSchedule::query()->where('loan_id', $id)->where('is_paid', 0)->delete();

        $loan->paid_amount += $repaymentSchedule->principal;
        $loan->profit_amount += $repaymentSchedule->interest;
        $loan->state = Loan::STATE_CLOSED;
        $loan->closed_at = $repaymentSchedule->paid_at;
        $loan->save();
        return $this->response()->successMessage('提前还款成功');
    }
}
