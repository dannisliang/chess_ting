<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 14:12
 */
namespace app\model;

use think\Model;

class ServiceGatewayNew extends Model{

    protected $name = 'service_gateway_new';

    # 根据服务器ID获取服务器数据
    public function getInfoById($serviceId){
        return $this->where('id', '=', $serviceId)->find();
    }
}