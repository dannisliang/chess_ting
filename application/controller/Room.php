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
        $roomRate =  $clubGameOpt['room_rate']; # 房间算力
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

        $clubType = $clubInfo['club_type'];
        $presidentId = $clubInfo['president_id'];

        # 此处代码需要优化 基本完全copy过来  只分析了一下逻辑
        if($clubType > 0){ # 不是免费模式 需要判断资产
            if($clubType == 0){ # 扣用户资产 判断用户资产是否充足
                $bindingDiamondNum = 0; # 绑定钻石数
                $type = 10002;
                $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], $type);
                if($bindingDiamondInfo && isset($bindingDiamondInfo[0]['property_num'])){
                    $bindingDiamond = $bindingDiamondInfo[0]['property_num'];
                }

                $diamondNum = 0; # 非绑定钻石数
                $type = 10001;
                $diamondInfo = getUserProperty($userSessionInfo['userid'], $type);
                if($diamondInfo && isset($diamondInfo[0]['property_num'])){
                    $diamondNum = $diamondInfo[0]['property_num'];
                }
                $userAllDiamond = bcadd($bindingDiamondNum, $diamondNum, 0); # 玩家全部钻石


                if($roomRate == 0){ # AA扣款，房费均摊

                }


                // TODO - process table options like collation etc
                # 获取玩家折扣相关数据
                $tbUserVip = new TbUserVip();
                $userVipInfo = $tbUserVip->getInfoByUserIdAndClubId($playerId, $clubId);
                $discount = 1; # 默认不打折
                if($userVipInfo){
                    $vipCardId = $userVipInfo['vid'];
                    # 获取折扣数据
                    $tbVipCard = new TbVipCard();
                    $vipCardInfo = $tbVipCard->getInfoById($vipCardId);
                    if($vipCardInfo){
                        $discount = bcdiv($vipCardInfo['diamond_consumption'], 100, 1);
                    }
                }
                // 判断玩家钻石是否充足
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
                    return jsonRes(23401);
                }
            }
        }
        ## 此处代码需要优化

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        var_dump($roomNumber);die;
    }
}