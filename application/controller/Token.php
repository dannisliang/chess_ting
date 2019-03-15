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

        //验证传输的token是否可靠
        $url = Definition::$WEB_API_URL;
        $pathInfo = 'api/v3/authenticate.php';
        $data = [
            'uid'=> $this->opt['player_id'],
            'ip' => $ip,
            'token'=> $this->opt['token']
        ];

        $result = guzzleRequest( $url , $pathInfo , $data );

        if($result['result'] === false){
            return jsonRes(3002);
        }
        //验证完成的信息存入session
        Session::set(RedisKey::$USER_SESSION_INDO . $this->opt['player_id'],$this->opt);
        return jsonRes( 0 ,[
            'session_id' => session_id(),
            'curent_time'=> time()
        ]);
    }
}