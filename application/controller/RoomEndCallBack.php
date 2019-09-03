<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:49
 */
namespace app\controller;


use app\definition\RedisKey;
use think\cache\driver\Redis;


class RoomEndCallBack extends Base
{
    public function roomEndCallBack(){
        if(!isset($this->opt['roomId']) || !is_numeric($this->opt['roomId']) || !isset($this->opt['statistics']) || !is_array($this->opt['statistics'])){
            return jsonRes(0);
        }

        # 修改房间的结束时间
        $redis = new Redis();
        $redisHandle = $redis->handler();

        $setData = [
            'gameEndTime' => date('Y-m-d H:i:s', time()),
            'gameEndInfo' => json_encode($this->opt['statistics'])
        ];
        $redisHandle->hMset(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], $setData);

        # 牌局正常结束 返回逻辑服扣钻相关数据
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], ['playerInfos', 'clubType', 'roomRate']);
        $playerInfo = json_decode($roomHashInfo['playerInfos'], true);

        if($this->opt['round'] && ($roomHashInfo['clubType'] != 1) && $playerInfo){
            $returnData = [];
            if($roomHashInfo['roomRate'] == 1){ # 大赢家模式
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

                foreach ($this->opt['statistics'] as $k => $v){
                    if(in_array($v['playerId'], $userIds)){ # 需要扣钻
                        foreach ($playerInfo as $kk => $userInfo){
                            if($userInfo['userId'] == $v['playerId']){
                                $bind = isset($userInfo['needDiamond']['bind']) ? $userInfo['needDiamond']['bind'] : 0;
                                $noBind = isset($userInfo['needDiamond']['noBind']) ? $userInfo['needDiamond']['noBind'] : 0;
                                $total = bcdiv(bcadd($bind, $noBind, 0), $userNum, 0);
                                $returnData[] = [
                                    'player_id' => $v['playerId'],
                                    'room_cost' => $total,
                                ];
                            }
                        }
                    }else{
                        $returnData[] = [
                            'player_id' => $v['playerId'],
                            'room_cost' => 0,
                        ];
                    }
                }
            }

            if($roomHashInfo['roomRate'] == 0){ # 平均扣钻
                foreach ($this->opt['statistics'] as $k => $v){
                    foreach ($playerInfo as $kk => $userInfo){
                        if($v['playerId'] == $userInfo['userId']){
                            $bind = isset($userInfo['needDiamond']['bind']) ? $userInfo['needDiamond']['bind'] : 0;
                            $noBind = isset($userInfo['needDiamond']['noBind']) ? $userInfo['needDiamond']['noBind'] : 0;
                            $total = bcadd($bind, $noBind, 0);
                            $returnData[] = [
                                'player_id' => $v['playerId'],
                                'room_cost' => $total,
                            ];
                        }
                    }
                }
            }
        }else{
            $returnData = [];
            foreach ($this->opt['statistics'] as $k => $v){
                $returnData[] = [
                    'player_id' => $v['playerId'],
                    'room_cost' => 0,
                ];
            }
        }
        return jsonRes(0, $returnData);
    }
}