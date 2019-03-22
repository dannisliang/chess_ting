<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/22
 * Time: 14:29
 */
namespace app\model;

use app\definition\Definition;
use think\Model;

class UserClubVipCardModel extends Model{

    protected $name = 'user_club_vip_card';

    /**
     * 获取用户所有可以使用的vip卡
     * @param $userId
     * @param $clubId
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getUserClubVipCardInfo($userId, $clubId){
        return $this->where('uid', '=', $userId)->where('cid', '=', $clubId)->where('number', '>', 0)->limit(Definition::$VIP_CARD_ALL_TYPE_NUM)->select();
    }

    /**
     * 获取用户在俱乐部中某类型的可开通的
     * @param $userId
     * @param $clubId
     * @param $vId
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserClubVipCardInfoByVipId($userId, $clubId, $vId){
        return $this->where('uid', '=', $userId)->where('cid', '=', $clubId)->where('vid', '=', $vId)->where('number', '>', 0)->find();
    }
}
