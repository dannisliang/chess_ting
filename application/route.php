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
Route::rule('service/getComment','user/getComment'); //获取玩家的好评差评数量（暂时废弃）
Route::rule('service/checkUserInRoom','user/checkUserInRoom'); //检测玩家是否在游戏房间中（暂时废弃）
Route::rule('service/getImage','proceseImage/getImage'); //处理图片

/**
 * 与俱乐部相关
 */
Route::rule('service/club/getClubInfo','club/getClubInfo');  //首页点击进入俱乐部
Route::rule('service/club/getClubInfos','club/getClubListOrSearch'); //俱乐部列表合查找俱乐部
Route::rule('service/club/JoinClub','club/joinClub'); //加入俱乐部
Route::rule('service/club/outClub','club/outClub'); //退出俱乐部
Route::rule('service/club/getUserVipInfo','club/getUserVipInfo'); //获取玩家的vip信息

/**
 * 房间相关
 */
Route::rule('service/room/room_list','RoomList/getRoomList'); # 俱乐部房间列表
Route::rule('service/room/creatroom','CreateRoom/createRoom'); # 创建房间
Route::rule('service/room/joinRoom','JoinRoom/joinRoom'); # 加入房间
Route::rule('service/api/joinRoom','JoinRoomCallBack/joinRoomCallBack'); # 加入房间回调
Route::rule('service/api/outRoom','OutRoomCallBack/outRoomCallBack'); # 退出房间回调
Route::rule('service/api/roomStartGame','RoomStartCallBack/roomStartCallBack'); # 房间游戏开始回调
Route::rule('service/api/roomEndGame','RoomEndCallBack/roomEndCallBack'); # 房间游戏结束回调
Route::rule('service/api/roundStartGame','RoundStartCallBack/roundStartCallBack'); # 牌局游戏开始回调
Route::rule('service/api/roundEndGame','RoundEndCallBack/roundEndCallBack'); # 牌局游戏结束回调
Route::rule('service/api/roomDisband','DisBandCallBack/disBandCallBack'); # 房间解散回调

Route::rule('service/room/cheatRoom','CheatRoom/cheatRoom'); # 获取房间的gps相关数据
Route::rule('service/disband/getroom_list','Room/getUserRoom'); # 获取玩家房间
Route::rule('service/disband/disband_room','Room/disBandRoom'); # 强制解散房间
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
Route::rule('service/shop/userVip','Vip/getUserVipCard'); # vip卡
Route::rule('service/shop/use_vipcard','Vip/useVipCard'); # 使用vip卡

/**
 * 牌局记录相关
 */
Route::rule('service/room/getRecordRoomList','Record/getRecordRoomList'); # 牌局记录列表
Route::rule('service/room/getRecordList','Record/getRecordList'); # 牌局记录列表
Route::rule('service/room/getGamePlayBack','Record/getGamePlayBack'); # 播放录像
Route::rule('service/room/addEvaluate','Record/addEvaluate'); # 添加对玩家评价


/**
 * 邮件相关
 */
Route::rule('service/getMailList','Mail/lists');
Route::rule('service/GetMailDetail','Mail/detail');
Route::rule('service/deleteMail','Mail/delete');
Route::rule('service/reciveGoods','Mail/receive');
/*领取邮件里附加你接口地址service/reciveGoods*/

/*跑马灯*/
Route::rule('service/getBulletinList','HorseLamp/lists');
Route::rule('service/getScrollScreen','HorseLamp/getScrollScreen'); //获取俱乐部跑马灯

/*公告*/
Route::rule('service/getnotice','Notice/lists');

/*代理招募部分*/
Route::rule('service/agent/open_recruit','Agent/openRecruit');
Route::rule('service/agent/recruit_state','Agent/state');
Route::rule('service/agent/recive_recruit','Agent/recive');

/**
 * 聊天相关
 */
Route::rule('service/inform/inform','Inform/inform');

/**
 * 地区相关
 */
Route::rule('service/area/get_area','Area/lists');

/**
 * 推广员相关
 */
Route::rule('service/generalize/bandPlayerId','Generalize/bandPlayerId'); //绑定邀请玩家
Route::rule('service/generalize/getUserInfo','Generalize/getUserInfo'); //推广员首页时的用户信息
Route::rule('service/generalize/getPlayerList','Generalize/getPlayerList'); //获取绑定其他玩家的信息

/**
 * 河南海选段相关
 */
Route::rule('match/getSort','Match/getSort');

//测试
Route::rule('service/test','AsyncTest/test');
Route::rule('service/demo','AsyncTest/demo');
Route::rule('service/demo1','AsyncTest/demo1');

/**
 * miss路由设置
 */
Route::miss('Miss/index');
Route::rule('route/miss','Miss/index');

/**
 * 微信商城
 */
Route::rule('service/getPlayerClubs','getPlayerClubs/getPlayerClubList'); // 俱乐部列表
Route::rule('service/WeChatShop/getShopList','WeChatShop/getShopList'); // 微信商城的列表
Route::rule('service/WeChatShop/createOrder','WeChatShop/createOrder'); // 微信商城生成订单




return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

