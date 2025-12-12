<?php

namespace App\Admin\Controllers;

use App\Admin\Supports\Components;
use App\Enums\CollateralCityType;
use App\Models\Loan;
use App\Services\CollateralService;
use App\Services\CustomerService;
use App\Services\LoanService;

/**
 * 放款表
 *
 * @property LoanService $service
 */
class LoanController extends AdminController
{
    protected string $serviceName = LoanService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->filterTogglable()
            ->headerToolbar([
                $this->createButton('dialog', 'lg'),
                ...$this->baseHeaderToolBar()
            ])
            ->columns([
                amis()->TableColumn('ticket_no', '票号')->copyable(),
                amis()->TableColumn('customer.name', '客户姓名'),
                amis()->TableColumn('city', '归属地'),
                amis()->TableColumn('collaterals', '抵押物')->type('each')->items(
                    amis()->Tag()->label('${name}')->className('my-1')
                ),
                Components::make()->tableNumberColumn('collateral_total_value', '抵押物价值'),
                Components::make()->tableNumberColumn('amount', '借款金额'),
                Components::make()->tableNumberColumn('total_interest', '总利息'),
                amis()->TableColumn('discount_ratio', '折当率(%)'),
                amis()->TableColumn('month_profit_ratio', '月综合利润(%)'),
                amis()->TableColumn('term_months', '期数(个月)'),
                Components::make()->tableNumberColumn('paid_amount', '已还金额'),
                Components::make()->tableNumberColumn('profit_amount', '盈利金额'),
                amis()->TableColumn('overdue_count', '逾期次数'),
                amis()->TableColumn('disbursed_at', '借款时间')->type('date'),
                amis()->TableColumn('closed_at', '结清时间')->type('date'),
                amis()->TableColumn('state_label', '贷款状态'),
                $this->rowActions([
                    $this->rowEditButton('dialog', 'xl'),
                    $this->rowShowButton(),
                    $this->rowDeleteButton(),
                ]),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->TextControl('loan_number', '当票号')->clearable(),
                    amis()->TextControl('ticket_no', '票号')->clearable(),
                    amis()->SelectControl('customer_id', '关联客户')
                        ->clearable()
                        ->options($this->customerOptions()),
                    amis()->SelectControl('collateral_id', '关联抵押物')
                        ->clearable()
                        ->options($this->collateralOptions()),
                    amis()->SelectControl('state', '贷款状态')
                        ->clearable()
                        ->options($this->loanStateOptions()),
                    amis()->DateControl('disbursed_at', '借款日期')->clearable(),
                    amis()->TextControl('note', '备注关键字')->clearable(),
                ])
            );

        return $this->baseList($crud);
    }

    public function form($isEdit = false)
    {
        return $this->baseForm()->body([
            amis()->FieldSetControl()->collapsable()->title('客户信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('customer.name', '姓名')->static(),
                    amis()->TextControl('customer.id_card', '身份证号')->static(),
                    amis()->TextControl('customer.phone', '电话')->static()
                ]),
                amis()->TextControl('customer.address', '家庭住址')->static(),
                amis()->FieldSetControl()->title('共同借款人')->collapsed()->collapsable()->body([
                    amis()->GroupControl()->body([
                        amis()->TextControl('co_borrower_snapshot.name', '姓名')->static(),
                        amis()->TextControl('co_borrower_snapshot.id_card', '身份证号')->static(),
                        amis()->TextControl('co_borrower_snapshot.phone', '电话')->static()
                    ]),
                ])
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->title('抵押物')->body([
                amis()->TableControl('collaterals', false)
                    ->columnsTogglable(false)
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
            amis()->Divider(),
            amis()->FieldSetControl()->title('贷款信息')->collapsable()->body([
                amis()->GroupControl()->body([
                    amis()->NumberControl('collateral_total_value', '抵押物价值')->kilobitSeparator()->prefix('￥'),
                    amis()->NumberControl('amount', '借款金额')->kilobitSeparator()->prefix('￥'),
                    amis()->NumberControl('total_interest_amount', '总利息')->kilobitSeparator()->prefix('￥')
                ]),
                amis()->GroupControl()->body([
                    amis()->DateControl('disbursed_at', '借款日期')->value(null),
                    amis()->NumberControl('term_months', '借款期数')->precision(0),
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
            amis()->FieldSetControl()->title('沟通记录')->body([
                amis()->TableControl('communications', false)
                    ->columns([
                        amis()->SelectControl('channel', '沟通方式')
                            ->options(\App\Models\Communication::channelOptions())
                            ->width(100),
                        amis()->DatetimeControl('happened_at', '沟通时间')
                            ->format('YYYY-MM-DD HH:mm:ss')
                            ->width(150),
                        amis()->TextareaControl('content', '沟通内容')->width(300),
                    ])
                    ->addable()
                    ->removable()
                    ->columnsTogglable(false)
                    ->needConfirm(false)
                    ->defaultValue([])
            ])
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
}
