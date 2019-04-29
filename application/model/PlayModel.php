<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:56
 */

namespace app\model;

use think\Model;

class PlayModel extends Model{

    protected $name = 'play';

    # 根据规则ID获取相关规则
    public function getPlayInfo($playId){
        return $this->where('id', '=' , $playId)->find();
    }

    /**
     * 获取玩法的信息
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where( $where ) -> field($field) -> find();
    }

    /**
     * 获取多条数据
     */
    public function getSomeById($where,$field){
        return $this -> where($where) -> field($field) -> select();
    }

}




