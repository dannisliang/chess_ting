<?php
/**
 * Created by Xie
 * User: DELL
 * Date: 2019/3/12
 * Time: 19:19
 */
namespace app\model;

use think\Model;

class UserVipModel extends Model{

    protected $name = 'user_vip';

    /**
     * @param $userId 用户ID
     * @param $clubId 俱乐部ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getUserVipInfoByUserIdAndClubId($userId, $clubId){
        return $this->where('uid', '=', $userId)->where('club_id', '=', $clubId)->where('vip_status', '=', 1)->find();
    }

    /**
     * 根据条件去查询一条数据
     * @param $where array
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where){

        try{
            return $this -> where($where)
                -> alias('a')
                ->join('vip_card b','a.vid = b.vip_id','LEFT')
                ->field('a.vid,a.end_day,a.card_number,b.name,b.type')
                ->join('vip_type c','c.v_type = b.type','LEFT')
                ->field('c.v_name')
                ->find();
        }catch(\Exception $e){
            return false;
        }
    }
}