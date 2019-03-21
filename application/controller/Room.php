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
use app\model\UserClubModel;

class Room extends Base
{
    # 玩家加入房间回调完成
    public function joinRoomCallBack()
    {
        return jsonRes(0);
    }
    # 强制解散玩家房间完成  还钻没有完成
    public function disBandRoom(){
        if(!isset($this->opt['uid']) || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        $isDisBand = false; # 标记没有解散
        $roomId = ''; # 房间ID

        # 先在redis查找数据
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $userRoom = $redisHandle->get(RedisKey::$USER_ROOM_KEY.$this->opt['uid']);
        if($userRoom){
            $roomUrl = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$userRoom, 'roomUrl');
            if($roomUrl){
                # 请求逻辑服解散房间
                $disBandRes = sendHttpRequest($roomUrl.Definition::$DIS_BAND_ROOM.$userRoom, ['playerId' => $this->opt['uid']]);
                if($disBandRes && isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                    $isDisBand = true;
                    $roomId = $userRoom;
                }
            }
        }

        # 依靠redis没能解散房间
        if(!$isDisBand){
            $gameServiceNew = new GameServiceNewModel();
            $gameServiceNewInfos = $gameServiceNew->getGameServiceNewInfos();
            $serviceGatewayNew = new ServiceGatewayNewModel();
            foreach ($gameServiceNewInfos as $k => $v){
                $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($v['service_id']);
                $userRoom = sendHttpRequest($serviceGatewayNewInfo['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
                if($userRoom && isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                    $disBandRes = sendHttpRequest($serviceGatewayNewInfo['service'].Definition::$DIS_BAND_ROOM.$userRoom['content']['roomId'], ['playerId' => $this->opt['uid']]);
                    if($disBandRes && isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                        $isDisBand = true;
                        $roomId = $userRoom['content']['roomId'];
                    }
                }
            }
        }

        # 解散失败
        if(!$isDisBand){
            return jsonRes(3508);
        }

        # 清数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomId, ['clubId', 'playerInfos', 'roomPlayInfo', 'clubType', 'needDiamond', 'roomOptionsId']);
        if(isset($roomHashInfo['clubId']) && $roomHashInfo['clubId']){
            $sRemRes = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfo['clubId'], $roomId);
            if(!$sRemRes){
                $errorData = [
                    $roomHashInfo['clubId'],
                    $roomId
                ];
                errorLog(Definition::$DEL_CLUB_ROOM, $errorData);
            }
        }

        if(isset($roomHashInfo['playerInfos']) && $roomHashInfo['playerInfos']){
            # 加锁删用户所在房间的记录
            $roomUserInfos = json_decode($roomHashInfo['playerInfos'], true);
            foreach ($roomUserInfos as $userId => $val){
                # 使用redis锁处理
                $getLock = false;
                $timeOut = bcadd(time(), 2, 0);
                $lockKey = RedisKey::$USER_ROOM_KEY.$userId.'lock';
                while(!$getLock){
                    if(time() > $timeOut){
                        break;
                    }
                    $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
                    if($getLock){
                        break;
                    }
                }
                if($getLock){
                    if($redisHandle->get(RedisKey::$USER_ROOM_KEY.$userId) == $roomId){ # 判断用户当前房间是否是被解散的房间
                        $res = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userId); # 删除用户所在房间
                        if(!$res){
                            $errorData = [
                                $userId,
                                $roomId
                            ];
                            errorLog(Definition::$DEL_USER_ROOM, $errorData);
                        }
                    }
                    $redisHandle->del($lockKey); # 解锁
                }else{
                    $errorData = [
                        $userId,
                        $roomId
                    ];
                    errorLog(Definition::$DEL_USER_ROOM, $errorData);
                }
            }
        }

        # 还钻
        if(isset($roomHashInfo['clubId']) && $roomHashInfo['clubId'] && isset($roomHashInfo['roomPlayInfo']) && $roomHashInfo['roomPlayInfo'] && isset($roomHashInfo['clubType']) && $roomHashInfo['clubType'] && ($roomHashInfo['clubType'] == 1) && isset($roomHashInfo['needDiamond']) && $roomHashInfo['needDiamond'] && isset($roomHashInfo['roomOptionsId']) && $roomHashInfo['roomOptionsId']){
            $roomPlayInfo = json_decode($roomHashInfo['roomPlayInfo'], true);
            if(is_array($roomPlayInfo)){ # 判断是否是数组
                $round = count($roomPlayInfo);
                if($round < 1){ # 一局都没玩
                    $propertyType = $roomHashInfo['clubId'].'_'.$roomHashInfo['roomOptionsId'].'_'.Definition::$USER_PROPERTY_PRESIDENT;
                    operaUserProperty($roomHashInfo['roomOptionsId'], $propertyType, $roomHashInfo['needDiamond']);
                }
            }
        }
        return jsonRes(3507);
    }
    # 获取gps相关信息完成
    public function getRoomGpsInfo(){
        if(isset($this->opt['room_id']) && $this->opt['room_id'] && is_numeric($this->opt['room_id'])){
            $redis = new Redis();
            $redisHandle = $redis->handler();
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['isGps', 'gpsRange']);
            if(!$roomHashInfo){
                return jsonRes(3505); # 房间不存在
            }
            $returnData = [
                'room_cheat' => $roomHashInfo['isGps'],
                'gps_range' => $roomHashInfo['gpsRange']
            ];
            return jsonRes(0, $returnData);
        }

