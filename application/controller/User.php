<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/11
 * Time: 12:00
 */

namespace app\controller;


use app\definition\Definition;
use app\definition\RedisKey;
use app\model\GameServiceNewModel;
use app\model\PlayModel;
use app\model\ClubModel;
use app\model\RoomOptionsModel;
use app\model\ServiceGatewayNewModel;
use app\model\UserEvaluateModel;
use app\model\UserLastClubModel;
use think\cache\driver\Redis;
use think\Log;
use think\Env;

class User
{
    /**
     * 获取用户的信息
     * @return \think\response\Json\
     */
    public function getUserInfo()
    {
        //实例化model
        $lastClubModel = new UserLastClubModel();
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }

        //获取未读取邮件数量
        $email_num  = $this ->getEmailNum($user_id);

        //获取用户基本信息
        $user_info  = getUserBaseInfo($user_id);
        //获取用户电话
        $phone_num  = isset($user_info['tel_number']) ? $user_info['tel_number'] : '' ;
        //获取是否显示招募代理入口参数
        $is_open    = (int)Env::get('recruit.is_open');

        //获取上次登录的俱乐部id
        $lastClub = $lastClubModel -> getLastClubId($user_id);
        if(!$lastClub){
            $club_name = '';
            $club_id = '';
        }else{
            $club_id = $lastClub['club_id'];
            $club_name = $this -> getClubName($lastClub['club_id']);
        }

        //检测玩家是否存在于房间中
        $user_room_info = $this -> checkPlayer($user_id);

        //返回房间信息
        $roomInfo = $this -> getRoomInfo($user_room_info);

        //获取用户评价数量
        $evaluate = $this -> getEvaluate($user_id);

        //获取用户资产（返回钻石数量）
        $assets = $this -> getUserAssets($user_id);

