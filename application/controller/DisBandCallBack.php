<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:55
 */

namespace app\controller;

use think\Log;
use think\Env;
use GuzzleHttp\Client;
use app\model\BeeSender;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;



class DisBandCallBack extends Base
{
    public function disBandCallBack(){
        if(!isset($this->opt['statistics']) || !is_array($this->opt['statistics']) || !isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) ||
            !isset($this->opt['round']) || !is_numeric($this->opt['round']) || !isset($this->opt['set']) || !is_numeric($this->opt['set'])){
            return jsonRes(0);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'])){
            return jsonRes(0);
        }else{
            $redisHandle->expire(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], bcmul(bcmul(3600, 24, 0), 4, 0));
        }

        $roomHashInfo = $redisHandle->hGetAll(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId']);
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);

        // Todo 牌局记录
        if($this->opt['round'] && $playerInfo){
            foreach ($playerInfo as $k => $userInfo){
                $redisHandle->zAdd(RedisKey::$USER_ROOM_RECORD.$userInfo['userId'], time(), $this->opt['roomId']);
            }
        }

        // Todo 迭代占用的房间号
        $remRes = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$roomHashInfo['clubId'], $this->opt['roomId']); // 俱乐部移除房间   两步移除顺序不可变
        if($remRes){
            if($this->opt['round'] && $playerInfo){
                $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, time(), $this->opt['roomId']); // 迭代占用的房间号
            }else{
                $redisHandle->zRem(RedisKey::$USED_ROOM_NUM, $this->opt['roomId']); // 删除占用的房间号
            }
        }

        // Todo 报送
        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip'), config('app_debug'));
        if($playerInfo){
            foreach ($playerInfo as $k => $userInfo){
                $bigData = [
                    'server_id' => '-',
                    'user_id' => $userInfo['userId'],
                    'role_id' => '-'.'_'.$userInfo['userId'],
                    'role_name' => $userInfo['nickName'],
                    'client_id' => '-',
                    'client_type' => $userInfo['clientType'],
                    'system_type' => $userInfo['systemType'],
                    'ip' => $userInfo['ipAddr'],

                    'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                    'room_type_id' => $roomHashInfo['roomType'],
                    'room_type_name' => $roomHashInfo['roomName'],
                    'room_channel' => $roomHashInfo['roomChannel'],
                    'rule_detail' => '-',
                    'table_type' => $roomHashInfo['tableType'],
                    'table_num' => $roomHashInfo['tableNum'],
                    'table_true_num' => $this->opt['round'],
                    'close_reason' => ($this->opt['round'] >= $roomHashInfo['tableNum']) ? 'finish' : 'out',
                    'keep_time' => bcsub(strtotime($roomHashInfo['gameEndTime']), strtotime($roomHashInfo['gameStartTime']), 0),
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];

                $beeSender->send('room_close', $bigData);
            }
        }
        // 报送结束

        // Todo 助手报送
        $userIds = [];
        $userScore = [];
        foreach ($this->opt['statistics'] as $k => $v){
            $userScore[$v['playerId']] = $v['totalScore'];
            $userIds[] = $v['playerId'];
        }
        $baoSong = [];
        foreach ($roomHashInfo as $k => $v){
            if(in_array($k, ['playChecks', 'roomOptions', 'playerInfos', 'roomOptions', 'roundEndInfo', 'gameEndInfo'])){
                $baoSong[$k] = json_decode($v, true);
            }else{
                $baoSong[$k] = $v;
            }
        }
        $baoSong['opt'] = $this->opt;

        if($this->opt['round']){
            $winnerInfos = $this->opt['statistics'];
            $gameEndScore = [];
            foreach ($winnerInfos as $k => $v){
                $gameEndScore[$v['playerId']] = $v['totalScore'];
            }
            $maxScore = max($gameEndScore);
            foreach ($winnerInfos as $k => $v){
                if($v['totalScore'] == $maxScore){
                    $winnerInfos[$k]['isWinner'] = 1;
                }else{
                    $winnerInfos[$k]['isWinner'] = 0;
                }
            }
            $baoSong['winnerInfos'] = $winnerInfos;
        }

        if($userIds){
            $zhushou = [
                'type' => 'common',
                'timestamp' => time(),
                'content' => json_encode($baoSong),
                'product' => Env::get('app_id'),
                'filter_userid' => $userIds,
                'filter_clubid' => $roomHashInfo['clubId'],
                'filter_roomid' => $this->opt['roomId'],
                'filter_presidentid' => $roomHashInfo['presidentId'],
            ];
            $client = new Client();
            $client->post(Env::get('zhushou_url'), ['json' => $zhushou, 'connect_timeout' => 3, 'timeout' => 1, 'headers' => ['Accept-Encoding' => 'gzip'], 'decode_content' => 'gzip', 'http_errors' => false]);
        }
        // 助手报送结束

        // Todo 会长模式未开局还钻
        if(($roomHashInfo['clubType'] == 1) && !$this->opt['round']){
            $operateData[] = [
                'uid' => $roomHashInfo['presidentId'],
                'event_type' => '+',
                'reason_id' => 8,
                'property_type' => $roomHashInfo['clubId'].'_'.$roomHashInfo['presidentId'].'_'.Definition::$USER_PROPERTY_PRESIDENT,
                'property_name' => '赠送蓝钻',
                'change_num' => $roomHashInfo['diamond']
            ];
            $res = operatePlayerProperty($operateData);
            if(!isset($res['code']) || ($res['code'] != 0)){ # 还钻失败 记录日志
                Log::write(json_encode($operateData), 'operateError');
            }
        }
        // 会长模式还钻结束

        // Todo 会长模式开局钻石消耗报送
        if(($roomHashInfo['clubType'] == 1) && $this->opt['round']){
            $bigData = [
                'server_id' => '-',
                'user_id' => $roomHashInfo['presidentId'],
                'role_id' => '-'.'_'.$roomHashInfo['presidentId'],
                'role_name' => '-',
                'client_id' => '-',
                'client_type' => '-',
                'system_type' => '-',
                'ip' => '-',
                'club_id' => $roomHashInfo['clubId'],
                'club_name' => $roomHashInfo['clubName'],
                'club_region_id' => $roomHashInfo['clubRegionId'],
                'club_region_name' => $roomHashInfo['clubRegionName'],
                'club_mode' => $roomHashInfo['clubMode'],
                'pay_mode' => $roomHashInfo['payMode'],
                'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                'room_type_id' => $roomHashInfo['roomType'],
                'room_type_name' => $roomHashInfo['roomName'],
                'room_channel' => $roomHashInfo['roomChannel'],
                'rule_detail' => '-',
                'token_name' => 'diamond',
                'token_num' => $roomHashInfo['diamond'],
                'token_type' => '-',
                'current_token' => '-',
                'player_list' => json_encode($userIds)
            ];
            $beeSender->send('room_token_reduce', $bigData);
        }
        // 会长模式报送结束

        // Todo 玩家模式开局需要扣钻和报送
        if(($roomHashInfo['clubType'] == 0) && $playerInfo && $this->opt['round']){
            $rebate = 0; // 返利基数
            if($roomHashInfo['roomRate'] == 1){ // 大赢家模式
                $userScore = [];
                foreach ($this->opt['statistics'] as $k => $v){
                    $userScore[$v['playerId']] = $v['totalScore'];
                }
                $userIds = [];
                $maxScore = max($userScore);
                foreach ($userScore as $playerId => $score){
                    if($score == $maxScore){
                        $userIds[] = $playerId;
                    }
                }

                $userNum = count($userIds);
                foreach ($playerInfo as $k => $userInfo){
                    if(in_array($userInfo['userId'], $userIds)){
                        if(isset($userInfo['needDiamond'])){
                            foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                                if($diamondType == 'bind'){
                                    $operateData[] = [
                                        'uid' => $userInfo['userId'],
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => bcdiv($diamondValue, $userNum, 0),
                                    ];
                                }
                                if($diamondType == 'noBind'){
                                    $operateData[] = [
                                        'uid' => $userInfo['userId'],
                                        'event_type' => '-',
                                        'reason_id' => 7,
                                        'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                        'property_name' => '赠送蓝钻',
                                        'change_num' => bcdiv($diamondValue, $userNum, 0),
                                    ];
                                    $rebate = bcadd(bcdiv($diamondValue, $userNum, 0), $rebate, 0);
                                }
                            }
                        }
                    }
                }
            }

            if($roomHashInfo['roomRate'] == 0){ // 平均扣钻
                foreach ($playerInfo as $k => $userInfo){
                    if(isset($userInfo['needDiamond'])){
                        foreach ($userInfo['needDiamond'] as $diamondType => $diamondValue){
                            if($diamondType == 'bind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                            }
                            if($diamondType == 'noBind'){
                                $operateData[] = [
                                    'uid' => $userInfo['userId'],
                                    'event_type' => '-',
                                    'reason_id' => 7,
                                    'property_type' => Definition::$USER_PROPERTY_TYPE_NOT_BINDING,
                                    'property_name' => '赠送蓝钻',
                                    'change_num' => $diamondValue,
                                ];
                                $rebate = bcadd($rebate, $diamondValue, 0);
                            }
                        }
                    }
                }
            }
            if(isset($operateData)){
                // 获取用户资产
                $operateDataFor = [];
                foreach ($operateData as $kk =>$vv){
                    if(isset($operateDataFor[$vv['uid']])){
                        $operateDataFor[$vv['uid']] = bcadd($operateDataFor[$vv['uid']], $vv['change_num'], 0);
                    }else{
                        $operateDataFor[$vv['uid']] = $vv['change_num'];
                    }
                }

                $userDiamondInfos = [];
                foreach ($operateDataFor as $kk => $vv){
                    $userDiamondInfos[$kk] = getUserProperty($kk, [Definition::$USER_PROPERTY_TYPE_NOT_BINDING, Definition::$USER_PROPERTY_TYPE_BINDING, Definition::$USER_PROPERTY_TYPE_GOLD]);
                }

                $res = operatePlayerProperty($operateData);
                if(!isset($res['code']) || ($res['code'] != 0)){ // 扣钻失败 记录日志
                    Log::write(json_encode($operateData), 'operateError');
                }else{ // 报送大数据
                    $users = [];
                    foreach ($playerInfo as $k => $userInfo){
                        $users[$userInfo['userId']] = $userInfo;
                    }

                    $currentToken = [];
                    foreach ($operateDataFor as $kk =>$vv){
                        if(isset($userDiamondInfos[$kk]['code']) && ($userDiamondInfos[$kk]['code'] == 0)){
                            $noBindDiamond = 0;
                            $bindDiamond = 0;
                            $gold = 0;

                            foreach ($userDiamondInfos[$kk]['data'] as $k => $v){
                                if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_NOT_BINDING){
                                    $noBindDiamond = $v['property_num'];
                                }
                                if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_BINDING){
                                    $bindDiamond = $v['property_num'];
                                }
                                if($v['property_type'] == Definition::$USER_PROPERTY_TYPE_GOLD){
                                    $gold = $v['property_num'];
                                }
                            }
                            $user_diamond = $noBindDiamond + $bindDiamond;
                            $currentToken[$kk] = bcsub($user_diamond, $vv, 0);
                            $send_data = array();
                            $send_user[0] = $kk;
                            $send_data['content']['gold'] = (int)$gold;
                            $send_data['content']['diamond'] = (int)bcsub($user_diamond, $vv, 0);
                            $send_data['type'] = 1029;
                            $send_data['sender'] = 0;
                            $send_data['reciver'] = $send_user;
                            $send_data['appid'] = Env::get('app_id');
                            $send_url = Env::get('inform_url') . 'api/send.php';
                            $client = new Client();
                            $client->post($send_url, ['json' => $send_data, 'connect_timeout' => 5, 'headers' => ['Accept-Encoding' => 'gzip'], 'decode_content' => 'gzip', 'http_errors' => false]);
                        }
                    }

                    foreach ($operateData as $k => $v){
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $v['uid'],
                            'role_id' => '-'.'_'.$v['uid'],
                            'role_name' => $users[$v['uid']]['nickName'],
                            'client_id' => '-',
                            'client_type' => $users[$v['uid']]['clientType'],
                            'system_type' => $users[$v['uid']]['systemType'],
                            'ip' => $users[$v['uid']]['ipAddr'],

                            'club_id' => $roomHashInfo['clubId'],
                            'club_name' => $roomHashInfo['clubName'],
                            'club_region_id' => $roomHashInfo['clubRegionId'],
                            'club_region_name' => $roomHashInfo['clubRegionName'],
                            'club_mode' => $roomHashInfo['clubMode'],
                            'pay_mode' => $roomHashInfo['payMode'],
                            'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                            'room_type_id' => $roomHashInfo['roomType'],
                            'room_type_name' => $roomHashInfo['roomName'],
                            'room_channel' => $roomHashInfo['roomChannel'],
                            'rule_detail' => '-',
                            'token_name' => 'diamond',
                            'token_num' => $v['change_num'],
                            'token_type' => $v['property_type'] == Definition::$USER_PROPERTY_TYPE_NOT_BINDING ? 'pay' : 'free',
                            'current_token' => isset($currentToken[$v['uid']]) ? $currentToken[$v['uid']] : 0,
                            'player_list' => json_encode($userIds),
                        ];
                        $beeSender->send('room_token_reduce', $bigData);
                    }
                }
            }
        }
        // 玩家模式开局需要扣钻和报送结束

        // Todo 会长返利和返利报送
        if(isset($rebate) && $rebate){ // 需要返利
            if($roomHashInfo['presidentId']){
                $generalChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['generalRebate'], 0), 100, 0);
                if($generalChangeNum > 0){
                    $generalRebateData[] = [
                        'uid' => $roomHashInfo['presidentId'],
                        'event_type' => '+',
                        'reason_id' => 5,
                        'property_type' => Definition::$PRESIDENT_REBATE,
                        'property_name' => '赠送蓝钻',
                        'change_num' =>  $generalChangeNum // 普通会长返利,
                    ];
                    $res = operatePlayerProperty($generalRebateData);
                    if(!isset($res['code']) || ($res['code'] != 0)){ //  失败 记录日志
                        Log::write(json_encode($generalRebateData), 'operateError');
                    }else{
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $generalRebateData[0]['uid'],
                            'role_id' => '-'.'_'.$generalRebateData[0]['uid'],
                            'role_name' => '-',
                            'client_id' => '-',
                            'client_type' => '-',
                            'system_type' => '-',
                            'ip' => '-',

                            'club_id' => $roomHashInfo['clubId'],
                            'club_name' => $roomHashInfo['clubName'],
                            'club_region_id' => $roomHashInfo['clubRegionId'],
                            'club_region_name' => $roomHashInfo['clubRegionName'],
                            'club_mode' => $roomHashInfo['clubMode'],
                            'room_id' => strtotime($roomHashInfo['createTime']).'_'.$this->opt['roomId'],
                            'room_type_id' => $roomHashInfo['roomType'],
                            'room_type_name' => $roomHashInfo['roomName'],
                            'token_name' => 'money',
                            'token_num' => $generalRebateData[0]['change_num'],
                            'pay_mode' => $roomHashInfo['payMode'],
                        ];
                        $beeSender->send('club_rebate', $bigData);
                    }
                }
            }

            if($roomHashInfo['seniorPresidentId']){
                $seniorChangeNum = bcdiv(bcmul($rebate, $roomHashInfo['seniorRebate'], 0), 100, 0);
                if($seniorChangeNum > 0){
                    $seniorRebateData[] = [
                        'uid' => $roomHashInfo['seniorPresidentId'],
                        'event_type' => '+',
                        'reason_id' => 5,
                        'property_type' => Definition::$PRESIDENT_REBATE,
                        'property_name' => '赠送蓝钻',
                        'change_num' => $seniorChangeNum, # 高级会长返利
                    ];
                    $res = operatePlayerProperty($seniorRebateData);
                    if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                        Log::write(json_encode($seniorRebateData), 'operateError');
                    }else{
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $seniorRebateData[0]['uid'],
                            'role_id' => '-'.'_'.$seniorRebateData[0]['uid'],
                            'role_name' => '-',
                            'client_id' => '-',
                            'client_type' => '-',
                            'system_type' => '-',
                            'ip' => '-',

                            'club_id' => $roomHashInfo['clubId'],
                            'club_name' => $roomHashInfo['clubName'],
                            'club_region_id' => $roomHashInfo['clubRegionId'],
                            'club_region_name' => $roomHashInfo['clubRegionName'],
                            'club_mode' => $roomHashInfo['clubMode'],
                            'do_rebate_user_id' => $roomHashInfo['presidentId'],
                            'do_rebate_user_name' => $roomHashInfo['presidentNickName'],
                            'token_name' => 'money',
                            'token_num' => $seniorRebateData[0]['change_num'],
                        ];
                        $beeSender->send('highlevel_club_rebate', $bigData);
                    }
                }
            }

            if($roomHashInfo['commerceId']){ # 商务会长
                $businessNum = bcdiv(bcmul($rebate, $roomHashInfo['businessRebate'], 0), 100, 0);
                if($businessNum > 0){
                    $businessRebateData[] = [
                        'uid' => $roomHashInfo['commerceId'],
                        'event_type' => '+',
                        'reason_id' => 5,
                        'property_type' => Definition::$PRESIDENT_REBATE,
                        'property_name' => '赠送蓝钻',
                        'change_num' => $businessNum, # 高级会长返利
                    ];
                    $res = operatePlayerProperty($businessRebateData);
                    if(!isset($res['code']) || ($res['code'] != 0)){ # 失败 记录日志
                        Log::write(json_encode($businessRebateData), 'operateError');
                    }else{
                        $bigData = [
                            'server_id' => '-',
                            'user_id' => $businessRebateData[0]['uid'],
                            'role_id' => '-'.'_'.$businessRebateData[0]['uid'],
                            'role_name' => '-',
                            'client_id' => '-',
                            'client_type' => '-',
                            'system_type' => '-',
                            'ip' => '-',

                            'do_rebate_user_id' => $roomHashInfo['seniorPresidentId'],
                            'do_rebate_user_name' => $roomHashInfo['seniorPresidentNickName'],
                            'token_name' => 'money',
                            'token_num' => $businessRebateData[0]['change_num'],
                        ];
                        $beeSender->send('business_club_rebate', $bigData);
                    }
                }
            }
        }
        // 会长返利和返利报送结束

        // 删房间结束
        return jsonRes(0);
    }
}