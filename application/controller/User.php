<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/11
 * Time: 12:00
 */

namespace app\controller;


use app\definition\Definition;
use app\model\GetLastClub;
use app\model\ServiceGatewayNewModel;
use app\model\TbClub;
use app\model\TbRoomOptions;
use app\model\UserLastClubModel;
use app\model\UserRoomModel;
use guzzle\GuzzleHttp;
class User extends Base
{
    public function getUserInfo()
    {
        //实例化model
        $lastClubModel = new UserLastClubModel();
        session('player_id',328946);
        $user_id    = session('player_id'); //收件人id

        //获取未读取邮件数量
        $email_num  = $this ->getEmailNum($user_id);

        //获取用户基本信息
        $user_info  = $this -> getUserBaseInfo($user_id);

        //获取用户电话
        $phone_num  = isset($user_info['tel_number']) ? $user_info['tel_number'] : '' ;
        //获取是否显示招募代理入口参数
        $is_open    = $this -> isShowAgentEntrance();

        //获取上次登录的俱乐部id
        $lastClub = $lastClubModel -> getLastClubId($user_id);
        $club_id = $lastClub['club_id'];
        //获取俱乐部名称
        if (!$lastClub){
            $club_name = '';
        }else{
            $club_name = $this -> getClubName($club_id);
        }

        //检测玩家是否存在于房间中
        $user_room_info = $this -> checkPlayer($user_id);

        //返回房间信息
        $this ->getRoomInfo($user_room_info);

        $result = [
            'phone_num' => $phone_num,
            'agent_recruit'=>$is_open,
            'player_id'=> $user_id,
            'new_mail' => $email_num,
            'nickname' => $user_info['nickname'],
            'head_img' => $user_info['headimgurl'],
            'club_name'=> $club_name,
            'club_id'  => $club_id,
//            'room_id'  => $room_id,
//            'socket_url'=>$socket_url,
//            'socket_h5'=> $socket_h5,
//            'check'    => $check,
//            'options'  => $options
        ];
        return jsonRes( 0 , $result);
//        echo '<pre/>';
//        print_r($result);die;
    }

    /**
     * 获取返回房间的信息
     */
    private function getRoomInfo($user_room_info){
        $roomOptionModel = new TbRoomOptions();
        //返回的房间信息
        $check      = '';
        $options    = '';
        $room_id    = '';
        $socket_h5  = '';
        $socket_url = '';
        if($user_room_info){
            $roomOption = $roomOptionModel ->getInfoById($user_room_info['match_id']);
            if (!$roomOption){

            }
            var_dump($roomOption);die;
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
            $path_info = 'api/v3/room/checkPlayer';
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
        $clubModel = new TbClub();
        $club = $clubModel -> getClubNameById($club_id);
        if(!$club){
            return $club_name = '';
        }
        $club_name = $club['club_name'];
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
     * 获取用户的基本信息
     * @param $user_id
     * @return mixed
     */
    private function getUserBaseInfo($user_id)
    {

        //请求用户中心接口地址
        $url = Definition::$WEB_API_URL;
        //获取用户中心接口路径
        $userInfo_url = 'api/get_info.php';
        //向用户中心传输的请求参数
        $data = [
            'uid' => $user_id,
            'app_id'=> Definition::$CESHI_APPID,
        ];
        $result = guzzleRequest( $url , $userInfo_url , $data);

        return $result['data'];
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

        //获取运营中心接口
        $email_url = 'api/email_list.php';

        //请求email需要的数据
        $email_data = [
            'appid'         => Definition::$CESHI_APPID,
            'recipient'     => $user_id,
            'read_status'   => 0
        ];
        $result = guzzleRequest( $url , $email_url , $email_data);

        return count($result['data']);
    }
}