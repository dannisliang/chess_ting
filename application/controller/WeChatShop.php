<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/7/8
 * Time: 16:41
 */

namespace app\controller;


use app\definition\RedisKey;
use app\model\ClubShopModel;
use app\model\ClubVipModel;
use app\model\OrderModel;
use app\model\UserClubModel;
use app\model\VipCardModel;
use think\Env;
use think\Log;
use think\Session;

class WeChatShop extends Base
{
    /**
     * 微信商城列表
     * @return \think\response\Json\
     */
    public function getShopList(){
        //验证参数 type 0:金币 1：钻石 2：会员卡
        $opt = ['type', 'session_id'];
        if(empty($this->opt) || !has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        session_id($this->opt['session_id']);
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes(9999);
        }
        $clubList = $this ->getPlayerClubList($player_id);
        // 获取玩家的资产信息
        $asset = $this -> getUserAssets($player_id);
        $data = [
            'vip_card_info' => [],
            'club_info' => $clubList,
            'asset_info' => $asset
        ];
        //先获取vip商品的数据
        if(isset($this->opt['club_id']) && $this->opt['club_id'] != 0 && $this->opt['type'] == 2 ){
            $clubVipCard = $this -> getVipDetail($this->opt['club_id'], $player_id);
            if(!$clubVipCard){
                return jsonRes(23403, $data);
            }
            return jsonRes(0,$clubVipCard);
        }elseif ($this->opt['type'] == 2 && !isset($this->opt['club_id'])){
            if(!$clubList){ // 没有俱乐部则只返回用户的资产
                $data = [
                    'vip_card_info' => [],
                    'club_info' => [],
                    'asset_info' => $asset
                ];
                return  jsonRes(0,$data);
            }
            $array_key = array_rand($clubList); // 随机出一个俱乐部
            $clubVipCard = $this -> getVipDetail($clubList[$array_key]['club_id'], $player_id);
            if(!$clubVipCard){
                return jsonRes(23403, $data);
            }
            return jsonRes(0,$clubVipCard);
        }

        // 获取钻石的商品种类
        $clubShopModel = new ClubShopModel();
        switch ($this->opt['type']){
            case 0: // 金币
                $goods = $clubShopModel -> getSomeByWhere(['goods_type'=>10000,'status'=>1]);
                break;
            case 1: // 蓝钻
                $goods = $clubShopModel -> getSomeByWhere(['goods_type'=>['in',[10001,10002]],'status'=>1],'goods_position desc');
                break;
            default:
                return jsonRes(23403, $data);
                break;
        }
        // 没有商品
        if(!$goods){
            return jsonRes(23403, $data);
        }
        $shopList = [];
        foreach ($goods as $good){
            if ($this->opt['type'] ==1){  // 蓝钻
                $good['pay_type'] = isset($good['pay_type']) ? $good['pay_type'] : 1;
                switch ($good['pay_type']){
                    case 1: // RMB
                        $price = (float)($good['price']/100);
                        break;
                    case 2: // 红包券
                        $price = (float)($good['price']);
                        break;
                    default:// 钻石
                        $price = (float)($good['price']);
                        break;
                }
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
                'pay_type'  => isset($good['pay_type']) ? $good['pay_type'] : 1 // 支付的方式 1：RMB 2：红包券 3：钻石（默认的是RMB）
            ];
            $shopList[] = $temp;
        }

        // 重写数据
        $data = [
            'goods_info' => $shopList,
            'club_info' => $clubList,
            'asset_info' => $asset
        ];
        return jsonRes(0,$data);
    }

