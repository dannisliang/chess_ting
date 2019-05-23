<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/28
 * Time: 19:23
 */
namespace app\controller;

use app\model\AreaModel;
use app\model\BeeSender;
use app\model\ClubModel;
use think\db;
use think\Session;
use app\model\VipCardModel;
use app\model\UserVipModel;
use app\definition\RedisKey;
use think\cache\driver\Redis;
use think\Env;

class Vip extends Base{

    # 获取用户的所有可使用vip卡
    public function getUserVipCard(){
        if(!isset($this->opt['club_id']) || !is_numeric($this->opt['club_id'])){
            return jsonRes(3006);
        }

        $userSessionInfo = Session::get(RedisKey::$USER_SESSION_INFO);
        if(!$userSessionInfo){
            return jsonRes(9999);
        }

        $checkTokenRes = checkUserToken($userSessionInfo);
        if(!isset($checkTokenRes['result']) || ($checkTokenRes['result'] == false)){
            return jsonRes(9999);
        }

        $userVip = new UserVipModel();
        $userAllVipCard = $userVip->getUserAllVipCard($userSessionInfo['userid'], $this->opt['club_id']);
        if(!$userAllVipCard){
            return jsonRes(0, []);
        }

        $vipCard = new VipCardModel();
        $vipCardInfos = $vipCard->getAllVipCardInfo();
        $vipCardInfoArr = [];
        foreach ($vipCardInfos as $kk => $vv){
            $vipCardInfoArr[$vv['vip_id']] = $vv;
        }

        $i = 0;
        foreach ($userAllVipCard as $k => $val){
            if(isset($vipCardInfoArr[$val['vid']])){
                $vipCardInfo = $vipCardInfoArr[$val['vid']];
                $userAllVipCard[$i]['vip_name'] = $vipCardInfo['name'];
                $userAllVipCard[$i]['vip_level'] = $vipCardInfo['type'];
                $userAllVipCard[$i]['diamond_consumption'] = $vipCardInfo['diamond_consumption'];
                $userAllVipCard[$i]['icon'] = 'https://tjmahjong.chessvans.com/GMBackstage/public/'.$vipCardInfo['icon'];
                $userAllVipCard[$i]['number_day'] = $vipCardInfo['number_day'];
                $i++;
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
        $vipCardInfo = $vipCard->getVipCardInfo($this->opt['vid']);
        if(!$vipCardInfo){
            return jsonRes(3512);
        }

        $club = new ClubModel();
        $clubInfo = $club->getClubInfo($this->opt['club_id']);
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
            return jsonRes(9999);
        }

        $checkTokenRes = checkUserToken($userSessionInfo);
        if(!isset($checkTokenRes['result']) || ($checkTokenRes['result'] == false)){
            return jsonRes(9999);
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

        # 查询是否有此卡
        $userVip = new UserVipModel();
        $userVipCardInfo = $userVip->getUserVipCardInfo($userSessionInfo['userid'], $this->opt['club_id'], $this->opt['vid']);
        if(!$userVipCardInfo){
            $redisHandle->del($lockKey);
            return jsonRes(3514);
        }

        # 查询是否正使用此类型卡
        $nowVip = $userVip->getUserNowVipCardInfo($userSessionInfo['userid'], $this->opt['club_id'], $userVipCardInfo['vip_level']);
        if($nowVip){
            $endTime = strtotime($nowVip['end_day']);
            if($endTime > time()){ # 没过期
                $endDay = date("Y-m-d H:i:s", bcadd($endTime, bcmul($vipCardInfo['number_day'], bcmul(24, 3600, 0), 0), 0));
            }else{ # 过期了
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
            'club_name' => base64_decode($clubInfo['club_name']),
            'club_region_id' => $clubInfo['area_id'],
            'club_region_name' => $areaInfo['area_name'],
            'club_mode' => $clubMode,
            'reason' => '-',
            'props_id' => $this->opt['vid'],
            'props_name' => $vipCardInfo['name'],
            'props_num' => 1,
        ];
        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip'), config('app_debug'));
        $beeSender->send('props_use', $bigData);
        return jsonRes(3515);
    }
}