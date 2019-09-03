<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
if(isset($_SERVER['HTTP_ORIGIN'])){
    $Access_Control_Allow_Origin = $_SERVER['HTTP_ORIGIN'];
    header("Access-Control-Allow-Credentials: true");
}else{
    $Access_Control_Allow_Origin = '*';
}
header("Access-Control-Allow-Origin: " . $Access_Control_Allow_Origin);

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
