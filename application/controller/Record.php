<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/28
 * Time: 19:31
 */
namespace app\controller;

use app\definition\Definition;
use app\model\UserEvaluateModel;
use Psr\Http\Message\ResponseInterface;
use Obs\ObsClient;
use think\Log;
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
                $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$v['room_id'], ['roomChecks', 'gameEndTime', 'playerInfos', 'roomOptions', 'gameEndInfo', 'roomCode', 'roomName']);
                $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);
                $gameEndInfo = json_decode($roomHashInfo['gameEndInfo'], true);
                # 处理数据
                $userScore = [];
                foreach ($gameEndInfo as $kk => $scoreInfo){
                    $userScore[$scoreInfo['playerId']] = $scoreInfo['totalScore'];
                }
                foreach ($roomUserInfo as $kkk => $userInfo){
                    $roomUserInfo[$kkk]['head_img'] = $roomUserInfo[$kkk]['headImgUrl'];
                    $roomUserInfo[$kkk]['nickname'] = $roomUserInfo[$kkk]['nickName'];
                    $roomUserInfo[$kkk]['player_id'] = $roomUserInfo[$kkk]['userId'];
                    $roomUserInfo[$kkk]['total_score'] = $userScore[$userInfo['userId']];
                }

                $return = [];
                $return['name'] = $roomHashInfo['roomName'];
                $return['player_infos'] = $roomUserInfo;
                $return['time'] = strtotime($roomHashInfo['gameEndTime']);
                $return['room_id'] = $v['room_id'];
                $return['options'] = json_decode($roomHashInfo['roomOptions'], true);
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

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['roundEndInfo', 'playerInfos']);
        $roundEndInfo = json_decode($roomHashInfo['roundEndInfo'], true);
        $playerInfos = json_decode($roomHashInfo['playerInfos'], true);

        if(!is_array($playerInfos) || !is_array($roundEndInfo)){
            return jsonRes(0, []);
        }

        $returnData = [];
        foreach ($roundEndInfo as $k => $v){
            foreach ($v[0] as $kk => $vv){
                $v[0][$kk]['player_id'] = $vv['playerId'];
            }

            $returnData[$k]['time'] = strtotime($v[2]);
            $returnData[$k]['record_id'] = $this->opt['room_id'].'|'.$v[1];
            $returnData[$k]['infos'] = $v[0];
        }
        return jsonRes(0, $returnData);
    }

    /**
     * 获取播放录像信息
     * @return \think\response\Json\
     */
    public function getGamePlayBack()
    {
        $opt = ['record_id'];
        if (!has_keys($opt, $this->opt)) {
            return jsonRes(3006);
        }

        $recordArr = explode('|', $this->opt['record_id']);
        $redis = new Redis();
        $redisHandle = $redis->handler();
        if (!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH . $recordArr[0])) {
            return jsonRes(3006);
        }

        $playChecks = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH . $recordArr[0], 'playChecks');

        $obsClient = new ObsClient([
            'key' => Definition::$OBS_KEY,
            'secret' => Definition::$OBS_SECRET,
            'endpoint' => Definition::$OBS_ENDPOINT
        ]);

        $playBackInfo = $obsClient->getObject([
            'Bucket' => Definition::$CHESS_RECORD_TEST,
            'Key' => $recordArr[1],
        ]);

        $returnData = [
            'room_check' => json_decode($playChecks, true),
            'data' => json_decode($playBackInfo['Body'], true)
        ];
        return jsonRes(0, $returnData);
    }

    /**
     * 观看录像给玩家评价（此方法暂时没有用）
     */
    public function addEvaluate(){
        $opt = ['type'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $user_id = getUserIdFromSession();
        switch ($this->opt['type']){
            case 0: //差评
                $res = $this -> saveEvaluateData($user_id , 'bad_num');
                if(!$res){
                    return jsonRes(3005);
                }
                break;
            case 1://好评
                $res = $this -> saveEvaluateData($user_id , 'good_num');
                var_dump($res);die;
                if(!$res){
                    return jsonRes(3005);
                }
                break;
            default:
                break;
        }
        return jsonRes(0);
    }

    /**
     * 保存修改评价
     * @return \think\response\Json\
     */
    private function saveEvaluateData($user_id , $evalType){
        $evaluateModle = new UserEvaluateModel();
        $evaluate = $evaluateModle ->getOneByWhere(['player_id'=>$user_id]);

        if(!$evaluate){
            $result = $evaluateModle ->saveData([
                'player_id' => $user_id,
                $evalType   => 1,
            ]);
            if(!$result){
                return false;
            }
        }else{
            $result = $evaluateModle ->saveData([
                $evalType   => $evaluate[$evalType] + 1,
            ],[
                'player_id' => $user_id,
            ]);
            if(!$result){
                return false;
            }
        }
        return true;
    }
}