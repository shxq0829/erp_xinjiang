<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    protected $fillable = ['keyword', 'reply', 'type'];
    protected $table = 'keyword_reply';
    public $timestamps = true;

    protected function getDateFormat(){
        return time();
    }

    protected function asDateTime($val){
        return $val;
    }
}
