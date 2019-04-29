<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/11
 * Time: 13:56
 */
namespace app\model;

use think\Model;

class ClubModel extends Model{

    protected $name = 'club';

    /**
     * 获取正常的俱乐部数据
     * @param $id 俱乐部ID的
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClubInfo($clubId)
    {
        return $this->where('cid', '=', $clubId)->where('club_status', '=', 1)->find();
    }

    /**
     * @param $id 俱乐部id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClubNameById($id){
        try{
            $res = $this -> where('cid',$id)->find();
            if (!$res){
                return false;
            }
            return $res;
        }catch(\Exception $exception){
            return false;
        }
    }

    /**
     * 传输条件获取一条信息
     * @param $where
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere( $where , $field = '*' ){
        return $this -> where( $where ) -> field( $field ) -> find();
    }

    /**
     * 获取玩家加入的俱乐部
     */
    public function getUserJoinClub($user_id){
         return $this -> alias('a')
                    ->join('user_club b','a.cid = b.club_id')
                    ->where(['b.status'=> ['in',[0,1]]])
                    ->where('a.club_status',1)
                    ->where('b.player_id',$user_id)
                    ->field('b.status,b.player_id,a.cid,a.club_icon,a.club_name,a.max_club_members_count,a.president_name,a.limitation,a.content,a.room_card,a.creat_player')
                    ->select();
    }


    /**
     * 根据club_id获取俱乐部的地域信息
     * @param $club_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getClubNameAndAreaName($club_id){
         return $this ->alias('a')
            ->join('area b' , 'a.area_id = b.aid')
            ->field('a.cid,a.club_name,a.club_type,a.area_id,b.area_name')
            ->where('cid',$club_id)
            ->find();
    }


    public function getAllClubIds(){
        return $this->where('club_status', '=', 1)->column('cid');
    }

}