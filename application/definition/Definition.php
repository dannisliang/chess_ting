<?php
/**
 * Created by Xie
 * User: DELL
 * Date: 2019/3/11
 * Time: 14:11
 */

namespace app\definition;

class Definition{

    public static $WEB_API_URL  = 'http://192.168.9.18:5204/user_center/'; # 宋福旺接口
    public static $WEB_USER_URL = 'http://192.168.9.18:5204/operator_center/'; //运营中心接口
    public static $INFORM_URL   = 'http://192.168.9.18:5204/notice_center/'; //运营中心接口
    public static $ROOM_URL     = 'http://192.168.9.18:9910/'; //王朝翠的接口

    public static $CESHI_APPID  = '72110'; //测试服appid
    public static $ZHENGSHI_APPID = ''; //正式服appid
    public static $SOCKET_SSL   = ''; //未完成牌局的游戏服的ssl,


    //public static $INFORM_URL = 'http://gezeal.f3322.net:8800/notice_center/');//王朝翠的接口
    public static $NOTIFICATION_URL ='http://mp.snplay.com:5202';//通知中心服务器地址,
    public static $NOTIFICATION_H5 ='http://mp.snplay.com:5202';//H5的通知地址
    public static $SOCKET_H5    ='ws://mp.snplay.com:5201';//H5的socket地址
    public static $SOCKET_URL   ='mp.snplay.com:5200';//超哥的socket
    public static $NEED_DIAMOND ='10';//结束后扣除的钻石数
    public static $BALANCE_COIN_A ='10001';//A模式扣除钻石种类数量
    public static $BALANCE_COIN_B = '10002';//B模式扣除钻石种类数量
    public static $SERVICE_IP   = '192.168.9.18';//项目的服务器IP地址
    public static $MY_APP_NAME  ='tianjin_mahjong';//报送大数据是传送的项目名字
    public static $IS_DEBUG     = '1';//报送大数据是否为测试

    public static $USER_SESSION_INDO = 'user_session_info'; // 设置用户session的key   key中存json





    public static $CHECK_TOKEN_TIME = 'api/v3/check_token_time.php'; # 用户中心检查用户token接口请求地址
    public static $GET_PLAYER_PROPERTY = 'api/get_player_property.php'; # 用户中心获取用户资产接口
    public static $CHECK_ROOM = 'api/v3/room/checkRoom'; # 逻辑服检测房间地址
    public static $CREATE_ROOM = 'api/v3/room/createRoom/'; # 逻辑服创建房间地址
    public static $RAISE_PLAYER_PROPERTY = 'api/raise_player_property.php'; # 扣用户资产
    public static $DIS_BAND_ROOM = 'api/v3/room/disbandRoom/'; # 解绑房间


    # 资产类型
    public static $USER_PROPERTY_TYPE_BINDING = 10002; # 绑定钻石
    public static $USER_PROPERTY_TYPE_NOT_BINDING = 10001; # 非绑定钻石
    public static $USER_PROPERTY_PRESIDENT = 10003; # 会长资产

}

