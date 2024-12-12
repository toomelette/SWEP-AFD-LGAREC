<?php


namespace App\Http\Controllers;


use App\Models\BiometricDevices;
use App\Models\DTR;
use App\Models\Employee;
use App\Models\JoEmployees;
use App\Models\UserSubmenu;
use App\Swep\Helpers\Helper;
use App\Swep\Services\DTRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use Rats\Zkteco\Lib\Helper\Util;
use Rats\Zkteco\Lib\ZKTeco;
use Yajra\DataTables\DataTables;
use function foo\func;

class BiometricDevicesController extends Controller
{

    public function index(){
        $colors = [
            1 => 'bg-green',
            2 => 'bg-aqua',
            3 => 'bg-purple',
            4 => 'bg-blue',
            5 => 'bg-red',
            6 => 'bg-info',
            7 => 'bg-green',
        ];

        $devices = BiometricDevices::query()->get();
        return view('dashboard.biometric_devices.index')->with([
            'devices' => $devices,
            'colors' => $colors,
        ]);
    }

    public function restart(Request $request){
        $device = BiometricDevices::query()->find($request->id);

        if(empty($device)){
            abort(503,'Device not found');
        }

        if($device->status != 1){
            abort(503,'Device status is set to off. Change it to active first.');
        }

        $ip = $device->ip_address;
        $this->checkDevice($ip);
        try{
            $zk = new ZKTeco($ip);
            $zk->connect();
            $zk->restart();
        }catch (\Exception $e){
           // return $e;
            abort(503,'Error communicating to device. Device might be off.');
        }

    }

    public function extract(Request $request, DTRService $DTRService){
        $user_id = Auth::user()->user_id;
        $check_sm = UserSubmenu::query()->where('user_id','=',$user_id)->where('submenu_id','=','rfOgNb0Y')->first();
        if(empty($check_sm)){
            abort(503,'You are not allowed to make this action');
        }

        $device = BiometricDevices::query()->find($request->id);
        $ip_address = $device->ip_address;
        $this->checkDevice($ip_address);

        $serve = $DTRService->extract($ip_address);
        return $serve;
    }
    public function attendances(Request $request){

        if($request->has('draw')){

            if ($request->has('device')){
//                return $request;
                $dtrs = DTR::query()->with(['employee'])->where('device','=',$request->device);

                $dt = DataTables::of($dtrs)
                    ->editColumn('fullname',function($data){
                        if(empty($data->employee)){
                            return $data->user;
                        }
                        return strtoupper($data->employee->lastname).', '.strtoupper($data->employee->firstname);
                    })
                    ->editColumn('type',function ($data){
                        return Helper::dtr_type($data->type);
                    })
                    ->toJson();

                return $dt;
            }
        }

        if (!$request->has('id')){
            abort(503,'Missing parameters');
        }


        $device = BiometricDevices::query()->find($request->id);

        if (empty($device)){
            abort(503,"Device not found");
        }
        if($device->status != 1){
            abort(503,'Device is not available.');
        }
        $ip_address = $device->ip_address;
        $employees_arr = [];

        $perm_e = Employee::query()->select('firstname','middlename','lastname','employee_no','biometric_user_id')->where('biometric_user_id', '!=' ,0);

        $union = $perm_e->get();

        foreach ($union as $employee){
            $employees_arr[$employee->biometric_user_id] = $employee;
        }

        return view('dashboard.biometric_devices.logs')->with([
            'device' => $device,
            'employees_arr' => $employees_arr,
        ]);
    }

    public function clear_attendance(Request $request, DTRService $DTRService){
        if($request->has('id') && $request->has('password')){
            if(Hash::check($request->password , Auth::user()->password)){
                $device = BiometricDevices::query()->find($request->id);
                $this->checkDevice($device->ip_address);
                if(!empty($device)){
                    $dtr_service = $DTRService->clearAttendance($device->ip_address);
                    if($dtr_service){
                        return 'Attendance cleared successfully.';
                    }
                    abort(503, 'Error clearing attendance.');
                }
                abort(503,'Device not found');
            }
            abort(503,'Incorrect Password');
        }
        abort(503,'Missing parameters');
    }
    private function checkDevice($ip, $port=4370, $timeout=10) {

        try{
            $fp = fSockOpen($ip, $port, $errno, $errstr, $timeout);
            return true;
        }catch (\Exception $e){
            abort(503, 'Device not connected to the network.');
        }
    }
    public function admin(Request $request){

        $bd = $this->getDevice($request->id);
        $ip = $bd->ip_address;
        $this->checkDevice($ip);
        $zk = new ZKTeco($ip);
        $zk->connect();
        $password = 1234;
        $zk = new ZKTeco($bd->ip_address);
        $zk->connect();
        if(!isset($zk->getUser()[999])){
            $zk->setUser(
                '999',
                '999',
                'ADMIN',
                $password,
                Util::LEVEL_ADMIN
            );
        }
        if(!isset($zk->getUser()[999])){
            abort(503,'Admin User 999 not found');
        }
        return view('dashboard.biometric_devices.admin')->with([
            'admin' => $zk->getUser()[999],
            'device' => $bd,
        ]);
    }

    private function getDevice($id){
        $bd = BiometricDevices::query()->find($id);

        if(empty($bd)){
            abort(503,'Device not found in the database.');
        }
        if($bd->status != 1){
            abort(503, 'Device is inactive.');
        }
        return $bd;
    }

    public function adminChangePassword(Request $request){
        $request->validate([
            'password' => 'required|int|max:9999|min:1000',
        ]);
        $bd = $this->getDevice($request->id);
        $this->checkDevice($bd->ip_address);
        $zk = new ZKTeco($bd->ip_address);
        $zk->connect();
        $zk->setUser(
            '999',
            '999',
            'ADMIN',
            $request->password,
            Util::LEVEL_ADMIN
        );
        return [
            'text' => 'Device admin password changed succesfully.',
            'new_pass' => $request->password,
        ];
    }

    public function clearAdmin(Request $request){
        $bd = $this->getDevice($request->id);
        $this->checkDevice($bd->ip_address);
        $zk = new ZKTeco($bd->ip_address);
        try{
            $zk->connect();
            $zk->clearAdmin();
            $zk->removeUser(999);
            $zk->setUser(
                '999',
                '999',
                'ADMIN',
                        '1234',
                Util::LEVEL_ADMIN
            );
            return 'Admin password cleared successfully.';
        }catch (\Exception $e){
            abort(504,'Error performing the action.');
        }
    }
}