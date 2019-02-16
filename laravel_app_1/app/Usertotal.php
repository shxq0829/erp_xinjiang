<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Usertotal extends Model
{
    protected $fillable = ['weight', 'total', 'ext'];
    protected $table = 'user_total';
    public $timestamps = true;

    protected function getDateFormat(){
        return time();
    }

    protected function asDateTime($val){
        return $val;
    }
}
