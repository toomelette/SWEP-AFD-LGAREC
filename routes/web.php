<?php

/** Auth **/

use App\Swep\Helpers\Helper;
use Rats\Zkteco\Lib\ZKTeco;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::get('/pdf', function () {
    $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
    file_put_contents('D:/SRA-QC-0001.png', $generator->getBarcode('SRA-QC-0001', $generator::TYPE_CODE_128));

    $image1 ='D:/SRA-QC-0001.png';
    $pdf = new \setasign\Fpdi\Fpdi();

    $filename = 'D:/sample.pdf';
    $totalPages = $pdf->setSourceFile($filename);
    $page_height = $pdf->GetPageHeight();
    $page_width = $pdf->GetPageWidth();


    for ($pageNo = 1;$pageNo <= $totalPages; $pageNo++){
        $pdf->AddPage();
        $tplIdx = $pdf->importPage($pageNo);

        $pdf->useTemplate($tplIdx, 0, 0, null, null, true);

//        UPPER RIGHT
        $mainX = $page_width - 50;
        $mainY = 20;
        //UPPER LEFT
//        $mainX = 10;
//        $mainY = 20;

        //BOTTOM LEFT
//        $mainX = 10;
//        $mainY = $page_height - 20;

        //BOTTOM RIGHT
//        $mainX = $page_width  - 45;
//        $mainY = $page_height - 20;



        $pdf->SetXY($mainX,$mainY);
        $pdf->SetFont('Arial', '', '8');
        $pdf->Image($image1,$mainX,$mainY-15,40 , 10);
        $pdf->SetFont('Arial', '', '8');
        $pdf->SetXY($mainX-10,$mainY-3);

        $pdf->Multicell(60,2    ,"SRA-QC-0001",0,"C");

        $pdf->SetXY($mainX-10,$mainY-18);
        $pdf->SetFont('Arial', '', '6');
        $pdf->Multicell(60,2    ,"SUGAR REGULATORY ADMINISTRATION",0,"C");
    }


    $pdf->Output('D:/sample.pdf', 'F');

//    header("location: ".$filename);
    exit;

});

Route::get('/qr',function (){
    $documents = \App\Models\Document::query()->get();
    $num = 1000001;
    foreach ($documents as $document){

        $document->document_id = 'SRA-VIS-'.$num;
        $document->update();
        $num++;
    }
    return 1;

});

Route::get('/rec1',function (){
    if(!\Illuminate\Support\Facades\Request::has('less_than') OR !\Illuminate\Support\Facades\Request::has('more_than')){
        return 'Missing less_than and _more_than';
    }
    $old_docs = DB::table('temp_rec_documents_qc')
        ->where('id','<=',\Illuminate\Support\Facades\Request::get('less_than'))
        ->orWhere('id','=>',\Illuminate\Support\Facades\Request::get('more_than'))
        ->get();

    foreach ($old_docs as $old_doc){
        $new_doc = \App\Models\Document::query()->where('slug','=',$old_doc->slug)->first();

        if(!empty($new_doc)){
            $new_doc->old_document_id = $old_doc->document_id;
            $new_doc->save();
        }
    }
    return 'Done';
});
Route::get('/rec2',function (){
    $d_logs = \App\Models\DocumentDisseminationLog::query()->where('document_id','like','%DOC%')->limit(5000)->get();
    foreach ($d_logs as $d_log){
        $doc = \App\Models\Document::query()->where('old_document_id','=', $d_log->document_id)->first();
        if(!empty($doc)){
            $d_log->document_id = $doc->document_id;
            $d_log->save();
        }
    }
    return 'DONE 5000';
});

Route::get('/rec3',function (){
    $d_logs = \App\Models\DocumentDisseminationLog::query()
        ->where('document_id','like','%DOC%')
        ->limit(500)->get();

    foreach ($d_logs as $d_log){

        $doc = \App\Models\Document::query()->where('subject','like', '%'.$d_log->subject.'%')->first();

        if(!empty($doc)){

            $d_log->document_id = $doc->document_id;
            $d_log->save();
        }
    }
    return 'DONE 1000';
});

Route::get('rec4',function (){
   $docs = \App\Models\Document::query()->where('path','=',null)->limit(4000)->get();
   foreach ($docs as $doc){

       $doc->path = \Illuminate\Support\Carbon::parse($doc->date)->format('Y').'/'.$doc->folder_code.'/';
       if($doc->folder_code2 != null){
           $doc->path2 =\Illuminate\Support\Carbon::parse($doc->date)->format('Y').'/'.$doc->folder_code2.'/';
       }
       $doc->save();
   }
   return 1;
});