        $result = [
            'phone_num' => $phone_num,
            'agent_recruit'=> $is_open,
            'player_id'=> $user_id,
            'new_mail' => $email_num,
            'nickname' => $user_info['nickname'],
            'head_img' => $user_info['headimgurl'],
            'ismark'   => $user_info['ismark'], //是否绑定手机
            'is_tag'   => isset($user_info['istag']) ? $user_info['istag'] : 0, // 是否绑定微信或者吹牛
            'club_name'=> $club_name,
            'club_id'  => $club_id,
            'room_id'  => $roomInfo['room_id'],
            'socket_url'=>$roomInfo['socket_url'],
            'socket_h5'=> $roomInfo['socket_h5'],
            'check'    => $roomInfo['check'],
            'options'  => $roomInfo['options'],
            'socket_ssl'=> Env::get('socket_ssl'),
            'notification_h5'=> Env::get('notification_h5'),
            'notification_url'=> Env::get('notification_url'),
            'match_service' => Env::get('match_service'),
            'good_nums'=> $evaluate['good_num'],
            'bad_nums' => $evaluate['bad_num'],
            'diamond_num'=> $assets['diamond_num'],
            'gold_num' => $assets['gold_num']
        ];
        return jsonRes( 0 , $result);
    }

    /**
     * 解散房间
     * @param $user_id
     */
    private function disBandRoom($user_id){

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
                    $userRoom = sendHttpRequest($v['service'].Definition::$GET_USER_ROOM, ['playerId' => $user_id]);
                    if(isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                        $disBandRes = sendHttpRequest($v['service'].Definition::$DIS_BAND_ROOM.$userRoom['content']['roomId'], ['playerId' => $user_id]);
                        if(isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                            return;
                        }
                    }
                }
            }
        }
        Log::write(date('Y-m-d H:i:s',time()).'解散房间失败','disBandRoomError');
        return;
    }

    /**
     * 获取用户资产
     * @param $user_id
     */
    private function getUserAssets($user_id){
        //资产类型
        $property_type = [10000,10001,10002];
        $userAssets = getUserProperty($user_id,$property_type);
        $diamond_num = 0; //钻石数量
        $gold_num = 0; //金币数量
        if(!empty($userAssets['data'])){
            foreach ($userAssets['data'] as $val){

                switch ($val['property_type']){
                    case 10000: //金币
                        $gold_num += $val['property_num'];
                        break;
                    case 10001: //购买钻
                        $diamond_num += $val['property_num'];
                        break;
                    case 10002: //赠送钻
                        $diamond_num += $val['property_num'];
                        break;
                }
            }
        }
        $assets = [
            'diamond_num' => $diamond_num,
            'gold_num'    => $gold_num,
        ];
        return $assets;
    }

    /**
     * 获取用户的评价数量
     * @param $user_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getEvaluate($user_id){
        $evaluateModel = new UserEvaluateModel();
        $evalInfo = $evaluateModel ->getInfoById($user_id);
        $evaluate = [
            'good_num' => 0,
            'bad_num'   => 0,
        ];
        if(!empty($evalInfo)){
            $evaluate = [
                'good_num' => $evalInfo['good_num'] ,
                'bad_num'   => $evalInfo['bad_num'],
            ];
        }
        return $evaluate;
    }

    /**
     * 获取房间信息
     * @param $user_room_info
     * @return array
     */
    private function getRoomInfo($user_room_info){
        if (!$user_room_info){
            return $res = [
                'room_id'  => '',
                'socket_url'=>'',
                'socket_h5'=> '',
                'check'    => '',
                'options'  => ''
            ];
        }
        $room_id    = $user_room_info['room_id'];
        $socket_h5  = $user_room_info['socket_h5'];
        $socket_url = $user_room_info['socket_url'];
        $check      = json_decode($user_room_info['playChecks'],true);
        $options    = json_decode($user_room_info['roomOptions'],true);

        return $res = [
            'room_id'  => $room_id,
            'socket_url'=>$socket_url,
            'socket_h5'=> $socket_h5,
            'check'    => $check,
            'options'  => $options
        ];
    }

    /**
     * 检测玩家是否存在于房间中
     * @param $user_id
     */
    private function checkPlayer($user_id){
        //从逻辑服获取房间id
        $room_id = getRoomIdFromService($user_id);
        //不存在房间
        if(!$room_id){
            return false;
        }

        $redis = new Redis();
        $redisHandler = $redis -> handler();
        $user_room_info = $redisHandler -> hMget(RedisKey::$USER_ROOM_KEY_HASH . $room_id ,['socketH5','socketUrl','roomOptions','playChecks']);
        if(!$user_room_info || !$user_room_info['socketH5'] || !$user_room_info['socketUrl']){
            //逻辑服存在，redis里面没有房间解散房间
            $this -> disBandRoom($user_id);
            return false;
        }

        $socket_h5  = $user_room_info['socketH5'];
        $socket_url = $user_room_info['socketUrl'];

        $back_room_info = [
            'room_id'   => $room_id,
            'socket_h5' => $socket_h5,
            'socket_url'=> $socket_url,
//            'match_id'  => $user_room_info['roomOptionsId'],
            'playChecks' => $user_room_info['playChecks'],
            'roomOptions'=> $user_room_info['roomOptions'],

        ];

        return $back_room_info;
    }

    /**
     * 获取俱乐部名称
     * @param $club_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getClubName($club_id){
        $clubModel = new ClubModel();
        $club = $clubModel -> getClubNameById($club_id);
        if(!$club){
            return $club_name = '';
        }
        $club_name = base64_decode($club['club_name']);
        return $club_name;
    }

    /**
     * 获取邮件列表信息
     * @param $user_id
     * @return mixed
     */
    private function getEmailNum($user_id)
    {
        //请求运营中心接口地址
        $url = Env::get('web_user_url');

        //获取运营中心接口邮件列表
        $email_url = Definition::$EMAIL_LIST;

        //请求email需要的数据
        $email_data = [
            'appid'         => Env::get('app_id'),
            'recipient'     => $user_id,
            'read_status'   => 0
        ];
        $result = guzzleRequest( $url , $email_url , $email_data);
        if(!$result){
            return 0;
        }
        return count($result['data']);
    }

    /**
     * 获取玩家的好评和差评数(暂弃)
     * @return \think\response\Json\
     */
    public function getComment(){
        $user_id = getUserIdFromSession();
        //获取用户评价数量
        $evaluate = $this -> getEvaluate($user_id);
        $result = [
            'good_nums'=> $evaluate['good_num'],
            'bad_nums' => $evaluate['bad_num'],
        ];
        return jsonRes(0,$result);
    }

    /**
     * 检测玩家是否在房间中(暂弃)
     * @return \think\response\Json\
     * @throws \think\exception\DbException
     */
    public function checkUserInRoom(){
        //实例化model
        $lastClubModel = new UserLastClubModel();
        $user_id = getUserIdFromSession();

        //获取上次登录的俱乐部id
        $lastClub = $lastClubModel -> getLastClubId($user_id);
        $club_id = $lastClub['club_id'];
        //获取俱乐部名称
        if(!$club_id){
            return jsonRes(3300);
        }
        $club_name = $this -> getClubName($club_id);

        //检测玩家是否存在于房间中
        $user_room_info = $this -> checkPlayer($user_id);

        //返回房间信息
        $roomInfo = $this -> getRoomInfo($user_room_info);
        if(!$roomInfo){
            return jsonRes(23202);
        }
        $result = [
            'club_name'=> $club_name,
            'club_id'  => $club_id,
            'room_id'  => $roomInfo['room_id'],
            'socket_url'=>$roomInfo['socket_url'],
            'socket_h5'=> $roomInfo['socket_h5'],
            'check'    => $roomInfo['check'],
            'options'  => $roomInfo['options'],
            'socket_ssl'=> Env::get('socket_ssl'),
            'notification_h5'=> Env::get('notification_h5'),
            'notification_url'=> Env::get('notification_url'),
        ];

        return jsonRes( 0 , $result);
    }
}