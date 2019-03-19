<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:31
 */

namespace app\model;

use think\Model;

class RoomOptionsModel extends Model{

    protected $name = 'room_options';

    /**
     * @param $id 玩法规则ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getRoomOptionInfoByRoomOptionsId($roomOptionsId){
        return $this->where('id', $roomOptionsId)->find();
    }

}