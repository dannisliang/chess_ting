<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\controller;

use app\definition\Definition;
use app\model\RoomOptionsModel;
use app\model\PlayModel;
use app\model\ClubModel;
use think\cache\driver\Redis;
use app\definition\RedisKey;
use app\model\UserVipModel;
use app\model\VipCardModel;
use app\model\ClubSocketModel;
use app\model\GameServiceNewModel;
use app\model\UserRoomModel;
use app\model\ServiceGatewayNewModel;
use think\Session;

class Room extends Base
{
    #  创建房间
    public function createRoom(){
//        $this->opt['match_id'] = roomOptionId (各种玩法相关数据)
//        $this->opt['club_id'] = clubId (俱乐部相关数据)
        $sess = ['userid' => 552610];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);

        # 检查用户登录状态
//        $checkUserToken = checkUserToken();
//        if($checkUserToken || !isset($checkUserToken['result']) || !$checkUserToken['result']){
//            return jsonRes(9999);
//        }

        # 获取用户的session数据
        $userSessionInfo = getUserSessionInfo();

        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id']) || !is_numeric($this->opt['match_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        # 根据俱乐部ID获取俱乐部相关数据
        $club = new ClubModel();
        $clubInfo = $club->getClubInfoByClubId($this->opt['club_id']);
        if(!$clubInfo){
            return jsonRes(3500);
        }

        # 根据玩法规则ID获取规则
        $roomOptions = new RoomOptionsModel();
        $roomOptionsInfo = $roomOptions->getRoomOptionInfoByRoomOptionsId($this->opt['match_id']);
        if(!$roomOptionsInfo){
            return jsonRes(3501);
        }

        if(!in_array($roomOptionsInfo['room_type'], explode(',', $clubInfo['play_id']))){
            return jsonRes(3502);
        }

        # 根据房间类型ID获取房间玩法相关数据（大json）
        $play = new PlayModel();
        $playInfo = $play->getPlayInfoByPlayId($roomOptionsInfo['room_type']);
        if(!$playInfo){
            return jsonRes(3501);
        }

        # 玩法规则json解码
        $playInfoPlayJsonDecode = json_decode($playInfo['play'], true);

        # 房间规则json解码
        $roomOptionsInfoOptionsJsonDecode = json_decode($roomOptionsInfo['options'], true);

        # 获取房间开始需要的玩家数
        $roomNeedUserNum = getRoomNeedUserNum($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode);
        if(!$roomNeedUserNum){ # 解析不出人数
            return jsonRes(3503);
        }
        if(($clubInfo['club_type'] != 0) && ($clubInfo['club_type'] != 1)){
            return jsonRes(3504);
        }

