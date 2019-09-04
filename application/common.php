<?php

use think\Log;
use think\Session;
use app\definition\CodeMes;
use app\definition\RedisKey;
use app\definition\Definition;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Env;
use think\Debug;

// 应用公共文件

function p($info){
    print_r($info);die;
}

/**
 * 接口json返回
 * @param $code 状态码
 * @param $data 返回值
 * @return \think\response\Json\
 */
function jsonRes($code, $data = []){
    $res = [
        'code' => $code,
        'mess' => CodeMes::$errorCode[$code],
    ];

    $res['data'] = $data;
    return json($res);
}

/**
 * 检查用户token是否有效
 * @return mixed ture/false
 */
function checkUserToken($userSessionInfo){
    $data['uid'] = $userSessionInfo['userid'];
    $data['ip'] = $userSessionInfo['ip'];
    $data['token'] = $userSessionInfo['token'];
    $requestUrl = Env::get('web_api_url').Definition::$CHECK_TOKEN_TIME;
    $response = sendHttpRequest($requestUrl, $data);
    return $response;
}

/**
 * 获取用户资产
 * @param $userIds 用户ID或用户ID集
 * @param $propertyType 请求类型固定值可以为数组
 * @return mixed
 */
function getUserProperty($userIds, $propertyType){
    $requestUrl = Env::get('web_api_url').Definition::$GET_PLAYER_PROPERTY;
    $data['app_id'] = Env::get('app_id');
    $data['property_type'] = $propertyType;
    $data['uid'] = $userIds;
    $response = sendHttpRequest($requestUrl, $data);
    return $response;
}

/**
 * 发送http请求  并记录错误请求/超时请求的日志
 * @param $url 完整请求地址
 * @param $data 请求数组 无需json
 * @param string $type 请求方式GET/POST等
 * @param array $headers 请求头
 * @return mixed|\Psr\Http\Message\StreamInterface 记录超时日志 记录状态码非200日志 请求正确返回array
 */
function sendHttpRequest($url, $data = [], $type = 'POST', $headers = [], $config = []){
    $requestConfig = [
        'json' => $data,
        'connect_timeout' => 3, # 最长握手时间
        'timeout' => 3, # 最长等待时间
        'headers' => ['Accept-Encoding' => 'gzip'],
        'decode_content' => 'gzip',
        'http_errors' => false, # 非200状态码不抛出异常
    ];

    if($config){
        foreach ($config as $k => $v){
            $requestConfig[$k] = $v;
        }
    }

    if($headers){
        foreach ($headers as $k => $v){
            foreach ($v as $key => $val){
                $requestConfig['headers'][$key] = $val;
            }
        }
    }

    try{
        Debug::remark('begin');
        $httpClient = new Client(['base_uri' => $url]);
        $response = $httpClient->request($type, '', $requestConfig);
        Debug::remark('end');
        $markTime = Debug::getRangeTime('begin','end');
        if($markTime > 0.5){
            $errorData = [
                $markTime.'s',
                $url
            ];
            Log::write($errorData, 'slowRequest');
        }

        if($response->getStatusCode() != 200){ # 服务器错误
            $errorData = [
                $url
            ];
            Log::write($errorData, 'errorRequest');
            return [];
        }
        return json_decode($response->getBody()->getContents(), true);
    }catch (RequestException $e){ # 连接超时等
        $errorData = [
            $url
        ];
        Log::write($errorData, 'errorConnectRequest');
        return [];
    }
}

/**
 * 获取房间需要的玩家人数
 * @param $roomOptions
 * @return mixed
 */
function getRoomNeedUserNum($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode){
    if(!isset($playInfoPlayJsonDecode['checks']['group'])){
        return false;
    }
    $userNum = getPlayInfoWhichInOptionsInfo($playInfoPlayJsonDecode['checks']['group'], $roomOptionsInfoOptionsJsonDecode, 'playerSize');
    return $userNum;
}

/**
 * 获取房间圈数
 * @param $roomOptions
 * @return mixed
 */