Route::get('/dv1',function(){
    $dvs = \App\Models\DisbursementVoucher::query()->get();
    foreach ($dvs as $dv){
        $resp_center = '';
        switch ($dv->department_name){
            case 'RD':
                $resp_center = 'RD-VIS';
                break;
            case 'AFD':
                $resp_center = 'AFD-VIS';
                break;
            case 'RDE':
                $resp_center = 'RDE-VIS';
                break;
            default:
                break;
        }
        \App\Models\DisbursementVoucherDetails::insert([
            'slug' => \Illuminate\Support\Str::random(),
            'dv_slug' => $dv->slug,
            'dv_id' => $dv->dv_id,
            'resp_center' => $resp_center,
            'mfo_pap' => $dv->project_code,
            'amount' => $dv->amount,
        ]);
    }
    return 1;
});


//PUBLIC ROUTES
Route::group(['as' => 'auth.'], function () {
	
	Route::get('/', 'Auth\LoginController@showLoginForm')->name('showLogin');
	Route::post('/', 'Auth\LoginController@login')->name('login');
	Route::post('/logout', 'Auth\LoginController@logout')->name('logout');
	Route::get('/logout', 'Auth\LoginController@logout')->name('logout');
    Route::post('/username_lookup','Auth\AccountRecoveryController@username_lookup')->name('username_lookup');
    Route::post('/reset_password','Auth\AccountRecoveryController@reset_password')->name('reset_password');
    Route::post('/verify_email','Auth\AccountRecoveryController@verify_email')->name('verify_email');
    Route::get('/reset_password_via_email','Auth\AccountRecoveryController@reset_password_via_email')->name('reset_password_via_email');
});


/** HOME **/
Route::get('dashboard/home', 'HomeController@index')->name('dashboard.home')->middleware(['check.user_status','verify.email']);


Route::get('/dashboard/plantilla/print','PlantillaController@print')->name('plantilla.print');


//USER LEVEL ROUTES
Route::group(['prefix'=>'dashboard', 'as' => 'dashboard.',
    'middleware' => ['check.user_status', 'last_activity','sidenav_mw', 'verify.email']
], function () {
    Route::get('/dtr/my_dtr', 'DTRController@myDtr')->name('dtr.my_dtr');
    Route::get('/dtr/download','DTRController@download')->name('dtr.download');
    Route::get('/dtr/fetch_by_user_and_month', 'DTRController@fetchByUserAndMonth')->name('dtr.fetch_by_user_and_month');
    Route::post('dashboard/changePass','UserController@changePassword')->name('all.changePass');
    Route::post('/change_side_nav','SidenavController@change')->name('sidenav.change');
    Route::post('/dtr/update_time_record','DTRController@updateTimeRecord')->name('dtr.update_time_record');
    Route::post('/dtr/update_remarks','DTRController@updateRemarks')->name('dtr.update_remarks');
    Route::post('/dtr/update_lt_ut','DTRController@updateLateUndertime')->name('dtr.update_lt_ut');

    /** MIS REQUESTS **/
    Route::get('/mis_requests/my_requests','MisRequestsController@myRequests')->name('mis_requests.my_requests');
    Route::post('/mis_requests/store','MisRequestsController@store')->name('mis_requests.store');
    Route::post('/mis_requests/cancel_request','MisRequestsController@cancelRequest')->name('mis_requests.cancel_request');
    Route::get('/mis_requests/{slug}/print','MisRequestsController@printRequestForm')->name('mis_requests.print_request_form');
    Route::post('/mis_requests/store_img','MisRequestsController@storeImg')->name('mis_requests.store_img');
    Route::get('/mis_requests_status/index_open','MisRequestsStatusController@indexOpen')->name('mis_requests_status.index_open');

    /** PROFILE **/
    Route::get('/profile', 'ProfileController@details')->name('profile.details');
    Route::patch('/profile/update_account_username/{slug}', 'ProfileController@updateAccountUsername')->name('profile.update_account_username');
    Route::patch('/profile/update_account_password/{slug}', 'ProfileController@updateAccountPassword')->name('profile.update_account_password');
    Route::patch('/profile/update_account_color/{slug}', 'ProfileController@updateAccountColor')->name('profile.update_account_color');
    Route::get('/profile/print_pds/{slug}/{page}', 'ProfileController@printPds')->name('profile.print_pds');
    Route::post('/profile/save_family_info','ProfileController@saveFamilyInfo')->name('profile.save_family_info');

    /** PROFILE SERVICE RECORD**/
    Route::get('/profile/service_record','ProfileController@serviceRecord')->name('profile.service_record');
    Route::post('/profile/service_record_store','ProfileController@serviceRecordStore')->name('profile.service_record_store');
    Route::put('/profile/service_record_update/{slug}','ProfileController@serviceRecordUpdate')->name('profile.service_record_update');
    Route::delete('/profile/service_record/destroy/{slug}','ProfileController@serviceRecordDestroy')->name('profile.service_record_destroy');

    Route::get('/profile/training','ProfileController@training')->name('profile.training');
    Route::post('/profile/training_store','ProfileController@trainingStore')->name('profile.training_store');
    Route::put('/profile/training_update/{slug}','ProfileController@trainingUpdate')->name('profile.training_update');
    Route::delete('/profile/training_destroy/{slug}','ProfileController@trainingDestroy')->name('profile.training_destroy');

    Route::get('/ajax/{for}','AjaxController@get')->name('ajax.get');
    Route::post('/ajaxPost/{for}','AjaxPostController@post')->name('ajax.post');
    Route::post('/profile/educ_bg_store','ProfileController@educationalBackgroundStore')->name('profile.educ_bg_store');
    Route::post('/profile/eligibility_store','ProfileController@eligibilityStore')->name('profile.eligibility_store');
    Route::post('/profile/work_experience_store','ProfileController@workExperienceStore')->name('profile.work_experience_store');

    Route::post('/profile/select_theme','ProfileController@selectTheme')->name('profile.select_theme');

    Route::get('/view_doc/{id}','NewsController@viewDoc')->name('news.view_doc');
    Route::get('/view_document/{id}/{type}','ViewDocument@index')->name('view_document.index');

    /** PERMISSION SLIPS **/
    Route::get('/permission_slips/my_permission_slips','PermissionSlipController@myPermissionSlips')->name('permission_slip.my_permission_slips');

});


