<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/7/8
 * Time: 17:43
 */
namespace app\command;

use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class MigrateRedisRoomNum extends Command{

    protected function configure()
    {
        $this->setName('MigrateRedisRoomNum')->setDescription('用于迁移redis中的占用房间号，在set集合迁移到zset有序集合，key名不变');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $usedRoomNum = $redisHandle->sMembers(RedisKey::$USED_ROOM_NUM);
        $redisHandle->del(RedisKey::$USED_ROOM_NUM);
        foreach ($usedRoomNum as $v){
            $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, 0, $v);
        }
        echo "数据迁移成功";
    }
}