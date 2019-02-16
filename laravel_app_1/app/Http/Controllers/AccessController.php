<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/11/30 下午11:00
 */
namespace App\Http\Controllers;

use App;
use Egulias\EmailValidator\Warning\DomainTooLong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Check;
use League\Flysystem\Exception;
use Illuminate\Support\Facades\Cache;
use App\Message;
use App\Userinfo;
use App\Access;
use App\Gifts;
use App\Keyword;
use App\Questions;
use App\Tool;
use App\Field;

class AccessController extends Controller
{
    const CACHE_NUM         = 10;
    const CACHE_TIME        = 300;
    const CACHE_VALID       = 1440;
    const CACHE_TOKEN       = 120;
    const CACHE_MOBILE      = 5;
    const MOBILE_BIND       = 1;
    const MOBILE_CHANGE     = 2;
    const STATUS_NOT_ASSIGN = 0;
    const STATUS_ASSIGNED   = 1;
    const MENU_URL          = 'https://api.weixin.qq.com/cgi-bin/menu/create';
    const ACCESS_TOCKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';

    public function access(Request $request)
    {
//        $echostr = $request->input('echostr');
//        $signature = $request->input('signature');
//        $timestamp = $request->input('timestamp');
//        $nonce = $request->input('nonce');
//        $token = config('wx.token');
//        $list = [$token, $timestamp, $nonce];
//        sort($list);
//        $sign = sha1(implode($list));
//
//        return $sign == $signature ? $echostr : '';
        try
        {
//            $userInfo = Userinfo::where('apple_id', '=', 'osU-uw1grlZEe5XB3VGi8kDRzUGw')->get();
//            $info =json_decode(json_encode($userInfo),true);
//            var_dump($info);die;
//            $userInfo = new Userinfo();
//
//            $res = $userInfo::where('apple_id', '=', 1)->get();
//            var_dump($res->first());
//            $userInfo = Userinfo::where('apple_id', '=', '1')->update(['code' => '2']);
//            var_dump($userInfo);die;

//            $userInfo->apple_id = 1;
//            $userInfo->mobile = 2;
//            $userInfo->save();die;
            //get post data, May be due to the different environments
//        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//php:input
            $textTpl = "<xml>
                       <ToUserName><![CDATA[%s]]></ToUserName>
                       <FromUserName><![CDATA[%s]]></FromUserName>
                       <CreateTime>%s</CreateTime>
                       <MsgType><![CDATA[%s]]></MsgType>
                       <Content><![CDATA[%s]]></Content>
                       <FuncFlag>0</FuncFlag>
                       </xml>";

            $picTpl = "
            <xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[news]]></MsgType>
                <ArticleCount>%s</ArticleCount>
                <Articles>
                    %s
                </Articles>
            </xml>
            ";
            $tpl = "
            <xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[news]]></MsgType>
                <ArticleCount>%s</ArticleCount>
                <Articles>
                    <item>
                        <Title><![CDATA[%s]]></Title> 
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                    </item>
                </Articles>
            </xml>
            ";
            $itemPicTpl = "
                <item>
                        <Title><![CDATA[%s]]></Title> 
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                </item>
            ";

            $postStr = file_get_contents("php://input");
            //写入日志  在同级目录下建立php_log.txt
            //chmod 777php_log.txt(赋权) chown wwwphp_log.txt(修改主)
            Log::info("记录" . json_encode($postStr));
            //日志图片

