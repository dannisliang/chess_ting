<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:56
 */

namespace app\model;

use think\Model;

class TbPlay extends Model{

    protected $name = 'tb_play';

    # 根据规则ID获取相关规则
    public function getInfoById($id){
        return $this->where(['id', '=', $id])->find();
    }


}




