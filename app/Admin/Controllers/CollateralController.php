<?php

namespace App\Admin\Controllers;

use App\Models\Collateral;
use App\Services\CustomerService;
use App\Services\CollateralService;

/**
 * 抵押物
 *
 * @property CollateralService $service
 */
class CollateralController extends AdminController
{
    protected string $serviceName = CollateralService::class;

    public function getEditPath()
    {
        return parent::getEditPath() .'&customer_id='.request('customer_id');
    }

    public function getListGetDataPath()
    {
        return parent::getListGetDataPath() .'&customer_id='.request('customer_id');
    }

    public function getCreatePath()
    {
        return parent::getCreatePath() .'&customer_id='.request('customer_id');
    }

    public function list()
    {
        $crud = $this->baseCRUD()
            ->alwaysShowPagination(false)
            ->filterTogglable()
            ->headerToolbar([
                $this->createButton('dialog', 'lg'),
                $this->bulkDeleteButton(),
                $this->filterToggleButton(),
                $this->refreshButton(),
            ])
            ->footerToolbar([])
            ->columns([
                amis()->TableColumn('customer.name', '所属客户'),
                amis()->TableColumn('name', '抵押物名称')->copyable(),
                amis()->TableColumn('type_label', '类型'),
                amis()->TableColumn('city', '所属地'),
                amis()->TableColumn('discount_rate', '折扣率(%)'),
                amis()->TableColumn('pledge_value', '评估价值'),
                amis()->TableColumn('certificate_no', '权证编号'),
                amis()->TableColumn('area', '面积/规格'),
                amis()->TableColumn('note', '备注说明')->ellipsis(),
                $this->rowActions([
                    $this->rowEditButton('dialog', 'lg'),
                    $this->rowDeleteButton(),
                ]),
            ])
            ->filter(
                amis()->Form()->wrapWithPanel(false)->body([
                    amis()->SelectControl('customer_id', '所属客户')
                        ->clearable()
                        ->options($this->customerOptions()),
                    amis()->SelectControl('type', '类型')
                        ->clearable()
                        ->options($this->collateralTypeOptions()),
                    amis()->TextControl('name', '名称')->clearable(),
                    amis()->TextControl('city', '城市')->clearable(),
                ])
            );

        return $this->baseList($crud);
    }

    public function form($isEdit = false)
    {
        return $this->baseForm()->body([
            amis()->SelectControl('customer_id', '所属客户')
                ->options($this->customerOptions())
                ->value((int)request()->get('customer_id'))
                ->required(),
            amis()->TextControl('name', '抵押物名称')->required(),
            amis()->SelectControl('type', '抵押物类型')
                ->options($this->collateralTypeOptions())
                ->required(),
            amis()->TextControl('city', '所属地')->placeholder('例: 福州市台江区'),
            amis()->NumberControl('discount_rate', '折扣率(%)')->precision(2),
            amis()->NumberControl('pledge_value', '评估价值')->step(1000),
            amis()->TextControl('certificate_no', '权证编号'),
            amis()->NumberControl('area', '面积/规格')->precision(2),
            amis()->TextareaControl('note', '备注说明')->rows(3),
        ]);
    }

    public function detail()
    {
        return $this->baseDetail()->body([
            amis()->TextControl('customer.name', '所属客户')->static(),
            amis()->TextControl('name', '抵押物名称')->static(),
            amis()->TextControl('type_label', '类型')->static(),
            amis()->TextControl('city', '所属地')->static(),
            amis()->TextControl('discount_rate', '折扣率(%)')->static(),
            amis()->TextControl('pledge_value', '评估价值')->static(),
            amis()->TextControl('certificate_no', '权证编号')->static(),
            amis()->TextControl('area', '面积/规格')->static(),
            amis()->TextControl('note', '备注说明')->static(),
            amis()->TextControl('created_at', admin_trans('admin.created_at'))->static(),
            amis()->TextControl('updated_at', admin_trans('admin.updated_at'))->static(),
        ]);
    }

    public function options()
    {
        return $this->response()->success($this->service::options());
    }

    protected function customerOptions(): array
    {
        return collect(CustomerService::make()->options())
            ->map(fn($label, $value) => ['label' => $label, 'value' => (int)$value])
            ->values()
            ->toArray();
    }

    protected function collateralTypeOptions(): array
    {
        return collect(Collateral::typeOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => (int)$value])
            ->values()
            ->toArray();
    }
}
