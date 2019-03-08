<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/8
 * Time: 10:30
 */

namespace app\controller;

class Index extends Base
{
    /**
     * 测试
     * @return \think\response\Json
     */
    public function index(){
        //测试测试
        return json(msg( 0 , '测试'));
    }
}