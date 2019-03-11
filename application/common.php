<?php

use app\definition\CodeMes;
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/**
 * 返回统一信息
 * @param $code
 * @param $data
 * @param $msg
 * @return array
 */
function msg($code , $msg , $data = [])
{
    return compact('code', 'msg' , 'data');
}

/**
 * 对象转换成数组
 * @param $obj
 */
function objToArray($obj)
{
    return json_decode(json_encode($obj), true);
}


/**
 * @param $code 状态码
 * @param $data 返回值
 * @return \think\response\Json\
 */
function jsonRes($code, $data = []){
    $res = [
        'code' => $code,
        'mess' => CodeMes::$errorCode[$code],
    ];

    if($data){
        $res['data'] = $data;
    }
    return json($res);
}