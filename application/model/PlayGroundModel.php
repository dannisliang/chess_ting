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

}