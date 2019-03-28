<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;


Route::rule('service/getUserInfo','user/getUserInfo');
Route::rule('service/getToken','token/getToken');

/**
 * 与俱乐部相关
 */
Route::rule('service/club/getClubInfo','club/getClubInfo');
Route::rule('service/club/getClubInfos','club/getClubInfos');
Route::rule('service/club/JoinClub','club/joinClub');
Route::rule('service/club/outClub','club/outClub');

/**
 * 房间相关
 */
Route::rule('service/room/cheatRoom','Room/getRoomGpsInfo'); # 获取房间的gps相关数据
Route::rule('service/room/room_list','Room/getRoomList'); # 俱乐部房间列表

Route::rule('service/disband/getroom_list','Room/getUserRoom'); # 获取玩家房间
Route::rule('service/disband/disband_room','Room/disBandRoom'); # 强制解散房间
Route::rule('service/room/createRoom','Room/createRoom'); # 创建房间
Route::rule('service/room/joinRoom','Room/joinRoom'); # 加入房间
Route::rule('service/api/joinRoom','Room/joinRoomCallBack'); # 加入房间回调
Route::rule('service/api/outRoom','Room/outRoomCallBack'); # 退出房间回调
Route::rule('service/api/roomStartGame','Room/roomStartGameCallBack'); # 房间游戏开始回调
Route::rule('service/api/roomEndGame','Room/roomEndGameCallBack'); # 房间游戏结束回调
Route::rule('service/api/roundStartGame','Room/roundStartGameCallBack'); # 牌局游戏开始回调
Route::rule('service/api/roundEndGame','Room/roundEndGameCallBack'); # 牌局游戏结束回调
Route::rule('service/api/roomDisband','Room/disBandRoomCallBack'); # 房间解散回调
Route::rule('service/getTarUserInfo','gamingRoomInfo/getOtherUserInfo');  //获取房间内其他用户信息

/**
 * 商城相关
 */
Route::rule('service/shop/shopDetail','shop/shopGoodsList'); //商城列表
Route::rule('service/shop/getOrder','shop/getOrder'); //获取订单号
Route::rule('service/shop/buygold','shop/buyGold'); //购买金币
Route::rule('service/shop/orderPay','shop/orderPay'); //H5下单

Route::rule('service/shop/reciveOrder','paySuccessCallBack/receiveOrder'); //支付订单回调


/**
 * vip卡相关
 */
Route::rule('service/vip/useVipCard','Shop/useVipCard'); # 使用vip卡

/**
 * 牌局记录相关
 */
Route::rule('service/room/getRecordRoomList','Room/getRecordRoomList'); # 牌局记录列表
Route::rule('service/room/getRecordList','Room/getRecordList'); # 牌局记录列表




return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

