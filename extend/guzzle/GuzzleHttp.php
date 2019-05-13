<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/12
 * Time: 11:22
 */
namespace guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Log;

class GuzzleHttp
{
    public static $client; //储存实例化信息

    public static $url;  //服务器地址

    public  function __construct( $url )
    {
        self::$url = $url;
        self::$client = new Client(['base_uri'=> $url]);
    }

    /**
     * 返回请求的信息为数组格式
     * @param $pathInfo
     * @param array $data
     * @param string $method
     * @return string
     */
    public function getBodyContent( $pathInfo ,  $data  , $method = 'post' )
    {
        try{
            $requestStartTime = microtime();
            //请求接口信息

            $response = self::$client -> request( $method , $pathInfo , ['json'=>$data , 'timeout' => 1] );

            $requestEndTime      = microtime();
            $requestBeginTimeArr = explode(' ', $requestStartTime);
            $requestEndTimeArr   = explode(' ', $requestEndTime);
            $requestBeginTime    = bcadd($requestBeginTimeArr[0], $requestBeginTimeArr[1], 2);
            $requestEndTime      = bcadd($requestEndTimeArr[0], $requestEndTimeArr[1], 2);
            $requestKeepTime     = bcsub($requestEndTime, $requestBeginTime, 1);

            if($requestKeepTime > 0.5 ){
                $requestLog = date('Y-m-d H:i:s', time()). '|' . self::$url . $pathInfo . '|' . '请求服务器响应慢' . '|' . $requestKeepTime;
                trace($requestLog);
            }

            if ($response->getStatusCode() !== 200 ){
                $requestLog = date('Y-m-d H:i:s', time()). '|' . self::$url . $pathInfo .  '|' . '请求服务器异常' . '|' . $response->getStatusCode() ;
                trace($requestLog);
            }

            //返回body信息
            $result = $response->getBody()->getContents();

            return json_decode($result,true);

        }catch(RequestException $e){ //连接超时错误
            $logInfo = date('Y-m-d H:i:s', time()) . '|' . '请求超时' . '|' . self::$url . $pathInfo;
            trace($logInfo);
            return false;

        }


    }
}