<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Gifts extends Model
{
    protected $fillable = ['gift_id', 'type', 'expired_key', 'status', 'apple_id', 'assign_time'];
    protected $table = 'gifts';
    public $timestamps = true;

    protected function getDateFormat(){
        return time();
    }

    protected function asDateTime($val){
        return $val;
    }
}
