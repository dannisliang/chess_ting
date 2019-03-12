<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/12
 * Time: 19:44
 */

namespace app\model;

use think\Model;

class TbVipCard extends Model{

    protected $name = 'tb_vip_card';

    # 根据俱乐部ID获取俱乐部数据
    public function getInfoById($vipCardId){
        return $this->where('id', '=', $vipCardId)->find();
    }
}