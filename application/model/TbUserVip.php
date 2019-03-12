<?php
/**
 * Created by Xie
 * User: DELL
 * Date: 2019/3/12
 * Time: 19:19
 */
namespace app\model;

use think\Model;

class TbUserVip extends Model{

    protected $name = 'tb_user_vip';

    # 根据俱乐部ID获取俱乐部数据
    public function getInfoByUserIdAndClubId($userId, $clubId){
        $where = [
            ['uid', '=', $userId],
            ['club_id', '=', $clubId],
            ['vip_status', '=', 1],
        ];
        return $this->where($where)->find();
    }
}