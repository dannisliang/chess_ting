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
use think\Env;
use think\Log;

class Mail extends Base
{

    /**
     * 获取邮件列表1
     * @param mail_type:邮件类型,limit:查看的邮件最大数量
     */
    public function lists(){
        $mail_type = $this->opt['mail_type'];//邮件的类型
        $data['appid'] = Env::get('app_id');//省份的appid
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
        $url = Env::get('web_user_url');//刘进永运营中心的域名
        $url_area = Definition::$EMAIL_LIST;//接口方法路径
        $list = sendHttpRequest($url.$url_area,$data);
        $result = array();
        if(!isset($list['data'])){
            return jsonRes(3004,$result);
        }
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
        $data['appid'] = Env::get('app_id');//省份的appid
        $data['id'] = $mail_id;
        $data['playerId'] = $player_id;
        $url = Env::get('web_user_url');//运营中心域名
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
                            if(!$vip_opt){
                                $vip_icon = '';
                            }else{
                                $vip_icon = $vip_opt[0]['icon'];
                            }

                            $new_goods[$i]['vip_icon'] = "https://obs.cn-north-1.myhuaweicloud.com"."$vip_icon";
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
                $url = Env::get('web_user_url');
                $url_area = Definition::$EMAIL_DELETE;
                $datadel['appid'] = Env::get('app_id');
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
        $data['appid'] = Env::get('app_id');//游戏的appID
        $id = $this->opt['mail_id'];
        $data['id'] = $id;//邮件的ID
        $player = getUserIdFromSession();
        $url = Env::get('web_user_url');//运营中心的域名
        if($id == '0'){
            //查询该玩家所有的邮件,然后再循环获取所有邮件的id数组
            $url_area = Definition::$EMAIL_LIST;//邮件列表
            $appid = Env::get('app_id');
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
     * 领取邮件里的物品1
     */
    public function receive(){
        $player_id = getUserIdFromSession();
        $mail_id   = $this->opt['mail_id'];
        if(!$player_id){
            return jsonRes( 9999 );
        }
        $data = [
            'appid' => Env::get('app_id'),//省份的appid;
            'id'    => $mail_id,
            'playerId' => $player_id
        ];
        $email_detail = sendHttpRequest(Env::get('web_user_url'). Definition::$EMAIL_DETAIL, $data);
        if(!isset($email_detail['data']['goods']) || empty($email_detail['data']['goods'])){
            return jsonRes(3019);
        }
        $goods_array = json_decode($email_detail['data']['goods'],true);//"{"10002":"1000"}"
//        var_dump($goods_array);die;
        foreach ($goods_array as $key=>$value){
            $keys = explode('_',$key);

            if(count($keys) == 1){
                //增加钻石或者金币的数量
                $propertyData = [
                    [
                        'uid'           => $player_id,
                        'reason_id'     => 3,
                        'change_num'    => (int)$value,
                        'event_type'    => '+',
                        'property_type' => (int)$key,
                        'property_name' => '用户邮件领取数量'
                    ]
                ];
                //添加用户资产(改成获取数据批量修改)
                $addOperateResult = operatePlayerProperty($propertyData);
                if($addOperateResult['code'] != 0){
                    return jsonRes(3011);
                }
            }elseif(count($keys) == 2){
                //添加会员卡
                $addCardResult = $this->addCard($player_id , (int)$keys[0] , (int)$keys[1] , $value);
                if(!$addCardResult){
                    return jsonRes(3010);
                }
            }
        }
        //修改邮件的状态
        $datas = [
            'appid' => Env::get('app_id'),
            'id' => (int)$mail_id,
            'receive_status' => 1,
        ];
        $result = sendHttpRequest(Env::get('web_user_url').  Definition::$UPDATE_STATU, $datas);
        if ($result['code'] == 0) {
            //删除邮件
            $datadel = [
                'appid' => $mail_id,
                'id' => $mail_id,
            ];
            sendHttpRequest(Env::get('web_user_url'). Definition::$EMAIL_DELETE, $datadel);

            $property = $this -> getUserPro($player_id);
            $send_data = [
                'content' => [
                    'gold' => (int)$property['gold'],
                    'diamond' => (int)$property['diamond'],
                ],
                'type' => 1029,
                'sender' => 0,
                'reciver' => [$player_id],
                'appid' => Env::get('app_id')
            ];
            //发送数据
            $list = sendHttpRequest(Env::get('inform_url') . Definition::$SEND,$send_data);
//            Log::write($list,'notice_error');
            return jsonRes(0);

        } else {

            return jsonRes(3004);
        }
    }

    /**
     * 领取会原卡添加会员卡的数量
     * @param $player_id
     * @param $club_id
     * @param $vip_id
     * @param $goods_num
     * @return bool|\think\response\Json\
     * @throws \think\exception\DbException
     */
    private function addCard($player_id , $club_id , $vip_id , $goods_num){
        //查询vip卡信息
        $vipCardModel = new VipCardModel();
        $vipCard = $vipCardModel->getVipCardInfoByVipCardId($vip_id);
        if($vipCard){
            $vip_level = $vipCard['type'];
        }else{
            return false;
        }

        //查看是否有会员卡
        $userVipModel = new UserVipModel();
        $userVips = $userVipModel -> getOneByWhere(['uid'=>$player_id , 'vid'=>$vip_id , 'club_id' => $club_id]);
        if($userVips){
            //更新数据
            $vip_number = $userVips['card_number'] + $goods_num;
            $update_data['card_number'] = $vip_number;
            $result =$userVipModel->updateByWhere(['uid'=>$player_id , 'vid'=>$vip_id , 'club_id' => $club_id],$update_data);
            if(!$result){
                return false;
            }
        }else{
            //添加数据
            $add_data = [
                'vid' => $vip_id,
                'uid' => $player_id,
                'club_id' => $club_id,
                'vip_status' => 0,
                'card_number' => $goods_num,
                'vip_level' => $vip_level,
                'end_day' => '0000-00-00 00:00:00',
            ];
            $result = $userVipModel->insertData($add_data);
            if (!$result){
                return false;
            }
        }
        return true;
    }

    /**
     * 获取用户的资产信息
     * @param $player_id
     */
    private function getUserPro($player_id){
        $user_propertys = getUserProperty($player_id,[10000,10001,10002]);
        if($user_propertys['code'] != 0){
            return [
                'gold' => 0,
                'diamond' => 0
            ];
        }
        $gold_nums = 0; $diamond = 0; $diamond1 = 0;
        foreach ($user_propertys['data'] as $property){
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