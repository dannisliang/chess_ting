<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:42
 */

namespace app\controller;

use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\Log;


class OutRoomCallBack extends Base
{
    public function outRoomCallBack(){
        if(!isset($this->opt['roomId']) || !isset($this->opt['playerId']) || !is_numeric($this->opt['roomId']) || !is_numeric($this->opt['playerId'])){
            return json(['code' => 0, 'mess' => '成功']);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'].'lock';
        $getLock = $this->getLock($redisHandle, $lockKey);

        if($getLock){
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'needUserNum']);
            $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);

            if($roomUserInfo){
                $roomUserNum = count($roomUserInfo); # 房间用户数
                $newRoomUserInfo = [];
                foreach ($roomUserInfo as $k => $userInfo){
                    if($userInfo['userId'] != $this->opt['playerId']){
                        $newRoomUserInfo[] = $userInfo;
                    }
                }
                if($roomUserNum >= $roomHashInfo['needUserNum']){
                    $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['joinStatus' => 1, 'playerInfos' => json_encode($newRoomUserInfo)]);
                }else{
                    $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'playerInfos', json_encode($newRoomUserInfo));
                }
            }
            $redisHandle->del($lockKey); # 解锁
        }else{
            $logData = [
                $this->opt['roomId'],
                $this->opt['playerId']
            ];
            Log::write(json_encode($logData), '用户退出房间获取锁超时');
        }
        return json(['code' => 0, 'mess' => '成功']);
    }
}