//ADMIN LEVEL ROUTES
/** Dashboard **/
Route::group(['prefix'=>'dashboard', 'as' => 'dashboard.',
    'middleware' => ['check.user_status', 'check.user_route', 'last_activity','verify.email']
], function () {

	/** USER **/

	Route::post('/user/activate/{slug}', 'UserController@activate')->name('user.activate');
	Route::post('/user/deactivate/{slug}', 'UserController@deactivate')->name('user.deactivate');
	Route::get('/user/{slug}/reset_password', 'UserController@resetPassword')->name('user.reset_password');
	Route::patch('/user/reset_password/{slug}', 'UserController@resetPasswordPost')->name('user.reset_password_post');
	Route::get('/user/{slug}/sync_employee', 'UserController@syncEmployee')->name('user.sync_employee');
	Route::patch('/user/sync_employee/{slug}', 'UserController@syncEmployeePost')->name('user.sync_employee_post');
	Route::post('/user/unsync_employee/{slug}', 'UserController@unsyncEmployee')->name('user.unsync_employee');

	Route::resource('user', 'UserController');


	/** DISBURSEMENT VOUCHERS **/
	Route::get('/disbursement_voucher/user_index', 'DisbursementVoucherController@userIndex')->name('disbursement_voucher.user_index');
	Route::get('/disbursement_voucher/print/{slug}/{type}', 'DisbursementVoucherController@print')->name('disbursement_voucher.print');
    Route::get('/disbursement_voucher/print_preview/{slug}', 'DisbursementVoucherController@printPreview')->name('disbursement_voucher.print_preview');
	Route::patch('/disbursement_voucher/set_no/{slug}', 'DisbursementVoucherController@setNo')->name('disbursement_voucher.set_no_post');
	Route::post('/disbursement_voucher/confirm_check/{slug}', 'DisbursementVoucherController@confirmCheck')->name('disbursement_voucher.confirm_check');
	Route::get('/disbursement_voucher/{slug}/save_as', 'DisbursementVoucherController@saveAs')->name('disbursement_voucher.save_as');
	Route::resource('disbursement_voucher', 'DisbursementVoucherController');





	/** MENU **/
	Route::resource('menu', 'MenuController');

    /** MENU **/
    Route::get('/submenu/fetch','SubmenuController@fetch')->name('submenu.fetch');
	Route::resource('submenu','SubmenuController');

	/** SIGNATORIES **/
	Route::resource('signatory', 'SignatoryController');


	/** DEPARTMENTS **/
	Route::resource('department', 'DepartmentController');


	/** DEPARTMENT UNITS **/
	Route::resource('department_unit', 'DepartmentUnitController');


	/** PROJECT CODES **/
	Route::resource('project_code', 'ProjectCodeController');


	/** FUND SOURCE **/
	Route::resource('fund_source', 'FundSourceController');


	/** LEAVE APPLICATION **/
	Route::get('/leave_application/user_index', 'LeaveApplicationController@userIndex')->name('leave_application.user_index');
	Route::get('/leave_application/print/{slug}/{type}', 'LeaveApplicationController@print')->name('leave_application.print');
	Route::get('/leave_application/{slug}/save_as', 'LeaveApplicationController@saveAs')->name('leave_application.save_as');
	Route::resource('leave_application', 'LeaveApplicationController');


	/** EMPLOYEE **/
    Route::get('/employee/edit_bm_uid','EmployeeController@edit_bm_uid')->name('employee.edit_bm_uid');
    Route::post('/employee/update_bm_uid','EmployeeController@update_bm_uid')->name('employee.update_bm_uid');
	Route::get('/employee/print_pds/{slug}/{page}', 'EmployeeController@printPds')->name('employee.print_pds');
	
	Route::get('/employee/service_record/{slug}', 'EmployeeController@serviceRecord')->name('employee.service_record');
	Route::post('/employee/service_record/store/{slug}', 'EmployeeController@serviceRecordStore')->name('employee.service_record_store');
	Route::put('/employee/service_record/update/{slug}', 'EmployeeController@serviceRecordUpdate')->name('employee.service_record_update');
	Route::delete('/employee/service_record/destroy/{slug}', 'EmployeeController@serviceRecordDestroy')->name('employee.service_record_destroy');
	Route::get('/employee/service_record/print/{slug}', 'EmployeeController@serviceRecordPrint')->name('employee.service_record_print');

    Route::get('/employee/credentials/{slug}', 'EmployeeCredentialsController@index')->name('employee.credentials');

    Route::get('/employee/educ_bg/create/{slug}', 'Employee\EducationalBGController@create')->name('employee.educ_bg.create');
    Route::get('/employee/educ_bg/edit/{slug}', 'Employee\EducationalBGController@edit')->name('employee.educ_bg.edit');
    Route::patch('/employee/educ_bg/update/{slug}', 'Employee\EducationalBGController@update')->name('employee.educ_bg.update');
    Route::delete('/employee/educ_bg/destroy/{slug}', 'Employee\EducationalBGController@destroy')->name('employee.educ_bg.destroy');
    Route::post('/employee/educ_bg/store', 'Employee\EducationalBGController@store')->name('employee.educ_bg.store');

    Route::get('/employee/elig/create/{slug}', 'Employee\EligibilityController@create')->name('employee.elig.create');
    Route::get('/employee/elig/edit/{slug}', 'Employee\EligibilityController@edit')->name('employee.elig.edit');
    Route::patch('/employee/elig/update/{slug}', 'Employee\EligibilityController@update')->name('employee.elig.update');
    Route::delete('/employee/elig/destroy/{slug}', 'Employee\EligibilityController@destroy')->name('employee.elig.destroy');
    Route::post('/employee/elig/store', 'Employee\EligibilityController@store')->name('employee.elig.store');

    Route::get('/employee/work/create/{slug}', 'Employee\WorkExperienceController@create')->name('employee.work.create');
    Route::get('/employee/work/edit/{slug}', 'Employee\WorkExperienceController@edit')->name('employee.work.edit');
    Route::patch('/employee/work/update/{slug}', 'Employee\WorkExperienceController@update')->name('employee.work.update');
    Route::delete('/employee/work/destroy/{slug}', 'Employee\WorkExperienceController@destroy')->name('employee.work.destroy');
    Route::post('/employee/work/store', 'Employee\WorkExperienceController@store')->name('employee.work.store');

    Route::put('/employee/credentials/update/{slug}', 'EmployeeController@serviceRecordUpdate')->name('employee.credentials_update');
    Route::delete('/employee/credentials/destroy/{slug}', 'EmployeeController@serviceRecordDestroy')->name('employee.credentials_destroy');


	Route::get('/employee/training/{slug}', 'EmployeeController@training')->name('employee.training');
	Route::post('/employee/training/store/{slug}', 'EmployeeController@trainingStore')->name('employee.training_store');
	Route::put('/employee/training/update/{slug}', 'EmployeeController@trainingUpdate')->name('employee.training_update');
	Route::delete('/employee/training/destroy/{slug}', 'EmployeeController@trainingDestroy')->name('employee.training_destroy');
	Route::get('/employee/training/print/{slug}', 'EmployeeController@trainingPrint')->name('employee.training_print');

	Route::get('/employee/matrix/{slug}', 'EmployeeController@matrix')->name('employee.matrix');
	Route::post('/employee/matrix/update/{slug}', 'EmployeeController@matrixUpdate')->name('employee.matrix_update');
	Route::get('/employee/matrix/show/{slug}', 'EmployeeController@matrixShow')->name('employee.matrix_show');
	Route::get('/employee/matrix/print/{slug}', 'EmployeeController@matrixPrint')->name('employee.matrix_print');

	Route::get('/employee/report', 'EmployeeController@report')->name('employee.report');
	Route::get('/employee/report_generate', 'EmployeeController@reportGenerate')->name('employee.report_generate');
	Route::resource('employee', 'EmployeeController');

	Route::resource('file201','File201Controller');
    Route::get('/other_hr_actions_print/{slug}/{type}','EmployeeController@otherHrActionsPrint')->name('employee.other_hr_actions_print');
    Route::get('/other_hr_actions/{slug}','EmployeeController@otherHrActions')->name('employee.other_hr_actions');



	/** DOCUMENTS **/
	Route::get('/document/print_qr/{slug}','DocumentController@printQr')->name('document.print_qr');
	Route::get('/document/report', 'DocumentController@report')->name('document.report');
	Route::get('/document/report_generate', 'DocumentController@report_generate')->name('document.report_generate');

	Route::get('/document/view_file/{slug}', 'DocumentController@viewFile')->name('document.view_file');
	Route::get('/document/download', 'DocumentController@download')->name('document.download');
	Route::post('/document/download_direct/{slug}', 'DocumentController@downloadDirect')->name('document.download_direct');
	Route::get('/document/dissemination/{slug}', 'DocumentController@dissemination')->name('document.dissemination');
	Route::post('/document/dissemination_post/{slug}', 'DocumentController@disseminationPost')->name('document.dissemination_post');

	Route::get('/document/rename_all', 'DocumentController@rename_all')->name('document.rename_all');

	Route::resource('document', 'DocumentController');

	Route::get('/document/dissemination/print/{slug}', 'DocumentController@print')->name('document.dissemination.print');

	




	/** Document Folder Codes **/
	Route::get('/document_folder/browse/{folder_code}', 'DocumentFolderController@browse')->name('document_folder.browse');
	Route::resource('document_folder', 'DocumentFolderController');


	/** Email Contacts **/
	Route::resource('email_contact', 'EmailContactController');


	/** Permission Slip **/
	Route::get('/permission_slip/report', 'PermissionSlipController@report')->name('permission_slip.report');
	Route::get('/permission_slip/report_generate', 'PermissionSlipController@reportGenerate')->name('permission_slip.report_generate');
	Route::resource('permission_slip', 'PermissionSlipController');


	/** Leave Card **/
	Route::get('/leave_card/report', 'LeaveCardController@report')->name('leave_card.report');
	Route::get('/leave_card/report_generate', 'LeaveCardController@reportGenerate')->name('leave_card.report_generate');
	Route::resource('leave_card', 'LeaveCardController');


	/** Applicant **/
	Route::post('/applicant/addToShortList/{slug}', 'ApplicantController@addToShortList')->name('applicant.add_to_shortlist');
	Route::post('/applicant/removeToShortList/{slug}', 'ApplicantController@removeToShortList')->name('applicant.remove_to_shortlist');
	Route::get('/applicant/report', 'ApplicantController@report')->name('applicant.report');
	Route::get('/applicant/report_generate', 'ApplicantController@reportGenerate')->name('applicant.report_generate');
	Route::resource('applicant', 'ApplicantController');


	/** Course **/
	Route::resource('course', 'CourseController');


	/** Plantilla **/
    Route::get('/plantilla/report', 'PlantillaController@report')->name('plantilla.report');
    Route::get('/plantilla/report_generate', 'PlantillaController@reportGenerate')->name('plantilla.report_generate');
	Route::resource('plantilla', 'PlantillaController');
    Route::resource('plantilla_employees', 'HrPayPlantillaEmployeesController');

    /** Activity Logs **/
    Route::get('/activity_logs/fetch_properties', 'ActivityLogsController@fetch_properties')->name('activity_logs_fetch_properties');

    /** PAP **/
    Route::resource('pap', 'PapController');

    /** PAP  Parents**/
    Route::resource('pap_parent', 'PapParentController');

    Route::resource('ppmp', 'PPMPController');

    /** DTR **/
    Route::get('/dtr/extract', 'DTRController@extract2')->name('dtr.extract');
    Route::get('/dtr/reconstruct', 'DTRController@reconstruct')->name('dtr.reconstruct');
//    Route::get('/dtr/my_dtr', 'DTRController@myDtr')->name('dtr.my_dtr');
//    Route::post('/dtr/download','DTRController@download')->name('dtr.download');

    Route::resource('dtr', 'DTRController');

    /** DTR **/
    Route::resource('jo_employees','JOEmployeesController');

    /** DTR **/
    Route::resource('news','NewsController');

    /** DTR **/
    Route::get('holidays/fetch_google','HolidayController@fetchGoogleApi')->name('holidays.fetch_google');
    Route::resource('holidays','HolidayController');

    /** Biometric Devices **/
    Route::get('biometric_devices','BiometricDevicesController@index')->name('biometric_devices.index');
    Route::post('biometric_devices/extract','BiometricDevicesController@extract')->name('biometric_devices.extract');
    Route::post('biometric_devices/restart','BiometricDevicesController@restart')->name('biometric_devices.restart');
    Route::get('biometric_devices/attendances','BiometricDevicesController@attendances')->name('biometric_devices.attendances');
    Route::post('biometric_devices/clear_attendance','BiometricDevicesController@clear_attendance')->name('biometric_devices.clear_attendance');
    Route::get('biometric_device/admin','BiometricDevicesController@admin')->name('biometric_devices.admin');
    Route::post('biometric_device/admin_change_password','BiometricDevicesController@adminChangePassword')->name('biometric_devices.admin_change_password');
    Route::post('biometric_device/clear_admin','BiometricDevicesController@clearAdmin')->name('biometric_devices.clear_admin');

    Route::get('mis_requests','MisRequestsController@index')->name('mis_requests.index');
    Route::get('mis_requests/{slug}/edit','MisRequestsController@edit')->name('mis_requests.edit');
    Route::put('mis_requests/{request_slug}/update','MisRequestsController@update')->name('mis_requests.update');
    Route::resource('ip_address','IpAddressController');


    Route::resource('mis_requests_status','MisRequestsStatusController');

    /** Budget Proposal**/
    Route::resource('budget_proposal', 'BudgetProposalController');

    /** PPMP **/
    Route::resource('ppmp', 'PPMPController');

    /** PPDO **/
    Route::resource('ppdo', 'PPU\PPDOController');


    /** ORS **/
    Route::get('ors/{slug}/print','Budget\ORSController@print')->name('ors.print');
    Route::get('ors/reports','Budget\ORSController@reports')->name('ors.reports');
    Route::get('ors/report_generate/{type}','Budget\ORSController@reportGenerate')->name('ors.report_generate');
    Route::resource('ors','Budget\ORSController');

    /** Projects **/
    Route::resource('projects','Budget\ProjectsController');

    /** Annual Budget **/
    Route::resource('annual_budget','Budget\AnnualBudgetController');
});