        # 计算房费
        $needDiamond = $roomOptionsInfo['diamond'];
        if($clubInfo['club_type'] == 0){
            # 是否均分
            if($roomOptionsInfo['room_rate'] == 0){
                $needDiamond  = bcdiv($needDiamond, $roomNeedUserNum, 0);
            }

            # 获取折扣
            $userVip = new UserVipModel();
            $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $this->opt['club_id']);
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
                if($vipCardInfo){
                    $discount = bcdiv($vipCardInfo['diamond_consumption'], 100, 1);
                    $needDiamond = bcmul($discount, $needDiamond, 0);
                }
            }

            # 获取非绑定钻石数 判断是否能够开房
            $diamondNum = 0;
            $propertyType = Definition::$USER_PROPERTY_TYPE_NOT_BINDING;
            $diamondInfo = getUserProperty($userSessionInfo['userid'], $propertyType);

            if($diamondInfo && isset($diamondInfo['data'][0]['property_num'])){
                $diamondNum = $diamondInfo['data'][0]['property_num'];
            }
            if($diamondNum < $needDiamond){
                $bindingDiamondNum = 0;
                $propertyType = Definition::$USER_PROPERTY_TYPE_BINDING;
                $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], $propertyType);
                if($bindingDiamondInfo && isset($bindingDiamondInfo['data'][0]['property_num'])){
                    $bindingDiamondNum = $bindingDiamondInfo['data'][0]['property_num'];
                }
                $userAllDiamond = bcadd($bindingDiamondNum, $diamondNum, 0);
                if($userAllDiamond < $needDiamond){
                    $resData['need_diamond'] = $needDiamond;
                    return jsonRes(23401, $resData);
                }
            }
        }

        # 扣会长资产 判断会长资产是否充足
        if($clubInfo['club_type'] == 1){
            $userDiamond = 0;
            $propertyType = $this->opt['club_id'].'_'.$clubInfo['president_id'].'_'.Definition::$USER_PROPERTY_PRESIDENT;
            $diamondInfo = getUserProperty($clubInfo['president_id'], $propertyType);
            if($diamondInfo && isset($diamondInfo['data'][0]['property_num'])){
                $userDiamond = $diamondInfo['data'][0]['property_num'];
            }
            if($userDiamond < $needDiamond){
                $resData['need_diamond'] = $needDiamond;
                return jsonRes(23401, $resData);
            }
        }

        # 接口返回值
        $returnArr = [
            'need_gold' => $needDiamond, # 所需钻石
            'check' => $playInfoPlayJsonDecode['checks'], # play表的json的checks
            'options' => $roomOptionsInfoOptionsJsonDecode, # room_options表的options
            'room_num' => '', # 房间号
            'socket_ssl' => '',
            'socket_h5' => '',
            'socket_url' =>  '',
        ];

        # 根据俱乐部ID获取俱乐部socket通道
        $clubSocket = new ClubSocketModel();
        $clubSocketInfo = $clubSocket->getClubSocketInfoByClubId($this->opt['club_id']);
        if($clubSocketInfo){
            $serviceId = 1;
            $createRoomUrl = $clubSocketInfo['room_url'];
            $returnArr['socket_h5'] = $clubSocket['socket_h5'];
            $returnArr['socket_url'] = $clubSocket['socket_url'];
        }else{
            $gameServiceNew = new GameServiceNewModel();
            $gameServiceNewInfos = $gameServiceNew->getGameServiceNewInfosByRoomTypeId($roomOptionsInfo['room_type']);
            $serviceRoomNumArr = [];
            $userRoom = new UserRoomModel();
            foreach ($gameServiceNewInfos as $k => $v){
                $serviceRoomNum = $userRoom->getServiceRoomNumByServiceId($v['service_id']);
                $serviceRoomNumArr[$v['service_id']] = $serviceRoomNum;
            }
            $serviceId = array_search(min($serviceRoomNumArr), $serviceRoomNumArr);
            $serviceGatewayNew = new ServiceGatewayNewModel();
            $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($serviceId);
            if(!$serviceGatewayNewInfo){
                $createRoomUrl = $serviceGatewayNewInfo['service'];
                $returnArr['socket_h5'] = $serviceGatewayNewInfo['gateway_h5'];
                $returnArr['socket_url'] = $serviceGatewayNewInfo['gateway_app'];
            }else{
                $createRoomUrl = Definition::$ROOM_URL;
                $returnArr['socket_h5'] = Definition::$SOCKET_H5;
                $returnArr['socket_url'] = Definition::$SOCKET_URL;
            }
        }

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        if(!$roomNumber){
            return jsonRes(23205);
        }
        $returnArr['room_num'] = $roomNumber;

        # Redis写数据
        $playerIds = json_encode([$userSessionInfo['userid']]); # 房间的用户ID集
        $playerIps = json_encode([[$userSessionInfo['userid'] => getUserIp()]]); # 房间的用户IP集
        $needDiamonds = json_encode([[$userSessionInfo['userid'] => $needDiamond]]); # 需要支付的钻石集
        $redisHashValue = [
            'createTime' => date('Y-m-d H:i:s'), # 房间创建时间
            'owner' => $userSessionInfo['userid'], # 房间创始人
            'roomNeedUserNum' => $roomNeedUserNum, # 房间需要的人数
            'serviceId' => $serviceId, # 服务器ID
            'diamond' => $roomOptionsInfo['diamond'], # 进房需要的钻石  没均分没折扣的值
            'playerNum' => 1, # 当前玩家数
            'joinStatus' => 1, # 其他人是否能够申请加入
            'clubId' => $this->opt['club_id'], # 俱乐部ID
            'clubType' => $clubInfo['club_type'], # 俱乐部结算类型 免费房间和不免费房间 凌驾于roomRate之上
            'roomRate' => $roomOptionsInfo['room_rate'], # 房间结算类型 大赢家/房主/均摊
            'roomCheat' => $roomOptionsInfo['cheat'], # 是否检查GPS房间
            'roomType' => $roomOptionsInfo['room_type'], # 规则表中的类型 对应play表Id
            'roomOptionsId' => $this->opt['match_id'], # roomOptionsID
            'socketH5' => $returnArr['socket_h5'], # H5的socket连接地址
            'socketUrl' => $returnArr['socket_url'], # socket的连接地址
            'playChecks' => json_encode($playInfoPlayJsonDecode['checks']), # 玩法数据中的play的checks json
            'diamonds' => $needDiamonds, # 每个用户需要扣的钻石集 json
            'playerIds' => $playerIds, # 房间的用户ID集 json
            'playerIps' => $playerIps, # 玩家IP地址集 json
            'roomOptions' => $roomOptionsInfo['options'], # 玩法相关数据 json
        ];
        $setUserRoom = $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $roomNumber);
        if(!$setUserRoom){
            return jsonRes(23205);
        }
        $hSetRes = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $redisHashValue);
        if(!$hSetRes){
            $delRes = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            if(!$delRes){
                delErrorLog(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            }
            return jsonRes(23205);
        }
        $sAddRes = $redisHandle->sadd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
        if(!$sAddRes){
            $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber);
            $delRes = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            if(!$delRes){
                delErrorLog(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            }
            return jsonRes(23205);
        }


        # 会长模式提前结算
        if($clubInfo['club_type'] == 1){
            $subDiamond = bcsub(0, $needDiamond, 0);
            $operaRes = operaUserProperty($clubInfo['president_id'], $propertyType, $subDiamond);
            if(!$operaRes || !isset($operaRes['code']) || ($operaRes['code'] != 0)){
                $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber);
                $sRemRes = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                if(!$sRemRes){
                    sRemErrorLog(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                }
                $delRes = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
                if(!$delRes){
                    delErrorLog(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
                }
                return jsonRes(23205);
            }
        }

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($createRoomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        p($createRoomInfo);
        if(!$createRoomInfo || !isset($createRoomInfo['content']['result']) || ($createRoomInfo['content']['result'] != 0)){ # 创建房间失败
            $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber);
            $sRemRes = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
            if(!$sRemRes){
                sRemErrorLog(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
            }
            $delRes = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            if(!$delRes){
                delErrorLog(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid']);
            }
            if($clubInfo['club_type'] == 1){ # 还钻
                operaUserProperty($clubInfo['president_id'], $propertyType, $needDiamond);
            }
            return jsonRes(23205);
        }

        # 返回客户端
        return jsonRes(0, $returnArr);
    }


    # 玩家加入房间
    public function joinRoom(){
        $sess = ['userid' => 552610];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);
        # 检查用户登录状态
//        $checkUserToken = checkUserToken();
//        if($checkUserToken || !isset($checkUserToken['result']) || !$checkUserToken['result']){
//            return jsonRes(9999);
//        }

        if(!isset($this->opt['room_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 获取房间人数
        $roomHashValue = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id']);

        $issetRoom = $redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id']);
        if(!$issetRoom){
            return jsonRes(23202);
        }

        # 判断房间是否满员
        $joinStatus = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'joinStatus');
        if(!$joinStatus){ # 已经满员
            return jsonRes(23204);
        }

        # 获取房间信息中的俱乐部ID 判断玩家是否加入该俱乐部
        $roomClubId = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'clubId');
//        $isJoinClub =
    }

    # 强制解散房间
    public function disBandRoom(){
        $userSessionInfo = getUserSessionInfo();
        print_r(disBandRoom('http://192.168.9.18:9910/', $userSessionInfo['userid'], 994839));die;
    }
}