function getRoomSet($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode){
    if(!isset($playInfoPlayJsonDecode['checks']['group'])){
        return false;
    }
    $setNum = getPlayInfoWhichInOptionsInfo($playInfoPlayJsonDecode['checks']['group'], $roomOptionsInfoOptionsJsonDecode, 'set');
    return $setNum;
}

/**
 * 获取房间局数
 * @param $roomOptions
 * @return mixed
 */
function getRoomRound($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode){
    if(!isset($playInfoPlayJsonDecode['checks']['group'])){
        return false;
    }
    $roundNum = getPlayInfoWhichInOptionsInfo($playInfoPlayJsonDecode['checks']['group'], $roomOptionsInfoOptionsJsonDecode, 'round');
    return $roundNum;
}

/**
 * 获取房间低分
 * @param $roomOptions
 * @return mixed
 */
function getRoomBaseScore($playInfoPlayJsonDecode, $roomOptionsInfoOptionsJsonDecode){
    if(!isset($playInfoPlayJsonDecode['checks']['group'])){
        return false;
    }
    $baseScore = getPlayInfoWhichInOptionsInfo($playInfoPlayJsonDecode['checks']['group'], $roomOptionsInfoOptionsJsonDecode, 'baseScore');
    return $baseScore;
}

/**
 * 获取玩法中的玩家人数值
 * @param $playInfoPlayJsonDecodeChecksGroup play表中的play字段的checks的group
 * @param $roomOptionsInfoOptionsJsonDecode roomOptions表中的options
 * @param $keyName 需要查找的key的名字
 * @return mixed|string
 */
function getPlayInfoWhichInOptionsInfo($playInfoPlayJsonDecodeChecksGroup, $roomOptionsInfoOptionsJsonDecode, $keyName){
//    print_r($playInfoPlayJsonDecodeChecksGroup);die;
    $ret = '';
    if(is_array($playInfoPlayJsonDecodeChecksGroup) && is_array($roomOptionsInfoOptionsJsonDecode)){
        foreach($playInfoPlayJsonDecodeChecksGroup as $k => $v){
            if(isset($v['id']) && in_array($v['id'], $roomOptionsInfoOptionsJsonDecode) && isset($v['option'][$keyName])){
                $ret = $v['option'][$keyName];
                break;
            }else{
                $ret = getPlayInfoWhichInOptionsInfo($v, $roomOptionsInfoOptionsJsonDecode, $keyName);
                if($ret){
                    break;
                }
            }
        }
    }
    return $ret;
}

/**
 * 发送guzzle http请求
 * @param $user_id /用户id
 * @param $url  /服务器地址
 * @param $pathInfo /访问路径
 * @param $data /携带参数
 * @return mixed
 */
function guzzleRequest( $url , $pathInfo , $data )
{
    //实例化guzzle
    $client = new \guzzle\GuzzleHttp( $url );

    $result = $client -> getBodyContent( $pathInfo , $data );

    return $result;
}

/**
 * 检查一个变量是否为空，0 不算空
 * @param $str:要处理的二维数组
 */
function check_empty($str){
    if(is_numeric($str)){
        return false;
    }else{
        return empty($str);
    }
}

/**
 * 检查key 是否存在数组中
 * @param mixed $key 可以是一个键值，也可以是多个，多个用数组表示，
 * @param Array $arr
 * @param Boole $is_true 如果该值为真，则要求值不能为 空字符串 和 null
 * @return Boole 多个键值，必须全部存在才返回 true
 */
function has_keys($key, $arr, $is_true = false){
    if(!is_array($key)){
        $key = [$key];
    }
    foreach($key as $v){
        if(!array_key_exists($v, $arr) ){
            return false;
        }
        if($is_true){
            if(check_empty($arr[$v])){
                return false;
            }
        }
    }
    return true;
}

/**
 * 扣用户资产
 * @param $player 用户ID
 * @param $type 资产类型
 * @param $diamond 需要的钻石
 * @return mixed
 */
