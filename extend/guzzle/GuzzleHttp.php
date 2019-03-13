<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/12
 * Time: 11:22
 */
namespace guzzle;

use GuzzleHttp\Client;
use think\Log;

class GuzzleHttp
{
    public static $client; //储存实例化信息

    public  function __construct( $url )
    {
        self::$client = new Client(['base_uri'=> $url]);
    }

    /**
     * 返回请求的信息为数组格式
     * @param $pathInfo
     * @param array $data
     * @param string $method
     * @return string
     */
    public function getBodyContent( $pathInfo ,  $data , $method = 'post')
    {
        try{
            //请求接口信息
            $response = self::$client -> request( $method , $pathInfo , ['json'=>$data , 'timeout' => 1] );

            //返回body信息
            $result = $response->getBody()->getContents();

            return json_decode($result,true)['data'];

        }catch(\Exception $e){

            Log::write('email_response_error',$e->getMessage());

            return false;

        }


    }
}