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
use app\definition\RedisKey;
use app\model\TbUserVip;
use app\model\TbVipCard;

class Room extends Base
{
    #  创建房间
    public function createRoom(){
//        $checkUserToken = checkUserToken();
//        if(!$checkUserToken){
//            return jsonRes(9999);
//        }

//        $userSessionInfo = getUserSessionInfo();

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
        $roomOptions = json_decode($clubGameOpt['options']); # 具体规则
        $roomType =  $clubGameOpt['room_type']; # 房间类型
        $roomRate =  $clubGameOpt['room_rate']; # 扣开房人员钻石的规则
        $cheat = $clubGameOpt['cheat']; # 作弊

        $tbPlay = new TbPlay();
        $tbPlayInfo = $tbPlay->getInfoById($roomType);
        if(!$tbPlayInfo || !$tbPlayInfo['play'] || !$tbPlayInfo['name']){
            return jsonRes(3999);
        }

        $roomRule = $tbPlayInfo['play'];
        $roomName = $tbPlayInfo['name'];

        $clubId = $this->opt['club_id']; # 俱乐部ID
        $tbClub = new tbClub();
        $clubInfo = $tbClub->getInfoById($clubId);
        if(!$clubInfo || !$clubInfo['president_id']){
            return jsonRes(3999);
        }

        $clubType = $clubInfo['club_type']; # 开房房费结算类型 0扣开房人员  1扣会长
        $presidentId = $clubInfo['president_id'];


        if($clubType == 0){ # 扣用户资产 判断用户资产是否充足  玩家扣款中不可能出现免费房间，所以rate<0无需处理
            # 因为最终需要拿到房费 所以先计算房费
            if($roomRate == 0){
                $needDiamond  = $needDiamond/4; # AA后的房费
            }
            # 获取折扣
            $discount = 1;
            $tbUserVip = new TbUserVip();
            $userVipInfo = $tbUserVip->getInfoByUserIdAndClubId($userSessionInfo['userid'], $clubId);
            if($userVipInfo){
                $vipCardId = $userVipInfo['vid'];
                $tbVipCard = new TbVipCard();
                $vipCardInfo = $tbVipCard->getInfoById($vipCardId);
                if($vipCardInfo){
                    $discount = bcdiv($vipCardInfo['diamond_consumption'], 100, 1);
                }
            }
            $needDiamond = bcmul($discount, $needDiamond, 0); # 最终房费

            # 获取非绑定钻石数 判断是否能够开房
            $diamondNum = 0;
            $type = 10001;
            $diamondInfo = getUserProperty($userSessionInfo['userid'], $type);
            if($diamondInfo && isset($diamondInfo[0]['property_num'])){
                $diamondNum = $diamondInfo[0]['property_num'];
            }

            if($diamondNum < $needDiamond){ # 非绑定钻石不够 获取绑定钻石 判断是是否能够开房
                $bindingDiamondNum = 0;
                $type = 10002;
                $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], $type);
                if($bindingDiamondInfo && isset($bindingDiamondInfo[0]['property_num'])){
                    $bindingDiamondNum = $bindingDiamondInfo[0]['property_num'];
                }
                if($bindingDiamondNum < $needDiamond){ # 绑定钻石不够 判断钻石总和是否能够开房
                    $userAllDiamond = bcadd($bindingDiamondNum, $diamondNum, 0);
                    if($userAllDiamond < $needDiamond){ # 绑定钻石非绑定钻石相加不够
                        $resData['need_diamond'] = $needDiamond;
                        return jsonRes(23401, $resData);
                    }
                }
            }
        }


        if($clubType == 1){ # 扣会长资产 判断会长资产是否充足
            $userDiamond = 0;
            $diamondType = $clubId.'_'.$presidentId.'_10003';
            $playerId = $presidentId;
            $diamondInfo = getUserProperty($playerId, $diamondType);
            if($diamondInfo && isset($diamondInfo[0]['property_num'])){
                $userDiamond = $diamondInfo[0]['property_num'];
            }
            if($userDiamond < $needDiamond){
                $resData['need_diamond'] = $needDiamond;
                return jsonRes(23401, $resData);
            }
        }



        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        var_dump($roomNumber);die;
    }
}