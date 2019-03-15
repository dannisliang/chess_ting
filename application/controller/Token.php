<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/14
 * Time: 16:57
 */

namespace app\controller;


use app\definition\Definition;

class Token extends Base
{
    /**
     * 获取token
     * @return \think\response\Json\
     */
    public function getToken(){
        //验证必须传的参数
        $opt = ['player_id','token','client_type','app_type'];
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
        //todo 还有参数未完成
        return jsonRes( 0 );
    }
}