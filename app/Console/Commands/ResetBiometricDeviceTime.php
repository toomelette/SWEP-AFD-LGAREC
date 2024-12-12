<?php

namespace App\Console\Commands;

use App\Swep\Services\DTRService;
use Illuminate\Console\Command;

class ResetBiometricDeviceTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtr:reset_device_time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets time on all devices';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected $DTRService;
    public function handle(DTRService $DTRService)
    {
        $DTRService->reset();
        return Command::SUCCESS;
    }
}