Route::get('display_qr/{slug}',function ($slug, \App\Http\Controllers\DocumentController $documentController){
    $document = \App\Models\Document::query()->where('slug','=',$slug)->first();
    $documentController->makeQR($document,$document->document_id);
    return response()->file(\Illuminate\Support\Facades\Storage::path('/QRCODE_TEMP/'.$document->document_id.'.png'));
})->name('display_qr');



Route::get('/dtr', function (){
    return redirect('/dashboard/dtr/my_dtr');
});
Route::get('/assign',function (){
    $users = \App\Models\User::query()->where('user_id','=','')->get();
    foreach ($users as $user){
        $user->user_id = rand(1111111,9999999);
        $user->update();
    }
    return 1;
});

Route::get('/dashboard/compute', function (\App\Swep\Services\DTRService $service){

    return $service->compute();


});

Route::get('/dashboard/tree', function (){
    return view('dashboard.blank');
});

Route::get('/file_explorer',function (){

   return view('dashboard.file_explorer.index');
})->name('dashboard.documents.file_explorer.index');

/** Test Route **/

Route::get('/dashboard/test', function(){

//    $zk = new ZKTeco('10.36.1.23');
//    //ini_set('max_execution_time', 300);
//    $zk->connect();
//    $zk->testVoice();
//    $zk->setTime('2022-01-04 14:59:03');
//
//    $zk->disconnect();

	return dd([
	    'slug' => Illuminate\Support\Str::random(16),
        'small' => strtoupper(Illuminate\Support\Str::random(7)),
    ]);

});

