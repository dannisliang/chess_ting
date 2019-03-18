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
        try{
            $result = $this -> where('id',$id)->field('service')->find();
            return $result;

        }catch (\Exception $e){
            return false;
        }

    }

    /**
     * 根据服务器ID获取服务器房间数
     * @param $serviceId 服务器ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getServiceGatewayNewInfoByServiceId($serviceId){
        return $this->where('id', '=', $serviceId)->find();
    }

    /**
     * 根据条件获取一条记录
     * @param $where
     * @param string $field
     * @return ServiceGatewayNewModel
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }
}