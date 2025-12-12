<?php

namespace App\Services;

use App\Models\SmsLog;
use Slowlyo\OwlAdmin\Services\AdminService;

/**
 * 抵押物
 *
 * @method SmsLog getModel()
 * @method SmsLog|\Illuminate\Database\Query\Builder query()
 */
class SmsLogService extends AdminService
{
	protected string $modelName = SmsLog::class;

}
