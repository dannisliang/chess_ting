<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:40
 */
namespace app\controller;

use think\Env;
use app\model\BeeSender;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class JoinRoomCallBack extends Base
{
    public function joinRoomCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['playerId']) || !is_numeric($this->opt['playerId'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'],
            ['playerInfos', 'roomOptionsId', 'roomTypeName', 'clubName', 'roomChannel', 'clubMode',
                'tableType', 'tableNum', 'betNums', 'clubId', 'clubRegionId', 'clubRegionName', 'createTime']);
        # 报送大数据
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
        if($playerInfo){
            foreach ($playerInfo as $k => $userInfo){
                if($userInfo['userId'] == $this->opt['playerId']){
                    $bigData = [
                        'server_id' => '-',
                        'user_id' => $this->opt['playerId'],
                        'role_id' => '-'.'_'.$this->opt['playerId'],
                        'role_name' => $userInfo['nickName'],
                        'client_id' => '-',
                        'client_type' => $userInfo['clientType'],
                        'system_type' => $userInfo['systemType'],
                        'ip' => $userInfo['ipAddr'],

                        'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                        'room_type_id' => $roomHashInfo['roomOptionsId'],
                        'room_type_name' => $roomHashInfo['roomTypeName'],
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
                    break;
                }
            }
        }
        return jsonRes(0);
    }
}
