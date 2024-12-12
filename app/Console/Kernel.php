<?php

namespace App\Console;

use App\Console\Commands\DemoCron;
use App\Console\Commands\UploadDtrs;
use App\Models\CronLogs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\DemoCron::class,
        Commands\ExtractBiometricData::class,
        Commands\ReconstructDTR::class,
        Commands\ComputeLateUndertime::class,
        Commands\SanitizeBiometricDevice::class,
        Commands\UploadDtrs::class,
        Commands\ResetBiometricDeviceTime::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('dtr:extract')->everyTenMinutes();
        $schedule->command('dtr:reconstruct')->everyTenMinutes();
        $schedule->command('dtr:compute_late_undertime')->everyTenMinutes();
        $schedule->command('dtr:reset_device_time')->dailyAt('06:20');
        $schedule->command('dtr:sanitizeBiometricDevices')->weeklyOn(0);
//        $schedule->command('dtr:upload')->everyTenMinutes();
//        $schedule->command('demo:cron')->everyMinute();
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
