<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeTrainingEditForm extends FormRequest{
    


    public function authorize(){

        return false;
    
    }

    



    public function rules(){

        return [
            
        ];
    
    }




}
