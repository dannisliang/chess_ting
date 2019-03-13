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
}