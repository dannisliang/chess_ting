<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/4/2
 * Time: 16:41
 */

namespace app\model;


use think\Model;

class CommerceModel extends Model
{
    protected $name = 'commerce';

    /**
     * 获取一条数据
     * @param $where
     */
    public function getOneByWhere($where){
        return $this -> where($where) -> find();
    }
}