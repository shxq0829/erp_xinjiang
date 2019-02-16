<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Administrators extends Model
{
    protected $fillable = ['user_name', 'password', 'name', 'mobile'];
    protected $table = 'administrators';
    public $timestamps = true;

    protected function getDateFormat(){
        return time();
    }

    protected function asDateTime($val){
        return $val;
    }
}
