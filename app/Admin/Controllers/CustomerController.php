<?php

namespace App\Admin\Controllers;

use App\Models\Customer;
use App\Services\CustomerService;

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
                amis()->TableColumn('name', '客户'),
                amis()->TableColumn('id_card', '身份证号')->copyable(),
                amis()->TableColumn('phone', '手机号码')->copyable(),
                amis()->TableColumn('address', '联系地址')->ellipsis(),
                amis()->TableColumn('risk_level_label', '风险等级'),
                amis()->TableColumn('credit_score', '信用分'),
                amis()->TableColumn('created_at', admin_trans('admin.created_at')),
                $this->rowActions([
                    $this->rowEditButton('dialog', 'lg'),
                    $this->rowShowButton(),
                    $this->rowDeleteButton(),
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
            amis()->TextControl('name', '客户姓名')->required()->clearable(true),
            amis()->TextControl('id_card', '身份证号')->required()->clearable(true),
            amis()->TextControl('phone', '手机号码')->clearable(true),
            amis()->TextareaControl('address', '联系地址')->rows(2),
            amis()->SelectControl('risk_level', '风险等级')
                ->options($this->riskLevelOptions())
                ->clearable()
                ->value(Customer::RISK_LOW),
            amis()->NumberControl('credit_score', '信用分')
                ->min(0)
                ->max(100)
                ->step(1),
        ]);
    }

    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->TextControl('name', '客户')->static(),
            amis()->TextControl('id_card', '身份证号')->static(),
            amis()->TextControl('phone', '手机号码')->static(),
            amis()->TextControl('address', '联系地址')->static(),
            amis()->TextControl('risk_level_label', '风险等级')->static(),
            amis()->TextControl('credit_score', '信用分')->static(),
            amis()->TextControl('created_at', admin_trans('admin.created_at'))->static(),
            amis()->TextControl('updated_at', admin_trans('admin.updated_at'))->static(),
        ]);
    }

    protected function riskLevelOptions(): array
    {
        return collect(Customer::riskLevelOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->toArray();
    }
}
