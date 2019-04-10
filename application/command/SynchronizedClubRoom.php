<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/9
 * Time: 17:17
 */
namespace app\command;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

use app\model\ClubModel;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;

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

        $requestData = [];
        $redis = new Redis();
        $redisHandle = $redis->handler();
        foreach ($clubInfo as $clubId){
            $allRoomNumber = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$clubId);
            if($allRoomNumber){
                foreach ($allRoomNumber as $roomNumber){
                    if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber)){
                        $roomUrl = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, 'roomUrl');
                        $requestData[$roomUrl][] = $roomNumber;
                    }
                }
            }
        }
        $client = new Client();
        if(isset($requestData) && $requestData){
            foreach ($requestData as $roomUrl => $roomNums){
                foreach ($roomNums as $roomNumber)
                $promises[$roomNumber] = $client->postAsync($roomUrl.Definition::$CHECK_ROOM, ['roomId' => $roomNumber]);
            }
        }

        if(isset($promises) && $promises){
            $results = Promise\unwrap($promises);
            p($results);
        }
    }
}