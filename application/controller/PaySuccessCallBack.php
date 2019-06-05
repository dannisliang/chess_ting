<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/25
 * Time: 10:58
 */

namespace app\controller;


use app\definition\Definition;
use app\model\BeeSender;
use app\model\ClubModel;
use app\model\ClubShopModel;
use app\model\ClubVipModel;
use app\model\CommerceModel;
use app\model\OrderModel;
use app\model\UserVipModel;
use app\model\VipCardModel;
use think\Db;
use think\Log;
use think\Env;

class PaySuccessCallBack
{
    /**
     * 支付的回调
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function receiveOrder(){
        $opt = ['cp_order_id','sign','pay_type','fee','sign','app_id'];
        $sign_data = $_GET;
        if(!has_keys($opt,$sign_data)){
            return json(['result'=>3]); //缺少参数
        }
        $sign = $_GET['sign'];
        $pay_type = $_GET['pay_type'];
        unset($sign_data['sign']);
        //验证签名是否合法
        $key_sign = $this ->get_sign($sign_data,Env::get('sign'));
        if($key_sign != $sign){
            Log::write($key_sign . '----' . $sign ,'sign_error');
            return json(['result'=>3]); //签名不合法
        }

        //查出订单的详细信息
        $orderModel = new OrderModel();
        $order = $orderModel -> getOneByWhere(['id' => $sign_data['cp_order_id']] , 'id,vip_id,order_status,fee,product_id,product_amount,player_id,club_id,system_type,client_type,type');
        if(!$order){
            return json(['result' => 3]); //没有订单信息
        }
        //订单状态已经改变
        if($order['order_status'] == 1 || $order['order_status'] == 2){
            return json(['result' => 3]);
        }
        //判断订单类型 是买卡的还是买钻石的
        if(!empty($order['vip_id'])){
            //获取会员卡信息(会长返利)
            $result = $this -> buyVipCard($order,$pay_type);

            if($result != 1){
                Log::write($result , 'buy_vip_card_error'); //根据不同的返回码确认报错信息
                return json(['result'=>3]);
            }
            //请求成功
            return json(['result'=>1]);
        }elseif(!empty($order['product_id'])){
            $result = $this ->buyDiamond($order,$pay_type);
            if($result !=1){
                Log::write($result , 'buy_diamond_error'); //根据不同的返回码确认报错信息
                return json(['result'=>3]);
            }
            //请求成功
            return json(['result' => 1]);
        }else{
            return json(['result' => 3]); //都不存在
        }

    }

    /**
     * 玩家买钻石
     * @return bool
     * @throws \think\exception\DbException
     */
    private function buyDiamond($order,$pay_type){
        //玩家买钻回调
        $clubShopModel = new ClubShopModel();
        $orderModel = new OrderModel();
        $clubShop = $clubShopModel -> getOneByWhere(['id'=>$order['product_id']]);
        if(!$clubShop){
            return -1;
        }
        $data = [
            [
                'uid' => $order['player_id'],
                'property_type' => 10001,
                'change_num' => $clubShop['goods_number'], //购买数量
                'event_type' => '+',
                'reason_id' => 4,
                'property_name' => '玩家购买钻石数量'
            ],
            [
                'uid' => $order['player_id'],
                'property_type' => 10002,
                'change_num' => $clubShop['give'], //赠送数量数量
                'event_type' => '+',
                'reason_id' => 4,
                'property_name' => '玩家购买钻石赠送数量'
            ]
        ];
        //操作用户钻石增减
        $operateRes = operatePlayerProperty($data);
        if($operateRes['code'] != 0){
            //操作失败
            return -2;
        }
        //向客户端发送消息
        $user_property = getUserProperty($order['player_id'],[10000,10001,10002]);
        if($user_property['code'] !==0){
            return -3;
        }
        $gold = 0;$buyDiamond = 0;//购买钻
        $freeDiamond = 0; //赠送钻
        foreach ($user_property['data'] as $value){
            switch ($value['property_type']){
                case 10000: //金币
                    $gold += $value['property_num'];
                    break;
                case 10001: //购买蓝钻
                    $buyDiamond += $value['property_num'];
                    break;
                case 10002: //赠送蓝钻
                    $freeDiamond += $value['property_num'];
                    break;
                default:
                    break;
            }
        }
        //给客户端发送一条数据
        $content = [
            'diamond' => $buyDiamond + $freeDiamond,
            'gold'    => $gold,
        ];
        $reciver = [ $order['player_id']];
        $sendResult = $this -> sendToClient($content , $reciver);
        Db::startTrans();
        try{
            if($sendResult['code'] == 0){
                $orderModel -> setFieldByWhere(['id' => $order['id']],['notify_status' => 1]);
            }else{
                $orderModel -> setFieldByWhere(['id' => $order['id']],['notify_status' => 2]);
            }
            //修改订单状态
            $orderModel -> setFieldByWhere(['id' => $order['id']],['order_status' => 1]);

            //支付类型转换
            $type_num = $this -> getPayType($pay_type);
            $res = $orderModel ->setFieldByWhere(['id'=> $order['id']] , ['pay_type'=>$type_num]);
            if(!$res){
                Db::rollback();
                return -4;
            }
            //报送大数据(修改成功)
            $this ->buyDiamondSendBeeSend($order , $pay_type , $buyDiamond + $freeDiamond,'success');
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            return -5;
        }

        return 1;
    }

