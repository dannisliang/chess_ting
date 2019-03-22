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
        0       => '获取数据成功',
        3001    => '请求方法不正确',
        3002    => '验证未通过',
        3004    => '没有查询到',
        3006    => '请求参数有误',
        3008    => '俱乐部人数已满',
        3009    => '查询的俱乐部不存在',
        3012    => '该工会不存在',
        3015    => '您已加入该俱乐部',
        3300    => '还没加入俱乐部呢',
        3999    => '没有此玩法相关数据',
        3998    => '查询数据库失败',
        3400    => 'http请求错误！',
        3000    => '请求成功',
        3401    => '获取去数据异常',
        3402    => '数据不存在',
        9999    => '请重新登陆',
        1111    => '服务器内部错误',
        23202   => '房间不存在',
        23203   => '房间已存在',
        23204   => '房间已满',
        23206   => '创建房间成功',
        23004   => '进入失败,没有加入该俱乐部',
        23005   => '没有符合此条件的俱乐部',
        23402   => '您是本亲友圈会长,不能退出亲友圈',
        23403   => '没有商品',


        # 谢百川专用
        3500 => '俱乐部不存在',
        3501 => '房间玩法不存在',
        3502 => '该俱乐部没有此房间玩法',
        3503 => '玩法解析错误',
        3504 => '房间计费模式解析错误',
        3505 => '房间不存在',
        3506 => '加入房间失败',
        3507 => '解散房间成功',
        3508 => '解散房间失败',
        3509 => '玩家不在房间',
        3510 => '退出房间成功',
        3511 => '没有加入此俱乐部',
        23401   => '扣钻失败',
        23205 => '创建房间失败，请重试',

    ];
}