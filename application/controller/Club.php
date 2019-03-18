<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/15
 * Time: 14:38
 */

namespace app\controller;


use app\definition\Definition;
use app\model\ClubModel;
use app\model\ClubSocketModel;
use app\model\GameServiceNewModel;
use app\model\PlayModel;
use app\model\RoomOptionsModel;
use app\model\ServiceGatewayNewModel;
use app\model\UserClubModel;
use app\model\UserRoomModel;
use app\model\UserVipModel;
use think\Session;

class Club extends Base
{
    /**
     * 获取俱乐部
     * @return \think\response\Json\
     */
    public function getClubInfo(){
        $user_id = getUserIdFromSession();
        $opt = ['club_id'];
        if (!has_keys($opt,$this->opt,true)){
            return jsonRes(3006);
        }
        //获取用户的vip信息
        $this -> getUserVipInfo($user_id,$this ->opt['club_id']);

        //获取俱乐部信息
        $res = $this -> getClubMessage($user_id , $this ->opt['club_id']);

        return jsonRes(0,$res);
    }

    /**
     * 获取俱乐部信息
     * @param $user_id
     * @param $club_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getClubMessage($user_id ,$club_id){

        //检查用户是否在俱乐部
        $userClubModel  = new UserClubModel();
        $clubModel      = new ClubModel();
        $roomOptionModel= new RoomOptionsModel();
        $playModel      = new PlayModel();
        $club_socket    = new ClubSocketModel();
        $where = [
            'player_id' => $user_id,
            'club_id'   => $club_id,
            'status'    => 1
        ];
        $field = 'club_id';

        $user_club = $userClubModel ->getSomeByWhere($where,$field);
        if(!$user_club){
            return jsonRes(23004);
        }
        $where = [
            'cid' => $club_id
        ];
        $field = 'club_name,club_icon,content,club_type';
        $clubInfo = $clubModel -> getOneByWhere( $where , $field );
        if(!$clubInfo){
            return jsonRes(3012);
        }
        $where = [
            'club_id' => $club_id,
        ];
        $field = 'room_type,room_rate,room_name,diamond,options,id';
        $room_options = $roomOptionModel ->getSomeByWhere($where , $field);
        //不存在房间玩法
        if(!$room_options){
            $list = [
                'check' => [],
                'club_id'=> $club_id,
                'club_name'=> base64_decode($clubInfo['club_name']),
                'club_icon'=> $clubInfo['club_icon'],
                'club_notice'=> $clubInfo['content'],
                'club_type' => $clubInfo['club_type'], //0:A模式 1：B模式
                'gameinfos' => [],
            ];

            return $list;
        }
        //存在房间玩法
        $where = [
            'id' => $room_options[0]['room_type'],
        ];
        $field = 'play';
        $play = $playModel -> getOneByWhere($where , $field);
        if(!$play){
            return jsonRes(3998);
        }
        $room_type = [];
        foreach ($room_options as $room_option){
            $room_type[] = $room_option['room_type'];
        }
        $where = [
            'id' => [
                'in',$room_type
            ]
        ];
        $field = 'play';
        $plays = $playModel -> getSomeById($where,$field);
        $rule_code = [];$check_array = [];
        foreach ($plays as $play){
            $rule_code[] = json_decode($play['play'],true)['checks']['code'];
            $check_array[] = json_decode($play['play'],true)['checks'];
        }
        //获取play and option
        $playOptions = $roomOptionModel ->getPlayAndOptions(['club_id' => $club_id]);
        if(!$playOptions){
            $list['gameinfos'] = [];
        }
        $game_info = [];$game_infos = [];
        foreach ($playOptions as $val){
            //查找合适的服务器
            if($club_id == 999999){
                $socket = $club_socket->getClubSocketInfoByClubId($club_id);
                $back_list['socket_url'] = $socket['socket_url'];
                $socket_h5 = $socket['socket_h5'];
            }else{
                $back_sercie = $this->getService($val['room_type']);//根据玩法的ID去找出合适的服务器
                $back_list['socket_url'] = $back_sercie['socket_app'];
                $socket_h5 = $back_sercie['socket_h5'];
            }
            $game_info['room_code'] = json_decode($val['play'],true)['checks']['code'];
            $game_info['game_socket_h5'] = $socket_h5;
            $game_info['options']   = $val['options'];
            $game_info['match_id']  = $val['id'];
            $game_info['pay_type']  = $val['room_rate'];
            $game_info['room_name'] = $val['room_name'];
            //计算房费
            if($val['room_rate'] == 0){
                $diamond = $val['diamond'];
            }
            //人数
            $playSize = getRoomNeedUserNum(json_decode($val['play'],true),json_decode($val['options'],true));
            //如果反回false则说明配置规则有问题
            if (!$playSize){
                $diamond = $val['diamond'];
            }
            $diamond = $diamond/$playSize;
            $game_info['room_cost'] = $diamond;
            $game_infos[] = $game_info;
        }
        $list = [
            'gameinfos' => $game_infos,
            'rule_code' => $rule_code,
            'check' => $check_array,
            'club_id'   => $club_id,
            'club_icon'=> $clubInfo['club_icon'],
            'club_notice'=> $clubInfo['content'],
            'club_type' => $clubInfo['club_type'], //0:A模式 1：B模式
        ];
        return $list;
        return $plays;
    }

    /**
     * 根据玩法的ID去找出合适的服务器
     * @param $room_type
     * @return array
     */
    private function getService($room_type){
        $gameServiceModel = new GameServiceNewModel();
        $serviceGateWayNewModel = new ServiceGatewayNewModel();
        $userRoomModel = new UserRoomModel();
        $where = [
            'is_open' => 1,
            'room_type'=> $room_type
        ];
        $field = 'service_id';
        //根据room_type(纸牌或麻将)选择对应开着的服务器
        $game_services_opts = $gameServiceModel -> getSomeByWhere($where , $field);
        //声明一个空数组,以服务器的ID为键,数量为值存进去
        $services = [];
        foreach ($game_services_opts as $game_services_opt){
            $num = $userRoomModel -> getServiceCount(['service'=>$game_services_opt['service_id']]);
            $services[$game_services_opt['service_id']] = $num;
        }
        $service_id = array_search(min($services), $services);//数量最小的服务器
        //根据服务器的ID查出服务器,h5和app的socket的地址
        $where = [
            'id'=> $service_id
        ];
        $field = 'service,gateway_h5,gateway_app';
        //根据服务器的ID查出服务器的地址
        $game_service = $serviceGateWayNewModel -> getOneByWhere($where , $field);
        if (!$game_service){
            $room_url = Definition::$ROOM_URL;
            $socket_h5 = Definition::$SOCKET_H5;
            $socket_app = Definition::$SOCKET_URL;
        }
        $room_url   = $game_service['service'];
        $socket_h5  = $game_service['gateway_h5'];
        $socket_app = $game_service['gateway_app'];
        //声明个数组,把socket_h5,socket_url和room_url返回去
        return $back_array = [
            'socket_h5' => $socket_h5,
            'socket_app'=> $socket_app,
            'room_url'  => $room_url,
            'service_id'=> $service_id
        ];
    }


    /**
     * 获取用户的会员卡信息
     * @param $user_id
     * @param $club_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getUserVipInfo($user_id,$club_id){
        $userVipModel = new UserVipModel();
        $where = [
            'a.club_id' => $club_id,
            'vip_status'=> 1,
            'uid'       => $user_id,
            'end_day'   => [
                '>',date('Y-m-d H:i:s',time())
            ]
        ];
        $userVipInfo = $userVipModel -> getOneByWhere($where);
        if($userVipInfo){
            //拼接的数据
            $user_vipinfos = [
                'vip_level' => $userVipInfo['type'],
                'vid'       => $userVipInfo['vid'],
                'vip_name'  => $userVipInfo['v_name']
            ];
            //获取用户的vip信息
            $list = [
                'end_day'     => strtotime($userVipInfo['end_day']),
                'cards_num'   => $userVipInfo['card_number'],
                'surplus_day' => strtotime($userVipInfo['end_day']) - time(),//会员卡剩余时间
                'user_vipinfos'=>$user_vipinfos,
            ];
        }
        return $list;
    }
}