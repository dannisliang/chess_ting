<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/24
 * Time: 14:18
 */

namespace app\controller;

use app\definition\Definition;
use app\model\GameServiceNewModel;
use app\model\ServiceGatewayNewModel;


class Room extends Base {
    /**
     * 后台
     */
# 查询玩家所在的房间完成
    public function getUserRoom(){
        if(!isset($this->opt['uid']) || !$this->opt['uid'] || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        # 去逻辑服获取玩家所在房间
        $gameServiceNew = new GameServiceNewModel();
        $gameServiceNewInfos = $gameServiceNew->getGameService();
        $gameServiceNewArr = [];
        foreach ($gameServiceNewInfos as $k => $v){
            $gameServiceNewArr[] = $v['service_id'];
        }

        $serviceGatewayNew = new ServiceGatewayNewModel();
        $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();

        foreach ($serviceGatewayNewInfos as $k => $v){
            if(in_array($v['id'], $gameServiceNewArr)){
                $userRoom = sendHttpRequest($v['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
                if(isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                    return jsonRes(0, [$userRoom['content']['roomId']]);
                }
            }
        }
        return jsonRes(3509);
    }
# 强制解散玩家房间完成
    public function disBandRoom(){
        if(!isset($this->opt['uid']) || !is_numeric($this->opt['uid'])){
            return jsonRes(3006);
        }

        # 去逻辑服获取玩家所在房间
        $gameServiceNew = new GameServiceNewModel();
        $gameServiceNewInfos = $gameServiceNew->getGameService();
        $gameServiceNewArr = [];
        foreach ($gameServiceNewInfos as $k => $v){
            $gameServiceNewArr[] = $v['service_id'];
        }

        if($gameServiceNewArr){
            $serviceGatewayNew = new ServiceGatewayNewModel();
            $serviceGatewayNewInfos = $serviceGatewayNew->getServiceGatewayNewInfos();
            foreach ($serviceGatewayNewInfos as $k => $v){
                if(in_array($v['id'], $gameServiceNewArr)){
                    $userRoom = sendHttpRequest($v['service'].Definition::$GET_USER_ROOM, ['playerId' => $this->opt['uid']]);
                    if(isset($userRoom['content']['roomId']) && $userRoom['content']['roomId']){
                        $disBandRes = sendHttpRequest($v['service'].Definition::$DIS_BAND_ROOM.$userRoom['content']['roomId'], ['playerId' => $this->opt['uid']]);
                        if(isset($disBandRes['content']['result']) && ($disBandRes['content']['result'] == 0)){
                            return jsonRes(0);
                        }
                    }
                }
            }
        }
//
        return jsonRes(3508);
    }
}
