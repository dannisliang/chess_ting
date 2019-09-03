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
            return json(['code' => -10000, 'mess' => '系统繁忙']);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        $sMembers = $redisHandle->sMembers(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id']);
        if(!$sMembers){
            return json(['code' => 0, 'mess' => '成功', 'data' => ['roominfo' => []]]);
        }

        $client = new Client();
        $promises = [];
        foreach ($sMembers as $k => $roomNumber){
            $roomUrl = $redisHandle->hGet(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, 'roomUrl');
            $promises[$roomNumber] = $client->postAsync($roomUrl.Definition::$CHECK_ROOM, ['json' => ['roomId' => $roomNumber], 'connect_timeout' => 2, 'timeout' => 2]);
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
                    $redisHandle->expire(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, bcmul(bcmul(3600, 24, 0), 4, 0));
                    $res = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                    if($res){
                        $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, time(), $roomNumber); // 迭代占用的房间号
                    }
                }
            }else{
                $redisHandle->expire(RedisKey::$USER_ROOM_KEY_HASH.$roomNumber, bcmul(bcmul(3600, 24, 0), 4, 0));
                $res = $redisHandle->sRem(RedisKey::$CLUB_ALL_ROOM_NUMBER_SET.$this->opt['club_id'], $roomNumber);
                if($res){
                    $redisHandle->zAdd(RedisKey::$USED_ROOM_NUM, time(), $roomNumber); // 迭代占用的房间号
                }
            }
        }
        // 和逻辑服同步

        $i = 0;
        $clubRoomReturn = [];
        $tmp = [];
        foreach ($newNumbers as $k => $roomNum){
            $roomHashValue = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$roomNum, ['roomName', 'joinStatus', 'clubType', 'roomRate', 'roomCode', 'diamond', 'roomOptionsId', 'needUserNum', 'roomRate', 'socketH5', 'socketUrl', 'roomOptions', 'playerInfos', 'createTime']);
            $roomUserInfos = json_decode($roomHashValue['playerInfos'], true);
            if($roomUserInfos){
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
                foreach ($roomUserInfos as $userInfo){
                    $userInfoReturn['image'] = $userInfo['headImgUrl'];
                    $userInfoReturn['nickname'] = $userInfo['nickName'];
                    $userInfoReturn['player_id'] = $userInfo['userId'];
                    $userInfoReturn['player_status'] = '';
                    $clubRoomReturn[$i]['player_info'][] = $userInfoReturn;
                }

                $clubRoomReturn[$i]['createTime'] = strtotime($roomHashValue['createTime']);
                $nowUserNum = count($clubRoomReturn[$i]['player_info']);
                if($nowUserNum >= $roomHashValue['needUserNum']){
                    $clubRoomReturn[$i]['nowNeedUserNum'] = 100;
                }else{
                    $clubRoomReturn[$i]['nowNeedUserNum'] = bcsub($roomHashValue['needUserNum'], $nowUserNum, 0);
                }
                $tmp['nowNeedUserNum'][] = $clubRoomReturn[$i]['nowNeedUserNum'];
                $tmp['createTime'][] = $clubRoomReturn[$i]['createTime'];
                $i++;
            }
        }
        if(isset($tmp['nowNeedUserNum']) && isset($tmp['createTime'])){
            array_multisort($tmp['nowNeedUserNum'], SORT_ASC, $tmp['createTime'], SORT_DESC, $clubRoomReturn);
        }

        $return['roominfo'] = $clubRoomReturn;
        return json(['code' => 0, 'mess' => '成功', 'data' => $return]);
    }
}