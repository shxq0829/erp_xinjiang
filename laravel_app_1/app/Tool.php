<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/16 下午8:06
 */

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Administrators;
use Symfony\Component\HttpFoundation\Session\Session;


class Tool extends Model
{
    const SESSION_KEY = 'ADMIN';
    private static $_userInfo = null;

    public static function signInUser($userName, $password, $mobile, $name)
    {
        $userInfo = new Administrators();
        $userInfo->user_name = $userName;
        $userInfo->password = md5($password);
        $userInfo->mobile = $mobile;
        $userInfo->name = $name;
        $ret = $userInfo->save();
        if (empty($ret))
        {
            return false;
        }
        return true;
    }

    public static function logoutUser()
    {
        self::$_userInfo = null;
        $session = new Session();
        $session->remove(self::SESSION_KEY);

        return true;
    }

    public static function loginUser($userName, $password)
    {
        if ($userName == '')
        {
            return false;
        }

        // 数据库检查
        $admin = Administrators::where([
            'user_name' => $userName,
            'password'  => md5($password),
            'status' => 0,
        ])->get(['user_name', 'password']);
        if ($admin->first())
        {
            // 写入Session
            self::_setUserSession($userName, $password);
            return self::getUser();
        }
        else
        {
            return false;
        }
    }

    private static function _getFromSession($key)
    {
        $session = new Session();
        $userInfo = $session->get($key);

        $user = json_decode($userInfo, true);

        return $user;
    }

    public static function getUser()
    {
        if (self::$_userInfo)
        {
            return self::$_userInfo;
        }

        self::$_userInfo = self::_getFromSession(self::SESSION_KEY);

        return self::$_userInfo;
    }

    private static function _setUserSession($userName, $password)
    {
        $createTime = time();
        $userInfo = [
            'u' => $userName,
            'p' => md5($password),
            't' => $createTime,
        ];
        $session = new Session();
        $session->set(self::SESSION_KEY, json_encode($userInfo));

        self::$_userInfo = self::_getFromSession(self::SESSION_KEY);

        return true;
    }

    public static function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data))
        {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);

        return $output;
    }
}
