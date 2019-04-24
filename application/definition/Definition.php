<?php
/**
 * Created by Xie
 * User: DELL
 * Date: 2019/3/11
 * Time: 14:11
 */

namespace app\definition;



class Definition{

    /**
     * 请求服务器IP地址
     */
    public static $WEB_USER_URL = 'http://192.168.9.18:80/operator_center/'; //运营中心接口
    public static $WEB_API_URL  = 'http://192.168.9.18:80/user_center/'; # 宋福旺接口
    public static $INFORM_URL   = 'http://192.168.9.18:80/notice_center/'; //通知中心接口
    public static $ROOM_URL     = 'http://192.168.9.18:80/'; //王朝翠的接口

    public static $ZHENGSHI_APPID = ''; //正式服appid
    public static $CESHI_APPID  = '72110'; //测试服appid
    public static $SOCKET_SSL   = ''; //未完成牌局的游戏服的ssl,


//    public static $INFORM_URL = 'http://gezeal.f3322.net:8800/notice_center/');//王朝翠的接口
    public static $IS_DEBUG         = '1';//报送大数据是否为测试
    public static $SOCKET_H5        = 'ws://mp.snplay.com:80';//H5的socket地址
    public static $SOCKET_URL       = 'mp.snplay.com:80';//超哥的socket
    public static $SERVICE_IP       = '192.168.9.18';//项目的服务器IP地址
    public static $MY_APP_NAME      = 'tianjin_mahjong';//报送大数据是传送的项目名字
    public static $NEED_DIAMOND     = '10';//结束后扣除的钻石数
    public static $MATCH_SERVICE    = 'http://mp.snplay.com:80'; //匹配服地址 （河南专用）
    public static $NOTIFICATION_H5  = 'http://mp.snplay.com:11509';//H5的通知地址
    public static $NOTIFICATION_URL = 'http://mp.snplay.com:11509';//通知中心服务器地址,


    /**
     * 资产类型
     */
    public static $USER_PROPERTY_TYPE_NOT_BINDING = 10001; # 非绑定钻石
    public static $USER_PROPERTY_TYPE_BINDING = 10002; # 绑定钻石
    public static $USER_PROPERTY_PRESIDENT = 10003; # 会长资产
    public static $PRESIDENT_REBATE = 10009; # 会长返利
    public static $ROOMLIST_AREA = 1; # 控制房间列表显示参数

    /**
     * 用户中心接口+++++++++++++++++++++++++++++++++++++++++++++++
     */
    public static $GET_INFO             = 'api/get_info.php'; # 用户中心获取用户信息
    public static $AUTHENTICATE         = 'api/v3/authenticate.php'; # 用户中心验证token接口
    public static $CHECK_TOKEN_TIME     = 'api/v3/check_token_time.php'; # 用户中心检查用户token接口请求地址
    public static $GET_PLAYER_PROPERTY  = 'api/get_player_property.php'; # 用户中心获取用户资产接口
    public static $RAISE_PLAYER_PROPERTY= 'api/raise_player_property.php'; # 操作用户资产
    public static $RAISE_PLAYER_PROPERTY2= 'api/raise_player_property2.php'; # 批量操作用户资产
    public static $PROPERTY_CHANGE      = 'api/property_change.php'; #修改用户资产（可增加减掉）

    /**
     * 逻辑服接口
     */
    public static $DIS_BAND_ROOM = 'api/v3/room/disbandRoom/'; # 解绑房间
    public static $CHECK_ROOM   = 'api/v3/room/checkRoom'; # 逻辑服检测房间地址
    public static $CREATE_ROOM  = 'api/v3/room/createRoom/'; # 逻辑服创建房间地址
    public static $GET_USER_ROOM = 'api/v3/room/checkPlayer'; # 逻辑服获取用户房间
    public static $JOIN_ROOM = 'api/v3/room/joinRoom/'; # 逻辑服请求加入房间的接口

    /**
     * 运营中心
     */
    public static $EMAIL_LIST = 'api/email_list.php'; //运营中心获取邮件列表
    public static $EMAIL_DETAIL = '/api/email_detail.php'; //运营中心获取邮件详情
    public static $EMAIL_DELETE = '/api/email_del.php'; //运营中心删除邮件列表地址
    public static $EMAIL_DELETE_MORE = '/api/email_del_list.php';//运营中心批量删除邮件
    public static $HORSE_LAMP = '/api/horse_list.php';//运营中心获取跑马灯列表
    public static $NOTICE_LIST = '/api/notice_list.php';//公告列表
    public static $UPDATE_STATU = '/api/email_update.php';//修改邮件状态

    /**
     * 通知中心接口
     */
    public static $SEND = 'api/send.php'; //发送数据接口

    /**
     * 日志
     */
    public static $DEL_USER_ROOM = 'delUserRoom'; # 删除用户房间 参数 用户ID 房间ID
    public static $SET_USER_ROOM = 'setUserRoom'; # 写用户房间 参数 用户ID 房间ID
    public static $DEL_CLUB_ROOM = 'delClubRoom'; # 俱乐部删除房间 参数 俱乐部ID 房间ID
    public static $ADD_CLUB_ROOM = 'addClubRoom'; # 俱乐部添加房间 参数 俱乐部ID 房间ID
    public static $SET_ROOM_HASH = 'setRoomHash'; # 写房间hash数据 参数 房间ID 房间其他数据
    public static $CHANGE_ROOM_HASH = 'changeRoomHash'; # 修改房间hash数据 参数 用户ID 房间ID
    public static $CHANGE_ROOM_STATUS = 'changeRoomStatus'; # 修改房间状态 参数 房间ID
    public static $CHANGE_ROOM_PLAY = 'changeRoomPlay'; # 修改房间牌局记录 参数 房间ID
    public static $FAILED_TO_OPERATE_PROPERTY = 'failed_to_operate_property'; # 钻失败

    /**
     * obs存储的桶
     */
    public static $CHESS_RECORD_TEST = 'chess-record-test'; # 测试桶
    public static $CHESS_RECORD = 'chess-record'; # 桶
    public static $OBS_KEY = 'CJTRUPEZBC2RVGFBAB6T';
    public static $OBS_SECRET = 'hodhHpjMIIDhl6KXbpDB35bzjLFG5KisKATnigcE';
    public static $OBS_ENDPOINT = 'obs.cn-north-1.myhuaweicloud.com';

    /**
     * 支付调用
     * @var string
     */
    public static $TJMAHJONG_CHESSVANS = 'https://tjmahjong.chessvans.com/h5/index.php'; //H5生成订单用（支付返回页面）
    public static $ASYNC_CALLBACK_URL = 'https://tjmahjong.chessvans.com/tianjin_mahjong/service/shop/reciveOrder.php'; //异步回调地址


    /**
     * 助手
     * @var string
     */
    public static $ZHUSHOU_URL_TEST = 'https://record.chessvans.com:8001/records'; //助手牌局记录推送
    public static $ZHUSHOU_URL = 'https://record.chessvans.com:8000/records'; //助手牌局记录推送


}

