<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/4/16
 * Time: 9:41
 */

namespace app\model;


use think\Model;

class AgentApplicationModel extends Model
{
    protected $name = 'agent_application';

    /**
     * 插入数据
     * @param $data
     * @return int|string
     */
    public function addData($data){
        return $this -> insert($data);
    }
}