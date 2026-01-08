<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Models\AdminUser;
use Slowlyo\OwlAdmin\Services\AdminService as OwlAdminService;

abstract class AdminService extends OwlAdminService
{
    protected ?AdminUser $adminUser = null;

    public function __construct()
    {
        parent::__construct();

        $this->adminUser = Admin::user() ?: AdminUser::query()->first();
    }

    public function adminUser(): ?AdminUser
    {
        return $this->adminUser;
    }

    public function sortColumn(): string
    {

        if ($this->hasColumn($this->getModel()->getKeyName())) {
            return $this->getModel()->getKeyName();
        }

        return Arr::first($this->getTableColumns());
    }
}
