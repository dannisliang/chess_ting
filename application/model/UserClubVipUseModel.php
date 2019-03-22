<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/22
 * Time: 15:33
 */
namespace app\model;

use think\Model;

class UserClubVipUseModel extends Model{

    protected $name = 'user_club_vip_use';

     /**
     * 获取玩家vip卡相关信息 不限制是否过期
     * @param $userId
     * @param $clubId
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserClubVipUseInfoByUserIdAndClubId($userId, $clubId){
        return $this->where('uid', '=', $userId)->where('cid', '=', $clubId)->find();
    }


}