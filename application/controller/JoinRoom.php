<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:31
 */

namespace app\controller;

use think\Log;
use think\Session;
use app\model\VipCardModel;
use app\model\UserVipModel;
use app\model\UserClubModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;


class JoinRoom extends Base
{

    public function joinRoom(){
        if(!isset($this->opt['room_id']) || !is_numeric($this->opt['room_id'])){
            return json(['code' => -10000, 'mess' => '系统繁忙']);
        }

        # 获取session数据
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return json(['code' => 9999, 'mess' => '请重新登录']);
        }

        $checkTokenRes = checkUserToken($userSessionInfo);
        if(!isset($checkTokenRes['result']) || ($checkTokenRes['result'] == false)){
            return json(['code' => 9999, 'mess' => '请重新登录']);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        # 获取房间信息中的俱乐部ID
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['diamond', 'needUserNum', 'clubType', 'roomRate', 'clubId', 'roomUrl']);
        if(!$roomHashInfo['roomUrl']){
            return json(['code' => 23202, 'mess' => '您要加入的房间不存在']);
        }

        # 获取玩家vip卡
        $userVip = new UserVipModel();
        $userVipInfo = $userVip->getUserVipInfo($userSessionInfo['userid'], $roomHashInfo['clubId']);
        # 计算房费
        $needDiamond = $roomHashInfo['diamond']; # 基础房费
        $diamondInfo = [
            'bind' => 0,
            'noBind' => 0,
        ];
        if($roomHashInfo['clubType'] == 0){
            # 是否均分
            if($roomHashInfo['roomRate'] == 0){
                $needDiamond  = bcdiv($needDiamond, $roomHashInfo['needUserNum'], 0);
            }

            # 获取折扣
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfo($userVipInfo['vid']);
                if($vipCardInfo){
                    $needDiamond = bcmul($needDiamond, bcdiv($vipCardInfo['diamond_consumption'], 100, 1), 0);
                }
            }

            if($needDiamond > 0){ # 需要扣钻
                # 获取钻石数 判断是否能够开房
                $userDiamondInfo = getUserProperty($userSessionInfo['userid'], [Definition::$USER_PROPERTY_TYPE_NOT_BINDING, Definition::$USER_PROPERTY_TYPE_BINDING]);
                if(!isset($userDiamondInfo['code']) || ($userDiamondInfo['code'] != 0)){
                    $returnData = [
                        'need_diamond' => $needDiamond
                    ];
                    return json(['code' => 40002, 'mess' => '钻石不足，无法进入房间', 'data' => $returnData]);
                }

                $noBindDiamond = 0;
                $bindDiamond = 0;
                foreach ($userDiamondInfo['data'] as $k => $v){
                    if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_NOT_BINDING){
                        $noBindDiamond = $v['property_num'];
                    }
                    if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_BINDING){
                        $bindDiamond = $v['property_num'];
                    }
                }

                if($noBindDiamond >= $needDiamond){
                    $diamondInfo['noBind'] = $needDiamond;
                }else{
                    if($noBindDiamond > 0){
                        $diamondInfo['noBind'] = $noBindDiamond;
                    }
                    if(bcadd($bindDiamond, $noBindDiamond, 0) >= $needDiamond){
                        if(isset($diamondInfo['noBind'])){
                            $diamondInfo['bind'] = bcsub($needDiamond, $diamondInfo['noBind'], 0);
                        }else{
                            $diamondInfo['bind'] = $needDiamond;
                        }
                    }else{
                        $returnData = [
                            'need_diamond' => $needDiamond
                        ];
                        return json(['code' => 40002, 'mess' => '钻石不足，无法进入房间', 'data' => $returnData]);
                    }
                }
            }
        }

        # 请求逻辑服加入房间
        $joinRoomInfo = sendHttpRequest($roomHashInfo['roomUrl'].Definition::$JOIN_ROOM.$userSessionInfo['userid'], ['roomId' => $this->opt['room_id']]);
//        p($joinRoomInfo);
        if(!isset($joinRoomInfo['content']['result'])){
            return json(['code' => 3517, 'mess' => '逻辑服返回其他原因导致加入房间失败']);
        }else{
            if($joinRoomInfo['content']['result'] != 0){
                $logData = [
                    $this->opt['room_id'],
                    $userSessionInfo['userid']
                ];
                Log::write(json_encode($logData), '用户加入房间异常');

                if($joinRoomInfo['content']['result'] == 10002){
                    return json(['code' => 9999, 'mess' => '请重新登录']);
                }

                if($joinRoomInfo['content']['result'] == 10000){
                    return json(['code' => 23202, 'mess' => '您要加入的房间不存在']);
                }

                if($joinRoomInfo['content']['result'] == 10001){
                    return json(['code' => 23204, 'mess' => '您要加入的房间人数已满']);
                }

                return json(['code' => 3517, 'mess' => '逻辑服返回其他原因导致加入房间失败']);
            }
        }

        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'].'lock';
        $getLock = $this->getLock($redisHandle, $lockKey);

        if($getLock){ # 拿到锁处理数据并解锁
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['needUserNum', 'playerInfos', 'socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl', 'clubName', 'clubId']);
            # 重写hash中用户信息
            $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);
            $userInfo = [
                'userId' => $userSessionInfo['userid'],
                'nickName' => $userSessionInfo['nickname'],
                'headImgUrl' => $userSessionInfo['headimgurl'],
                'ipAddr' => $userSessionInfo['ip'],
                'sex' => $userSessionInfo['sex'],
                'vipId' => isset($userVipInfo['vid']) ? $userVipInfo['vid'] : '',
                'clientId' => '-',
                'clientType' => $userSessionInfo['client_type'],
                'systemType' => $userSessionInfo['app_type'],
                'needDiamond' => $diamondInfo,
            ];
            $roomUserInfo[] = $userInfo;

            # 房间人数
            $userNum = count($roomUserInfo); # 获取房间人数
            if($userNum >= $roomHashInfo['needUserNum']){
                $setHashInfo = [
                    'joinStatus' => 0,
                    'playerInfos' => json_encode($roomUserInfo)
                ];
                $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], $setHashInfo);
            }else{
                $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁
            $returnData = [
                'need_gold' => $needDiamond, # 需要的钻石
                'room_num' => $this->opt['room_id'], # 房间号
                'check' => json_decode($roomHashInfo['playChecks'], true), #
                'options' => json_decode($roomHashInfo['roomOptions'], true), # 规则
                'socket_h5' => $roomHashInfo['socketH5'], # H5链接地址
                'socket_url' => $roomHashInfo['socketUrl'], # app链接地址
                'socket_ssl' => $roomHashInfo['socketSsl'], # 证书
                'club_id' => $roomHashInfo['clubId'],
                'club_name' => $roomHashInfo['clubName'],
            ];
            return json(['code' => 0, 'mess' => '成功', 'data' => $returnData]);
        }else{
            $logData = [
                $this->opt['room_id'],
                $userSessionInfo['userid'],

            ];
            Log::write(json_encode($logData), '加入房间获取锁超时');
        }
    }
}