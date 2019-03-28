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

/**
 * 用户相关
 */
Route::rule('service/getUserInfo','user/getUserInfo'); //获取大厅玩家信息
Route::rule('service/getToken','token/getToken');  //验证token
Route::rule('service/getComment','user/getComment'); //获取玩家的好评差评数量
Route::rule('service/checkUserInRoom','user/checkUserInRoom'); //检测玩家是否在游戏房间中

/**
 * 与俱乐部相关
 */
Route::rule('service/club/getClubInfo','club/getClubInfo');  //首页点击进入俱乐部
Route::rule('service/club/getClubInfos','club/getClubInfos'); //俱乐部列表
Route::rule('service/club/JoinClub','club/joinClub'); //加入俱乐部
Route::rule('service/club/outClub','club/outClub'); //退出俱乐部
Route::rule('service/club/getUserVipInfo','club/getUserVipInfo'); //获取玩家的vip信息

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
Route::rule('service/room/roundEndGameCallBack','Room/roundEndGameCallBack');

Route::rule('service/getTarUserInfo','gamingRoomInfo/getOtherUserInfo');  //获取房间内其他用户信息
Route::rule('service/room/getusergold','gamingRoomInfo/getUserProperty');  //获取房间用户资产


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
/**
 * 邮件相关
 */
Route::rule('service/getMailList','Mail/lists');
Route::rule('service/GetMailDetail','Mail/detail');
Route::rule('service/deleteMail','Mail/delete');
/*领取邮件里附加你接口地址service/reciveGoods*/
/*跑马灯*/
Route::rule('service/getBulletinList','HorseLamp/lists');
/*公告*/
Route::rule('service/getnotice','Notice/lists');
/*代理招募部分*/
Route::rule('service/agent/open_recruit','Agent/openRecruit');
Route::rule('service/agent/recruit_state','Agent/state');
Route::rule('service/agent/recive_recruit','Agent/recive');



return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

