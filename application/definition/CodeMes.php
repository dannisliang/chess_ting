<?php

/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\definition;

class CodeMes{

    # 自定义错误返回码
    public static $errorCode = [
        9999 => '请重新登陆',
        3001 => '请求方法不正确',
        3006 => '请求参数不全',
        3999 => '没有此玩法相关数据',
        23401 => '玩家钻石不足',
        1111 => '服务器内部错误，请重试',
    ];
}