<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 15:48
 */

namespace app\controller;


use think\Exception;
use think\Db;
class Agent extends Base
{
    private $path = 'application/open_recruit.php';
    private $data = array();

    /*控制招募代理按钮的显示*/
    public function openRecruit()
    {
        $file_path = $this->path;
        $opt = $this->opt;
        $is_open = $opt['is_open'];//0关闭,1打开
        if ($is_open == true) {
            $data['is_open'] = 1;
        } else {
            $data['is_open'] = 0;
        }
        if (array_key_exists('content', $opt)) {
            $data['content'] = $opt['content'];
        } else {
            $data['content'] = '';
        }
        try {
            $arr = operaFile($file_path, $data, 'write');
            $is_open = $arr['is_open'];
            return json(['code'=>0,'recruit_state'=>$is_open]);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

    }

    /*显示当前按钮是否是开启状态*/
    public function state()
    {
        $file_path = $this->path;
        $data = $this->data;
        try{
            $content = operaFile($file_path, $data, 'read');
            return jsonRes(0,$content);
        }catch(Exception $e){
            echo $e->getMessage();
        }

    }
    /*接收客户端表单的提交*/
    public function recive(){
        $opt = $this->opt;//1234566
        $player_obj = getUserSessionInfo();
        $data['player_id'] = $player_obj['uid'];
        //$player_name = $player_obj['nickname'];
        $data['phone'] = $player_obj['phone_num'];
        $data['application_time'] = date('Y-m-d H:i:s');
        $area = $opt['area'];
        $area_array = explode(',',$area);
        if(count($area) == 3){
            $data['area_name'] = $area_array[1];
            $data['area_infos'] = $area_array[2];
        }else{
            $data['area_name'] = $area_array[0];
            $data['area_infos'] = $area_array[1];
        }
        $data['status'] = 0;
        Db::name('agent_application')->insert($data);
        return jsonRes(0);

    }
}



































