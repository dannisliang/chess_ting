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
        $player_id = getUserIdFromSession();
        $mail_id   = $this->opt['mail_id'];
        $player_id = 330289;
        if(!$player_id){
            return jsonRes( 9999 );
        }
        $data = [
            'appid' => (int)Definition::$CESHI_APPID,//省份的appid;
            'id'    => $mail_id,
            'playerId' => $player_id
        ];
        $email_detail = sendHttpRequest(Definition::$WEB_USER_URL . Definition::$EMAIL_DETAIL, $data);
        $goods_array = json_decode($email_detail['data']['goods'],true);

        $goods_name = array();$goods_counts = array();
        foreach ($goods_array as $k=>$v){
            array_push($goods_name,$k);
            array_push($goods_counts,$v);
        }
        $upinfo = array();
        $user_vip = new UserVipModel();//实例化
        $i = 0;
        foreach ($goods_name as $key => $item){
            $upinfo['uid'] = $player_id;
            $upinfo['property_type'] = $item;
            $upinfo['property_num'] = $goods_counts[$key];
            $upinfo['app_id'] = (int)Definition::$CESHI_APPID;
            $goods_type = $item;
            $goods_num = $goods_counts[$key];
            //判断物品的类型
            if(strpos("$goods_type",'_') !== false ){
                //包含'_';
                $opt = explode('_',"$goods_type");
                $num = count($opt);
                $club_id = $opt[0];
                $vip_id = $opt[1];
                if($num == 2){
                    //$num=2,说明是VIP卡
                    array_splice($upinfo, $i, 1);
                    $vip_card_opt = new VipCardModel();
                    $tb_vip_card = $vip_card_opt->getVipCardInfoByVipCardId($vip_id);
                    if($tb_vip_card){
                        $tb_vip_card = json_decode($tb_vip_card,true);
                        $vip_level = $tb_vip_card['type'];
                    }else{
                        return json(['code'=>3004,'mess'=>'查询不到数据']);
                    }
                    //判断玩家在该俱乐部是否有vip的信息记录
                    $result = $user_vip->getOneByWhere(['uid'=>$player_id , 'vid'=>$vip_id , 'club_id' => $club_id],'vid,card_number');
                    if($result){
                        $result = json_decode($result,true);
                        $vip_number = $result['card_number']+$goods_num;
                        //更新数据
                        $update_data['card_number'] = $vip_number;
                        $user_vip->updateByWhere(['uid'=>$player_id , 'vid'=>$vip_id , 'club_id' => $club_id],$update_data);
                    }else{
                        $add_data = [
                            'vid' => $vip_id,
                            'uid' => $player_id,
                            'club_id' => $club_id,
                            'vip_status' => 0,
                            'card_number' => $goods_num,
                            'vip_level' => $vip_level,
                            'end_day' => '0000-00-00 00:00:00',
                        ];
                        $result = $user_vip->insertData($add_data);
                        if (!$result){
                            return jsonRes(3003);
                        }
                    }
                }
            }
            $i++;
        }
        //调用宋哥的接口,批量修改用户资产(因为一个邮件里面可以有多种游戏币类型)
//        $all_data['upinfo'] = $upinfo;
//        $all_list = sendHttpRequest(Definition::$WEB_API_URL . Definition::$RAISE_PLAYER_PROPERTY2,$all_data);
        //修改邮件的状态
        $datas = [
            'appid' => (int)Definition::$CESHI_APPID,
            'id' => $mail_id,
            'receive_status' => 1,
        ];
        $result = sendHttpRequest(Definition::$WEB_USER_URL .  Definition::$UPDATE_STATU, $datas);
        if ($result['code'] == 0) {
            //删除邮件
            $datadel = [
                'appid' => $mail_id,
                'id' => $mail_id,
            ];
            $a = sendHttpRequest(Definition::$WEB_USER_URL . Definition::$EMAIL_DELETE, $datadel);

            //发送通知
            $property = $this -> getUserPro($player_id);
            $send_data = [
                'content' => [
                    'gold' => (int)$property['gold'],
                    'diamond' => (int)$property['diamond'],
                ],
                'type' => 1029,
                'sender' => 0,
                'reciver' => [$player_id],
                'appid' => (int)Definition::$CESHI_APPID
            ];

            $list = sendHttpRequest(Definition::$ROOM_URL . Definition::$SEND,$send_data);

            return jsonRes(0);

        } else {
            return jsonRes(3004);
        }
    }

    /**
     * 获取用户的资产信息
     * @param $player_id
     */
    private function getUserPro($player_id){
        $user_propertys = getUserProperty($player_id,[10000,10001,10002]);
        $gold_nums = 0; $diamond = 0; $diamond1 = 0;
        foreach ($user_propertys as $user_property){
            $property = $user_property['data'];
            switch ($property['property_type']){
                case 10000:
                    $gold_nums += $property['property_num'];
                    break;
                case 10001:
                    $diamond += $property['property_num'];
                    break;
                case 10002:
                    $diamond1 += $property['property_num'];
                    break;
                default:
                    break;
            }
        }
        $user_diamond = $diamond1+$diamond;

        return [
            'gold' => $gold_nums,
            'diamond' => $user_diamond
        ];
    }
}