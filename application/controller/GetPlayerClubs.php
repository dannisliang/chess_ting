<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/7/8
 * Time: 10:00
 */

namespace app\controller;


use app\model\UserClubModel;

class GetPlayerClubs extends Base
{
    /**
     * 获取玩家的俱乐部信息
     * @return \think\response\Json\
     */
    public function getPlayerClubList(){
        $opt = ['session_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        session_id($this->opt['session_id']);
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes(9999);
        }
        $userClubModel = new UserClubModel();
        $infos = $userClubModel -> getInfoByWhere(['a.player_id'=>$player_id,'a.status'=>1]);
        $data = [];
        if($infos){
            foreach ($infos as $info){
                $temp = [
                    'club_id' => $info['club_id'],
                    'club_name' => base64_decode($info['club_name'])
                ];
                $data[] = $temp;
            }
        }

        return jsonRes(0,$data);
    }
}