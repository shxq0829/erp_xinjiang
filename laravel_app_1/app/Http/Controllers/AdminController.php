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
use Illuminate\Support\Facades\Storage;
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

class AdminController extends Controller
{
    const CACHE_TOKEN       = 120;

    public function index()
    {
        $user = $this->_checkAdminUser();
        if (!$user)
        {
            $user = '';
        }
        return view('admin', [
            'name' => $user,
        ]);
    }

    // POST 请求
    public function login(Request $request)
    {
        try
        {
            $userName = $request->input('u', '');
            $password = $request->input('p', '');

            if (empty($userName) || empty($password))
            {
                throw new Exception('请填写用户名和密码');
            }

            $user = Tool::loginUser($userName, $password);
            if (empty($user))
            {
                throw new Exception('用户未注册');
            }

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '登录成功',
                'data' => [],
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

    public function signIn(Request $request)
    {
        try
        {
            $userName = $request->input('u', '');
            $password = $request->input('p', '');
            $name     = $request->input('name', '');
            $mobile   = $request->input('mobile', '');

            if (empty($userName) || empty($password) || empty($name) || empty($mobile))
            {
                throw new Exception('请填写完整信息');
            }

            $signRet = Tool::signInUser($userName, $password, $name, $mobile);
            if (!$signRet)
            {
                throw new Exception('注册失败');
            }

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '注册成功',
                'data' => [],
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

    public function uploadFile(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

//            if ($request->isMethod('POST'))
//            {
                $id = $request->input('id', '');
                if (empty($id))
                {
                    $key = $request->input('keyword', '');
                    $expiredKey = $request->input('reply_word', '');
                    $file = $request->file('source');
                    if (empty($key) || empty($expiredKey))
                    {
                        throw new Exception('请填写礼包码标题和过期文案');
                    }
                    if (!$file->isValid())
                    {
                        throw new Exception('文件上传失败');
                    }

                    // 保存关键词
                    $giftKey    = Keyword::where([
                        'type' => Field::KEYWORD_TYPE_GIFT,
                    ])->get(['keyword']);
                    $giftKeyArr = [];
                    foreach ($giftKey as $gift)
                    {
                        $giftKeyArr[] = $gift->keyword;
                    }

                    if (!in_array($key, $giftKeyArr))
                    {

                        $keyword          = new Keyword();
                        $keyword->type    = Field::KEYWORD_TYPE_GIFT;
                        $keyword->keyword = $key;
                        $keyword->reply   = $expiredKey;
                        $ret              = $keyword->save();
                        if (!$ret)
                        {
                            throw new Exception('保存礼包码关键词失败');
                        }
                    }
                    else
                    {
                        throw new Exception('该关键词已存在');
                    }


                    //判断文件是否上传成功
                    if ($file->isValid())
                    {
                        //获取原文件名
                        $originalName = $file->getClientOriginalName();
                        //扩展名
                        $ext = $file->getClientOriginalExtension();
                        //文件类型
                        $type = $file->getClientMimeType();
                        //临时绝对路径
                        $realPath = $file->getRealPath();

//                    $filename = date('Y-m-d-H-i-S') . '-' . uniqid() . '-' . $ext;
//                    $bool = Storage::disk('uploads')->put($filename, file_get_contents($realPath));

                        if ($ext != 'csv')
                        {
                            throw new Exception('仅支持csv格式');
                        }

                        $str = file_get_contents($realPath);
                        if (preg_match("/\r\n/", $str))
                        {
                            $arr = explode("\r\n", $str);
                        }
                        elseif (preg_match("/\r/", $str))
                        {
                            $arr = explode("\r", $str);
                        }
                        elseif (preg_match("/\n/", $str))
                        {
                            $arr = explode("\n", $str);
                        }
                        else
                        {
                            throw new Exception('数据格式有误');
                        }

                        $queryItem = [];
                        foreach ($arr as $item)
                        {
                            if (empty($item) && in_array($item, $queryItem))
                            {
                                continue;
                            }
                            $input[] = [
                                'gift_id' => $item,
                                'type' => $key,
                            ];
                            $queryItem[] = $item;
                        }
                        $mulRet = Gifts::whereIn('gift_id', $queryItem)->get();
                        if ($mulRet->first())
                        {
                            foreach ($mulRet as $item)
                            {
                                $existGiftIds[] = $item->gift_id;
                            }
                            foreach ($input as $k => $v)
                            {
                                if (in_array($v['gift_id'], $existGiftIds))
                                {
                                    unset($input[$k]);
                                }
                            }
                            if (count($input) == 0)
                            {
                                throw new Exception("文件中的礼包码全部重复，不可再添加");
                            }
                        }
                        $giftObj = new Gifts();
                        foreach ($input as $item)
                        {
                            $insert[] = $item;
                        }
                        $res = $giftObj::insert($insert);
                        if (!$res)
                        {
                            throw new Exception('添加礼包码失败');
                        }

                        return response()->json([
                            'code' => config('ajcode.succ'),
                            'msg'  => "添加礼包码成功，共添加个数为：" . count($input),
                            'data' => [],
                        ]);
                    }
                    else
                    {
                        throw new Exception('文件上传失败');
                    }
                }
                else
                {
                    $exist = Keyword::where('id', '=', $id)->get(['keyword', 'reply', 'type']);
                    $expiredKey = $request->input('reply_word', '');
                    $file = $request->file('source');
                    if (empty($expiredKey))
                    {
                        throw new Exception('缺少过期回复文案');
                    }

                    if ($exist->first())
                    {
                        // 更新自动回复
                        $oldKey   = json_decode(json_encode($exist), true);
                        $oldKeyword = $oldKey[0]['keyword'];
                        $oldReply = $oldKey[0]['reply'];
                        $upReply = Keyword::where('id', '=', $id)->update([
                            'reply' => $expiredKey,
                        ]);
                        if (!$upReply)
                        {
                            throw new Exception('更新礼包码过期文案失败');
                        }
                        Log::info("{$user} 编辑关键词自动回复 {$id} => {$expiredKey}");
                    }
                    else
                    {
                        throw new Exception('id没有对应的关键词来进行更改');
                    }

                    if (!empty($file))
                    {
                        if ($file->isValid())
                        {
                            //扩展名
                            $ext = $file->getClientOriginalExtension();
                            //临时绝对路径
                            $realPath = $file->getRealPath();

                            if ($ext != 'csv')
                            {
                                throw new Exception('仅支持csv格式');
                            }

                            $str = file_get_contents($realPath);
                            if (preg_match("/\r\n/", $str))
                            {
                                $arr = explode("\r\n", $str);
                            }
                            elseif (preg_match("/\r/", $str))
                            {
                                $arr = explode("\r", $str);
                            }
                            elseif (preg_match("/\n/", $str))
                            {
                                $arr = explode("\n", $str);
                            }
                            else
                            {
                                throw new Exception('数据格式有误');
                            }
                            $queryItem = [];
                            foreach ($arr as $item)
                            {
                                if (empty($item) && in_array($item, $queryItem))
                                {
                                    continue;
                                }
                                $input[] = [
                                    'gift_id' => $item,
                                    'type' => $oldKeyword,
                                ];
                                $queryItem[] = $item;
                            }
                            $mulRet = Gifts::whereIn('gift_id', $queryItem)->get();
                            if ($mulRet->first())
                            {
                                foreach ($mulRet as $item)
                                {
                                    $existGiftIds[] = $item->gift_id;
                                }
                                foreach ($input as $k => $v)
                                {
                                    if (in_array($v['gift_id'], $existGiftIds))
                                    {
                                        unset($input[$k]);
                                    }
                                }
                                if (count($input) == 0)
                                {
                                    throw new Exception("文件中的礼包码全部重复，不可再添加");
                                }
                            }

                            $giftObj = new Gifts();
                            try
                            {
                                foreach ($input as $item)
                                {
                                    $insert[] = $item;
                                }
                                $res = $giftObj::insert($insert);
                            }
                            catch (Exception $e)
                            {
                                throw new Exception('添加礼包码失败');
                            }
                            if (!$res)
                            {
                                throw new Exception('添加礼包码失败');
                            }

                            return response()->json([
                                'code' => config('ajcode.succ'),
                                'msg'  => "添加礼包码成功，共添加个数为：" . count($input),
                                'data' => [],
                            ]);
                        }
                    }



                    return response()->json([
                        'code' => config('ajcode.succ'),
                        'msg'  => "success",
                        'data' => [],
                    ]);

                }

//            }
//            else
//            {
//                throw new Exception('必须为post请求');
//            }
//            return view('test.upload');
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function getGiftKeywordList()
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $exist = Keyword::where([
                'type'    => Field::KEYWORD_TYPE_GIFT,
            ])->get(['id', 'keyword', 'reply']);
            if ($exist->first())
            {
                $list = [];
                foreach ($exist as $item)
                {
                    $list[] = [
                        'id' => $item->id,
                        'keyword' => $item->keyword,
                        'reply_word' => $item->reply,
                    ];
                }
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "succ",
                    'data' => [
                        'list' => $list,
                    ],
                ]);
            }
            else
            {
                return response()->json([
                    'code' => config('ajcode.error'),
                    'msg'  => "没有礼包码关键词",
                    'data' => [],
                ]);
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function addGiftKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            $replyKey = $request->input('keyword', '');
            $reply    = $request->input('reply_word', '');
            if (empty($replyKey) || empty($reply))
            {
                throw new Exception('参数错误');
            }

            if (empty($id))
            {
                // 添加自动回复
                $keyword          = new Keyword();
                $keyword->type    = Field::KEYWORD_TYPE_GIFT;
                $keyword->keyword = $replyKey;
                $keyword->reply   = $reply;
                $ret              = $keyword->save();
                if (!$ret)
                {
                    throw new Exception('保存礼包码关键词失败,关键词可能已经存在');
                }
                Log::info("{$user} 添加关键词 {$replyKey} => {$reply}");
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "添加礼包码关键词成功，回复{$replyKey},返回{$reply}",
                    'data' => [],
                ]);
            }
            else
            {
                // 更新自动回复
                $exist = Keyword::where([
                    'id' => $id,
                    'type'    => Field::KEYWORD_TYPE_GIFT,
                ])->get(['keyword', 'reply']);
                if ($exist->first())
                {
                    // 更新自动回复
                    $oldKey   = json_decode(json_encode($exist), true);
                    $oldReply = $oldKey[0]['reply'];
                    $upReply = Keyword::where('id', '=', $id)->update([
                        'reply' => $reply,
                        'keyword' => $replyKey,
                    ]);
                    if (!$upReply)
                    {
                        throw new Exception('更新礼包码关键词失败');
                    }
                    Log::info("{$user} 编辑关键词自动回复 {$replyKey} => {$reply}");
                    return response()->json([
                        'code' => config('ajcode.succ'),
                        'msg'  => "更新自动回复关键词{$replyKey}成功",
                        'data' => [],
                    ]);
                }
                else
                {
                    throw new Exception('id没有对应的关键词来进行更改');
                }
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function deleteGiftKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            if (empty($id))
            {
                throw new Exception('参数错误');
            }
            $delRet = Keyword::where([
                'id' => $id,
                'type' => Field::KEYWORD_TYPE_GIFT,
            ])->delete();
            if (!$delRet)
            {
                throw new Exception('删除失败');
            }
            Log::info("{$user} 删除礼包码关键词 id:{$id}");
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '删除成功',
                'data' => [],
            ]);

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function getAutoReplyList()
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $exist = Keyword::where([
                'type'    => Field::KEYWORD_TYPE_REPLY,
            ])->get(['id', 'keyword', 'reply']);
            if ($exist->first())
            {
                $list = [];
                foreach ($exist as $item)
                {
                    $list[] = [
                        'id' => $item->id,
                        'keyword' => $item->keyword,
                        'reply_word' => json_decode($item->reply, true),
                    ];
                }
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "succ",
                    'data' => [
                        'list' => $list,
                    ],
                ]);
            }
            else
            {
                return response()->json([
                    'code' => config('ajcode.error'),
                    'msg'  => "没有关键词",
                    'data' => [],
                ]);
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function deleteAutoReplyKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            if (empty($id))
            {
                throw new Exception('参数错误');
            }
            $delRet = Keyword::where([
                'id' => $id,
                'type' => Field::KEYWORD_TYPE_REPLY,
            ])->delete();
            if (!$delRet)
            {
                throw new Exception('删除失败');
            }
            Log::info("{$user} 删除关键词自动回复 id:{$id}");
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '删除成功',
                'data' => [],
            ]);

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function addAutoReplyKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            $replyKey = $request->input('keyword', '');
            $reply    = $request->input('reply_word', '');
            if (empty($replyKey) || empty($reply))
            {
                throw new Exception('参数错误');
            }
            $replyJsonDe = json_decode($reply, true);
            foreach ($replyJsonDe as $item)
            {
                if (!isset($item['type']) || !isset($item['value']))
                {
                    throw new Exception('reply_word中type或value缺少数据');
                }
                if ($item['type'] == 'news')
                {
                    $values = $item['value'];
                    foreach ($values as $value)
                    {
                        if (!isset($value['title']) || !isset($value['desc']))
                        {
                            throw new Exception('title和decription是必填项');
                        }
                    }
                }
            }

            if (empty($id))
            {
                // 添加自动回复
                $keyword          = new Keyword();
                $keyword->type    = Field::KEYWORD_TYPE_REPLY;
                $keyword->keyword = $replyKey;
                $keyword->reply   = $reply;
                $ret              = $keyword->save();
                if (!$ret)
                {
                    throw new Exception('保存自动回复关键词失败,关键词可能已经存在');
                }
                Log::info("{$user} 添加关键词自动回复 {$replyKey} => {$reply}");
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "添加自动回复关键词成功，回复{$replyKey},返回{$reply}",
                    'data' => [],
                ]);
            }
            else
            {
                // 更新自动回复
                $exist = Keyword::where([
                    'id' => $id,
                    'type'    => Field::KEYWORD_TYPE_REPLY,
                ])->get(['keyword', 'reply']);
                if ($exist->first())
                {
                    // 更新自动回复
                    $oldKey   = json_decode(json_encode($exist), true);
                    $oldReply = $oldKey[0]['reply'];
                    $upReply = Keyword::where('id', '=', $id)->update([
                        'reply' => $reply,
                        'keyword' => $replyKey,
                    ]);
                    if (!$upReply)
                    {
                        throw new Exception('更新自动回复关键词失败');
                    }
                    Log::info("{$user} 编辑关键词自动回复 {$replyKey} => {$reply}");
                    return response()->json([
                        'code' => config('ajcode.succ'),
                        'msg'  => "更新自动回复关键词{$replyKey}成功",
                        'data' => [],
                    ]);
                }
                else
                {
                    throw new Exception('id没有对应的关键词来进行更改');
                }
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function addTasetKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            $replyKey = $request->input('keyword', '');
            $reply    = $request->input('reply_word', '');
            if (empty($replyKey) || empty($reply))
            {
                throw new Exception('参数错误');
            }
            $replyJsonDe = json_decode($reply, true);
            foreach ($replyJsonDe as $item)
            {
                if (!isset($item['type']) || !isset($item['value']))
                {
                    throw new Exception('reply_word中type或value缺少数据');
                }
                if ($item['type'] == 'news')
                {
                    $values = $item['value'];
                    foreach ($values as $value)
                    {
                        if (!isset($value['title']) || !isset($value['desc']))
                        {
                            throw new Exception('title和decription是必填项');
                        }
                    }
                }
            }

            if (empty($id))
            {
                // 添加自动回复
                $keyword          = new Keyword();
                $keyword->type    = Field::KEYWORD_TYPE_TASET;
                $keyword->keyword = $replyKey;
                $keyword->reply   = $reply;
                $ret              = $keyword->save();
                if (!$ret)
                {
                    throw new Exception('保存CLICK失败,关键词可能已经存在');
                }
                Log::info("{$user} 添加CLICK自动回复 {$replyKey} => {$reply}");
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "添加回复CLICK成功，回复{$replyKey},返回{$reply}",
                    'data' => [],
                ]);
            }
            else
            {
                // 更新自动回复
                $exist = Keyword::where([
                    'id' => $id,
                    'type'    => Field::KEYWORD_TYPE_TASET,
                ])->get(['keyword', 'reply']);
                if ($exist->first())
                {
                    // 更新自动回复
                    $oldKey   = json_decode(json_encode($exist), true);
                    $oldReply = $oldKey[0]['reply'];
                    $upReply = Keyword::where('id', '=', $id)->update([
                        'reply' => $reply,
                        'keyword' => $replyKey,
                    ]);
                    if (!$upReply)
                    {
                        throw new Exception('更新CLICK失败');
                    }
                    Log::info("{$user} 编辑CLICK回复 {$replyKey} => {$reply}");
                    return response()->json([
                        'code' => config('ajcode.succ'),
                        'msg'  => "更新CLICK回复{$replyKey}成功",
                        'data' => [],
                    ]);
                }
                else
                {
                    throw new Exception('id没有对应的CLICK来进行更改');
                }
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function getTasetList()
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $exist = Keyword::where([
                'type'    => Field::KEYWORD_TYPE_TASET,
            ])->get(['id', 'keyword', 'reply']);
            if ($exist->first())
            {
                $list = [];
                foreach ($exist as $item)
                {
                    $list[] = [
                        'id' => $item->id,
                        'keyword' => $item->keyword,
                        'reply_word' => json_decode($item->reply, true),
                    ];
                }
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "succ",
                    'data' => [
                        'list' => $list,
                    ],
                ]);
            }
            else
            {
                return response()->json([
                    'code' => config('ajcode.error'),
                    'msg'  => "没有CLICK关键词",
                    'data' => [],
                ]);
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function deleteTasetKeyword(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            $id = $request->input('id', '');
            if (empty($id))
            {
                throw new Exception('参数错误');
            }
            $delRet = Keyword::where([
                'id' => $id,
                'type' => Field::KEYWORD_TYPE_TASET,
            ])->delete();
            if (!$delRet)
            {
                throw new Exception('删除失败');
            }
            Log::info("{$user} 删除CLICK回复 id:{$id}");
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '删除成功',
                'data' => [],
            ]);

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function addQuestion(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $id = $request->input('id', '');
            $content = $request->input('question', '');
            $answer = $request->input('answer', '');
            $date = $request->input('date', '');
            if (empty($content) || empty($date) || empty($answer))
            {
                throw new Exception('参数错误');
            }

            $date = date('Ymd', strtotime($date));
            if (empty($id))
            {
                $question          = new Questions();
                $question->date    = $date;
                $question->content = htmlspecialchars($content);
                $question->answer  = $answer;
                $ret               = $question->save();
                if (!$ret)
                {
                    throw new Exception("添加{$date}问题失败");
                }
            }
            else
            {
                $exist = Questions::where('id', '=', $id)->get(['date', 'content', 'answer']);
                if ($exist->first())
                {
                    // 更新自动回复
                    $ret = Questions::where('id', '=', $id)->update(['date' => $date, 'content' => $content, 'answer' => $answer]);
                    if (!$ret)
                    {
                        throw new Exception("更新{$date}问题失败");
                    }
                }
                else
                {
                    throw new Exception('id没有对应的每日问题来进行更改');
                }
            }
            Log::info("{$user} 添加/更新每日问题 {$content} => {$answer}");
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => "{$date} success",
                'data' => [],
            ]);

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function deleteQuestion(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $id = $request->input('id', '');
            if (empty($id))
            {
                throw new Exception('参数错误');
            }
            $delRet = Questions::where([
                'id' => $id,
            ])->delete();
            if (!$delRet)
            {
                throw new Exception('删除失败');
            }
            Log::info("{$user} 删除每日问题 id:{$id}");
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '删除成功',
                'data' => [],
            ]);

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function getQuestionList(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

            $exist = Questions::where('id', '>', 0)->get(['id', 'date', 'content', 'answer']);
            if ($exist->first())
            {
                $list = [];
                foreach ($exist as $item)
                {
                    $list[] = [
                        'id' => $item->id,
                        'date' => $item->date,
                        'question' => $item->content,
                        'answer' => $item->answer,
                    ];
                }
                return response()->json([
                    'code' => config('ajcode.succ'),
                    'msg'  => "succ",
                    'data' => [
                        'list' => $list,
                    ],
                ]);
            }
            else
            {
                return response()->json([
                    'code' => config('ajcode.error'),
                    'msg'  => "获取每日问题列表失败",
                    'data' => [],
                ]);
            }

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function menu(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

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
            Log::info("{$user} 修改菜单 {$menuJson}");
            $opts    = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $menuJson
                ]
            ];
            $context = stream_context_create($opts);
            $url     = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $accessToken;
            $result  = file_get_contents($url, false, $context);
            Log::info("create自定义菜单{$result}");

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => 'succ',
                'data' => json_decode($result, true),
            ]);
        }
        catch (Exception $e)
        {
            Log::info("异常：" . $e->getMessage());

            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }


    public function getMenu()
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }

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
                    'method'  => 'GET',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => []
                ]
            ];
            $context = stream_context_create($opts);
            $url     = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' . $accessToken;
            $result  = file_get_contents($url, false, $context);
            Log::info("get自定义菜单{$result}");

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => 'succ',
                'data' => json_decode($result, true),
            ]);
        }
        catch (Exception $e)
        {
            Log::info("异常：" . $e->getMessage());

            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function userExport()
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            Log::info("{$user} 导出user_info数据");

            $fileName = "user_info.csv";
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $fileName);

            $userInfos = Userinfo::get(['apple_id', 'mobile', 'bonus']);
            $userList  = json_decode(json_encode($userInfos), true);

            $title = iconv('utf-8', 'gbk', "微信ID,电话,积分\n");
            echo $title;
            foreach ($userList as $item)
            {
                $content = iconv('utf-8', 'gbk', "{$item['apple_id']},{$item['mobile']},{$item['bonus']}\n");
                echo $content;
            }
        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }

    }

    public function giftExport(Request $request)
    {
        try
        {
            $user = $this->_checkAdminUser();
            if (!$user)
            {
                throw new Exception('未登录', config('ajcode.redirect'));
            }
            Log::info("{$user} 导出gift数据");

            $keyword = $request->input('keyword', '');
            if (empty($keyword))
            {
                throw new Exception('请传入关键词');
            }


            $giftFile = "gift_info.csv";
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $giftFile);

            $giftInfos = self::getGiftInfo($keyword);
            $userInfos = self::getUserInfo();

            $giftTitle = iconv('utf-8', 'gbk', "礼包码,手机号\n");
            echo $giftTitle;
            foreach ($giftInfos as $giftInfo)
            {
                $appleId = $giftInfo['apple_id'];
                $giftCode = $giftInfo['gift_id'];
                $mobile = empty($appleId) ? '-' : $userInfos[$appleId]['mobile'];
                $giftContent = iconv('utf-8', 'gbk', "{$giftCode},{$mobile}\n");
                echo $giftContent;
            }

        }
        catch (Exception $e)
        {
            $code = $e->getCode();
            if ($code != config('ajcode.redirect'))
            {
                $code = config('ajcode.error');
            }
            return response()->json([
                'code' => $code,
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }

    }

    private function _checkAdminUser()
    {
        $user = Tool::getUser();

        return empty($user) ? false : $user['u'];
    }

    public function logout()
    {
        try
        {
            $res = Tool::logoutUser();
            if (!$res)
            {
                throw new Exception('退出失败');
            }
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => 'success',
                'data' => [],
            ]);
        }
        catch (Exception $e)
        {
            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => $e->getMessage(),
                'data' => [],
            ]);
        }

    }

    private static function getUserInfo()
    {
        $userInfos = Userinfo::get(['apple_id', 'mobile', 'bonus']);
        $userList  = json_decode(json_encode($userInfos), true);

        $res = [];
        foreach ($userList as $item)
        {
            $res[$item['apple_id']] = [
                'apple_id' => $item['apple_id'],
                'mobile' => $item['mobile'],
                'bonus' => $item['bonus']
            ];
        }

        return $res;
    }

    private static function getGiftInfo($keyword)
    {
        $giftInfos = Gifts::where('type', '=', $keyword)->get(['gift_id', 'apple_id', 'type']);
        $giftList  = json_decode(json_encode($giftInfos), true);

        $res = [];
        foreach ($giftList as $item)
        {
            $res[$item['gift_id']] = [
                'gift_id' => $item['gift_id'],
                'apple_id' => $item['apple_id'],
                'type' => $item['type'],
            ];
        }

        return $res;
    }

    public function modifyBonus(Request $request)
    {
        try
        {
            $mobile = $request->input('moible', '');
            $bonus = $request->input('bonus', '');
            if (empty($mobile) || empty($bonus))
            {
                throw new Exception('参数错误');
            }
            $upRet = Userinfo::where('mobile', '=', $mobile)->update([
                'bonus' => $bonus,
            ]);

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '成功',
                'data' => $upRet,
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

    public function clearCache(Request $request)
    {
        try
        {
            $key = $request->input('key', '');
            if (empty($key))
            {
                throw new Exception('参数错误');
            }
            $res = Cache::forget($key);

            return response()->json([
                'code' => config('ajcode.succ'),
                'msg'  => '成功',
                'data' => $res,
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
}