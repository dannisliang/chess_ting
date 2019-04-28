<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/28
 * Time: 10:34
 */
namespace app\model;

use think\Model;

class UserMatchNumModel extends Model{

    protected $name = 'user_match_num';

    # 获取玩家已经进行的比赛次数
    public function getUserMatchNum($userId, $date, $matchId){
        return $this->where('player_id', '=', $userId)->where('match_str_time', '=', $date)->where('match_id', '=', $matchId)->find();
    }

    public function getUserMatchNumA($userId, $date){
        return $this->where('player_id', '=', $userId)->where('match_str_time', '=', $date)->find();
    }
}