Route::get('dashboard/prayer', function (){
    $path = asset('json/quotes.json');
    $content = json_decode(file_get_contents($path),true);

    $today = Carbon::now()->format('Y-m-d');
    $qod_db = \App\Models\QuoteOfTheDay::query()->where('date',$today)->first();
    if(empty($qod_db)){
        $random = rand(0,1643);
        $qod = new \App\Models\QuoteOfTheDay();
        $qod->quote = $random;
        $qod->date = $today;
        $qod->save();

    }
    $qod_db_2 = \App\Models\QuoteOfTheDay::query()->where('date',$today)->first();
    return view('dashboard.prayer.index')->with([
        'qod' => $content[$qod_db_2->quote],
    ]);
})->name('dashboard.prayer');

Route::get('dashboard/zk_test',function (){

    $zk = new \Rats\Zkteco\Lib\ZKTeco('10.36.1.21');
    $zk->connect();

    return $zk->getUser();
    //return $zk->serialNumber();
});


Route::get('jo',function (){
   return view('dashboard.public.jo_entry')->with([
       'user_menus_records ' => '',

   ]);
});

Route::get('check_device',function (\App\Swep\Services\DTRService $DTRService){
    if(!request()->has('ip')){
        return 'Missing IP';
    }
    $ip = request()->get('ip');
    $zk = new ZKTeco($ip);
    $zk->connect();
    return $zk->getAttendance();
//    return $DTRService->clearAttendance('10.36.1.23');
});

