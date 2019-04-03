<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/28
 * Time: 19:23
 */
namespace app\controller;

use app\definition\Definition;
use app\model\AreaModel;
use app\model\BeeSender;
use app\model\ClubModel;
use think\db;
use think\Session;
use app\model\VipCardModel;
use app\model\UserVipModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class Vip extends Base{

    # 获取用户的所有可使用vip卡
    public function getUserVipCard(){
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        $userVip = new UserVipModel();
        $userAllVipCard = $userVip->getUserAllVipCard($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userAllVipCard){
            return jsonRes(0, []);
        }

        $vipCard = new VipCardModel();
        foreach ($userAllVipCard as $k => $val){
            $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($val['vid']);
            if(!$vipCardInfo){
                unset($userAllVipCard[$k]);
            }else{
                $userAllVipCard[$k]['vip_name'] = $vipCardInfo['name'];
                $userAllVipCard[$k]['vip_level'] = $vipCardInfo['type'];
                $userAllVipCard[$k]['diamond_consumption'] = $vipCardInfo['diamond_consumption'];
                $userAllVipCard[$k]['icon'] = 'https://tjmahjong.chessvans.com//GMBackstage/public/'.$vipCardInfo['icon'];
                $userAllVipCard[$k]['number_day'] = $vipCardInfo['number_day'];
            }
        }
        return jsonRes(0, $userAllVipCard);
    }

    # 用户使用vip卡
    public function useVipCard(){
        if(!isset($this->opt['club_id']) || !isset($this->opt['vid']) || !is_numeric($this->opt['club_id']) || !is_numeric($this->opt['vid'])){
            return jsonRes(3006);
        }

        $vipCard = new VipCardModel();
        $vipCardInfo = $vipCard->getVipCardInfoByVipCardId($this->opt['vid']);
        if(!$vipCardInfo){
            return jsonRes(3512);
        }

        $club = new ClubModel();
        $clubInfo = $club->getClubInfoByClubId($this->opt['club_id']);
        if(!$clubInfo){
            return jsonRes(3500);
        }

        $area = new AreaModel();
        $areaInfo = $area->getInfoById($clubInfo['area_id']);
        if(!$areaInfo){
            return jsonRes(3520);
        }
        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return jsonRes(3006);
        }

        $redis = new Redis();
        $redisHandle = $redis->handler();
        # 判断是否能使用此vip卡
        $getLock = false;
        $timeOut = bcadd(time(), 2, 0);
        $lockKey = RedisKey::$USE_VIP_CARD.$userSessionInfo['userid'].$this->opt['club_id'].$this->opt['vid'].'lock';
        while(!$getLock){
            if(time() > $timeOut){
                break;
            }
            $getLock = $redisHandle->set($lockKey, 'lock', array('NX', 'EX' => 10));
            if($getLock){
                break;
            }
        }
        if(!$getLock){
            return jsonRes(3513);
        }

        $userVip = new UserVipModel();
        $userVipCardInfo = $userVip->getUserVipCardInfo($userSessionInfo['userid'], $this->opt['club_id'], $this->opt['vid']);
        if(!$userVipCardInfo){
            $redisHandle->del($lockKey);
            return jsonRes(3514);
        }

        # 计算会员卡持续时间
        if($userVipCardInfo['vip_status'] == 1){ # 当前使用此会员卡
            $endTime = strtotime($userVipCardInfo['end_day']);
            if($endTime > time()){ # 会员卡没有到期
                $endDay = date("Y-m-d H:i:s", bcadd($endTime, bcmul($vipCardInfo['number_day'], bcmul(24, 3600, 0), 0), 0));
            }else{
                $endDay = date("Y-m-d H:i:s", bcadd(time(), bcmul($vipCardInfo['number_day'], bcmul(24, 3600, 0), 0), 0));
            }
        }else{
            $endDay = date("Y-m-d H:i:s", bcadd(time(), bcmul($vipCardInfo['number_day'], bcmul(24, 3600, 0), 0), 0));
        }

        $cardNumber = bcsub($userVipCardInfo['card_number'], 1, 0);
        # 修改所有的本俱乐部其他卡片为status=0    本卡片数量-1并修改本卡片的过期时间status=1
        try{
            Db::startTrans();
            $userVip->where('uid', '=', $userSessionInfo['userid'])->where('club_id', '=', $this->opt['club_id'])->update(['vip_status' => 0]);
            $userVip->where('uid', '=', $userSessionInfo['userid'])->where('club_id', '=', $this->opt['club_id'])->where('vid', '=', $this->opt['vid'])->update(['vip_status' => 1, 'end_day' => $endDay, 'card_number' => $cardNumber]);
            Db::commit();
            $redisHandle->del($lockKey);
        }catch(\Exception $e){
            Db::rollback();
            $redisHandle->del($lockKey);
            return jsonRes(3513);
        }

        // Todo 报送大数据
        $clubMode = 'divide'; # 免费房
        if($clubInfo['club_type'] == 1){
            $clubMode = 'free';
        }
        $bigData = [
            'server_id' => '-',
            'user_id' => $userSessionInfo['userid'],
            'role_id' => '-'.'_'.$userSessionInfo['userid'],
            'role_name' => $userSessionInfo['nickname'],
            'client_id' => '-',
            'client_type' => $userSessionInfo['client_type'],
            'system_type' => $userSessionInfo['app_type'],
            'ip' => $userSessionInfo['ip'],

            'club_id' => $this->opt['club_id'],
            'club_name' => $clubInfo['club_name'],
            'club_region_id' => $clubInfo['clubRegionId'],
            'club_region_name' => $areaInfo['area_name'],
            'club_mode' => $clubMode,
            'reason' => '-',
            'props_id' => $this->opt['vid'],
            'props_name' => '会员卡',
            'props_num' => 1,
        ];
        $beeSender = new BeeSender(Definition::$CESHI_APPID, Definition::$MY_APP_NAME, Definition::$SERVICE_IP, config('app_debug'));
        $beeSender->send('room_join', $bigData);
        return jsonRes(3515);
    }
}