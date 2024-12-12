<?php


namespace App\Swep\Services;


use App\Models\BiometricDevices;
use App\Models\CronLogs;
use App\Models\DailyTimeRecord;
use App\Models\DTR;
use App\Models\Employee;
use App\Models\JoEmployees;
use App\Models\SuSettings;
use App\Swep\BaseClasses\BaseService;
use App\Swep\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rats\Zkteco\Lib\ZKTeco;

class DTRService extends BaseService
{
    public function extract($ip){
        $last_uid = 0;
        $last_from_device = 0;
        $attendances = $this->fetchAttendance($ip);
        $serial_no = $this->getSerialNo($ip);
        $last_uid_db = BiometricDevices::query()->where('serial_no','=',$serial_no)->first();

        try{
            if(!empty($last_uid_db)){
                if($last_uid_db->last_uid == null){
                    $last_uid = 0;
                }else{
                    $last_uid = $last_uid_db->last_uid;
                }
            }
            if(count($attendances) > 0){
                $last_from_device = array_key_last($attendances);
            }
            $server_location = SuSettings::query()->where('setting','=','server_location')->first()->string_value;

            $attendances_array = [];
            $forceClearDevice = false;

            //IF LAST FROM DEVICE IS LESS THAN THE LAST UID IN DATABASE, THE DEVICE MAY HAVE RESET ITS ID BACK TO 1
            if($last_from_device < $last_uid){
                //loop from last id + 1 to the last id before 0 index
                $while = $last_uid+1;
                while (isset($attendances[$while])){
                    $while++;
                }
                $while = $while - 1;
                for ($x = $last_uid+1 ; $x <= $while ; $x++){

                    if(isset($attendances[$x])){
                        $attendances_array[] = [
                            'uid' => $attendances[$x]['uid'],
                            'user' => $attendances[$x]['id'],
                            'state' => $attendances[$x]['state'],
                            'timestamp' => $attendances[$x]['timestamp'],
                            'type' => $attendances[$x]['type'],
                            'device' => $serial_no,
                            'location' => $server_location,
                        ];
                    }
                }

                //loop from 0 to last uid less 20000
                for ($x = 0 ; $x < $last_uid - 20000 ; $x++){
                    if(isset($attendances[$x])){
                        $attendances_array[] = [
                            'uid' => $attendances[$x]['uid'],
                            'user' => $attendances[$x]['id'],
                            'state' => $attendances[$x]['state'],
                            'timestamp' => $attendances[$x]['timestamp'],
                            'type' => $attendances[$x]['type'],
                            'device' => $serial_no,
                            'location' => $server_location,
                        ];
                    }
                }
                //clear device after insert
                $forceClearDevice = true;
            }else{
                for ($x = $last_uid+1 ; $x <= $last_from_device ; $x++){
                    if(isset($attendances[$x])){
                        if(isset($this->biometric_values(true)[$attendances[$x]['type']])){
                            $attendances_array[] = [
                                'uid' => $attendances[$x]['uid'],
                                'user' => $attendances[$x]['id'],
                                'state' => $attendances[$x]['state'],
                                'timestamp' => $attendances[$x]['timestamp'],
                                'type' => $attendances[$x]['type'],
                                'device' => $serial_no,
                                'location' => $server_location,
                            ];
                        }

                    }
                }
            }

            if(count($attendances_array) > 0){
                $a = DTR::insert($attendances_array);
                if($a){
                    $string = 'Copied '.count($attendances_array).' data from device: '.$ip;
                    $cl = new CronLogs;
                    $cl->log = $string;
                    $cl->type = 1;
                    $cl->save();

                    $last_uid_db->last_uid = $last_from_device;
                    $last_uid_db->last_state = 1;
                    $last_uid_db->last_state_timestamp = Carbon::now();
                    $last_uid_db->update();

                    if($forceClearDevice == true){
//                        $this->clearAttendance($ip);
                    }
                    return $string;
                }
                $string = 'Error doing insert';
                $cl = new CronLogs;
                $cl->log = $string;
                $cl->type = 1;
                $cl->save();

                $last_uid_db->last_state = -1;
                $last_uid_db->last_state_timestamp = Carbon::now();
                $last_uid_db->update();
                return 'Error doing insert';
            }else{
                $string = 'From device: '.$ip.' | No new attendance';
                $cl = new CronLogs;
                $cl->log = $string;
                $cl->type = 1;
                $cl->save();

                $last_uid_db->last_state = 1;
                $last_uid_db->last_state_timestamp = Carbon::now();
                $last_uid_db->update();

                return 'No new attendance found';
            }


        }catch (\Exception $e){
            $string = 'Error saving data from device: '.$ip.' | '.$e->getMessage();
            $cl = new CronLogs;
            $cl->log = $string;
            $cl->type = 0;
            $cl->save();

            $last_uid_db->last_state = -1;
            $last_uid_db->last_state_timestamp = Carbon::now();
            $last_uid_db->update();

            echo 'Device might be off: '.$e->getMessage();
        }
    }

