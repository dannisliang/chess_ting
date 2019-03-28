<?php
/**
 * Created by PhpStorm.
 * User: 唐杨
 * Date: 2019/3/19
 * Time: 13:58
 */

namespace app\controller;
use app\definition\Definition;
use app\model\ClubModel;
use guzzle\GuzzleHttp;
use think\Session;
use app\definition\RedisKey;
class Mail extends Base
{

    /**
     *获取邮件列表
     * @param mail_type:邮件类型,limit:查看的邮件最大数量
     */
    public function lists(){
        $mail_type = $this->opt['mail_type'];//邮件的类型
        $mail_limit = $this->opt['limit'];//查询的邮件的数量
        $data['appid'] = Definition::$CESHI_APPID;//省份的appid
        $user_object = getUserSessionInfo();//获取用户详细信息
        $player_id = $user_object['uid'];//用户ID
        if($mail_type == 1){
            //1代表是自己收到的邮件列表
            $data['recipient'] = $player_id;
        }else{
            //不等于1则代表是自己发送的邮件列表
            $data['sender'] = $player_id;
        }
        $url = Definition::$WEB_USER_URL;//刘进永运营中心的域名
        $url_area = Definition::$EMAIL_LIST;//接口方法路径
        $list = guzzleRequest($url,$url_area,$data);
        $mail_num = $list['data'];
        $count = count($mail_num);
        for ($i = 0; $i < $count; $i++) {
            $result[$i]['mail_id'] = (int)$list['data'][$i]['id'];
            $result[$i]['is_read'] = (int)$list['data'][$i]['read_status'];
            if ($list['data'][$i]['sender'] == 0) {
                $result[$i]['mail_type'] = 1;
            } else {
                $result[$i]['mail_type'] = 2;
                //获取俱乐部名字
                $club_id = $list['data'][$i]['sender'];
                $club_name = getCLubName($club_id);
                $result[$i]['club_name'] = $club_name;
            }
            $result[$i]['title'] = $list['data'][$i]['title'];
            $result[$i]['date'] = strtotime($list['data'][$i]['send_time']);
            $result[$i]['sender'] = $list['data'][$i]['sender'];
            if ($list['data'][$i]['receive_status'] == 1) {
                $result[$i]['is_get_reward'] = (int)1;
            } else {
                $result[$i]['is_get_reward'] = (int)0;
            }
        }
        return jsonRes( 0 , $result);
        //调用运营中心的接口获取邮件列表
        /* //调用curl方法,获得到接口里的数据,然后传递给前台

        $data = json_encode($data);
. '/api/email_list.php'

        $list = postInterface($url, $data);
        Log::write($list,"listopt");
        $list = json_decode($list, true);
        $count = count($list['data']);
        $result = array();
        for ($i = 0; $i < $count; $i++) {
            $result[$i]['mail_id'] = (int)$list['data'][$i]['id'];
            $result[$i]['is_read'] = (int)$list['data'][$i]['read_status'];
            if ($list['data'][$i]['sender'] == 0) {
                $result[$i]['mail_type'] = 1;
            } else {
                $result[$i]['mail_type'] = 2;
                //获取俱乐部名字
                $club_id = $list['data'][$i]['sender'];
                $club_name = getCLubName($club_id);
                $result[$i]['club_name'] = $club_name;
            }
            $result[$i]['title'] = $list['data'][$i]['title'];
            $result[$i]['date'] = strtotime($list['data'][$i]['send_time']);
            $result[$i]['sender'] = $list['data'][$i]['sender'];
            if ($list['data'][$i]['receive_status'] == 1) {
                $result[$i]['is_get_reward'] = (int)1;
            } else {
                $result[$i]['is_get_reward'] = (int)0;
            }
        }
        //调用宋福旺的接口,查出uid指定的昵称
        if ($list['code'] == 0) {
            return json(['code' => 0, 'mess' => '请求成功', 'data' => $result]);
        } else {
            return json(['code' => 3004, 'mess' => '查询失败,没有查询到数据']);
        }*/
    }
    /**
     *邮件详情
     * @param mail_type:邮件类型,limit:查看的邮件最大数量
     */
    public function detail()
    {
        $mail_id = $this->opt['mail_id'];
        $user_obj = getUserSessionInfo();
        $player_id = $user_obj['uid'];
        $data['appid'] = Definition::$CESHI_APPID;//省份的appid
        $data['id'] = $mail_id;
        $data['playerId'] = $player_id;
        $url = Definition::$WEB_USER_URL;//运营中心域名
        $url_area = Definition::$EMAIL_DETAIL;//邮件详情
        $list = guzzleRequest($url,$url_area,$data);

        if ($list['code'] == 0) {
            $sender = $list['data']['sender'];//发送的俱乐部ID
            if($sender == 0){
                $email_type = 1;
            }else{
                $club_obj = new ClubModel();
                //如果不是,获取俱乐部的得名字
                $club_name =$club_obj->getClubNameById($sender);
                $email_type = 2;
                $result['club_name'] = $club_name;
            }

            if ($list['data']['content'] == '') {
                $content = '';
            } else {
                $content = $list['data']['content'];
            }
            if ($list['data']['goods'] == '') {
                $result['rewards'] = '';
            } else {
                $goods = $list['data']['goods'];
                $goods = json_decode($goods,true);
                $goods_name = array();
                $goods_counts = array();
                foreach ($goods as $k=>$v){
                    array_push($goods_name,$k);
                    array_push($goods_counts,$v);
                }
                $new_goods = array();
                $foreach_num = count($goods_name);//物品的数量
                for ($i=0;$i<$foreach_num;$i++){
                    $goods_type = $goods_name[$i];
                    if(strpos("$goods_type",'_') !== false){
                        //'包含_';
                        $opt = explode('_',"$goods_type");
                        $vip_id = $opt[1];
                        $num = count($opt);
                        if($num==3){
                            //说明是绑钻
                            $new_goods[$i]['good_name'] = 10002;
                            $new_goods[$i]['good_counts'] = $goods_counts[$i];
                        }else{
                            //说明是VIP卡
                            $new_goods[$i]['good_name'] = 10004;
                            $new_goods[$i]['vip_id'] = (int)$vip_id;
                            $new_goods[$i]['good_counts'] = $goods_counts[$i];
                            $vip_id = $opt[1];
                            //查出vip卡的图片(实例化化模型)目前没有,自己单独查询
                            $vip_opt = Db::query("SELECT icon FROM tb_vip_card WHERE vip_id = $vip_id");
                            $vip_icon = $vip_opt[0]['icon'];
                            $new_goods[$i]['vip_icon'] = "https://tjmahjong.chessvans.com//GMBackstage/public/"."$vip_icon";
                        }
                    }else{
                        $new_goods[$i]['good_name'] = $goods_name[$i];
                        $new_goods[$i]['good_counts'] = $goods_counts[$i];
                    }
                }
                $result['rewards'] = $new_goods;
            }
            $result['content'] = $content;
            $result['email_type'] = $email_type;
            $liujinyon = $list['data'];
            $read_status = $liujinyon['read_status'];
            $recive_statua = $liujinyon['receive_status'];
            if(($read_status ==1 && $recive_statua ==1) || ($read_status ==1 && $liujinyon['goods'] == '')){
                //都为1则删除
                $url = Definition::$WEB_USER_URL;
                $url_area = Definition::$EMAIL_DELETE;
                $datadel['appid'] = Definition::$CESHI_APPID;
                $datadel['id'] = $mail_id;
                guzzleRequest($url,$url_area,$datadel);
            }
            Log::write($result,'$result_$resultdetail');
            return jsonRes( 0 , $result);
        } else {
            return jsonRes( 3004 );
        }

    }
    /**
     *删除邮件
     * @param
     */
    public function delete(){
        $data['appid'] = Definition::$CESHI_APPID;//游戏的appID
        $id = $this->opt['mail_id'];
        $data['id'] = $id;//邮件的ID
        $player_opt = getUserSessionInfo();
        $player = $player_opt['uid'];
        $url = Definition::$WEB_USER_URL;//运营中心的域名
        if($id == '0'){
            //查询该玩家所有的邮件,然后再循环获取所有邮件的id数组
            $url_area = Definition::$EMAIL_LIST;//邮件列表
            $appid = Definition::$CESHI_APPID;
            $datas['appid'] = $appid;//appid
            $datas['recipient'] = $player;//玩家的ID
            $email_list = guzzleRequest($url,$url_area,$datas);//玩家的邮件列表
            $mail_list = array();
            for($i=0;$i<count($email_list['data']);$i++){
                $mail_list[$i] = $email_list['data'][$i]['id'];
            }
            $del_url = Definition::$EMAIL_DELETE_MORE;//批量删除
            $data['ids'] = $mail_list;
        }else{
            $del_url = Definition::$EMAIL_DELETE;//单独删除
        }
        $list = guzzleRequest($url,$del_url,$data);
        if ($list['code'] == 0) {
            return jsonRes(0);
        } else {
            return jsonRes(3004);
        }
    }
}