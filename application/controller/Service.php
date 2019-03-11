<?php
namespace app\controller;
use think\Controller;
use think\Log;
use think\Request;
use think\Session;
use think\Db;
class Service extends Controller
{
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/getToken.php",
     *     summary="获取客户端token",
     *     description="返回结果",
     *     tags={"token-controller"},
     *     @SWG\Parameter(
     *         description="玩家ID",
     *         in="formData",
     *         name="player_id",
     *         required=true,
     *         type="string",
     *     ),
     *      @SWG\Parameter(
     *         description="用户token",
     *         in="formData",
     *         name="token",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=0,
     *         description="{'code':0,'msg':'验证成功'}",
     *     ),
     *     @SWG\Response(
     *         response=3001,
     *         description="{'code':3001,'msg':'请求方法不正确'}",
     *     ),
     *     @SWG\Response(
     *         response=3002,
     *         description="{'code':3002,'msg':'验证未通过'}"
     *     ),
     *     @SWG\Response(
     *         response=3002,
     *         description="{'code':3006,'msg':'缺少应上传参数'}"
     *     ),
     *     @SWG\Response(
     *         response=3002,
     *         description="{'code':3007,'msg':'有参数值为空'}"
     *     ),
     *
     * )
     */
    function getToken()
    {

        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'result' => '', 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
            Log::write($post,'post_opt');
        }
        $player_id = $post['player_id'];
        /*'client_type' => 'ANDROID',
                'app_type' => 'h5',*/
        if(array_key_exists('client_type', $post)){
            $client_type = $post['client_type'];
            $client_type = strtolower($client_type);
        }else{
            $client_type = '-';
        }
        if(array_key_exists('app_type', $post)){
            $app_type = $post['app_type'];
        }else{
            $app_type = '-';
        }

        //判断是否传了用户ID
        if (!array_key_exists('player_id', $post) || !array_key_exists('token', $post)) {
            return json(['code' => 3006, 'mess' => '缺少应上传参数']);
        } else if ($post['player_id'] === '' || $post['token'] === '') {
            return json(['code' => 3007, 'mess' => '有参数值为空']);
        }
        //获取ip
        $unknown = 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (false !== strpos($ip, ',')) {
            $ip = reset(explode(',', $ip));
        }
        //调用宋福旺接口验证token
        $url = WEB_API_URL . 'api/v3/authenticate.php';
        $data['uid'] = $post['player_id'];
        $data['ip'] = $ip;
        $data['token'] = $post['token'];
        $user_token = $post['token'];
        $data = json_encode($data);
        $list = postInterface($url, $data);
        $list = json_decode($list,true);

        if ($list['result'] == 'true') {
            Session::set("player", "$player_id", "think");
            Session::set("$player_id","$user_token","think");
            $a = '1'."$player_id";
            $b = '2'."$player_id";
            Session::set("$a","$client_type","think");//机型
            Session::set("$b","$app_type","think");//app还是h5
            $id = session_id();
            $result['session_id'] = $id;
            $result['curent_time'] = (int)strtotime(date("Y-m-d H:i:s", time()));


            return json(['code' => 0, 'mess' => '验证成功', 'data' => $result]);
        } else {
            return json(['code' => 3002, 'mess' => '验证未通过']);
        }
    }

    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/getUserInfo.php",
     *     summary="获取玩家信息",
     *     description="返回指定数据",
     *     tags={"player-controller"},
     *     @SWG\Response(
     *         response=0,
     *         description="{'code':0,'msg':'操作成功'}",
     *     ),
     *      @SWG\Response(
     *         response=3001,
     *         description="{'code':'3001','msg':'请求方法不正确'}"
     *     ),
     *     @SWG\Response(
     *         response=3003,
     *         description="{'code':'3003','msg':'获取用户失败'}"
     *     ),
     *     @SWG\Response(
     *         response=3004,
     *         description="{'code':'3004','msg':'查询失败,用户不存在'}"
     *     ),
     * )
     */
    function getUserInfo(){
//    Session::set("player",997264);
        $user_id = Session::get("player");
        /*$user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'result' => '', 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        //调用刘进勇的接口获取邮件列表,按id来查询
        $uurl = WEB_USER_URL .'/api/email_list.php';
        $udata['recipient'] = Session::get('player');
        $udata['appid'] = CESHI_APPID;
        $udata = json_encode($udata);
        $maillist = postInterface($uurl, $udata);
        $maillist = json_decode($maillist, true);
        $count = count($maillist['data']);
        $num = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($maillist['data'][$i]['read_status'] == 0) {
                $num += 1;
            } else if ($i == $count) {
                break;
            }
        }
        //调取宋福旺接口,获取用户的基本信息
        $data['uid'] = Session::get('player');
        $playerssss = Session::get('player');
        $data['app_id'] = CESHI_APPID;
        $data = json_encode($data);
        $url = WEB_API_URL . 'api/get_info.php';
        $list = postInterface($url, $data);
        $list = json_decode($list, true);
        //查玩家与俱乐部关联表
        $result = array();
    	if(!array_key_exists('tel_number',$list['data'])){
            $result['phone_num'] = '';
        }else{
            $result['phone_num'] =  (int)$list['data']['tel_number'];
        }
        //是否显示招募代理入口
        $file_path = "application/open_recruit.php";
        $str = file_get_contents($file_path);//将整个文件内容读入到一个字符串中
        $str = str_replace("\r\n","<br />",$str);
        $str = json_decode($str,true);
        $is_open = $str['is_open'];//客户端的代理申请的显示(0不显示,1显示)
        $content = $str['content'];
        $result['agent_recruit'] = (int)$is_open;
        $result['player_id'] = Session::get('player');
        $player_id = Session::get("player");
        //查出玩家上次所载的俱乐部ID和俱乐部名字
        $clubid = Db::query("SELECT club_id FROM tb_user_last_club WHERE player_id = $player_id");
        if ($clubid) {
            $club_id = $clubid[0]['club_id'];
            //根据俱乐部ID查出俱乐部名字,
            $club_name = Db::query("SELECT club_name FROM tb_club WHERE cid = $club_id");
            if (!$club_name){
                return json(['code'=>3999,'mess'=>'数据库查询失败']);
            }
            $user_club_name = $club_name[0]['club_name'];
            $user_club_name = base64_decode($user_club_name);
            $result['club_name'] = $user_club_name;
        } else {
            $club_id = "";
            $result['club_name'] = "";
        }
        if ($clubid) {
            $result['nickname'] = $list['data']['nickname'];
            $result['head_img'] = $list['data']['headimgurl'];
            $result['club_id'] = (int)$club_id;
            $result['new_mail'] = (int)$num;
        } else {
            $result['nickname'] = $list['data']['nickname'];
            $result['head_img'] = $list['data']['headimgurl'];
            $result['new_mail'] = (int)$num;
        }


        //检查玩家在哪个房间
        $check_obj = new Base();
        $result_check = $check_obj->check_player($player_id);
        Log::write($result_check,'$result_check');
        if($result_check){
            //如果存在需要调用逻辑服的麻将和扑克验证下,如果不存在,不存在说明就没加入过房间
            $result['socket_url'] = $result_check['socket_url'];
            $result['socket_h5'] = $result_check['socket_h5'];
            $result['room_id'] = $result_check['room_num'];
            $room_num = $result_check['room_id'];
            if ($room_num){
                $match_id = $result_check['match_id'];
                //获取options
                $options_opt = Db::query("SELECT room_type,options FROM tb_room_options WHERE id = $match_id");
                if (!$options_opt){
                    /*$options_opt = Db::query("SELECT room_type,options FROM tb_room WHERE room_num = $room_num AND room_status = '1' AND game_id = $match_id");
                    if(!$options_opt){
                        $options_opt = Db::query("SELECT room_type,options FROM tb_room WHERE room_num = $room_num AND room_status = '5' AND game_id = $match_id");
                    }*/
                    $options_opt = Db::name('user_room')
                        ->where('room_num',$room_num)
                        ->field('play_type,options')
                        ->select();
                    $options_opt[0]['room_type'] = $options_opt[0]['play_type'];
                }
                $room_type = $options_opt[0]['room_type'];
                //获取check
                $play_opt = Db::query("SELECT play FROM tb_play WHERE id = $room_type");
                if($options_opt){
                    $options = $options_opt[0]['options'];
                    $options = json_decode($options,true);
                }else{
                    $options = [];
                }
                $room_rule = $play_opt[0]['play'];
                $room_rule = json_decode($room_rule,true);
                $check = $room_rule['checks'];
                $result['check'] = $check;
                $result['options'] = $options;
            }

        }else{
            $result['room_id'] = '';
        }
        //获取用户的评价数量
        $evaluate_opt = Db::query("SELECT good_num,bad_num FROM tb_user_evaluate WHERE player_id = $player_id");
        if ($evaluate_opt){
            $good_num = $evaluate_opt[0]['good_num'];
            $bad_num = $evaluate_opt[0]['bad_num'];
        }else{
            $good_num = 0;
            $bad_num = 0;
        }
        if ($list['code'] == 0) {
            $result['socket_ssl'] = SOCKET_SSL;
            $result['notification_h5'] = NOTIFICATION_H5;
            $result['notification_url'] = NOTIFICATION_URL;
            $result['good_nums'] = $good_num;
            $result['bad_nums'] = $bad_num;
            //获取用户资产金币
            $property = 10000;
            $property_list = getUserProperty($player_id,$property);
            if ($property_list){
                $result['gold_num'] = $property_list[0]['property_num'];
            }else{
                $result['gold_num'] = 0;
            }
            $property2 = 10001;
            $property_list2 = getUserProperty($player_id,$property2);
            if ($property_list2){
                $usr_dia1 = $property_list2[0]['property_num'];
            }else{
                $usr_dia1 = 0;
            }
            $property3 = 10002;
            $property_list3 = getUserProperty($player_id,$property3);
            if ($property_list3){
                $user_dia = $property_list3[0]['property_num'];
            }else{
                $user_dia = 0;
            }
            //查询房间表
            $result['diamond_num'] =$user_dia+ $usr_dia1;
            return json(['code' => 0, 'mess' => '请求成功', 'data' => $result]);
        } else if ($list['code'] == 1061) {
            return json(['code' => 3003, 'mess' => '获取用户失败']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/getBulletinList.php",
     *     summary="跑马灯信息",
     *     description="返回跑马灯列表数据",
     *     tags={"lamp-controller"},
     *     @SWG\Response(
     *         response=0,
     *         description="{'code':0,'msg':'操作成功','data':{}}",
     *     ),
     *     @SWG\Response(
     *         response="3001",
     *         description="{'code':'3001','msg':'请求方法不正确','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3005          ",
     *         description="{'code':'3005','msg':'不允许上传参数','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'查询不到数据','data':{}}"
     *     ),
     *
     * )
     */
    function getBulletinList()
    {
    //Session::set("player",997264);
        $user_id = Session::get("player");
        $user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        /*if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'result' => '', 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        if (!empty($post)) {
            return json(['code' => 3005, 'mess' => '不允许上传参数']);
        }
        $data['appid'] = CESHI_APPID;
        $data['status'] = 1;
        $data = json_encode($data);
        //调用跑马灯的接口
        $url = WEB_USER_URL . '/api/horse_list.php';
        $bulletinlist = postInterface($url, $data);
        $bulletinlist = json_decode($bulletinlist, true);
        $count = count($bulletinlist['data']);
        if ($bulletinlist['code'] == 0) {
            $result = array();
            for ($i = 0; $i < $count; $i++) {
                $result[$i]['content'] = $bulletinlist['data'][$i]['content'];
                $result[$i]['speed'] = (float)$bulletinlist['data'][$i]['speed'];
                $result[$i]['interval'] = (int)$bulletinlist['data'][$i]['interval_time'];
            }
            return json(['code' => 0, 'mess' => '请求成功', 'data' => $result]);
        } else if ($bulletinlist['code'] == 2001) {
            return json(['code' => 3004, 'mess' => '查询不到数据']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/getMailList.php",
     *     summary="邮件列表",
     *     description="返回玩家的邮件",
     *     tags={"email-controller"},
     *     @SWG\Parameter(
     *         description="邮件类型(1为收件,0位发件)",
     *         in="formData",
     *         name="mail_type",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         description="返回结果条数",
     *         in="formData",
     *         name="limit",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response="100",
     *         description="{'code':0,'msg':'操作成功'}",
     *     ),
     *     @SWG\Response(
     *         response="3001",
     *         description="{'code':'3001','msg':'请求方法不正确'}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'查询失败'}"
     *     ),
     *     @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'没有上传参数'}"
     *     ),
     *
     * )
     */
    function getMailList()
    {
        $user_id = Session::get("player");
        /*$user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        //获取前端发送过来的全部的post数据
        $request = Request::instance();
        $method = $request->method();
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");

            $post = json_decode($post, true);
        }
        if (empty($post) || !array_key_exists('mail_type', $post)) {
            return json(['code' => 3006, 'mess' => '参数没有上传']);
        }

        //调用curl方法,获得到接口里的数据,然后传递给前台
        $data['appid'] = CESHI_APPID;
        if ($post['mail_type'] == 1) {
            $data['recipient'] = Session::get('player');
        } else {
            $data['sender'] = Session::get('player');
        }
        $data = json_encode($data);

        $url = WEB_USER_URL . '/api/email_list.php';
        $list = postInterface($url, $data);

        $list = json_decode($list, true);
        $count = count($list['data']);
        $result = array();
        for ($i = 0; $i < $count; $i++) {
            $result[$i]['mail_id'] = (int)$list['data'][$i]['id'];
            $result[$i]['is_read'] = (int)$list['data'][$i]['read_status'];
            if ($list['data'][$i]['sender'] == 0) {
                $result[$i]['mail_type'] = 1;
            } else {
                $result[$i]['mail_type'] = 2;
                //获取俱乐部名字
                $club_id = $list['data'][$i]['sender'];
                $club_name = getCLubName($club_id);
                $result[$i]['club_name'] = $club_name;
            }
            $result[$i]['title'] = $list['data'][$i]['title'];
            $result[$i]['date'] = strtotime($list['data'][$i]['send_time']);
            $result[$i]['sender'] = $list['data'][$i]['sender'];
            if ($list['data'][$i]['receive_status'] == 1) {
                $result[$i]['is_get_reward'] = (int)1;
            } else {
                $result[$i]['is_get_reward'] = (int)0;
            }
        }
        //调用宋福旺的接口,查出uid指定的昵称
        if ($list['code'] == 0) {
            return json(['code' => 0, 'mess' => '请求成功', 'data' => $result]);
        } else {
            return json(['code' => 3004, 'mess' => '查询失败,没有查询到数据']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/GetMailDetail.php",
     *     summary="邮件详细信息",
     *     description="返回玩家的邮件纤细信息",
     *     tags={"email-controller"},
     *     @SWG\Parameter(
     *         description="邮件ID",
     *         in="formData",
     *         name="mail_id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response="0",
     *         description="{'code':0,'msg':'操作成功'}",
     *     ),
     *     @SWG\Response(
     *         response="3001",
     *         description="{'code':'3001','msg':'请求方法不正确'}"
     *     ),
     *      @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'参数没有上传'}"
     *     ),
     *     @SWG\Response(
     *         response="3007",
     *         description="{'code':'3007','msg':'参数值不能为空'}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'系统错误,联系接口负责人'}"
     *     ),
     *
     * )
     */
    function GetMailDetail()
    {

        $user_id = Session::get("player");
        /*$user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        $request = Request::instance();
        $method = $request->method();
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
        }
        $post = json_decode($post, true);
        if (!array_key_exists('mail_id', $post)) {
            return json(['code' => 3006, 'mess' => '参数没有上传']);
        } else if ($post['mail_id'] == '') {
            return json(['code' => 3007, 'mess' => '参数值不能为空']);
        }
        $data['appid'] = CESHI_APPID;
        $data['id'] = $post['mail_id'];
        $data['playerId'] = Session::get('player');
        $data = json_encode($data);
        Log::write($data,'optdata');
        $url = WEB_USER_URL .'/api/email_detail.php';
        $list = postInterface($url, $data);
        $list = json_decode($list, true);
        if ($list['code'] == 0) {
            $sender = $list['data']['sender'];
            if($sender == 0){
                $email_type = 1;
            }else{
                //如果不是,获取俱乐部的得名字
                $club_name = getCLubName($sender);
                $email_type = 2;
                $result['club_name'] = $club_name;
            }

            if ($list['data']['content'] == '') {
                $content = '';
            } else {
                $content = $list['data']['content'];
            }
            if ($list['data']['goods'] == '') {
                $result['rewards'] = '';
            } else {
                $goods = $list['data']['goods'];
                $goods = json_decode($goods,true);
                $goods_name = array();
                $goods_counts = array();
                foreach ($goods as $k=>$v){
                    array_push($goods_name,$k);
                    array_push($goods_counts,$v);
                }
                $new_goods = array();
                for ($i=0;$i<count($goods_name);$i++){
                    $goods_type = $goods_name[$i];
                    if(strpos("$goods_type",'_') !== false){
                        //'包含_';
                        $opt = explode('_',"$goods_type");

                        $vip_id = $opt[1];
                        $num = count($opt);
                        if($num==3){
                            //说明是绑钻
                            $new_goods[$i]['good_name'] = 10002;
                            $new_goods[$i]['good_counts'] = $goods_counts[$i];
                        }else{
                            //说明是VIP卡
                            $new_goods[$i]['good_name'] = 10004;
                            $new_goods[$i]['vip_id'] = (int)$vip_id;
                            $new_goods[$i]['good_counts'] = $goods_counts[$i];
                            $vip_id = $opt[1];
                            //查出vip卡的图片
                            $vip_opt = Db::query("SELECT icon FROM tb_vip_card WHERE vip_id = $vip_id");
                            $vip_icon = $vip_opt[0]['icon'];
                            $new_goods[$i]['vip_icon'] = "https://tjmahjong.chessvans.com//GMBackstage/public/"."$vip_icon";
                        }
                    }else{
                        $new_goods[$i]['good_name'] = $goods_name[$i];
                        $new_goods[$i]['good_counts'] = $goods_counts[$i];
                    }
                }
                $result['rewards'] = $new_goods;
            }
            $result['content'] = $content;
            $result['email_type'] = $email_type;
            $liujinyon = $list['data'];

            $read_status = $liujinyon['read_status'];
            $recive_statua = $liujinyon['receive_status'];


            if($read_status ==1 && $recive_statua ==1){
                //都为1则删除
                $url = WEB_USER_URL .'/api/email_del.php';

                $datadel['appid'] = CESHI_APPID;
                $datadel['id'] = $post['mail_id'];
                $datadel = json_encode($datadel);

                postInterface($url,$datadel);

            }elseif($read_status ==1 && $liujinyon['goods'] == ''){
                $url = WEB_USER_URL .'/api/email_del.php';

                $datadel['appid'] = CESHI_APPID;
                $datadel['id'] = $post['mail_id'];
                $datadel = json_encode($datadel);

                postInterface($url,$datadel);

            }

            return json(['code' => 0, 'mess' => '请求成功', 'data' => $result]);
        } else {
            return json(['code' => 3004, 'mess' => '获取失败']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/deleteMail.php",
     *     summary="删除邮件",
     *     description="成功返回标记",
     *     tags={"email-controller"},
     *     @SWG\Parameter(
     *         description="邮件ID",
     *         in="formData",
     *         name="mail_id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response="100",
     *         description="{'code':0,'msg':'操作成功'}",
     *     ),
     *     @SWG\Response(
     *         response="3001",
     *         description="{'code':'3001','msg':'请求方法不正确'}"
     *     ),
     *      @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'参数没有上传'}"
     *     ),
     *     @SWG\Response(
     *         response="3007",
     *         description="{'code':'3007','msg':'参数值不能为空'}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'删除失败'}"
     *     ),
     *
     * )
     */
    function deleteMail()
    {

        $request = Request::instance();
        $method = $request->method();
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        if (!array_key_exists('mail_id', $post)) {
            return json(['code' => 3006, 'mess' => '参数没有上传']);
        } else if ($post['mail_id'] == '') {
            return json(['code' => 3007, 'mess' => '参数值不能为空']);
        }
        $data['appid'] = CESHI_APPID;
        $data['id'] = $post['mail_id'];
        $id = $post['mail_id'];

        $player = Session::get("player");
        if($id['mail_id'] == '0'){
            //查询该玩家所有的邮件
            $urls = WEB_USER_URL .'/api/email_list.php';

            $datas['appid'] = CESHI_APPID;
            $datas['recipient'] = $player;
            $datas = json_encode($datas);
            $email_list = postInterface($urls,$datas);
            $email_list = json_decode($email_list,true);

            $mail_list = array();
            for($i=0;$i<count($email_list['data']);$i++){
                $mail_list[$i] = $email_list['data'][$i]['id'];
            }
            $url = WEB_USER_URL .'/api/email_del_list.php';
            $data['ids'] = $mail_list;
        }else{
            $url = WEB_USER_URL .'/api/email_del.php';
        }
        $data = json_encode($data);
        $list = postInterface($url, $data);

        $list = json_decode($list, true);

        if ($list['code'] == 0) {
            return json(['code' => 0, 'mess' => '删除成功']);
        } else {
            return json(['code' => 3004, 'mess' => '删除失败']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/sendMail.php",
     *     summary="发送邮件",
     *     description="成功会返回成功消息",
     *     tags={"email-controller"},
     *     @SWG\Parameter(
     *         description="收件人ID",
     *         in="formData",
     *         name="receiver_id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         description="邮件标题",
     *         in="formData",
     *         name="title",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Parameter(
     *         description="邮件内容",
     *         in="formData",
     *         name="content",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response="0",
     *         description="{'code':0,'msg':'操作成功'}",
     *     ),
     *     @SWG\Response(
     *         response="3001",
     *         description="{'code':'3001','msg':'请求方法不正确'}"
     *     ),
     *      @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'存在没有上传的参数'}"
     *     ),
     *     @SWG\Response(
     *         response="3007",
     *         description="{'code':'3007','msg':'参数值不能为空'}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'发送失败'}"
     *     ),
     *     @SWG\Response(
     *         response="3009",
     *         description="{'code':'3009','msg':'收件人不能是自己'}"
     *     ),
     *     @SWG\Response(
     *         response="3011",
     *         description="{'code':'3011','msg':'收件人ID不存在'}"
     *     ),
     *
     * )
     */
    function sendMail()
    {

        $player = Session::get("player");
        $request = Request::instance();
        $method = $request->method();
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        if (!$post) {
            return json(['code' => 3007, 'mess' => '上传数据为空']);
        }
        if (!array_key_exists('receiver_id', $post) || !array_key_exists('title', $post) || !array_key_exists('content', $post)) {
            return json(['code' => 3006, 'mess' => '存在没有上传的参数']);
        } else if ($post['receiver_id'] == '') {
            return json(['code' => 3007, 'mess' => '参数值不能为空']);
        } else if ($player == $post['receiver_id']) {
            return json(['code' => 3009, 'mess' => '收件人不能是自己']);
        } else {
            if ($post['title'] == '') {
                $data['title'] = '';
            } else {
                $data['title'] = $post['title'];
            }
            if ($post['content'] == '') {
                $data['content'] = '';
            } else {
                $data['content'] = $post['content'];
            }
            //查用户数据,看看接收人id是否存在如果不存在返回错误
            $datas['app_id'] = CESHI_APPID;
            $datas['uid'] = $post['receiver_id'];//接收人ID
            $datas = json_encode($datas);
            $url = WEB_API_URL . 'api/get_info.php';
            $list = postInterface($url, $datas);
            $list = json_decode($list, true);

            if ($list['code'] !== 0) {
                return json(['code' => 3011, 'mess' => '收件人ID不存在']);
            }
        }
        $data['appid'] = CESHI_APPID;
        $data['sender'] = $player;
        $data['recipient'] = $post['receiver_id'];
        $data['send_time'] = date('Y-m-d H:i:s', time());
        $data['read_status'] = 0;
        $data = json_encode($data);
        $url = WEB_USER_URL . '/api/email_add.php';
        $lists = postInterface($url, $data);
        $lists = json_decode($lists, true);
        if ($lists['code'] == 0) {
            return json(['code' => 0, 'mess' => '邮件发送成功']);
        } else if ($lists['code'] == 2001) {
            return json(['code' => 3007, 'mess' => 'sender参数不能为空']);
        } else {
            return json(['code' => 3004, 'mess' => '发送失败']);
        }
    }

    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/reciveGoods.php",
     *     summary="领取邮件金币",
     *     description="返回指定数据",
     *     tags={"goods-controller"},
     *     @SWG\Parameter(
     *         description="邮件ID",
     *         in="formData",
     *         name="mail_id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=0,
     *         description="{'code':0,'msg':'操作成功','data':{}}",
     *     ),
     *     @SWG\Response(
     *         response=3001,
     *         description="{'code':'3001','msg':'请求方法不正确','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'参数没有上传','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'系统错误,联系接口负责人','data':{}}"
     *     ),
     *
     * )
     */
    function reciveGoods()
    {
        $user_id = Session::get("player");
        /*$user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        if (!array_key_exists('mail_id', $post)) {
            return json(['code' => 3006, 'mess' => '参数没有上传']);
        } else if ($post['mail_id'] == '') {
            return json(['code' => 3007, 'mess' => '参数值不能为空']);
        }
        $mail_id = $post['mail_id'];
        $player_id = Session::get("player");

        //根据邮件的ID去获取邮件详情,调用刘进勇的接口
        $user_email_data['appid'] = CESHI_APPID;
        $user_email_data['id'] = $post['mail_id'];
        $user_email_data['playerId'] = $player_id;
        $user_email_data = json_encode($user_email_data);
        Log::write($user_email_data,'$user_email_data');
        $user_email_url = WEB_USER_URL .'/api/email_detail.php';
        $user_email_list = postInterface($user_email_url, $user_email_data);
        Log::write($user_email_list,'$user_email_list');
        $user_email_list = json_decode($user_email_list,true);

        $goods_array = $user_email_list['data']['goods'];
        $goods_type = $user_email_list['data']['receive_status'];
        if($goods_type == 1){
            return json(['code'=>23407]);
        }
        $goods_array = json_decode($goods_array,true);
        $goods_name = array();
        $goods_counts = array();
        foreach ($goods_array as $k=>$v){
            array_push($goods_name,$k);
            array_push($goods_counts,$v);
        }
        $upinfo = array();
        for ($i=0;$i<count($goods_name);$i++){
            $upinfo[$i]['uid'] = $player_id;
            $goods_type = $goods_name[$i];
            $upinfo[$i]['property_type'] = $goods_name[$i];
            $goods_num = $goods_counts[$i];
            $upinfo[$i]['property_num'] = $goods_counts[$i];
            $upinfo[$i]['app_id'] = CESHI_APPID;
            //判断物品的类型
            if(strpos("$goods_type",'_') !== false ){
                //包含'_';
                $opt = explode('_',"$goods_type");
                $num = count($opt);
                $club_id = $opt[0];
                $vip_id = $opt[1];
                if($num == 2){
                    //$num=2,说明是VIP卡
                    $tb_vip_card = Db::query("SELECT type FROM tb_vip_card WHERE vip_id = $vip_id");
                    if($tb_vip_card){
                        $vip_level = $tb_vip_card[0]['type'];
                    }else{
                        return json(['code'=>3004,'mess'=>'查询不到数据']);
                    }
                    //如果是vip卡要向user_vip表里增加数据
                    //先判断卡包里是否存在该VIP卡的ID,如果存在,则该种类VIP卡加1,如果不存在,插入一条数据
                    $result = Db::query("SELECT vid,card_number FROM tb_user_vip WHERE vid = $vip_id AND uid = $player_id AND club_id = $club_id");
                    if($result){
                        $vip_number = $result[0]['card_number']+$goods_num;
                        Db::execute("UPDATE tb_user_vip SET card_number = '$vip_number' WHERE vid = $vip_id AND uid = $player_id AND club_id = $club_id");
                    }else{
                        Db::execute("INSERT INTO tb_user_vip (vid,uid,club_id,vip_status,card_number,vip_level) VALUES ('$vip_id','$player_id','$club_id','0','$goods_num','$vip_level')");
                    }
                }
            }
        }
        //调用宋哥的接口,批量修改用户资产
        $all_url = WEB_API_URL . 'api/raise_player_property2.php';
        $all_data['upinfo'] = $upinfo;
        $all_data = json_encode($all_data);
        $all_list = postInterface($all_url,$all_data);
        $all_list = json_decode($all_list,true);
        //修改邮件的状态
        $urls = WEB_USER_URL . '/api/email_update.php';
        $datas['appid'] = CESHI_APPID;
        $datas['id'] = $mail_id;
        $datas['receive_status'] = 1;
        $datas = json_encode($datas);
        $result = postInterface($urls, $datas);
        $result = json_decode($result, true);

        if ($all_list['code'] ==0 && $result['code'] == 0) {
            //删除邮件
            $deleurl = WEB_USER_URL .'/api/email_del.php';
            $datadel['appid'] = CESHI_APPID;
            $datadel['id'] = $post['mail_id'];
            $datadel = json_encode($datadel);
            $list111 = postInterface($deleurl,$datadel);
            //发送通知
            $user_opt = getUserProperty($player_id,10000);
            if($user_opt){
                $gold_nums = $user_opt[0]['property_num'];
            }else{
                $gold_nums = 0;
            }
            $user_opt1 = getUserProperty($player_id,10001);
            if($user_opt1){
                $diamond = $user_opt1[0]['property_num'];
            }else{
                $diamond = 0;
            }
            $user_opt2 = getUserProperty($player_id,10002);
            if($user_opt2){
                $diamond1 = $user_opt2[0]['property_num'];
            }else{
                $diamond1 = 0;
            }
            $recive_user[0] = $player_id;
            $user_diamond = $diamond1+$diamond;
            $send_data['content']['gold'] = (int)$gold_nums;
            $send_data['content']['diamond'] = (int)$user_diamond;
            $send_data['type'] = 1029;
            $send_data['sender'] = 0;
            $send_data['reciver'] = $recive_user;
            $send_data['appid'] = (int)CESHI_APPID;
            $send_url = INFORM_URL.'api/send.php';

            $send_data = json_encode($send_data);

            $list = postInterface($send_url,$send_data);

            return json(['code' => 0, 'mess' => '领取成功']);

        } else if ($result['code'] == 2003) {
            return json(['code' => 3004, 'mess' => '已领取']);
        } else {
            return json(['code' => 3004, 'mess' => '领取失败']);
        }
    }
    /**
     * @SWG\Post(
     *     path="/tianjin_mahjong/service/getTarUserInfo.php",
     *     summary="获取其他玩家信息",
     *     description="返回指定数据",
     *     tags={"user-controller"},
     *     @SWG\Parameter(
     *         description="player_id",
     *         in="formData",
     *         name="mail_id",
     *         required=true,
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response=0,
     *         description="{'code':0,'msg':'操作成功','data':{}}",
     *     ),
     *     @SWG\Response(
     *         response=3001,
     *         description="{'code':'3001','msg':'请求方法不正确','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3006",
     *         description="{'code':'3006','msg':'参数没有上传','data':{}}"
     *     ),
     *     @SWG\Response(
     *         response="3004",
     *         description="{'code':'3004','msg':'获取失败','data':{}}"
     *     ),
     *
     * )
     */
    public function getTarUserInfo(){
        $user_id = Session::get("player");
        /*$user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }*/
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        if (!array_key_exists('player_id', $post)) {
            return json(['code' => 3006, 'mess' => '参数没有上传']);
        } else if ($post['player_id'] == '') {
            return json(['code' => 3007, 'mess' => '参数值不能为空']);
        }
        $player_id = $post['player_id'];

        if(!$player_id){
            return json(['code'=>3999,'mess'=>'用户SESSION没有存上']);
        }
        //调用宋哥接口
        $data['uid'] = $player_id;
        $data['app_id'] = CESHI_APPID;
        $data = json_encode($data);
        $url = WEB_API_URL . 'api/get_info.php';
        $list = postInterface($url, $data);
        $list = json_decode($list, true);

        $ress = array();
        if ($list['code'] == 0) {
            //查用户IP
            $res = Db::query("SELECT player_ip FROM tb_user_room WHERE player_id = $player_id");
            if ($res) {
                $ip = $res[0]['player_ip'];
                $ress['ip'] = $ip;
            }else{
                $ress['ip'] = "";
            }
            $ress['nickname'] = $list["data"]['nickname'];
            $ress['head_img'] = $list["data"]['headimgurl'];
            $ress['gender'] = $list["data"]['sex'];
            //查出来玩家所在的club_id
            $tb_last_club = Db::query("SELECT club_id FROM tb_user_last_club WHERE player_id = $player_id");
            if ($tb_last_club){
                $club_id = $tb_last_club[0]['club_id'];
            }else{
                //查出房间号
                $tb_user_room = Db::query("SELECT room_num FROM tb_user_room WHERE player_id = $player_id ORDER BY join_time DESC");
                if($tb_user_room){
                    $room_num = $tb_user_room[0]['room_num'];
                    //根据房间表去查房间表里的club_id
                    $club_id = $tb_user_room[0]['club_id'];
                    //查询tb_user_vip判断是否是VIP
                    $tb_user_vip = Db::query("SELECT vid FROM tb_user_vip WHERE end_day>now() AND uid = $player_id AND club_id = $club_id AND vip_status = '1'");

                    if($tb_user_vip){
                        $vid = $tb_user_vip[0]['vid'];
                        $ress['vip_id'] = $vid;
                    }
                }
            }
            return json(['code' => 0, 'mess' => '获取成功', 'data' => $ress]);
        } else {
            return json(['code' => 3004, 'mess' => '获取失败']);
        }
    }

    public function addEvaluate(){
        $user_id = Session::get("player");
        $user_token = Session::get("$user_id");
        $user_ip = getUserIp('unknown');
        $check_reuslt = checkToken($user_id,$user_ip,$user_token);
        if($check_reuslt != 'true'){
            return json(['code'=>'9999','mess'=>'请重新登录']);
        }
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        $type = $post['type'];
        $player_id = Session::get("player");
        //判断玩家的操作次数是否大于等于3,如果是则返回错误
        $tb_user_evaluate = Db::query("SELECT operations FROM tb_user_evaluate WHERE player_id = $player_id");
        if ($tb_user_evaluate){
            $operations = $tb_user_evaluate[0]['operations'];
        }else{
            $operations = 0;
        }

        if($operations >= 3 ){
            return json(['code'=>3004,'mess'=>'差评操作已达上限']);
        }
        if ($type == 0){
            //0是差评差评增加1
            //先查书据表,有的话就修改让差评加1,没有的话,就插入一条数据
            $tb_evaluate = Db::query("SELECT player_id FROM tb_user_evaluate WHERE player_id = $player_id");
            if($tb_evaluate){
                $res = Db::execute("UPDATE tb_user_evaluate SET bad_num = bad_num+1,operations = operations+1 WHERE 
                player_id = $player_id");
            }else{
                $res = Db::execute("INSERT INTO tb_user_evaluate (player_id,bad_num,operations) VALUES ('$player_id','1','1')");
            }
        }elseif ($type == 1){
            //1是好评,好评增加1
            $tb_evaluate = Db::query("SELECT player_id FROM tb_user_evaluate WHERE player_id = $player_id");
            if($tb_evaluate){
                $res = Db::execute("UPDATE tb_user_evaluate SET bad_num = good_num+1 WHERE player_id = $player_id");
            }else{
                $res = Db::execute("INSERT INTO tb_user_evaluate (player_id,good_num) VALUES ('$player_id','1')");
            }
        }
        if ($res){
            return json(['code'=>0,'mess' => '成功']);
        }else{
            return json(['code'=>3004,'mess'=>'失败']);
        }

    }
    public function getNotice(){
        $user_id = Session::get("player");
        
        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {
            return json(['code' => 3001, 'mess' => '请求方法不正确']);
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
        }
        $url = WEB_USER_URL .'/api/notice_list.php';
        $data['appid'] = CESHI_APPID;
        $data['status'] = 1;
        $data = json_encode($data);
        $list = postInterface($url,$data);
        $list = json_decode($list,true);
        $notice_list = $list['data'];
        if($notice_list){
            $back_list = array();
            for($i=0;$i<count($notice_list);$i++){
                $back_list[$i]['title'] = $notice_list[$i]['title'];
                $back_list[$i]['content'] = $notice_list[$i]['content'];
            }
            return json(['code'=>0,'mess'=>'成功','data'=>$back_list]);
        }

    }
    public function getImage(){

        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method !== 'POST') {

            $url =urldecode($_GET['image_url']);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $file_data    = curl_exec($ch);

            curl_close($ch);



            $image_base = 'data: image/png;base64,'.chunk_split(base64_encode($file_data));
            return $image_base;
        } else {
            $post = file_get_contents("php://input");
            $post = json_decode($post, true);
            $url = $post['image_url'];
            $image_base = 'data: image/png;base64,'.chunk_split(base64_encode(file_get_contents($url)));
            return json(['data'=>$image_base]);
        }
    }
    public function getTime(){
        $time =  gmdate ("l d F Y H:i:s")." GMT";
        return $time;
    }
}