function operaUserProperty($player, $type, $diamond){
    $url = Env::get('web_api_url').Definition::$RAISE_PLAYER_PROPERTY;
    $data['uid'] = $player;
    $data['app_id'] = Env::get('app_id');
    $data['property_type'] = $type;
    $data['property_num'] = $diamond;
    return sendHttpRequest($url, $data);
}

/**
 * 操作用户资产
 * @param $player_id /用户id
 * @param $type /资产类型
 * @param $diamond /数量
 * @param $event_type /操作类型
 * @param $reason_id /reason_id说明： 1 -牌局消耗 2-GM后台修改 3-邮件管理 4-商城购买 5-会长返利 6-提现 7-房费扣减 8-房费退还
 * @param $property_name /操作资产说明
 * @return mixed
 */
function operateUserProperty($player_id, $type, $diamond, $event_type , $reason_id ,$property_name){
    $url      = Env::get('web_api_url');
    $pathInfo = Definition::$PROPERTY_CHANGE;
    $data = [
        'app_id' => Env::get('app_id'),
        'upinfo' => [
            [
                'uid'           => $player_id,
                "property_type" => $type,
                "change_num"    => $diamond,
                'event_type'    => $event_type, //" + 或者 -  或者 update ",
                "reason_id"     => $reason_id,
                "property_name" => $property_name,
            ]
        ],
    ];
    $res = guzzleRequest($url , $pathInfo ,$data);
    return $res;
}

/**
 * 批量操作用户资产
 * @param $player_id /用户id
 * @param $type /资产类型
 * @param $diamond /数量
 * @param $event_type /操作类型
 * @param $reason_id /reason_id说明： 1 -牌局消耗 2-GM后台修改 3-邮件管理 4-商城购买 5-会长返利 6-提现 7-房费扣减 8-房费退还
 * @param $property_name /操作资产说明
 * @return mixed
 */
function operatePlayerProperty($data){
    $url      = Env::get('web_api_url');
    $pathInfo = Definition::$PROPERTY_CHANGE;
    $info = [
        'app_id' => Env::get('app_id'),
        'upinfo' => $data,
    ];
    $res = guzzleRequest($url , $pathInfo , $info);
    return $res;
}

/**
 * 从session获取
 * @return mixed
 */
function getUserIdFromSession(){
    //杨腾飞调试专用
//    $user_info = Session::get(RedisKey::$USER_SESSION_INFO);
//    var_dump($user_info);die;
////    $user_info = json_decode($user_info,true);
//    if(!is_array($user_info)){
//        $user_info = [];
//    }
//    Session::set(RedisKey::$USER_SESSION_INFO,array_merge($user_info,['player_id'=>328946]));

    try{
        $user_id = Session::get(RedisKey::$USER_SESSION_INFO)['player_id'];
        if(!$user_id){
            return false;
        }
        return $user_id;
    }catch (\Exception $e){
        return false;
    }
}

/**
 * 验证token
 * $data = [
'ip'    => $ip,
'token' => $this->opt['token'],
'uid'   => $this->opt['player_id'],
];
 * @param $data
 * @return mixed
 */
function checkToken($data){
    //验证传输的token是否可靠
    $url = Env::get('web_api_url');
    $pathInfo = Definition::$AUTHENTICATE;
    $result = guzzleRequest( $url , $pathInfo , $data );
    return $result;
}

/**
 * 操作文件
 */
function operaFile($path,$data,$type){
    switch ($type){
        case  'write':
            $myfile = fopen($path, "w") or die("Unable to open file!");
            $txt = json_encode($data, JSON_UNESCAPED_UNICODE);//设置为中文不unicode
            fwrite($myfile, $txt);
            fclose($myfile);
            break;
        case 'read':
            $str = file_get_contents($path);//将整个文件内容读入到一个字符串中
            $str = str_replace("\r\n","<br />",$str);
            $str = json_decode($str,true);
            return $str;
            break;
        case 'creat':
            break;
        case 'delete':
            break;
        default:
            return false;
    }
}

/**
 * 返回用户昵称(只用于登录的用户获取)
 * @param $player_id
 * @return mixed
 */