Route::get('dashboard/set', 'Pub\SetController@index')->name('dashboard.set');


Route::get('/phpinfo',function (){
    echo phpinfo();
});





Route::get('/getSerial',function (\Illuminate\Http\Request $request){
    if(!$request->has('ip')){
        return 'IP Address missing';
    }
    $zk = new ZKTeco($request->ip);
    $zk->connect();
    return $zk->serialNumber();
});










Route::get('/acc',function (){
    $a = \App\Models\UserSubmenu::query()->where('user_id','=',\Illuminate\Support\Facades\Auth::user()->user_id)
        ->leftJoin('su_submenus','su_submenus.submenu_id','=','su_user_submenus.submenu_id')
        ->where('su_submenus.route','=','dashboard.dtr.store')
        ->first();
    return $a;
});

Route::get('grab',function (){
    if(!request()->has('date')){
        return view('dashboard.gj.pre_grab');
    }
    return view('dashboard.gj.grab');
});

Route::get('/verifyEmail',function (){
    if(\Illuminate\Support\Facades\Auth::user()->email != null){
        return redirect('/');
    }
    return view('dashboard.verify_email.verify');
});

Route::post('/verifyEmail',function (\Illuminate\Http\Request $request){
    $request->validate([
        'email' => 'required|email',
    ]);
    $user = Auth::user();
    $user->email = $request->email;
    if($user->save()){
        return 1;
    }
    abort(503,'Error updating email.');
});

