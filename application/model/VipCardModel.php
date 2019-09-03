<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/12
 * Time: 19:44
 */

namespace app\model;

use think\Model;

class VipCardModel extends Model{

    protected $name = 'vip_card';

    /**
     * @param $vipCardId
     * @return array|false|\PDOStatement|string|Model
     */
    public function getVipCardInfoByVipCardId($vipCardId){
        return $this->where('vip_id', '=', $vipCardId)->find();
    }

    /**
     * 根据条件获取一条数据
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }

    /**
     * 获取所有vip卡类型
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getAllVipCardInfo(){
        return $this->select();
    }

    public function getVipCardInfo($vipCardId){
        return $this->where('vip_id', '=', $vipCardId)->find();
    }
}