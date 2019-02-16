<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/10 上午12:15
 */
namespace App;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    public static function checkMobile($mobile)
    {
        if ( !preg_match('/^\d{11}$/', $mobile) )
        {
            return false;
        }
        return true;
    }
}
