<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13
 * Time: 11:13
 */

namespace app\model;


use think\Model;

class ServiceGatewayNewModel extends Model
{
    protected $name = 'service_gateway_new';

    /**
     * 获取服务器列表
     * @param $id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getService($id){
        $result = $this -> where('id',$id)->field('service')->find();
        return $result;
    }

    /**
     * 根据服务器ID获取服务器房间数
     * @param $serviceId 服务器ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getServiceGatewayNewInfo($serviceId){
        return $this->where('id', '=', $serviceId)->select();
    }

    /**
     * 根据条件获取一条记录
     * @param $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }


    /**
     * 获取所有可连接的服务器
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getServiceGatewayNewInfos(){
        return $this->select();
    }

    /**
     * 获取可连接的服务
     * @param $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getServiceGatewayNewInfosByWhere($where){
        return $this->where($where)->select();
    }
}