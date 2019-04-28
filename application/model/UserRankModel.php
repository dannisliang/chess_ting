<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/28
 * Time: 9:25
 */
namespace app\model;

use think\Model;

class UserRankModel extends Model{

    protected $name = 'user_rank';

    # 获取排行榜
    public function getSort($date){
        return $this->where('match_day', '=', $date)->order('total_store desc')->limit(50)->select();
    }

    # 获取玩家rank值
    public function getMyRank($playerId, $date){
        return $this->where('match_day', '=', $date)->where('player_id', '=', $playerId)->find();
    }
}
