<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:51
 */

namespace app\controller;

use think\Env;
use app\model\BeeSender;
use app\definition\RedisKey;
use think\cache\driver\Redis;


class RoundStartCallBack extends Base
{
    public function roundStartCallBack(){
        if(!isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(0);
        }
        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 报送大数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['createTime', 'clubMode', 'playerInfos', 'clubType',
            'roomOptionsId', 'roomTypeName', 'roomChannel', 'betNums', 'needUserNum', 'clubId', 'clubName', 'clubRegionId', 'clubRegionName',
            'clubType', 'roomType', 'roomName']);

        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip'), config('app_debug'));
        $playerInfos = json_decode($roomHashInfo['playerInfos'], true);
        // Todo 报送
        if($playerInfos){
            foreach ($playerInfos as $k => $userInfo){
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
                    'table_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'].'_'.(isset($this->opt['set']) ? $this->opt['set'] : 1).'_'.$this->opt['round'],
                    'rule_detail' => '',
                    'bet_num' => $roomHashInfo['betNums'],
                    'user_num' => $roomHashInfo['needUserNum'],
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];
                $beeSender->send('table_start', $bigData);
            }
        }
        # 报送大数据完成
        return jsonRes(0);
    }
}