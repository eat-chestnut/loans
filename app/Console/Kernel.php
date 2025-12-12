<?php

namespace App\Console;

use App\Console\Commands\SendRepaymentReminders;
use App\Console\Commands\UpdateOverdueStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        SendRepaymentReminders::class,
        UpdateOverdueStatus::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(SendRepaymentReminders::class)->dailyAt('09:00');
        $schedule->command(UpdateOverdueStatus::class)->dailyAt('00:30');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
