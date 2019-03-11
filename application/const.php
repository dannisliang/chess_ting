<?php
namespace app;
/**
 * 定义常量目录文件.
 * User: 杨腾飞
 * Date: 2019/3/11
 */
define('WEB_API_URL','http://192.168.9.18:5204/user_center/');//宋福旺接口
define('WEB_USER_URL','http://192.168.9.18:5204/operator_center/');//运营中心接口
define('INFORM_URL','http://192.168.9.18:5204/notice_center/');//王朝翠的接口
define('ROOM_URL','http://192.168.9.18:9910/');

//define('INFORM_URL','http://gezeal.f3322.net:8800/notice_center/');//王朝翠的接口
define('CESHI_APPID',72110);//测试服appid
define('ZHENGSHI_APPID','');//测试服appid
define('SOCKET_SSL','');//未完成牌局的游戏服的ssl,
define('NOTIFICATION_URL','http://mp.snplay.com:5202');//通知中心服务器地址,
define('NOTIFICATION_H5','http://mp.snplay.com:5202');//H5的通知地址
define('SOCKET_H5','ws://mp.snplay.com:5201');//H5的socket地址
define('SOCKET_URL','mp.snplay.com:5200');//超哥的socket
define('NEED_DIAMOND','10');//结束后扣除的钻石数
define('BALANCE_COIN_A','10001');//A模式扣除钻石种类数量
define('BALANCE_COIN_B','10002');//B模式扣除钻石种类数量
define('SERVICE_IP','192.168.9.18');//项目的服务器IP地址
define('MY_APP_NAME','tianjin_mahjong');//报送大数据是传送的项目名字
define('IS_DEBUG','1');//报送大数据是否为测试
