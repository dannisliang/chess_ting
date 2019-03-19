<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/14
 * Time: 11:36
 */

namespace app\model;


use think\Model;

class UserEvaluateModel extends Model
{
    protected $name = 'user_evaluate';

    /**
     * 获取评论的信息数量
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfoById($id){
        try{
            return $this -> where('player_id',$id)->find();
        }catch(\Exception $e){
            return false;
        }
    }
}