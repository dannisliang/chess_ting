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
        Session::set(RedisKey::$USER_SESSION_INDO, $sess);

        # 检查用户登录状态
//        $checkUserToken = checkUserToken();
//        if($checkUserToken || !isset($checkUserToken['result']) || !$checkUserToken['result']){
//            return jsonRes(9999);
//        }

        # 获取用户的session数据
        $userSessionInfo = getUserSessionInfo();

        # 判断传参是否有效
        if(!isset($this->opt['match_id']) || !isset($this->opt['club_id'])){
            return jsonRes(3006);
        }

        # 根据玩法规则ID获取规则
        $roomOptions = new RoomOptionsModel();
        $roomOptionsInfo = $roomOptions->getRoomOptionInfoByRoomOptionsId($this->opt['match_id']);
        if(!$roomOptionsInfo){
            return jsonRes(3999);
        }

        # 根据俱乐部ID获取俱乐部相关数据
        $club = new ClubModel();
        $clubInfo = $club->getClubInfoByClubId($this->opt['club_id']);
        if(!$clubInfo){
            return jsonRes(3999);
        }

        # 根据房间类型ID获取房间玩法相关数据（大json）
        $play = new PlayModel();
        $playInfo = $play->getPlayInfoByPlayId($roomOptionsInfo['room_type']);
        if(!$playInfo){
            return jsonRes(3999);
        }

        # 房费
        $needDiamond = $roomOptionsInfo['diamond'];

        # 玩法规则json解码
        $playInfoPlayJsonDecode = json_decode($playInfo['play'], true);

        # 房间规则json解码
        $roomOptionsInfoOptionsJsonDecode = json_decode($roomOptionsInfo['options'], true);

        # 获取房间开始需要的玩家数
        $roomNeedUserNum = getRoomNeedUserNum($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode);

        # 资产判断
        if($clubInfo['club_type'] == 0){ # 扣用户资产 判断用户资产是否充足  玩家扣款中不可能出现免费房间，所以rate<0无需处理
            # 因为最终需要拿到房费 所以先计算房费
            if($roomOptionsInfo['room_rate'] == 0){ # 判断是否AA扣款
                $needDiamond  = bcdiv($needDiamond, $roomNeedUserNum, 0);
            }
            # 获取折扣
            $discount = 1;
            $userVip = new UserVipModel();
            $userVipInfo = $userVip->getUserVipInfoByUserIdAndClubId($userSessionInfo['userid'], $this->opt['club_id']);
            if($userVipInfo){
                $vipCardId = $userVipInfo['vid'];
                $vipCard = new VipCardModel();
                $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($vipCardId);
                if($vipCardInfo){
                    $discount = bcdiv($vipCardInfo['diamond_consumption'], 100, 1);
                }
            }
            $needDiamond = bcmul($discount, $needDiamond, 0); # 最终房费
            # 获取非绑定钻石数 判断是否能够开房
            $diamondNum = 0;
            $propertyType = 10001;
            $diamondInfo = getUserProperty($userSessionInfo['userid'], $propertyType);

            if($diamondInfo && isset($diamondInfo['data'][0]['property_num'])){
                $diamondNum = $diamondInfo['data'][0]['property_num'];
            }
            if($diamondNum < $needDiamond){ # 非绑定钻石不够 获取绑定钻石 判断是是否能够开房
                $bindingDiamondNum = 0;
                $propertyType = 10002;
                $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], $propertyType);
                if($bindingDiamondInfo && isset($bindingDiamondInfo['data'][0]['property_num'])){
                    $bindingDiamondNum = $bindingDiamondInfo['data'][0]['property_num'];
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

        # 扣会长资产 判断会长资产是否充足
        if($clubInfo['club_type'] == 1){
            $userDiamond = 0;
            $propertyType = $this->opt['club_id'].'_'.$clubInfo['president_id'].'_10003';
            $diamondInfo = getUserProperty($clubInfo['president_id'], $propertyType);
            if($diamondInfo && isset($diamondInfo['data'][0]['property_num'])){
                $userDiamond = $diamondInfo['data'][0]['property_num'];
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

        # 根据俱乐部ID获取俱乐部socket通道
        $returnArr = [
            'room_num' => $roomNumber, # 房间号
            'need_gold' => $needDiamond, # 所需钻石
            'check' => $playInfoPlayJsonDecode['checks'], # play表的json的checks
            'options' => $roomOptionsInfoOptionsJsonDecode, # room_options表的options
            'socket_ssl' => '',
            'socket_h5' => '',
            'socket_url' =>  '',
        ];

        $clubSocket = new ClubSocketModel();
        $clubSocketInfo = $clubSocket->getClubSocketInfoByClubId($this->opt['club_id']);
        if($clubSocketInfo){ # 存在专属通道
            $serviceId = 1; # 创建房间需要的服务器ID
            $createRoomUrl = $clubSocketInfo['room_url']; # 创建房间需要请求逻辑服的地址
            $checkUrl = $createRoomUrl.Definition::$CHECK_ROOM;
            $returnArr['socket_h5'] = $clubSocket['socket_h5'];
            $returnArr['socket_url'] = $clubSocket['socket_url'];
        }else{ # 不存在专属通道 要寻找一个压力最小的服务器
            $gameServiceNew = new GameServiceNewModel();
            $gameServiceNewInfos = $gameServiceNew->getGameServiceNewInfosByRoomTypeId($roomOptionsInfo['room_type']);
            //声明一个空数组,以服务器的ID为键,数量为值存进去
            $serviceRoomNumArr = [];
            $userRoom = new UserRoomModel();
            foreach ($gameServiceNewInfos as $k => $v){
                # 根据服务器ID获取服务器房间数
                $serviceRoomNum = $userRoom->getServiceRoomNumByServiceId($v['service_id']);
                $serviceRoomNumArr[$v['service_id']] = $serviceRoomNum;//服务器的ID为键,数量为值
            }
            $serviceId = array_search(min($serviceRoomNumArr), $serviceRoomNumArr);//数量最小的服务器
            # 根据服务器的ID查出服务器的地址
            $serviceGatewayNew = new ServiceGatewayNewModel();
            $serviceGatewayNewInfo = $serviceGatewayNew->getServiceGatewayNewInfoByServiceId($serviceId);
            if($serviceGatewayNewInfo){
                $createRoomUrl = $serviceGatewayNewInfo['service'];
                $returnArr['socket_h5'] = $serviceGatewayNewInfo['gateway_h5'];
                $returnArr['socket_url'] = $serviceGatewayNewInfo['gateway_app'];
            }else{
                $createRoomUrl = Definition::$ROOM_URL;
                $returnArr['socket_h5'] = Definition::$SOCKET_H5;
                $returnArr['socket_url'] = Definition::$SOCKET_URL;
            }
        }



        $playerIds = json_encode([$userSessionInfo['userid']]); # 房间的用户ID集
        $playerIps = json_encode([getUserIp()]); # 房间的用户IP集
        $needDiamonds = json_encode([[$userSessionInfo['userid'] => $needDiamond]]); # 需要支付的钻石集

        # 先在redis创建房间 后请求逻辑服
        $redisHashValue = [
            'createTime' => date('Y-m-d H:i:s'), # 房间创建时间
            'owner' => $userSessionInfo['userid'], # 房间创始人
            'roomNum' => $roomNumber, # 房间需要的人数
            'serviceId' => $serviceId, # 服务器ID
            'diamond' => $roomOptionsInfo['diamond'], # 进房需要的钻石  没均分没折扣的值
            'playerNum' => 1, # 当前玩家数
            'joinStatus' => 1, # 其他人是否能够申请加入
            'clubId' => $this->opt['club_id'], # 俱乐部ID
            'clubType' => $clubInfo['club_type'], # 俱乐部结算类型 免费房间和不免费房间 凌驾于roomRate之上
            'roomRate' => $roomOptionsInfo['room_rate'], # 房间结算类型 大赢家/房主/均摊
            'roomCheat' => $roomOptionsInfo['cheat'], # 是否检查GPS房间
            'roomType' => $roomOptionsInfo['room_type'], # 规则表中的类型 对应play表Id
            'roomId' => $this->opt['match_id'], # roomOptionsID

            'socketH5' => $returnArr['socket_h5'], # H5的socket连接地址
            'socketUrl' => $returnArr['socket_url'], # socket的连接地址

            'roomCheck' => json_encode(json_decode($playInfo['play'], true)['checks']), # 玩法数据中的play的checks json
            'diamonds' => $needDiamonds, # 每个用户需要扣的钻石集 json
            'playerIds' => $playerIds, # 房间的用户ID集 json
            'playerIps' => $playerIps, # 玩家IP地址集 json
            'roomOptions' => $roomOptionsInfo['options'], # 玩法相关数据 json
        ];

        $hsetRes = $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $redisHashValue);
        if(!$hsetRes){ # 创建房间写Redis失败
            return jsonRes(23205);
        }

        # 会长模式提前结算
        if($clubInfo['club_type'] == 0){
            $subDiamond = bcsub(0, $needDiamonds, 0);
            $operaRes = operaUserProperty($clubInfo['president_id'], $propertyType, $subDiamond);
            if(!$operaRes || !isset($operaRes['code']) || ($operaRes['code'] != 0)){ # 扣费失败 删除房间
                $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber);
                return jsonRes(23205);
            }
        }

        # 请求逻辑服创建房间
        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;
        $createRoomInfo = sendHttpRequest($createRoomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'], $data);
//        print_r($createRoomInfo);die;
        if(!$createRoomInfo || !isset($createRoomInfo['content']['result']) || ($createRoomInfo['content']['result'] != 0)){ # 创建房间失败
            # 10002已经在房间 10003号存在
            $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber);
            # 还钻
            if($clubInfo['club_type'] == 0){ # 会长模式还钻
                operaUserProperty($clubInfo['president_id'], $propertyType, $needDiamond);
            }
            return jsonRes(23205);
        }

        # 加入到俱乐部房间集合中
        $redisHandle->sadd(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
        # 返回客户端
        return jsonRes(0, $returnArr);
    }


    # 玩家加入房间
    public function joinRoom(){

    }

    # 强制解散房间
    public function disBandRoom(){
        $userSessionInfo = getUserSessionInfo();
        print_r(disBandRoom('http://192.168.9.18:9920/', $userSessionInfo['userid'], 266494));die;
    }

    #
}