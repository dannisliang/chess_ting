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
        3001    => '请求方法不正确',
        3002    => '验证未通过',
        3006    => '请求参数不全',
        3999    => '没有此玩法相关数据',
        23401   => '玩家钻石不足',
        3400    => 'http请求错误！',
        3000    => '请求成功',
        0       => '获取数据成功',
        3401    => '获取去数据异常',
        3402    => '数据不存在',
        9999 => '请重新登陆',
        1111 => '服务器内部错误',
        23202 => '房间不存在',
        23203 => '房间已存在',
        23204 => '房间已满',
        23205 => '创建房间失败，请重试',
        23206 => '创建房间成功',
    ];
}