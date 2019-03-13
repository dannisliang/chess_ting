<?php

use think\Session;
use app\definition\CodeMes;
use app\definition\RedisKey;
use app\definition\Definition;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// 应用公共文件

/**
 * 接口json返回
 * @param $code 状态码
 * @param $data 返回值
 * @return \think\response\Json\
 */
function jsonRes($code, $data = [] ){
    $res = [
        'code' => $code,
        'mess' => CodeMes::$errorCode[$code],
    ];

    if($data){
        $res['data'] = $data;
    }
    return json($res);
}

/**
 * 获取用户的session数据
 * @return mixed   {’nickname’:’昵称’,’sex’:1,’province’:’省份’,’city’:’城市’,’country’:’国家’,’headimgurl’:’头像图片url’}  'userid'  '1'
 */
function getUserSessionInfo(){
    $userInfo = json_decode(Session::get(RedisKey::$USER_SESSION_INDO));
    if(!$userInfo){
        return json(['code'=>9999, 'mess' => '请重新登录'])->send();
        exit();
    }
    return $userInfo;
}

/**
 * 获取用户的IP
 * @return string
 */
function getUserIp(){
    $ip = '';
    if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']){
        $ip = $_SERVER['REMOTE_ADDR'];
    }else if(isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP']){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    if(!$ip){
        $ip = 'unknown';
    }
    return $ip;
}

/**
 * 检查用户token是否有效
 * @return mixed ture/false
 */
function checkUserToken(){
    $userSessionInfo = getUserSessionInfo();
    $data['uid'] = $userSessionInfo['userid'];
    $data['ip'] = getUserIp();
    $data['token'] = $userSessionInfo['token'];
    $requestUrl = Definition::$WEB_API_URL . 'api/v3/check_token_time.php';
    $response = sendHttpRequest($requestUrl, $data);
    return $response['result'];
}

/**
 * 获取用户资产
 * @param $userIds 用户ID或用户ID集
 * @param $propertyType 请求类型 固定值
 * @return mixed
 */
function getUserProperty($userIds, $propertyType){
    $requestUrl = Definition::$WEB_API_URL . "api/get_player_property.php";
    $data['app_id'] = Definition::$CESHI_APPID;
    $data['property_type'] = $propertyType;
    $data['uid'] =$userIds;
    $response = sendHttpRequest($requestUrl, $data);
    return $response['data'];
}

/**
 * 发送http请求  并记录错误请求/超时请求的日志
 * @param $url 完整请求地址
 * @param $data 请求数组 无需json
 * @param string $type 请求方式GET/POST等
 * @param array $headers 请求头
 * @return mixed|\Psr\Http\Message\StreamInterface 记录超时日志 记录状态码非200日志 请求正确返回array
 */
function sendHttpRequest($url, $data, $type = 'POST', $headers = []){
    $requestConfig = [
        'json' => $data,
        'connect_timeout' => 1, # 最长握手时间
        'timeout' => 1, # 最长等待时间
        'headers' => ['Accept-Encoding' => 'gzip'],
        'decode_content' => 'gzip',
        'http_errors' => false, # 非200状态码不抛出异常
    ];

    if($headers){
        foreach ($headers as $k => $v){
            foreach ($v as $key => $val){
                $requestConfig['headers'][$key] = $val;
            }
        }
    }

    try{
        $requestBeginTime = microtime();
        $httpClient = new Client(['base_uri' => $url]);
        $response = $httpClient->request($type, '', $requestConfig);
        $requestEndTime = microtime();

        $requestBeginTimeArr = explode(' ', $requestBeginTime);
        $requestEndTimeArr = explode(' ', $requestEndTime);
        $requestBeginTime = bcadd($requestBeginTimeArr[0], $requestBeginTimeArr[1], 2);
        $requestEndTime = bcadd($requestEndTimeArr[0], $requestEndTimeArr[1], 2);
        $requestKeepTime = bcsub($requestEndTime, $requestBeginTime, 1);

        if($requestKeepTime > 0.5){
            $logInfo = date('Y-m-d H:i:s', time()). '|' . '请求服务器响应慢' . '|' . $url . '|' . $requestKeepTime;
            trace($logInfo);
        }

        if($response->getStatusCode() != 200){ # 服务器错误
            $logInfo = date('Y-m-d H:i:s', time()). '|' . '请求服务器错误' . '|' . $url . '|' . $response->getStatusCode();
            trace($logInfo);
            return json(['code' => 1111, 'mess' => '服务器内部错误，请重试'])->send();
        }
        return json_decode($response->getBody()->getContents(), true);
    }catch (RequestException $e){ # 连接超时RequestException
        $logInfo = date('Y-m-d H:i:s', time()) . '|' . '请求超时' . '|' . $url;
        trace($logInfo);
        return json(['code' => 1111, 'mess' => '服务器内部错误，请重试'])->send();
    }
}

/**
 * 获取房间需要的玩家人数
 * @param $roomOptions
 * @return mixed
 */
function getRoomNeedUserNum($roomOptions){
    $roomNeedUserNum = 1;
    foreach (Definition::$ROOM_NEED_USER_NUMBER as $k => $v){
        if(in_array($v, $roomOptions)){
            $roomNeedUserNum = $v;
            break;
        }
    }
    return $roomNeedUserNum;
}
