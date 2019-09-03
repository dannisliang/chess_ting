<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:27
 */
namespace app\controller;

use think\Log;
use think\Env;
use think\Session;
use app\model\AreaModel;
use app\model\PlayModel;
use app\model\ClubModel;
use app\model\VipCardModel;
use app\model\UserVipModel;
use app\model\CommerceModel;
use app\model\UserClubModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\model\ClubSocketModel;
use app\definition\Definition;
use app\model\RoomOptionsModel;
use app\model\GameServiceNewModel;
use app\model\ServiceGatewayNewModel;




class CreateRoom extends Base
{
    /**
     * 获取房间号
     * @param $redisHandle object redis实例
     * @return int
     */
    public function getRoomNum($redisHandle){
        $roomNum = 0;
        $lua = <<<SCRIPT
        local k1 = KEYS[1]
        local v1 = tonumber(ARGV[1])
        local v2 = ARGV[2]
        return redis.call('zadd', k1, 'NX', v1, v2);
SCRIPT;

        $timeOut = bcadd(time(), 2, 0);
        while (true){
            if(time() > $timeOut){
                break;
            }
            $rand = mt_rand(100000, 999999);
            $res = $redisHandle->eval($lua, array(RedisKey::$USED_ROOM_NUM, 0, $rand), 1);
            if($res){
                $roomNum = $rand;
                break;
            }
            usleep(1000);
        }
        return $roomNum;
    }

    public function createRoom(){
        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id']) || !is_numeric($this->opt['match_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        # 获取用户的session数据
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return jsonRes(9999);
        }

        $checkTokenRes = checkUserToken($userSessionInfo);
        if(!isset($checkTokenRes['result']) || ($checkTokenRes['result'] == false)){
            return jsonRes(9999);
        }

        # 查询玩家是否加入此俱乐部
        $userClub = new UserClubModel();
        $userClubInfo = $userClub->getUserClubInfo($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userClubInfo){
            return jsonRes(3511);
        }

        # 根据俱乐部ID获取俱乐部相关数据
        $club = new ClubModel();
        $clubInfo = $club->getClubInfo($this->opt['club_id']);
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
        $roomOptionsInfo = $roomOptions->getRoomOptionInfo($this->opt['match_id']);
        if(!$roomOptionsInfo){
            return jsonRes(3501);
        }

        if(!in_array($roomOptionsInfo['room_type'], explode(',', $clubInfo['play_id']))){
            return jsonRes(3502);
        }

        # 根据房间类型ID获取房间玩法相关数据（大json）
        $play = new PlayModel();
        $playInfo = $play->getPlayInfo($roomOptionsInfo['room_type']);
        if(!$playInfo){
            return jsonRes(3501);
        }

        # 根据玩法的类型去查找玩法启动的服务
        $gameServiceNew = new GameServiceNewModel();
        $serviceInfos = $gameServiceNew->getService($playInfo['play_type']);
        if(!$serviceInfos) {
            return jsonRes(3521);
        }

        $rand = rand(0, count($serviceInfos)-1);
        $serviceId = $serviceInfos[$rand]['service_id'];
        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfo($serviceId);
        if(!$serviceGatewayNewInfo){
            return jsonRes(3517);
        }

        $rand = rand(0, count($serviceGatewayNewInfo)-1);
        $httpUrl = $serviceGatewayNewInfo[$rand]['service'];
        $socketH5 = $serviceGatewayNewInfo[$rand]['gateway_h5'];
        $socketUrl = $serviceGatewayNewInfo[$rand]['gateway_app'];

