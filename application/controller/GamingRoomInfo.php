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
        $room_id    = $redisHandler -> get(RedisKey::$USER_ROOM_KEY . $this->opt['player_id']);
        $player_infos = $redisHandler -> hGet(RedisKey::$USER_ROOM_KEY_HASH . $room_id,'playerInfos');
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

    /**
     * 获取房间内玩家的资产信息(暂时没有用)
     * @return \think\response\Json\
     */
    public function getUserProperty(){
        $opt = ['room_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        //从Redis里获取房间内用户的信息
        $redis = new Redis();
        $redisHandler = $redis -> handler();
        $player_infos = $redisHandler -> hGet(RedisKey::$USER_ROOM_KEY_HASH . $this->opt['room_id'],'playerInfos');
        if(!$player_infos){
            return jsonRes(3006);
        }
        $player_infos = json_decode($player_infos,true);
        $userIds = [];
        foreach ($player_infos as $player_info){
            $userIds[] = $player_info['userId'];
        }
        $user_propertys = getUserProperty($userIds , 10001);
        if($user_propertys['code'] != 0){
            return jsonRes(23406);
        }

        //拼接返回值
        $data = [];
        foreach ($user_propertys['data'] as $user_property){
            $temp = [
                'have_gold' => $user_property['property_num'],
                'player_id' => $user_property['uid'],
            ];
            $data[] = $temp;
        }
        return jsonRes(0 , $data);
    }
}