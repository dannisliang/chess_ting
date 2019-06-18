<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/17
 * Time: 14:16
 */

namespace app\controller;


use app\definition\RedisKey;
use think\cache\driver\Redis;

class AddUserViewScoreSign extends Base
{
    /**
     * 添加看过标识
     * @return \think\response\Json\
     */
    public function addSign(){
        $opt = ['room_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $userId = getUserIdFromSession();
        if(!$userId){
            return jsonRes(9999);
        }

        $redis = new Redis();
        $redis_handler = $redis ->handler();
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redis_handler->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
            usleep(50000);
        }
        if ($getLock){
            $roomHashInfo = $redis_handler -> hMget(RedisKey::$USER_ROOM_KEY_HASH . $this ->opt['room_id'],['playerInfos']);
            if($roomHashInfo['playerInfos']){
                return jsonRes(3505);
            }
            $playerInfos = json_decode($roomHashInfo['playerInfos'],true);
            foreach ($playerInfos as &$playerInfo){
                if($playerInfo['userId'] == $userId){
                    $playerInfo['viewSign'] = 1;
                }
            }
            $redis_handler->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'playerInfos', json_encode($playerInfos));
            $redis_handler->del($lockKey); # 解锁
        }
        return jsonRes(0);
    }
}