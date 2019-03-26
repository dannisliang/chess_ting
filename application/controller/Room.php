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
    # 强制解散玩家房间完成 缺少扣钻还钻逻辑
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
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$roomId)){
            return jsonRes(3507); # redis中没有房间数据 直接返回成功
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomId, ['clubId', 'playerInfos', 'roomPlayInfo', 'clubType', 'needDiamond', 'presidentId', 'set', 'round', 'generalRebate', 'seniorPresidentId', 'seniorRebate', 'roomRate']);
        $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfo['clubId'], $roomId);
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
        # 加锁删用户所在房间的记录
        if(is_array($playerInfo)){
            foreach ($playerInfo as $k => $userInfo){
                # 使用redis锁处理
                $getLock = false;
                $timeOut = bcadd(time(), 2, 0);
                $lockKey = RedisKey::$USER_ROOM_KEY.$userInfo['userId'].'lock';
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
                    if($redisHandle->get(RedisKey::$USER_ROOM_KEY.$userInfo['userId']) == $roomId){ # 判断用户当前房间是否是被解散的房间
                        $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userInfo['userId']); # 删除用户所在房间
                    }
                    $redisHandle->del($lockKey); # 解锁
                }
            }
        }

        # 房主模式还钻 玩家模式扣钻
        if($roomHashInfo['clubType'] == 1){ # 会长模式 如果第一局没打完需要还钻
            if((!$roomHashInfo['set']) && (!$roomHashInfo['round'])){
                $operateData[] = [
                    'uid' => $roomHashInfo['presidentId'],
                    'event_type' => '+',
                    'reason_id' => 8,
                    'property_type' => Definition::$USER_PROPERTY_PRESIDENT,
                    'property_name' => '赠送蓝钻',
                    'change_num' => $roomHashInfo['diamond']
                ];
                $res = operatePlayerProperty($operateData);
                if(!isset($res['code']) || ($res['code'] != 0)){ # 还钻失败 记录日志
                    errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $operateData);
                }
            }
        }

        if($roomHashInfo['clubType'] == 0 && is_array($playerInfo)){ # 玩家模式
            if($roomHashInfo['set'] || $roomHashInfo['round']){ # 第一句结束扣钻
                # 组装扣钻数组
                if($roomHashInfo['roomRate'] == 1){ # 大赢家模式
                    $userScore = [];
                    foreach ($this->opt['statistics'] as $k => $v){
                        $userScore[$v['playerId']] = $v['totalScore'];
                    }
                    $userId = array_search(max($userScore), $userScore);
                    foreach ($playerInfo as $k => $userInfo){
                        if($userInfo['userId'] == $userId){
                            foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                                if($diamondType == 'bind'){
                                    $operateData[] = [
                                        'uid' => $userId,
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => $diamondValue,
                                    ];
                                }
                                if($diamondType == 'noBind'){
                                    $operateData[] = [
                                        'uid' => $userId,
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => $diamondValue,
                                    ];
                                    $rebate = $diamondValue;
                                }
                            }
                        }
                    }
                }
                if($roomHashInfo['roomRate'] == 0){ # 平均扣钻
                    $operateData = [];
                    $rebate = 0;
                    foreach ($playerInfo as $k => $userInfo){
                        foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                            if($diamondType == 'bind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                            }
                            if($diamondType == 'noBind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                                $rebate = bcadd($rebate, $diamondValue, 0);
                            }
                        }
                    }
                }
                # 组装扣钻数组结束

                # 扣钻返利
                if(isset($operateData)){
                    $res = operatePlayerProperty($operateData);
                    if(!isset($res['code']) || ($res['code'] != 0)){ # 扣钻失败 记录日志
                        errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $operateData);
                    }
                    if(isset($rebate)){ # 需要返利
                        if($roomHashInfo['presidentId']){
                            $generalChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['generalRebate'], 0), 100, 0);
                            if($generalChangeNum > 0){
                                $generalRebateData[] = [
                                    'uid' => $roomHashInfo['presidentId'],
                                    'event_type' => '+',
                                    'reason_id' => 5,
                                    'property_type' => Definition::$PRESIDENT_REBATE,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' =>  $generalChangeNum# 普通会长返利,
                                ];
                                $res = operatePlayerProperty($generalRebateData);
                                if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                                    errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $generalRebateData);
                                }
                            }
                        }

                        if($roomHashInfo['seniorPresidentId']){
                            $seniorChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['seniorRebate'], 0), 100, 0);
                            if($seniorChangeNum > 0){
                                $seniorRebateData[] = [
                                    'uid' => $roomHashInfo['seniorPresidentId'],
                                    'event_type' => '+',
                                    'reason_id' => 5,
                                    'property_type' => Definition::$PRESIDENT_REBATE,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $seniorChangeNum, # 高级会长返利
                                ];
                                $res = operatePlayerProperty($seniorRebateData);
                                if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                                    errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $seniorRebateData);
                                }
                            }
                        }
                    }
                }
                # 扣钻返利结束
            }
        }
        return jsonRes(3507);
    }
    # 获取gps相关信息完成
    public function getRoomGpsInfo(){
        # 根据房间ID获取
        if(isset($this->opt['room_id']) && is_numeric($this->opt['room_id'])){
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

        # 根据房间规则ID获取
        if(isset($this->opt['match_id']) && is_numeric($this->opt['match_id'])){
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

        # 计费模式有问题
        if(($clubInfo['club_type'] != 0) && ($clubInfo['club_type'] != 1)){
            return jsonRes(3504);
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



        # 根据俱乐部ID获取俱乐部socket通道
        $clubSocket = new ClubSocketModel();
        $clubSocketInfo = $clubSocket->getClubSocketInfoByClubId($this->opt['club_id']);
        if($clubSocketInfo){
            $serviceId = 1;
            $createRoomUrl = $clubSocketInfo['room_url'];
            $socketH5 = $clubSocketInfo['socket_h5'];
            $socketUrl = $clubSocketInfo['socket_url'];
        }else{
            $gameServiceNew = new GameServiceNewModel();
            $gameServiceNewInfos = $gameServiceNew->getGameServiceNewInfosByRoomTypeId($roomOptionsInfo['room_type']);
            $len = count($gameServiceNewInfos);
            if($len < 0){
                return jsonRes(3517);
            }
            $rand = mt_rand(0, $len-1);
            $serviceId = $gameServiceNewInfos[$rand]['service_id'];
            $serviceGatewayNew = new ServiceGatewayNewModel();
            $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($serviceId);
            if(!$serviceGatewayNewInfo){
                return jsonRes(3517);
            }
            $createRoomUrl = $serviceGatewayNewInfo['service'];
            $socketH5 = $serviceGatewayNewInfo['gateway_h5'];
            $socketUrl = $serviceGatewayNewInfo['gateway_app'];
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

            # 获取钻石数 判断是否能够开房
            $userDiamondInfo = getUserProperty($userSessionInfo['userid'], [Definition::$USER_PROPERTY_TYPE_NOT_BINDING, Definition::$USER_PROPERTY_TYPE_BINDING]);
            if(!isset($userDiamondInfo['code']) || ($userDiamondInfo['code'] != 0)){
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(3516, $returnData);
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
                    return jsonRes(3516, $returnData);
                }
            }
        }

        # 扣会长资产 判断会长资产是否充足 充足直接结算
        if($clubInfo['club_type'] == 1){ # 直接扣钻
            $operateData[] = [
                'uid' => $clubInfo['president_id'],
                'event_type' => '-',
                'reason_id' => 7,
                'property_type' => Definition::$USER_PROPERTY_PRESIDENT,
                'property_name' => '赠送蓝钻',
                'change_num' => $needDiamond
            ];
            $operaRes = operatePlayerProperty($operateData);
            if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(3516, $returnData);
            }
        }

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        if(!$roomNumber){
            return jsonRes(3517);
        }

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($createRoomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        p($createRoomInfo);
        if(!isset($createRoomInfo['content']['result']) || ($createRoomInfo['content']['result'] != 0)){ # 创建房间失败
            if($clubInfo['club_type'] == 1){ # 还钻
                $operateData[] = [
                    'uid' => $clubInfo['president_id'],
                    'event_type' => '+',
                    'reason_id' => 8,
                    'property_type' => Definition::$USER_PROPERTY_PRESIDENT,
                    'property_name' => '赠送蓝钻',
                    'change_num' => $needDiamond
                ];
                $operaRes = operatePlayerProperty($operateData);
                if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){

                }
            }
            return jsonRes(3517);
        }

        # Redis数据
        $userInfo = [
            'userId' => $userSessionInfo['userid'],
            'nickName' => $userSessionInfo['nickname'],
            'headImgUrl' => $userSessionInfo['headimgurl'],
            'ipAddr' => $userSessionInfo['ip']
        ];
        if(isset($diamondInfo)){
            $userInfo['needDiamond'] = $diamondInfo;
        }
        $playerInfo[] = $userInfo;

        $roomHashInfo = [
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
            'socketH5' => $socketH5, # H5的socket连接地址
            'socketUrl' => $socketUrl, # socket的连接地址
            'socketSsl' => Definition::$SOCKET_SSL, # socket证书
            'roomUrl' => $createRoomUrl, # 房间操作的接口的请求地址
            'playChecks' => json_encode($playInfoPlayJsonDecode['checks']), # 玩法数据中的play的checks json
            'roomOptions' => $roomOptionsInfo['options'], # 玩法相关数据 json
            'playerInfos' => json_encode($playerInfo), # 用户信息集 json
            'isGps' => $roomOptionsInfo['cheat'], # 是否判断gps 0不检测
            'gpsRange' => $clubInfo['gps'], # gps检测距离
            'presidentId' => $clubInfo['president_id'], # 普通会长ID
            'generalRebate' => $clubInfo['pin_drilling_ratio'], # 普通会长返利比
            'seniorPresidentId' => $clubInfo['senior_president'], # 高级会长ID
            'seniorRebate' => $clubInfo['rebate'], # 高级会长返利比
        ];

        # 写房间hash 写失败记录日志
        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $roomHashInfo);

        # 写用户房间 使用redis锁处理 写失败记录日志
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
            $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $roomNumber);
            $redisHandle->del($lockKey); # 解锁
        }

        # 加入到俱乐部房间集
        $redisHandle->sadd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);

        # 接口返回值
        $returnData = [
            'need_gold' => $needDiamond, # 所需钻石
            'check' => $playInfoPlayJsonDecode['checks'], # play表的json的checks
            'options' => $roomOptionsInfoOptionsJsonDecode, # room_options表的options
            'room_num' => $roomNumber, # 房间号
            'socket_ssl' => Definition::$SOCKET_SSL,
            'socket_h5' => $socketH5,
            'socket_url' =>  $socketUrl,
        ];
        # 返回客户端
        return jsonRes(0, $returnData);
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
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);

        # 获取房间信息中的俱乐部ID
        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'])){
            return jsonRes(3505);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['diamond', 'needUserNum', 'clubType', 'roomRate', 'clubId', 'roomUrl']);

