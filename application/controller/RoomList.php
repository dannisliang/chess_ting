<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:33
 */

namespace app\controller;

use think\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use app\definition\Definition;



class RoomList extends Base
{
    public function getRoomList(){
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $sMembers = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
        if(!$sMembers){
            return jsonRes(0, ['roominfo' => []]);
        }

        $client = new Client();
        $promises = [];
        foreach ($sMembers as $k => $roomNumber){
            $roomUrl = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, 'roomUrl');
            $promises[$roomNumber] = $client->postAsync($roomUrl.Definition::$CHECK_ROOM, ['json' => ['roomId' => $roomNumber], 'connect_timeout' => 1]);
        }
        // 忽略某些请求的异常，保证所有请求都发送出去
        $results = Promise\settle($promises)->wait();
        $newNumbers = [];
        foreach ($results as $roomNumber => $v){
            if(isset($results[$roomNumber]['value'])){
                $roomCheckInfo = json_decode($results[$roomNumber]['value']->getBody()->getContents(), true);
                if(isset($roomCheckInfo['content']['exist']) && $roomCheckInfo['content']['exist']){
                    $newNumbers[] = $roomNumber;
                }else{
                    Log::write('房间不存在'.$this->opt['club_id'].'_'.$roomNumber, "log");
                    $res = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                    if($res){
                        $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, time(), $this->opt['roomId']); // 迭代占用的房间号
                    }
                }
            }else{
                Log::write('服务不可用'.$this->opt['club_id'].'_'.$roomNumber, "log");
                $res = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                if($res){
                    $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, time(), $this->opt['roomId']); // 迭代占用的房间号
                }
            }
        }
        # 和逻辑服同步

        $i = 0;
        $clubRoomReturn = [];
        foreach ($newNumbers as $k => $roomNum){
            if(!$redisHandle->exists(RedisKey::$USER_ROOM_KEY_HASH.$roomNum)){
                continue;
            }
            $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomNum, ['roomName', 'joinStatus', 'clubType', 'roomRate', 'roomCode', 'diamond', 'roomOptionsId', 'needUserNum', 'roomRate', 'socketH5', 'socketUrl', 'roomOptions', 'playerInfos', 'createTime']);
//            p($roomHashValue);
            if($roomHashValue){
                $diamond = $roomHashValue['diamond'];
                if(($roomHashValue['roomRate'] == 0) && ($roomHashValue['clubType'] == 0)){
                    if($roomHashValue['needUserNum']){
                        $diamond = bcdiv($diamond, $roomHashValue['needUserNum'], 0);
                    }
                }
                $clubRoomReturn[$i]['joinStatus'] = $roomHashValue['joinStatus'];
                $clubRoomReturn[$i]['room_id'] = $roomNum;
                $clubRoomReturn[$i]['diamond'] = $diamond;
                $clubRoomReturn[$i]['match_id'] = $roomHashValue['roomOptionsId'];
                $clubRoomReturn[$i]['player_size'] = $roomHashValue['needUserNum'];
                $clubRoomReturn[$i]['room_code'] = $roomHashValue['roomCode'];
                $clubRoomReturn[$i]['room_rate'] = $roomHashValue['roomRate'];
                $clubRoomReturn[$i]['room_name'] = $roomHashValue['roomName'];
                $clubRoomReturn[$i]['socket_h5'] = $roomHashValue['socketH5'];
                $clubRoomReturn[$i]['socket_url'] = $roomHashValue['socketUrl'];
                $clubRoomReturn[$i]['options'] = json_decode($roomHashValue['roomOptions'], true);
                $roomUserInfos = json_decode($roomHashValue['playerInfos'], true);
                if($roomUserInfos){
                    foreach ($roomUserInfos as $userInfo){
                        $userInfoReturn['image'] = $userInfo['headImgUrl'];
                        $userInfoReturn['nickname'] = $userInfo['nickName'];
                        $userInfoReturn['player_id'] = $userInfo['userId'];
                        $userInfoReturn['player_status'] = '';
                        $clubRoomReturn[$i]['player_info'][] = $userInfoReturn;
                    }
                }else{
                    $clubRoomReturn[$i]['player_info'] = [];
                }
                $clubRoomReturn[$i]['createTime'] = strtotime($roomHashValue['createTime']);
                $nowUserNum = count($clubRoomReturn[$i]['player_info']);
                if($nowUserNum >= $roomHashValue['needUserNum']){
                    $clubRoomReturn[$i]['nowNeedUserNum'] = 100;
                }else{
                    $clubRoomReturn[$i]['nowNeedUserNum'] = bcsub($roomHashValue['needUserNum'], $nowUserNum, 0);
                }
                $i++;
            }
        }

        $newArr = [];
        $kArr = [];
        foreach ($clubRoomReturn as $k => $v){
            if(!in_array($v['nowNeedUserNum'], $kArr)){
                $kArr[] = $v['nowNeedUserNum'];
            }
            $newArr[$v['nowNeedUserNum']][] = $v;
        }

        $KArrLen = count($kArr) - 1;
        while (true){
            $flag = true;
            for($i = 0; $i < $KArrLen; $i++){
                if($kArr[$i] > $kArr[$i+1]){
                    $tmp = $kArr[$i];
                    $kArr[$i] = $kArr[$i+1];
                    $kArr[$i+1] = $tmp;
                    $flag = false;
                }
            }
            if($flag == true){
                break;
            }
            $KArrLen--;
        }

        foreach ($kArr as $k => $v){
            $vArrLen = count($newArr[$v]) - 1;
            while (true){
                $flag = true;
                for ($i = 0; $i < $vArrLen; $i++){
                    if($newArr[$v][$i]['createTime'] > $newArr[$v][$i+1]['createTime']){
                        $tmp = $newArr[$v][$i];
                        $newArr[$v][$i] = $newArr[$v][$i+1];
                        $newArr[$v][$i+1] = $tmp;
                        $flag = false;
                    }
                }
                if($flag == true){
                    break;
                }
                $vArrLen--;
            }
        }

        $returnData = [];
        foreach ($newArr as $k => $v){
            foreach ($v as $kk => $vv){
                $returnData[] = $vv;
            }
        }

        $return['roominfo'] = $returnData;
        return jsonRes(0, $return);
    }
}