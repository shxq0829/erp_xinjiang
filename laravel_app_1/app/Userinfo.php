<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Userinfo extends Model
{
    protected $fillable = ['apple_id', 'mobile', 'bonus', 'code'];
    protected $table = 'user_info';
    public $timestamps = true;

    protected function getDateFormat(){
        return time();
    }

    protected function asDateTime($val){
        return $val;
    }
}
