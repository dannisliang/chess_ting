<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/8
 * Time: 11:04
 */

namespace app\controller;

use think\Config;
use think\Controller;
use think\Log;
use think\Request;


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
            exit();
        }

        # 线下调试模式接受非json传参
        $appDebug = Config::get('app_debug');
        if($appDebug){
            $this->opt = input('post.');
        }else{
            $this->opt = file_get_contents("php://input");
            $this->opt = json_decode($this->opt,true);
        }

        if(!$this->opt){
            return json(['code'=>3006, 'mess' => '缺少请求参数'])->send();
            exit();
        }
    }
    //jjjj
}