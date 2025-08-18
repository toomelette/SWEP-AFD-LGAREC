<?php

namespace App\Console\Commands;

use App\Swep\Services\API\ApiDtrService;
use Illuminate\Console\Command;

class ApiUploadDTRToBacolod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtr:api-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This uploads some data to Bacolod office (DTR)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ApiDtrService $apiDtrService)
    {
        $apiDtrService->sendDtr();
    }
}