function backNickname($player_id){
    $user_session_info = Session::get(RedisKey::$USER_SESSION_INFO);
    if(isset($user_info['nick_name'])){
        $nick_name = $user_session_info['nick_name'];
    }else{
        $data = [
            'uid'=>$player_id,
            'app_id'=> Env::get('app_id'),
        ];
        $url = Env::get('web_api_url');
        $path_info = Definition::$GET_INFO;
        $user_info = guzzleRequest($url , $path_info , $data);
        $nick_name = $user_info['data']['nickname'];
        //再存入session
        if(!is_array($user_session_info)){
            $user_session_info = [];
        }
        $user_info = array_merge($user_session_info,['nick_name'=>$nick_name]);
        Session::set(RedisKey::$USER_SESSION_INFO,$user_info);
    }
    return $nick_name;
}

/**
 * 获取用户的基本信息
 * @param $user_id
 * @return mixed
 */
function getUserBaseInfo($user_id)
{

    //请求用户中心接口地址
    $url = Env::get('web_api_url');
    //获取用户中心接口路径
    $userInfo_url = Definition::$GET_INFO;
    //向用户中心传输的请求参数
    $data = [
        'uid' => $user_id,
        'app_id'=> Env::get('app_id'),
    ];
    $result = guzzleRequest( $url , $userInfo_url , $data);

    return $result['data'];
}

/**
 * 批量获取用户的信息
 * @param $user_id（array）
 * @return mixed
 */
function getUserBaseInfos($user_id)
{

    //请求用户中心接口地址
    $url = Env::get('web_api_url');
    //获取用户中心接口路径
    $userInfo_url = Definition::$GET_BATCH_INFO;
    //向用户中心传输的请求参数
    $data = [
        'uid' => $user_id,
        'app_id'=> Env::get('app_id'),
    ];
    $result = guzzleRequest( $url , $userInfo_url , $data);
    if($result['code'] != 0){
        return false;
    }
    return $result['data'];
}

/**
 * 从逻辑服获取房间id
 * @param $user_id
 * @return bool
 */
function getRoomIdFromService($user_id){
    $serviceGatewayModel = new \app\model\ServiceGatewayNewModel();
    $gameServiceNewModel = new \app\model\GameServiceNewModel();
    $clubSocketModel = new \app\model\ClubSocketModel();
    $gameServices = $gameServiceNewModel ->getSomeByWhere(['is_open'=>1]);
    if(!$gameServices){
        return false;
    }
    $service_id = [];
    foreach ($gameServices as $gameService){
        $service_id[] = $gameService['service_id'];
    }
    //获取所有逻辑服地址
    $services = $serviceGatewayModel -> getServiceGatewayNewInfosByWhere(['id'=>['in',$service_id]]);
    if(!$services){
        return false;
    }
    foreach ($services as $service){
        $path_info = Definition::$GET_USER_ROOM;
        //请求逻辑服
        $serviceInfo = guzzleRequest( $service['service'] , $path_info , ['playerId' => (int)$user_id]);

        if(!isset($serviceInfo['content'])){
            continue;
        }
        if(isset($serviceInfo['content']['roomId']) && array_key_exists('roomId',$serviceInfo['content'])){
            $room_id = $serviceInfo['content']['roomId'];
            break;
        }
    }

    //不存在房间
    if(empty($room_id)){
        //获取逻辑服特定的连接（内测使用）
        if(\think\Env::get('is_online') == false){
            $services = $clubSocketModel ->getSome();
            if(!$services){
                return false;
            }
            $room_id = 0;
            foreach ($services as $service){
                $path_info = Definition::$GET_USER_ROOM;
                //请求逻辑服
                $serviceInfo = guzzleRequest( $service['room_url'] , $path_info , ['playerId' => (int)$user_id]);

                if(!isset($serviceInfo['content'])){
                    continue;
                }
                if(isset($serviceInfo['content']['roomId']) && array_key_exists('roomId',$serviceInfo['content'])){
                    $room_id = $serviceInfo['content']['roomId'];
                    break;
                }
            }
            return $room_id;
        }else{
            return false;
        }
    }
    return $room_id;
}