    /**
     * 获取俱乐部会员卡详情
     */
    private function getVipDetail($club_id,$player_id){
        $clubVipModel   = new ClubVipModel();
        $clubVipCard    = $clubVipModel ->getClubVipCard($club_id);
        if(!$clubVipCard){
            return false;
        }
        // 获取俱乐部
        $clubList = $this ->getPlayerClubList($player_id);
        // 获取玩家的资产信息
        $asset = $this -> getUserAssets($player_id);

        $list = [];
        foreach ($clubVipCard as $value){
            // 如若没有设置价格则选择价格下限
            $pricing = $value['pricing'];
            if(!$pricing){
                $pricing = $value['price'];
            }
            $temp = [
                'goods_id'   => $value['id'],
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
        $data = [
            'vip_card_info' => $list,
            'club_info' => $clubList,
            'asset_info' => $asset
        ];
        return $data;
    }

    /**
     * 获取玩家的俱乐部信息
     * @param $player_id
     * @return array
     */
    private function getPlayerClubList($player_id){
        $userClubModel = new UserClubModel();
        $infos = $userClubModel -> getInfoByWhere(['a.player_id'=>$player_id,'a.status'=>1]);
        $data = [];
        if($infos){
            foreach ($infos as $info){
                $temp = [
                    'club_id' => $info['club_id'],
                    'club_name' => base64_decode($info['club_name'])
                ];
                $data[] = $temp;
            }
        }

        return $data;
    }

    /**
     * 获取用户资产
     * @param $user_id
     */
    private function getUserAssets($user_id){
        //资产类型
        $property_type = [10000,10001,10002,10005,10006];
        $userAssets = getUserProperty($user_id,$property_type);
        $diamond_num = 0; //钻石数量
        $gold_num = 0; //金币数量
        $red_coupon_num = 0; //红包券数量
        if(!empty($userAssets['data'])){
            foreach ($userAssets['data'] as $val){
                switch ($val['property_type']){
                    case 10000: //金币
                        $gold_num += $val['property_num'];
                        break;
                    case 10001: //购买钻
                        $diamond_num += $val['property_num'];
                        break;
                    case 10002: //赠送钻
                        $diamond_num += $val['property_num'];
                        break;
                    case 10005:
                        $red_coupon_num += $val['property_num'];
                        break;
                    default:
                        break;
                }
            }
        }
        $assets = [
            'diamond_num' => $diamond_num,
            'gold_num'    => $gold_num,
            'red_coupon_num' => $red_coupon_num,
        ];
        return $assets;
    }

    /**
     * H5生成订单
     * @return \think\response\Json\
     */
    public function createOrder(){
        $opt = ['shop_id','vip_id','club_id','url_id','session_id'];
        if(!has_keys($opt , $this->opt)){
            return jsonRes(3006);
        }
        //验证token
        session_id($this->opt['session_id']);
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

        $clubShopModel = new ClubShopModel();
        $clubVipModel  = new ClubVipModel();
        $vipCardModel  = new VipCardModel();

        $ret_url = Env::get('weChat_back_url'); // 支付完成返回页面
        if($this->opt['url_id']){
            $ret_url = $ret_url . $this -> opt['url_id'];
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
            $club_vip  = $clubVipModel -> getOneByWhere(['vid'=>$this->opt['vip_id'],'club_id'=>$this->opt['club_id'],'number'=>['>',0]]);
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

        //生成订单号
        $order_num  = $this -> getOrderID();

        $time = date('Y-m-d H:i:s');

        $notify_url = Env::get('async_callback_url');//异步回调地址

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
        $sign = $this -> getSign($sign_data , Env::get('sign'));
        $url = 'https://payment.chessvans.com/umf_pay/service/wechat_mp.php?app_id=' . Env::get('app_id') . '&&cp_order_id=' . $order_num . '&&fee=' . $price . '&&goods_inf=' . $goods_info . '&&notify_url=' . $notify_url . '&&ret_url=' . $ret_url . '&&sign=' . $sign;
//        var_dump($url);die;
        $result = sendHttpRequest( $url );
        if(!$result || !isset($result['ErrCode']) || $result['ErrCode']!= 0){
             Log::write($result,'result_error');
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
        $order_result = $orderModel ->insertData($data);
        if(!$order_result){
            return jsonRes(3004);
        }
        return jsonRes(0,$url);
    }

    /**
     * @param $data_list
     * @param $secret
     * @return string
     */
    private function getSign($data_list,$secret){
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

        return date('YmdHis') . $d . rand(100,999);
    }
}