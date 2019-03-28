<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/21
 * Time: 18:16
 */

namespace app\model;


use think\Model;

class OrderModel extends Model
{
    protected $name = 'order';

    /**
     * 插入数据
     * @param $data
     * @return int|string
     */
    public function insertData($data){
        return $this -> insert($data);
    }

    /**
     * 根据条件查出一条数据
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this ->where($where) -> field($field) ->find();
    }

    /**
     * 根据条件修改字段值
     * @return OrderModel
     */
    public function setFieldByWhere($where , $field){
        return $this -> where($where) ->update($field);
    }
}