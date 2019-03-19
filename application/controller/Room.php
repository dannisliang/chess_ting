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
    #  创建房间
    public function createRoom(){
        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id']) || !is_numeric($this->opt['match_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

//        删掉
//        $this->opt['match_id'] = roomOptionId (各种玩法相关数据)
//        $this->opt['club_id'] = clubId (俱乐部相关数据)
        $sess = ['userid' => 552610, 'headimgurl' => 'www.a.com', 'nickname' => 'xie', 'ip' => '192.168.1.1'];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);

        # 获取用户的session数据
        $userSessionInfo = getUserSessionInfo();

//        # 检查用户登录状态
//        $checkUserToken = checkUserToken($userSessionInfo);
//        if($checkUserToken || !isset($checkUserToken['result']) || !$checkUserToken['result']){
//            return jsonRes(9999);
//        }

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

        # 扣会长资产 判断会长资产是否充足 充足直接结算
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
            }else{ # 扣钻
                $subDiamond = bcsub(0, $needDiamond, 0);
                $operaRes = operaUserProperty($clubInfo['president_id'], $propertyType, $subDiamond);
                if(!$operaRes || !isset($operaRes['code']) || ($operaRes['code'] != 0)){
                    return jsonRes(23205);
                }
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
        $playerInfos = [
            $userSessionInfo['userid'] => [
                'userId' => $userSessionInfo['userid'],
                'nickName' => $userSessionInfo['nickname'],
                'headImgUrl' => $userSessionInfo['headimgurl'],
                'ipAddr' => $userSessionInfo['ip'],
                'needDiamond' => $needDiamond,
            ]
        ];
        $redisHashValue = [
            'createTime' => date('Y-m-d H:i:s'), # 房间创建时间
            'needUserNum' => $roomNeedUserNum, # 房间需要的人数
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
                RedisKey::$USER_ROOM_KEY_HASH.$roomNumber,
            ];
            foreach ($redisHashValue as $v){
                $errorData[] = $v;
            }
            errorLog('setRoomHash', $errorData);
        }

        # 写用户房间
        $setUserRoom = $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $roomNumber);
        if(!$setUserRoom){ # 写日志
            $errorData = [
                RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'],
                $roomNumber
            ];
            errorLog('setUserRoom', $errorData);
        }

        # 加入到俱乐部房间集
        $sAddRes = $redisHandle->sadd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
        if(!$sAddRes){ # 写日志
            $errorData = [
                RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'],
                $roomNumber
            ];
            errorLog('addClubRoom', $errorData);
        }

        # 返回客户端
        return jsonRes(0, $returnArr);
    }

    # 玩家加入房间
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
        $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['diamond', 'roomNeedUserNum', 'clubType', 'roomRate', 'clubId', 'roomUrl']);
