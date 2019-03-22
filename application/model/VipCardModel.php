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
     * 根据VIP卡ID获取vip卡详细信息
     * @param $vipCardId
     * @return array|false|\PDOStatement|string|Model
     */
    public function getVipCardInfoByVipCardId($vipCardId){
        return $this->where('vip_id', '=', $vipCardId)->find();
    }

    public function getOneByWhere(){

    }
}