<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/11
 * Time: 12:00
 */

namespace app\controller;


use GuzzleHttp\Client;

class User extends Base
{
    public function getUserInfo(){
        session('player_id',328946);
        //获取运营中心接口
        $email_url = 'api/email_list.php';
        $user_id = session('player_id'); //收件人id
        $email_data = [
            'recipient' => $user_id,
            'appid'     => CESHI_APPID
        ];
        $client = new Client(['base_uri'=> WEB_USER_URL]);
        $res = $client -> request('post',$email_url,['json'=>$email_data]);
        echo '<pre/>';
        print_r(json_decode($res->getBody()->getContents(), true));die;

    }
}