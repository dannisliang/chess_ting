<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/12
 * Time: 15:43
 */

namespace app\controller;


use think\Request;

class Error
{
    public function index(Request $request){
        return json(['code'=>404,'mess'=>'路由未定义']);
    }
}