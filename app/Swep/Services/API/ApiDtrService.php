<?php

namespace App\Swep\Services\API;

use App\Models\DailyTimeRecord;
use App\Models\DTR;

class ApiDtrService extends ApiService
{
    public function sendDtr($loop = 0)
    {
        $headers = [
            'Accept'        => 'application/json',
            'Content-type' => 'application/json',
        ];
        try {
            //ADD TOKEN TO THE HEADER:
            $headers['Authorization'] = 'Bearer ' . $this->getSavedToken();
            $client = new \GuzzleHttp\Client(['base_uri' => $this->baseUri]);

            $dailyTimeRecords = DailyTimeRecord::query()
                ->where('api_status','=',null)
                ->where('slug','!=', null)
                ->limit(400)
                ->get();
            $dailyTimeRecordsArray = [];

            if(!empty($dailyTimeRecords)){
                foreach ($dailyTimeRecords as $d){
                    $dailyTimeRecordsArray[] = [
                        'slug' => $d->slug,
                        'employee_no' => $d->employee_no,
                        'biometric_user_id' => $d->biometric_user_id,
                        'biometric_uid' => $d->biometric_uid,
                        'date' => $d->date,
                        'am_in' => $d->am_in,
                        'am_out' => $d->am_out,
                        'pm_in' => $d->pm_in,
                        'pm_out' => $d->pm_out,
                        'ot_in' => $d->ot_in,
                        'ot_out' => $d->ot_out,
                        'late' => $d->late,
                        'undertime' => $d->undertime,
                        'calculated' => $d->calculated,
                        'remarks' => $d->remarks,
                        'remarks_updated_at' => $d->remarks_updated_at,
                        'remarks_user_updated' => $d->remarks_user_updated,
                    ];
                }
            }

            $dtr = DTR::query()
                ->where('uploaded','=',null)
                ->limit(800)
                ->get();
            $dtrsArray = [];
            if(!empty($dtr)){
                foreach ($dtr as $d) {
                    $dtrsArray[] = [
                        'uid' => $d->uid,
                        'user' => $d->user,
                        'state' => $d->state,
                        'type' => $d->type,
                        'timestamp' => $d->timestamp,
                        'device' => $d->device,
                        'processed' => $d->processed,
                        'location' => $d->location,
                    ];
                }
            }


            // Make a POST request
            $response = $client->post('/api/dtr-lgarec/store',[
                'headers' => $headers,
                'json' => [
                    'daily_time_records' => $dailyTimeRecordsArray,
                    'dtrs' => $dtrsArray,
                ],
            ]);
            if($response->getStatusCode() == 200){
                DailyTimeRecord::query()
                    ->whereIn('id',$dailyTimeRecords->pluck('id')->toArray())
                    ->update([
                        'api_status' => 'UPLOADED TO HRRS'
                    ]);
                DTR::query()
                    ->whereIn('id',$dtr->pluck('id')->toArray())
                    ->update([
                        'uploaded' => 1,
                    ]);
            }
            // Get the response body as an array
            $data = json_decode($response->getBody(), true);

            dd($data);

        } catch (\Exception $e) {
            // Handle any errors that occur during the API request
            // If unauthorized or token expired then login:
            if($e->getCode() == 401){
                $token = $this->login('gjg021','admin12345');
                if($token){
                    if($loop < $this->maxRecursion){
                        $this->sendDtr($loop+1);
                    }
                    //If multiple unsuccessful attempts were made:
                    if($loop = $this->maxRecursion){
                        dd('You have reached max attempt.');
                    }
                }
            }
            dd($e);
        }
    }
}