    public  function reconstruct(){
        $dtrs_raw = DTR::query()->where('processed','=',null)
            ->orWhere('processed','=',0)->get();
        $values = $this->biometric_values();
        $no_of_processed = 0;
        $not_processed = 0;
        foreach ($dtrs_raw as $dtr_raw) {
            $biometric_user_id = $dtr_raw->user;
            $p_employee = Employee::query()->select('firstname','lastname','biometric_user_id','employee_no',DB::raw('"permanent" as type'))->where('biometric_user_id','=',$biometric_user_id);
            $jo_employee = JoEmployees::query()->select('firstname','lastname','biometric_user_id','employee_no',DB::raw('"jo" as type'))->where('biometric_user_id','=',$biometric_user_id);
            $employees = $p_employee->union($jo_employee)->first();
            $ext = '';

            if(!empty($employees)){
                $dtr_check = DailyTimeRecord::query()
                    ->where('date','=',Carbon::parse($dtr_raw->timestamp)->format('Y-m-d'))
                    ->where('employee_no','=',$employees->employee_no)
                    ->first();
                if(isset($values[$dtr_raw->type])){
                    $db_col = $values[$dtr_raw->type];
                    if(empty($dtr_check)){
                        $dtr = new DailyTimeRecord;
                        $dtr->slug = Str::random(30);
                        $dtr->$db_col = Carbon::parse($dtr_raw->timestamp)->format('H:i');
                        $dtr->date = Carbon::parse($dtr_raw->timestamp)->format('Y-m-d');
                        $dtr->employee_no = $employees->employee_no;
                        $dtr->biometric_user_id = $biometric_user_id;
                        $dtr->biometric_uid = 0;
                        $dtr->calculated = 0;
                        if($dtr->save()){
                            $dtr_raw->processed = 1;
                            $dtr_raw->update();
                            $no_of_processed++;
                        }
                    }else{

                        $dtr_check->$db_col = Carbon::parse($dtr_raw->timestamp)->format('H:i');
                        $dtr_check->biometric_user_id = $biometric_user_id;
                        $dtr_check->biometric_uid = 0;
                        $dtr_check->calculated = 0;
                        if($dtr_check->update()){
                            $dtr_raw->processed = 1;
                            $dtr_raw->update();
                            $no_of_processed++;
                        }
                    }
                }else{
                    $not_processed++;
                    $ext = ' | '.$not_processed.' data not processed due to DTR Value not set.';
                }

            }
        }

        $cl = new CronLogs;
        $cl->log = 'Reconstructed '.$no_of_processed.' raw DTR data'. $ext;
        $cl->type = 1;
        $cl->save();
    }

