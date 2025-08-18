<?php

namespace App\Swep\Services\API;

use App\Models\SuSettings;

class ApiService
{
    public function __construct()
    {
        $this->maxRecursion = 3;
        $this->baseUri = 'https://hrrs.sra.gov.ph';
    }

    public function login($username,$password)
    {
        $headers = [
            'Accept'        => 'application/json',
            'Content-type' => 'application/x-www-form-urlencoded',
        ];
        $login = new \GuzzleHttp\Client(['base_uri' => $this->baseUri]);
        $response = $login->post('/api/login',[
            'headers' => $headers,
            'form_params' => [
                'username' => 'gjg021',
                'password' => 'admin12345',
            ],
        ]);

        //If login is OK:
        if($response->getStatusCode() == 200){
            //This is the new token generated after login:
            $newToken = json_decode($response->getBody(),true)['authorization']['token'];
            //Insert a code here to save the token to the DB for future use:
            $this->setSavedToken($newToken);
            //Return Token
            return [
                'token' => $newToken,
            ];
        }
    }

    public function getSavedToken()
    {
        $setting = SuSettings::query()->where('setting','=','api_bearer_token')->first();
        if(!empty($setting)){
            return  $setting->text_value;
        }
        return null;
    }

    public function setSavedToken($token)
    {

        $setting = SuSettings::query()->where('setting','=','api_bearer_token')->first();
        if(empty($setting)){
            $set = new SuSettings();
            $set->setting = 'api_bearer_token';
            $set->text_value = $token;
            $set->save();
        }else{
            $setting->text_value = $token;
            $setting->save();
        }
    }
}