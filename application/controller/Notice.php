<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 11:09
 */

namespace app\controller;


use app\definition\Definition;
use think\Env;

class Notice
{
    /*获取游戏的公告列表*/
    public function lists()
    {
        $url = Env::get('web_user_url');//运营中心的域名
        $url_area = Definition::$NOTICE_LIST;//公告列表
        $data['appid'] = Env::get('app_id');//该地区的APPid
        $data['status'] = 1;
        $list = sendHttpRequest($url.$url_area, $data);
        if(!isset($list['data'])){
            return jsonRes(3004);
        }
        $notice_list= $list['data'];
        if($notice_list){
            $back_list = array();
            $for_num = count($notice_list);
            for($i=0;$i<$for_num;$i++){
                $back_list[$i]['title'] = $notice_list[$i]['title'];
                $back_list[$i]['content'] = $notice_list[$i]['content'];
            }
            return jsonRes(0,$back_list);
        }

    }

}