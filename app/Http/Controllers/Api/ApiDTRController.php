<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DTR;
use Illuminate\Http\Request;

class ApiDTRController extends Controller
{
    public function getDtrData (Request $request){
        $dtr = DTR::query();
        if($request->has('last') && $request->last != ''){
            $dtr = $dtr->where('id','>',$request->last);
        }

        $dtr = $dtr->orderBy('id','asc')
            ->limit(10000)
            ->get();
        return $dtr;
    }
}