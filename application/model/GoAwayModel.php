<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/20
 * Time: 10:32
 */

namespace app\model;


use think\Model;

class GoAwayModel extends Model
{
    protected $name = 'go_away';

    /**
     * 插入一条数据
     * @param $data
     * @return int|string
     */
    public function insertData($data){
        return $this -> insert($data);
    }
}