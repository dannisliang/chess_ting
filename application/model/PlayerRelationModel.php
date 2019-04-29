<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/11
 * Time: 14:45
 */

namespace app\model;


use think\Model;

class PlayerRelationModel extends Model
{
    protected $name = 'player_relation';

    /**
     * 根据条件获取一条数据
     * @param $where
     * @param string $field
     * @return mixed
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }

    /**
     * 插入数据
     * @param $data
     * @return mixed
     */
    public function insertData($data){
        return $this ->insert($data);
    }

    /**
     * 根据条件获取批量数据
     * @param $where
     * @return mixed
     */
    public function getSomeByWhere($where){
        return $this -> where($where) -> select();
    }
}