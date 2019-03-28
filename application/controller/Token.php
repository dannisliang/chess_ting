<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/14
 * Time: 16:57
 */

namespace app\controller;


use app\definition\Definition;
use app\definition\RedisKey;
use think\Log;
use think\Session;

class Token extends Base
{
    /**
     * 获取token
     * @return \think\response\Json\
     */
    public function getToken(){
        //验证必须传的参数
        $opt = ['player_id','token','client_type','app_type'];// uid token 机型 app还是h5
        if(!has_keys($opt,$this->opt,true)){
            return jsonRes(3006);
        }

        //获取ip
        $unknown = 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (false !== strpos($ip, ',')) {
            $ip = reset(explode(',', $ip));
        }

        //验证token
        $data = [
            'ip'    => $ip,
            'token' => $this->opt['token'],
            'uid'   => $this->opt['player_id'],
        ];
        $result = checkToken( $data );

        if($result['result'] === false){
            return jsonRes(3002);
        }
        $user_info = getUserBaseInfo($this->opt['player_id']);
        //验证完成的信息存入session
        $user_data = [
            'client_type'   => $this -> opt['client_type'],
            'player_id'     => $this -> opt['player_id'],
            'app_type'      => $this -> opt['app_type'],
            'token'         => $this -> opt['token'],
            'ip'            => $ip,
            'sex'           => $user_info['sex'],
            'userid'        => $this -> opt['player_id'],
            'headimgurl'    => $user_info['headimgurl'],
            'nickname'      => $user_info['nickname'],
        ];
        Session::set(RedisKey::$USER_SESSION_INFO, $user_data);
        return jsonRes( 0 ,[
            'session_id' => session_id(),
            'curent_time'=> time()
        ]);
    }
}