    /**
     * 购买钻报送大数据
     */
    private function buyDiamondSendBeeSend($order,$pay_type,$diamondNum,$pay_result){
        $content = [
            'order_id'    => $order['id'],//订单号
            'real_amount' => $order['fee'], //订单金额 (单位/分)
            'pay_channel' => 'user', //购买渠道
            'pay_result'  => $pay_result, //支付结果
            'currency'    => 'cny', //币种
            'pay_type'    => $pay_type, //支付类型
            'props_id'    => $order['product_id'], //道具id（获取道具id）
            'props_name'  => 'diamond', //道具名称
            'props_num'   => $order['product_amount'], //道具数量
            'current_num' => $diamondNum, //获取道具后所拥有的数量(变更后蓝钻总数（购买+赠送）)
        ];
        $clubInfo = getClubNameAndAreaName($order['club_id']);
        $baseInfo = getBeeBaseInfo();
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
        if(!$baseInfo){
            $baseInfo = [
                'ip '       => $ip,  //事件发生端iP
                'user_id'   => $order['player_id'],  //用户id
                'role_id'   => '-' . '_' . $order['player_id'],  //角色id，若没有即为serverid_userid
                'role_name' => backNickname($order['player_id']),  //昵称
                'client_id' => '-',  //设备的UUID（可传-号）
                'server_id' => '-',  //区服id ，服务器为服务器的网元id（可传减号）
                'system_type'=> $order['system_type'], //操作系统
                'client_type'=> $order['client_type'], //设备端应用类型
            ];
        }
//        Log::write($baseInfo , 'Base_info_error');
        $contents = array_merge($content,$clubInfo,$baseInfo);
        $this -> beeSend('recharge_finish' , $contents);
    }

    /**
     * 发送大数据
     * @param $event_name
     * @param $content
     */
    private function beeSend($event_name , $content){
        $beeSend = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip') ,Env::get('app_debug'));
        $result = $beeSend ->send($event_name , $content);
        if(!$result){
            Log::write($result , 'paySuccessCallBackBeeSenderError');
        }
    }

    /**
     * 如果使用会员卡--记录
     * @param $order
     * @return bool
     * @throws \think\exception\DbException
     */
    private function buyVipCard($order,$pay_type){
        //获取会员卡的库存和价格信息
        $clubVipModel = new ClubVipModel();
        $orderModel   = new OrderModel();
        $clubVip      = new ClubVipModel();

        $club_vip_info = $clubVip -> getOneByWhere(['club_id' => $order['club_id']]);
        if(!$club_vip_info){
	    Log::write(1,'buyerr');
            return -1;
        }
        $price = $club_vip_info['pricing'];
        //获取会员卡信息
        $vipCard = new VipCardModel();
        $vip_card_info = $vipCard -> getOneByWhere(['vip_id'=>$order['vip_id']]);
        if(!$vip_card_info){
	    Log::write(2,'buyerr');
            return -1;
        }

        if(!$price){
            $price = $vip_card_info['price'];
        }
        //往用户tb_user_vip表里加数据
        //先查看用户有没有这张卡,有的话修改,没有的话增加
        $userVipModel = new UserVipModel();
        $where = [
            'uid' => $order['player_id'],
            'vid' => $order['vip_id'],
            'club_id' => $order['club_id']
        ];
        $user_vip = $userVipModel -> getOneByWhere($where);
        if($user_vip){
            $data = [
                'card_number' => $user_vip['card_number'] +1, //vip卡数量
                'vip_level'   => $vip_card_info['type'],
            ];
            $res = $userVipModel ->updateByWhere($where,$data);
            if(!$res){
		Log::write(3,'buyerr');
                return -1; //更新失败
            }
        }else{
            $data = [
                'vid' => $order['vip_id'],
                'uid' => $order['player_id'],
                'club_id' => $order['club_id'],
                'vip_status' => 0,
                'card_number' => 1,
                'vip_level' => $vip_card_info['type'],
            ];
            $result = $userVipModel -> insertData($data);
            if(!$result){
		Log::write(4,'buyerr');
                return -1; //插入数据失败
            }
        }
        //会长和高级会长分成
        $wageResult = $this ->clubSeniorWage($order,$club_vip_info,$price);
        if($wageResult != 1){
	    Log::write(5,'buyerr');
            return $wageResult;
        }

        $userVip = $userVipModel -> getOneByWhere($where); //查询会员卡数量(报送大数据专用)
        //支付类型转换
        $type_num = $this -> getPayType($pay_type);
        Db::startTrans();
        try{
            //减少库存
            $clubVipModel -> setDecByWhere(['vid'=>$order['vip_id'],'club_id'=>$order['club_id']],'number');
            //修改订单状态和支付类型
            $orderModel -> setFieldByWhere(['id'=>$order['id']],['order_status'=>1,'pay_type'=>$type_num]);
            //报送大数据（修改成功）
            $this -> buyVipCardSendBeeSend($order,$pay_type,$userVip['card_number'],$vip_card_info['name'],'success');
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            $orderRes = $orderModel -> setFieldByWhere(['id'=>$order['id']],['order_status'=>2,'pay_type'=>$type_num]);
            if(!$orderRes){
                Log::write('修改订单状态失败','update_order_status_error');
            }
            Log::write($e->getMessage(),'update_order_error');
            return -1;
        }
        //给客户端发送一条数据
        $content = ['vip_card' => 1];
        $reciver = [ $order['player_id']];
        $this -> sendToClient($content , $reciver);
        return 1; //操作成功
    }

