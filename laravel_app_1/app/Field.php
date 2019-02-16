<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/16 下午8:06
 */

namespace App;

use Illuminate\Database\Eloquent\Model;


class Field extends Model
{
    const KEYWORD_TYPE_REPLY = 0;
    const KEYWORD_TYPE_GIFT = 1;
    const KEYWORD_TYPE_TASET = 2;

    const GIFT_STATUS_NOT_ASSIGN = 0;
    const GIFT_STATUS_ASSIGNED   = 1;
}
