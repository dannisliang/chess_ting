<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/4/11
 * Time: 14:39
 */

namespace app\controller;


use app\model\AchievementModel;
use app\model\GainAchievement;
use app\model\PlayerRelationModel;

class Generalize extends Base
{
    /**
     * 绑定关系
     * @return \think\response\Json\
     */
    public function bandPlayerId(){
        $opt = ['p_player_id'];
        if(!has_keys($opt,$this->opt)){
            return jsonRes(3006);
        }
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes(9999);
        }
        $playerRelationModel = new PlayerRelationModel();
        //查询是否绑定
        $result = $playerRelationModel ->getOneByWhere(['player_id' => $player_id]);
        if($result){
            return jsonRes(3600);
        }
        $p_player_info = getUserBaseInfo($this->opt['p_player_id']);
        if (!$p_player_info){
            return jsonRes(3601);
        }
        $res = $playerRelationModel -> insertData(['player_id'=>$player_id,'p_player_id'=>$this->opt['p_player_id'],'insert_time'=>time()]);
        if(!$res){
            return jsonRes(3003);
        }
        $data = [
            'player_id' =>$this -> opt['p_player_id'],
            'nick_name' => $p_player_info['nickname'],
            'image_url' => $p_player_info['headimgurl'],
        ];
        return jsonRes(0 , $data);
    }

    /**
     * 获取绑定的玩家列表
     * @return \think\response\Json\
     */
    public function getPlayerList(){
        $player_id = getUserIdFromSession();
        if(!$player_id){
            return jsonRes(9999);
        }
        $playerRelationModel = new PlayerRelationModel();
        $players = $playerRelationModel -> getSomeByWhere(['p_player_id'=>$player_id]);

        $infos = [];
        if($players){
            $player_ids = [];
            foreach ($players as $player){
                $player_ids[] = $player['player_id'];
            }
            $playerInfos = getUserBaseInfos($player_ids);
            //获取玩家列表信息
            foreach ($playerInfos as $playerInfo){
                $temp['nick_name'] = $playerInfo['nickname'];
                $temp['player_id'] = $playerInfo['uid'];
                $temp['image_url'] = !empty($playerInfo['headimgurl']) ? $playerInfo['headimgurl']:'http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/0';
                $temp['last_login_time'] = strtotime($playerInfo['last_login_time']);
                $infos[] = $temp;
            }
        }

        //获取可领取的红包的数量
        $claim_number = $this -> getClaimNumber($player_id);
        //获取父id信息
        $p_player_info = $this -> getParentPlayerInfo($player_id);
        if(!$p_player_info){
            return jsonRes(3601);
        }
        $data = [
            'invite_list'  => $infos, //邀请列表信息
            'claim_number' => $claim_number, //可领取的红包券数量
            'p_player_id'    => $p_player_info['p_player_id'],
            'p_nick_name'    => $p_player_info['p_nick_name'],
            'p_img_url'    => $p_player_info['p_img_url']
        ];
        return json(['code'=>0,'mess'=>'获取数据成功','data'=>$data]);
    }

    /**
     * 获取父级用户的信息
     * @return bool|array|\think\response\Json\
     */
    private function getParentPlayerInfo($player_id){
        $playerRelationModel = new PlayerRelationModel();
        $playerRelationInfo = $playerRelationModel ->getOneByWhere(['player_id' => $player_id]);
        //不存在上级用户
        if(!$playerRelationInfo){
            $playerInfo = getUserBaseInfo($player_id);
            if(!$playerInfo){
                return false;
            }
            $data = [
                'p_player_id' => 0,
                'p_nick_name' => '',
                'p_img_url' => '',
            ];
            return $data;
        }
        //存在上级用户
        $player_ids = [
            (int)$playerRelationInfo['p_player_id']
        ];
        $playerInfos = getUserBaseInfos($player_ids);
        if(!$playerInfos){
            return false;
        }
        $data = [
            'p_player_id' => $playerInfos[0]['uid'],
            'p_nick_name' => $playerInfos[0]['nickname'],
            'p_img_url'   => $playerInfos[0]['headimgurl']
        ];
        return $data;
    }

    /**
     * todo 可领取红包券的数量
     * @return int
     */
    private function getClaimNumber($player_id){
        return 1;
    }

    /**
     * 获取成就列表
     * @return \think\response\Json\
     */
    public function getAchievementList(){
        $achievementModel = new AchievementModel();
        //获取所有成就
        $achievements = $achievementModel -> getSome();
        //TODO 添加是否可以去获取的条件
        foreach ($achievements as $achievement){
            switch ($achievement['type']){
                case 1:
                    //todo 判断条件
                    break;
                case 2:
                    //todo
                    break;
                default:
                    break;
            }
        }

        return jsonRes(0,$achievements);

    }

    /**
     * TODO 获取邀请的人数
     * @param $user_id
     * @return int
     */
    private function getInvitationNum($user_id){
        return 1;
    }

    /**
     * TODO 获取邀请玩家的充值钱数（超过最大显示最大）
     * @param $user_id
     * @return int
     */
    private function getChargeMoney($user_id){
        return 1;
    }

    /**
     * TODO 获取邀请玩家的耗钻数（超过最大显示最大）
     * @param $user_id
     * @return int
     */
    private function getCostDiamond($user_id){
        return 1;
    }

    /**
     * TODO 获取用户使用红包券兑换钻石数量（超过最大显示最大）
     * @param $user_id
     * @return int
     */
    private function getChangeDiamondNum($user_id){
        return 1;
    }

    /**
     * 判断玩家是否领取对应的奖励
     * @param $user_id
     * @param $type（成就类型）
     * @return int
     */
    private function getPlayerIsGain($user_id , $type){
        $gainAchievementModel = new GainAchievement();
        $result = $gainAchievementModel -> getOneByWhere(['player_id' => $user_id , 'type' => $type]);
        if(!$result){
            return 0;
        }
        return 1;
    }

    /**
     * 获取成就内容
     * @return \think\response\Json\
     */
    public function gainAchievement(){

        return jsonRes(0);
    }

}