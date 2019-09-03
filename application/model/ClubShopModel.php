<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/20
 * Time: 17:37
 */

namespace app\model;


use think\Model;

class ClubShopModel extends Model
{
    protected $name = 'club_shop';

    /**
     * 根据类型获取钻石种类
     * @param $type
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSomeByWhere($where , $order = ''){
       return $this -> where($where) -> order($order) -> select();
    }

    /**
     * 根据条件查询一条记录
     * @param $where
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) ->find();
    }
}