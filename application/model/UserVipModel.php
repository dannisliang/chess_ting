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
    public function getUserVipInfo($userId, $clubId){
        $date = date("Y-m-d H:i:s", time());
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('vip_status', '=', 1)->where('end_day', '>', $date)->find();
    }

    /**
     * 根据条件去查询一条数据
     * @param $where array
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByJoinWhere($where){

         return $this -> where($where)
            -> alias('a')
            ->join('vip_card b','a.vid = b.vip_id','LEFT')
            ->field('a.vid,a.end_day,a.card_number,b.name,b.type')
            ->join('vip_type c','c.v_type = b.type','LEFT')
            ->field('c.v_name')
            ->find();
    }

    /**
     * 查用户俱乐部的vip卡
     * @param $userId
     * @param $clubId
     * @param $vid
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserVipCardInfo($userId, $clubId, $vid){
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('vid', '=', $vid)->where('card_number', '>', 0)->find();
    }

    /**
     * 查询用户俱乐部的所有可用vip卡
     * @param $userId
     * @param $clubId
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getUserAllVipCard($userId, $clubId){
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('card_number', '>', 0)->select();
    }

    /**
     * 获取一条数据
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }

    /**
     * 根据条件更新数据
     * @param $where
     * @param $data
     * @return UserVipModel
     */
    public function updateByWhere($where , $data){
        return $this -> where($where) -> update($data);
    }

    /**
     * 插入数据
     * @param $data
     * @return int|string
     */
    public function insertData($data){
        return $this -> insert($data);
    }

    /**
     * 获取用户在使用得相同级别得vip卡
     * @param $userId
     * @param $clubId
     * @param $vipLevel
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserNowVipCardInfo($userId, $clubId, $vipLevel){
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('vip_level', '=', $vipLevel)->where('vip_status', '=', 1)->find();
    }
}