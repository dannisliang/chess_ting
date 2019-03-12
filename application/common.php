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
 * @param $code 状态码
 * @param $data 返回值
 * @return \think\response\Json\
 */
function jsonRes($code, $data = [] ){
    $res = [
        'code' => $code,
        'mess' => CodeMes::$errorCode[$code],
    ];

    if($data){
        $res['data'] = $data;
    }
    return json($res);
}

/**
 * 统一返回数据为array
 * @param $code
 * @param $msg
 * @param $data
 * @return array
 */
function msg($code, $data = [] ){
    $res = [
        'code' => $code,
        'mess' => CodeMes::$errorCode[$code],
    ];

    if($data){
        $res['data'] = $data;
    }
    return $res;
}


