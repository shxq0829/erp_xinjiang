<?php
/**
 * @created:
 * @author : xiaoqiang6@staff.weibo.com
 * @date   : 17/11/30 下午11:00
 */
namespace App\Http\Controllers;

use App;

use App\Orderuser;

class ExportController extends Controller
{
    public function export()
    {
        $fileName = "user_info.csv";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);

        $userInfos = Orderuser::where('id', '>', 0)->get(['mobile', 'sys_version', 'inviter', 'invite_count', 'created_at']);
//        $userList  = json_decode(json_encode($userInfos), true);

        $title = iconv('utf-8', 'gbk', "电话,设备,推荐人,邀请总数,预约时间\n");
        echo $title;
        foreach ($userInfos as $item)
        {
            $info = [
                'mobile' => $item->mobile,
                'sys_version' => $item->sys_version,
                'inviter' => $item->inviter,
                'invite_count' => $item->invite_count,
                'created_at' => $item->created_at
            ];
            $content = iconv('utf-8', 'gbk', "{$info['mobile']},{$info['sys_version']},{$info['inviter']},{$info['invite_count']},{$info['created_at']}\n");
            echo $content;
        }
    }
}
