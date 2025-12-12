<?php

namespace App\Admin\Controllers;

use App\Services\WecomLogService;

/**
 * 客户表
 *
 * @property WecomLogService $service
 */
class WecomLogController extends AdminController
{
    protected string $serviceName = WecomLogService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                ...$this->baseHeaderToolBar(),
            ])
            ->bulkActions()
            ->filterTogglable()
            ->columns([
                amis()->TableColumn('id', 'ID'),
                amis()->TableColumn('customer.name', '绑定客户'),
                amis()->TableColumn('loan.ticket_no', '当票号'),
                amis()->TableColumn('sent_at', '发送时间'),
                amis()->TableColumn('contact_name', '联系人'),
                amis()->TableColumn('wecom.name', '企微账号'),
                amis()->TableColumn('content.text.content', '短信内容'),
            ]);

        return $this->baseList($crud);
    }
}
