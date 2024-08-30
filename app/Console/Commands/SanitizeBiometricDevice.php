<?php

namespace App\Console\Commands;

use App\Models\BiometricDevices;
use App\Swep\Services\DTRService;
use Illuminate\Console\Command;

class SanitizeBiometricDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtr:sanitizeBiometricDevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected $DTRService;
    public function handle(DTRService $DTRService)
    {
        $bds = BiometricDevices::query()
            ->where('status' ,'=',1)
            ->where('last_state','=',1)
            ->get();
        if(!empty($bds)){
            foreach ($bds as $bd){
                $ip = $bd->ip_address;
                $DTRService->clearAttendance($ip);
            }
        }
    }
}
