<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/11
 * Time: 17:46
 */

namespace app\model;


use think\Model;

class GainAchievement extends Model
{
    protected $name = 'gain_achievement';

    /**
     * 根据条件获取一条数据
     * @param $where
     * @param string $field
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) ->find();
    }
}