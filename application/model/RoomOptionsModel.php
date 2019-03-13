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

    # 根据玩法ID获取玩法相关数据
    public function getInfoById($id){
        return $this->where('id', $id)->find();
    }

}