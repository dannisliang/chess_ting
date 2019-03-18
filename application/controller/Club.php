<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/15
 * Time: 14:38
 */

namespace app\controller;


use app\model\UserVipModel;
use think\Session;

class Club extends Base
{
    /**
     * 获取俱乐部
     * @return \think\response\Json\
     */
    public function getClubInfo(){
        $user_id = getUserIdFromSession();
        $opt = ['club_id'];
        if (!has_keys($opt,$this->opt,true)){
            return jsonRes(3006);
        }
        //获取用户的vip信息
        $this -> getUserVipInfo($user_id,$this ->opt['club_id']);
        return jsonRes(0);
    }

    private function getUserVipInfo($user_id,$club_id){
        $userVipModel = new UserVipModel();
        $where = [
            'a.club_id' => $club_id,
            'vip_status'=> 1,
            'uid'       => $user_id,
            'end_day'   => [
                '>',date('Y-m-d H:i:s',time())
            ]
        ];
        $userVipInfo = $userVipModel -> getOneByWhere($where);
        if($userVipInfo){
            //拼接的数据
            $user_vipinfos = [
                'vip_level' => $userVipInfo['type'],
                'vid'       => $userVipInfo['vid'],
                'vip_name'  => $userVipInfo['v_name']
            ];
            //获取用户的vip信息
            $list = [
                'end_day'     => strtotime($userVipInfo['end_day']),
                'cards_num'   => $userVipInfo['card_number'],
                'surplus_day' => strtotime($userVipInfo['end_day']) - time(),//会员卡剩余时间
                'user_vipinfos'=>$user_vipinfos,
            ];
        }
        var_dump($list);die;
        var_dump(json_decode($userVipInfo,true));die;
        return $vipInfo;
    }
}