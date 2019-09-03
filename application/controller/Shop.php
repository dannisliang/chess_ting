<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/20
 * Time: 13:09
 */

namespace app\controller;


use app\definition\Definition;
use app\definition\RedisKey;
use app\model\ClubShopModel;
use app\model\ClubVipModel;
use app\model\VipCardModel;
use app\model\OrderModel;
use think\Session;
use think\Log;
use think\Env;

class Shop extends Base
{
    protected $user_id; //储存用户id

    public function _initialize()
    {
        parent::_initialize();
        $this -> user_id = getUserIdFromSession();
        if(!$this -> user_id){
            return json(['code'=>3400,'mess'=>'用户不存在'])->send();
            exit;
        }
    }

    /**
     * 获取商城商品列表
     * @return \think\response\Json\
     */
    public function shopGoodsList(){
        //验证参数
        $opt = ['type'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        //先获取vip商品的数据
        if(isset($this->opt['club_id']) && $this->opt['club_id'] != 0 && $this->opt['type'] == 2 ){
            $clubVipCard = $this -> getVipDetail($this->opt['club_id']);
            if(!$clubVipCard){
                return jsonRes(23403);
            }
            return jsonRes(0,$clubVipCard);
        }
        //获取钻石的商品种类
        $clubShopModel = new ClubShopModel();
        switch ($this->opt['type']){
            case 0: //金币
                $goods = $clubShopModel -> getSomeByWhere(['goods_type'=>10000,'status'=>1]);
                break;
            case 1: //蓝钻
                $goods = $clubShopModel -> getSomeByWhere(['goods_type'=>10001,'status'=>1],'goods_position desc');
                break;
            default:
                return jsonRes(23403);
                break;
        }
        //没有商品
        if(!$goods){
            return jsonRes(23403);
        }
        $shopList = [];
        foreach ($goods as $good){
            if ($this->opt['type'] ==1){
                $price = (float)($good['price']/100);
            }else{
                $price = $good['price'];
            }
            if($good['goods_count'] < 0){
                $limit_sale = 0;
            }else{
                $limit_sale = 1;
            }
            $temp = [
                'item_id'   => $good['id'],
                'item_count'=> $good['goods_number'],
                'good_type' => $good['goods_type'],
                'item_extra'=> $good['give'],
                'item_price'=> $price,
                'limit_sale'=> $limit_sale,
                'goods_count'=>(int)$good['goods_count'],
                'start_time'=> (int)strtotime($good['start_time']),
                'end_time'  => (int)strtotime($good['end_time']),
                'status'    => (int)$good['status'],
                'hot_sale'  => (int)$good['hot_sale'],
                'goods_position'  => (int)$good['goods_position'],
            ];
            $shopList[] = $temp;
        }
        return jsonRes(0,$shopList);
    }

    /**
     * h获取订单信息
     * @return \think\response\Json\
     */
    public function getOrder(){
        $opt = ['club_id','shop_id','vip_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $user_id = $this -> user_id;
        $clubVipModel = new ClubVipModel();
        $vipCardModel = new VipCardModel();
        $orderModel    = new OrderModel();
        $clubShopModel = new ClubShopModel();

        //如果存在vip_id查询俱乐部的vip卡
        if($this->opt['vip_id']){
            $clubVip = $clubVipModel ->getOneByWhere(['club_id'=>$this->opt['club_id'],'vid'=>$this->opt['vip_id']],'pricing,number');
            if(!$clubVip){
                return jsonRes(23403);
            }
            $price = $clubVip['pricing'];
            $goods_num = 1;//暂时只能买一张
            $give_counts = 0; //赠送数量
            $goods_type = 10004;
            if(!$price){
                //如果没有设值则去平台的会员卡取值
                $vip_card = $vipCardModel ->getOneByWhere(['vip_id'=>$this->opt['vip_id']]);
                if(!$vip_card){
                    return jsonRes(23403);
                }
                $price = $vip_card['price']; //会长没有设置则取价格下限
            }
        }else{
            //不存在vip_id则查询钻石
            $clubShop = $clubShopModel ->getOneByWhere(['id'=>$this->opt['shop_id']]);
            if(!$clubShop){
                return jsonRes(23403);
            }
            $price = $clubShop['price'];
            $goods_num  = $clubShop['goods_number'];
            $give_counts= $clubShop['give'];
            $goods_type = 10001;
        }
        //生成订单号 保证13位数字输出
//        $order_num  = str_pad(substr(time(),-7) . $user_id,13,'0',STR_PAD_RIGHT);
        $order_num = $this -> getOrderID();
        $time       = date('Y-m-d H:i:s',time());
        $nick_name  = backNickname($user_id);
        $user_session_info = Session::get(RedisKey::$USER_SESSION_INFO);
        $client_type= $user_session_info['client_type'];
        $app_type   = $user_session_info['app_type'];
        $data = [
            'id'            => $order_num,
            'fee'           => $price,
            'vip_id'        => $this ->opt['vip_id'],
            'club_id'       => $this->opt['club_id'],
            'player_id'     => $user_id,
            'order_time'    => $time,
            'product_id'    => $this->opt['shop_id'],
            'give_count'    => $give_counts,
            'goods_type'    => $goods_type,
            'system_type'   => $client_type,//机型
            'player_name'   => base64_encode($nick_name),
            'client_type'   => $app_type, //app还是H5
            'product_amount'=> $goods_num,
        ];
        $result = $orderModel ->insertData($data);
        if(!$result){
            return jsonRes(3004);
        }
        return jsonRes(0,$order_num);
    }

    /**
     * 购买金币
     * @return \think\response\Json\
     */
    public function buyGold(){
        $user_id = $this -> user_id;
        $opt = ['item_id'];
        if(!has_keys($opt ,$this ->opt)){
            return jsonRes(3006);
        }
        $clubShopModel = new ClubShopModel();
        //获取商品内容
        $club_shop = $clubShopModel ->getOneByWhere(['id'=>$this->opt['item_id']]);
        $userProperty = getUserProperty($user_id , [10001,10002]);
        $buyDiamond = 0; $freeDiamond = 0;
        foreach ($userProperty['data'] as $property){
            switch ($property['property_type']){
                case 10001: //购买蓝钻
                    $buyDiamond += $property['property_num'];
                    break;
                case 10002: //赠送蓝钻
                    $freeDiamond += $property['property_num'];
                    break;
                default:
                    break;
            }
        }
        //钻石不够判断
        if($buyDiamond + $freeDiamond < $club_shop['price']){
            return jsonRes(3201);
        }
        //先扣购买蓝钻
        if($buyDiamond >= $club_shop['price']){
            $result = operateUserProperty($user_id,10001,$club_shop['price'], '-', 4,'购买蓝钻购买金币');
            if($result['code'] != 0){
                return jsonRes(23401);
            }
            //增加用户的金币
            $gold = $club_shop['goods_number'] + $club_shop['give'];
            $results = operaUserProperty($user_id,10002 , $gold , '+' , 4,'蓝钻购买金币');
            if($results['code'] != 0){
                $res = operateUserProperty($user_id,10001,$club_shop['price'], '+', 4,'增加金币失败回滚');
                if($res['code'] != 0){
                    //回滚失败
                    Log::write(date('Y-m-d H:i:s') . '回滚操作失败', 'buyGold_error1');
                }
            }
        }else{
            //购买钻不够的话先扣完购买钻再扣赠送钻
            $result = operateUserProperty($user_id,10001,1, '-', 4,'购买蓝钻购买金币');
            if($result['code'] != 0){
                return jsonRes(23401);
            }
            $result = operateUserProperty($user_id,10002,1 , '-' , 4,'赠送蓝钻购买金币');
            if($result['code'] != 0){
                return jsonRes(23401);
            }
            //增加用户的金币
            $gold = $club_shop['goods_number'] + $club_shop['give'];
            $results = operaUserProperty($user_id,10000 , $gold , '+' , 4,'蓝钻购买金币');
            if($results['code'] != 0){
                $res = operateUserProperty($user_id,10002,$club_shop['price'] - $buyDiamond , '-' , 4,'赠送蓝钻购买金币');
                if($res['code'] != 0 ){
                    //回滚失败
                    Log::write(date('Y-m-d H:i:s') . '回滚操作失败', 'buyGold_error2');
                }
                $result = operateUserProperty($user_id,10001,$buyDiamond, '-', 4,'购买蓝钻购买金币');
                if($result['code'] != 0 ){
                    //回滚失败
                    Log::write(date('Y-m-d H:i:s') . '回滚操作失败', 'buyGold_error3');
                }
            }
        }

        //购买完成发送通知
        $data = [
            'content' => [
                'gold'    => $gold,
                'diamond' => $buyDiamond + $freeDiamond - $club_shop['price'],
            ],
            'type'   => 1092,
            'sender' => 0,
            'reciver'=> [
                $user_id,
            ],
            'appid'  => Env::get('app_id'),
        ];
        $url        = Env::get('inform_url');
        $pathInfo   = Definition::$SEND;
        $res = guzzleRequest($url , $pathInfo , $data);
        if($res['code'] != 0){
            Log::write($res,'buyGold_error');
        }

        return jsonRes(0);
    }

    /**
     * H5下单 todo 测试服没有测
     * @return \think\response\Json\
     */
    public function orderPay(){
        //验证token
        $user_session_info = Session::get(RedisKey::$USER_SESSION_INFO);
        $user_id = $user_session_info['userid'];
        $data = [
            'ip'    => $user_session_info['ip'],
            'token' => $user_session_info['token'],
            'uid'   => $user_id,
        ];
        $result = checkToken( $data );
        if(!isset($result['result']) || $result['result'] === false || !$user_id){
            return jsonRes(9999);
        }

        $opt = ['shop_id','vip_id','club_id','url_id'];
        if(!has_keys($opt , $this->opt)){
            return jsonRes(3006);
        }
        $clubShopModel = new ClubShopModel();
        $clubVipModel  = new ClubVipModel();
        $vipCardModel  = new VipCardModel();

        $ret_url = Env::get('mahjong_chessvans'); //支付返回页面
        if($this->opt['url_id']){
            $ret_url = Env::get('mahjong_chessvans') . $this -> opt['url_id'];
        }
        $user_name = backNickname($user_id);

        //根据传输的shop_id和vip_id查找商品数据
        if(!empty($this->opt['shop_id'])){
            $club_shop = $clubShopModel -> getOneByWhere(['id'=>$this->opt['shop_id']]);
            if(!$club_shop){
                return jsonRes(23407);
            }
            $price  = $club_shop['price'];
            $type   = $club_shop['goods_type'];
            $goods_number = $club_shop['goods_number'];
            $give_counts  = $club_shop['give'];
            $goods_type   = 10001;
        }elseif (!empty($this->opt['vip_id'])){
            $club_vip  = $clubVipModel -> getOneByWhere(['vid'=>$this->opt['vip_id'],'club_id'=>$this->opt['club_id']]);
            if(!$club_vip){
                return jsonRes(23407);
            }
            //如果表里的价格为0,再查tb_vip_card
            if(empty($club_vip['pricing'])){
                $vip_card = $vipCardModel -> getOneByWhere(['vip_id'=>$this -> opt['vip_id']]);
                $price = $vip_card['price'];
            }else{
                $price = $club_vip['pricing'];
            }
            $type = $this->opt['club_id'] . '_' . $this->opt['vip_id'];
            $goods_number = 1;
            $give_counts = 0;
            $goods_type  = 10004;
        }else{
            return jsonRes(23407);
        }

        //生成订单号 保证13位数字输出
//        $order_num  = str_pad(substr(time(),-7) . $user_id,13,'0',STR_PAD_RIGHT);
        $order_num = $this -> getOrderID();
        $time = date('Y-m-d H:i:s');
        //$ret_url = urlencode($ret_url);

        $notify_url = Env::get('async_callback_url');//异步回调地址
        //$notify_url = urlencode($notify_url);

        switch ($type){
            case 10001:
                $goods_info = '钻石';
                break;
            case 10002:
                $goods_info = '钻石';
                break;
            case 10000:
                $goods_info = '金币';
                break;
            default:
                if (!empty($type)){
                    $goods_info = 'vip卡';
                }else{
                    return jsonRes(23407);
                }
                break;
        }
        $sign_data = [
            'app_id' => Env::get('app_id'),
            'cp_order_id'=> $order_num,
            'fee' => $price,
            'goods_inf' => $goods_info,
            'notify_url' => $notify_url,
            'ret_url' => $ret_url,
        ];
        //获取签名
        $sign = $this -> get_sign($sign_data , Env::get('sign'));
        $url = 'https://payment.chessvans.com/umf_pay/service/wechat_mp.php?app_id=' . Env::get('app_id') . '&&cp_order_id=' . $order_num . '&&fee=' . $price . '&&goods_inf=' . $goods_info . '&&notify_url=' . $notify_url . '&&ret_url=' . $ret_url . '&&sign=' . $sign;
        $result = sendHttpRequest( $url );
        Log::write($result,'result_error');
        if(!$result || !isset($result['ErrCode']) || $result['ErrCode']!= 0){
            return jsonRes(3004);
        }
        $url = $result['URL'];
        //获取机型 和 类型
        $user_session_info = Session::get(RedisKey::$USER_SESSION_INFO);
        $client_type= $user_session_info['client_type'];
        $app_type   = $user_session_info['app_type'];
        $data = [
            'id'            => $order_num,
            'fee'           => $price,
            'vip_id'        => $this ->opt['vip_id'],
            'club_id'       => $this->opt['club_id'],
            'player_id'     => $user_id,
            'order_time'    => $time,
            'product_id'    => $this->opt['shop_id'],
            'give_count'    => $give_counts,
            'goods_type'    => $goods_type,
            'system_type'   => $client_type,//机型
            'player_name'   => base64_encode($user_name),
            'client_type'   => $app_type, //app还是H5
            'product_amount'=> $goods_number,
        ];
        $orderModel = new OrderModel();
        $result = $orderModel ->insertData($data);
        if(!$result){
            return jsonRes(3004);
        }
        return jsonRes(0,$url);
    }

    /**
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

    /**
     * 获取俱乐部会员卡详情
     */
    private function getVipDetail($club_id){
        $clubVipModel   = new ClubVipModel();
        $clubVipCard    = $clubVipModel ->getClubVipCard($club_id);
        if(!$clubVipCard){
            return false;
        }

        $list = [];
        foreach ($clubVipCard as $value){
            //如若没有设置价格则选择价格下限
            $pricing = $value['pricing'];
            if(!$pricing){
                $pricing = $value['price'];
            }

            $temp = [
                'item_price' => (float)$pricing/100, //价格
                'goods_count'=> $value['number'], //商品的库存
                'end_day'    => strtotime($value['end_time']), //vip到期时间
                'vid'        => $value['vid'], //vip卡id
                'number_day' => $value['number_day'], //使用增加的天数
                'name'       => $value['name'], //会员卡名称
                'icon'       => 'https://tjmahjong.chessvans.com//GMBackstage/public/' . $value['icon'], //商品图片地址
                'diamond_consumption'=> $value['diamond_consumption'], //vip卡的折扣
            ];
            $list[] = $temp;
        }
        return $list;
    }

    /**
     * 生成订单号
     * @return string
     */
    private function getOrderID(){
        $a = explode(' ', microtime());
        $b = round(floatval($a[0]),3);
        $c = explode('.', strval($b));
        $d = '0';
        if(count($c) == 2){
            $d = $c[1];
        }
        while(strlen($d) < 3){
            $d .= '0';
        }

        return date('YmdHis').$d.rand(100,999);
    }

}
