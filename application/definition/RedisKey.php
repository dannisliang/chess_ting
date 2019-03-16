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
}