    public function compute(){
        $perm_latest_time_in = SuSettings::query()->where('setting','=','permanent_latest_time_in')->first()->time_value;
        $perm_earliest_time_out = SuSettings::query()->where('setting','=','permanent_earliest_time_out')->first()->time_value;


        $jo_latest_time_in = SuSettings::query()->where('setting','=','jo_latest_time_in')->first()->time_value;
        $jo_earliest_time_out = SuSettings::query()->where('setting','=','jo_earliest_time_out')->first()->time_value;

        $dtrs = DailyTimeRecord::query()->where('calculated','=',null)
            ->orWhere('calculated' , '=' ,0)
            ->get();
        $no_of_computed = 0;
        if(!empty($dtrs)){
            foreach ($dtrs as $dtr){
                $late = 0;
                $undertime = 0;
                $no_of_computed++;
//                $p_employee = Employee::query()->select('lastname','firstname',DB::raw('"PERM" as type'))->where('employee_no', '=' ,$dtr->employee_no);
//                $jo_employee = JoEmployees::query()->select('lastname','firstname',DB::raw('"JO" as type'))->where('employee_no', '=' ,$dtr->employee_no);
//                $all_employees = $p_employee->union($jo_employee);
//                $employee = $all_employees->first();
////              $employee->type = 'PERM';
//                if($employee->type == 'JO'){
//                    $latest_time_in = $jo_latest_time_in;
//                    $earliest_time_out = $jo_earliest_time_out;
//                }
//
//                if($employee->type == 'PERM'){
//                    $latest_time_in = $perm_latest_time_in;
//                    if($dtr->am_in == null || $dtr->am_in == '' || $dtr->am_in > $latest_time_in){
//                        $earliest_time_out = '18:00';
//                    }else{
//                        $earliest_time_out = Carbon::parse($dtr->am_in)->addHours(9)->format('H:i');
//                    }
//                }

                $employee = Employee::query()->select('lastname','firstname','locations')->where('employee_no', '=' ,$dtr->employee_no)->first();
                if(!empty($employee)){
                    if($employee->locations == 'COS-VISAYAS' || $employee->locations == 'COS-LUZMIN'){
                        $latest_time_in = $jo_latest_time_in;
                        $earliest_time_out = $jo_earliest_time_out;
                    }

                    if($employee->locations == 'VISAYAS' || $employee->locations == 'LUZON/MINDANAO'){
                        $latest_time_in = $perm_latest_time_in;
                        if($dtr->am_in == null || $dtr->am_in == '' || $dtr->am_in > $latest_time_in){
                            $earliest_time_out = '18:00';
                        }else{
                            $earliest_time_out = Carbon::parse($dtr->am_in)->addHours(9)->format('H:i');
                        }
                    }
                    //AM IN
                    if($dtr->am_in > $latest_time_in){
                        $diff = Carbon::parse($dtr->am_in)->diffInMinutes($latest_time_in);
                        $late = $late+$diff;
                    }

                    //PM IN
                    if($dtr->pm_in > '13:00'){
                        $diff = Carbon::parse($dtr->pm_in)->diffInMinutes('13:00');
                        $late = $late + $diff;
                    }

                    //AM OUT
                    if($dtr->am_out < '12:00'){
                        $diff = Carbon::parse($dtr->am_out)->diffInMinutes('12:00');
                        $undertime = $undertime + $diff;
                    }

                    //PM OUT
                    if($dtr->pm_out < $earliest_time_out){
                        $diff = Carbon::parse($dtr->pm_out)->diffInMinutes($earliest_time_out);
                        $undertime = $undertime + $diff;
                    }

                    $dtr->calculated = 1;
                    if(empty($dtr->am_in) || empty($dtr->am_out) || empty($dtr->pm_in) || empty($dtr->pm_out) || $dtr->am_in == '00:00:00' || $dtr->am_out == '00:00:00' || $dtr->pm_in == '00:00:00' || $dtr->pm_out == '00:00:00'){
                        $dtr->calculated = -1;
                    }
                    $dtr->late = $late;
                    $dtr->undertime = $undertime;
                    $dtr->save();
                }




            }
            if($no_of_computed > 0){
                $cl = new CronLogs;
                $cl->log = 'Computed '.$no_of_computed.' DTR Data';
                $cl->type = 3;
                $cl->save();
                return 1;
            }
        }
        return 0;
    }

