<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/11/30 下午11:12
 */
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Message extends Model
{
    /**
     * 发送短信
     *
     * @param string $mobile 		手机号码
     * @param string $msg 			短信内容
     * @param string $needstatus 	是否需要状态报告
     */
    public static function sendSMS( $mobile, $msg, $needstatus = 'true') {
        $chuanglan_config = config('sms.chuanglan');

        //创蓝接口参数
        $postArr = array (
            'account'  =>  $chuanglan_config['api_account'],
            'password' => $chuanglan_config['api_password'],
            'msg' => urlencode($msg),
            'phone' => $mobile,
            'report' => $needstatus
        );

        $result = self::curlPost( $chuanglan_config['api_send_url'] , $postArr);
        return $result;
    }

    /**
     * 发送变量短信
     *
     * @param string $msg 			短信内容
     * @param string $params 	最多不能超过1000个参数组
     */
    public static function sendVariableSMS( $msg, $params) {
        $chuanglan_config = config('sms.chuanglan');

        //创蓝接口参数
        $postArr = array (
            'account' => $chuanglan_config['api_account'],
            'password' => $chuanglan_config['api_password'],
            'msg' => $msg,
            'params' => $params,
            'report' => 'true'
        );

        $result = self::curlPost( $chuanglan_config['API_VARIABLE_URL'], $postArr);
        return $result;
    }


    /**
     * 查询额度
     *
     *  查询地址
     */
    public static function queryBalance() {
        $chuanglan_config = config('sms.chuanglan');

        //查询参数
        $postArr = array (
            'account' => $chuanglan_config['api_account'],
            'password' => $chuanglan_config['api_password'],
        );
        $result = self::curlPost($chuanglan_config['api_balance_query_url'], $postArr);
        return $result;
    }

    /**
     * 通过CURL发送HTTP请求
     * @param string $url  //请求URL
     * @param array $postFields //请求参数
     * @return mixed
     */
    private static function curlPost($url,$postFields){
        $postFields = json_encode($postFields);
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8'
            )
        );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec ( $ch );
        Log::info('send sms code|' . json_encode($ret));
        if (false == $ret) {
            $result = curl_error(  $ch);
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close ( $ch );
        return $result;
    }
}
