<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/11
 * Time: 13:56
 */
namespace app\model;

use think\Model;

class TbClub extends Model{

    protected $name = 'tb_club';

    # 根据俱乐部ID获取俱乐部数据
    public function getInfoById($id){
        return $this->where(['id', '=', $id])->find();
    }
}