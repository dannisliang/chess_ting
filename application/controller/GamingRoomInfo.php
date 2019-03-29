<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/26
 * Time: 14:56
 */

namespace app\controller;


use app\definition\RedisKey;
use app\model\UserEvaluateModel;
use think\cache\driver\Redis;

class GamingRoomInfo extends Base
{
    /**
     * 获取房间内其他玩家的信息
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
        $room_id      = $redisHandler -> get(RedisKey::$USER_ROOM_KEY . $this->opt['player_id']);
        $player_infos = $redisHandler -> hGet(RedisKey::$USER_ROOM_KEY_HASH . $room_id,'playerInfos');
        if(!$player_infos){
            return jsonRes(3518);
        }
        $play_infos = json_decode($player_infos,true);
        foreach ($play_infos as $play_info){
            $player = [];
            if($play_info['userId'] == $this ->opt['player_id']){
                $evalInfo = $this ->getEvaluate($play_info['userId']);
                $player = [
                    'nickname'  => $play_info['nickName'],
                    'head_img'  => $play_info['headImgUrl'],
                    'gender'    => (int)$play_info['sex'],
                    'vip_id'    => $play_info['vipId'],
                    'ip'        => $play_info['ipAddr'],
                    'good_num'  => $evalInfo['good_num'],
                    'bad_num'   => $evalInfo['bad_num'],
                ];
            }
        }
        return jsonRes(0 , $player);
    }

    /**
     * 获取用户点赞数
     * @param $user_id
     * @return array
     */
    private function getEvaluate($user_id){
        $evaluateModel = new UserEvaluateModel();
        $evalInfo = $evaluateModel ->getInfoById($user_id);
        $evaluate = [
            'good_num' => 0 ,
            'bad_num'   => 0,
        ];
        if(!empty($evalInfo)){
            $evaluate = [
                'good_num' => $evalInfo['good_num'] ,
                'bad_num'   => $evalInfo['bad_num'],
            ];
        }

        return $evaluate;
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