            //extract post data
            if (!empty($postStr))
            {
                /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
                   the best way is to check the validity of xml by yourself */
                libxml_disable_entity_loader(true);
                $postObj      = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername   = $postObj->ToUserName;
                $keyword      = trim($postObj->Content);
                Log::info("{$fromUsername}用户发送内容为{$keyword}");

                if ($postObj->Event == 'CLICK')
                {
                    $key           = $postObj->EventKey;
                    $clickResponse = '';

                    // 点击绑定手机号
                    if ($key == 'taset_cell_phone')
                    {
                        $userInfo  = Userinfo::where('apple_id', '=', $fromUsername)->get(['mobile']);
                        $mobileKey = "mobile_{$fromUsername}";
                        if ($userInfo->first())
                        {
                            $info   = json_decode(json_encode($userInfo), true);
                            $mobile = substr_replace($info[0]['mobile'], '****', 3, 4);
                            Cache::put($mobileKey, self::MOBILE_CHANGE, self::CACHE_MOBILE);
                            $clickResponse = "您的微信账号已经被:{$mobile}绑定。请勿重复绑定哦，如果您需要更换绑定号码，请重新输入手机号码（5分钟之内输入才有效哦）";
                        }
                        else
                        {
                            Cache::put($mobileKey, self::MOBILE_BIND, self::CACHE_MOBILE);
                            $clickResponse = '请输入手机号码绑定手机(5分钟之内输入才有效哦)。';
                        }
                    }

                    // 点击签到
                    elseif ($key == 'taset_sign_in')
                    {
                        $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'sign_at']);
                        if ($userInfo->first())
                        {
                            $info   = json_decode(json_encode($userInfo), true);
                            $signAt = $info[0]['sign_at'];
                            if ($signAt > strtotime(date('Y-m-d')))
                            {
                                $clickResponse = "亲爱的玩家，您今天已经完成签到了哦，请明天再来吧！";
                            }
                            else
                            {
                                $bonus         = $info[0]['bonus'] + 3;
                                $user          = Userinfo::where('apple_id', '=', $fromUsername)->update(['bonus' => $bonus, 'sign_at' => time()]);
                                $clickResponse = "亲爱的玩家，恭喜签到成功，获取3积分作为奖励。积分可用于参加奖励丰厚的福利活动哟~";
                            }
                        }
                        else
                        {
                            $clickResponse = "请先绑定手机号";
                        }
                    }

                    // 点击每日问答
                    elseif ($key == 'taset_daily_ask')
                    {
                        $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'sign_at']);
                        if ($userInfo->first())
                        {
                            $date     = date('Ymd');
                            $question = Questions::where('date', '=', $date)->get(['date', 'content', 'answer']);
                            if ($question->first())
                            {
                                $info            = json_decode(json_encode($question), true);
                                $questionContent = $info[0]['content'];
                                $questionAnswer  = $info[0]['answer'];
                                $questionKey     = "key_{$date}";
                                //TODO 读取题目数据库
                                $clickResponse = ($questionContent);

                                //TODO 设置问题答案
                                Cache::put($questionKey, $questionAnswer, self::CACHE_VALID);
                            }
                            else
                            {
                                $clickResponse = "今日没有题目";
                            }
                        }
                        else
                        {
                            $clickResponse = "请先绑定手机号";
                        }
                    }

                    // 积分查询
                    elseif ($key == 'taset_bonus_query')
                    {
                        $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'sign_at']);
                        if ($userInfo->first())
                        {
                            $info          = json_decode(json_encode($userInfo), true);
                            $bonus         = $info[0]['bonus'];
                            $clickResponse = "亲爱的玩家，您当前的总积分为：{$bonus}";
                        }
                        else
                        {
                            $clickResponse = "请先绑定手机号";
                        }
                    }
                    else
                    {
                        $tasetRet = Keyword::where('keyword', '=', $key)->get(['reply', 'type']);
                        if ($tasetRet->first())
                        {
                            $tasetInfo  = json_decode(json_encode($tasetRet), true);
                            $tasetType  = $tasetInfo[0]['type'];
                            $tasetReply = $tasetInfo[0]['reply'];

                            // Click关键词
                            if ($tasetType == Field::KEYWORD_TYPE_TASET)
                            {
                                $tasetDecode = json_decode($tasetReply, true);
                                foreach ($tasetDecode as $item)
                                {
                                    $msgType    = $item['type'];
                                    $contentStr = $item['value'];
                                    Log::info("{$fromUsername}");
                                    if ($msgType == 'text')
                                    {
                                        self::sendMsg($fromUsername, $msgType, $contentStr);
                                    }
                                    elseif ($msgType == 'news')
                                    {
                                        $count = count($contentStr);
                                        $itemPicRes = '';
                                        foreach ($contentStr as $news)
                                        {
                                            $title = $news['title'];
                                            $description = $news['desc'];
                                            $picUrl = $news['pic'];
                                            $url = $news['url'];
                                            $itemPicRes .= sprintf($itemPicTpl, $title, $description, $picUrl, $url);
                                        }
                                        $picRes = sprintf($picTpl, $fromUsername, $toUsername, time(), $count, $itemPicRes);
                                        Log::info($picRes);
                                        echo $picRes;
                                    }
                                }
                                exit;
                            }
                        }
                    }

                    // 领取礼包
