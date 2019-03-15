<?php

/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:17
 */

namespace app\definition;

class RedisKey{

    public static $ROOM_NUMBER_KEY_LIST = 'room_number_key_list'; # 房间号队列

    public static $USER_SESSION_INDO = 'user_session_info'; # 设置用户session的key   key中存json

    public static $APPTYPE_TOKEN = 'apptype_token_'; //验证token时存入机型,token,和登录的客户端型号。
}