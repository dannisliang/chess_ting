<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/18
 * Time: 11:34
 */

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class JoinOrOutRoom extends Command{

    protected function configure()
    {
        $this->setName('JoinOrOutRoom')->setDescription('处理用户加入或退出房间的队列');
    }

    protected function execute(Input $input, Output $output)
    {
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $joinOrOutRoomList = RedisKey::$JOIN_OR_OUT_ROOM_LIST;

        # 处理队列数据写hash
        while (true){
            $listPopInfoArr = $redisHandle->bRpop($joinOrOutRoomList, 10); # 阻塞10秒，防止linux把进程当作僵尸
            if($listPopInfoArr){

                $listPopInfoJsonDecode = json_decode($listPopInfoArr[1], true);
                if($listPopInfoJsonDecode){

                    $roomHashKey = RedisKey::$USER_ROOM_KEY_HASH.$listPopInfoJsonDecode['roomId']; # 房间key
                    $setRes = true;

                    if($listPopInfoJsonDecode['type'] == 1){ # 加入房间
                        $roomInfo = $redisHandle->hMget($roomHashKey, ['playerInfos', 'roomNeedUserNum']);
                        $playerInfos = json_decode($roomInfo['playerInfos'], true);

                        $playerInfos[$listPopInfoJsonDecode['userId']] = [
                            'userId' => $listPopInfoJsonDecode['userId'],
                            'nickName' => $listPopInfoJsonDecode['nickName'],
                            'headImgUrl' => $listPopInfoJsonDecode['headImgUrl'],
                            'ipAddr' => $listPopInfoJsonDecode['ipAddr'],
                            'needDiamond' => $listPopInfoJsonDecode['needDiamond']
                        ];

                        $userNum = count($playerInfos);
                        $playerInfosJsonEncode = json_encode($playerInfos);

                        if($userNum >= $roomInfo['roomNeedUserNum']){ # 房间人数是否满
                            $setArr = [
                                'joinStatus' => 0,
                                'playerInfos' => $playerInfosJsonEncode,
                            ];
                            $setRes = $redisHandle->hMset($roomHashKey, $setArr);
                        }else{
                            $setRes = $redisHandle->hSet($roomHashKey, 'playerInfos', $playerInfosJsonEncode);
                        }
                    }

                    if($listPopInfoJsonDecode['type'] == 0){ # 退出房间
                        $roomInfo = $redisHandle->hMget($roomHashKey, ['playerInfos', 'joinStatus']);
                        $playerInfos = json_decode($roomInfo['playerInfos'], true);

                        unset($playerInfos[$listPopInfoJsonDecode['userId']]);
                        $playerInfosJsonEncode = json_encode($playerInfos);
                        if($roomInfo['joinStatus'] == 0){ # 以前是满员
                            $setArr = [
                                'joinStatus' => 1,
                                'playerInfos' => $playerInfosJsonEncode
                            ];

                            $setRes = $redisHandle->hMset($roomHashKey, $setArr);
                        }else{
                            $setRes = $redisHandle->hSet($roomHashKey, 'playerInfos', $playerInfosJsonEncode);
                        }
                    }

                    if(!$setRes){ # 写日志
                        $errorArr = [
                            $joinOrOutRoomList,
                        ];
                        foreach ($listPopInfoJsonDecode as $v){
                            $errorArr[] = $v;
                        }
                        errorLog('joinOrOutRoom', $errorArr);
                    }

                }
            }
        }
    }
}