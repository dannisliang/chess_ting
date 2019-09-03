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
use think\Env;
use app\model\BeeSender;

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

            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'],
                ['playerInfos', 'roomOptionsId', 'roomTypeName', 'clubName', 'roomChannel', 'clubMode',
                    'tableType', 'tableNum', 'betNums', 'clubId', 'clubRegionId', 'clubRegionName', 'createTime', 'roomType', 'roomName']);
            # 报送大数据
            $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
            if($playerInfo){
                foreach ($playerInfo as $k => $userInfo){
                    if(in_array($userInfo['userId'], $this->opt['players'])){
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $userInfo['userId'],
                            'role_id' => '-'.'_'.$userInfo['userId'],
                            'role_name' => $userInfo['nickName'],
                            'client_id' => '-',
                            'client_type' => $userInfo['clientType'],
                            'system_type' => $userInfo['systemType'],
                            'ip' => $userInfo['ipAddr'],

                            'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                            'room_type_id' => $roomHashInfo['roomType'],
                            'room_type_name' => $roomHashInfo['roomName'],
                            'room_channel' => $roomHashInfo['roomChannel'],
                            'rule_detail' => '-',
                            'table_type' => $roomHashInfo['tableType'],
                            'table_num' => $roomHashInfo['tableNum'],
                            'bet_num' => $roomHashInfo['betNums'],
                            'club_id' => $roomHashInfo['clubId'],
                            'club_name' => $roomHashInfo['clubName'],
                            'club_region_id' => $roomHashInfo['clubRegionId'],
                            'club_region_name' => $roomHashInfo['clubRegionName'],
                            'club_mode' => $roomHashInfo['clubMode'],
                        ];

                        // Todo 报送
                        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip'), config('app_debug'));
                        $beeSender->send('room_join', $bigData);
                    }
                }
            }
        }

        return jsonRes(0);
    }
}