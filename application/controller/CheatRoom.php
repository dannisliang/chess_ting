<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/24
 * Time: 14:12
 */
namespace app\controller;

use app\model\ClubModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\model\RoomOptionsModel;

class CheatRoom extends Base {

    /**
     * 客户端
     */
    // 获取gps相关信息完成
    public function cheatRoom(){
        # 根据房间ID获取
        if(isset($this->opt['room_id']) && is_numeric($this->opt['room_id'])){
            $redis = new Redis();
            $redisHandle = $redis->handler();
            if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'])){
                $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['isGps', 'gpsRange']);
                $returnData = [
                    'room_cheat' => $roomHashInfo['isGps'],
                    'gps_range' => $roomHashInfo['gpsRange']
                ];
                return jsonRes(0, $returnData);
            }
        }

        # 根据房间规则ID获取
        if(isset($this->opt['match_id']) && is_numeric($this->opt['match_id'])){
            $roomOptions = new RoomOptionsModel();
            $roomOptionsInfo = $roomOptions->getRoomOptionInfo($this->opt['match_id']);
            if($roomOptionsInfo){
                $club = new ClubModel();
                $clubInfo = $club->getClubInfo($roomOptionsInfo['club_id']);
                if($clubInfo){
                    $returnData = [
                        'room_cheat' => $roomOptionsInfo['cheat'],
                        'gps_range' => $clubInfo['gps']
                    ];
                    return jsonRes(0, $returnData);
                }
            }
        }
    }
}