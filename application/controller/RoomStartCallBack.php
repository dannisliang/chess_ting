<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:46
 */


namespace app\controller;

use app\definition\RedisKey;
use think\cache\driver\Redis;


class RoomStartCallBack extends Base
{
    public function roomStartCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['founderId']) || !is_numeric($this->opt['founderId']) || !isset($this->opt['players'])){
            return jsonRes(0);
        }

        # 修改房间的状态
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
        }
        if($getLock){
            $changeRoomInfo = [
                'joinStatus' => 2, # 游戏中
                'gameStartTime' => date('Y-m-d H:i:s', time()),
                'founderId' => $this->opt['founderId'],
                'players' => json_encode($this->opt['players'])
            ];
            $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $changeRoomInfo);
            $redisHandle->del($lockKey);
            return jsonRes(0);
        }
        return jsonRes(0);
    }
}