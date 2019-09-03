<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:31
 */

namespace app\model;

use think\Model;

class RoomOptionsModel extends Model{

    protected $name = 'room_options';

    /**
     * @param $id 玩法规则ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getRoomOptionInfo($roomOptionsId){
        return $this->where('id', $roomOptionsId)->find();
    }

    /**
     * 获取多条信息
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSomeByWhere($where , $field = '*' ){
        return $this -> Where( $where ) -> field( $field ) -> select();
    }

    /**
     * 获取一条信息
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where( $where ) -> field( $field ) -> find();
    }

    /**
     * 获取玩法和规则
     * @param $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getPlayAndOptions($where){
        return $this -> where($where)
            -> alias('a')
            -> join('play b','a.room_type = b.id')
            -> field('a.room_type,a.room_rate,a.room_name,a.diamond,a.options,a.id,b.play,b.play_type')
            -> select();
    }

}