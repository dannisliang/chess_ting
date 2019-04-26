<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/25
 * Time: 14:44
 */


namespace app\model;


use think\Model;

class PlayGroundModel extends Model
{
    protected $name = 'tb_playground';

    # 查找一个可以参加的活动
    public function getPlayGroundInfo(){
        return $this->where('status', '=', 1)->find();
    }


    # 获取一个活动规则
    public function getAPlayGroundInfo(){
        $dateTime = date("Y-m-d H:i:s", time());
        return $this->where('registration_time_end', '>', $dateTime)->find();
    }

    public function getBPlayGroundInfo(){
        $dateTime = date("Y-m-d H:i:s", time());
        $date = date("Y-m-d", time());
        $time = date("H:i:s", time());
        return $this->where('registration_time_start', '<', $dateTime)->where('match_date_end', '>', $date)->where('match_time_end', '>', $time)->find();
    }
}