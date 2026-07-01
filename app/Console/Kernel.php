<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Daily database backup at 2:00 AM PH time — mysqldump, gzip-compressed,
        // uploaded to DigitalOcean Spaces. Failure is logged and emailed.
        $schedule->command('backup:run --only-db')
                 ->dailyAt('02:00')
                 ->onFailure(function () {
                     logger()->error('Daily database backup failed.');
                 });

        // Prune old backups per the retention policy in config/backup.php.
        $schedule->command('backup:clean')
                 ->dailyAt('02:30');

        // Weekly health check — emails if newest backup is stale or storage bloated.
        $schedule->command('backup:monitor')
                 ->weeklyOn(1, '08:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
