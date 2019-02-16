<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/12/9 下午9:40
 */
namespace App\Http\Controllers\Aj;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Check;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Exception;
use App\Message;
use App\Orderuser;
use App\Usertotal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Session\Session;

class SmscodeController extends BaseController
{
    const CACHE_NUM   = 20;
    const CACHE_TIME  = 1800;
    const CACHE_VALID = 1440;
    const KEY         = 1;

    public function sendSmscode(Request $request)
    {
        try
        {
            $mobile = $request->input('mobile');
            if (!Check::checkMobile($mobile))
            {
                throw new Exception('手机号码不符合格式');
            }

            if (Cache::has($mobile))
            {
                $oldCache = json_decode(Cache::get($mobile), true);
                if ($oldCache['num'] > self::CACHE_NUM && time() < $oldCache['curDayTime'])
                {
                    throw new Exception('同一手机号码验证次数过多');
                }

                // 验证码过期，则重新生成；验证码不过期，那发送原来的验证码
                $checkNum = time() < $oldCache['curDayTime'] ? ($oldCache['num'] + 1) : 1;
                $smsCode  = self::generateSmsCache($checkNum);
            }
            else
            {
                $smsCode = self::generateSmsCache();
            }

            Cache::put($mobile, json_encode($smsCode), self::CACHE_VALID);
            $msg    = "您的验证码是{$smsCode['code']}(三十分钟内有效)";
            $ret    = Message::sendSMS($mobile, $msg);
            $result = json_decode($ret, true);
            if ($result['code'] != 0)
            {
                throw new Exception('发送验证码失败' . $result['code']);
            }

            Log::info(__FUNCTION__ . "{$mobile}请求验证码,cache_info为:" . json_encode($smsCode));

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '获取验证码成功',
                'data' => '',
            ]);
        }
        catch (Exception $e)
        {
            Log::error(__FUNCTION__ . "{$mobile}请求验证失败" . $e->getMessage());

            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function save(Request $request)
    {
        try
        {
            $mobile  = $request->input('mobile');
            $version = $request->input('device');
            $inviter = $request->input('inviter');
            $smsCode = $request->input('code');
            if (empty($mobile))
            {
                throw new Exception('请填写手机号');
            }
            if (empty($smsCode))
            {
                throw new Exception('请填写验证码');
            }
            if (empty($version))
            {
                throw new Exception('系统版本异常');
            }
            $isOrdered = Orderuser::where('mobile', '=', $mobile)->get(['mobile']);
            if (!empty($isOrdered) && $isOrdered->first())
            {
                $session = new Session();
                $session->set('mobile', $mobile);
                $invitedCount = Orderuser::where('mobile', '=', $mobile)->get(['invite_count']);
                $info         = json_decode(json_encode($invitedCount), true);
                $invited      = $info[0]['invite_count'];

                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => '您已成功预约 快来邀请好友吧',
                    'data' => [
                        'invited' => $invited,
                    ],
                ]);
                exit;
            }
            $cacheVal = Cache::get($mobile);
            if (empty($cacheVal))
            {
                throw new Exception('请先获取验证码');
            }
            $cacheVal = json_decode($cacheVal, true);
            if ($cacheVal['code'] != $smsCode)
            {
                throw new Exception('验证码错误');
            }
            if (time() > $cacheVal['expireTime'])
            {
                throw new Exception('验证码已过期');
            }

            $user = new Orderuser();
            // 对邀请人进行统计操作
            if (!empty($inviter))
            {
                $inviterUser = Orderuser::where('mobile', '=', $inviter)->get(['invite_count']);
                if ($inviterUser->first())
                {
                    $info = json_decode(json_encode($inviterUser), true);

                    $count         = $info[0]['invite_count'] + 1;
                    $inviterRet    = Orderuser::where('mobile', '=', $inviter)->update(['invite_count' => $count]);
                    $user->inviter = $inviter;
                }
            }

            // 保存预约人手机号
            $user->mobile      = $mobile;
            $user->sys_version = $version;
            $OrderRet          = $user->save();
            Log::info(__FUNCTION__ . "{$mobile}验证成功,cache_info为:" . json_encode($smsCode));

            // 修改总预约人数
            try
            {
                $totalInfo = Usertotal::find(self::KEY);
                $weight    = $totalInfo->weight;
                $total     = $totalInfo->total + $weight;
                $ret       = Usertotal::where('id', '=', 1)->update(['total' => $total]);
            }
            catch (Exception $e)
            {
                Log::info('总人数增加异常');
            }

            // 保存session
            $session = new Session();
            $session->set('mobile', $mobile);

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '预约成功',
                'data' => $cacheVal,
            ]);
        }
        catch (Exception $e)
        {
            Log::error(__FUNCTION__ . "{$mobile}验证失败,cache_info为:" . json_encode($smsCode));

            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public static function generateSmsCache($num = 1)
    {
        return [
            'code'       => mt_rand(100000, 999999),
            'num'        => $num,
            'expireTime' => time() + self::CACHE_TIME,
            'curDayTime' => mktime(23, 59, 59, date('m'), date('d'), date('Y')),
        ];
    }

    public function weight(Request $request)
    {
        try
        {
            $weight = $request->input('weight');
            if (empty($weight))
            {
                throw new Exception('请输入权重');
            }

            $preWeight = Usertotal::find(self::KEY);
            $ret       = Usertotal::where('id', '=', self::KEY)->update(['weight' => $weight]);
            if (empty($ret))
            {
                throw new Exception('修改权重失败');
            }
            $pre = $preWeight->weight;
            Log::info("修改权重,从{$pre}改为{$weight}");

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "修改权重成功,从{$pre}改为{$weight}",
                'data' => [
                    'old' => $pre,
                    'new' => $weight,
                ],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function admin()
    {
        try
        {
            $admin = Usertotal::find(self::KEY);
            $weight = $admin->weight;
            $total = $admin->total;

            $trueTotal = Orderuser::where('id', '>', 0)->count();

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "当前权重为{$weight},总预约人数{$total},真实人数{$trueTotal}",
                'data' => [
                    'weight' => $weight,
                    'amount' => $total,
                    'amount_real' => $trueTotal
                ],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [
                    'weight' => '',
                    'amount' => '',
                    'amount_real' => ''
                ],
            ]);
        }
    }

    public function total()
    {
        try
        {
            $info = Usertotal::find(self::KEY);
            if (!empty($info) && $info->first())
            {
                $total = $info->total;
            }
            else
            {
                throw new Exception('获取总预约人数异常');
            }

            $session    = new Session();
            $sessionRet = $session->get('mobile');
            if (empty($sessionRet))
            {
                $mobile  = '';
                $invited = 0;
            }
            else
            {
                $mobile       = $sessionRet;
                $invitedCount = Orderuser::where('mobile', '=', $mobile)->get(['invite_count']);
                if ($invitedCount->first())
                {
                    $info         = json_decode(json_encode($invitedCount), true);
                    $invited      = $info[0]['invite_count'];
                }
                else
                {
                    $invited = 0;
                    $mobile = '';
                }
            }

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "获取成功",
                'data' => [
                    'amount'  => $total,
                    'invited' => $invited,
                    'mobile'  => $mobile,
                ],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function setSession(Request $request)
    {
        $mobile  = $request->input('mobile');
        $session = new Session();

        $session->set('mobile', $mobile);
    }

    public function getSession()
    {
        $session = new Session();
        $res     = $session->get('mobile');
        echo $res;
    }

    public function modifyTotal(Request $request)
    {
        try
        {
            $total = $request->input('total');
            if (empty($total))
            {
                throw new Exception('请输入要修改的预约总数');
            }

            $preTotal = Usertotal::find(self::KEY);
            $ret       = Usertotal::where('id', '=', self::KEY)->update(['total' => $total]);
            if (empty($ret))
            {
                throw new Exception('修改预约总数失败');
            }
            $pre = $preTotal->total;
            Log::info("修改预约总数,从{$pre}改为{$total}");

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "修改预约总数成功,从{$pre}改为{$total}",
                'data' => [
                    'old' => $pre,
                    'new' => $total,
                ],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function addTotal(Request $request)
    {
        try
        {
            $increase = $request->input('increase');
            if (empty($increase))
            {
                throw new Exception('请输入要增加的预约数');
            }

            $pre = Usertotal::find(self::KEY);
            $preTotal     = $pre->total;
            $afterTotal = $preTotal + $increase;
            $ret       = Usertotal::where('id', '=', self::KEY)->update(['total' => $afterTotal]);
            if (empty($ret))
            {
                throw new Exception('增加预约数失败');
            }
            Log::info("增加预约数,从{$preTotal}改为{$afterTotal}");

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "增加预约数成功,从{$preTotal}改为{$afterTotal}",
                'data' => [
                    'old' => $preTotal,
                    'new' => $afterTotal,
                ],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function deleteUser(Request $request)
    {
        $mobile = $request->input('mobile');
        if ($mobile == 'root')
        {
            $res = Orderuser::where('id', '>', 0)->delete();
            if (!empty($res))
            {
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "删除成功{$res}条",
                    'data' => [],
                ]);
            }
        }
        else
        {
            $res = Orderuser::where('mobile', '=', $mobile)->delete();
            if (!empty($res))
            {
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "删除成功{$res}条",
                    'data' => [],
                ]);
            }
        }
        return response()->json([
            'code' => config('ajcode.error'),
            'msg'  => "已全部删除",
            'data' => [],
        ]);
    }
}
