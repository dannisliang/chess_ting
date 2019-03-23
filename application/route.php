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
Route::rule('service/club/getClubInfo','club/getClubInfo');

/**
 * 房间相关
 */
Route::rule('service/room/createRoom','Room/createRoom');
Route::rule('service/room/disBandRoom','Room/disBandRoom');
Route::rule('service/room/joinRoom','Room/joinRoom');
/**
 * 邮件相关
 */
Route::rule('service/getMailList','mail/lists');
Route::rule('service/GetMailDetail','mail/detail');
Route::rule('service/Mail/delete','mail/delete');
/*领取邮件里附加你接口地址service/reciveGoods*/
/*跑马灯*/
Route::rule('service/getBulletinList','HorseLamp/lists');
/*公告*/
Route::rule('service/getnotice','notice/lists');
return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

];

