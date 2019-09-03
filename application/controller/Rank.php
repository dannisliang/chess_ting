<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/28
 * Time: 11:41
 */
namespace app\controller;
use think\Db;
use think\Log;
use think\Request;
use think\Session;
use app\model\SendBigData;
class Rank extends Base
{
    //排行榜
    public function rank_list(){

        $method = Request::instance()->method();
        if($method !== "POST"){
            return json(['code' => 3001,'mess' => '上传方法不正确']);
        }else{
            $opt = file_get_contents("php://input");
            $opt = json_decode($opt,true);
        }
        $check_match = $opt['match_date'];//0是今天,1是昨天
        $player_id = Session::get("player");

        $event_type = 'watch_rank';
        $sendbig = new SendBigData();
        $event_name = 'match_game_step';
        $sendbig->sendMatch($event_type,$content='',$event_name,$player_id);
        if($check_match == 0){
            //今天的排行榜
            $date = date('Y-m-d');
        }else{
            //昨天的排行榜
            $now_time = time();
            $one_days = $now_time-86400;
            $date = date('Y-m-d',$one_days);
        }
        //当前开着给的赛期
        $playground = Db::name('playground')
            ->where('status',1)
            ->field('match_date_start')
            ->select();
        $match_date_start = $playground[0]['match_date_start'];


        //排行榜数据
        $user_rank = Db::name('user_rank')
            ->where('match_day',$date)
            ->order('total_store desc')
            ->limit(50)
            ->select();

        if($user_rank){
            //判断这50个玩家里是否存在这个玩家
            $a = Db::name('user_rank')
                ->where('player_id',$player_id)
                ->field('total_store')
                ->select();
            if($a){
                $is_inrank = 1;//在里面
            }else{
                $is_inrank = 0;//不在里面
            }
            $num = count($user_rank);
            for($i=0;$i<$num;$i++){
                $uid = $user_rank[$i]['player_id'];
                if($is_inrank ==1){
                    //如果玩家id在里,则要返回当前玩家的名次
                    if($uid == $player_id){
                        $back_result['user_ranking'] = $i+1;//用户名次

                        $back_result['user_curent_score'] = $user_rank[$i]['total_store'];
                        break;
                    }else{
                        //没有的话去获取后台设计的局数
                        $back_result['user_ranking'] = NULL;//用户名次

                        $back_result['user_curent_score'] = NULL;
                    }
                }else{
                    $back_result['user_ranking'] = NULL;//用户名次
                    $back_result['user_curent_score'] = NULL;
                }
            }
            for ($j=0;$j<$num;$j++){
                $playerid = $user_rank[$j]['player_id'];
                $player_name = $user_rank[$j]['nick_name'];
                $player_name  = base64_decode($player_name);
                $back_result['player_infos'][$j]['user_image'] = $user_rank[$j]['user_image'];//玩家头像地址
                $back_result['player_infos'][$j]['user_name'] = $player_name;//玩家名字
                $back_result['player_infos'][$j]['user_store'] = $user_rank[$j]['total_store'];//玩家总得分
                $county_a = $user_rank[$j]['county'];
                $city_a = $user_rank[$j]['city'];
                if($county_a){
                    $county = $county_a;
                }else{
                    $county ='';
                }
                if($city_a){
                    $city = $city_a;
                }else{
                    $city = '';
                }
                $back_result['player_infos'][$j]['user_city'] = "$county".' '."$city";//玩家所在城市
            }
        }else{
            //如果不存在返回空
            $back_result['user_ranking'] = NULL;  //用户名次
            $back_result['user_match_num'] = NULL;//剩余比赛场次
            $back_result['user_curent_score'] = NULL;//用户当前得分
            $back_result['player_infos'] = array();//玩家信息
            return json(['code'=>0,'data'=>$back_result]);
        }

        $match_num = $this->player_match_num($player_id,$date);
        $back_result['user_match_num'] = $match_num;//剩余比赛场次
        $back_result['match_date'] = $match_date_start;//赛期
        return json(['code'=>0,'data'=>$back_result]);

    }
    //玩家比赛详情
    public function user_match_infos(){
        $player_id = Session::get('player');
        $user_sign = Db::name('user_sign_up')
            ->where('player_id',$player_id)
            ->count(0);
        if($user_sign){
            $result['is_sign_up'] = 1;//玩家是否报名
            $match_num = $this->player_match_num($player_id);
            $result['match_num'] = (int)$match_num;//剩余比赛的次数
            Log::write($match_num,'$match_num_log');
        }else{
            $result['is_sign_up'] = 0;//玩家是否报名
            $result['match_num'] = NULL;//玩家剩余比赛的次数
        }
        //如果存在说明已经报名,则需要把比赛时间返回
        $now_day = date('Y-m-d H:i:s');
        $now_date = date('Y-m-d');//比赛开始天
        Log::write($now_date,'$now_date_log');
        $now_time = date('H:i:s');//比赛开始时间
        $a = Db::query("SELECT * FROM tb_playground WHERE status = 1");//符合比赛的数据
        Log::write($a,'$a_log');
        if($a){
            //未开始比赛
            $sign_str_time = $a[0]['registration_time_start'];//报名开始时间
            $sign_end_time = $a[0]['registration_time_end'];//报名结束时间
            $match_str_day = $a[0]['match_date_start'];
            $match_str_date = $a[0]['match_time_start'];
            $str_day = date('Y-m-d');
            $match_end_day = $a[0]['match_date_end'];
            $match_end_date = $a[0]['match_time_end'];
            $end_day = date('Y-m-d');
            $match_date_end = "$end_day"."$match_end_date";//比赛结束时间
            if(strtotime($match_str_day)>=strtotime($now_date)){
                $match_date_str = "$match_str_day"."$match_str_date";//比赛开始时间

            }else{
                $match_date_str = "$str_day"."$match_str_date";//比赛开始时间
                $match_str_day = $str_day;
            }
            $match_date = $this->getmatch_time($match_str_day,$match_time1='');//获取天
            $result['match_month'] = $match_date['month'];
            $result['match_day'] = $match_date['day'];
            $match_time = $this->getmatch_time($match_day = '',$match_str_date);//获取小时,分钟
            $result['match_minute'] = $match_time['minute'];
            $result['match_hour'] = $match_time['hour'];
        }else{
            $result['sign_str_time'] = NULL;
            $result['sign_end_time'] = NULL;
            $result['match_date_end'] = NULL;
            $result['match_date_str'] = NULL;
            return json(['code'=>0,'data'=>$result]);
        }
        $result['sign_str_time'] = strtotime($sign_str_time);
        $result['sign_end_time'] = strtotime($sign_end_time);
        $result['match_date_str'] = strtotime($match_date_str);
        $result['match_date_end'] = strtotime($match_date_end);

        $sendbig = new SendBigData();
        $event_name = 'match_game_step';
        $event_type = 'enter_hall';
        $sendbig->sendMatch($event_type,$content='',$event_name,$player_id);

        return json(['code'=>0,'data'=>$result]);
    }
    //获取玩家的比赛剩余场次
    public function player_match_num($player_id,$match_date =''){

        //查出单当前期的比赛局数
        $playground = Db::name('playground')
            ->where('status',1)
            ->field('opportunity,id')
            ->select();
        if(!$playground){
            $num = 10;
            Log::write('$num_a',$num);
            return $num;
        }
        $cur_match_id = $playground[0]['id'];//当前开着的赛期ID
        $match_num = $playground[0]['opportunity'];//当前赛期玩家每天可以进行比赛的次数
        if($match_date){
            $match_num_opt = Db::name('user_match_num')
                ->where('player_id',$player_id)
                ->where('match_str_time',$match_date)
                ->where('match_id',$cur_match_id)
                ->field('match_num')
                ->select();
            if(!$match_num_opt){
                $num = $match_num;
            }else{
                $num = $match_num_opt[0]['match_num'];
                $num = $match_num-$num;
            }

            return $num;
        }


        $date = date('Y-m-d');
        $match_num_opt = Db::name('user_match_num')
            ->where('player_id',$player_id)
            ->where('match_str_time',$date)
            ->field('match_num,match_id')
            ->select();
        if(!$match_num_opt){
            $num = $match_num;
            return $num;
        }
        $his_match_id = $match_num_opt[0]['match_id'];
        if($his_match_id != $cur_match_id){
            $num = $match_num;
            return $num;
        }
        $user_match_num = $match_num_opt[0]['match_num'];
        $num = $match_num-$user_match_num;
        return $num;
    }
    //获取比赛开始的月,天,小时,分钟
    public function getmatch_time($day='',$time=''){
        if($day){
            $a = explode('-',$day);
            $month = $a[1];
            $day = $a[2];
            $result['month'] = (int)$month;
            $result['day'] = $day;
            return $result;
        }
        if($time){
            $a = explode(':',$time);
            $minute = $a[1];
            $hour = $a[0];
            $result['minute'] = $minute;
            $result['hour'] = $hour;
            return $result;
        }

    }
    //判断玩家的比赛次数是否足够,比赛开始时间是否到了
    public function ready_match(){
        $player_id = Session::get("player");

        //获取比赛的开始时间
        $tb_playground = Db::query("SELECT match_date_start,match_time_start,match_time_end,match_date_end,opportunity FROM tb_playground WHERE status = 1");//符合比赛的数据
        //比赛的开始时间
        $date = date('Y-m-d');
        $date_time = date('H:i:s');
        if($tb_playground){
            $match_str_date = $tb_playground[0]['match_date_start'];//数据库里的比赛开始的日期
            $match_str_time = $tb_playground[0]['match_time_start'];//比赛开始的时间
            $str_date_time = "$date".''."$match_str_time";//实际上每天应该开始的时间
        }else{
            return json(['code'=>23600,'mess'=>'比赛尚未开始']);
        }
        if($date_time<$match_str_time || $match_str_date>$date){
            return json(['code'=>23600,'mess'=>'比赛尚未开始']);
        }
        //报送大数据
        $sendbig = new SendBigData();
        $event_name = 'match_game_step';
        $event_type = 'start_match';
        $sendbig->sendMatch($event_type,$content='',$event_name,$player_id);
        return json(['code'=>0]);
    }

}