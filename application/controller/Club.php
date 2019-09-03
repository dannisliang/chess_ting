<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/15
 * Time: 14:38
 */

namespace app\controller;


use app\definition\Definition;
use app\definition\RedisKey;
use app\model\AreaModel;
use app\model\BeeSender;
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
use think\Env;
use think\Log;
use think\Session;

class Club extends Base
{

    /**
     * 获取俱乐部
     * @return \think\response\Json\
     */
    public function getClubInfo(){
        //验证token
        $user_session_info = Session::get(RedisKey::$USER_SESSION_INFO);
        $user_id = $user_session_info['userid'];
        $data = [
            'ip'    => $user_session_info['ip'],
            'token' => $user_session_info['token'],
            'uid'   => $user_id,
        ];
        $result = checkToken( $data );
        if(!isset($result['result']) || $result['result'] === false || !$user_id){
            return jsonRes(9999);
        }

        $opt = ['club_id'];
        if (!has_keys($opt,$this->opt,true)){
            return jsonRes(3006);
        }

        //获取俱乐部信息
        $clubMessage = $this -> getClubMessage($user_id , $this ->opt['club_id']);
        //判断俱乐部信息是否存在
        if(is_int($clubMessage)){
            if($clubMessage === 23004) return jsonRes(23004);
            if($clubMessage === 3012)  return jsonRes(3012);
            if($clubMessage === 3998)  return jsonRes(3998);
        }
        //发送大数据
        $this->clubLoginBeeSender($clubMessage);
        $data = [
            //俱乐部信息
            'check'         => $clubMessage['check'],       //玩法数组
            'club_id'       => $clubMessage['club_id'],     //俱乐部id
            'area_id'       => $clubMessage['area_id'],     //俱乐部所在地区
            'rule_code'     => $clubMessage['rule_code'],   //规则数组
            'gameinfos'     => $clubMessage['gameinfos'],   //游戏信息
            'club_name'     => $clubMessage['club_name'],   // 俱乐部名称
            'club_icon'     => $clubMessage['club_icon'],   // 俱乐部图标
            'club_type'     => $clubMessage['club_type'],   //0:A模式 1：B模式
            'club_notice'   => $clubMessage['club_notice'], //俱乐部公告
            'roomlist_area' => Definition::$ROOMLIST_AREA,  //控制优先显示房间列表
        ];
        return jsonRes(0,$data);
    }

    /**
     * 获取会员卡信息
     * @return \think\response\Json\
     */
    public function getUserVipInfo(){
        $user_id = getUserIdFromSession();
        if(!$user_id){
            return jsonRes(9999);
        }
        $opt = ['club_id'];
        if (!has_keys($opt,$this->opt,true)){
            return jsonRes(3006);
        }
        $userVipModel = new UserVipModel();
        $where = [
            'a.club_id' => $this->opt['club_id'],
            'vip_status'=> 1,
            'uid'       => $user_id,
            'end_day'   => [
                '>',date('Y-m-d H:i:s',time())
            ]
        ];

        $userVipInfo = $userVipModel -> getOneByJoinWhere($where);
        $list = [];
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

        return jsonRes(0,$list);
    }

