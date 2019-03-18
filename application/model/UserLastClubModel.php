<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/12
 * Time: 20:25
 */

namespace app\model;


use think\Model;

class UserLastClubModel extends Model
{
    protected $name = 'user_last_club';

    /**
     * 获取玩家上次登录的俱乐部id
     * @param $player_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getLastClubId($player_id){
        try{
            $res = $this -> where('player_id',$player_id)->field('club_id')->find();
            if(!$res){
                return false;
            }
            return $res;
        }catch (\Exception $exception){
            return jsonRes(3401,$exception->getMessage());
        }

    }

    /**
     * 根据获取一套数据
     * @param $where
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOneByWhere($where , $field = '*'){
        return $this -> where($where) -> field($field) -> find();
    }

    public function updateByWhere(){

    }

    public function  insertByWhere(){
        
    }


}