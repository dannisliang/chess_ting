<?php
/**
 * Created by PhpStorm.
 * User: PHP
 * Date: 2019/3/23
 * Time: 18:17
 */

namespace app\model;


use think\Db;
use think\Model;

class AreaModel extends Model
{
    protected $name = 'area';

    public function selectArea()
    {
        try{
            //查表
            $area = Db::query("SELECT area_name FROM tb_area WHERE area_name != '测试区' AND area_name != '市内六区' AND area_name != '其他区'");
            $num = count($area);
            $areas = array();//声明一个空数组
            if($area){
                for ($i=0;$i<$num;$i++){
                    $area_name = $area[$i]['area_name'];
                    $areas[$i]['name'] = $area_name;
                }
            }
            return $areas;
        }catch(\Exception $exception){
            return false;
        }
    }

    /**
     * 根据地域ID获取地域数据
     * @param $areaId
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoById($areaId)
    {
        return $this->where('aid', '=', $areaId)->find();
    }

    /**
     * 根据条件查找一条信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOneByWhere($where , $field = '*'){
        return $this ->where($where) ->field($field)->find();
    }

}