//                    elseif ($key == 'taset_gift_get')
//                    {
//                        $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['gift']);
//                        if ($userInfo->first())
//                        {
//                            $info = json_decode(json_encode($userInfo), true);
//                            $gift = $info[0]['gift'];
//                            if (!empty($gift))
//                            {
//                                $clickResponse = "您已经领取过礼包，礼包码为{$gift}";
//                            }
//                            else
//                            {
//                                $giftCode = Gifts::where('status', '=', self::STATUS_NOT_ASSIGN)->take(1)->get(['gift_id']);
//                                if ($giftCode->first())
//                                {
//                                    $giftInfo      = json_decode(json_encode($giftCode), true);
//                                    $giftId        = $giftInfo[0]['gift_id'];
//                                    $user          = Gifts::where('gift_id', '=', $giftId)->update([
//                                        'status'      => self::STATUS_ASSIGNED,
//                                        'apple_id'    => $fromUsername,
//                                        'assign_time' => time()]);
//                                    $user          = Userinfo::where('apple_id', '=', $fromUsername)->update(['gift' => $giftId]);
//                                    $clickResponse = "获得礼包码为{$giftId}";
//                                }
//                                else
//                                {
//                                    $clickResponse = "没有可以获取的礼包码";
//                                }
//                            }
//                        }
//                        else
//                        {
//                            $clickResponse = "请先绑定手机号";
//                        }
//                    }

                    $msgType    = "text";
                    $contentStr = $clickResponse;
                    Log::info("{$fromUsername}{$contentStr}");
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, time(), $msgType, $contentStr);
                    echo $resultStr;
                    exit;
                }

                // 手机号
                if (preg_match('/^\d{11}$/', $keyword))
                {
                    $mobileKey = "mobile_{$fromUsername}";
                    if (Cache::has($mobileKey))
                    {
                        $mobileCache = Cache::get($mobileKey);
                        $userInfo    = Userinfo::where('apple_id', '=', $fromUsername)->get(['mobile']);
                        if ($userInfo->first())
                        {
                            $info      = json_decode(json_encode($userInfo), true);
                            $oldMobile = $info[0]['mobile'];
                        }
                        else
                        {
                            $oldMobile = '';
                        }

                        if ($mobileCache == self::MOBILE_CHANGE && !empty($oldMobile) && $oldMobile == $keyword)
                        {
                            $responseMsg = "该手机号已经绑定，不必更换";
                        }
                        else
                        {
                            if (Cache::has($fromUsername))
                            {
                                $oldCache = json_decode(Cache::get($fromUsername), true);
                                if ($oldCache['num'] > self::CACHE_NUM && time() < $oldCache['curDayTime'])
                                {
                                    throw new Exception('同一手机号码验证次数过多');
                                }

                                // 验证码过期，则重新生成；验证码不过期，那发送原来的验证码
                                $checkNum = time() < $oldCache['curDayTime'] ? ($oldCache['num'] + 1) : 1;
                                $smsCode  = self::generateSmsCache($keyword, $checkNum);
                            }
                            else
                            {
                                $smsCode = self::generateSmsCache($keyword);
                            }

                            Cache::put($fromUsername, json_encode($smsCode), self::CACHE_VALID);
                            Log::info('缓存数据' . json_encode($smsCode));
                            $msg = "您的验证码是{$smsCode['code']}(5分钟内有效)";
                            $ret = Message::sendSMS($keyword, $msg);
                            Log::info("{$fromUsername}验证码{$ret}");
                            $responseMsg = "请输入手机验证码进行校验(5分钟之内输入才有效哦)。";
                        }
                    }
                    else
                    {
                        $responseMsg = "请先点击绑定手机号";
                    }
                }

                // 验证码
                elseif (preg_match('/^\d{6}$/', $keyword))
                {
                    if (Cache::has($fromUsername))
                    {
                        $oldCache = json_decode(Cache::get($fromUsername), true);
                        if ($oldCache['code'] != $keyword)
                        {
                            $responseMsg = "验证码错误";
                        }
                        // TODO 成功并存数据库
                        else
                        {
                            if (time() > $oldCache['expireTime'])
                            {
                                $responseMsg = "验证码已过期";
                            }
                            else
                            {
                                $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['mobile']);
                                if ($userInfo->first())
                                {
                                    $user        = Userinfo::where('apple_id', '=', $fromUsername)->update(['mobile' => $oldCache['mobile'], 'code' => json_encode($oldCache)]);
                                    $responseMsg = "恭喜您！手机更换绑定成功。";
                                }
                                else
                                {
                                    $userInfo           = new Userinfo();
                                    $userInfo->apple_id = $fromUsername;
                                    $userInfo->mobile   = $oldCache['mobile'];
                                    $userInfo->code     = json_encode($oldCache);
                                    $userInfo->save();
                                    $responseMsg = "恭喜您！手机绑定成功。";
                                }
                            }
                        }
                    }
                    else
                    {
                        $responseMsg = "请先输入手机号";
                    }
                }
                elseif (preg_match('/^sj.*?[a|b|c|d]$/i', $keyword))
                {
                    $questionKey = 'key_' . date('Ymd');
                    if (Cache::has($questionKey))
                    {
                        $answerVal   = Cache::get($questionKey);
                        $pregStr     = "/^sj.*?[{$answerVal}]$/i";
                        if (preg_match($pregStr, $keyword))
                        {
                            $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'answer_at']);
                            if ($userInfo->first())
                            {
                                $info     = json_decode(json_encode($userInfo), true);
                                $answerAt = $info[0]['answer_at'];
                                if ($answerAt > strtotime(date('Y-m-d')))
                                {
                                    $responseMsg = "您已回答过该问题";
                                }
                                else
                                {
                                    $bonus       = $info[0]['bonus'] + 3;
                                    $user        = Userinfo::where('apple_id', '=', $fromUsername)->update(['bonus' => $bonus, 'answer_at' => time()]);
                                    $responseMsg = "回答正确，积分+3，总积分{$bonus}";
                                }
                            }
                            else
                            {
                                $responseMsg = "请先绑定手机号";
                            }
                        }
                        else
                        {
                            $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'answer_at']);
                            if ($userInfo->first())
                            {
                                $info     = json_decode(json_encode($userInfo), true);
                                $answerAt = $info[0]['answer_at'];
                                if ($answerAt > strtotime(date('Y-m-d')))
                                {
                                    $responseMsg = "您已回答过该问题";
                                }
                                else
                                {
                                    $bonus       = $info[0]['bonus'] + 2;
                                    $user        = Userinfo::where('apple_id', '=', $fromUsername)->update(['bonus' => $bonus, 'answer_at' => time()]);
                                    $responseMsg = "回答错误，积分+2，总积分{$bonus}";
                                }
                            }
                            else
                            {
                                $responseMsg = "请先绑定手机号";
                            }
                        }
                    }
                    else
                    {
                        $responseMsg = "问题没有答案";
                    }

                }