    public function upload(){
        $server = \App\Models\SuSettings::query()->where('setting','=','server_location')->first()->string_value;
        $token = \App\Models\SuSettings::query()->where('setting','=','pairing_token')->first()->string_value;
        // set post fields
        $array = [
            'server' => $server,
            'token' => $token,
            'dtrs' => [],
        ];
        $staged_ids = [];
        $dtrs_array = [];
        $dtrs = \App\Models\DTR::query()->where('uploaded','=',null)->orWhere('uploaded','=',0)->limit(100)->get();
        if(!empty($dtrs)){
            foreach ($dtrs as $dtr) {
                $temp_arr = [
                    'uid' => $dtr->uid,
                    'user' => $dtr->user,
                    'state' => $dtr->state,
                    'type' => $dtr->type,
                    'timestamp' => $dtr->timestamp,
                    'device' => $dtr->device,
                    'location' => $server,
                ];
                array_push($dtrs_array,$temp_arr);
                array_push($staged_ids,$dtr->id);
            }
        }

        $array['dtrs'] = $dtrs_array;
        $ch = curl_init('http://119.93.145.202:1986/insertDTR');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($array));

        // execute!
        $response = curl_exec($ch);

        // close the connection, release resources used
        curl_close($ch);

        // do anything you want with your response
        $response = json_decode($response);
        if(!empty($response->code)){
            if($response->code == 200){
                if(count($staged_ids) > 0){
                    foreach ($staged_ids as $staged_id){
                        \App\Models\DTR::query()->find($staged_id)->update([
                            'uploaded' => 1,
                        ]);
                    }
                }
                \App\Models\CronLogs::insert([
                    'log' => 'Uploaded '.count($staged_ids).' DTRs to the server',
                    'type' => 8,
                ]);
            }
        }
    }

    public  function biometric_values($displayMode = false){
        if($displayMode == false){
            return [
                10 => 'am_in',
                20 => 'am_out',
                30 => 'pm_in',
                40 => 'pm_out',
                50 => 'ot_in',
                60 => 'ot_out',
            ];
        }else{
            return [
                10 => 'Morning IN',
                20 => 'Morning OUT',
                30 => 'Afternoon IN',
                40 => 'Afternoon OUT',
                50 => 'Overtime IN',
                60 => 'Overtime OUT',
            ];
        }
    }
    private function fetchAttendance($ip){

        $attendance = [];
        $zk = new ZKTeco($ip);
        $zk->connect();
        $zk = $zk->getAttendance();
        foreach ($zk as $data){
            $attendance[$data['uid']] = $data;
        }
        return $attendance;
    }


    public function clearAttendance($ip){

        try{
            $zk = new ZKTeco($ip);
            $zk->connect();
            $serial_no = $this->getSerialNo($ip);
            $dev = BiometricDevices::query()->where('serial_no','=',$serial_no)->first();
            if(!empty($dev)){
                $zk->clearAttendance();
                $dev->last_uid = 0;
                $dev->last_cleared = Carbon::now();
                $dev->last_cleared_user = Auth::user()->user_id;
                $dev->update();

                $cl = new CronLogs;
                $cl->log = 'Device cleared: '.$ip;
                $cl->type = 4;
                $cl->save();
                return 1;
            }

        }
        catch (\Exception $e){
            $string = 'Error saving sanitizing device: '.$ip.' | '.$e->getMessage();
            $cl = new CronLogs;
            $cl->log = $string;
            $cl->type = 0;
            $cl->save();

            return $e->getMessage();
        }
    }

    private function getSerialNo($ip){
        $zk = new ZKTeco($ip);
        $zk->connect();
        return  preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', Helper::getStingAfterChar($zk->serialNumber(),'='));
    }


}