<?php
/**
 * Created by Xie.
 * User: DELL
 * Date: 2019/3/11
 * Time: 9:56
 */

namespace app\model;

use think\Model;

class PlayModel extends Model{

    protected $name = 'play';

    # 根据规则ID获取相关规则
    public function getPlayInfoByPlayId($playId){
        return $this->where('id', '=' , $playId)->find();
    }


}




