<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/8
 * Time: 11:04
 */

namespace app\controller;


class Base
{
    /**
     * 封装post请求 （如果将结果存入日志请传参数）
     * @param null $logName
     * @return mixed|\think\response\Json
     */
    protected function postRespond($logName = false){
        $method = Request::instance()->method();

        if($method !== 'POST'){
            return msg( 3001 , '请求方法不正确');
        }

        $opt = file_get_contents("php://input");
        if(!$logName){
            Log::write($opt,$logName);
        }
        $opt = json_decode($opt,true);
        return msg( 0 , '获取数据成功' , $opt);
    }
}