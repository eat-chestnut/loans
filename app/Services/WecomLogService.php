<?php

namespace App\Services;

use App\Models\WecomLog;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 抵押物
 *
 * @method WecomLog getModel()
 * @method WecomLog|\Illuminate\Database\Query\Builder query()
 */
class WecomLogService extends AdminService
{
	protected string $modelName = WecomLog::class;

}
