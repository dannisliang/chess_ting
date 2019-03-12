<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\controller;

use app\model\TbRoomOptions;
use app\model\TbPlay;
use app\model\TbClub;
use think\cache\driver\Redis;
use think\Session;
use app\definition\RedisKey;

class Room extends Base
{

    #  创建房间
    public function createRoom(){
        Session::set('player', 10000);
        $playerId = Session::get("player");
        if (!$playerId){
            return jsonRes(9999);
        }

        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $matchId = $this->opt['match_id']; # 玩法ID
        $TbRoomOptions = new TbRoomOptions();
        $clubGameOpt = $TbRoomOptions->getInfoById($matchId);
        if(!$clubGameOpt){
            return jsonRes(3999);
        }

        $needDiamond = $clubGameOpt['diamond']; # 房费
        $oldDiamond = $needDiamond; # 房费
        $roomOptions = $clubGameOpt['options']; # 具体规则
        $roomOptions = json_decode($roomOptions); # 具体规则
        $roomType =  $clubGameOpt['room_type']; # 房间类型
        $roomRate =  $clubGameOpt['room_rate']; # 房间算力
        $cheat = $clubGameOpt['cheat']; # 作弊

        $tbPlay = new TbPlay();
        $tbPlayInfo = $tbPlay->getInfoById($roomType);
        if(!$tbPlayInfo || !$tbPlayInfo['play'] || !$tbPlayInfo['name']){
            return jsonRes(3999);
        }
        $roomRule = $tbPlayInfo['play'];
        $roomName = $tbPlayInfo['name'];

        $clubId = $opt['club_id']; # 俱乐部ID
        $tbClub = new tbClub();
        $clubInfo = $tbClub->getInfoById($clubId);
        if(!$clubInfo || !$clubInfo['club_type'] || !$clubInfo['president_id']){
            return jsonRes(3999);
        }

        $clubType = $clubInfo['club_type'];
        $presidentId = $clubInfo['president_id'];

        # 判断是A模式还是B模式,A模式判断玩家是否货币充足,B模式判断会长货币是否充足
        if($clubType == 0){ # 扣用户资产 判断用户资产是否充足
            $bindingDiamondNum = 0; # 绑定钻石数
            $type = 10002;
            $bindingDiamondInfo = getUserProperty($playerId, $type);
            if($bindingDiamondInfo && isset($bindingDiamondInfo[0]['property_num'])){
                $bindingDiamond = $bindingDiamondInfo[0]['property_num'];
            }

            $diamondNum = 0; # 非绑定钻石数
            $type = 10001;
            $diamondInfo = getUserProperty($playerId, $type);
            if($diamondInfo && isset($diamondInfo[0]['property_num'])){
                $diamondNum = $diamondInfo[0]['property_num'];
            }
            $userAllDiamond = bcadd($bindingDiamondNum, $diamondNum, 0);
        }

        if($clubType == 1){ # 扣会长资产 判断会长资产是否充足
            $userDiamond = 0;
            $diamondType = $clubId.'_'.$presidentId.'_10003';
            $playerId = $presidentId;
            $playerDiamond = getUserProperty($playerId, $diamondType);
            if($diamondInfo && isset($diamondInfo[0]['property_num'])){
                $userDiamond = $diamondInfo[0]['property_num'];
            }
            if($userDiamond < $needDiamond){
                $resData['need_diamond'] = $needDiamond;
                return jsonRes(23401);
            }
        }

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        var_dump($roomNumber);die;
    }
}