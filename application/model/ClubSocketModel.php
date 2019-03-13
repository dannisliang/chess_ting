<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/13
 * Time: 11:32
 */
namespace app\model;

use think\Model;

class ClubSocketModel extends Model{

    protected $name = 'club_socket';

    # 根据俱乐部ID获取俱乐部连接通道
    public function getInfoByClubId($clubId){
        return $this->where('club_id', '=', $clubId)->find();
    }
}