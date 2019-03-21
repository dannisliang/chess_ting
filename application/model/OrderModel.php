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
}