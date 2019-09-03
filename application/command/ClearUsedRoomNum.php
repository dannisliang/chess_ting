<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/31
 * Time: 9:25
 */

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/9
 * Time: 10:58
 */
namespace app\command;

use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\console\Input;
use think\console\Output;
use think\console\Command;

class ClearUsedRoomNum extends Command{

    protected function configure()
    {
        $this->setName('ClearUsedRoomNum')->setDescription('用于清除redis中占用的已经超过三天的房间号， 每小时执行一次');
    }

    protected function execute(Input $input, Output $output)
    {
        $endTime = bcsub(time(), bcmul(bcmul(3600, 24, 0), 3, 0), 0);
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $redisHandle->zRemRangeByScore(RedisKey::$USED_ROOM_NUM, 1, $endTime);
    }
}