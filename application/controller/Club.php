<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/15
 * Time: 14:38
 */

namespace app\controller;


use app\definition\Definition;
use app\model\ClubModel;
use app\model\ClubSocketModel;
use app\model\GameServiceNewModel;
use app\model\GoAwayModel;
use app\model\PlayModel;
use app\model\RoomOptionsModel;
use app\model\ServiceGatewayNewModel;
use app\model\UserClubModel;
use app\model\UserLastClubModel;
use app\model\UserRoomModel;
use app\model\UserVipModel;
use think\Db;
use think\Request;

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
        $userVipInfo = $this -> getUserVipInfo($user_id,$this ->opt['club_id']);

        //获取俱乐部信息
        $clubMessage = $this -> getClubMessage($user_id , $this ->opt['club_id']);

        //获取用户资产 （废弃）
//        $gold = $this -> getUserProperty($user_id);

        //TODO 报送大数据
        $this -> beeSender();

        $data = [
            //vip信息
            'end_day'       => $userVipInfo['end_day'], //玩家vip到期时间
            'cards_num'     => $userVipInfo['cards_num'],
            'surplus_day'   => $userVipInfo['surplus_day'],
            'user_vipinfos' => $userVipInfo['user_vipinfos'],

            //俱乐部信息
            'check'         => $clubMessage['check'],       //玩法数组
            'club_id'       => $clubMessage['club_id'],     //俱乐部id
            'rule_code'     => $clubMessage['rule_code'],   //规则数组
            'gameinfos'     => $clubMessage['gameinfos'],   //游戏信息
            'club_name'     => $clubMessage['club_name'],   // 俱乐部名称
            'club_icon'     => $clubMessage['club_icon'],   // 俱乐部图标
            'club_type'     => $clubMessage['club_type'],   //0:A模式 1：B模式
            'club_notice'   => $clubMessage['club_notice'],

            //用户资产（废弃）
//            'gold' => $gold,
        ];
        return jsonRes(0,$data);
    }

    /**
     * 获取加俱乐部列表（现在是查找俱乐部也在这里）
     * @return \think\response\Json\
     */
    public function getClubInfos(){
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }
        $opt = ['type'];
        if (!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $clubModel = new ClubModel();
        $userClubModel = new UserClubModel();

        if(!isset($this->opt['club_id'])){
            $clubInfo = $clubModel ->getUserJoinClub($user_id);
        }else{
            //根据club_id搜索club
            $clubInfo[] = $this -> getClubByCid($user_id , $this ->opt['club_id']);
        }
        if(!$clubInfo){
            return jsonRes(23005);
        }
        $lists = [];
        foreach ($clubInfo as $value){
            //返回给客户端的状态
            switch ($value['status']){
                case 0:
                    if(isset($value['tempo']) && $value['tempo'] == 0){
                        $join_state = 0;
                    }else{
                        $join_state = 1;
                    }
                    break;
                case 1:
                    $join_state = 2;
                    break;
                case 2:
                    $join_state = 0;
                    break;
                default:
                    $join_state = 0;
                    break;
            }
            //todo 需改进
            $num = $userClubModel ->getCountByWhere(['club_id'=>$value['cid']]);
            $list = [
                'notice'            => $value['content'],
                'club_id'           => $value['cid'],
                'club_name'         => base64_decode($value['club_name']),
                'room_card'         => $value['room_card'],
                'join_state'        => $join_state,
                'limitation'        => $value['limitation'],
                'president_name'    => $value['president_name'],
                'president_id'      => $value['creat_player'],
                'club_icon_image'   => $value['club_icon'],
                'club_members_count'=> $num,
                'max_club_members_count' => $value['max_club_members_count'],
            ];
            $lists[] = $list;
        }
        return jsonRes(0,$lists);

    }

    /**
     * 加入俱乐部
     * @return \think\response\Json\|void
     */
    public function joinClub(){
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }
        $opt = ['club_id'];
        if (!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $clubModel = new ClubModel();
        $userClubModel = new UserClubModel();
        $clubInfo = $clubModel -> getOneByWhere(['cid' => $this -> opt['club_id']],'limitation,max_club_members_count');
        //俱乐部不存在
        if(!$clubInfo){
            return jsonRes(3009);
        }
        //俱乐部已加满
        $clubNum = $userClubModel -> getCountByWhere(['club_id'=>$this->opt['club_id'],'status' => 1]);
        if($clubNum >= $clubInfo['max_club_members_count']){
            return jsonRes(3008);
        }
        //已经加入俱乐部
        $userClub = $userClubModel -> getOneByWhere(['player_id'=>$user_id,'club_id'=>$this->opt['club_id']]);
        if($userClub){
            return jsonRes(3015);
        }

        switch ($clubInfo['limitation']){
            case 0:
                //不用审核直接加入
                $data = [
                    'status'    => 1,
                    'player_id' => $user_id,
                    'club_id'   => $this->opt['club_id']
                ];
                $backmess = 2;
                break;
            case 1:
                //需要审核才可加入
                $data = [
                    'status'    => 0,
                    'player_id' => $user_id,
                    'club_id'   => $this->opt['club_id']
                ];
                $backmess = 1;
                break;
            default:
                break;
        }
        $result = $userClubModel -> insert($data);
        if(!$result){
            return jsonRes(23004);
        }
        if($backmess == 1){
            return json(['code'=>0,'mess'=>'申请成功','data'=>['status'=>$backmess]]);
        }
        return json(['code'=>0,'mess'=>'加入成功','data'=>['status'=>$backmess]]);
    }

    /**
     * 退出俱乐部
     * @return \think\response\Json\
     */
    public function outClub(){
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }
        $opt = ['club_id'];
        if(!has_keys($opt , $this ->opt)){
            return jsonRes(3006);
        }
        $clubModel = new ClubModel();
        $goAwayModel = new GoAwayModel();
        $userClubModel = new UserClubModel();
        $userLastClubModel = new UserLastClubModel();
        $club = $clubModel ->getOneByWhere(['cid'=>$this->opt['club_id']],'cid,president_id');
        //俱乐部存在
        if(!$club){
            return jsonRes(3500);
        }
        //不能是会长
        if($club['president_id'] == $user_id){
            return jsonRes(23402);
        }
        $userClub = $userClubModel ->getOneByWhere(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id]);
        //是否加入俱乐部
        if(!$userClub){
            return jsonRes(3300);
        }
        //事务处理删除数据
        Db::startTrans();
        try{
            $userClubModel-> delByWhere(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id]);
            $userLastClubModel -> delByWhere(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id]);
            $goAwayModel -> insert(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id,'reason'=>'主动退出','out_time'=>date('Y-m-d H:i:s',time())]);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json(['code' => 23004,'mess' => '退出失败']);
        }
        //todo 报送大数据
        return json(['code' => 0,'mess' => '退出成功']);


    }

    /**
     * 搜索俱乐部的信息
     */
    private function getClubByCid($user_id , $club_id){
        $userClubModel = new UserClubModel();
        $clubModel = new ClubModel();
        $where = [
            'player_id' => $user_id,
            'club_id'   => $club_id
        ];
        $userClub = $userClubModel -> getOneByWhere($where);
        if(!$userClub){
            $status = 0;
            $tempo = 0; //临时变量
        }else{
            $status = $userClub['status'];
            $tempo = 1;
        }
        $club = $clubModel -> getOneByWhere(['cid' => $club_id]);
        $clubInfo = [
            'cid'       =>  $club['cid'],
            'status'    =>  $status,
            'content'   =>  $club['content'],
            'player_id' =>  $user_id,
            'club_icon' =>  $club['club_icon'],
            'club_name' =>  $club['club_name'],
            'room_card' =>  $club['room_card'],
            'limitation'=>  $club['limitation'],
            'creat_player'=>$club['creat_player'],
            'president_name'=>  $club['president_name'],
            'max_club_members_count'=>  $club['max_club_members_count'],
            'tempo'     => $tempo,
        ];
        return $clubInfo;
    }

    /**
     * todo 暂时先不写
     */
    private function beeSender(){
        return;
    }

    /**
     * 获取用户资产
     * @param $user_id
     * @return int
     */
    private function getUserProperty($user_id){
        $url = Definition::$WEB_API_URL;
        $pathInfo = Definition::$GET_PLAYER_PROPERTY;
        $data = [
            'uid' => $user_id,
            'app_id' => Definition::$CESHI_APPID,
            'property_type' => 10001
        ];
        $result = guzzleRequest($url , $pathInfo , $data);
        if($result['code'] != 0){
            return $gold=0;
        }
        $gold = $result['data'][0]['property_num'];
        return $gold;
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
        //记录玩家最后加入的俱乐部信息
        $this -> getUserLastClub($user_id,$club_id);

        $where = [
            'club_id' => $club_id,
        ];
        $field = 'room_type,room_rate,room_name,diamond,options,id';
        $room_options = $roomOptionModel ->getSomeByWhere($where , $field);
        //不存在房间玩法
        if(!$room_options){
            $list = [
                'check'     => [],
                'rule_code' => [], //原先没有
                'club_id'   => $club_id,
                'club_name' => base64_decode($clubInfo['club_name']),
                'club_icon' => $clubInfo['club_icon'],
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

        //获取checks , code
        foreach ($plays as $play){
            $rule_code[] = json_decode($play['play'],true)['checks']['code'];
            $check_array[] = json_decode($play['play'],true)['checks'];
        }
        //获取play 和 option
        $playOptions = $roomOptionModel ->getPlayAndOptions(['club_id' => $club_id]);
        if(!$playOptions){
            $list['gameinfos'] = [];
        }
        $game_info = [];$game_infos = [];
        //获取游戏的游戏信息
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
            'check'     => $check_array,
            'club_id'   => $club_id,
            'club_icon' => $clubInfo['club_icon'],
            'club_notice'=> $clubInfo['content'],
            'club_name' => base64_decode($clubInfo['club_name']),
            'club_type' => $clubInfo['club_type'], //0:A模式 1：B模式
        ];

        return $list;
    }

    /**
     * 存在修改，不存在插入
     * @param $user_id
     * @param $club_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getUserLastClub($user_id,$club_id){
        $userLastClubModel = new UserLastClubModel();
        $lastClub = $userLastClubModel -> getOneByWhere(['player_id' => $user_id]);
        if(!$lastClub){
            $userLastClubModel ->insertGetId(['player_id'=>$user_id,'club_id'=>$club_id]);
            return;
        }
        $userLastClubModel ->updateByWhere(['player_id' => $user_id],['club_id'=>$club_id]);
        return;
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
        $room_url   = $game_service['service'];  //逻辑服地址
        $socket_h5  = $game_service['gateway_h5']; //给H5的逻辑服地址
        $socket_app = $game_service['gateway_app'];//给app的逻辑服地址
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