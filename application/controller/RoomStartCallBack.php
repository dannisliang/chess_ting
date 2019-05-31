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

        if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            $changeRoomInfo = [
                'joinStatus' => 2, # 游戏中
                'gameStartTime' => date('Y-m-d H:i:s', time()),
                'founderId' => $this->opt['founderId'],
                'players' => json_encode($this->opt['players'])
            ];
            $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $changeRoomInfo);
        }

        return jsonRes(0);
    }
}