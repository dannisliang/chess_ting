<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13
 * Time: 10:46
 */

namespace app\model;


use think\Exception;
use think\Model;

class UserRoomModel extends Model
{
    protected $name = 'user_room';

    /**
     * 获取玩家信息
     * @param $player_id
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getUserRoomInfo($player_id){
        try{
            return $this -> where('player_id',$player_id) ->field('service,socket_h5,socket_url,room_num')->select();
        }catch (\Exception $e){
            return false;
        }
    }

    public function delUserRoom($player_id,$room_num){
        try{
            return $this -> where('player_id',$player_id)->where('room_num',$room_num)->delete();
        }catch (\Exception $e){
            return false;
        }
    }

    # 根据服务器ID获取获取服务器房间数
    public function getServiceRoomNumByServiceId($serviceId){
        return $this->where('service',$serviceId)->count();
    }
}