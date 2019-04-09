<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/9
 * Time: 17:17
 */
namespace app\command;

use app\definition\Definition;
use app\model\ClubModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class SynchronizedClubRoom extends Command{

    protected function configure()
    {
        $this->setName('SynchronizedClubRoom')->setDescription('同步俱乐部房间脚本，每10分钟执行');
    }

    protected function execute(Input $input, Output $output)
    {
        $club = new ClubModel();
        $clubInfo = $club->getAllClubIds();

        if(!$clubInfo){
            return false;
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        foreach ($clubInfo as $clubId){
            $allRoomNumber = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$clubId);
            if($allRoomNumber){
                foreach ($allRoomNumber as $roomNumber){

                }
            }
        }
    }
}