Route::get('summaryOfOrsWithProjects',function (\Illuminate\Http\Request $request){
    if(!$request->has('start') || !$request->has('end')){
        return view('dashboard.budget.ors.pre');
    }
    $arr = [];
    $start = $request->start;
    $end = $request->end;
    $burs = \App\Models\SqlServer\BUR::query()
        ->with('BURDetails')
        ->whereBetween('BURDate',[
            $start,$end
        ]);

    if(!empty($request->funds)){
        $funds = $request->funds;
        $burs = $burs->where(function ($q) use ($funds){
            foreach ($funds as $fund){
                $q = $q->orWhere('Funds','=',$fund);
            }
        });
    }

    $burs = $burs->orderBy('BURDate','asc')
        ->get();
    $cols = \App\Models\SqlServer\BURDet::query()
        ->whereHas('BURParentData',function ($q) use ($start,$end){
            return $q->whereBetween('BURDate',[
                $start,$end
            ]);
        })
        ->groupBy('Dept')->pluck('dept')->toArray();
    $colss = [];
    foreach ($cols as $col){
        $colss[$col] = null;
    }
    ksort($colss);

    foreach ($burs as $bur){
        $arr[$bur->BURNo]['obj'] = $bur;
        $arr[$bur->BURNo]['accountEntries'] = $colss;
        if(!empty($bur->BURDetails)){
            foreach ($bur->BURDetails as $det){
                $arr[$bur->BURNo]['accountEntries'][$det->Dept] = $arr[$bur->BURNo]['accountEntries'][$det->Dept] + $det->Debit;
            }
        }

        if(!empty($bur->BURProjApplied)){
            foreach ($bur->BURProjApplied as $proj){
                $arr[$bur->BURNo]['projectsApplied'][\Illuminate\Support\Str::random()] = $proj;
            }
        }
    }



    return view('printables.bur.bur_with_projects')->with([
        'burs' => $arr,
        'cols' => $colss,
    ]);
});

Route::get('/migrate_bur',function (){
    //please resume on 44,000

    $offset = 3000;
    $burs = \App\Models\SqlServer\BUR::query()
        ->with(['BURDetails','BURProjApplied','certified','budget'])
        ->where('BURDate','>=','2023-01-01')
        ->offset($offset)
        ->limit(1000)
        ->get();
    $orsArr = [];
    $orsDetailsArr = [];
    $orsProjectsAppliedArr = [];

    if($burs->isEmpty()){
        return 'NO records found';
    }

    foreach ($burs as $bur){
        $slug = \Illuminate\Support\Str::random();
        array_push($orsArr,[
            'slug' => $slug,
            'project_id' => $bur->ProjectID,
            'ors_id' => $bur->BURID,
            'funds' => $bur->Funds,
            'ors_no' => $bur->BURNo,
            'base_ors_no' => null,
            'ors_date' => Carbon::parse($bur->BURDate)->format('Y-m-d'),
            'ref_book' => $bur->RefBook,
            'ref_doc' => $bur->RefDoc,
            'payee' => $bur->Payee,
            'office' => $bur->Office,
            'address' => $bur->Address,
            'particulars' => $bur->Particulars,
            'certified_by' => $bur->certified->SignName ?? $bur->CertifiedByInitial,
            'certified_by_position' => $bur->Position,
            'certified_budget_by' => $bur->budget->SignName ?? $bur->CertifiedBudget,
            'certified_budget_by_position' => $bur->BudgetPost,
            'amount' => $bur->BURAmt,
        ]);

        if(count($bur->BURDetailsAll) > 0){
            foreach ($bur->BURDetailsAll as $detail){
                array_push($orsDetailsArr,[
                    'slug' => \Illuminate\Support\Str::random(),
                    'ors_slug' => $slug,
                    'type' => $detail->BURorDV == 'BUR' ? 'ORS': $detail->BURorDV,
                    'seq_no' => $detail->SEQNO,
                    'resp_center' => null,
                    'dept' => $detail->Dept,
                    'unit' => $detail->DeptUnit,
                    'account_code' => $detail->AcctCode,
                    'debit' => $detail->Debit == 0 ? null : $detail->Debit,
                    'credit' => $detail->Credit == 0 ? null: $detail->Credit,
                ]);
            }
        }

        if(count($bur->BURProjApplied) > 0){
            foreach ($bur->BURProjApplied as $proj){
                array_push($orsProjectsAppliedArr,[
                    'slug' => Str::random(),
                    'ors_slug' => $slug,
                    'pap_code' => $proj->AcctCode,
                    'co' => $proj->CoAmt == 0 ? null : $proj->CoAmt,
                    'mooe' => $proj->Amount == 0 ? null : $proj->Amount,
                    'total' => $proj->CoAmt + $proj->Amount,
                ]);
            }
        }
    }
    \App\Models\Budget\ORS::insert($orsArr);
    \App\Models\Budget\ORSAccountEntries::insert($orsDetailsArr);
    \App\Models\Budget\ORSProjectsApplied::insert($orsProjectsAppliedArr);
    return $offset;
});

