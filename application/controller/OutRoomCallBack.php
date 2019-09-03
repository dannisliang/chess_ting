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
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 使用redis加锁重写房间用户
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
            //todo 为抢占式锁增加一个微秒睡眠时间， 减轻redis的瞬间并发请求数
            usleep(1000);
        }

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
        }
        return jsonRes(0);
    }
}