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
     * 根据条件查询字段
     * @param $where
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSomeByWhere($where,$field = '*' )
    {
        return $this->where($where)->field($field)->select();
    }
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