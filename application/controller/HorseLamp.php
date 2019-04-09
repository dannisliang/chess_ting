<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/21
 * Time: 17:21
 */

namespace app\controller;


use app\definition\Definition;

class HorseLamp extends Base
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
        $bulletinlist = sendHttpRequest($url.$url_area, $data);
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

    /**
     * 俱乐部跑马灯
     * @return \think\response\Json\
     */
    public function getScrollScreen(){
        $opt = ['area_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $data = [
            'appid' => Definition::$CESHI_APPID,
            'status' => 1,
            'areaid' => $this->opt['area_id'],
        ];
        $bulletinlists = sendHttpRequest(Definition::$WEB_USER_URL . Definition::$HORSE_LAMP, $data);
        if ($bulletinlists['code'] == 0) {
            $data = [];
            foreach ($bulletinlists['data'] as $bulletinlist){
                $result['content']  = $bulletinlist['content'];
                $result['speed']    = (float)$bulletinlist['speed'];
                $result['interval'] = (int)$bulletinlist['interval_time'];
                $data[] = $result;
            }
            return jsonRes(0,$data);
        } elseif ($bulletinlists['code'] == 2001) {
            return jsonRes(3004);
        }
    }
}