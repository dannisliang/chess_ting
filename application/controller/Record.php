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

        # 获取所有的用户玩过的房间
        $userClubRoomRecord = new UserClubRoomRecordModel();
        $userClubRoomRecordInfo = $userClubRoomRecord->getUserClubRoomRecord($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userClubRoomRecordInfo){
            return jsonRes(0, []);
        }

        $obsClient = new ObsClient([
            'key' => Env::get('obs.key'),
            'secret' => Env::get('obs.secret'),
            'endpoint' => Env::get('obs.endpoint')
        ]);

        $returnData = [];
        foreach ($userClubRoomRecordInfo as $k => $v){
            $gameInfo = $obsClient->getObject([
                'Bucket' => Env::get('obs.chess_record'),
                'Key' => $v['add_time'].$v['room_id'],
            ]);
            $body = json_decode($gameInfo['Body'], true);
            $roomUserInfo = json_decode($body['playerInfos'], true);
            $gameEndInfo = json_decode($body['gameEndInfo'], true);
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
            $return['check'] = json_decode($body['playChecks'], true);
            $return['name'] = $body['roomName'];
            $return['player_infos'] = $roomUserInfo;
            $return['time'] = strtotime($body['gameEndTime']);
            $return['room_id'] = $v['room_id'];
            $return['options'] = json_decode($body['roomOptions'], true);
            $return['room_code'] = $body['roomCode'];
            $returnData[] = $return;
        }
        return jsonRes(0, $returnData);
    }

    # 获取房间的牌局记录
    public function getRecordList(){
        if(!isset($this->opt['room_id'])){
            return jsonRes(3006);
        }

        $sessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$sessionInfo){
            return jsonRes(3006);
        }
        $userClubRoomRecord = new UserClubRoomRecordModel();
        $record = $userClubRoomRecord->getOneRecord($sessionInfo['userid'], $this->opt['room_id']);
        if(!$record){
            return jsonRes(3006);
        }

        $this->opt['room_id'] = $record['add_time'].$this->opt['room_id'];

        $obsClient = new ObsClient([
            'key' => Env::get('obs.key'),
            'secret' => Env::get('obs.secret'),
            'endpoint' => Env::get('obs.endpoint')
        ]);

        $gameInfo = $obsClient->getObject([
            'Bucket' => Env::get('obs.chess_record'),
            'Key' => $this->opt['room_id'],
        ]);
        $body = json_decode($gameInfo['Body'], true);

        $roundEndInfo = json_decode($body['roundEndInfo'], true);
        $playerInfos = json_decode($body['playerInfos'], true);

        if(!is_array($playerInfos) || !is_array($roundEndInfo)){
            return jsonRes(0, []);
        }

        $returnData = [];
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
        $obsClient = new ObsClient([
            'key' => Env::get('obs.key'),
            'secret' => Env::get('obs.secret'),
            'endpoint' => Env::get('obs.endpoint')
        ]);

        $gameInfo = $obsClient->getObject([
            'Bucket' => Env::get('obs.chess_record'),
            'Key' => $recordArr[0],
        ]);
        $body = json_decode($gameInfo['Body'], true);

        $playerInfos = json_decode($body['playerInfos'], true);
        if(!$playerInfos){
            return jsonRes(3006);
        }
        # 用户数数据
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

        $playBackInfo = $obsClient->getObject([
            'Bucket' => Env::get('obs.chess_record'),
            'Key' => $recordArr[1],
        ]);

        $returnData = [
            'room_check' => json_decode($body['playChecks'], true),
            'data' => json_decode($playBackInfo['Body'], true),
            'user_info' => $userInfos
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