//                elseif ($keyword == "签到")
//                {
//                    $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['bonus', 'ext']);
//                    if ($userInfo->first())
//                    {
//                        $info = json_decode(json_encode($userInfo), true);
//                        $ext  = $info[0]['ext'];
//                        if ($ext > strtotime(date('Y-m-d')))
//                        {
//                            $responseMsg = "您已签过到";
//                        }
//                        else
//                        {
//                            $bonus       = $info[0]['bonus'] + 2;
//                            $user        = Userinfo::where('apple_id', '=', $fromUsername)->update(['bonus' => $bonus, 'ext' => time()]);
//                            $responseMsg = "签到成功积分+2, 总积分{$bonus}";
//                        }
//                    }
//                    else
//                    {
//                        $responseMsg = "请先绑定手机号";
//                    }
//                }
                else
                {
                    $keyRet = Keyword::where('keyword', '=', $keyword)->get(['reply', 'type']);
                    if ($keyRet->first())
                    {
                        $info  = json_decode(json_encode($keyRet), true);
                        $type  = $info[0]['type'];
                        $reply = $info[0]['reply'];

                        // 自动回复关键词
                        if ($type == Field::KEYWORD_TYPE_REPLY)
                        {
                            $replyJsonDecode = json_decode($reply, true);
                            foreach ($replyJsonDecode as $item)
                            {
                                $msgType    = $item['type'];
                                $contentStr = $item['value'];
                                Log::info("{$fromUsername}");
                                if ($msgType == 'text')
                                {
                                    self::sendMsg($fromUsername, $msgType, $contentStr);
                                }
                                elseif ($msgType == 'news')
                                {
//                                    $contentStr = [
//                                        [
//                                            'title' => "title1",
//                                            'description' => "description1",
//                                            'picurl' => "https://www.baidu.com/img/bd_logo1.png",
//                                            'url' => "https://www.baidu.com",
//                                        ],
//                                        [
//                                            'title' => "title2",
//                                            'description' => "description2",
//                                            'picurl' => "https://www.baidu.com/img/bd_logo1.png",
//                                            'url' => "https://www.baidu.com",
//                                        ],
//                                    ];
                                    $count = count($contentStr);
                                    $itemPicRes = '';
                                    foreach ($contentStr as $news)
                                    {
                                        $title = $news['title'];
                                        $description = $news['desc'];
                                        $picUrl = $news['pic'];
                                        $url = $news['url'];
                                        $itemPicRes .= sprintf($itemPicTpl, $title, $description, $picUrl, $url);
                                    }
                                    $picRes = sprintf($picTpl, $fromUsername, $toUsername, time(), $count, $itemPicRes);
                                    Log::info($picRes);
                                    echo $picRes;
                                }
                            }
                            exit;
                        }
                        // 礼包码关键词
                        elseif ($type == Field::KEYWORD_TYPE_GIFT)
                        {
                            $userInfo = Userinfo::where('apple_id', '=', $fromUsername)->get(['gift']);
                            if ($userInfo->first())
                            {
                                $giftInfo = Gifts::where([
                                    'apple_id' => $fromUsername,
                                    'type'     => $keyword,
                                ])->get(['gift_id', 'status']);

                                if ($giftInfo->first())
                                {
                                    $responseMsg = '您已经领取过该活动的礼包码';
                                }
                                else
                                {
                                    $giftCode = Gifts::where([
                                        'status' => self::STATUS_NOT_ASSIGN,
                                        'type'   => $keyword,
                                    ])->take(1)->get(['gift_id']);
                                    if ($giftCode->first())
                                    {
                                        $giftInfo = json_decode(json_encode($giftCode), true);
                                        $giftId   = $giftInfo[0]['gift_id'];
                                        $user     = Gifts::where('gift_id', '=', $giftId)->update([
                                            'status'      => self::STATUS_ASSIGNED,
                                            'apple_id'    => $fromUsername,
                                            'assign_time' => time()]);

                                        $info        = json_decode(json_encode($userInfo), true);
                                        $gift        = $info[0]['gift'];
                                        $giftArr     = json_decode($gift, true);
                                        $giftArr[]   = $giftId;
                                        $gift        = json_encode($giftArr);
                                        $user        = Userinfo::where('apple_id', '=', $fromUsername)->update(['gift' => $gift]);
                                        $responseMsg = "感谢您参与{$reply}，您的礼包码为：{$giftId}";
                                    }
                                    else
                                    {
                                        $responseMsg = "{$keyword}已领取完毕";
                                    }
                                }
                            }
                            else
                            {
                                $responseMsg = "请先绑定手机号";
                            }
                        }
                    }
                    else
                    {
                        $responseMsg = "游戏现在还处于预约阶段哦~公布测试信息的时候小二会在公众号告诉大家哒";
                    }
                }

                $time = time();
                //订阅事件
                if ($postObj->Event == "subscribe")
                {
                    $tasetRet = Keyword::where('keyword', '=', 'follow_reply')->get(['reply', 'type']);
                    if ($tasetRet->first())
                    {
                        $tasetInfo  = json_decode(json_encode($tasetRet), true);
                        $tasetType  = $tasetInfo[0]['type'];
                        $tasetReply = $tasetInfo[0]['reply'];

                        $tasetDecode = json_decode($tasetReply, true);
                        foreach ($tasetDecode as $item)
                        {
                            $msgType    = $item['type'];
                            $contentStr = $item['value'];
                            Log::info("{$fromUsername}");
                            if ($msgType == 'text')
                            {
                                self::sendMsg($fromUsername, $msgType, $contentStr);
                            }
                            elseif ($msgType == 'news')
                            {
                                $count = count($contentStr);
                                $itemPicRes = '';
                                foreach ($contentStr as $news)
                                {
                                    $title = $news['title'];
                                    $description = $news['desc'];
                                    $picUrl = $news['pic'];
                                    $url = $news['url'];
                                    $itemPicRes .= sprintf($itemPicTpl, $title, $description, $picUrl, $url);
                                }
                                $picRes = sprintf($picTpl, $fromUsername, $toUsername, time(), $count, $itemPicRes);
                                Log::info($picRes);
                                echo $picRes;
                            }
                        }
                        exit;
                    }
                    else
                    {
                        $msgType    = "text";
                        $contentStr = "食于舌尖，游于指尖。终于等到你~感谢你关注【舌尖正版手游】，猛戳<a href ='http://sj.zhengyueyinhe.com/reservation?mid=1015'>立即预约</a>与千万吃货共赴美味珍馐的饕餮盛宴吧~";
                        Log::info("{$fromUsername}订阅公众号");
                        $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    }
                    echo $resultStr;
                }

                //语音识别
                if ($postObj->MsgType == "voice")
                {
                    $msgType    = "text";
                    $contentStr = trim($postObj->Recognition, "。");
                    Log::info("{$fromUsername}发送语音");
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
                }

                //自动回复
                if (!empty($keyword))
                {
                    $msgType    = "text";
                    $contentStr = $responseMsg;
                    Log::info("{$fromUsername}{$contentStr}");
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
                }
                else
                {
                    echo "Input something...";
                }
            }
            else
            {
                echo "";
                exit;
            }
        }
        catch (Exception $e)
        {
            Log::info("异常：" . $e->getMessage());
        }
    }

    public static function generateSmsCache($mobile, $num = 1)
    {
        return [
            'mobile'     => $mobile,
            'code'       => mt_rand(100000, 999999),
            'num'        => $num,
            'time'       => time(),
            'expireTime' => time() + self::CACHE_TIME,
            'curDayTime' => mktime(23, 59, 59, date('m'), date('d'), date('Y')),
        ];
    }

    public function export()
    {
        $fileName = "user_info.csv";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);

        $userInfos = Userinfo::get(['mobile', 'bonus']);
        $userList  = json_decode(json_encode($userInfos), true);

        $title = iconv('utf-8', 'gbk', "电话,积分\n");
        echo $title;
        foreach ($userList as $item)
        {
            $content = iconv('utf-8', 'gbk', "{$item['mobile']},{$item['bonus']}\n");
            echo $content;
        }
    }

    public function menu(Request $request)
    {
        try
        {
            if (Cache::has('menu'))
            {
                $accessToken = Cache::get('menu');
            }
            else
            {
                $accessToken = Access::getAccessToken();
                if (empty($accessToken))
                {
                    throw new Exception('accessToken获取失败');
                }
                Cache::put('menu', $accessToken, self::CACHE_TOKEN);
            }

            $menu  = $request->input('menu');
            $debug = $request->input('debug', '');
            if (empty($menu))
            {
                throw new Exception('请填写菜单json');
            }
            Log::info("菜单的json为{$menu}");
            $menuJson = json_encode(json_decode($menu, true), JSON_UNESCAPED_UNICODE);
            $arr      = [
                'button' => [
                    [
                        'type' => 'click',
                        'name' => '绑定手机号',
                        'key'  => 'taset_cell_phone'
                    ],
                    [
                        'type' => 'click',
                        'name' => '签到',
                        'key'  => 'taset_sign_in'
                    ],
                    [
                        'name'       => '福利',
                        'sub_button' => [
                            [
                                'type' => 'click',
                                'name' => '每日问答',
                                'key'  => 'taset_daily_ask'
                            ],
//                            [
//                                'type' => 'click',
//                                'name' => '领取礼包',
//                                'key'  => 'taset_gift_get'
//                            ],
                            [
                                'type' => 'click',
                                'name' => '积分查询',
                                'key'  => 'taset_bonus_query'
                            ],
                        ],
                    ],

                ]
            ];
            if ($debug == 'sj')
            {
                $menuJson = json_encode($arr, JSON_UNESCAPED_UNICODE);
            }
            $opts    = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $menuJson
                ]
            ];
            $context = stream_context_create($opts);
            $url     = self::MENU_URL . '?access_token=' . $accessToken;
            $result  = file_get_contents($url, false, $context);
            Log::info("create自定义菜单{$result}");

