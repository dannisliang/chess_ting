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
//        $res = json_encode(['userid' => 552610]);
//        session::set(RedisKey::$USER_SESSION_INDO, $res);

//        # 检查用户登录状态
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
            $type = 10001;
            $diamondInfo = getUserProperty($userSessionInfo['userid'], $type);

            if($diamondInfo && isset($diamondInfo['data'][0]['property_num'])){
                $diamondNum = $diamondInfo['data'][0]['property_num'];
            }
            if($diamondNum < $needDiamond){ # 非绑定钻石不够 获取绑定钻石 判断是是否能够开房
                $bindingDiamondNum = 0;
                $type = 10002;
                $bindingDiamondInfo = getUserProperty($userSessionInfo['userid'], $type);
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
            $type = $this->opt['club_id'].'_'.$clubInfo['president_id'].'_10003';
            $diamondInfo = getUserProperty($clubInfo['president_id'], $type);
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
        $clubSocket = new ClubSocketModel();
        $clubSocketInfo = $clubSocket->getClubSocketInfoByClubId($this->opt['club_id']);
        if($clubSocketInfo){ # 存在专属通道
            $roomUrl = $clubSocketInfo['room_url'];
            $checkUrl = $roomUrl.Definition::$CHECK_ROOM;
            $socketUrl = $clubSocket['socket_url'];
            $socketH5 = $clubSocket['socket_h5'];
            $backList['socket_url'] = $socketUrl;
            $backList['socket_h5'] = $socketH5;
            $serviceId = 1;
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
            if(!$serviceGatewayNewInfo){
                $serviceGatewayNewInfo['service'] = Definition::$ROOM_URL;
                $serviceGatewayNewInfo['gateway_h5'] = Definition::$SOCKET_H5;
                $serviceGatewayNewInfo['gateway_app'] = Definition::$SOCKET_URL;
            }
            $roomUrl = $serviceGatewayNewInfo['service'];
        }

        # 逻辑服需要的开房数据
        $data['roomId'] = $roomNumber.$this->opt['match_id'];
        $data['config'] = $playInfoPlayJsonDecode;
        $data['config']['options'] = $roomOptionsInfoOptionsJsonDecode;

        # 创建房间 成功返回房间号
        $requestUrl = $roomUrl.Definition::$CREATE_ROOM.$userSessionInfo['userid'];
        $createRoomInfo = sendHttpRequest($requestUrl, $data);

        if(!isset($createRoomInfo['content']['result'])){
            return jsonRes(1111);
        }

        if($createRoomInfo['content']['result'] == 10000){ # 房间不存在
            return jsonRes(23202);
        }

        if($createRoomInfo['content']['result'] == 10001){ # 房间已满
            return jsonRes(23204);
        }

        if($createRoomInfo['content']['result'] == 10002){ # 玩家已经在房间里
            return jsonRes(9999);
        }

        if($createRoomInfo['content']['result'] == 10003){ # 房间已存在
            return jsonRes(23203);
        }

        # 写数据
    }
}