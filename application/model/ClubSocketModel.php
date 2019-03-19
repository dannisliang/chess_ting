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

    /**
     * 获取俱乐部专属连接通道
     * @param $clubId 俱乐部ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClubSocketInfoByClubId($clubId){
        return $this->where('club_id', '=', $clubId)->find();
    }
}