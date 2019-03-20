<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/20
 * Time: 13:09
 */

namespace app\controller;


use app\model\ClubShopModel;
use app\model\ClubVipModel;

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
     * 获取商店详情
     * @return \think\response\Json\
     */
    public function shopDetail(){
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
                $goods = $clubShopModel -> getSomeByType(['goods_type'=>10000,'status'=>1]);
                break;
            case 1: //蓝钻
                $goods = $clubShopModel -> getSomeByWhere(['goods_type'=>10001,'status'=>1],'goods_position desc');
                break;
            default:
                return jsonRes(3004);
                break;
        }
        if(!$goods){
            return jsonRes(3004);
        }
        $shopList = [];
        foreach ($goods as $good){
            $temp = [
                'item_id'   => $good['id'],
                'item_count'=> $good['goods_number'],
                'good_type' => $good['goods_type'],
                'item_extra'=> $good['give'],
                // todo 2019 .03.20
            ];
        }

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
            //如若没有设置价格则选择价格上线
            $pricing = $value['pricing'];
            if(!$pricing){
                $pricing = $value['price_upper_limit'];
            }

            $temp = [
                'item_price' => $pricing/100, //价格
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