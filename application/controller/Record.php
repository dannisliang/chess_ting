<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/28
 * Time: 19:31
 */
namespace app\controller;

use think\Session;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\model\UserClubRoomRecordModel;

class Record extends Base{

    # 获取用户的历史纪录
    public function getRecordRoomList(){
        # 获取三天内玩过的房间
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);

        # 获取所有的用户玩过的房间
        $userClubRoomRecord = new UserClubRoomRecordModel();
        $userClubRoomRecordInfo = $userClubRoomRecord->getUserClubRoomRecord($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userClubRoomRecordInfo){
            return jsonRes(0, []);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $returnData = [];
        foreach ($userClubRoomRecordInfo as $k => $v){
            if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$v['room_id'])){
                $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$v['room_id'], ['roomChecks', 'gameEndTime', 'playerInfos', 'roomOptions', 'gameEndInfo', 'roomCode']);
                $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);
                $gameEndInfo = json_decode($roomHashInfo['gameEndInfo'], true);
                # 处理数据
                $userScore = [];
                foreach ($gameEndInfo as $kk => $scoreInfo){
                    $userScore[$scoreInfo['playerId']] = $scoreInfo['totalScore'];
                }
                foreach ($roomUserInfo as $kkk => $userInfo){
                    $roomUserInfo[$kkk]['total_score'] = $userScore[$userInfo['userId']];
                }
                $return = [];
                $return['player_infos'] = $roomUserInfo;
                $return['time'] = strtotime($roomHashInfo['gameEndTime']);
                $return['room_id'] = $v['room_id'];
                $return['Options'] = $roomHashInfo['roomOptions'];
                $return['room_code'] = $roomHashInfo['roomCode'];
                $returnData[] = $return;
            }
        }

        return jsonRes(0, $returnData);
    }
    # 获取房间的牌局记录
    public function getRecordList(){
        if(!isset($this->opt['room_id']) || !is_numeric($this->opt['room_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'])){
            return jsonRes(3006);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['gameEndInfo', 'playerInfos']);
        $gameEndInfo = json_decode($roomHashInfo['gameEndInfo'], true);
        $playerInfos = json_decode($roomHashInfo['playerInfos'], true);

        if(!is_array($playerInfos) || !is_array($gameEndInfo)){
            return jsonRes(0, []);
        }

        $returnData = [];
        foreach ($gameEndInfo as $k => $v){
            $returnData[$k]['playBack'] = $v[1];
            $returnData[$k]['time'] = $v[2];
            $returnData[$k]['record_id'] = $k;
            $returnData[$k]['info'] = $playerInfos;
            $userScore = [];
            foreach ($v[0] as $kk => $vv){
                $userScore[$vv['playerId']] = $vv['score'];
            }
            foreach ($returnData[$k]['info'] as $kkk => $vvv){
                $returnData[$k]['info'][$kkk]['score'] = $userScore[$vvv['userId']];
            }
        }
        return jsonRes(0, $returnData);
    }

    /**
     * 播放录像(playBack)返回的华为云的文件地址
     */
    public function getGamePlayBack(){
        $opt = ['playBack'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }

    }
}