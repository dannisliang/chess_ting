<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 15:48
 */

namespace app\controller;


use app\model\AgentApplicationModel;
class Agent extends Base
{
    private $path = '';
    private $data = array();
    public static $player_id = '';
    public function __construct()
    {
        $this -> path = __DIR__ . "/../../application/open_recruit.php";
        parent::_initialize();
        $player_id = self::$player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes(9999);
        }

    }

    /*控制招募代理按钮的显示*/
    public function openRecruit()
    {
        $file_path = $this->path;
        $opt = $this->opt;
        $is_open = $opt['is_open'];//0关闭,1打开
        if ($is_open == 1) {
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
            $result = operaFile($file_path, $data, 'write');
            if(!$result){
                return jsonRes(3016);
            }
            return jsonRes(0);
        } catch (\Exception $e) {
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
        }catch(\Exception $e){
            echo $e->getMessage();
        }

    }

    /*接收客户端表单的提交*/
    public function recive(){
        $opt = ['phone_num','area'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        if(strlen($this->opt['phone_num']) > 20){
            return jsonRes(3610);
        }
        $agentApplicationModel = new AgentApplicationModel();
        $player_id = getUserIdFromSession();
        $player_name = backNickname($player_id);
        $area_array = explode(',',$this->opt['area']);
        if(count($area_array) == 3){
            $area_name = $area_array[1];
            $area_infos = $area_array[2];
        }else{
            $area_name = $area_array[0];
            $area_infos = $area_array[1];
        }
        $data = [
            'player_id' => $player_id,
            'phone' => $this ->opt['phone_num'],
            'application_time' => date('Y-m-d H:i:s',time()),
            'applicant' => $player_name,
            'area_name' => $area_name,
            'area_infos' => $area_infos,
            'status' => 0,
        ];
        $result = $agentApplicationModel ->addData($data);
        if(!$result){
            return jsonRes(3017);
        }
        return jsonRes(0);

    }
}



