    /**
     * 发送一条数据给客户端
     * @param $content
     * @param $reciver
     * @return mixed
     */
    private function sendToClient($content , $reciver){
        //给客户端发送一条数据
        $notice_data = [
            'appid' => Env::get('app_id'),
            'content' => $content,
            'reciver' =>$reciver,
            'sender'  => 0,
            'type'    => 1029
        ];
        $res = guzzleRequest(Env::get('inform_url') , Definition::$SEND , $notice_data);
        return $res;
    }

    /**
     * 俱乐部会长收益
     * @param $order
     * @param $club_vip_info
     * @param $price
     * @return int
     */
    private function clubSeniorWage($order,$club_vip_info,$price){
        //获取是否存在高级会长
        $clubModel = new ClubModel();
        $club = $clubModel -> getOneByWhere(['cid'=>$order['club_id']]);
        if(!$club['senior_president']){
            //给俱乐部会长返利
            $user_data = [
                [
                    'uid' => $club_vip_info['player_id'],
                    'property_type' => 10009,
                    'change_num' => $price,
                    'event_type' => '+',
                    'reason_id' => 5,
                    'property_name' => '玩家购买会员卡给会长返利'
                ]
            ];
            $operateResult = operatePlayerProperty($user_data);
            if($operateResult['code'] != 0){
                return -2;
            }
        }else{
            //给俱乐部会长和高级会长返利和商务会长返利
            $user_data = [
                [
                    'uid' => $club_vip_info['player_id'],
                    'property_type' => 10009,
                    'change_num' => round($price , 2),
                    'event_type' => '+',
                    'reason_id' => 5,
                    'property_name' => '玩家购买会员卡给会长返利'
                ],
                [
                    'uid' => $club['senior_president'],
                    'property_type' => 10009,
                    'change_num' => round($price * $club['rebate'] * 0.01 * 0.6 , 2),
                    'event_type' => '+',
                    'reason_id' => 5,
                    'property_name' => '玩家购买会员卡给高级会长返利'
                ]
            ];

            //查找商务会长，给商务会长返利
            $commerceModel = new CommerceModel();
            $commerce = $commerceModel -> getOneByWhere(['senior_president' => $club['senior_president']]);
            if($commerce){
                $data = [
                    [
                        'uid' => $commerce['commerce_id'],
                        'property_type' => 10009,
                        'change_num' => round($price * $club['business_rebate'] * 0.01 * 0.6 , 2),
                        'event_type' => '+',
                        'reason_id' => 5,
                        'property_name' => '玩家购买会员卡给商务会长返利'
                    ]
                ];
                $user_data = array_merge($user_data,$data);
            }
            $operateResult = operatePlayerProperty($user_data);
            if($operateResult['code'] != 0){
                return -3;
            }
            //发送大数据(高级会长收益)
            $this -> highLevelClubRebateBeeSend($club_vip_info['player_id'],round($price , 2),$order['club_id'],$club['senior_president']);
            if($commerce){
                //发送大数据（商务会长收益）
                $this -> businessClubRebateBeeSend($club['senior_president'],round($price * $club['rebate'] * 0.01 * 0.6 , 2),$commerce['commerce_id']);
            }
        }
        return 1;
    }