/**
 * 获取报送大数据基础参数
 * @param $uuid
 * @return array|bool
 */
function getBeeBaseInfo($uuid = '-',$senior_id = null){
    $session_info = Session::get(RedisKey::$USER_SESSION_INFO);
    if(!$session_info){
        return [];
    }
    //判断是否是高级会长
    if($senior_id){
        $user_info = getUserBaseInfo($senior_id);
        if (!$user_info){
            return [];
        }
        //基础事件
        $content = [
            'ip '       => $session_info['ip'],  //事件发生端iP
            'user_id'   => $senior_id,  //用户id
            'role_id'   => '-' . '_' . $senior_id,  //角色id，若没有即为serverid_userid
            'role_name' => $user_info['nickname'],  //昵称
            'client_id' => $uuid,  //设备的UUID（可传-号）
            'server_id' => '-',  //区服id ，服务器为服务器的网元id（可传减号）
            'system_type'=> $session_info['client_type'], //操作系统
            'client_type'=> $session_info['app_type'], //设备端应用类型
        ];
        return $content;
    }
    //基础事件
    $content = [
        'ip '       => $session_info['ip'],  //事件发生端iP
        'user_id'   => $session_info['player_id'],  //用户id
        'role_id'   => '-' . '_' . $session_info['player_id'],  //角色id，若没有即为serverid_userid
        'role_name' => $session_info['nickname'],  //昵称
        'client_id' => $uuid,  //设备的UUID（可传-号）
        'server_id' => '-',  //区服id ，服务器为服务器的网元id（可传减号）

        'system_type'=> $session_info['client_type'], //操作系统
        'client_type'=> $session_info['app_type'], //设备端应用类型
    ];
    return $content;
}

/**
 * 报送大数据获取俱乐部信息
 * @param $club_id
 * @return array
 */
function getClubNameAndAreaName($club_id){
    $clubModel = new \app\model\ClubModel();
    $club = $clubModel -> getClubNameAndAreaName($club_id);
    //获取分成模式
    switch ($club['club_type']){
        case 0:
            $club_mode = 'divide'; //分成模式
            break;
        case 1:
            $club_mode = 'free';  //免费模式
            break;
        default:
            $club_mode = '';
            break;
    }
    $result = [
        'club_id' => $club['cid'],
        'club_mode'=> $club_mode,
        'club_name'=> $club['club_name'],
        'club_region_id'=> $club['area_id'],
        'club_region_name'=> $club['area_name'],
    ];
    return $result;
}


/**
 * 解锁
 * @param $lockKey
 * @param $time
 * @return mixed
 */
function delLock($lockKey, $time){
    $redis = new Redis();
    $redisHandle = $redis->handler();
    $lockKey = 'lock:'.$lockKey;

    $lua = <<<SCRIPT
        local k1 = KEYS[1]
        local v1 = tonumber(ARGV[1])
        local v2 = tonumber(redis.call('get', k1))
        if v2 == v1 then
               redis.call('del', k1)
               return true
        else
            return false
        end
SCRIPT;
    $res = $redisHandle->eval($lua, array($lockKey, $time), 1);
    if(!$res){
        Log::write($lockKey, '超时删除锁异常');
    }
    return $res;
}


/**
 * 获取redis锁
 * @param $lockKey
 * @return bool|int
 */
function getLock($lockKey){
    $redis = new Redis();
    $redisHandle = $redis->handler();

    $getLock = false;
    $timeOut = bcadd(time(), 2, 0);
    $lockKey = 'lock:'.$lockKey;
    while(!$getLock){
        $time = time();
        if($time > $timeOut){
            break;
        }
        $getLock = $redisHandle->set($lockKey, $time, array('NX', 'EX' => 5));
        if($getLock){
            return $time;
        }
        //todo 为抢占式锁增加一个微秒睡眠时间， 减轻redis的瞬间并发请求数
        usleep(10000);
    }

    if(!$getLock){
        Log::write($lockKey, '获取锁异常');
    }

    return $getLock;
}
