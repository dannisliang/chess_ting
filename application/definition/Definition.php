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
     * 资产类型
     */
    public static $USER_PROPERTY_TYPE_NOT_BINDING = 10001; # 非绑定钻石
    public static $USER_PROPERTY_TYPE_BINDING = 10002; # 绑定钻石
    public static $USER_PROPERTY_PRESIDENT = 10003; # 会长资产
    public static $PRESIDENT_REBATE = 10009; # 会长返利
    public static $USER_PROPERTY_TYPE_GOLD  = 10000; # 金币
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
     * 支付调用
     * @var string
     */
    public static $TJMAHJONG_CHESSVANS = 'https://tjmahjong.chessvans.com/h5/index.php'; //H5生成订单用（支付返回页面）
    public static $ASYNC_CALLBACK_URL = 'https://tjmahjong.chessvans.com/tianjin_mahjong/service/shop/reciveOrder.php'; //异步回调地址

}

