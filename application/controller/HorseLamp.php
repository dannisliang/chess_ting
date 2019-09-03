<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/21
 * Time: 17:21
 */

namespace app\controller;


use app\definition\Definition;
use think\Env;

class HorseLamp extends Base
{
    /*
     * 跑马灯列表
     * @param
     * */
    public function lists(){
        $data['appid'] = Env::get('app_id');
        $data['status'] = 1;
        $url = Env::get('web_user_url');//运营中心域名
        $url_area = Definition::$HORSE_LAMP;//跑马灯
        $bulletinlist = sendHttpRequest($url.$url_area, $data);
        if(!isset($bulletinlist['code'])){
            return jsonRes(3004);
        }
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
     * 俱乐部跑马灯(根据类型type 大厅、俱乐部、红包房)
     * @return \think\response\Json\
     */
    public function getScrollScreen(){
        $opt = ['type'];
        if(!$this->opt){
            return jsonRes(3006);
        }
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $area_id = 0;
        if(isset($this->opt['area_id'])){
            $area_id = $this->opt['area_id'];
        }
        $data = [
            'appid' => Env::get('app_id'),
            'status' => 1,
            //'areaid' => $area_id,
            //'type' => $this->opt['type'],
        ];
        $bulletinLists = sendHttpRequest(Env::get('web_user_url'). Definition::$HORSE_LAMP, $data);
        if ($bulletinLists['code'] == 0) {
            $data = [];
            foreach ($bulletinLists['data'] as $bulletinlist){
                $result['content']  = $bulletinlist['content'];
                $result['speed']    = (float)$bulletinlist['speed'];
                $result['interval'] = (int)$bulletinlist['interval_time'];
                $data[] = $result;
            }
            return jsonRes(0,$data);
        } elseif ($bulletinLists['code'] == 2001) {
            return jsonRes(3004);
        }
    }
}
