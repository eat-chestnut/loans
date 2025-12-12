<?php

namespace App\Admin\Controllers;

use App\Admin\Supports\Components;
use App\Models\Customer;
use App\Models\WecomContact;
use App\Services\CustomerService;
use App\Services\Wecom\WecomService;

/**
 * 客户表
 *
 * @property CustomerService $service
 */
class CustomerController extends AdminController
{
    protected string $serviceName = CustomerService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                $this->createButton('dialog', 'xl'),
                ...$this->baseHeaderToolBar()
            ])
            ->filterTogglable()
            ->columns([
                amis()->TableColumn('id', 'ID'),
                amis()->TableColumn('name', '客户'),
                amis()->TableColumn('id_card', '身份证号'),
                amis()->TableColumn('phone', '手机号码'),
                amis()->TableColumn('address', '联系地址')->ellipsis(),
                amis()->TableColumn('risk_level_label', '风险等级'),
                amis()->TableColumn('credit_score', '信用分')->backgroundScale([
                    'min' => 0,
                    'max' => 100,
                    "colors" => [
                        "#FF7127",
                        "#FFFFFF"
                    ]
                ]),
                amis()->TableColumn('wecom_contact.name', '企微绑定'),
                amis()->TableColumn('created_at', admin_trans('admin.created_at')),
                $this->rowActions([
                    $this->rowEditButton('dialog', 'lg'),
                    $this->rowShowButton('drawer', 'xl'),
                    amis()->DialogAction()
                        ->label('绑定企微')
                        ->dialog(
                            amis()->Dialog()
                                ->title('绑定企微客户')
                                ->body([
                                    amis()->Form()
                                        ->api('post:/customers/${id}/bind-wecom')
                                        ->body([
                                            amis()->SelectControl('wecom_contact_id', '选择企微客户')
                                                ->required()
                                                ->options(WecomService::make()->query()->whereNull('customer_id')->pluck('name', 'id')),
                                        ])
                                ])
                        )
                        ->level('link')
                        ->hiddenOn('${wecom_contact}'),
                    amis()->AjaxAction()
                        ->label('解绑企微')
                        ->api('post:/customers/${id}/unbind-wecom')
                        ->confirmText('确定要解绑该客户的企微账号吗？')
                        ->level('link')
                        ->className('text-danger')
                        ->hiddenOn('${!wecom_contact}'),
                ]),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->TextControl('name', '客户名')->clearable(),
                    amis()->TextControl('id_card', '身份证')->clearable(),
                    amis()->TextControl('phone', '手机')->clearable(),
                    amis()->SelectControl('risk_level', '风险等级')
                        ->clearable()
                        ->options($this->riskLevelOptions()),
                ])
            );

        return $this->baseList($crud);
    }

    public function form($isEdit = false)
    {
        return $this->baseForm()->body([
            amis()->TextControl('name', '客户姓名')->required()->clearable(),
            amis()->TextControl('id_card', '身份证号')->required()->clearable(),
            amis()->TextControl('phone', '手机号码')->clearable(),
            amis()->TextareaControl('address', '联系地址')->rows(2),
        ]);
    }

    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->FieldSetControl()->collapsable()->title('基本信息')->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('name', '姓名')->static(),
                    amis()->TextControl('id_card', '身份证')->static(),
                    amis()->TextControl('phone', '电话')->static(),
                    amis()->TextControl('wecom_contact.name', '企微绑定')->static(),
                ]),
                amis()->TextControl('address', '家庭住址')->static(),
                amis()->FieldSetControl()->collapsable()->title('共同借款人')->body([
                    amis()->GroupControl()->body([
                        amis()->TextControl('co_borrower_snapshot.name', '姓名')->static(),
                        amis()->TextControl('co_borrower_snapshot.id_card', '身份证')->static(),
                        amis()->TextControl('co_borrower_snapshot.phone', '电话')->static(),
                    ]),
                ]),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->collapsable()->title('数据统计')->body([
                amis()->Property()->title()->column(4)->items([
                    [
                        'label' => '抵押物数量',
                        'content' => amis()->TextControl('collateral_no')->static()
                    ],
                    [
                        'label' => '在贷/历史',
                        'content' => amis()->TextControl('loan_statistics')->static(),
                    ],
                    [
                        'label' => '当前贷款金额',
                        'content' => Components::make()->number('now_loans_total'),
                    ],
                    [
                        'label' => '历史贷款金额',
                        'content' => Components::make()->number('loans_total'),
                    ],
                    [
                        'label' => '历史还款金额',
                        'content' => Components::make()->number('loans_repayment_total'),
                    ],
                    [
                        'label' => '最近还款日期',
                        'content' => amis()->DateControl('next_loans_repayment_date')->static(),
                    ],
                    [
                        'label' => '贷款完结日期',
                        'content' => amis()->DateControl('last_loans_repayment_date')->static(),
                    ],
                    [
                        'label' => '逾期次数',
                        'content' => amis()->NumberControl('due_repayment_no')->static(),
                    ],
                    [
                        'label' => '信用分',
                        'content' => amis()->TextControl('credit_score')->static(),
                    ],
                    [
                        'label' => '风险等级',
                        'content' => amis()->TextControl('risk_level_label')->static(),
                    ]
                ])
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->collapsable()->title('抵押物列表')->body([
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
                    ->static(),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->collapsable()->title('贷款信息列表')->body([
                amis()->TableControl('loans', false)
                    ->columnsTogglable(false)
                    ->columns([
                        amis()->TableColumn('ticket_no', '票号'),
                        amis()->TableColumn('city', '归属地'),
                        Components::make()->tableNumberColumn('collateral_total_value', '抵押物价值'),
                        Components::make()->tableNumberColumn('amount', '借款金额'),
                        Components::make()->tableNumberColumn('total_interest', '总利息'),
                        amis()->TableColumn('discount_ratio', '折当率(%)'),
                        amis()->TableColumn('month_profit_ratio', '月综合利润(%)'),
                        amis()->TableColumn('term_months', '期数(个月)'),
                        amis()->TableColumn('rate_month', '月利率(%)'),
                        amis()->TableColumn('disbursed_at', '借款时间'),
                        amis()->TableColumn('state_label', '贷款状态'),
                        amis()->TableColumn('note', '备注')->ellipsis(),
                    ])
                    ->static(),
            ]),
            amis()->Divider(),
            amis()->FieldSetControl()->collapsable()->title('沟通记录')->body([
                amis()->TableControl('communications', false)
                    ->columnsTogglable(false)
                    ->columns([
                        amis()->TableColumn('loan.ticket_no', '对应当票号'),
                        amis()->TableColumn('admin_user.name', '沟通人'),
                        amis()->TableColumn('channel_label', '沟通渠道'),
                        amis()->TableColumn('content', '沟通结果'),
                        amis()->TableColumn('happened_at', '沟通时间'),
                    ])
                    ->perPage(20)
                    ->static(),
            ]),
        ]);
    }

    protected function riskLevelOptions(): array
    {
        return collect(Customer::riskLevelOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->toArray();
    }

    /**
     * 绑定企微客户
     */
    public function bindWecom($id)
    {
        $customer = Customer::findOrFail($id);
        $wecomContact = WecomContact::findOrFail(request('wecom_contact_id'));

        // 检查该企微客户是否已被其他客户绑定
        if ($wecomContact->customer_id && $wecomContact->customer_id != $customer->id) {
            return $this->response()->fail('该企微客户已被其他客户绑定');
        }

        // 绑定关系
        $wecomContact->customer_id = $customer->id;
        $wecomContact->save();

        return $this->response()->successMessage('绑定成功');
    }

    /**
     * 解绑企微客户
     */
    public function unbindWecom()
    {
        $customer = Customer::findOrFail(request('id'));

        // 解除绑定关系
        $wecomContact = $customer->wecomContact();
        if ($wecomContact) {
            $wecomContact->customer_id = null;
            $wecomContact->save();
        }

        return $this->response()->successMessage('解绑成功');
    }

    public function notice($id, $type)
    {
        return $this->response()->successMessage('通知成功');
    }
}
