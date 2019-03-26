<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/26
 * Time: 14:56
 */

namespace app\controller;


use app\definition\RedisKey;
use think\cache\driver\Redis;

class GamingRoomInfo extends Base
{
    /**
     * 获取房间内其他玩家的信息 todo 传输为二维数组
     * @return \think\response\Json\
     */
    public function getOtherUserInfo(){
        //验证参数
        $opt = ['player_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $redis = new Redis();
        $redisHandler = $redis -> handler();
        $player_id    = $redisHandler -> get(RedisKey::$USER_ROOM_KEY . $this->opt['player_id']);
        $player_infos = $redisHandler -> hGet(RedisKey::$USER_ROOM_KEY_HASH . $player_id,'playerInfos');
        if(!$player_infos){
            return jsonRes(3006);
        }
        $result = [];
        $play_infos = json_decode($player_infos,true);
        foreach ($play_infos as $play_info){
            $player = [
                'ip' => $play_info['ipAddr'],
                'nickname' => $play_info['nickName'],
                'head_img' => $play_info['headImgUrl'],
                'gender' => $play_info['sex'],
                'vip_id' => $play_info['vipId'],
            ];
            $result[] =  $player;
        }

        return jsonRes(0 , $result);
    }
}