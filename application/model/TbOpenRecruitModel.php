<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/12
 * Time: 17:04
 */

namespace app\model;


use think\Model;

class TbOpenRecruitModel extends Model
{
    //数据库操作的表名
    protected $name = 'open_recruit';

    /**
     * 获取招募代理的接口是否开关
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getIsOpen(){
        return $this -> where('id',1)-> field('is_open') ->find();
    }
}