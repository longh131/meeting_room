<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SyncOrganization::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('reservations:close-expired')->everyMinute();
        $schedule->command('tenants:deactivate-expired')->daily();
        
        // 每天凌晨2点全量同步组织架构
        $schedule->command('organization:sync --full')->dailyAt('02:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}