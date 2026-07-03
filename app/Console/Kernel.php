<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('tasks:remind')->dailyAt('07:00');
        // Dọn file upload nội tuyến (editor/) mồ côi — giữ 30 ngày cho an toàn với soft-delete/restore.
        $schedule->command('media:prune-inline')->dailyAt('03:00');
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
