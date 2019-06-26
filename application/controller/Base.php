<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/8
 * Time: 11:04
 */

namespace app\controller;

use think\Controller;
use think\Request;


class Base extends Controller
{
    public $opt;

    /**
     * 基础方法
     * @return mixed
     */
    public function _initialize()
    {
        // 拒绝一切非post请求
        $method = Request::instance()->method();
        if($method !== 'POST'){
            return json(['code' => 3001, 'mess' => '请求方法不正确'])->send();
        }

        $this->opt = file_get_contents("php://input");
        $this->opt = json_decode($this->opt,true);
    }

    /**
     * @param $redisHandle object redis实例
     * @param $lockKey string key名
     * @return bool
     */
    public function getLock($redisHandle, $lockKey){
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
            usleep(1000);
        }
        return $getLock;
    }
}