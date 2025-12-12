<?php

namespace App\Admin\Controllers;

use App\Admin\Supports\Components;
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
            ->bulkActions()
            ->headerToolbar([
                ...$this->baseHeaderToolBar()
            ])
            ->columns([
                amis()->TableColumn('loan.ticket_no', '当票号')->copyable(),
                amis()->TableColumn('loan.customer.name', '客户姓名'),
                amis()->TableColumn('loan.customer.phone', '联系电话'),
                amis()->TableColumn('period', '期数'),
                amis()->TableColumn('due_date', '应还日期')->type('date'),
                amis()->TableColumn('over_due_day', '逾期天数'),
                Components::make()->tableNumberColumn('amount', '应还金额'),
                Components::make()->tableNumberColumn('principal', '本金'),
                Components::make()->tableNumberColumn('interest', '利息'),
                $this->rowActions([
                    Components::make()->sendSmsButton('loan.customer.phone'),
                    Components::make()->sendWechatButton('loan.customer.phone'),
                    Components::make()->callPhoneButton('loan.customer.phone'),
                    Components::make()->paidRepaymentButton('id')
                ])
            ]);

        return $this->baseList($crud);
    }
}
