<?php
/**
 * Created by Xie
 * User: DELL
 * Date: 2019/3/12
 * Time: 19:19
 */
namespace app\model;

use think\Model;

class UserVipModel extends Model{

    protected $name = 'user_vip';

    /**
     * @param $userId 用户ID
     * @param $clubId 俱乐部ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserVipInfoByUserIdAndClubId($userId, $clubId){
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('vip_status', '=', 1)->find();
    }
}