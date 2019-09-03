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
    public function getUserClubInfo($userId, $clubId){
        return $this->where('player_id', '=', $userId)->where('club_id', '=', $clubId)->where('status', '=', 1)->find();
    }

    /**
     * 获取一条信息
     * @param $where
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }

    /**
     * 根据条件查询人数
     * @param $cid
     * @return int|string
     * @throws \think\Exception
     */
    public function getCountByWhere($where){
        return $this -> where($where)->count();
    }

    /**
     * 插入一条用户数据
     * @param $data
     * @return int|string
     */
    public function insertUser($data){
        return $this -> insert($data);
    }

    /**
     * 根据条件删除信息
     * @param $where
     * @return int
     */
    public function delByWhere($where){
        return $this -> where($where) ->delete();
    }

    /**
     * 获取用户俱乐部信息
     * @param $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getInfoByWhere($where){
        return $this -> alias('a')
            -> join('club b','a.club_id = b.cid')
            -> where($where)
            -> field('a.club_id,b.club_name')
            -> select();
    }


}