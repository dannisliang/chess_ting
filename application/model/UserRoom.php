<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 13:43
 */

namespace app\model;

use think\Model;

class UserRoom extends Model{

    protected $name = 'user_room';

    # 根据服务器ID获取获取服务器房间数
    public function getServiceRoomNumByServiceId($serviceId){
        return $this->where('service',$serviceId)->count();
    }

}