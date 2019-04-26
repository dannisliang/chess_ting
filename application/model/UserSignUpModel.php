<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/25
 * Time: 15:29
 */

namespace app\model;


use think\Model;

class UserSignUpModel extends Model
{
    protected $name = 'tb_user_sign_up';

    public function getUserSignUpInfo($userId, $matchPlayId){
        return $this->where('player_id', '=', $userId)->where('match_id', '=', $matchPlayId)->find();
    }

    public function addOne($data){
        return $this->insert($data);
    }

}