        if(isset($this->opt['match_id']) && $this->opt['match_id'] && is_numeric($this->opt['match_id'])){
            $roomOptions = new RoomOptionsModel();
            $roomOptionsInfo = $roomOptions->getRoomOptionInfoByRoomOptionsId($this->opt['match_id']);
            if(!$roomOptionsInfo){
                return jsonRes(3501);
            }

            $club = new ClubModel();
            $clubInfo = $club->getClubInfoByClubId($roomOptionsInfo['club_id']);
            if(!$clubInfo){
                return jsonRes(3500);
            }

            $returnData = [
                'room_cheat' => $roomOptionsInfo['cheat'],
                'gps_range' => $clubInfo['gps']
            ];
            return jsonRes(0, $returnData);
        }

        return jsonRes(3006); # 请求参数有误
    }
    # 查询玩家所在的房间完成
    public function getUserRoom(){
        if(!isset($this->opt['uid']) || !$this->opt['uid'] || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        # 先在redis查
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $userRoom = $redisHandle->get(RedisKey::$USER_ROOM_KEY.$this->opt['uid']);
        if($userRoom){
            return jsonRes(0, [$userRoom]);
        }

        # 去逻辑服获取玩家所在房间
        $gameServiceNew = new GameServiceNewModel();
        $gameServiceNewInfos = $gameServiceNew->getGameServiceNewInfos();
        $serviceGatewayNew = new ServiceGatewayNewModel();
        foreach ($gameServiceNewInfos as $k => $v){
            $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($v['service_id']);
            $userRoom = sendHttpRequest($serviceGatewayNewInfo['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
            if($userRoom && isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                return jsonRes(0, [$userRoom['content']['roomId']]);
            }
        }
        return jsonRes(3509);
    }
    # 创建房间完成
    public function createRoom(){
        $sess = ['userid' => 552610, 'headimgurl' => 'www.a.com', 'nickname' => 'xie', 'ip' => '192.168.1.1', 'token' => 'aaa'];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);

        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id']) || !is_numeric($this->opt['match_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        # 获取用户的session数据
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);

        # 查询玩家是否加入此俱乐部
        $userClub = new UserClubModel();
        $userClubInfo = $userClub->getUserClubInfoByUserIDAndClubId($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userClubInfo){
            return jsonRes(3511);
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
        $needUserNum = getRoomNeedUserNum($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode);
        if(!$needUserNum){ # 解析不出人数
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
                $needDiamond  = bcdiv($needDiamond, $needUserNum, 0);
            }

            # 获取折扣
            $userVip = new UserVipModel();
            $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $this->opt['club_id']);
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
                if($vipCardInfo){
                    $needDiamond = bcmul($needDiamond, bcdiv($vipCardInfo['diamond_consumption'], 100, 1), 0);
                }
            }

            # 获取非绑定钻石数 判断是否能够开房
            $diamondInfo = getUserProperty($userSessionInfo['userid'], Definition::$USER_PROPERTY_TYPE_NOT_BINDING);
            if(!isset($diamondInfo['data'][0]['property_num'])){
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(23401, $returnData);
            }else{
                if($diamondInfo['data'][0]['property_num'] >= $needDiamond){
                    $diamondInfo['noBind'] = $needDiamond; # 用于结算
                }else{
                    $diamondInfo['noBind'] = $diamondInfo['data'][0]['property_num']; # 用于结算
                    $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], Definition::$USER_PROPERTY_TYPE_BINDING);
                    if(!isset($bindingDiamondInfo['data'][0]['property_num'])){
                        $returnData = [
                            'need_diamond' => $needDiamond
                        ];
                        return jsonRes(23401, $returnData);
                    }else{
                        $userAllDiamond = bcadd($bindingDiamondInfo['data'][0]['property_num'], $diamondInfo['data'][0]['property_num'], 0);
                        if($userAllDiamond >= $needDiamond){
                            $diamondInfo['bind'] = bcsub($needDiamond, $diamondInfo['noBind'], 0);
                        }else{
                            $returnData = [
                                'need_diamond' => $needDiamond
                            ];
                            return jsonRes(23401, $returnData);
                        }
                    }
                }
            }
        }

        # 扣会长资产 判断会长资产是否充足 充足直接结算
        if($clubInfo['club_type'] == 1){ # 直接扣钻
            $subDiamond = bcsub(0, $needDiamond, 0);
            $operaRes = operaUserProperty($clubInfo['president_id'], $this->opt['club_id'].'_'.$clubInfo['president_id'].'_'.Definition::$USER_PROPERTY_PRESIDENT, $subDiamond);
            if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(23401, $returnData);
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
            $returnArr['socket_h5'] = $clubSocketInfo['socket_h5'];
            $returnArr['socket_url'] = $clubSocketInfo['socket_url'];
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

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($createRoomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        p($createRoomInfo);
        if(!$createRoomInfo || !isset($createRoomInfo['content']['result']) || ($createRoomInfo['content']['result'] != 0)){ # 创建房间失败
            if($clubInfo['club_type'] == 1){ # 还钻
                operaUserProperty($clubInfo['president_id'], $propertyType, $needDiamond);
            }
            return jsonRes(23205);
        }

        # Redis数据
        $playerInfos[$userSessionInfo['userid']] = [
            'userId' => $userSessionInfo['userid'],
            'nickName' => $userSessionInfo['nickname'],
            'headImgUrl' => $userSessionInfo['headimgurl'],
            'ipAddr' => $userSessionInfo['ip'],
        ];
        if(isset($diamondInfo)){
            $playerInfos[$userSessionInfo['userid']]['needDiamond'] = $diamondInfo;
        }
        $redisHashValue = [
            'createTime' => date('Y-m-d H:i:s'), # 房间创建时间
            'needUserNum' => $needUserNum, # 房间需要的人数
            'serviceId' => $serviceId, # 服务器ID
            'diamond' => $roomOptionsInfo['diamond'], # 进房需要的钻石  没均分没折扣的值
            'joinStatus' => 1, # 其他人是否能够申请加入
            'clubId' => $this->opt['club_id'], # 俱乐部ID
            'clubType' => $clubInfo['club_type'], # 俱乐部结算类型 免费房间和不免费房间 凌驾于roomRate之上
            'roomRate' => $roomOptionsInfo['room_rate'], # 房间结算类型 大赢家/房主/均摊
            'roomCheat' => $roomOptionsInfo['cheat'], # 是否检查GPS房间
            'roomType' => $roomOptionsInfo['room_type'], # 规则表中的类型 对应play表Id
            'roomOptionsId' => $this->opt['match_id'], # roomOptionsID
            'socketH5' => $returnArr['socket_h5'], # H5的socket连接地址
            'socketUrl' => $returnArr['socket_url'], # socket的连接地址
            'socketSsl' => Definition::$SOCKET_SSL, # socket证书
            'roomUrl' => $createRoomUrl, # 房间操作的接口的请求地址
            'playChecks' => json_encode($playInfoPlayJsonDecode['checks']), # 玩法数据中的play的checks json
            'roomOptions' => $roomOptionsInfo['options'], # 玩法相关数据 json
            'playerInfos' => json_encode($playerInfos), # 用户信息集 json
            'isGps' => $roomOptionsInfo['cheat'], # 是否判断gps 0不检测
            'gpsRange' => $clubInfo['gps'] # gps检测距离
        ];

        # 写房间hash
        $hSetRes = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $redisHashValue);
        if(!$hSetRes){ # 写日志
            $errorData = [
                $roomNumber,
            ];
            foreach ($redisHashValue as $v){
                $errorData[] = $v;
            }
            errorLog(Definition::$SET_ROOM_HASH, $errorData);
        }

        # 写用户房间 使用redis锁处理
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if($getLock){
            $setUserRoom = $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $roomNumber);
            $redisHandle->del($lockKey); # 解锁
            if(!$setUserRoom){ # 写日志
                $errorData = [
                    $userSessionInfo['userid'],
                    $roomNumber
                ];
                errorLog(Definition::$SET_USER_ROOM, $errorData);
            }
        }else{
            $errorData = [
                $userSessionInfo['userid'],
                $roomNumber
            ];
            errorLog(Definition::$GET_USER_ROOM, $errorData);
        }

        # 加入到俱乐部房间集
        $sAddRes = $redisHandle->sadd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
        if(!$sAddRes){ # 写日志
            $errorData = [
                RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'],
                $roomNumber
            ];
            errorLog(Definition::$ADD_CLUB_ROOM, $errorData);
        }

        # 返回客户端
        return jsonRes(0, $returnArr);
    }
    # 玩家加入房间完成
    public function joinRoom(){
        if(!isset($this->opt['room_id']) || !is_numeric($this->opt['room_id'])){
            return jsonRes(3006);
        }

//        删掉
        $sess = ['userid' => 328946, 'headimgurl' => 'www.a.com', 'nickname' => 'xie', 'ip' => '192.168.1.1'];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);

        # 获取session数据
        $userSessionInfo = getUserSessionInfo();

