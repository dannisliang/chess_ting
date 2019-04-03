<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 11:09
 */

namespace app\controller;


use app\definition\Definition;

class Notice
{
    /*获取游戏的公告列表*/
    public function lists()
    {
        $url = Definition::$WEB_USER_URL;//运营中心的域名
        $url_area = Definition::$NOTICE_LIST;//公告列表
        $data['appid'] = Definition::$CESHI_APPID;//该地区的APPid
        $list = sendHttpRequest($url.$url_area, $data);
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