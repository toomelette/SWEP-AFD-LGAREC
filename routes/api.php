<?php



// Employee
Route::get('/employee/serviceRecord/{slug}/edit', 'Api\ApiEmployeeController@editServiceRecord')
		->name('api.employee_serviceRecord_edit');


Route::get('/employee/training/{slug}/edit', 'Api\ApiEmployeeController@editTraining')
		->name('api.employee_training_edit');




// User
Route::get('/user/response_from_employee/{slug}', 'Api\ApiUserController@responseFromEmployee')
		->name('api.user_response_from_employee');




// Submenu
Route::get('/submenu/select_submenu_byMenuId/{menu_id}', 'Api\ApiSubmenuController@selectSubmenuByMenuId')
		->name('selectSubmenuByMenuId');




// Department Unit
Route::get('/department_unit/select_departmentUnit_byDeptName/{dept_name}', 'Api\ApiDepartmentUnitController@selectDepartmentUnitByDepartmentName')
		->name('selectDepartmentUnitByDepartmentName');


Route::get('/department_unit/select_departmentUnit_byDeptId/{dept_id}', 'Api\ApiDepartmentUnitController@selectDepartmentUnitByDepartmentId')
		->name('selectDepartmentUnitByDepartmentId');




// Project Code
Route::get('/project_code/select_projectCode_byDeptName/{dept_name}', 'Api\ApiProjectCodeController@selectProjectCodeByDepartmentName')
		->name('selectProjectCodeByDepartmentName');




// Department
Route::get('/department/textbox_department_ByDepartmentId/{dept_id}', 'Api\ApiDepartmentController@textboxDepartmentByDepartmentId')
		->name('textboxDepartmentByDepartmentId');

Route::get('/dtr_data',\Api\ApiDTRController::class."@getDtrData");