//        # 检查用户登录状态
//        $checkUserToken = checkUserToken($userSessionInfo);
//        if($checkUserToken || !isset($checkUserToken['result']) || !$checkUserToken['result']){
//            return jsonRes(9999);
//        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 获取房间信息中的俱乐部ID
        $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['diamond', 'needUserNum', 'clubType', 'roomRate', 'clubId', 'roomUrl']);
//        p($roomHashValue);
        if(!$roomHashValue){
            return jsonRes(3505);
        }

        $needDiamond = $roomHashValue['diamond']; # 基础房费
        if($roomHashValue['clubType'] == 0){ # 玩家扣费模式
            if($roomHashValue['roomRate'] == 0){
                $needDiamond = bcdiv($needDiamond, $roomHashValue['needUserNum'], 0);
            }

            # 获取折扣
            $userVip = new UserVipModel();
            $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $roomHashValue['clubId']);
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
                if($vipCardInfo){
                    $discount = bcdiv($vipCardInfo['diamond_consumption'], 100, 1);
                    $needDiamond = bcmul($needDiamond, $discount, 0);
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
                $diamondInfo['noBind'] = $diamondNum;
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
                }else{
                    $diamondInfo['bind'] = bcsub($needDiamond, $diamondInfo['noBind'], 0);
                }
            }else{
                $diamondInfo['noBind'] = $needDiamond;
            }
        }

        # 请求逻辑服加入房间
        $requestUrl = $roomHashValue['roomUrl'].Definition::$JOIN_ROOM.$userSessionInfo['userid']; # 逻辑服加入房间的请求地址
        $requestData['roomId'] = $this->opt['room_id'];
        $joinRoomInfo = sendHttpRequest($requestUrl, $requestData);