//        # 查询玩家是否加入此俱乐部
//        $userClub = new UserClubModel();
//        $userClubInfo = $userClub->getUserClubInfoByUserIDAndClubId($userSessionInfo['userid'], $roomHashInfo['clubId']);
//        if(!$userClubInfo){
//            return jsonRes(3511);
//        }

        # 计算房费
        $needDiamond = $roomHashInfo['diamond']; # 基础房费
        if($roomHashInfo['clubType'] == 0){
            # 是否均分
            if($roomHashInfo['roomRate'] == 0){
                $needDiamond  = bcdiv($needDiamond, $roomHashInfo['needUserNum'], 0);
            }

            # 获取折扣
            $userVip = new UserVipModel();
            $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $roomHashInfo['clubId']);
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
                if($vipCardInfo){
                    $needDiamond = bcmul($needDiamond, bcdiv($vipCardInfo['diamond_consumption'], 100, 1), 0);
                }
            }

            # 获取钻石数 判断是否能够开房
            $userDiamondInfo = getUserProperty($userSessionInfo['userid'], [Definition::$USER_PROPERTY_TYPE_NOT_BINDING, Definition::$USER_PROPERTY_TYPE_BINDING]);
            if(!isset($userDiamondInfo['code']) || ($userDiamondInfo['code'] != 0)){
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(3516, $returnData);
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
                    return jsonRes(3516, $returnData);
                }
            }
        }

        # 请求逻辑服加入房间
        $joinRoomInfo = sendHttpRequest($roomHashInfo['roomUrl'].Definition::$JOIN_ROOM.$userSessionInfo['userid'], ['roomId' => $this->opt['room_id']]);
