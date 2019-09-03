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
use Obs\ObsException;
use Psr\Http\Message\ResponseInterface;
use Obs\ObsClient;
use think\Env;
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

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $rangeStart = bcsub(time(), bcmul(bcmul(3600, 24, 0), 3, 0), 0);
        // todo 清理用户三天前的房间记录， 已经无用的记录
        $redisHandle->zRemRangeByScore(RedisKey::$USER_ROOM_RECORD.$userSessionInfo['userid'], 0, bcsub($rangeStart, 1, 0));

        $userRoomRecord = $redisHandle->zRangeByScore(RedisKey::$USER_ROOM_RECORD.$userSessionInfo['userid'], $rangeStart, time());
        if(!$userRoomRecord){
            return jsonRes(0, []);
        }

        $returnData = [];
        foreach ($userRoomRecord as $k => $v){
            $gameInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$v, ['playerInfos', 'gameEndInfo', 'playChecks', 'roomName', 'gameEndTime', 'roomOptions', 'roomCode']);
            $roomUserInfo = json_decode($gameInfo['playerInfos'], true);
            $gameEndInfo = json_decode($gameInfo['gameEndInfo'], true);
            # 处理数据

            $userScore = [];
            foreach ($gameEndInfo as $kk => $vv){
                $userScore[$vv['playerId']] = $vv['totalScore'];
            }

            $roomUsers = [];

            foreach ($roomUserInfo as $kkk => $vvv){
                $roomUsers[] = [
                    'head_img' => $vvv['headImgUrl'],
                    'nickname' => $vvv['nickName'],
                    'player_id' => $vvv['userId'],
                    'total_score' => isset($userScore[$vvv['userId']]) ? $userScore[$vvv['userId']] : 0,
                ];
            }

            $return = [];
            $return['check'] = json_decode($gameInfo['playChecks'], true);
            $return['name'] = $gameInfo['roomName'];
            $return['player_infos'] = $roomUsers;
            $return['time'] = strtotime($gameInfo['gameEndTime']);
            $return['room_id'] = $v;
            $return['options'] = json_decode($gameInfo['roomOptions'], true);
            $return['room_code'] = $gameInfo['roomCode'];
            $returnData[] = $return;

        }
        return jsonRes(0, $returnData);
    }

    # 获取房间的牌局记录
    public function getRecordList(){
        if(!isset($this->opt['room_id']) || !is_numeric($this->opt['room_id'])){
            return jsonRes(3006);
        }

        $sessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$sessionInfo){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $returnData = [];

        $gameInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['roundEndInfo', 'playerInfos']);

        $roundEndInfo = json_decode($gameInfo['roundEndInfo'], true);
        $playerInfos = json_decode($gameInfo['playerInfos'], true);

        if(!is_array($playerInfos) || !is_array($roundEndInfo)){
            return jsonRes(0, []);
        }

        foreach ($roundEndInfo as $k => $v){
            foreach ($v['score'] as $kk => $vv){
                $v['score'][$kk]['player_id'] = $vv['playerId'];
            }

            $returnData[$k]['time'] = strtotime($v['roundEndTime']);
            $returnData[$k]['record_id'] = $this->opt['room_id'].'|'.$v['roundId'];
            $returnData[$k]['infos'] = $v['score'];
        }
        return jsonRes(0, $returnData);
    }

    # 获取播放录像信息
    public function getGamePlayBack()
    {
        $opt = ['record_id'];
        if (!has_keys($opt, $this->opt)) {
            return jsonRes(3006);
        }

        $recordArr = explode('|', $this->opt['record_id']);

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $returnData = [];

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$recordArr[0], ['playChecks', 'playerInfos']);
        $playerInfos = json_decode($roomHashInfo['playerInfos'], true);
        if(!$playerInfos){
            return jsonRes(3006);
        }

        $userInfos = [];
        foreach ($playerInfos as $k => $userInfo){
            $userInfos[$k]['playerId'] = $userInfo['userId'];
            $userInfos[$k]['nickname'] = $userInfo['nickName'];
            $userInfos[$k]['headImgUrl'] = $userInfo['headImgUrl'];
            $userInfos[$k]['sex'] = $userInfo['sex'];
            $userInfos[$k]['vip'] = $userInfo['vipId'];
            $userInfos[$k]['ip'] = $userInfo['ipAddr'];
            $userInfos[$k]['good_num'] = 0;
            $userInfos[$k]['bad_num'] = 0;
        }

        try{
            $obsClient = new ObsClient([
                'key' => Env::get('obs.key'),
                'secret' => Env::get('obs.secret'),
                'endpoint' => Env::get('obs.endpoint')
            ]);

            $playBackInfo = $obsClient->getObject([
                'Bucket' => Env::get('obs.chess_record'),
                'Key' => $recordArr[1],
            ]);

            $returnData = [
                'room_check' => json_decode($roomHashInfo['playChecks'], true),
                'data' => json_decode($playBackInfo['Body'], true),
                'user_info' => $userInfos
            ];
        }catch (ObsException $obsException){

        }
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
     * @return bool|\think\response\Json\
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