<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/11
 * Time: 12:00
 */

namespace app\controller;


use app\definition\Definition;
use app\model\PlayModel;
use app\model\ServiceGatewayNewModel;
use app\model\ClubModel;
use app\model\RoomOptionsModel;
use app\model\UserEvaluateModel;
use app\model\UserLastClubModel;
use app\model\UserRoomModel;
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
        $is_open    = $this -> isShowAgentEntrance();

        //获取上次登录的俱乐部id
        $lastClub = $lastClubModel -> getLastClubId($user_id);
        $club_id = $lastClub['club_id'];
        //获取俱乐部名称
        $club_name = '';
        if ($club_id){
            $club_name = $this -> getClubName($club_id);
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
            'is_mark'   => $user_info['ismark'], //是否绑定手机
            'club_name'=> $club_name,
            'club_id'  => $club_id,
            'room_id'  => $roomInfo['room_id'],
            'socket_url'=>$roomInfo['socket_url'],
            'socket_h5'=> $roomInfo['socket_h5'],
            'check'    => $roomInfo['check'],
            'options'  => $roomInfo['options'],
            'socket_ssl'=> Definition::$SOCKET_SSL,
            'notification_h5'=> Definition::$NOTIFICATION_H5,
            'notification_url'=> Definition::$NOTIFICATION_URL,
            'match_service' => Definition::$MATCH_SERVICE,
            'good_nums'=> $evaluate['good_num'],
            'bad_nums' => $evaluate['bad_num'],
            'diamond_num'=> $assets['diamond_num'],
            'gold_num' => $assets['gold_num']
        ];
        return jsonRes( 0 , $result);
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
            'good_num' => 0 ,
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
        $userRoomModel = new UserRoomModel();
        $roomOptionModel = new RoomOptionsModel();
        $playModel = new PlayModel();
        //返回的房间信息
        $check      = '';
        $options    = '';
        $room_id    = '';
        $socket_h5  = '';
        $socket_url = '';
        if($user_room_info){
            $room_id    = $user_room_info['room_num'];
            $socket_h5  = $user_room_info['socket_h5'];
            $socket_url = $user_room_info['socket_url'];
            $roomOption = $roomOptionModel ->getOneByWhere(['id' => $user_room_info['match_id']]);
            if (!$roomOption){
                $roomOption = $userRoomModel -> getOptionsByRoomNum($user_room_info['room_id']);
                $roomOption['room_type'] = $roomOption['play_type'];
            }
            $options = [];
            if($roomOption){
                $options = json_decode($roomOption['options']);
            }
            //获取play中的玩法
            $play = $playModel -> getOneByWhere(['id' => $roomOption['room_type']]);
            //获取check
            $check = json_decode($play['play'],true)['checks'];
        }

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
        $userRoomModel = new UserRoomModel();
        $serviceGatewayModel = new ServiceGatewayNewModel();
        //todo 修改这里（房间信息）
        $user_room_info = $userRoomModel -> getUserRoomInfo($user_id);
        $data = [
            'playerId' => (int)$user_id,
        ];
        $back_room_info = [];
        foreach ($user_room_info as $item){
            $service_id = $item['service'];
            $room_num    = $item['room_num'];
            $socket_h5  = $item['socket_h5'];
            $socket_url = $item['socket_url'];
            $serviceInfo = $serviceGatewayModel ->getService($service_id);
            $url = $serviceInfo['service'];
            $path_info = Definition::$GET_USER_ROOM;
            //请求逻辑服
            $lists = guzzleRequest( $url , $path_info , $data);
            $serviceInfo = $lists['content'];
            if(array_key_exists('roomId',$serviceInfo)){
                $club_room = $serviceInfo['roomId'];
                $clubroom = explode('_',$club_room);
                $roomnums = $clubroom[1];//房间号和规则的组合体
                $num1 = strlen($roomnums);//获取字符串长度
                $num2 =  $num1-6;
                $match_id = substr("$roomnums",6,$num2);//规则id
                //说明玩家在房间里，socket_url,和socket_h5,和服务器地址放到数组里
                $back_room_info['room_id'] = $room_num;
                $back_room_info['socket_h5'] = $socket_h5;
                $back_room_info['socket_url'] = $socket_url;
                $back_room_info['room_num'] = $club_room;
                $back_room_info['match_id'] = $match_id;
            }else{
                //说明不在房间里，需要删除掉记录
                $userRoomModel -> delUserRoom($user_id,$room_num);
            }
        }
        //todo 方式后期会删除（暂时储存）

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
     * 显示是否显示招募代理入口
     * @return mixed
     */
    private function isShowAgentEntrance()
    {
        //是否显示招募代理入口
        $file_path = __DIR__ . "/../../application/open_recruit.php";
        $str = file_get_contents($file_path);//将整个文件内容读入到一个字符串中
        $str = str_replace("\r\n","<br />",$str);
        $str = json_decode($str,true);
        return $str['is_open'];
    }

    /**
     * 获取邮件列表信息
     * @param $user_id
     * @return mixed
     */
    private function getEmailNum($user_id)
    {
        //请求运营中心接口地址
        $url = Definition::$WEB_USER_URL;

        //获取运营中心接口邮件列表
        $email_url = Definition::$EMAIL_LIST;

        //请求email需要的数据
        $email_data = [
            'appid'         => Definition::$CESHI_APPID,
            'recipient'     => $user_id,
            'read_status'   => 0
        ];
        $result = guzzleRequest( $url , $email_url , $email_data);

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
            'socket_ssl'=> Definition::$SOCKET_SSL,
            'notification_h5'=> Definition::$NOTIFICATION_H5,
            'notification_url'=> Definition::$NOTIFICATION_URL,
        ];

        return jsonRes( 0 , $result);
    }
}