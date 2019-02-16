<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/10 下午3:42
 */
namespace App\Http\Controllers\Aj;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;


class BaseController extends Controller
{

    /**
     * 记录请求参数日志
     */
    protected function _requestLog()
    {
        $request = json_encode(array_merge($_GET, $_POST), JSON_UNESCAPED_UNICODE);
        $log = 'AJ_REQUEST_TYPE : ' . get_called_class() . " | REQUEST : {$request}";
        Log::info($log);
    }

    /**
     * 记录接口返回数据日志
     * @param $data
     */
    protected function _resultLog($data)
    {
        $resultLog = json_encode($data, JSON_UNESCAPED_UNICODE);
        $log = 'AJ_REQUEST_TYPE : ' . get_called_class() . " | OUTPUT : {$resultLog}";
        Log::info($log);
    }
}
