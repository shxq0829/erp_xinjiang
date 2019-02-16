<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/16 下午8:06
 */

namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Exception;

class Access extends Model
{
    const ACCESS_TOCKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';
    const JSAPI_TICKET = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';

    public static function isValid(Request $request)
    {
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $nonce = $request->input('nonce');

        $token = config('wx.token');
        $list = [$token, $timestamp, $nonce];
        sort($list);
        $sign = sha1(implode($list));

        return $sign == $signature ? true : false;
    }


    public static function getAccessToken()
    {
        try
        {
            $wx  = config('wx');
            $arr = [
                'grant_type' => $wx['grant_type'],
                'appid'      => $wx['appid'],
                'secret'     => $wx['secret'],
            ];

            $opts    = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                ]
            ];
            $context = stream_context_create($opts);
            $url = self::ACCESS_TOCKEN_URL . '?' . http_build_query($arr);
            $result  = file_get_contents($url, false, $context);

            $accessToken = json_decode($result, true);
            if (!isset($accessToken['access_token']))
            {
                throw new Exception('accessToken接口有错误');
            }
            Log::info("获取的AccessToken为{$accessToken['access_token']}");
            return $accessToken['access_token'];
        }
        catch (Exception $e)
        {
            return false;
        }

    }

    public static function getJsapiTicket($accessToken)
    {
        $arr = [
            'access_token' => $accessToken,
            'type'      => 'jsapi',
        ];

        $opts    = [
            'http' => [
                'method'  => 'GET',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
            ]
        ];
        $context = stream_context_create($opts);
        $url = self::JSAPI_TICKET . '?' . http_build_query($arr);
        $result  = file_get_contents($url, false, $context);

        return $result;
    }
}
