<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/14
 * Time: 11:36
 */

namespace app\model;


use think\Model;

class UserEvaluateModel extends Model
{
    protected $name = 'user_evaluate';

    /**
     * 获取评论的信息数量
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoById($id){
        return $this -> where('player_id',$id)->find();
    }

    /**
     * 获取条件选择一条数据
     * @param $where
     * @param string $fielld
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOneByWhere($where , $fielld = '*'){
        return $this -> Where($where) -> field($fielld) -> find();
    }

    /**
     * 添加修改数据
     */
    public function saveData($data , $where = null){
        if($where){
            return $this -> where($where) -> update($data);
        }else{
            return $this -> insert($data);
        }
    }
}