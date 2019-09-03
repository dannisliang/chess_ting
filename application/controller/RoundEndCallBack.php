<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/5/20
 * Time: 9:53
 */

namespace app\controller;


use think\Env;
use think\Log;
use Obs\ObsClient;
use Obs\ObsException;
use app\model\BeeSender;
use app\definition\RedisKey;
use think\cache\driver\Redis;

class RoundEndCallBack extends Base
{
    public function roundEndCallBack(){
        if(!isset($this->opt['faanNames']) || !isset($this->opt['score']) || !isset($this->opt['roomId']) || !isset($this->opt['round']) || !isset($this->opt['winnerIds']) || !isset($this->opt['duration']) || !isset($this->opt['playBack'])){
            return jsonRes(0);
        }
        if(!is_numeric($this->opt['set']) || !is_numeric($this->opt['round']) || !is_numeric($this->opt['roomId'])){
            return jsonRes(0);
        }
        $redis = new Redis();
        $redisHandle = $redis->handler();

        $roundId = date("Y-m-d", time()).'_'.$this->opt['roomId'].'_'.(isset($this->opt['set']) ? $this->opt['set'] : 0).'_'.$this->opt['round'];
        $roomHashInfo = $redisHandle->hMget(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'],
            ['roundEndInfo', 'roomOptionsId', 'roomTypeName', 'roomChannel', 'betNums', 'needUserNum', 'clubId', 'clubName',
                'clubRegionId', 'clubRegionName', 'clubMode', 'playerInfos', 'createTime',  'roomType', 'roomName']);

        $roundEndInfo = json_decode($roomHashInfo['roundEndInfo'], true);
        $roundEndInfo[] = [
            'score' => $this->opt['score'],
            'faanNames' => $this->opt['faanNames'],
            'duration' => $this->opt['duration'],
            'roundId' => $roundId,
            'roundEndTime' => date("Y-m-d H:i:s", time())
        ];
        $redisHandle->hSet(RedisKey::$USER_ROOM_KEY_HASH.$this->opt['roomId'], 'roundEndInfo', json_encode($roundEndInfo));

        //todo 双份存储牌局记录数据， 后期需要抛弃华为云存储， 上传速度太慢
        $redisHandle->set(RedisKey::$PLAY_BACK.$roundId, $this->opt['playBack'], array('EX' => bcmul(bcmul(3600, 24, 0), 4, 0)));

        //todo 上传牌局记录到华为云
        try{
            $obsClient = new ObsClient([
                'key' => Env::get('obs.key'),
                'secret' => Env::get('obs.secret'),
                'endpoint' => Env::get('obs.endpoint')
            ]);
            $obsClient -> putObject([
                'Bucket' => Env::get('obs.chess_record'),
                'Key' => $roundId,
                'Body' => $this->opt['playBack']
            ]);
        }catch (ObsException $obsException){
            Log::write(json_encode($this->opt), "obsPutError");
        }



        # 算用户积分和用户积分
        $userIds = [];
        $userScore = [];
        foreach ($this->opt['score'] as $k => $v){
            $userScore[$v['playerId']] = $v['score'];
            $userIds[] = $v['playerId'];
        }
        $beeSender = new BeeSender(Env::get('app_id'), Env::get('app_name'), Env::get('service_ip'), config('app_debug'));

        $playerInfo = [];
        if(isset($roomHashInfo['playerInfos'])){
            $playerInfo = json_decode($roomHashInfo['playerInfos'], true);
        }

        if($playerInfo) {
            foreach ($playerInfo as $k => $userInfo) {
                $isWin = 'lose';
                $winType = '-';
                if (in_array($userInfo['userId'], $this->opt['winnerIds'])) {
                    $isWin = 'win';
                    $winType = json_encode($this->opt['faanNames']);
                }
                $score = 0;
                foreach ($this->opt['score'] as $kkk => $vvv) {
                    if ($vvv['playerId'] == $userInfo['userId']) {
                        $score = $vvv['score'];
                    }
                }
                $bigData = [
                    'server_id' => '-',
                    'user_id' => $userInfo['userId'],
                    'role_id' => '-' . '_' . $userInfo['userId'],
                    'role_name' => $userInfo['nickName'],
                    'client_id' => '-',
                    'client_type' => $userInfo['clientType'],
                    'system_type' => $userInfo['systemType'],
                    'ip' => $userInfo['ipAddr'],

                    'room_id' => strtotime($roomHashInfo['createTime']) . '_' . $this->opt['roomId'],
                    'room_type_id' => $roomHashInfo['roomType'],
                    'room_type_name' => $roomHashInfo['roomName'],
                    'room_channel' => $roomHashInfo['roomChannel'],
                    'table_id' => strtotime($roomHashInfo['createTime']) . '_' . $this->opt['roomId'] . '_' . (isset($this->opt['set']) ? $this->opt['set'] : 0) . '_' . $this->opt['round'],
                    'rule_detail' => '-',
                    'bet_num' => $roomHashInfo['betNums'],
                    'user_num' => $roomHashInfo['needUserNum'],
                    'win_lose' => $isWin,
                    'win_type' => $winType,
                    'token_name' => 'score',
                    'token_num' => $userScore[$userInfo['userId']],
                    'current_token' => $score,
                    'keep_time' => $this->opt['duration'],
                    'club_id' => $roomHashInfo['clubId'],
                    'club_name' => $roomHashInfo['clubName'],
                    'club_region_id' => $roomHashInfo['clubRegionId'],
                    'club_region_name' => $roomHashInfo['clubRegionName'],
                    'club_mode' => $roomHashInfo['clubMode'],
                ];
                $beeSender->send('table_finish', $bigData);
            }
        }

        return jsonRes(0);
    }
}