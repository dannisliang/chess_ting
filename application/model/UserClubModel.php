<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/15
 * Time: 21:10
 */

namespace app\model;


use think\Model;

class UserClubModel extends Model
{
    protected $name = 'user_club';

    /**
     * 获取玩家是否加入俱乐部
     * @param $userId 玩家ID
     * @param $clubId 俱乐部ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserClubInfoByUserIDAndClubId($userId, $clubId){
        return $this->where('player_id', '=', $userId)->where('club_id', '=', $clubId)->where('status', '=', 1)->find();
    }
}