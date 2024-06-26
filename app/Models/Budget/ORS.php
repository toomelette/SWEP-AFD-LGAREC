<?php


namespace App\Models\Budget;


use App\Models\User;
use Auth;
use Illuminate\Database\Eloquent\Model;

class ORS extends Model
{
    protected $table = 'budget_ors';

    public static function boot()
    {
        parent::boot();
        static::updating(function($a){
            $a->user_updated = Auth::user()->user_id;
            $a->ip_updated = request()->ip();
            $a->updated_at = \Carbon::now();
        });

        static::creating(function ($a){
            $a->user_created = Auth::user()->user_id;
            $a->ip_created = request()->ip();
            $a->created_at = \Carbon::now();
        });
    }

    public function accountEntries(){
        return $this->hasMany(ORSAccountEntries::class,'ors_slug','slug');
    }

    public function orsEntries(){
        return $this->hasMany(ORSAccountEntries::class,'ors_slug','slug')->where('type','=','ORS');
    }

    public function dvEntries(){
        return $this->hasMany(ORSAccountEntries::class,'ors_slug','slug')->where('type','=','DV');
    }


    public function projectsApplied(){
        return $this->hasMany(ORSProjectsApplied::class,'ors_slug','slug');
    }

    public function creator(){
        return $this->hasOne(User::class,'user_id','user_created');
    }

    public function updater(){
        return $this->hasOne(User::class,'user_id','user_updated');
    }

}