    /**
     * 发送高级会长的收益大数据
     * @param $user_id 高级会长id
     * @param $token_num
     */
    private function highLevelClubRebateBeeSend($user_id,$token_num,$club_id,$senior_id){
        $user_info = getUserBaseInfo($user_id);
        $club_info = getClubNameAndAreaName($club_id);
        $senior_base_info = getBeeBaseInfo('-',$senior_id);
        if(!$user_info || !$club_info || !$senior_base_info){
            return false;
        }
        $content = [
            'do_rebate_user_id' => $user_id, //下会长id
            'do_rebate_user_name' => $user_info['nickname'], //下级会长的昵称
            'token_name' => 'money', //收益的代币名称
            'token_num'  => $token_num, //收益数量
        ];
        $contents = array_merge($content,$senior_base_info,$club_info);

        $this ->beeSend('highlevel_club_rebate',$contents);
    }

    /**
     * 发送商务会长大数据
     * @param $senior_id 商务会长id
     * @param $token_num
     * @param $club_id
     * @param $senior_id
     * @return bool
     */
    private function businessClubRebateBeeSend($senior_id,$token_num,$business_id){
        $user_info = getUserBaseInfo($senior_id);
        $business_info = getBeeBaseInfo('-',$business_id); //基础参数传商务会长
        if(!$user_info || !$business_info){
            return false;
        }
        $content = [
            'do_rebate_user_id' => $senior_id, //高级会长id
            'do_rebate_user_name' => $business_info['nickname'], //高级会长的昵称
            'token_name' => 'money', //收益的代币名称
            'token_num'  => $token_num, //收益数量
        ];
        $contents = array_merge($content,$business_info);

        $this ->beeSend('business_club_rebate ',$contents);
    }

    /**
     * 购买会员卡报送大数据
     */
    private function buyVipCardSendBeeSend($order,$pay_type,$cardNum,$vipcardName,$pay_result){
        $content = [
            'order_id'    => $order['id'],//订单号
            'real_amount' => $order['fee'], //订单金额 (单位/分)
            'pay_channel' => 'user', //购买渠道
            'pay_result'  => $pay_result, //支付结果
            'currency'    => 'cny', //币种
            'pay_type'    => $pay_type, //支付类型
            'props_id'    => $order['vip_id'], //道具id（获取道具id）
            'props_name'  => $vipcardName, //道具名称
            'props_num'   => $order['product_amount'], //道具数量
            'current_num' => $cardNum, //获取道具后所拥有的数量
        ];
        $clubInfo = getClubNameAndAreaName($order['club_id']);
        $baseInfo = getBeeBaseInfo();
        $unknown = 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (false !== strpos($ip, ',')) {
            $ip = reset(explode(',', $ip));
        }
        if(!$baseInfo){
            $baseInfo = [
                'ip '       => $ip,  //事件发生端iP
                'user_id'   => $order['player_id'],  //用户id
                'role_id'   => '-' . '_' . $order['player_id'],  //角色id，若没有即为serverid_userid
                'role_name' => backNickname($order['player_id']),  //昵称
                'client_id' => '-',  //设备的UUID（可传-号）
                'server_id' => '-',  //区服id ，服务器为服务器的网元id（可传减号）
                'system_type'=> $order['system_type'], //操作系统
                'client_type'=> $order['client_type'], //设备端应用类型
            ];
        }
	$contents = array_merge($content,$clubInfo,$baseInfo);
        $this -> beeSend('recharge_finish' , $contents);
    }

    /**
     * 判断支付类型
     * @param $pay_type
     * @return int
     */
    private function getPayType($pay_type){
        switch (strtoupper($pay_type)){
            case 'ALIPAYWEB':
                $type_num = 1;
                break;
            case 'WECHATWEB':
                $type_num = 4;
                break;
            case 'B2BBANK':
                $type_num = 2;
                break;
            case 'B2CBANK':
                $type_num = 3;
                break;
            case 'CREDITCARD':
                $type_num = 6;
                break;
            case 'DEBITCARD':
                $type_num = 7;
                break;
            default:
                $type_num = 5;
                break;
        }
        return $type_num;
    }

    /**
     * 验证签名
     * @param $data_list
     * @param $secret
     * @return string
     */
    private function get_sign($data_list,$secret){
        ksort($data_list);
        $sign_data = '';
        foreach ($data_list as $k=>$v){
            $sign_data .= $k.'='.$v.'&';
        }
        $sign_data = substr($sign_data, 0,strlen($sign_data) - 1);
        $sign_data .= $secret;
        return strtoupper(md5($sign_data));
    }
}