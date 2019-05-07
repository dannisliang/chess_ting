<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 11:57
 */
namespace app\model;

use think\Model;
class GameServiceNewModel extends Model{

    protected $name = 'game_service_new';

    /**
     * 根据房间规则的玩法ID获取玩法可连接的服务器  集
     * @param $roomType
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getService($playType){
        return $this->where('is_open', '=', 1)->where('is_goto', '=', 1)->where('room_type', '=', $playType)->select();
    }

    /**
     * 获取所有可连接的服务器
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getGameService(){
        return $this->where('is_open', '=', 1)->select();
    }

    /**
     * 根据条件获取多条数据
     * @param $where
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSomeByWhere($where, $field = '*'){
        return $this -> where($where) -> field($field) -> select();
    }

    /**
     * 根据条件获取一条数据
     * @param $where
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where, $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }
}