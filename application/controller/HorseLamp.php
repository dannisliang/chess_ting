<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/21
 * Time: 17:21
 */

namespace app\controller;


use app\definition\Definition;

class HorseLamp
{
    /*
     * 跑马灯列表
     * @param
     * */
    public function lists(){
        $data['appid'] = Definition::$CESHI_APPID;
        $data['status'] = 1;
        $url = Definition::$WEB_USER_URL;//运营中心域名
        $url_area = Definition::$HORSE_LAMP;//跑马灯
        $bulletinlist = guzzleRequest($url,$url_area,$data);
        if ($bulletinlist['code'] == 0) {
            $count = count($bulletinlist['data']);
            $result = array();
            for ($i = 0; $i < $count; $i++) {
                $result[$i]['content'] = $bulletinlist['data'][$i]['content'];
                $result[$i]['speed'] = (float)$bulletinlist['data'][$i]['speed'];
                $result[$i]['interval'] = (int)$bulletinlist['data'][$i]['interval_time'];
            }
            return jsonRes(0,$result);
        } else if ($bulletinlist['code'] == 2001) {
            return jsonRes(3004);
        }
    }
}