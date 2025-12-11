<?php

namespace App\Admin\Controllers;

use Illuminate\Http\Request;
use App\Services\EmployeeService;
use Slowlyo\OwlAdmin\Controllers\AdminController;

/**
 * 员工管理控制器
 */
class EmployeeController extends AdminController
{
    protected string $serviceName = EmployeeService::class;

    public function list()
    {
        $crud = $this->baseCRUD()
            ->headerToolbar([
                $this->createButton(),
                ...$this->baseHeaderToolBar()
            ])
            ->columns([
                amis()->TableColumn('id', 'ID')->sortable(),
                amis()->TableColumn('name', '姓名'),
                amis()->TableColumn('phone', '手机号'),
                amis()->TableColumn('email', '邮箱'),
                amis()->TableColumn('roles', '角色')
                    ->set('valueTpl', '${roles|map:item => item.name|join:\',\'}'),
                amis()->TableColumn('status', '状态')
                    ->set('valueTpl', '${status ? "启用" : "禁用"}')
                    ->set('type', 'mapping')
                    ->set('map', [
                        ['value' => true, 'label' => '启用', 'color' => 'success'],
                        ['value' => false, 'label' => '禁用', 'color' => 'danger'],
                    ]),
                amis()->TableColumn('created_at', '创建时间')->type('datetime'),
                amis()->OperationColumn('操作')->buttons([
                    amis()->ButtonAction()->label('编辑')->actionType('drawer')->drawer(
                        amis()->Drawer()->title('编辑员工')->body(
                            $this->form(true)
                        )
                    ),
                    amis()->ButtonAction()->label('删除')->actionType('ajax')->confirmText('确定删除？')->api('delete:/admin-api/employees/${id}'),
                ]),
            ])
            ->api('/admin-api/employees')
            ->filter([
                'items' => [
                    amis()->TextControl('name', '姓名')->placeholder('搜索姓名'),
                    amis()->TextControl('phone', '手机号')->placeholder('搜索手机号'),
                    amis()->SelectControl('status', '状态')->options([
                        ['label' => '全部', 'value' => ''],
                        ['label' => '启用', 'value' => true],
                        ['label' => '禁用', 'value' => false],
                    ]),
                ]
            ]);

        return $this->baseList($crud);
    }

    public function form($isEdit = false)
    {
        return amis()->Form()
            ->api($isEdit ? 'put:/admin-api/employees/${id}' : 'post:/admin-api/employees')
            ->body([
                amis()->GroupControl()->body([
                    amis()->TextControl('name', '姓名')->required(),
                    amis()->TextControl('phone', '手机号')->required()->validations([
                        'isPhoneNumber' => true
                    ]),
                ]),
                amis()->GroupControl()->body([
                    amis()->TextControl('email', '邮箱')->type('email')->required(),
                    amis()->TextControl('password', '密码')->type('password')->required(!$isEdit),
                ]),
                amis()->GroupControl()->body([
                    amis()->SelectControl('roles', '角色')->multiple()->required()->source('/admin-api/roles'),
                    amis()->SwitchControl('status', '状态')->value(true),
                ]),
                amis()->TextControl('remark', '备注')->type('textarea'),
            ]);
    }

    public function store(Request $request)
    {
        return $this->baseStore();
    }

    public function update($id)
    {
        $data = request()->all();
        
        // 如果编辑时没有提供新密码，则从数组中移除
        if (empty($data['password'])) {
            unset($data['password']);
        }
        
        return $this->baseUpdate($id, $data);
    }
}
