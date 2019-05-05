<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/2/15
 * Time: 19:07
 */

namespace app\model;


use app\definition\Definition;
use think\Env;
use think\Log;
use think\Model;

class SendBigData extends Model
{
    //$beesender = new BeeSender(CESHI_APPID,MY_APP_NAME,SERVICE_IP,IS_DEBUG);
    //$event = 'highlevel_club_rebate';//事件名字
    //$uuid = '-';
    //$context = getSendBasc($senior_president,$uuid);
    public function sendMatch($event_type,$content='',$event_name,$player_id){

        $beesender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip') ,Env::get('app_debug'));

        $uuid = '-';
        $senior_president =$player_id;
        $context = $this->getSendBasc($senior_president,$uuid);

        $context['event_type'] = $event_type;
        if($event_type){
            $context['event_type'] = $event_type;
        }
        if($event_name){
            $event ="$event_name";//事件名字
        }
        if($content){
            $context = array_merge($context,$content);
        }
        $beesender->send($event,$context);
        Log::write($context,'$context_$context');
    }

    function getSendBasc($player,$uuid){
        $context = array();
        $server_id = '-';
        $context['server_id'] = '-';//区服ID
        $context['user_id'] = $player;//玩家ID,待定
        $context['role_id'] = "$server_id".'_'."$player";//角色ID,没有就是serviceid_userid
        $user_nickename = backNickname($player);
        $context['role_name'] = $user_nickename;//角色名称
        $context['client_id'] = '-';//设备的uuid
        $context['client_type'] = '-';//设备端应用类型
        $unknown = 'unknown';
        $ip = $this->getUserIp($unknown);
        $context['ip'] = $ip;//用户ip
        $context['uuid'] = $uuid;//uuid
        return $context;
    }

    function getUserIp($unknown){
        $ip = '';
        if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (false !== strpos($ip, ',')){
            $ip = reset(explode(',', $ip));
        }
        return $ip;
    }
}