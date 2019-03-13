<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\controller;

use app\definition\Definition;
use app\model\TbRoomOptions;
use app\model\TbPlay;
use app\model\TbClub;
use think\cache\driver\Redis;
use app\definition\RedisKey;
use app\model\TbUserVip;
use app\model\TbVipCard;
use app\model\ClubSocket;
use app\model\GameServiceNew;
use app\model\UserRoom;
use app\model\ServiceGatewayNew;

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

        # 获取房间开始需要的玩家数
        $roomNeedUserNum = getRoomNeedUserNum($roomOptions);
        # 扣用户资产 判断用户资产是否充足  玩家扣款中不可能出现免费房间，所以rate<0无需处理
        if($clubType == 0){
            # 因为最终需要拿到房费 所以先计算房费
            if($roomRate == 0){ # 判断是否AA扣款
                $needDiamond  = bcdiv($needDiamond, $roomNeedUserNum, 0);
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
        # 扣会长资产 判断会长资产是否充足
        if($clubType == 1){
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

        # 根据俱乐部ID获取俱乐部socket通道
        $clubSocket = new ClubSocket();
        $clubSocketInfo = $clubSocket->getInfoByClubId($clubId);
        if($clubSocketInfo){ # 存在专属通道
            $roomUrl = $clubSocketInfo['room_url'];
            $checkUrl = $roomUrl .'api/v3/room/checkRoom';
            $socketUrl = $clubSocket['socket_url'];
            $socketH5 = $clubSocket['socket_h5'];
            $backList['socket_url'] = $socketUrl;
            $backList['socket_h5'] = $socketH5;
            $serviceId = 1;
        }else{ # 不存在专属通道 要寻找一个压力最小的服务器
            $gameServiceNew = new GameServiceNew();
            $gameServiceNewInfos = $gameServiceNew->getInfosByRoomTypeId($roomType);
            //声明一个空数组,以服务器的ID为键,数量为值存进去
            $serviceRoomNumArr = [];
            $userRoom = new UserRoom();
            foreach ($gameServiceNewInfos as $k => $v){
                # 根据服务器ID获取服务器房间数
                $serviceRoomNum = $userRoom->getServiceRoomNumByServiceId($v['service_id']);
                $serviceRoomNumArr[$v['service_id']] = $serviceRoomNum;//服务器的ID为键,数量为值
            }
            $serviceId = array_search(min($serviceRoomNumArr), $serviceRoomNumArr);//数量最小的服务器

            # 根据服务器的ID查出服务器的地址
            $serviceGatewayNew = new ServiceGatewayNew();
            $serviceInfo = $serviceGatewayNew->getInfoById($serviceId);
            $roomUrl = '';
            if(!$serviceInfo){
                $roomUrl = Definition::$ROOM_URL;
                $socketH5 = Definition::$SOCKET_H5;
                $socketApp = Definition::$SOCKET_URL;
            }else{
                $roomUrl = $serviceInfo['service'];
                $socketH5 = $serviceInfo['gateway_h5'];
                $socketApp = $serviceInfo['gateway_app'];
            }
        }

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);

        # 逻辑服需要的数据
        $roomId = $roomNumber.$matchId;
        $roomRule = json_decode($roomRule,true);
        $roomRule['options'] = $roomOptions;
        $check = $roomRule['checks'];

        # 创建房间 成功返回房间号 失败
        $clubSocket = new ClubSocket();
        $clubSocketInfo = $clubSocket->getInfoByClubId($clubId);

        if($clubSocketInfo){
            $roomUrl = $clubSocketInfo['room_url'];
            $url = $roomUrl."api/v3/room/createRoom/".$userSessionInfo['userid'];
        }else{
            //不存在则调用base里的方法查出socket和roomurl
            if ($roomType){
                $new_service = new \app\controller\Base();
                $new_roomurl = $new_service->getService($room_type);
                $room_url = $new_roomurl['room_url'];
            }else{
                $room_url = Definition::$ROOM_URL;
            }
            $url = $roomUrl.'api/v3/room/createRoom/'.$userSessionInfo['userid'];
        }

        $data['roomId'] = $clubId.'_'.$roomId;
        $data['config'] = $contion;
        $data = json_encode($data);
        \think\Log::write($data,'send_lijiang_data');
        $list = postInterface($url,$data);
        \think\Log::write($list,'back_creatroom');
        $list = json_decode($list,true);
        return $list;
        var_dump($roomNumber);die;
    }
}