Route::get('migrate_coa',function (){
    $coa = \App\Models\SqlServer\COA::query()->get();
    $arr = [];
    foreach ($coa as $c){
        array_push($arr,[
            'slug' => \Illuminate\Support\Str::random(),
            'account_code' => $c->AccountCode,
            'account_title' => $c->AccountTitle,
            'account_init' => $c->AccountInit,
            'gl_group_id' => $c->GLGroupInd,
            'gl_group' => $c->GLGroup,
            'nature_id' => $c->NatureID,
            'bank_rec_account' => $c->BackRecAcct,
            'normal_bal' => $c->NormalBal,
            'isbs_accounts' => $c->ISBSAccts,
            'resp_center' => $c->RespCenterCode,
            'header_1' => $c->Header1,
            'header_2' => $c->Header2,
            'header_3' => $c->Header3,
            'name_of_collecting_officer' => $c->NameOfCollOfficer,
            'parent_account' => $c->ParentAcct,
            'child_account' => $c->ChildAcct,
            'has_sched' => $c->HasSched,
            'auto_dv' => $c->AUTODV,
            'fa_account' => $c->FAACCT,
            'for_or' => $c->ForOR,
            'taxable' => $c->Taxable,
            'bur_per_account' => $c->BURPERACCT,
            'bur_oblig' => $c->BUR_OBLIG,
            'bur_oblig_group' => $c->BUR_OBLIG_GROUP,
            'g1' => $c->G1,
            'g2' => $c->G2,
            'g4' => $c->G4,
            'treas_account' => $c->TREASACCT,
            'tax' => $c->TAX,
            'account_number' => $c->ACCOUNTNUMBER,
        ]);
    }
    \App\Models\Budget\ChartOfAccounts::insert($arr);
});

Route::get('/sqlsrv',function (){
   $serverName = '10.36.1.105\SRA';
   $connectionInfo = [
           'Database' => 'GASS',
           'UID' => 'sa',
           'PWD' => 'noliboy',
//            'Encrypt' => 'no',
//            'TrustServerCertificate' => true,
       ];
   $conn = sqlsrv_connect($serverName,$connectionInfo);
    if( $conn ) {
        echo "Connection established.<br />";
    }else{
        echo "Connection could not be established.<br />";
        die( print_r( sqlsrv_errors(), true));
    }
});


Route::get('numtowords',function (){
    $num = 1546;

});
Route::resource('mail',\App\Http\Controllers\Test\MailController::class);
Route::get('/mails',function (){
    $connected = @fsockopen("www.google.com",80);
    $connected_2 = @fsockopen("www.yahoo.com",80);

        if(!$connected){
            if(!$connected_2){
                return "<center style='font-family:Arial; color:red; padding-top:100px; font-size:26px'><b>No internet or Server not responding</b></center>";
            }
        }

        $testMailData = [
            'title' => 'Test Email From SWEP',
            'body' => 'This is the body of test email.'
        ];

        Mail::to('gguance221@gmail.com')->send(new \App\Mail\SendMail($testMailData));

        dd('Success! Email has been sent successfully.');

});


Route::get('/checking',function (){
    $burs = \App\Models\SqlServer\BUR::query()->with(['BURProjApplied'])
        ->where('BURDate','>','2022-12-31')
        ->whereHas('BURProjApplied',function ($q){
            return $q->where('COAmt','>',0);
        })
        ->get();
    foreach ($burs as $bur){
        echo $bur->BURNo.'<hr>';
        foreach($bur->BURProjApplied as $d){
            echo $d->COAmt.'<br>';
        }
        echo '<br><br>';
    }
}
);

Route::get('fullPather',function (){
    $file201s = \App\Models\EmployeeFile201::query()
        ->whereNull('full_path')
        ->get();
    foreach ($file201s as $file201){
        $file201->full_path = '/File201/'.$file201->employee_no.'/'.$file201->filename;
        $file201->save();
    }
    dd(1);
});

Route::get('/sendDtr',[\App\Swep\Services\API\ApiDtrService::class,'sendDtr']);