    /**
     * 获取加俱乐部列表（现在是查找俱乐部也在这里）
     * @return \think\response\Json\
     */
    public function getClubListOrSearch(){
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
            $club_info = $this -> getClubByCid($user_id , $this ->opt['club_id']);
            if(!$club_info){
                return json(['code'=>0,'mess'=>'查找的俱乐部不存在','data'=>[]]); //配合客户端修改返回值
            }
            $clubInfo[] = $club_info;
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
        $club = $clubModel ->getOneByWhere(['cid'=>$this->opt['club_id']]);
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
            $userClubModel -> delByWhere(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id]);
            $userLastClubModel -> delByWhere(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id]);
            $goAwayModel -> insert(['club_id'=>$this->opt['club_id'],'player_id'=>$user_id,'reason'=>0,'out_time'=>date('Y-m-d H:i:s',time())]);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return json(['code' => 23004,'mess' => '退出失败']);
        }

        //报送大数据
        $this -> outClubBeeSend();

        return json(['code' => 0,'mess' => '退出成功']);
    }

    /**
     * 登录发送大数据
     * @param $clubMessage
     */
    private function clubLoginBeeSender($clubMessage){
        //获取分成模式
        $club_mode = $this->getClubType($clubMessage['club_type']);

        $club_info = [
            'club_id' => $this -> opt['club_id'],
            'club_name' => $clubMessage['club_name'],
            'club_region_id' => $clubMessage['area_id'] ,//俱乐部地域id
            'club_region_name'=> $clubMessage['area_name'] ,//俱乐部地域名
            'club_mode' => $club_mode //俱乐部模式 free免费/divide分成
        ];
        $this -> beeSender('club_log_in' , $club_info);
    }

    /**
     * 退出俱乐部发送俱乐部
     * @param $club
     * @throws \think\Exception
     */
    private function outClubBeeSend(){
        $userClubModel = new UserClubModel();

        //俱乐部人数
        $clubNum = $userClubModel -> getCountByWhere(['club_id'=>$this->opt['club_id'],'status' => 1]);

        $content = [
            'reason'            => '主动',// 加入渠道/退出原因 主动/强制/……
            'user_num'          => $clubNum, // 本操作之后此俱乐部中的人数
            'event_type'        => 'quit',// 加入或退出join/quit
            'approve_user_id'   => '-',// 审批人id，没有时传减号
            'approve_user_name' => '-',// 审批人昵称，没有时传减号
        ];
        $club_info = getClubNameAndAreaName($this->opt['club_id']);
        $content = array_merge($content,$club_info);
        $this -> beeSender('club_join_quit',$content);
    }

    /**
     * 报送大数据
     */
    private function beeSender($event_name , $club_info){
        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip') ,Env::get('app_debug'));
        //获取报送大数据的基础事件
        $content  = getBeeBaseInfo();
        $contents = array_merge($content,$club_info);
        $res = $beeSender ->send($event_name , $contents);
        if(!$res){
            //报送不成功写日志
            Log::write($res , 'club_beeSender_error');
        }
    }

    /**
     * 获取俱乐部type
     * @param $club_type
     * @return string
     */
    private function getClubType($club_type){
        //获取分成模式
        switch ($club_type){
            case 0:
                $club_mode = 'divide'; //分成模式
                break;
            case 1:
                $club_mode = 'free';  //免费模式
                break;
            default:
                $club_mode = '';
                break;
        }
        return $club_mode;
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
        if(!$club){
            return false;
        }
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
     * 获取俱乐部信息
     * @param $user_id
     * @param $club_id
     * @return array | int
     */
    private function getClubMessage($user_id ,$club_id){

        //检查用户是否在俱乐部
        $userClubModel  = new UserClubModel();
        $clubModel      = new ClubModel();
        $roomOptionModel= new RoomOptionsModel();
        $playModel      = new PlayModel();
        $club_socket    = new ClubSocketModel();
        $areaModle      = new AreaModel();

        $user_club = $userClubModel ->getSomeByWhere([
            'player_id' => $user_id,
            'club_id'   => $club_id,
            'status'    => 1
        ],'club_id');

        if(!$user_club){
            return 23004;
        }
        $clubInfo = $clubModel -> getOneByWhere( ['cid' => $club_id] , 'club_name,club_icon,content,club_type,area_id' );

        if(!$clubInfo){
            return 3012;
        }

        //获取地区名称
        if($clubInfo['area_id']){
            $area = $areaModle -> getOneByWhere(['aid'=>$clubInfo['area_id']],'area_name');
        }

        //记录玩家最后加入的俱乐部信息
        $this -> getUserLastClub($user_id,$club_id);

        $room_options = $roomOptionModel ->getSomeByWhere(['club_id' => $club_id] , 'room_type,room_rate,room_name,diamond,options,id');
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
                'area_id'   => $clubInfo['area_id'],
                'area_name' => isset($area['area_name']) ? $area['area_name'] : '',

            ];

            return $list;
        }
        //存在房间玩法(判断房间玩法存在)
        $play = $playModel -> getOneByWhere(['id' => $room_options[0]['room_type']] , 'play');
        if(!$play){
            return 3998;
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

        //获取checks , code
        $rule_code = [];$check_array = [];
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
            if(false == Env::get('is_online') && in_array($club_id, [555555, 999999, 888888, 777777])){
                $socket = $club_socket->getClubSocketInfo($club_id);
                $back_list['socket_url'] = $socket['socket_url'];
                $socket_h5 = $socket['socket_h5'];
            }else{
                $back_sercie = $this->getService($val['play_type']);//根据玩法的ID去找出合适的服务器(没有的话不返)
                if(!$back_sercie){
                    continue;
                }
                $back_list['socket_url'] = $back_sercie['socket_app'];
                $socket_h5 = $back_sercie['socket_h5'];
            }
            $game_info['room_code'] = json_decode($val['play'],true)['checks']['code'];
            $game_info['game_socket_h5'] = $socket_h5;
            $game_info['options']   = json_decode($val['options'], true);
            $game_info['match_id']  = $val['id'];
            $game_info['pay_type']  = $val['room_rate'];
            $game_info['room_name'] = $val['room_name'];

            $room_cost = $this->getRoomCost($clubInfo , $val);
            $game_info['room_cost'] = intval($room_cost);

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
            'area_id'   => $clubInfo['area_id'],  //俱乐部地区id
            'area_name' => isset($area['area_name']) ? $area['area_name'] : '',//俱乐部地区名称
        ];

        return $list;
    }

    /**
     * 计算房费
     * @param $clubInfo
     * @param $val
     */
    private function getRoomCost($clubInfo , $val){
        //免费房
        if($clubInfo['club_type'] == 1){
            $room_cost = 0;
        }else{
            //计算房费
            $diamond = 0;
            if($val['room_rate'] == 0){
                $diamond = $val['diamond'];
            }
            //人数
            $playSize = getRoomNeedUserNum(json_decode($val['play'],true),json_decode($val['options'],true));

            if($playSize == 0 || !$playSize){
                $diamond = $val['diamond'];
            }else{
                $diamond = $diamond/$playSize;
            }
            $room_cost = $diamond;
            //大赢家模式
            if($val['room_rate'] == 1){
                $room_cost = $val['diamond'];
            }
        }
        return $room_cost;
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
            'is_goto' => 1,
            'room_type'=> $room_type
        ];
        $field = 'service_id';
        //根据room_type(纸牌或麻将)选择对应开着的服务器
        $game_services_opts = $gameServiceModel -> getSomeByWhere($where , $field);
        if(!$game_services_opts){
            return false;
        }
        //随机取一个服务器地址(todo 后期是否可选)
        $service_ids = [];
        foreach ($game_services_opts as $game_services_opt){
            $service_ids[] = $game_services_opt['service_id'];
        }
        $service_id_key = array_rand($service_ids,1);
        $service_id = $service_ids[$service_id_key];
        //根据服务器的ID查出服务器,h5和app的socket的地址
        $where = [
            'id'=> $service_id
        ];
        $field = 'service,gateway_h5,gateway_app';
        //根据服务器的ID查出服务器的地址
        $game_service = $serviceGateWayNewModel -> getOneByWhere($where , $field);
        if (!$game_service){
            $room_url = Env::get('room_url');
            $socket_h5 = Env::get('socket_h5');
            $socket_app = Env::get('socket_url');
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
     * 获取用户资产
     * @param $user_id
     * @return int
     */
    private function getUserProperty($user_id){
        $url = Env::get('web_api_url');
        $pathInfo = Definition::$GET_PLAYER_PROPERTY;
        $data = [
            'uid' => $user_id,
            'app_id' => Env::get('app_id'),
            'property_type' => 10001
        ];
        $result = guzzleRequest($url , $pathInfo , $data);
        if($result['code'] != 0){
            return $gold=0;
        }
        $gold = $result['data'][0]['property_num'];
        return $gold;
    }

}