<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/20
 * Time: 13:09
 */

namespace app\controller;


use app\definition\RedisKey;
use app\model\ClubShopModel;
use app\model\ClubVipModel;
use app\model\OrderModel;
use app\model\VipCardModel;

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
                return jsonRes(3004);
                break;
        }
        //没有商品
        if(!$goods){
            return jsonRes(3004);
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
        $order_num  = str_pad(substr(time(),-7) . $user_id,13,'0',STR_PAD_RIGHT);
        $time       = date('Y-m-d H:i:s',time());
        $nick_name  = backNickname($user_id);
        $user_session_info = sessionInfo(RedisKey::$USER_SESSION_INFO);
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


}