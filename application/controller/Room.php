<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\controller;

use app\model\ClubSocketModel;
use app\model\CommerceModel;
use think\Env;
use think\Session;
use Obs\ObsClient;
use app\model\BeeSender;
use app\model\AreaModel;
use app\model\PlayModel;
use app\model\ClubModel;
use app\model\VipCardModel;
use app\model\UserVipModel;
use app\model\UserClubModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;
use app\model\RoomOptionsModel;
use app\model\GameServiceNewModel;
use app\model\ServiceGatewayNewModel;
use app\model\UserClubRoomRecordModel;
use think\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;



class Room extends Base
{

    /**
     * 客户端
     */
    # 获取gps相关信息完成
    public function getRoomGpsInfo(){
        # 根据房间ID获取
        if(isset($this->opt['room_id']) && is_numeric($this->opt['room_id'])){
            $redis = new Redis();
            $redisHandle = $redis->handler();

            if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'])){
                return jsonRes(3505);
            }
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['isGps', 'gpsRange']);
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
    # 创建房间完成
    public function createRoom(){
        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id']) || !is_numeric($this->opt['match_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        # 获取用户的session数据
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return jsonRes(3006);
        }

        # 使用redis锁写房间数据 失败写日志
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $lockKey = RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'].'lock';
        $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 2));
        if(!$getLock){
            return jsonRes(0);
        }

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

        # 获取会长昵称
        if($clubInfo['president_id']){
            $presidentInfo = getUserBaseInfo($clubInfo['president_id']);
            if(isset($presidentInfo['nickname'])){
                $presidentNickName = $presidentInfo['nickname'];
            }
        }

        # 获取高级会长昵称
        if($clubInfo['senior_president']){
            $seniorPresidentInfo = getUserBaseInfo($clubInfo['senior_president']);
            if(isset($seniorPresidentInfo['nickname'])){
                $seniorPresidentNickName = $seniorPresidentInfo['nickname'];
            }
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

        # 根据玩法的类型去查找玩法启动的服务
        $gameServiceNew = new GameServiceNewModel();
        $serviceInfos = $gameServiceNew->getServiceByPlayType($playInfo['play_type']);
        if(!$serviceInfos) {
            return jsonRes(3521);
        }
        $rand = array_rand($serviceInfos, 1);
        $serviceId = $serviceInfos[$rand]['id'];
        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($serviceId);
        if(!$serviceGatewayNewInfo){
            return jsonRes(3517);
        }
        $createRoomUrl = $serviceGatewayNewInfo['service'];
        $socketH5 = $serviceGatewayNewInfo['gateway_h5'];
        $socketUrl = $serviceGatewayNewInfo['gateway_app'];

        if(Env::get('is_online') == false){
            if(in_array($this->opt['club_id'], [555555, 999999, 888888])){
                $clubSocket = new ClubSocketModel();
                $clubSocketInfo = $clubSocket->getClubSocketInfoByClubId($this->opt['club_id']);
                $createRoomUrl = $clubSocketInfo['room_url'];
                $socketH5 = $clubSocketInfo['socket_h5'];
                $socketUrl = $clubSocketInfo['socket_url'];
            }
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

        //查找商务会长，给商务会长返利
        $commerceModel = new CommerceModel();
        $commerce = $commerceModel -> getOneByWhere(['senior_president' => $clubInfo['senior_president']]);

        # 获取玩家vip
        $userVip = new UserVipModel();
        $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $this->opt['club_id']);
        # 计算房费
        $needDiamond = $roomOptionsInfo['diamond'];
        if($clubInfo['club_type'] == 0){
            # 是否均分
            if($roomOptionsInfo['room_rate'] == 0){
                $payMode = 'aa';
                $needDiamond  = bcdiv($needDiamond, $needUserNum, 0);
            }
            if($roomOptionsInfo['room_rate'] == 1){
                $payMode = 'winer';
            }

            # 获取折扣
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
                if($vipCardInfo){
                    $needDiamond = bcmul($needDiamond, bcdiv($vipCardInfo['diamond_consumption'], 100, 1), 0);
                }
            }

            if($needDiamond > 0){ # 需要扣钻
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
        }

        # 生成房间号
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        if(!$roomNumber){
            return jsonRes(3517);
        }

        # 扣会长资产 判断会长资产是否充足 充足直接结算
        if($clubInfo['club_type'] == 1 && ($needDiamond > 0)){ # 直接扣钻
            $payMode = 'free';
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

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($createRoomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        p($createRoomInfo);
        if(!isset($createRoomInfo['content']['result']) || ($createRoomInfo['content']['result'] != 0)){ # 创建房间失败
            if($clubInfo['club_type'] == 1 && ($needDiamond > 0)){ # 还钻
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
                    Log::write(json_encode($operateData), 'operateError');
                }
            }

            return jsonRes(3517);
        }

        if(isset($createRoomInfo['content']['result']) && ($createRoomInfo['content']['result'] == 10002)){
            return jsonRes(9999);
        }

        # Redis数据
        $userInfo = [
            'userId' => $userSessionInfo['userid'],
            'nickName' => $userSessionInfo['nickname'],
            'headImgUrl' => $userSessionInfo['headimgurl'],
            'ipAddr' => $userSessionInfo['ip'],
            'sex' => $userSessionInfo['sex'],
            'vipId' => isset($userVipInfo['vid']) ? $userVipInfo['vid'] : '',
            'clientType' => $userSessionInfo['client_type'],
            'systemType' => $userSessionInfo['app_type'],
        ];
        if(isset($diamondInfo)){
            $userInfo['needDiamond'] = $diamondInfo;
        }
        $playerInfo[] = $userInfo;
         # 报送大数据
        $setNum = getRoomSet($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode);
        $tableType = 1; # 按局玩
        if($setNum){
            $tableType = 0; # 按圈玩
        }
        $clubMode = 'divide'; # 免费房
        if($clubInfo['club_type'] == 1){
            $clubMode = 'free';
        }
        $roundNum = getRoomRound($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode); # 圈数
        $baseScore = getRoomBaseScore($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode); # 基础分
        # 获取俱乐部所属地区
        $areaName = '-';
        $area = new AreaModel();
        $areaInfo = $area->getInfoById($clubInfo['area_id']);
        if($areaInfo){
            $areaName = $areaInfo['area_name'];
        }
        # 报送大数据结束

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
            'socketH5' => $socketH5, # H5的socket连接地址
            'socketUrl' => $socketUrl, # socket的连接地址
            'socketSsl' => Definition::$SOCKET_SSL, # socket证书
            'roomUrl' => $createRoomUrl, # 房间操作的接口的请求地址
            'playChecks' => json_encode($playInfoPlayJsonDecode['checks']), # 玩法数据中的play的checks json
            'roomCode' => $playInfoPlayJsonDecode['code'], # 客户端需要
            'roomOptions' => $roomOptionsInfo['options'], # 玩法相关数据 json
            'playerInfos' => json_encode($playerInfo), # 用户信息集 json
            'isGps' => $roomOptionsInfo['cheat'], # 是否判断gps 0不检测
            'gpsRange' => $clubInfo['gps'], # gps检测距离
            'presidentId' => $clubInfo['president_id'], # 普通会长ID
            'generalRebate' => $clubInfo['pin_drilling_ratio'], # 普通会长返利比
            'seniorPresidentId' => $clubInfo['senior_president'], # 高级会长ID
            'seniorRebate' => $clubInfo['rebate'], # 高级会长返利比
            'gameStartTime' => '', # 房间开始时间
            'gameEndTime' => '', # 房间结束时间
            'roundEndInfo' => '', # 对局结束相关数据
            'gameEndInfo' => '', # 房间结束相关数据
            'roomName' => $playInfoPlayJsonDecode['name'], # 房间记录列表需要

            # 大数据报送
            'roomOptionsId' => $this->opt['match_id'], # roomOptionsID
            'roomTypeName' => $roomOptionsInfo['room_name'],
            'roomChannel' => 1, # 房间渠道：朋友diy/俱乐部会长房间
            'ruleDetail' => '-',# 创建房间时勾选的详细玩法
            'tableType' => $tableType, # 按圈玩还是按局玩
            'setNum' => $setNum, # 圈数
            'tableNum' => $roundNum, # 创建房间时所选择的要进行多少牌局
            'betNums' => $baseScore, # 底分数量
            'clubName' => base64_decode($clubInfo['club_name']), # 俱乐部名称
            'clubRegionId' => $clubInfo['area_id'], # 俱乐部地域id
            'clubRegionName' => $areaName, # 俱乐部地域名
            'clubMode' => $clubMode, # 房间模式
            'payMode' => isset($payMode) ? $payMode : '-', # 支付方式
            'presidentNickName' => isset($presidentNickName) ? $presidentNickName : '-', # 会长昵称
            'seniorPresidentNickName' => isset($seniorPresidentNickName) ? $seniorPresidentNickName : '-', # 高级会长昵称
            'commerceId' => isset($commerce['commerce_id']) ? $commerce['commerce_id'] : '', # 商务会长ID
            'businessRebate' => $clubInfo['business_rebate'], # 上午会长返利比
        ];

        # 写房间hash 写失败记录日志
        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $roomHashInfo);

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

        # 获取session数据
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $lockKey = RedisKey::$USER_ROOM_KEY.$userSessionInfo['userid'].'lock';
        $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 1));
        if(!$getLock){
            return jsonRes(0);
        }

        # 获取房间信息中的俱乐部ID
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'])){
            return jsonRes(3505);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['diamond', 'needUserNum', 'clubType', 'roomRate', 'clubId', 'roomUrl']);

        # 查询玩家是否加入此俱乐部
        $userClub = new UserClubModel();
        $userClubInfo = $userClub->getUserClubInfoByUserIDAndClubId($userSessionInfo['userid'], $roomHashInfo['clubId']);
        if(!$userClubInfo){
            return jsonRes(3511);
        }

        # 获取玩家vip卡
        $userVip = new UserVipModel();
        $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $roomHashInfo['clubId']);
        # 计算房费
        $needDiamond = $roomHashInfo['diamond']; # 基础房费
        if($roomHashInfo['clubType'] == 0){
            # 是否均分
            if($roomHashInfo['roomRate'] == 0){
                $needDiamond  = bcdiv($needDiamond, $roomHashInfo['needUserNum'], 0);
            }

            # 获取折扣
            if($userVipInfo){
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($userVipInfo['vid']);
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
        }

        # 请求逻辑服加入房间
        $joinRoomInfo = sendHttpRequest($roomHashInfo['roomUrl'].Definition::$JOIN_ROOM.$userSessionInfo['userid'], ['roomId' => $this->opt['room_id']]);
//        p($joinRoomInfo);
        if(!isset($joinRoomInfo['content']['result']) || ($joinRoomInfo['content']['result'] != 0)){
            return jsonRes(3506);
        }

        if(isset($joinRoomInfo['content']['result']) && ($joinRoomInfo['content']['result'] == 10002)){
            return jsonRes(9999);
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
                    'playerInfos' => json_encode($roomUserInfo)
                ];
                $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], $setHashInfo);
            }else{
                $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], 'playerInfos', json_encode($roomUserInfo));
            }
            $redisHandle->del($lockKey); # 解锁
        }else{
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['room_id'], ['socketUrl', 'socketH5', 'playChecks', 'roomOptions', 'socketSsl', 'clubName', 'clubId']);
        }

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
        return jsonRes(0, $returnData);
    }
    # 游戏房间列表完成
    public function getRoomList(){
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $sMembers = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
        if(!$sMembers){
            return jsonRes(0, ['roominfo' => []]);
        }

        # 和逻辑服同步
        $gameServiceNew = new GameServiceNewModel();
        $serviceInfos = $gameServiceNew->getGameService();
        if(!$serviceInfos){ # 没有可用的服务 全都删了
            $redisHandle->del(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
            return jsonRes(0, ['roominfo' => []]);
        }

        $serviceIds = [];
        foreach ($serviceInfos as $k => $v){
            $serviceIds[] = $v['service_id'];
        }

        $client = new Client();
        $promises = [];
        foreach ($sMembers as $k => $roomNumber){
            $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, ['serviceId', 'roomUrl']);
            if(!in_array($roomHashInfo['serviceId'], $serviceIds)){
                $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
            }else{
                $promises[$roomNumber] = $client->postAsync($roomHashInfo['roomUrl'].Definition::$CHECK_ROOM, ['json' => ['roomId' => $roomNumber], 'connect_timeout' => 1]);
            }
        }

        // 忽略某些请求的异常，保证所有请求都发送出去
        $results = Promise\unwrap($promises);
        $newNumbers = [];
        foreach ($results as $roomNumber => $v){
            $roomCheckInfo = json_decode($results[$roomNumber]->getBody()->getContents(), true);
            if(isset($roomCheckInfo['content']['exist']) && $roomCheckInfo['content']['exist']){
                $newNumbers[] = $roomNumber;
            }else{
                $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
            }
        }
        # 和逻辑服同步

        $i = 0;
        $clubRoomReturn = [];
        foreach ($newNumbers as $k => $roomNum){
            if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$roomNum)){
                continue;
            }
            $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomNum, ['roomName', 'joinStatus', 'clubType', 'roomRate', 'roomCode', 'diamond', 'roomOptionsId', 'needUserNum', 'roomRate', 'socketH5', 'socketUrl', 'roomOptions', 'playerInfos', 'createTime']);
