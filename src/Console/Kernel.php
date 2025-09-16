<?php

namespace Bu\Gws\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send audit reminders daily at 9 AM
        $schedule->command('audits:send-reminders')
            ->dailyAt('09:00')
            ->description('Send daily audit reminders to employees');

        // Send corrective action reminders daily at 10 AM
        $schedule->command('corrective-actions:send-reminders')
            ->dailyAt('10:00')
            ->description('Send daily corrective action reminders for overdue items');

        $schedule->job(\Bu\Gws\Jobs\ScheduledGoogleWorkspaceSync::class)
            ->daily()
            ->at('06:00'); // Run daily at 6 AM
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
