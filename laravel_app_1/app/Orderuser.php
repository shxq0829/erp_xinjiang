<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Orderuser extends Model
{
    protected $table = 'order_user';
    public $timestamps = true;
    protected $hidden = ['updated_at', 'ext'];

    protected function getDateFormat(){
        return time();
    }

//    protected function asDateTime($val){
//        return $val;
//    }

}