        if(Env::get('is_online') == false && in_array($this->opt['club_id'], [555555, 999999, 888888, 777777])){
            $clubSocket = new ClubSocketModel();
            $clubSocketInfo = $clubSocket->getClubSocketInfo($this->opt['club_id']);
            $serviceId = $clubSocketInfo['id'];
            $httpUrl = $clubSocketInfo['room_url'];
            $socketH5 = $clubSocketInfo['socket_h5'];
            $socketUrl = $clubSocketInfo['socket_url'];
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
        $userVipInfo = $userVip->getUserVipInfo($userSessionInfo['userid'], $this->opt['club_id']);
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
                $vipCardInfo = $vipCard->getVipCardInfo($userVipInfo['vid']);
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
                    return jsonRes(40002, $returnData);
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
                        return jsonRes(40002, $returnData);
                    }
                }
            }
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 生成房间号
        $roomNumber = $this->getRoomNum($redisHandle);
        if(!$roomNumber){
            return jsonRes(3517);
        }
        $roomNumber = (string)$roomNumber;

        # 扣会长资产 判断会长资产是否充足 充足直接结算
        if($clubInfo['club_type'] == 1 && ($needDiamond > 0)){ # 直接扣钻
            $payMode = 'free';
            $operateData[] = [
                'uid' => $clubInfo['president_id'],
                'event_type' => '-',
                'reason_id' => 7,
                'property_type' => $this->opt['club_id'].'_'.$clubInfo['president_id'].'_'.Definition::$USER_PROPERTY_PRESIDENT,
                'property_name' => '赠送蓝钻',
                'change_num' => $needDiamond
            ];
            $operaRes = operatePlayerProperty($operateData);
            if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){
                $redisHandle->zRem(RedisKey::$USED_ROOM_NUM, $roomNumber);
                $returnData = [
                    'need_diamond' => $needDiamond
                ];
                return jsonRes(40002, $returnData);
            }
        }

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($httpUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        p($createRoomInfo);
        if(!isset($createRoomInfo['content']['result'])){ # 创建房间失败
            $redisHandle->zRem(RedisKey::$USED_ROOM_NUM, $roomNumber);
            if($clubInfo['club_type'] == 1 && ($needDiamond > 0)){ # 还钻
                $operateData[] = [
                    'uid' => $clubInfo['president_id'],
                    'event_type' => '+',
                    'reason_id' => 8,
                    'property_type' => $this->opt['club_id'].'_'.$clubInfo['president_id'].'_'.Definition::$USER_PROPERTY_PRESIDENT,
                    'property_name' => '赠送蓝钻',
                    'change_num' => $needDiamond
                ];
                $operaRes = operatePlayerProperty($operateData);
                if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){
                    Log::write(json_encode($operateData), 'operateError');
                }
            }
            return jsonRes(3517);
        }else{
            if($createRoomInfo['content']['result'] != 0){
                $redisHandle->zRem(RedisKey::$USED_ROOM_NUM, $roomNumber);
                if($clubInfo['club_type'] == 1 && ($needDiamond > 0)){ // 还钻
                    $operateData[] = [
                        'uid' => $clubInfo['president_id'],
                        'event_type' => '+',
                        'reason_id' => 8,
                        'property_type' => $this->opt['club_id'].'_'.$clubInfo['president_id'].'_'.Definition::$USER_PROPERTY_PRESIDENT,
                        'property_name' => '赠送蓝钻',
                        'change_num' => $needDiamond
                    ];
                    $operaRes = operatePlayerProperty($operateData);
                    if(!isset($operaRes['code']) || ($operaRes['code'] != 0)){
                        Log::write(json_encode($operateData), 'operateError');
                    }
                }

                if($createRoomInfo['content']['result'] == 10002){
                    return jsonRes(9999);
                }else{
                    return jsonRes(3517);
                }
            }
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
            'founderId' => '', // 接受回调保存房主ID
            'players' => '', // 接受回调保存玩家
            'createTime' => date('Y-m-d H:i:s'), // 房间创建时间
            'needUserNum' => $needUserNum, // 房间需要的人数
            'serviceId' => $serviceId, // 服务器ID
            'diamond' => $roomOptionsInfo['diamond'], // 进房需要的钻石  没均分没折扣的值
            'joinStatus' => 1, // 其他人是否能够申请加入
            'clubId' => $this->opt['club_id'], // 俱乐部ID
            'clubType' => $clubInfo['club_type'], // 俱乐部结算类型 免费房间和不免费房间 凌驾于roomRate之上
            'roomRate' => $roomOptionsInfo['room_rate'], // 房间结算类型 大赢家/房主/均摊
            'roomCheat' => $roomOptionsInfo['cheat'], // 是否检查GPS房间
            'roomType' => $roomOptionsInfo['room_type'], // 规则表中的类型 对应play表Id
            'socketH5' => $socketH5, // H5的socket连接地址
            'socketUrl' => $socketUrl, // socket的连接地址
            'socketSsl' => Env::get('socket_ssl'), // socket证书
            'roomUrl' => $httpUrl, // 房间操作的接口的请求地址
            'playChecks' => json_encode($playInfoPlayJsonDecode['checks']), // 玩法数据中的play的checks json
            'roomCode' => $playInfoPlayJsonDecode['code'], // 客户端需要
            'roomOptions' => $roomOptionsInfo['options'], // 玩法相关数据 json
            'playerInfos' => json_encode($playerInfo), // 用户信息集 json
            'isGps' => $roomOptionsInfo['cheat'], // 是否判断gps 0不检测
            'gpsRange' => $clubInfo['gps'], // gps检测距离
            'presidentId' => $clubInfo['president_id'], // 普通会长ID
            'generalRebate' => $clubInfo['pin_drilling_ratio'], // 普通会长返利比
            'seniorPresidentId' => $clubInfo['senior_president'], // 高级会长ID
            'seniorRebate' => $clubInfo['rebate'], // 高级会长返利比
            'gameStartTime' => '', // 房间开始时间
            'gameEndTime' => '', // 房间结束时间
            'roundEndInfo' => '', // 对局结束相关数据
            'gameEndInfo' => '', // 房间结束相关数据
            'roomName' => $playInfoPlayJsonDecode['name'], // 房间记录列表需要

            # 大数据报送
            'roomOptionsId' => $this->opt['match_id'], // roomOptionsID
            'roomTypeName' => $roomOptionsInfo['room_name'],
            'roomChannel' => 1, // 房间渠道：朋友diy/俱乐部会长房间
            'ruleDetail' => '-', // 创建房间时勾选的详细玩法
            'tableType' => $tableType, // 按圈玩还是按局玩
            'setNum' => $setNum, // 圈数
            'tableNum' => $roundNum, // 创建房间时所选择的要进行多少牌局
            'betNums' => $baseScore, // 底分数量
            'clubName' => base64_decode($clubInfo['club_name']), // 俱乐部名称
            'clubRegionId' => $clubInfo['area_id'], // 俱乐部地域id
            'clubRegionName' => $areaName, // 俱乐部地域名
            'clubMode' => $clubMode, // 房间模式
            'payMode' => isset($payMode) ? $payMode : '-', // 支付方式
            'presidentNickName' => isset($presidentNickName) ? $presidentNickName : '-', // 会长昵称
            'seniorPresidentNickName' => isset($seniorPresidentNickName) ? $seniorPresidentNickName : '-', // 高级会长昵称
            'commerceId' => isset($commerce['commerce_id']) ? $commerce['commerce_id'] : '', // 商务会长ID
            'businessRebate' => $clubInfo['business_rebate'], // 上午会长返利比
        ];

        # 写房间hash 写失败记录日志
        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $roomHashInfo);

        # 加入到俱乐部房间集
        $redisHandle->sAdd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);

        # 接口返回值
        $returnData = [
            'need_gold' => $needDiamond, # 所需钻石
            'check' => $playInfoPlayJsonDecode['checks'], # play表的json的checks
            'options' => $roomOptionsInfoOptionsJsonDecode, # room_options表的options
            'room_num' => $roomNumber, # 房间号
            'socket_ssl' => Env::get('socket_ssl'),
            'socket_h5' => $socketH5,
            'socket_url' =>  $socketUrl,
        ];
        # 返回客户端
        return jsonRes(0, $returnData);
    }
}