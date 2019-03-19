<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/11
 * Time: 18:58
 */

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class RoomNumPush extends Command{

    protected function configure()
    {
        $this->setName('RoomNumPush')->setDescription('用于在redis中生成一个单队列，队列中是房间号');
    }

    protected function execute(Input $input, Output $output)
    {
        $roomNumArr = [];
        $begin = 100000;
        $end = 1000000;
        for ($i = $begin; $i < $end; $i++){
            $roomNumArr[] = $i;
        }
        shuffle($roomNumArr);

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $redisHandle->del(RedisKey::$ROOM_NUMBER_KEY_LIST);
        foreach ($roomNumArr as $val){
            $redisHandle->lPush(RedisKey::$ROOM_NUMBER_KEY_LIST, $val);
        }

        $output->writeln("脚本执行完毕");
        exit();
    }
}