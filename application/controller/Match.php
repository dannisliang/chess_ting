<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/26
 * Time: 9:15
 */

namespace app\controller;


use think\Session;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;
use app\model\MatchPlayModel;
use app\model\PlayGroundModel;
use app\model\UserSignUpModel;



class Room extends Base
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

}