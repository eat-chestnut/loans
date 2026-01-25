<?php

namespace App\Admin\Controllers;

use App\Models\RepaymentSchedule;
use App\Services\RepaymentScheduleService;

/**
 * 日历看板
 */
class CalendarController
{
    public function index()
    {
        $list = RepaymentSchedule::query()->with('loan.customer')->get()->groupBy(function ($repaymentSchedule) {
            return data_get($repaymentSchedule, "loan.customer.name", '未知').$repaymentSchedule->due_date;
        })->transform(function ($repaymentScheduleList) {
            return [
                "startTime" => $repaymentScheduleList->first()->due_date,
                "endTime" => $repaymentScheduleList->first()->due_date,
                "className" => $repaymentScheduleList->pluck('is_paid')->min() == 0 ? 'bg-info' : 'bg-success',
                "content" => data_get($repaymentScheduleList->first(), "loan.customer.name", '未知') .' | ￥'. number_format($repaymentScheduleList->pluck('amount')->sum(), 2),
            ];
        })->values();
        return amis()->Page()->body([
            amis()->Calendar()->schedules($list)->largeMode()
        ]);
    }
}
