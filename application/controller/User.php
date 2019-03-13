<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/11
 * Time: 12:00
 */

namespace app\controller;


use app\definition\Definition;
use app\model\GetLastClub;
use app\model\UserLastClubModel;
use guzzle\GuzzleHttp;
class User extends Base
{
    public function getUserInfo()
    {
        //实例化model
        $lastClubModel = new UserLastClubModel();
        session('player_id',328946);
        $user_id    = session('player_id'); //收件人id
        //获取未读取邮件数量
        $email_num  = $this ->getEmailNum($user_id);
        //获取用户基本信息
        $user_info  = $this -> getUserBaseInfo($user_id);
        //获取用户电话
        $phone_num  = isset($user_info['tel_number']) ? $user_info['tel_number'] : '' ;
        //获取是否显示招募代理入口参数
        $is_open    = $this -> isShowAgentEntrance();
        //获取上次登录的俱乐部id
        $lastClub = $lastClubModel -> getLastClubId($user_id);
        $club_id = $lastClub['data']['club_id'];

        $result = [
            'phone_num' => $phone_num,
            'agent_recruit'=>$is_open,
            'player_id'=> $user_id,
            'new_mail' => $email_num,
            'nickname' => $user_info['nickname'],
            'head_img' => $user_info['headimgurl'],

        ];
        return jsonRes( 0 , $result);
//        echo '<pre/>';
//        print_r($result);die;
    }

    /**
     * 显示是否显示招募代理入口
     * @return mixed
     */
    private function isShowAgentEntrance()
    {
        //是否显示招募代理入口
        $file_path = __DIR__ . "/../../application/open_recruit.php";
        $str = file_get_contents($file_path);//将整个文件内容读入到一个字符串中
        $str = str_replace("\r\n","<br />",$str);
        $str = json_decode($str,true);
        return $str['is_open'];
    }

    /**
     * 获取用户的基本信息
     * @param $user_id
     * @return mixed
     */
    private function getUserBaseInfo($user_id)
    {
        //请求用户中心接口地址
        $url = Definition::$WEB_API_URL;
        //实例化guzzle
        $client = new GuzzleHttp( $url );
        //获取用户中心接口路径
        $userInfo_url = 'api/get_info.php';
        //向用户中心传输的请求参数
        $data = [
            'uid' => $user_id,
            'app_id'=> Definition::$CESHI_APPID,
        ];

        $result = $client -> getBodyContent($userInfo_url,$data);

        return $result;
    }

    /**
     * 获取邮件列表信息
     * @param $user_id
     * @return mixed
     */
    private function getEmailNum($user_id)
    {
        //请求运营中心接口地址
        $url = Definition::$WEB_USER_URL;
        //实例化guzzle
        $client = new GuzzleHttp( $url );

        //获取运营中心接口
        $email_url = 'api/email_list.php';

        //请求email需要的数据
        $email_data = [
            'appid'         => Definition::$CESHI_APPID,
            'recipient'     => $user_id,
            'read_status'   => 0
        ];

        $result = $client -> getBodyContent($email_url,$email_data);

        return count($result);
    }
}