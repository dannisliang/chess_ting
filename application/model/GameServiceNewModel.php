<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 11:57
 */
namespace app\model;

use think\Model;

class GameServiceNewModel extends Model{

    protected $name = 'game_service_new';

    /**
     * 根据房间规则的玩法ID获取玩法可连接的服务器  集
     * @param $roomType
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getGameServiceNewInfosByRoomTypeId($roomType){
        return $this->where('is_open', '=', 1)->where('room_type', '=', $roomType)->select();
    }
}