//        p($joinRoomInfo);
        if(!isset($joinRoomInfo['content']['result']) || ($joinRoomInfo['content']['result'] != 0)){
            return jsonRes(3506);
        }

        # 设置用户房间 枷锁失败写日志
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
            $redisHandle->set(RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'], $this->opt['room_id']);
            $redisHandle->del($lockKey); # 解锁
        }

        # 使用redis锁写房间数据 失败写日志
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
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['needUserNum', 'playerInfos', 'socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl']);
            # 重写hash中用户信息
            $roomUserInfo = json_decode($roomHashInfo['playerInfos'], true);
            $userInfo = [
                'userId' => $userSessionInfo['userid'],
                'nickName' => $userSessionInfo['nickname'],
                'headImgUrl' => $userSessionInfo['headimgurl'],
                'ipAddr' => $userSessionInfo['ip']
            ];
            if(isset($diamondInfo)){
                $userInfo['needDiamond'] = $diamondInfo;
            }
            $roomUserInfo[] = $userInfo;

            # 房间人数
            $userNum = count($roomUserInfo); # 获取房间人数
            if($userNum >= $roomHashInfo['needUserNum']){
                $setHashInfo = [
                    'joinStatus' => 0,
                    'playerInfos' => json_decode($roomUserInfo)
                ];
                $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], $setHashInfo);
            }else{
                $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁
        }else{
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl']);
        }

        $returnData = [
            'need_gold' => $needDiamond, # 需要的钻石
            'room_num' => $this->opt['room_id'], # 房间号
            'check' => json_decode($roomHashInfo['playChecks'], true), #
            'options' => json_decode($roomHashInfo['roomOptions'], true), # 规则
            'socket_h5' => $roomHashInfo['socketH5'], # H5链接地址
            'socket_url' => $roomHashInfo['socketUrl'], # app链接地址
            'socket_ssl' => $roomHashInfo['socketSsl'] # 证书
        ];
        return jsonRes(0, $returnData);
    }
    # 玩家加入房间回调完成
    public function joinRoomCallBack()
    {
        return jsonRes(0);
    }
    # 玩家退出房间回调完成
    public function outRoomCallBack(){
        if(!isset($this->opt['roomId']) || !isset($this->opt['playerId']) || !is_numeric($this->opt['roomId']) || !is_numeric($this->opt['playerId'])){
            return jsonRes(0);
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
                $redisHandle->del(RedisKey::$USER_ROOM_KEY.$this->opt['playerId']); # 删除用户所在房间
            }
            $redisHandle->del($lockKey); # 解锁
        }

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }
        # 使用redis锁重写roomhash
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

            foreach ($roomUserInfo as $k => $userInfo){
                if($userInfo['userId'] == $this->opt['playerId']){
                    unset($roomUserInfo[$k]);
                }
            }

            if($roomUserNum >= $roomHashInfo['needUserNum']){
                $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['joinStatus' => 1, 'playerInfos' => json_encode($roomUserInfo)]);
            }else{
                $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁
        }

        return jsonRes(0);
    }
    # 房间游戏开始回调完成
    public function roomStartGameCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['founderId']) || !is_numeric($this->opt['founderId']) || !isset($this->opt['players'])){
            return jsonRes(0);
        }

        # 修改房间的状态
        $redis = new Redis();
        $redisHandle = $redis->handler();

        if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            $changeRoomInfo = [
                'joinStatus' => 2, # 游戏中
                'gameStartTime' => date('Y-m-d H:i:s', time())
            ];
            $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $changeRoomInfo);
        }

        return jsonRes(0);
    }
    # 牌局游戏开始回调完成  房间信息中记录第几圈 第几局 用于处理强制解散房间的结算
    public function roundStartGameCallBack(){
        if(!isset($this->opt['set']) || !is_numeric($this->opt['set']) || !isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(0);
        }
        return jsonRes(0);
    }
    # 牌局游戏结束回调完成
    public function roundEndGameCallBack(){
        if(!isset($this->opt['faanNames']) || !isset($this->opt['score']) || !isset($this->opt['roomId']) || !isset($this->opt['set']) || !isset($this->opt['round']) || !isset($this->opt['winnerIds']) || !isset($this->opt['duration']) || !isset($this->opt['playBack'])){
            return jsonRes(0);
        }
        if(!is_numeric($this->opt['set']) || !is_numeric($this->opt['round']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $roomPlayInfo = json_decode($redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roomPlayInfo'), true);
        $roomPlayInfo[] = [
            'score' => $this->opt['score'], # 玩家得分
            'set' => $this->opt['set'], # 圈数
            'round' => $this->opt['round'], # 局数
            'winnerIds' => $this->opt['winnerIds'], # 赢家ID集
            'duration' => $this->opt['duration'], # 牌局耗时
            'playBack' => $this->opt['playBack'], # 录像json
            'faanNames' => $this->opt['faanNames'] # 番型集
        ];

        $setInfo = [
            'set' => $this->opt['set'],
            'round' => $this->opt['round'],
            'roomPlayInfo' => json_encode($roomPlayInfo)
        ];

        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $setInfo);
        return jsonRes(0);
    }
    # 房间游戏结束回调完成
    public function roomEndGameCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(3006);
        }

        # 修改房间的结束时间
        $redis = new Redis();
        $redisHandle = $redis->handler();
        if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'gameEndTime', date('Y-m-d H:i:s', time()));
        }
        return jsonRes(0);
    }
    # 房间解散回调完成
    public function disBandRoomCallBack(){
        if(!isset($this->opt['statistics']) || !is_array($this->opt['statistics']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['set']) || !is_numeric($this->opt['set'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'clubId', 'clubType', 'roomRate', 'diamond', 'generalRebate', 'seniorRebate', 'seniorPresidentId', 'presidentId', 'set', 'round']);
        $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfo['clubId'], $this->opt['roomId']); # 俱乐部移除房间
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);

        # 清除用户房间
        if(is_array($playerInfo)){
            foreach ($playerInfo as $k => $userInfo){ # 删除用户所在房间
                # 使用redis锁处理
                $getLock = false;
                $timeOut = bcadd(time(), 2, 0);
                $lockKey = RedisKey::$USER_ROOM_KEY.$userInfo['userId'].'lock';
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
                    if($redisHandle->get(RedisKey::$USER_ROOM_KEY.$userInfo['userId']) == $this->opt['roomId']){
                        $redisHandle->del(RedisKey::$USER_ROOM_KEY.$userInfo['userId']);
                    }
                    $redisHandle->del($lockKey); # 解锁
                }
            }
        }
        # 清除用户房间完成

        # 会长模式还钻
        if($roomHashInfo['clubType'] == 1){
            if((!$roomHashInfo['set']) && (!$roomHashInfo['round'])){
                $operateData[] = [
                    'uid' => $roomHashInfo['presidentId'],
                    'event_type' => '+',
                    'reason_id' => 8,
                    'property_type' => Definition::$USER_PROPERTY_PRESIDENT,
                    'property_name' => '赠送蓝钻',
                    'change_num' => $roomHashInfo['diamond']
                ];
                $res = operatePlayerProperty($operateData);
                if(!isset($res['code']) || ($res['code'] != 0)){ # 还钻失败 记录日志
                    errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $operateData);
                }
            }
        }
        # 会长模式还钻完成

        # 玩家模式扣钻
        if(($roomHashInfo['clubType'] == 0) && is_array($playerInfo)){
            if($roomHashInfo['set'] || $roomHashInfo['round']){ # 需要扣钻
                if($roomHashInfo['roomRate'] == 1){ # 大赢家模式
                    $userScore = [];
                    foreach ($this->opt['statistics'] as $k => $v){
                        $userScore[$v['playerId']] = $v['totalScore'];
                    }
                    $userId = array_search(max($userScore), $userScore);
                    foreach ($playerInfo as $k => $userInfo){
                        if($userInfo['userId'] == $userId){
                            foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                                if($diamondType == 'bind'){
                                    $operateData[] = [
                                        'uid' => $userId,
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => $diamondValue,
                                    ];
                                }
                                if($diamondType == 'noBind'){
                                    $operateData[] = [
                                        'uid' => $userId,
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => $diamondValue,
                                    ];
                                    $rebate = $diamondValue;
                                }
                            }
                        }
                    }
                }
                if($roomHashInfo['roomRate'] == 0){ # 平均扣钻
                    $operateData = [];
                    $rebate = 0;
                    foreach ($playerInfo as $k => $userInfo){
                        foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                            if($diamondType == 'bind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                            }
                            if($diamondType == 'noBind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                                $rebate = bcadd($rebate, $diamondValue, 0);
                            }
                        }
                    }
                }
            }
            if(isset($operateData)){
                $res = operatePlayerProperty($operateData);
                if(!isset($res['code']) || ($res['code'] != 0)){ # 扣钻失败 记录日志
                    errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $operateData);
                }
                if(isset($rebate)){ # 需要返利
                    if($roomHashInfo['presidentId']){
                        $generalChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['generalRebate'], 0), 100, 0);
                        if($generalChangeNum > 0){
                            $generalRebateData[] = [
                                'uid' => $roomHashInfo['presidentId'],
                                'event_type' => '+',
                                'reason_id' => 5,
                                'property_type' => Definition::$PRESIDENT_REBATE,
                                'property_name' => '赠送蓝钻',
                                'change_num' =>  $generalChangeNum# 普通会长返利,
                            ];
                            $res = operatePlayerProperty($generalRebateData);
                            if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                                errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $generalRebateData);
                            }
                        }
                    }

                    if($roomHashInfo['seniorPresidentId']){
                        $seniorChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['seniorRebate'], 0), 100, 0);
                        if($seniorChangeNum > 0){
                            $seniorRebateData[] = [
                                'uid' => $roomHashInfo['seniorPresidentId'],
                                'event_type' => '+',
                                'reason_id' => 5,
                                'property_type' => Definition::$PRESIDENT_REBATE,
                                'property_name' => '赠送蓝钻',
                                'change_num' => $seniorChangeNum, # 高级会长返利
                            ];
                            $res = operatePlayerProperty($seniorRebateData);
                            if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                                errorLog(Definition::$FAILED_TO_OPERATE_PROPERTY, $seniorRebateData);
                            }
                        }
                    }
                }
            }
        }
        # 玩家模式扣钻完成
        return jsonRes(0);
    }
    # 游戏房间列表完成 排序算法需要review
    public function getRoomList(){
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $sMembers = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
        if(!$sMembers){
            return jsonRes(0, []);
        }

        $clubRoomReturn = [];
        foreach ($sMembers as $k => $roomNum){
            $roomHashValue = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$roomNum);
//            p($roomHashValue);
            if($roomHashValue){
                $clubRoomReturn[$k]['diamond'] = $roomHashValue['diamond'];
                $clubRoomReturn[$k]['match_id'] = $roomHashValue['roomOptionsId'];
                $clubRoomReturn[$k]['player_size'] = $roomHashValue['needUserNum'];
                $playChecksJsonDecode = json_decode($roomHashValue['playChecks'], true);
                $clubRoomReturn[$k]['room_code'] = $playChecksJsonDecode['code'];
                $clubRoomReturn[$k]['room_id'] = $roomNum;
                $clubRoomReturn[$k]['room_rate'] = $roomHashValue['roomRate'];
                $clubRoomReturn[$k]['socket_h5'] = $roomHashValue['socketH5'];
                $clubRoomReturn[$k]['socket_url'] = $roomHashValue['socketUrl'];
                $clubRoomReturn[$k]['options'] = $roomHashValue['roomOptions'];
                if($roomHashValue['playerInfos']){
                    $roomUserInfos = json_decode($roomHashValue['playerInfos'], true);
                    foreach ($roomUserInfos as $userInfo){
                        $userInfoReturn['image'] = $userInfo['headImgUrl'];
                        $userInfoReturn['nickname'] = $userInfo['nickName'];
                        $userInfoReturn['player_id'] = $userInfo['userId'];
                        $userInfoReturn['player_status'] = '';
                        $clubRoomReturn[$k]['player_info'][] = $userInfoReturn;
                    }
                }else{
                    $clubRoomReturn[$k]['player_info'] = [];
                }
                $clubRoomReturn[$k]['createTime'] = strtotime($roomHashValue['createTime']);
                $nowUserNum = count($clubRoomReturn[$k]['player_info']);
                if($nowUserNum >= $clubRoomReturn[$k]['player_size']){
                    $clubRoomReturn[$k]['nowNeedUserNum'] = 100;
                }else{
                    $clubRoomReturn[$k]['nowNeedUserNum'] = bcsub($clubRoomReturn[$k]['player_size'], $nowUserNum, 0);
                }
            }
        }
        $len = count($clubRoomReturn)-1;
        while(true){
            $flag = true;
            for($i = 0; $i < $len; $i++){
                if($clubRoomReturn[$i]['nowNeedUserNum'] > $clubRoomReturn[$i+1]['nowNeedUserNum']){
                    $tmp = $clubRoomReturn[$i];
                    $clubRoomReturn[$i] = $clubRoomReturn[$i+1];
                    $clubRoomReturn[$i+1] = $tmp;
                    $flag = false;
                }
                if($clubRoomReturn[$i]['nowNeedUserNum'] == $clubRoomReturn[$i+1]['nowNeedUserNum']){
                    if($clubRoomReturn[$i]['createTime'] > $clubRoomReturn[$i+1]['createTime']){
                        $tmp = $clubRoomReturn[$i];
                        $clubRoomReturn[$i] = $clubRoomReturn[$i+1];
                        $clubRoomReturn[$i+1] = $tmp;
                        $flag = false;
                    }
                }
            }
            $len--;
            if($flag == true){
                break;
            }
        }

        return jsonRes(0, $clubRoomReturn);
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