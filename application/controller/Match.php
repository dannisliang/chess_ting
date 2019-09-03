<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/26
 * Time: 9:15
 */

namespace app\controller;


use app\model\UserMatchNumModel;
use app\model\UserRankModel;
use think\Session;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;
use app\model\MatchPlayModel;
use app\model\PlayGroundModel;
use app\model\UserSignUpModel;



class Match extends Base
{

    /**
     * 海选
     */
    # 匹配成功回调
    public function matchRoomCallBack(){
        if(!isset($this->opt['uid']) || !$this->opt['uid'] || !isset($this->opt['group']) || !is_array($this->opt['group'])){
            return jsonRes(0);
        }

        $playGround = new PlayGroundModel();
        $playGroundInfo = $playGround->getPlayGroundInfo();

        $matchPlay = new MatchPlayModel();
        $matchPlayInfo = $matchPlay->getMatchPlayInfo();

        $options = [2,5,9,14,17,19];//八局的规则

        if($playGroundInfo['bureau'] == 4){
            $options = [22,5,9,14,17,19];//16局的规则
        }

        if($playGroundInfo['bureau'] == 16){
            $options = [3,5,9,14,17,19];//默认的规则(八局)
        }

        $playInfo = json_decode($matchPlayInfo['play'], true);
        $playInfo['options'] = $options;

        # 生成房间号
        $redis = new Redis();
        $redisHandle = $redis->handler();
        $roomNumber = $redisHandle->rpoplpush(RedisKey::$ROOM_NUMBER_KEY_LIST, RedisKey::$ROOM_NUMBER_KEY_LIST);
        if(!$roomNumber){
            return jsonRes(3517);
        }

        $data['roomId'] = $roomNumber;
        $data['config'] = $playInfo;

        $a = mt_rand(0,1);
        if($a == 0){
            $matchUrl = "http://10.0.0.173:5011/";
            $socketH5 = "wss://gateway_hn_mahjong.volvale.com:9021";
            $socketApp = "gateway_hn_mahjong.volvale.com:9020";
            $serviceId = 6;
        }

        if ($a == 1){
            $matchUrl = "http://10.0.0.173:5011/";
            $socketH5 = "wss://gateway_hn_mahjong.volvale.com:9023";
            $socketApp = "gateway_hn_mahjong.volvale.com:9022";
            $serviceId = 7;
        }

        sendHttpRequest($matchUrl.Definition::$CREATE_ROOM.$this->opt['uid'], $data);
        foreach ($this->opt['group'] as $playerId){
            if($this->opt['uid'] != $playerId){
                sendHttpRequest($matchUrl.Definition::$JOIN_ROOM.$playerId, ['roomId' => $roomNumber]);
            }
        }

        $hashInfo = [
            'roomUrl' => $matchUrl,
            'socketH5' => $socketH5,
            'socketUrl' => $socketApp,
            'serviceId' => $serviceId,
            'roomId' => $roomNumber,
            'check' => json_encode($playInfo['checks']),
            'roomType' => 1,
            'roomOptions' => $options
        ];

        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, $hashInfo);

