<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/9
 * Time: 15:34
 */

namespace app\controller;


use app\definition\Definition;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\Env;

class Inform extends Base
{

    /**
     * 发送聊天记录
     * @return \think\response\Json\
     */
    public function inform(){
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }
        switch ($this->opt['type']){
            case 1026://邮件
                $data = [
                    'sender'  => $this -> opt['sender'],
                    'type'    => $this -> opt['type'],
                    'content' => $this -> opt['content'],
                    'reciver'=> [
                        $this -> opt['receiver'],
                    ],
                ];
                break;
            case 1025://聊天
                //查出房间内所有玩家
                $player_ids = $this -> getPlayerIds();
                $data = [
                    'sender'  => $user_id,
                    'type'    => $this -> opt['type'],
                    'content' => json_decode($this -> opt['content']),
                    'reciver'=> $player_ids,
                ];
                break;
            case 1028://未知
                $data = [
                    'sender'  => $user_id,
                    'type'    => $this -> opt['type'],
                    'content' => [
                        'status' => $this->opt['status'],
                        'club_id'=> $this->opt['club_id'],
                    ],
                    'reciver'=> $this -> opt['sender'],
                ];
                break;
            default:
                $data = [];
                break;
        }
        if (!$data){
            return jsonRes(3004);
        }
        $data['appid'] = Env::get('app_id');
        $list = guzzleRequest(Env::get('inform_url') , Definition::$SEND , $data);
        if($list['code'] == 0){
            return json(['code' => 0,'mess' => '发送成功']);
        }else if($list['code'] == '4002'){
            return json(['code' => 3004,'mess' => '客户端收到消息未回复,失败']);
        }else if($list['code'] == '4007'){
            return json(['code' => 3300,'mess' => '缺少参数']);
        }else if($list['code'] == '4008'){
            return json(['code' => 3301,'mess' => '参数不能为空']);
        }else if($list['code'] == '4001'){
            return json(['code' => 3302,'mess' => '内部错误']);
        }else if($list['code'] == '4003'){
            return json(['code' => 3303,'mess' => '接收方不在线']);
        }else if($list['code'] == '4006'){
            return json(['code' => 3304,'mess' => '请求方式错误']);
        }else if($list['code'] == '4005'){
            return json(['code' => 3305,'mess' => 'appid不对']);
        }

    }

    /**
     * 查出玩家的id
     * @return array|\think\response\Json\
     */
    private function getPlayerIds(){
        //查出房间内所有玩家
        $redis = new Redis();
        $redisHandler = $redis ->handler();
        $player_info = $redisHandler -> hGet(RedisKey::$USER_ROOM_KEY_HASH . $this->opt['room_id'],'playerInfos');
        if(!$player_info){
            return jsonRes(3505);
        }
        $player_infos = json_decode($player_info,true);
        $player_ids = [];
        foreach ($player_infos as $item){
            $player_ids[] = (int)$item['userId'];
        }
        return $player_ids;
    }
}
