<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/25
 * Time: 14:54
 */

namespace app\model;


use think\Model;

class MatchPlayModel extends Model
{
    protected $name = 'tb_match_play';

    # 查找一个可以参加的活动
    public function getMatchPlayInfo(){
        return $this->find();
    }

}