        $returnInfo = [
            'socket_h5' => $socketH5,
            'socket_app' => $socketApp,
            'room_id' => $roomNumber,
            'check' => json_encode($playInfo['checks']),
            'room_type' => 1,
            'options' => $options
        ];
        return json($returnInfo);
    }
    # 退出房间
    public function outRoomCallBack()
    {
        return jsonRes(0);
    }
    # 玩家加入房间回调
    public function joinRoomCallBack()
    {
        return json(['code' => 0]);
    }
    # 房间解散回调
    public function roomDisbandCallBack()
    {
        if(!isset($this->opt['roomId']) || !isset($this->opt['round']) || !isset($this->opt['statistics'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $redisHandle->del(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId']);
        return jsonRes(0);
    }
    # 牌局开始回调
    public function roundStartGame(){
        return jsonRes(0);
    }
    # 牌局结束回调
    public function roundEndGame(){
        return jsonRes(0);
    }
    # 房间开始回调
    public function roomStartGameCallBack(){
        return jsonRes(0);
    }
    # 房间结束回调
    public function roomEndGameCallBack(){
        return jsonRes(0);
    }

    # 客户端报名比赛
    public function signUp(){
        //以上是点击报名(无论成功与否都要报送一次)
        if(isset($this->opt['user_name']) && isset($this->opt['phone_num']) && isset($this->opt['user_city']) && isset($this->opt['user_county']) && isset($this->opt['user_town'])){
            # 是否有可以报名的赛期
            $playGround = new PlayGroundModel();
            $playGroundInfo = $playGround->getPlayGroundInfo();
            if(!$playGroundInfo){
                return jsonRes(3522);
            }

            $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
            $userName = base64_encode($this->opt['user_name']);

            $insertData = [
                'user_name' => $userName,
                'phone_wechat' => $this->opt['phone_num'],
                'city' => $this->opt['user_city'],
                'county' => $this->opt['user_county'],
                'town' => $this->opt['user_town'],
                'player_id' => $userSessionInfo['userid'],
                'time' => date("Y-m-d H:i:s", time()),
                'match_id' => $playGroundInfo['id']
            ];

            $userSignUp = new UserSignUpModel();
            $userSignUp->addOne($insertData);
            return jsonRes(0);
        }
        return jsonRes(3006);
    }
    # 客户端请求比赛规则
    public function getRule(){
        $playGround = new PlayGroundModel();

        $a = $playGround->getAPlayGroundInfo();
        $b = $playGround->getBPlayGroundInfo();

        if($a){
            $rule_content = $a['rule_copy'];
        }elseif ($b){
            $rule_content = $a['rule_copy'];
        }else{
            $rule_content = '';
        }
        $result['code'] = 0;
        $result['rule_content'] = $rule_content;
        return jsonRes(0, $result);
    }
    # 客户端请求排行榜
    public function getSort(){
        if(isset($this->opt['match_date'])){
            $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
            if($userSessionInfo){
                $player_id = $userSessionInfo['userid'];
                $check_match = $this->opt['match_date'];//0是今天,1是昨天
                if($check_match == 0){
                    //今天的排行榜
                    $date = date('Y-m-d');
                }else{
                    //昨天的排行榜
                    $now_time = time();
                    $one_days = $now_time-86400;
                    $date = date('Y-m-d',$one_days);
                }

                $playGround = new PlayGroundModel();
                $playGroundInfo = $playGround->getPlayGroundInfo();
                $match_date_start = $playGroundInfo['match_date_start'];

                //排行榜数据
                $userRank = new UserRankModel();
                $rankInfo = $userRank->getSort($date);

                //判断这50个玩家里是否存在这个玩家
                $userRankInfo = $userRank->getMyRank($player_id, $date);
                if($userRankInfo){
                    $is_inrank = 1;//在里面
                }else{
                    $is_inrank = 0;//不在里面
                }

                foreach ($rankInfo as $k => $v){
                    if($is_inrank == 1){
                        if($v['player_id'] == $player_id){
                            $back_result['user_ranking'] = $k+1;//用户名次
                            $back_result['user_curent_score'] = $v['total_store'];
                            break;
                        }else{
                            //没有的话去获取后台设计的局数
                            $back_result['user_ranking'] = NULL;//用户名次
                            $back_result['user_curent_score'] = NULL;
                        }
                    }else{
                        $back_result['user_ranking'] = NULL;//用户名次
                        $back_result['user_curent_score'] = NULL;
                    }
                }

                $newRankInfo = [];
                foreach ($rankInfo as $k => $v){
                    $back_result['player_infos'][$k]['user_image'] = $v['user_image'];//玩家头像地址
                    $back_result['player_infos'][$k]['user_name'] = base64_decode($v['nick_name']);//玩家名字
                    $back_result['player_infos'][$k]['user_store'] = $v['total_store'];//玩家总得分
                    if($v['county']){
                        $county = $v['county'];
                    }else{
                        $county ='';
                    }
                    if($v['city']){
                        $city = $v['city'];
                    }else{
                        $city = '';
                    }
                    $back_result['player_infos'][$k]['user_city'] = $county.' '.$city;//玩家所在城市
                }
            }else{
                //如果不存在返回空
                $back_result['user_ranking'] = NULL;  //用户名次
                $back_result['user_match_num'] = NULL;//剩余比赛场次
                $back_result['user_curent_score'] = NULL;//用户当前得分
                $back_result['player_infos'] = array();//玩家信息
                return json(['code'=>0, 'data' => $back_result]);
            }

            $match_num = $this->player_match_num($userSessionInfo['userid'], $date);
            $back_result['user_match_num'] = $match_num;//剩余比赛场次
            $back_result['match_date'] = $match_date_start;//赛期
            return json(['code'=>0,'data'=>$back_result]);
        }
    }
    # 获取玩家的比赛剩余场次
    private function player_match_num($player_id, $match_date =''){
        $playGround = new PlayGroundModel();
        $playGroundInfo = $playGround->getPlayGroundInfo();

        if(!$playGround){
            $num = 10;
            return $num;
        }

        if(!$playGroundInfo['opportunity']){
            $num = 10;
            return $num;
        }

        $cur_match_id = $playGroundInfo['id'];//当前开着的赛期ID
        $match_num = $playGroundInfo['opportunity'];//当前赛期玩家每天可以进行比赛的次数
        if($match_date){
            $userMatchNum = new UserMatchNumModel();
            $userMatchNumInfo = $userMatchNum->getUserMatchNum($player_id, $match_date, $cur_match_id);
            if(!$userMatchNumInfo){
                $num = $match_num;
            }else{
                $num = $userMatchNumInfo['match_num'];
                $num = $match_num-$num;
            }
            return $num;
        }



        $date = date('Y-m-d');
        $userMatchNum = new UserMatchNumModel();
        $userMatchNumInfo = $userMatchNum->getUserMatchNumA($player_id, $date);
        if(!$userMatchNumInfo){
            $num = $match_num;
            return $num;
        }
        $his_match_id = $userMatchNumInfo['match_id'];
        if($his_match_id != $cur_match_id){
            $num = $match_num;
            return $num;
        }
        $user_match_num = $userMatchNumInfo['match_num'];
        $num = $match_num-$user_match_num;
        return $num;
    }
    # 玩家比赛详情
    public function user_match_infos(){
        $player_id = Session::get('player');
        $user_sign = Db::name('user_sign_up')
            ->where('player_id',$player_id)
            ->count(0);
        if($user_sign){
            $result['is_sign_up'] = 1;//玩家是否报名
            $match_num = $this->player_match_num($player_id);
            $result['match_num'] = (int)$match_num;//剩余比赛的次数
            Log::write($match_num,'$match_num_log');
        }else{
            $result['is_sign_up'] = 0;//玩家是否报名
            $result['match_num'] = NULL;//玩家剩余比赛的次数
        }
        //如果存在说明已经报名,则需要把比赛时间返回
        $now_day = date('Y-m-d H:i:s');
        $now_date = date('Y-m-d');//比赛开始天
        Log::write($now_date,'$now_date_log');
        $now_time = date('H:i:s');//比赛开始时间
        $a = Db::query("SELECT * FROM tb_playground WHERE status = 1");//符合比赛的数据
        Log::write($a,'$a_log');
        if($a){
            //未开始比赛
            $sign_str_time = $a[0]['registration_time_start'];//报名开始时间
            $sign_end_time = $a[0]['registration_time_end'];//报名结束时间
            $match_str_day = $a[0]['match_date_start'];
            $match_str_date = $a[0]['match_time_start'];
            $str_day = date('Y-m-d');
            $match_end_day = $a[0]['match_date_end'];
            $match_end_date = $a[0]['match_time_end'];
            $end_day = date('Y-m-d');
            $match_date_end = "$end_day"."$match_end_date";//比赛结束时间
            if(strtotime($match_str_day)>=strtotime($now_date)){
                $match_date_str = "$match_str_day"."$match_str_date";//比赛开始时间

            }else{
                $match_date_str = "$str_day"."$match_str_date";//比赛开始时间
                $match_str_day = $str_day;
            }
            $match_date = $this->getmatch_time($match_str_day,$match_time1='');//获取天
            $result['match_month'] = $match_date['month'];
            $result['match_day'] = $match_date['day'];
            $match_time = $this->getmatch_time($match_day = '',$match_str_date);//获取小时,分钟
            $result['match_minute'] = $match_time['minute'];
            $result['match_hour'] = $match_time['hour'];
        }else{
            $result['sign_str_time'] = NULL;
            $result['sign_end_time'] = NULL;
            $result['match_date_end'] = NULL;
            $result['match_date_str'] = NULL;
            return json(['code'=>0,'data'=>$result]);
        }
        $result['sign_str_time'] = strtotime($sign_str_time);
        $result['sign_end_time'] = strtotime($sign_end_time);
        $result['match_date_str'] = strtotime($match_date_str);
        $result['match_date_end'] = strtotime($match_date_end);

        $sendbig = new SendBigData();
        $event_name = 'match_game_step';
        $event_type = 'enter_hall';
        $sendbig->sendMatch($event_type,$content='',$event_name,$player_id);

        return json(['code'=>0,'data'=>$result]);
    }

}