<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/20
 * Time: 16:22
 */

namespace app\model;


use think\Model;

class ClubVipModel extends Model
{
    protected $name = 'club_vip';

    /**
     * 获取俱乐部的vip卡详情
     * @param $club_id
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClubVipCard($club_id){
         return $this->alias('a')
            ->join('vip_card b','a.vid = b.vip_id')
            ->where('a.club_id',$club_id)
            ->field('*')
            ->select();
    }
}