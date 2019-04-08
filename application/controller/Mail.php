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
use app\model\UserVipModel;
use app\model\VipCardModel;
use think\Db;
class Mail extends Base
{


    /**
     * 获取邮件列表
     * @param mail_type:邮件类型,limit:查看的邮件最大数量
     */
    public function lists(){
        $mail_type = $this->opt['mail_type'];//邮件的类型
        $data['appid'] = Definition::$CESHI_APPID;//省份的appid
        $player_id = getUserIdFromSession();//用户ID

        if(!$player_id){
            return jsonRes( 9999 );
        }
        if($mail_type == 1){
            //1代表是自己收到的邮件列表
            $data['recipient'] = $player_id;
        }else{
            //不等于1则代表是自己发送的邮件列表
            $data['sender'] = $player_id;
        }
        $url = Definition::$WEB_USER_URL;//刘进永运营中心的域名
        $url_area = Definition::$EMAIL_LIST;//接口方法路径
        $list = sendHttpRequest($url.$url_area,$data);
        $mail_num = $list['data'];
        $count = count($mail_num);
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
        if($result){
            return jsonRes( 0,$result );
        }
        return jsonRes( 3004,$result );

    }

    /**
     * 邮件详情
     * @param mail_type:邮件类型,limit:查看的邮件最大数量
     */
    public function detail()
    {
        /*$sess = ['player_id' => 552610, 'headimgurl' => 'www.a.com', 'nickname' => 'xie', 'ip' => '192.168.1.1', 'token' => 'aaa', 'sex' => '1'];
        Session::set(RedisKey::$USER_SESSION_INFO, $sess);*/
        $mail_id = $this->opt['mail_id'];
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes( 9999 );
        }
        $data['appid'] = (int)Definition::$CESHI_APPID;//省份的appid
        $data['id'] = $mail_id;
        $data['playerId'] = $player_id;
        $url = Definition::$WEB_USER_URL;//运营中心域名
        $url_area = Definition::$EMAIL_DETAIL;//邮件详情
        $list = sendHttpRequest($url.$url_area, $data);

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
                sendHttpRequest($url.$url_area,$datadel);
            }
            return jsonRes( 0 , $result);
        } else {
            return jsonRes( 3004 );
        }

    }

    /**
     * 删除邮件
     * @param
     */
    public function delete(){
        $data['appid'] = Definition::$CESHI_APPID;//游戏的appID
        $id = $this->opt['mail_id'];
        $data['id'] = $id;//邮件的ID
        $player = getUserIdFromSession();
        $url = Definition::$WEB_USER_URL;//运营中心的域名
        if($id == '0'){
            //查询该玩家所有的邮件,然后再循环获取所有邮件的id数组
            $url_area = Definition::$EMAIL_LIST;//邮件列表
            $appid = Definition::$CESHI_APPID;
            $datas['appid'] = $appid;//appid
            $datas['recipient'] = $player;//玩家的ID
            $email_list = sendHttpRequest($url.$url_area, $datas);//玩家的邮件列表
            $mail_list = array();
            for($i=0;$i<count($email_list['data']);$i++){
                $mail_list[$i] = $email_list['data'][$i]['id'];
            }
            $del_url = Definition::$EMAIL_DELETE_MORE;//批量删除
            $data['ids'] = $mail_list;
        }else{
            $del_url = Definition::$EMAIL_DELETE;//单独删除
        }
        $list = sendHttpRequest($url.$del_url,$data);
        if ($list['code'] == 0) {
            return jsonRes(0);
        } else {
            return jsonRes(3004);
        }
    }

    /*
     * 领取邮件里的物品
     */
    public function receive(){
        $mail_id = $this->opt['mail_id'];
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes( 9999 );
        }
        $appid = (int)Definition::$CESHI_APPID;//省份的appid;
        $data['appid'] = $appid;
        $data['id'] = $mail_id;
        $data['playerId'] = $player_id;
        $url = Definition::$WEB_USER_URL;//运营中心域名
        $url_area = Definition::$EMAIL_DETAIL;//邮件详情
        $email_detail = sendHttpRequest($url.$url_area, $data);

        $goods_array = $email_detail['data']['goods'];
        $goods_array = json_decode($goods_array,true);
        $goods_name = array();
        $goods_counts = array();
        foreach ($goods_array as $k=>$v){
            array_push($goods_name,$k);
            array_push($goods_counts,$v);
        }
        $upinfo = array();
        $user_vip = new UserVipModel();//实例化
        for ($i=0;$i<count($goods_name);$i++){
            $upinfo[$i]['uid'] = $player_id;
            $goods_type = $goods_name[$i];
            $upinfo[$i]['property_type'] = $goods_name[$i];
            $goods_num = $goods_counts[$i];
            $upinfo[$i]['property_num'] = $goods_counts[$i];
            $upinfo[$i]['app_id'] = $appid;
            //判断物品的类型
            if(strpos("$goods_type",'_') !== false ){
                //包含'_';
                $opt = explode('_',"$goods_type");
                $num = count($opt);
                $club_id = $opt[0];
                $vip_id = $opt[1];
                if($num == 2){
                    //$num=2,说明是VIP卡
                    $vip_card_opt = new VipCardModel();
                    $tb_vip_card = $vip_card_opt->getVipCardInfoByVipCardId($vip_id);
                    if($tb_vip_card){
                        $tb_vip_card = json_decode($tb_vip_card,true);

                        $vip_level = $tb_vip_card['type'];
                    }else{
                        return json(['code'=>3004,'mess'=>'查询不到数据']);
                    }
                    //判断玩家在该俱乐部是否有vip的信息记录
                    $where['uid'] = $player_id;
                    $where['vid'] = $vip_id;
                    $where['club_id'] = $club_id;
                    $field = 'vid,card_number';
                    $result = $user_vip->getOneByWhere($where,$field);
                    if($result){
                        $result = json_decode($result,true);
                        $vip_number = $result['card_number']+$goods_num;
                        //更新数据
                        $update_data['card_number'] = $vip_number;
                        $user_vip->updateByWhere($where,$update_data);
                    }else{
                            $add_data['vid'] = $vip_id;
                            $add_data['uid'] = $player_id;
                            $add_data['club_id'] = $club_id;
                            $add_data['vip_status'] = 0;
                            $add_data['card_number'] = $goods_num;
                            $add_data['vip_level'] = $vip_level;
                            $a = $user_vip->insertData($add_data);
                    }
                }
            }
        }
        //调用宋哥的接口,批量修改用户资产(因为一个邮件里面可以有多种游戏币类型)
        $url = Definition::$WEB_API_URL;
        $url_area = Definition::$RAISE_PLAYER_PROPERTY2;
        $all_data['upinfo'] = $upinfo;//批量去查
        $all_list = sendHttpRequest($url.$url_area,$all_data);


        //修改邮件的状态
        $update_url = Definition::$WEB_USER_URL;
        $update_url_area = Definition::$UPDATE_STATU;
        $datas['appid'] = $appid;
        $datas['id'] = $mail_id;
        $datas['receive_status'] = 1;
        $result = sendHttpRequest($update_url.$update_url_area, $datas);
        if ($all_list['code'] ==0 && $result['code'] == 0) {
            //删除邮件
            $deleurl = Definition::$WEB_USER_URL;
            $deleurl_area = Definition::$EMAIL_DELETE;
            $datadel['appid'] = $mail_id;
            $datadel['id'] = $mail_id;
            $a = sendHttpRequest($deleurl.$deleurl_area, $datadel);

            //发送通知
            $user_opt = getUserProperty($player_id,10000);
            if($user_opt){
                $gold_nums = $user_opt[0]['property_num'];
            }else{
                $gold_nums = 0;
            }
            $user_opt1 = getUserProperty($player_id,10001);
            if($user_opt1){
                $diamond = $user_opt1[0]['property_num'];
            }else{
                $diamond = 0;
            }
            $user_opt2 = getUserProperty($player_id,10002);
            if($user_opt2){
                $diamond1 = $user_opt2[0]['property_num'];
            }else{
                $diamond1 = 0;
            }
            $recive_user[0] = $player_id;
            $user_diamond = $diamond1+$diamond;
            $send_data['content']['gold'] = (int)$gold_nums;
            $send_data['content']['diamond'] = (int)$user_diamond;
            $send_data['type'] = 1029;
            $send_data['sender'] = 0;
            $send_data['reciver'] = $recive_user;
            $send_data['appid'] = (int)$appid;
            //$send_url = INFORM_URL.'api/send.php';
            $send_url = Definition::$ROOM_URL;
            $send_url_area = Definition::$SEND;
            $list = sendHttpRequest($send_url.$send_url_area,$send_data);

            return jsonRes(0);

        } else if ($result['code'] == 2003) {
            return jsonRes(3004);
        } else {
            return jsonRes(3004);
        }
    }
}