//            $url         = self::MENU_URL . '?access_token=' . $accessToken;
//            $jsonMenu    = '{"button":[{"type":"click","name":"绑定手机号","key":"taset_cell_phone"},{"type":"click","name":"签到","key":"taset_sign_in"},{"type":"click","name":"每日问答","key":"taset_daily_ask"}]}';
//            $result      = Tool::https_request($url, $jsonMenu);
//            Log::info("create自定义菜单{$result}");

            return $result;
        }
        catch (Exception $e)
        {
            Log::info("异常：" . $e->getMessage());

            return response()->json([
                'code' => config('ajcode.error'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function getAutoReply()
    {
        try
        {
            if (Cache::has('menu'))
            {
                $accessToken = Cache::get('menu');
            }
            else
            {
                $accessToken = Access::getAccessToken();
                if (empty($accessToken))
                {
                    throw new Exception('accessToken获取失败');
                }
                Cache::put('menu', $accessToken, self::CACHE_TOKEN);
            }

            $opts    = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                ]
            ];
            $context = stream_context_create($opts);
            $url     = "https://api.weixin.qq.com/cgi-bin/get_current_autoreply_info?access_token={$accessToken}";
            $result  = file_get_contents($url, false, $context);

            return $result;
        }
        catch (Exception $e)
        {
            Log::info("异常：" . $e->getMessage());

            return false;
        }
    }

    public function addClient()
    {
        if (Cache::has('menu'))
        {
            $accessToken = Cache::get('menu');
        }
        else
        {
            $accessToken = Access::getAccessToken();
            if (empty($accessToken))
            {
                throw new Exception('accessToken获取失败');
            }
            Cache::put('menu', $accessToken, self::CACHE_TOKEN);
        }
        $account = [
            'kf_acount' => 'test@SheJianShouYou',
            'nickname' => '客服',
            'password' => 'jinganghuyu',
        ];
        $opts    = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => json_encode($account)
            ]
        ];
        $context = stream_context_create($opts);
        $url     = 'https://api.weixin.qq.com/customservice/kfaccount/add?access_token=' . $accessToken;
        $result  = file_get_contents($url, false, $context);
        var_dump($result);
    }

    public static function sendMsg($userId, $msgType, $msgVal)
    {
        if (Cache::has('send'))
        {
            $accessToken = Cache::get('send');
        }
        else
        {
            $accessToken = Access::getAccessToken();
            if (empty($accessToken))
            {
                throw new Exception('accessToken获取失败');
            }
            Cache::put('send', $accessToken, self::CACHE_TOKEN);
        }
        $msg = [
            "touser" => "{$userId}",
            "msgtype" => "{$msgType}",
            "{$msgType}" => [
                "content" => $msgVal,
            ],
        ];
        $opts    = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => json_encode($msg, JSON_UNESCAPED_UNICODE)
            ]
        ];
        $context = stream_context_create($opts);
        $url     = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $accessToken;
        $result  = file_get_contents($url, false, $context);

        Log::info("客服消息" . $result);
    }
}
