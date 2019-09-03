<?php

/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\definition;

class RedisKey{

    public static $ROOM_NUMBER_KEY_LIST = 'room_number_key_list'; # 房间号list 右出左进原子性操作

    public static $USER_SESSION_INFO = 'user_session_info'; # 设置用户session的key   key中存json

    public static $USER_ROOM_KEY_HASH = 'user_room_key_hash:'; # 用户房间hash

    public static $CLUB_ALL_ROOM_NUMBER_SET = 'club_all_room_number_set_'; # 俱乐部所有房间号set

    public static $USER_ROOM_KEY = 'user_room_'; # 用户房间对应关系

    public static $USE_VIP_CARD = 'use_vip_card'; # 使用vip卡加锁

    public static $GATEWAY_CACHE = 'gateway_cache:'; // 网关缓存

    public static $OPEN_SERVICE_CACHE = 'open_service_cache'; // 开启的服务的缓存

    public static $USED_ROOM_NUM = 'used_room_num'; // 开启的服务的缓存

    public static $USER_INFO = 'user_info_'; // 用户的资料

    public static $USER_ROOM_RECORD = 'user_room_record:'; // 用户房间记录

    public static $PLAY_BACK = 'play_back:'; // 用户房间记录
}