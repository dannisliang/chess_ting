<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/8
 * Time: 11:04
 */

namespace app\controller;

use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\Controller;
use think\Request;
use think\Session;


class Base extends Controller
{
    public $opt;

    # 自测通过
    public function _initialize()
    {
        # 拒绝一切非post请求
        $method = Request::instance()->method();
        if($method !== 'POST'){
            return json(['code' => 3001, 'mess' => '请求方法不正确'])->send();
        }

        $this->opt = file_get_contents("php://input");
        $this->opt = json_decode($this->opt,true);

//        if(!$this->opt){
//            return json(['code'=>3006, 'mess' => '缺少请求参数'])->send();
//        }
    }
}