//            p($roomHashValue);
            if($roomHashValue){
                $diamond = $roomHashValue['diamond'];
                if(($roomHashValue['roomRate'] == 0) && ($roomHashValue['clubType'] == 0)){
                    if($roomHashValue['needUserNum']){
                        $diamond = bcdiv($diamond, $roomHashValue['needUserNum'], 0);
                    }
                }
                $clubRoomReturn[$i]['joinStatus'] = $roomHashValue['joinStatus'];
                $clubRoomReturn[$i]['room_id'] = $roomNum;
                $clubRoomReturn[$i]['diamond'] = $diamond;
                $clubRoomReturn[$i]['match_id'] = $roomHashValue['roomOptionsId'];
                $clubRoomReturn[$i]['player_size'] = $roomHashValue['needUserNum'];
                $clubRoomReturn[$i]['room_code'] = $roomHashValue['roomCode'];
                $clubRoomReturn[$i]['room_rate'] = $roomHashValue['roomRate'];
                $clubRoomReturn[$i]['room_name'] = $roomHashValue['roomName'];
                $clubRoomReturn[$i]['socket_h5'] = $roomHashValue['socketH5'];
                $clubRoomReturn[$i]['socket_url'] = $roomHashValue['socketUrl'];
                $clubRoomReturn[$i]['options'] = json_decode($roomHashValue['roomOptions'], true);
                $roomUserInfos = json_decode($roomHashValue['playerInfos'], true);
                if($roomUserInfos){
                    foreach ($roomUserInfos as $userInfo){
                        $userInfoReturn['image'] = $userInfo['headImgUrl'];
                        $userInfoReturn['nickname'] = $userInfo['nickName'];
                        $userInfoReturn['player_id'] = $userInfo['userId'];
                        $userInfoReturn['player_status'] = '';
                        $clubRoomReturn[$i]['player_info'][] = $userInfoReturn;
                    }
                }else{
                    $clubRoomReturn[$i]['player_info'] = [];
                }
                $clubRoomReturn[$i]['createTime'] = strtotime($roomHashValue['createTime']);
                $nowUserNum = count($clubRoomReturn[$i]['player_info']);
                if($nowUserNum >= $roomHashValue['needUserNum']){
                    $clubRoomReturn[$i]['nowNeedUserNum'] = 100;
                }else{
                    $clubRoomReturn[$i]['nowNeedUserNum'] = bcsub($roomHashValue['needUserNum'], $nowUserNum, 0);
                }
                $i++;
            }
        }

        $newArr = [];
        $kArr = [];
        foreach ($clubRoomReturn as $k => $v){
            if(!in_array($v['nowNeedUserNum'], $kArr)){
                $kArr[] = $v['nowNeedUserNum'];
            }
            $newArr[$v['nowNeedUserNum']][] = $v;
        }

        $KArrLen = count($kArr) - 1;
        while (true){
            $flag = true;
            for($i = 0; $i < $KArrLen; $i++){
                if($kArr[$i] > $kArr[$i+1]){
                    $tmp = $kArr[$i];
                    $kArr[$i] = $kArr[$i+1];
                    $kArr[$i+1] = $tmp;
                    $flag = false;
                }
            }
            if($flag == true){
                break;
            }
            $KArrLen--;
        }

        foreach ($kArr as $k => $v){
            $vArrLen = count($newArr[$v]) - 1;
            while (true){
                $flag = true;
                for ($i = 0; $i < $vArrLen; $i++){
                    if($newArr[$v][$i]['createTime'] > $newArr[$v][$i+1]['createTime']){
                        $tmp = $newArr[$v][$i];
                        $newArr[$v][$i] = $newArr[$v][$i+1];
                        $newArr[$v][$i+1] = $tmp;
                        $flag = false;
                    }
                }
                if($flag == true){
                    break;
                }
                $vArrLen--;
            }
        }

        $returnData = [];
        foreach ($newArr as $k => $v){
            foreach ($v as $kk => $vv){
                $returnData[] = $vv;
            }
        }

        $return['roominfo'] = $returnData;
        return jsonRes(0, $return);
    }

    /**
     * 后台
     */
    # 查询玩家所在的房间完成
    public function getUserRoom(){
        if(!isset($this->opt['uid']) || !$this->opt['uid'] || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        # 去逻辑服获取玩家所在房间
        $gameServiceNew = new GameServiceNewModel();
        $gameServiceNewInfos = $gameServiceNew->getGameService();
        $gameServiceNewArr = [];
        foreach ($gameServiceNewInfos as $k => $v){
            $gameServiceNewArr[] = $v['service_id'];
        }

        if(config('app_debug')){
            $gameServiceNewArr[] = 4;
        }

        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();

        foreach ($serviceGatewayNewInfos as $k => $v){
            if(in_array($v['id'], $gameServiceNewArr)){
                $userRoom = sendHttpRequest($v['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
                if(isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                    return jsonRes(0, [$userRoom['content']['roomId']]);
                }
            }
        }
        return jsonRes(3509);
    }
    # 强制解散玩家房间完成
    public function disBandRoom(){
        if(!isset($this->opt['uid']) || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        # 去逻辑服获取玩家所在房间
        $gameServiceNew = new GameServiceNewModel();
        $gameServiceNewInfos = $gameServiceNew->getGameService();
        $gameServiceNewArr = [];
        foreach ($gameServiceNewInfos as $k => $v){
            $gameServiceNewArr[] = $v['service_id'];
        }

        if($gameServiceNewArr){
            $serviceGatewayNew = new ServiceGatewayNewModel();
            $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();
            foreach ($serviceGatewayNewInfos as $k => $v){
                if(in_array($v['id'], $gameServiceNewArr)){
                    $userRoom = sendHttpRequest($v['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
                    if(isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                        $disBandRes = sendHttpRequest($v['service'].Definition::$DIS_BAND_ROOM.$userRoom['content']['roomId'], ['playerId' => $this->opt['uid']]);
                        if(isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                            return jsonRes(3507);
                        }
                    }
                }
            }
        }
//
        return jsonRes(3508);
    }

    /**
     * 逻辑服回调
     */
    # 玩家加入房间回调完成
    public function joinRoomCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['playerId']) || !is_numeric($this->opt['playerId'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'],
            ['playerInfos', 'roomOptionsId', 'roomTypeName', 'clubName', 'roomChannel', 'clubMode',
                'tableType', 'tableNum', 'betNums', 'clubId', 'clubRegionId', 'clubRegionName']);
        # 报送大数据
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
        if($playerInfo){
            foreach ($playerInfo as $k => $userInfo){
                if($userInfo['userId'] == $this->opt['playerId']){
                    $bigData = [
                        'server_id' => '-',
                        'user_id' => $this->opt['playerId'],
                        'role_id' => '-'.'_'.$this->opt['playerId'],
                        'role_name' => $userInfo['nickName'],
                        'client_id' => '-',
                        'client_type' => $userInfo['clientType'],
                        'system_type' => $userInfo['systemType'],
                        'ip' => $userInfo['ipAddr'],

                        'room_id' => $this->opt['roomId'],
                        'room_type_id' => $roomHashInfo['roomOptionsId'],
                        'room_type_name' => $roomHashInfo['roomTypeName'],
                        'room_channel' => $roomHashInfo['roomChannel'],
                        'rule_detail' => '-',
                        'table_type' => $roomHashInfo['tableType'],
                        'table_num' => $roomHashInfo['tableNum'],
                        'bet_num' => $roomHashInfo['betNums'],
                        'club_id' => $roomHashInfo['clubId'],
                        'club_name' => $roomHashInfo['clubName'],
                        'club_region_id' => $roomHashInfo['clubRegionId'],
                        'club_region_name' => $roomHashInfo['clubRegionName'],
                        'club_mode' => $roomHashInfo['clubMode'],
                    ];

                    // Todo 报送
                    $beeSender = new BeeSender(Definition::$CESHI_APPID, Definition::$MY_APP_NAME, Definition::$SERVICE_IP, config('app_debug'));
                    $beeSender->send('room_join', $bigData);
                    break;
                }
            }
        }
        return jsonRes(0);
    }
    # 玩家退出房间回调完成
    public function outRoomCallBack(){
        if(!isset($this->opt['roomId']) || !isset($this->opt['playerId']) || !is_numeric($this->opt['roomId']) || !is_numeric($this->opt['playerId'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }
        # 使用redis加锁重写roomHash
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

            if($roomUserInfo){
                $roomUserNum = count($roomUserInfo); # 房间用户数
                $newRoomUserInfo = [];
                foreach ($roomUserInfo as $k => $userInfo){
                    if($userInfo['userId'] != $this->opt['playerId']){
                        $newRoomUserInfo[] = $userInfo;
                    }
                }
                if($roomUserNum >= $roomHashInfo['needUserNum']){
                    $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['joinStatus' => 1, 'playerInfos' => json_encode($newRoomUserInfo)]);
                }else{
                    $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'playerInfos', json_encode($newRoomUserInfo));
                }
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

        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $changeRoomInfo = [
            'joinStatus' => 2, # 游戏中
            'gameStartTime' => date('Y-m-d H:i:s', time())
        ];
        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $changeRoomInfo);
        return jsonRes(0);
    }
    # 牌局游戏开始回调完成
    public function roundStartGameCallBack(){
        if(!isset($this->opt['set']) || !is_numeric($this->opt['set']) || !isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(0);
        }
        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        # 报送大数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['clubMode', 'playerInfos', 'clubType', 'roomOptionsId', 'roomTypeName', 'roomChannel', 'betNums', 'needUserNum', 'clubId', 'clubName', 'clubRegionId', 'clubRegionName', 'clubType']);

        $beeSender = new BeeSender(Definition::$CESHI_APPID, Definition::$MY_APP_NAME, Definition::$SERVICE_IP, config('app_debug'));
        $playerInfos = json_decode($roomHashInfo['playerInfos'], true);
        // Todo 报送
        if($playerInfos){
            foreach ($playerInfos as $k => $userInfo){
                $bigData = [
                    'server_id' => '-',
                    'user_id' => $userInfo['userId'],
                    'role_id' => '-'.'_'.$userInfo['userId'],
                    'role_name' => $userInfo['nickName'],
                    'client_id' => '-',
                    'client_type' => $userInfo['clientType'],
                    'system_type' => $userInfo['systemType'],
                    'ip' => $userInfo['ipAddr'],

                    'room_id' => $this->opt['roomId'],
                    'room_type_id' => $roomHashInfo['roomOptionsId'],
                    'room_type_name' => $roomHashInfo['roomTypeName'],
                    'room_channel' => $roomHashInfo['roomChannel'],
                    'table_id' => $this->opt['roomId'].'_'.$this->opt['set'].'_'.$this->opt['round'],
                    'rule_detail' => '',
                    'bet_num' => $roomHashInfo['betNums'],
                    'user_num' => $roomHashInfo['needUserNum'],
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];
                $beeSender->add_batch('table_start', $bigData);
            }
        }
        $beeSender->batch_send();
        # 报送大数据完成
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

        # 上传牌局记录到华为云
        $obsClient = new ObsClient([
            'key' => Definition::$OBS_KEY,
            'secret' => Definition::$OBS_SECRET,
            'endpoint' => Definition::$OBS_ENDPOINT
        ]);
        $obsClient -> putObject([
            'Bucket' => Definition::$CHESS_RECORD_TEST,
            'Key' => date("Y-m-d", time()).'_'.$this->opt['roomId'].'_'.$this->opt['set'].'_'.$this->opt['round'],
            'Body' => $this->opt['playBack']
        ]);

        $roundEndInfo = json_decode($redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roundEndInfo'), true);
        $roundEndInfo[] = [
            $this->opt['score'],
            date("Y-m-d", time()).'_'.$this->opt['roomId'].'_'.$this->opt['set'].'_'.$this->opt['round'],
            date("Y-m-d H:i:s", time())
        ];
        $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roundEndInfo', json_encode($roundEndInfo));

        # 报送大数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'],
            ['roomOptionsId', 'roomTypeName', 'roomChannel', 'betNums', 'needUserNum', 'clubId', 'clubName', 'clubRegionId', 'clubRegionName', 'clubMode', 'playerInfos']);

        # 算用户积分和用户积分
        $userIds = [];
        $userScore = [];
        foreach ($this->opt['score'] as $k => $v){
            $userScore[$v['playerId']] = $v['score'];
            $userIds[] = $v['playerId'];
        }
        $beeSender = new BeeSender(Definition::$CESHI_APPID, Definition::$MY_APP_NAME, Definition::$SERVICE_IP, config('app_debug'));

        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
        if($playerInfo){

            foreach ($playerInfo as $k => $userInfo){
                $isWin = 'lose';
                $winType = '';
                if(in_array($userInfo['userId'], $this->opt['winnerIds'])){
                    $isWin = 'win';
                    $winType = json_encode($this->opt['faanNames']);
                }
                $bigData = [
                    'server_id' => '-',
                    'user_id' => $userInfo['userId'],
                    'role_id' => '-'.'_'.$userInfo['userId'],
                    'role_name' => $userInfo['nickName'],
                    'client_id' => '-',
                    'client_type' => $userInfo['clientType'],
                    'system_type' => $userInfo['systemType'],
                    'ip' => $userInfo['ipAddr'],

                    'room_id' => $this->opt['roomId'],
                    'room_type_id' => $roomHashInfo['roomOptionsId'],
                    'room_type_name' => $roomHashInfo['roomTypeName'],
                    'room_channel' => $roomHashInfo['roomChannel'],
                    'table_id' => $this->opt['roomId'].'_'.$this->opt['set'].'_'.$this->opt['round'],
                    'rule_detail' => '-',
                    'bet_num' => $roomHashInfo['betNums'],
                    'user_num' => $roomHashInfo['needUserNum'],
                    'win_lose' => $isWin,
                    'win_type' => $winType,
                    'token_name' => 'score',
                    'token_num' => $userScore[$userInfo['userId']],
                    'current_token' => 'score',
                    'keep_time' => $this->opt['duration'],
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];
                $beeSender->add_batch('table_finish', $bigData);
            }
        }
        $beeSender->batch_send();
        # 报送大数据完成
        return jsonRes(0);
    }
    # 房间游戏结束回调完成
    public function roomEndGameCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['statistics']) || !is_array($this->opt['statistics'])){
            return jsonRes(0);
        }

        # 修改房间的结束时间
        $redis = new Redis();
        $redisHandle = $redis->handler();
        if($redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            $setData = [
                'gameEndTime' => date('Y-m-d H:i:s', time()),
                'gameEndInfo' => json_encode($this->opt['statistics'])
            ];
            $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $setData);
        }

        # 牌局正常结束 返回逻辑服扣钻相关数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'clubType', 'roomRate']);
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);

        if($this->opt['round'] && ($roomHashInfo['clubType'] != 1) && $playerInfo){
            $returnData = [];
            if($roomHashInfo['roomRate'] == 1){ # 大赢家模式
                $userScore = [];
                foreach ($this->opt['statistics'] as $k => $v){
                    $userScore[$v['playerId']] = $v['totalScore'];
                }
                $userIds = [];
                $maxScore = max($userScore);
                foreach ($userScore as $playerId => $score){
                    if($score == $maxScore){
                        $userIds[] = $playerId;
                    }
                }
                $userNum = count($userIds);

                foreach ($this->opt['statistics'] as $k => $v){
                    if(in_array($v['playerId'], $userIds)){ # 需要扣钻
                        foreach ($playerInfo as $kk => $userInfo){
                            if($userInfo['userId'] == $v['playerId']){
                                $bind = isset($userInfo['needDiamond']['bind']) ? $userInfo['needDiamond']['bind'] : 0;
                                $noBind = isset($userInfo['needDiamond']['noBind']) ? $userInfo['needDiamond']['noBind'] : 0;
                                $total = bcdiv(bcadd($bind, $noBind, 0), $userNum, 0);
                                $returnData[] = [
                                    'player_id' => $v['playerId'],
                                    'room_cost' => $total,
                                ];
                            }
                        }
                    }else{
                        $returnData[] = [
                            'player_id' => $v['playerId'],
                            'room_cost' => 0,
                        ];
                    }
                }
            }

            if($roomHashInfo['roomRate'] == 0){ # 平均扣钻
                foreach ($this->opt['statistics'] as $k => $v){
                    foreach ($playerInfo as $kk => $userInfo){
                        if($v['playerId'] == $userInfo['userId']){
                            $bind = isset($userInfo['needDiamond']['bind']) ? $userInfo['needDiamond']['bind'] : 0;
                            $noBind = isset($userInfo['needDiamond']['noBind']) ? $userInfo['needDiamond']['noBind'] : 0;
                            $total = bcadd($bind, $noBind, 0);
                            $returnData[] = [
                                'player_id' => $v['playerId'],
                                'room_cost' => $total,
                            ];
                        }
                    }
                }
            }
        }else{
            $returnData = [];
            foreach ($this->opt['statistics'] as $k => $v){
                $returnData[] = [
                    'player_id' => $v['playerId'],
                    'room_cost' => 0,
                ];
            }
        }
        return jsonRes(0, $returnData);
    }
    # 房间解散回调完成
    public function disBandRoomCallBack(){
        if(!isset($this->opt['statistics']) || !is_array($this->opt['statistics']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) ||
            !isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['set']) || !is_numeric($this->opt['set'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }

        $roomHashInfo = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId']);
        $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfo['clubId'], $this->opt['roomId']); # 俱乐部移除房间
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);

        // Todo 报送
        $beeSender = new BeeSender(Definition::$CESHI_APPID, Definition::$MY_APP_NAME, Definition::$SERVICE_IP, config('app_debug'));

        if($playerInfo){
            foreach ($playerInfo as $k => $userInfo){
                $bigData = [
                    'server_id' => '-',
                    'user_id' => $userInfo['userId'],
                    'role_id' => '-'.'_'.$userInfo['userId'],
                    'role_name' => $userInfo['nickName'],
                    'client_id' => '-',
                    'client_type' => $userInfo['clientType'],
                    'system_type' => $userInfo['systemType'],
                    'ip' => $userInfo['ipAddr'],

                    'room_id' => $this->opt['roomId'],
                    'room_type_id' => $roomHashInfo['roomOptionsId'],
                    'room_type_name' => $roomHashInfo['roomTypeName'],
                    'room_channel' => $roomHashInfo['roomChannel'],
                    'rule_detail' => '-',
                    'table_type' => $roomHashInfo['tableType'],
                    'table_num' => $roomHashInfo['tableNum'],
                    'table_true_num' => $this->opt['round'],
                    'close_reason' => $this->opt['round'] >= $roomHashInfo['tableNum'] ? '完成' : '中断',
                    'keep_time' => bcsub(strtotime($roomHashInfo['gameEndTime']), strtotime($roomHashInfo['gameStartTime']), 0),
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];

                $beeSender->add_batch('room_close', $bigData);
            }
        }
        # 报送完成

        $userIds = [];
        $userScore = [];
        foreach ($this->opt['statistics'] as $k => $v){
            $userScore[$v['playerId']] = $v['totalScore'];
            $userIds[] = $v['playerId'];
        }

        $baoSong = [];
        foreach ($roomHashInfo as $k => $v){
            if(in_array($k, ['playChecks', 'roomOptions', 'playerInfos', 'roomOptions', 'roundEndInfo', 'gameEndInfo'])){
                $baoSong[$k] = json_decode($v, true);
            }else{
                $baoSong[$k] = $v;
            }
        }
        $baoSong['opt'] = $this->opt;

        foreach ($userIds as $v){
            # 报送助手
            $zhushou = [
                'type' => 'common',
                'timestamp' => time(),
                'content' => $baoSong,
                'product' => Definition::$CESHI_APPID,
                'filter_userid' => $v,
                'filter_clubid' => $roomHashInfo['clubId'],
            ];
            $data[] = $zhushou;
        }
        if(isset($data)){
            sendHttpRequest(Definition::$ZHUSHOU_URL_TEST, $data, 'POST', [], ['connect_timeout' => 3]);
        }

        # 会长模式还钻
        if(($roomHashInfo['clubType'] == 1) && !$this->opt['round']){
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
                Log::write(json_encode($operateData), 'operateError');
            }
        }
        # 会长模式还钻完成

        # 会长模式报送
        if(($roomHashInfo['clubType'] == 1) && $this->opt['round']){
            $bigData = [
                'server_id' => '-',
                'user_id' => $roomHashInfo['presidentId'],
                'role_id' => '-'.'_'.$roomHashInfo['presidentId'],
                'role_name' => '-',
                'client_id' => '-',
                'client_type' => '-',
                'system_type' => '-',
                'ip' => '-',
                'club_id' => $roomHashInfo['clubId'],
                'club_name' => $roomHashInfo['clubName'],
                'club_region_id' => $roomHashInfo['clubRegionId'],
                'club_region_name' => $roomHashInfo['clubRegionName'],
                'club_mode' => $roomHashInfo['clubMode'],
                'pay_mode' => $roomHashInfo['payMode'],
                'room_id' => $this->opt['roomId'],
                'room_type_id' => $roomHashInfo['roomOptionsId'],
                'room_type_name' => $roomHashInfo['roomTypeName'],
                'room_channel' => $roomHashInfo['roomChannel'],
                'rule_detail' => '-',
                'token_name' => 'diamond',
                'token_num' => $roomHashInfo['diamond'],
                'token_type' => '-',
                'current_token' => '-',
                'player_list' => json_encode($userIds)
            ];
            $beeSender->add_batch('room_token_reduce', $bigData);
        }
        # 会长模式报送完成

        # 牌局记录入库
        if($playerInfo && $this->opt['round']){
            $insertAll = [];
            foreach ($playerInfo as $k => $userInfo){
                $insert = [
                    'room_id' => $this->opt['roomId'],
                    'user_id' => $userInfo['userId'],
                    'club_id' => $roomHashInfo['clubId'],
                    'add_time' => date("Y-m-d H:i:s", time()),
                ];
                $insertAll[] = $insert;
            }
            if($insertAll){
                $userClubRoomRecord = new UserClubRoomRecordModel();
                $userClubRoomRecord->insertAllUserRecord($insertAll);
            }
        }

        # 玩家模式扣钻
        if(($roomHashInfo['clubType'] == 0) && $playerInfo && $this->opt['round']){
            $rebate = 0; # 返利基数
            if($roomHashInfo['roomRate'] == 1){ # 大赢家模式
                $userScore = [];
                foreach ($this->opt['statistics'] as $k => $v){
                    $userScore[$v['playerId']] = $v['totalScore'];
                }
                $userIds = [];
                $maxScore = max($userScore);
                foreach ($userScore as $playerId => $score){
                    if($score == $maxScore){
                        $userIds[] = $playerId;
                    }
                }

                $userNum = count($userIds);
                foreach ($playerInfo as $k => $userInfo){
                    if(in_array($userInfo['userId'], $userIds)){
                        if(isset($userInfo['needDiamond'])){
                            foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                                if($diamondType == 'bind'){
                                    $operateData[] = [
                                        'uid' => $userInfo['userId'],
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => bcdiv($diamondValue, $userNum, 0),
                                    ];
                                }
                                if($diamondType == 'noBind'){
                                    $operateData[] = [
                                        'uid' => $userInfo['userId'],
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => bcdiv($diamondValue, $userNum, 0),
                                    ];
                                    $rebate = bcadd(bcdiv($diamondValue, $userNum, 0), $rebate, 0);
                                }
                            }
                        }
                    }
                }
            }

            if($roomHashInfo['roomRate'] == 0){ # 平均扣钻
                foreach ($playerInfo as $k => $userInfo){
                    if(isset($userInfo['needDiamond'])){
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
                    Log::write(json_encode($operateData), 'operateError');
                }else{ # 报送大数据
                    $users = [];
                    foreach ($playerInfo as $k => $userInfo){
                        $users[$userInfo['userId']] = $userInfo;
                    }

                    foreach ($operateData as $k => $v){
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $v['uid'],
                            'role_id' => '-'.'_'.$v['uid'],
                            'role_name' => $users[$v['uid']]['nickName'],
                            'client_id' => '-',
                            'client_type' => $users[$v['uid']]['clientType'],
                            'system_type' => $users[$v['uid']]['systemType'],
                            'ip' => $users[$v['uid']]['ipAddr'],

                            'club_id' => $roomHashInfo['clubId'],
                            'club_name' => $roomHashInfo['clubName'],
                            'club_region_id' => $roomHashInfo['clubRegionId'],
                            'club_region_name' => $roomHashInfo['clubRegionName'],
                            'club_mode' => $roomHashInfo['clubMode'],
                            'pay_mode' => $roomHashInfo['payMode'],
                            'room_id' => $this->opt['roomId'],
                            'room_type_id' => $roomHashInfo['roomOptionsId'],
                            'room_type_name' => $roomHashInfo['roomTypeName'],
                            'room_channel' => $roomHashInfo['roomChannel'],
                            'rule_detail' => '-',
                            'token_name' => 'diamond',
                            'token_num' => $v['change_num'],
                            'token_type' => $v['property_type'] == Definition::$USER_PROPERTY_TYPE_NOT_BINDING ? 'pay' : 'free',
                            'current_token' => '-',
                            'player_list' => json_encode($userIds),
                        ];
                        $beeSender->add_batch('room_token_reduce', $bigData);
                    }

                    foreach ($operateData as $kk =>$vv){
                        $userDiamondInfo = getUserProperty($vv['uid'], [Definition::$USER_PROPERTY_TYPE_NOT_BINDING, Definition::$USER_PROPERTY_TYPE_BINDING, 10000]);
                        if(isset($userDiamondInfo['code']) && ($userDiamondInfo['code'] == 0)){
                            $noBindDiamond = 0;
                            $bindDiamond = 0;
                            $gold = 0;
                            foreach ($userDiamondInfo['data'] as $k => $v){
                                if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_NOT_BINDING){
                                    $noBindDiamond = $v['property_num'];
                                }
                                if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_BINDING){
                                    $bindDiamond = $v['property_num'];
                                }
                                if($v['property_type'] == 10000){
                                    $gold = $v['property_num'];
                                }
                            }

                            $user_diamond = $noBindDiamond + $bindDiamond;
                            $send_data = array();
                            $send_user[0] = $vv['uid'];
                            $send_data['content']['gold'] = (int)$gold;
                            $send_data['content']['diamond'] = (int)$user_diamond;
                            $send_data['type'] = 1029;
                            $send_data['sender'] = 0;
                            $send_data['reciver'] = $send_user;
                            $send_data['appid'] = Definition::$CESHI_APPID;
                            $send_url = Definition::$INFORM_URL . 'api/send.php';
                            $client = new Client();
                            $res = $client->post($send_url, ['json' => $send_data, 'connect_timeout' => 1]);
                        }
                    }
                }
            }

            if($rebate){ # 需要返利
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
                            Log::write(json_encode($generalRebateData), 'operateError');
                        }else{
                            $bigData = [
                                'server_id' => '-',
                                'user_id' => $generalRebateData[0]['uid'],
                                'role_id' => '-'.'_'.$generalRebateData[0]['uid'],
                                'role_name' => '-',
                                'client_id' => '-',
                                'client_type' => '-',
                                'system_type' => '-',
                                'ip' => '-',

                                'club_id' => $roomHashInfo['clubId'],
                                'club_name' => $roomHashInfo['clubName'],
                                'club_region_id' => $roomHashInfo['clubRegionId'],
                                'club_region_name' => $roomHashInfo['clubRegionName'],
                                'club_mode' => $roomHashInfo['clubMode'],
                                'room_id' => $this->opt['roomId'],
                                'room_type_id' => $roomHashInfo['roomOptionsId'],
                                'room_type_name' => $roomHashInfo['roomTypeName'],
                                'token_name' => 'money',
                                'token_num' => bcmul($generalRebateData[0]['change_num'], 100, 0),
                                'pay_mode' => $roomHashInfo['payMode'],
                            ];
                            $beeSender->add_batch('club_rebate', $bigData);
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
                            Log::write(json_encode($seniorRebateData), 'operateError');
                        }else{
                            $bigData = [
                                'server_id' => '-',
                                'user_id' => $seniorRebateData[0]['uid'],
                                'role_id' => '-'.'_'.$seniorRebateData[0]['uid'],
                                'role_name' => '-',
                                'client_id' => '-',
                                'client_type' => '-',
                                'system_type' => '-',
                                'ip' => '-',

                                'club_id' => $roomHashInfo['clubId'],
                                'club_name' => $roomHashInfo['clubName'],
                                'club_region_id' => $roomHashInfo['clubRegionId'],
                                'club_region_name' => $roomHashInfo['clubRegionName'],
                                'club_mode' => $roomHashInfo['clubMode'],
                                'do_rebate_user_id' => $roomHashInfo['presidentId'],
                                'do_rebate_user_name' => $roomHashInfo['presidentNickName'],
                                'token_name' => 'money',
                                'token_num' => bcmul($seniorRebateData[0]['change_num'], 100, 0),
                            ];
                            $beeSender->add_batch('highlevel_club_rebate', $bigData);
                        }
                    }
                }

                if($roomHashInfo['commerceId']){ # 商务会长
                    $businessNum = bcdiv(bcmul($rebate, $roomHashInfo['businessRebate'], 0), 100, 0);
                    if($businessNum > 0){
                        $businessRebateData[] = [
                            'uid' => $roomHashInfo['commerceId'],
                            'event_type' => '+',
                            'reason_id' => 5,
                            'property_type' => Definition::$PRESIDENT_REBATE,
                            'property_name' => '赠送蓝钻',
                            'change_num' => $businessNum, # 高级会长返利
                        ];
                        $res = operatePlayerProperty($businessRebateData);
                        if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                            Log::write(json_encode($businessRebateData), 'operateError');
                        }else{
                            $bigData = [
                                'server_id' => '-',
                                'user_id' => $businessRebateData[0]['uid'],
                                'role_id' => '-'.'_'.$businessRebateData[0]['uid'],
                                'role_name' => '-',
                                'client_id' => '-',
                                'client_type' => '-',
                                'system_type' => '-',
                                'ip' => '-',

                                'do_rebate_user_id' => $roomHashInfo['seniorPresidentId'],
                                'do_rebate_user_name' => $roomHashInfo['seniorPresidentNickName'],
                                'token_name' => 'money',
                                'token_num' => bcmul($businessRebateData[0]['change_num'], 100, 0),
                            ];
                            $beeSender->add_batch('business_club_rebate', $bigData);
                        }
                    }
                }
            }
        }
        $beeSender->batch_send();
        if(!$this->opt['round']){
            $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId']);
        }
        # 玩家扣钻模式完成
        return jsonRes(0);
    }


}