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
    public function getClubInfoByClubId($clubId)
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
}