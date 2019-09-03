<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/20
 * Time: 16:22
 */

namespace app\model;


use think\Model;

class ClubVipModel extends Model
{
    protected $name = 'club_vip';

    /**
     * 获取俱乐部的vip卡详情
     * @param $club_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\exception\DbException
     */
    public function getClubVipCard($club_id){
         return $this->alias('a')
            ->join('vip_card b','a.vid = b.vip_id')
            ->where('a.club_id',$club_id)
            ->field('*')
            ->select();
    }

    /**
     * 根据条件获取一条记录
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field)->find();
    }

    /**
     * 根据条件某字段自减一
     * @param $where
     * @param $field
     * @return int|true
     * @throws \think\Exception
     */
    public function setDecByWhere($where , $field){
        return $this -> where($where) ->setDec($field);
    }
}