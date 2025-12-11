<?php

namespace App\Admin\Controllers;

use Slowlyo\OwlAdmin\Controllers\AdminController as BaseAdminController;

/**
 */
class AdminController extends BaseAdminController
{

    public function list()
    {
        return $this->baseList([]);
    }

    public function form($isEdit = false)
    {
        return $this->baseForm()->body([]);
    }

    public function detail()
    {
        return $this->baseDetail()->body([]);
    }

    public function options()
    {
        return $this->response()->success($this->service->options());
    }
}
