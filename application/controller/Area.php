<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 18:15
 */

namespace app\controller;

use app\model\AreaModel;

class Area extends Base
{
    public function lists()
    {
        $area_opt = new AreaModel();
        $result = $area_opt->select();
        return jsonRes(0,$result);
    }
}