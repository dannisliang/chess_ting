<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/25
 * Time: 10:58
 */

namespace app\controller;


use app\model\ClubModel;
use app\model\ClubShopModel;
use app\model\ClubVipModel;
use app\model\OrderModel;
use app\model\UserVipModel;
use app\model\VipCardModel;
use think\Db;
use think\Log;

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
        $pay_type = $_GET['pay_type'];
        //验证签名是否合法
        $key_sign = $this ->get_sign($sign_data,'c80b7d337dc57d5d');
//        var_dump($key_sign);die;
//        if($key_sign != $sign_data['sign']){
//            return json(['result'=>3]); //签名不合法
//        }

        //查出订单的详细信息
        $orderModel = new OrderModel();
        $order = $orderModel -> getOneByWhere(['id' => $sign_data['cp_order_id']] , 'vip_id,product_id,player_id,club_id,system_type,client_type');
        if(!$order){
            return json(['result' => 3]); //没有订单信息
        }

        //判断订单类型 是买卡的还是买钻石的
        if(!empty($order['vip_id'])){
            //获取会员卡信息(会长返利)
            $result = $this -> buyVipCard($order,$pay_type);

            if(!$result != 1){
                Log::write($result , 'buy_vip_card_error'); //根据不同的返回码确认报错信息
                return json(['result'=>3]);
            }
            return json(['result'=>1]);
        }elseif(!empty($order['product_id'])){
            $result = $this ->buyDiamond($order);

        }else{
            return json(['result' => 3]); //都不存在
        }

    }

    /**
     * 玩家买钻石
     * @return bool
     * @throws \think\exception\DbException
     */
    private function buyDiamond($order){
        //玩家买钻回调
        $clubShopModel = new ClubShopModel();
        $clubShop = $clubShopModel -> getOneByWhere(['id'=>$order['product_id']]);
        if(!$clubShop){
            return -1;
        }

        $data = [
            [
                'player_id' => $order['player_id'],
                'type' => 10001,
                'change_num' => $clubShop['goods_number'], //购买数量
                'event_type' => '+',
                'reason_id' => 4,
                'property_name' => '玩家购买钻石数量'
            ],
            [
                'player_id' => $order['player_id'],
                'type' => 10002,
                'change_num' => $clubShop['give'], //赠送数量数量
                'event_type' => '+',
                'reason_id' => 4,
                'property_name' => '玩家购买钻石赠送数量'
            ]
        ];
        //操作用户钻石增减 cehsi
        $res = operateUserProperty($order['player_id'] ,10002,$clubShop['give'],'+',4,'sss');
        $operateRes = operatePlayerProperty($data);
        var_dump($operateRes);die();
        if($operateRes['code'] != 0){
            //操作失败
            return -1;
        }

        return 1;
    }

    /**
     * 如果使用会员卡--记录
     * @param $order
     * @return bool
     * @throws \think\exception\DbException
     */
    private function buyVipCard($order,$pay_type){
        //获取会员卡的库存和价格信息
        $clubVip = new ClubVipModel();
        $club_vip_info = $clubVip -> getOneByWhere(['club_id' => $order['club_id']]);
        if(!$club_vip_info){
            return -1;
        }
        $price = $club_vip_info['pricing'];
        //获取会员卡信息
        $vipCard = new VipCardModel();
        $vip_card_info = $vipCard -> getOneByWhere(['vip_id'=>$order['vip_id']]);
        if(!$vip_card_info){
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
        $user_vip = $userVipModel -> getOneByWhere(['uid' => $order['player_id'] , 'vid' => $order['vip_id'] , 'club_id' => $order['club_id']]);
        if($user_vip){
            $data = [
                'card_number' => $user_vip['card_number'] +1,
                'vip_level'   => $club_vip_info['type'],
            ];
            $res = $userVipModel ->updateByWhere($where,$data);
            if(!$res){
                return -1; //更新失败
            }
        }else{
            $data = [
                'vid' => $order['vip_id'],
                'uid' => $order['player_id'],
                'club_id' => $order['club_id'],
                'vip_status' => 0,
                'card_number' => 1,
                'vip_level' => $order['type'],
            ];
            $result = $userVipModel -> insertData($data);
            if(!$result){
                return -1; //插入数据失败
            }
        }
        //获取是否存在高级会长
        $clubModel = new ClubModel();
        $club = $clubModel -> getOneByWhere(['cid'=>$order['club_id']]);
        if(!$club['senior_president']){
            //给俱乐部会长返利
            $user_data = [
                [
                    'player_id' => $club_vip_info['player_id'],
                    'type' => 10009,
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
            //给俱乐部会长返利
            $user_data = [
                [
                    'player_id' => $club_vip_info['player_id'],
                    'type' => 10009,
                    'change_num' => round($price , 2),
                    'event_type' => '+',
                    'reason_id' => 5,
                    'property_name' => '玩家购买会员卡给会长返利'
                ],
                [
                    'player_id' => $club['senior_president'],
                    'type' => 10009,
                    'change_num' => round($price * $club['rebate'] * 0.01 * 0.6 , 2),
                    'event_type' => '+',
                    'reason_id' => 5,
                    'property_name' => '玩家购买会员卡给高级会长返利'
                ]
            ];
            $operateResult = operatePlayerProperty($user_data);
            if($operateResult['code'] != 0){
                return -3;
            }
        }
        //TODO 报送会长收益大数据

        $clubVipModel = new ClubVipModel();
        $orderModel   = new OrderModel();
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
        Db::startTrans();
        try{
            //减少库存
            $clubVipModel ->setDecByWhere(['vid'=>$order['vip_id'],'cid'=>$order['club_id']],'number');
            //修改订单状态和支付类型
            $orderModel -> setFieldByWhere(['id'=>$order['id']],['order_status'=>1,'pay_type'=>$type_num]);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            $orderRes = $orderModel -> setFieldByWhere(['id'=>$order['id']],['order_status'=>2,'pay_type'=>$type_num]);
            if(!$orderRes){
                Log::write('修改订单状态失败','update_order_status_error');
            }
            return -1;
        }
        //todo 报送大数据（支付完成部分）

        //todo 给客户端发送一条数据
        return 1; //操作成功
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