//        p($roomHashValue);
        if(!$roomHashValue){
            return jsonRes(3505);
        }

        $needDiamond = $roomHashValue['diamond']; # 基础房费
        if($roomHashValue['clubType'] == 0){ # 玩家扣费模式
            if($roomHashValue['roomRate'] == 0){
                $needDiamond = bcdiv($needDiamond, $roomHashValue['roomNeedUserNum'], 0);
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

        # 请求逻辑服加入房间
        $requestUrl = $roomHashValue['roomUrl'].Definition::$JOIN_ROOM.$userSessionInfo['userid']; # 逻辑服加入房间的请求地址
        $requestData['roomId'] = $this->opt['room_id'];
        $joinRoomInfo = sendHttpRequest($requestUrl, $requestData);
//        p($joinRoomInfo);
        if(!$joinRoomInfo || !isset($joinRoomInfo['content']['result']) || ($joinRoomInfo['content']['result'] != 0)){
            return jsonRes(3506);
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

        if(!$getLock){ # 没能拿到锁
            $errorData = [
                RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'],
                $userSessionInfo['userid']
            ];
            errorLog('joinRoomError', $errorData);
        }

        $roomHashValue = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id']);
        $roomUserInfo = json_decode($roomHashValue['playerInfos'], true);
        $roomUserInfo[$userSessionInfo['userid']] = [
            'userId' => $userSessionInfo['userid'],
            'nickName' => $userSessionInfo['nickName'],
            'headImgUrl' => $userSessionInfo['headImgUrl'],
            'ipAddr' => $userSessionInfo['ip'],
            'needDiamond' => $needDiamond
        ];

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
                RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id']
            ];
            if(isset($setHashInfo)){
                foreach ($setHashInfo as $v){
                    $errorData[] = $v;
                }
            }else{
                $errorData[] = json_encode($roomUserInfo);
            }
            errorLog('changeRoomHash', $errorData);
        }

        # 设置用户房间
        $setUserRoom = $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $this->opt['room_id']);
        if($setUserRoom){ # 写用户房间失败 记录日志
            $errorData = [
                RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'],
                $this->opt['room_id']
            ];
            errorLog('setUserRoom', $errorData);
        }

        # 返回客户端的值
        $returnArr = [
            'socket_url' => $roomHashValue['socketUrl'],
            'socket_h5' => $roomHashValue['socketH5'], # H5链接地址
            'room_num' => $this->opt['room_id'], # 房间号
            'need_gold' => $needDiamond, # 需要的钻石
            'check' => $roomHashValue['playChecks'],
            'options' => $roomHashValue['roomOptions'],
            'socket_ssl' => Definition::$SOCKET_SSL,
        ];
        return jsonRes(0, $returnArr);
    }

    # 玩家加入房间回调
    public function joinRoomCallBack()
    {
        return jsonRes(0);
    }

    # 房间游戏开始回调
    public function startGameCallBack(){

    }

    # 退出房间
    public function outRoom(){

    }

    # 强制解散房间
    public function disBandRoom(){
        if(!isset($this->opt['uid']) || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $userRoom = $redisHandle->get(RedisKey::$USER_ROOM_KEY.$this->opt['uid']);
        if($userRoom){
            $redisHandle->del(RedisKey::$USER_ROOM_KEY.$this->opt['uid']);
            $roomHashValue = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$userRoom);
            if($roomHashValue){
                $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$userRoom);
                $clubId = $roomHashValue['clubId'];
                $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$clubId, $userRoom);

                $disBandUrl = $roomHashValue['roomUrl'];
                $disBandRes = sendHttpRequest($disBandUrl.Definition::$DIS_BAND_ROOM.$userRoom, ['playerId' => $this->opt['uid']]);
                if($disBandRes && isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                    return jsonRes(3507);
                }
            }
        }

        # 获取玩家所在房间
        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();
        foreach ($serviceGatewayNewInfos as $v){
            $checkPlayerRes = sendHttpRequest($v['service'].Definition::$CHECK_PLAYER, ['playerId' => $this->opt['uid']], 'POST', [], ['connect_timeout' => 1, 'timeout' => 1]);
            if($checkPlayerRes && isset($checkPlayerRes['content']['roomId']) && $checkPlayerRes['content']['roomId']){
                $disBandRes = sendHttpRequest($v['service'].Definition::$DIS_BAND_ROOM.$checkPlayerRes['content']['roomId'], ['playerId' => $this->opt['uid']]);
                if($disBandRes && isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                    return jsonRes(3507);
                }
            }
        }

        return jsonRes(3508);

    }

    # 获取gps相关信息
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

        if(isset($this->opt['match_id']) && $this->opt['match_id'] && is_numeric($this->opt['room_id'])){
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
                'room_cheat' => $roomOptionsInfo['room_cheat'],
                'gps_range' => $clubInfo['gps']
            ];
            return jsonRes(0, $returnData);
        }

        return jsonRes(3006); # 请求参数有误
    }

    # 查询玩家所在的房间
    public function getUserRoom(){
        if(!isset($this->opt['uid']) || !$this->opt['uid'] || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        $userRoom = $redisHandle->get(RedisKey::$USER_ROOM_KEY.$this->opt['uid']);
        if($userRoom){
            return jsonRes(0, [$userRoom]);
        }

        # 去逻辑服获取玩家所在房间
        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();
        foreach ($serviceGatewayNewInfos as $v){
            $checkPlayerRes = sendHttpRequest($v['service'].Definition::$CHECK_PLAYER, ['playerId' => $this->opt['uid']], 'POST', [], ['connect_timeout' => 1, 'timeout' => 1]);
            if($checkPlayerRes && isset($checkPlayerRes['content']['roomId']) && $checkPlayerRes['content']['roomId']){
                return jsonRes(0, [$checkPlayerRes['content']['roomId']]);
            }
        }
        return jsonRes(3509);
    }

    # 房间列表
    public function roomList(){
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



}