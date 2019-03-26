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
Route::rule('service/room/createRoom','Room/createRoom');
Route::rule('service/room/disBandRoom','Room/disBandRoom');
Route::rule('service/room/joinRoom','Room/joinRoom');
Route::rule('service/room/getUserRoom','Room/getUserRoom');
Route::rule('service/room/getRoomGpsInfo','Room/getRoomGpsInfo');
Route::rule('service/room/outRoom','Room/outRoomCallBack');
Route::rule('service/room/room_list','Room/getRoomList');
Route::rule('service/room/disBandRoomCallBack','Room/disBandRoomCallBack');

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



return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

