<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/15
 * Time: 21:10
 */

namespace app\model;


use think\Model;

class UserClubModel extends Model
{
    protected $name = 'user_club';

    public function getSomeByWhere($where,$field = '*' ){
        return $this -> where( $where ) -> field( $field ) -> select();
    }
}