//        p($joinRoomInfo);
        if(!$joinRoomInfo || !isset($joinRoomInfo['content']['result']) || ($joinRoomInfo['content']['result'] != 0)){
            return jsonRes(3506);
        }

        # 设置用户房间
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if($getLock){ # 拿到锁处理数据并解锁
            $setUserRoom = $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $this->opt['room_id']);
            $redisHandle->del($lockKey); # 解锁
            if(!$setUserRoom){ # 写用户房间失败 记录日志
                $errorData = [
                    $userSessionInfo['userid'],
                    $this->opt['room_id']
                ];
                errorLog(Definition::$SET_USER_ROOM, $errorData);
            }
        }else{
            $errorData = [
                $userSessionInfo['userid'],
                $this->opt['room_id']
            ];
            errorLog(Definition::$SET_USER_ROOM, $errorData);
        }

        # 使用redis锁处理
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if($getLock){ # 拿到锁处理数据并解锁
            $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['needUserNum', 'playerInfos', 'socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl']);
            # 返回客户端的值
            $returnData = [
                'socket_url' => $roomHashValue['socketUrl'],
                'socket_h5' => $roomHashValue['socketH5'], # H5链接地址
                'room_num' => $this->opt['room_id'], # 房间号
                'need_gold' => $needDiamond, # 需要的钻石
                'check' => $roomHashValue['playChecks'],
                'options' => $roomHashValue['roomOptions'],
                'socket_ssl' => $roomHashValue['socketSsl']
            ];

            # 重写hash中用户信息
            $roomUserInfo = json_decode($roomHashValue['playerInfos'], true);
            $roomUserInfo[$userSessionInfo['userid']] = [
                'userId' => $userSessionInfo['userid'],
                'nickName' => $userSessionInfo['nickname'],
                'headImgUrl' => $userSessionInfo['headimgurl'],
                'ipAddr' => $userSessionInfo['ip'],
            ];
            if(isset($diamondInfo)){
                $roomUserInfo[$userSessionInfo['userid']]['needDiamond'] = $diamondInfo;
            }
            # 房间人数
            $userNum = count($roomUserInfo); # 获取房间人数
            if($userNum >= $roomHashValue['needUserNum']){
                $setHashInfo = [
                    'joinStatus' => 0,
                    'playerInfos' => json_decode($roomUserInfo)
                ];
                $setHash = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], $setHashInfo);
            }else{
                $setHash = $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁

            if(!$setHash){ # 修改房间数据失败 记录日志
                $errorData = [
                    $userSessionInfo['userid'],
                    $this->opt['room_id']
                ];
                errorLog(Definition::$CHANGE_ROOM_HASH, $errorData);
            }
        }else{
            $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl']);
            # 返回客户端的值
            $returnData = [
                'socket_url' => $roomHashValue['socketUrl'],
                'socket_h5' => $roomHashValue['socketH5'], # H5链接地址
                'room_num' => $this->opt['room_id'], # 房间号
                'need_gold' => $needDiamond, # 需要的钻石
                'check' => $roomHashValue['playChecks'],
                'options' => $roomHashValue['roomOptions'],
                'socket_ssl' => $roomHashValue['socketSsl']
            ];
            $errorData = [
                $userSessionInfo['userid'],
                $this->opt['room_id']
            ];
            errorLog(Definition::$CHANGE_ROOM_HASH, $errorData);
        }

        return jsonRes(0, $returnData);
    }
    # 玩家退出房间回调完成
    public function outRoomCallBack(){
        if(!isset($this->opt['roomId']) || !isset($this->opt['playerId']) || !$this->opt['roomId'] || !$this->opt['playerId'] || !is_numeric($this->opt['roomId']) || !is_numeric($this->opt['playerId'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        # 使用redis锁处理
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY.$this->opt['playerId'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if($getLock){
            if($redisHandle->get(RedisKey::$USER_ROOM_KEY.$this->opt['playerId']) == $this->opt['roomId']){
                $res = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$this->opt['playerId']); # 删除用户所在房间
                if(!$res){
                    $errorData = [
                        $this->opt['playerId'],
                        $this->opt['roomId']
                    ];
                    errorLog(Definition::$DEL_USER_ROOM, $errorData);
                }
            }
            $redisHandle->del($lockKey); # 解锁
        }else{
            $errorData = [
                $this->opt['playerId'],
                $this->opt['roomId'],
            ];
            errorLog(Definition::$DEL_USER_ROOM, $errorData);
        }

        # 使用redis锁处理
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if($getLock){ # 重写hash中的用户数据
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'needUserNum']);
            $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);
            $roomUserNum = count($roomUserInfo); # 房间用户数

            unset($roomUserInfo[$this->opt['playerId']]); # 删除用户

            if($roomUserNum == $roomHashInfo['needUserNum']){
                $hSetRes = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['joinStatus' => 1, 'playerInfos' => json_encode($roomUserInfo)]);
            }else{
                $hSetRes = $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁
            if(!$hSetRes){
                $errorData = [
                    $this->opt['playerId'],
                    $this->opt['roomId']
                ];
                errorLog(Definition::$CHANGE_ROOM_HASH, $errorData);
            }
        }else{
            $errorData = [
                $this->opt['playerId'],
                $this->opt['roomId']
            ];
            errorLog(Definition::$CHANGE_ROOM_HASH, $errorData);
        }

        return jsonRes(0);
    }
    # 房间游戏开始回调完成
    public function startGameCallBack(){
        if(!isset($this->opt['roomId']) || !$this->opt['roomId'] || !is_numeric($this->opt['roomId'])){
            return jsonRes(3006);
        }

        # 修改房间的状态
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $changeRoomInfo = [
            'joinStatus' => 2, # 游戏中
            'gameStartTime' => date('Y-m-d H:i:s', time())
        ];
        $hSetRes = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $changeRoomInfo);
        if(!$hSetRes){
            $errorData = [
                $this->opt['roomId'],
            ];
            errorLog(Definition::$CHANGE_ROOM_STATUS, $errorData);
        }
        return jsonRes(0);
    }
    # 牌局游戏开始回调完成
    public function roundStartGameCallBack(){
        return jsonRes(0);
    }
    # 牌局游戏结束回调完成
    public function roundEndGameCallBack(){
        if(!isset($this->opt['faanNames']) || !isset($this->opt['score']) || !isset($this->opt['roomId']) || !isset($this->opt['set']) || !isset($this->opt['round']) || !isset($this->opt['winnerIds']) || !isset($this->opt['duration']) || !isset($this->opt['playBack'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomPlayInfo = json_decode($redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roomPlayInfo'), true);
        $roomPlayInfo[] = [
            'score' => $this->opt['score'],
            'set' => $this->opt['set'],
            'round' => $this->opt['round'],
            'winnerIds' => $this->opt['winnerIds'],
            'duration' => $this->opt['duration'],
            'playBack' => $this->opt['playBack'],
            'faanNames' => $this->opt['faanNames']
        ];

        $hSetRes = $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roomPlayInfo', json_encode($roomPlayInfo));
        if(!$hSetRes){
            $errorData = [
                $this->opt['roomId'],
            ];
            errorLog(Definition::$CHANGE_ROOM_PLAY, $errorData);
        }
        return jsonRes(0);
    }
    # 房间游戏结束回调
    public function roomEndGameCallBack(){
        if(!isset($this->opt['roomId']) || !$this->opt['roomId'] || !is_numeric($this->opt['roomId'])){
            return jsonRes(3006);
        }

        # 修改房间的状态
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $hSetRes = $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'gameEndTime', date('Y-m-d H:i:s', time()));
        if(!$hSetRes){
            $errorData = [
                $this->opt['roomId'],
            ];
            errorLog(Definition::$CHANGE_ROOM_STATUS, $errorData);
        }
        return jsonRes(0);
    }
    # 房间解散回调  加扣钻逻辑
    public function disBandRoomCallBack(){
        if(!isset($this->opt['statistics']) || !$this->opt['statistics'] || !isset($this->opt['roomId']) || !$this->opt['roomId'] || !isset($this->opt['round']) || !$this->opt['round'] || !is_numeric($this->opt['roomId'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $roomHashInfos = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'clubId']);
        if(isset($roomHashInfos['playerInfos'])){
            $playerInfos = json_decode($roomHashInfos['playerInfos'], true);
            foreach ($playerInfos as $userId => $val){ # 删除用户所在房间
                # 使用redis锁处理
                $getLock = false;
                $timeOut = bcadd(time(), 2, 0);
                $lockKey = RedisKey::$USER_ROOM_KEY.$userId.'lock';
                while(!$getLock){
                    if(time() > $timeOut){
                        break;
                    }
                    $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
                    if($getLock){
                        break;
                    }
                }

                if($getLock){ # 拿到锁读取用户房间相同就删除 并解锁
                    if($redisHandle->get(RedisKey::$USER_ROOM_KEY.$userId) == $this->opt['roomId']){
                        $delUserRoom = $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userId);
                        if(!$delUserRoom){
                            $errorData = [RedisKey::$USER_ROOM_KEY.$userId];
                            errorLog('delUserRoom', $errorData);
                        }
                    }
                    $redisHandle->del($lockKey); # 解锁
                }else{
                    $errorData = [RedisKey::$USER_ROOM_KEY.$userId];
                    errorLog('delUserRoom', $errorData);
                }
            }
        }

        if(isset($roomHashInfos['clubId'])){ # 移除俱乐部
            $sRemRes = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfos['clubId'], $this->opt['roomId']);
            if(!$sRemRes){
                $errorData = [
                    RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfos['clubId'],
                    $this->opt['roomId']
                ];
                errorLog('delClubRoom', $errorData);
            }
        }
        return jsonRes(0);
    }
    # 游戏房间列表
    public function roomList(){
        $lua = '';


        if(!isset($this->opt['club_id']) || !$this->opt['club_id'] || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $sMembers = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
        if(!$sMembers){
            return jsonRes(0, []);
        }

        $userRoomReturn = [];
        foreach ($sMembers as $roomNum){
            $roomHashValue = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$roomNum);
            if($roomHashValue){
                $roomUserNum = count(json_decode($roomHashValue['playerInfos'], true));

            }
        }
    }

    public function test(){
        $hashKey = $this->opt['hashKey'];
        $redis = new Redis();
        $redisHandle = $redis->handler();
        p(json_decode($redisHandle->hGetAll($hashKey)['playerInfos'], true));
    }


//Lua脚本测试redis
//$lua = <<<SCRIPT
//        local key = KEYS[1]
//        local hashkey = ARGV[1]
//        local hashval = ARGV[2]
//        local hashke = ARGV[3]
//        local hashva = ARGV[4]
//
//        local list = redis.call("hMset", key, hashkey, hashval, hashke, hashva);
//        return list;
//SCRIPT;
//
//$redis = new Redis();
//$redisHandle = $redis->handler();
//$s = $redisHandle->eval($lua, array('hh', 'age', 10, 'name', 'xie'), 1);
////        $s = $redisHandle->hGetAll('hh');
//p($s);



}