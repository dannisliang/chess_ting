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

