<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/14
 * Time: 16:57
 */

namespace app\controller;


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

        if(!isset($result['result']) || $result['result'] === false){
            return jsonRes(9999);
        }
        $user_info = getUserBaseInfo($this->opt['player_id']);
        //没有查到用户信息验证不通过
        if(!$user_info || empty($user_info['nickname'])){
            return jsonRes(3002);
        }
        //配置默认的头像地址
        if(empty($user_info['headimgurl'])){
            $user_info['headimgurl'] = 'http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56v...avHiaiceqxibJxCfHe/0';
        }
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
        if(empty($user_data)){
            return jsonRes(3018);
        }
        Session::set(RedisKey::$USER_SESSION_INFO, $user_data);
        return jsonRes( 0 ,[
            'session_id' => session_id(),
            'curent_time'=> time()
        ]);
    }
}