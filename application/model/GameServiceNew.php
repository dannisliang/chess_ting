<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 11:57
 */
namespace app\model;

use think\Model;

class GameServiceNew extends Model{

    protected $name = 'game_service_new';

    # 根据玩法获取玩法专属服务器
    public function getInfosByRoomTypeId($roomType){
        $where = [
            ['is_open', '=', 1],
            ['room_type', '=', $roomType],
        ];
        return $this->where($where)->select();
    }
}