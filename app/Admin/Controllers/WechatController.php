<?php

namespace App\Admin\Controllers;

use App\Models\Customer;
use App\Models\WecomContact;
use App\Services\CustomerService;
use App\Services\Wecom\WecomService;

/**
 * 客户表
 *
 * @property CustomerService $service
 */
class WechatController extends AdminController
{
    protected string $serviceName = WecomService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                ...$this->baseHeaderToolBar()
            ])
            ->bulkActions()
            ->filterTogglable()
            ->columns([
                amis()->TableColumn('name', '企微名称'),
                amis()->TableColumn('mobile', '手机号码'),
                amis()->TableColumn('customer.name', '绑定客户')
            ]);

        return $this->baseList($crud);
    }
}
