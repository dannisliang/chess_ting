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

    # 根据俱乐部ID获取俱乐部数据
    public function getInfoById($id)
    {
        return $this->where('cid', '=', $id)->find();
    }

    /**
     * @param $id 俱乐部ID的
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClubInfoByClubId($clubId)
    {
        return $this->where('cid', '=', $clubId)->find();
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
}