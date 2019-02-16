<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/25 下午11:26
 */
namespace App\Http\Controllers;

use App;
use App\Access;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Exception;

class IndexController extends Controller
{
    const CACHE_VALID = 120;
    public function headpage()
    {
        return view('index');
    }
    public function headpageMobile()
    {
        return view('headpage_mobile');
    }

    public function reservation()
    {
        try
        {
            if (!function_exists('getallheaders')) {
                function getallheaders() {
                    foreach ($_SERVER as $name => $value) {
                        if (substr($name, 0, 5) == 'HTTP_') {
                            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                        }
                    }
                    return $headers;
                }
            }
            foreach (getallheaders() as $name => $value) {
                echo "$name: $value\n";
            }
            if (Cache::has('ticket'))
            {
                $ticketJson = Cache::get('ticket');
            }
            else
            {
                $accessToken = Access::getAccessToken();
                if (empty($accessToken))
                {
                    throw new Exception('accessToken获取失败');
                }
                $ticketJson = Access::getJsapiTicket($accessToken);
                Cache::put('ticket', $ticketJson, self::CACHE_VALID);
            }
            $ticket   = json_decode($ticketJson, true);
            if ($ticket['errcode'] != 0)
            {
                throw new Exception('获取ticket出错');
            }
            $noncestr = $this->getRandomStr(16);
            $time     = time();
            $url      = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $param    = [
                'noncestr'     => $noncestr,
                'jsapi_ticket' => $ticket['ticket'],
                'timestamp'    => $time,
                'url'          => $url,
            ];
            ksort($param);
            $str = "jsapi_ticket={$ticket['ticket']}&noncestr={$noncestr}&timestamp={$time}&url={$url}";
            $signature = sha1($str);

            return view('index', [
                'time'      => $time,
                'noncestr'  => $noncestr,
                'signature' => $signature,
            ]);
        }
        catch (Exception $e)
        {
            return view('index', [
                'time'      => '',
                'noncestr'  => '',
                'signature' => '',
            ]);
        }
    }

    public function reservationAdmin()
    {
        try
        {
            if (Cache::has('ticket'))
            {
                $ticketJson = Cache::get('ticket');
            }
            else
            {
                $accessToken = Access::getAccessToken();
                if (empty($accessToken))
                {
                    throw new Exception('accessToken获取失败');
                }
                $ticketJson = Access::getJsapiTicket($accessToken);
                Cache::put('ticket', $ticketJson, self::CACHE_VALID);
            }
            $ticket   = json_decode($ticketJson, true);
            if ($ticket['errcode'] != 0)
            {
                throw new Exception('获取ticket出错');
            }
            $noncestr = $this->getRandomStr(16);
            $time     = time();
            $url      = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $param    = [
                'noncestr'     => $noncestr,
                'jsapi_ticket' => $ticket['ticket'],
                'timestamp'    => $time,
                'url'          => $url,
            ];
            ksort($param);
            $str = "jsapi_ticket={$ticket['ticket']}&noncestr={$noncestr}&timestamp={$time}&url={$url}";
            $signature = sha1($str);

            return view('index', [
                'time'      => $time,
                'noncestr'  => $noncestr,
                'signature' => $signature,
            ]);
        }
        catch (Exception $e)
        {
            return view('index', [
                'time'      => '',
                'noncestr'  => '',
                'signature' => '',
            ]);
        }
    }

    function getRandomStr($length = 16)
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++)
        {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $str;
    }
}