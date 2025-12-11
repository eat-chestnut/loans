<?php

namespace App\Services;

use Slowlyo\OwlAdmin\Services\AdminService;
use Slowlyo\OwlAdmin\Models\AdminUser;

/**
 * 员工管理服务类
 */
class EmployeeService extends AdminService
{
    protected string $modelName = AdminUser::class;

    public function list()
    {
        $query = $this->model::with('roles');

        // 搜索
        if ($name = request('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($phone = request('phone')) {
            $query->where('phone', 'like', "%{$phone}%");
        }
        if (request('status') !== '') {
            $query->where('status', request('status'));
        }

        $list = $query->paginate(request()->input('perPage', 20));

        return $this->response()->success($list);
    }

    public function store($data)
    {
        // 密码加密
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $model = $this->model::create($data);

        // 分配角色
        if (isset($data['roles']) && is_array($data['roles'])) {
            $model->roles()->sync($data['roles']);
        }

        return $this->response()->success($model);
    }

    public function update($id, $data)
    {
        $model = $this->model::findOrFail($id);

        // 密码加密
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $model->update($data);

        // 更新角色
        if (isset($data['roles']) && is_array($data['roles'])) {
            $model->roles()->sync($data['roles']);
        }

        return $this->response()->success($model);
    }
}
