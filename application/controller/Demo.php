<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/8
 * Time: 13:32
 */

namespace app\controller;


use think\cache\driver\Redis;
use think\Session;

class Demo extends Base
{
    public function index(){
        Session::set('name','test');
        $res = Session::get('name');
//        $redis = new \Redis();
//        $redis ->  connect('127.0.0.1');
//        $redis -> auth('samsung');
//        $redis -> set('name','zhangsan',10);
//        $res = $redis ->keys('*');
        var_dump($_SESSION);die;
        $a = new Redis();
    }
}