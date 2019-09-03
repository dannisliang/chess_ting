<?php
/**
 * Created by PhpStorm.
 * User: 测试异步请求
 * Date: 2019/3/29
 * Time: 11:07
 */

namespace app\controller;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use think\Log;

class AsyncTest
{
    /**
     * 测试异步
     * @return \think\response\Json\
     */
    public function test(){
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://192.168.9.18:5204/yangchonggou/',
            // You can set any number of default request options.
            'timeout'  => 10.0,
        ]);
        $request = new Request('POST','http://192.168.9.18:5204/yangchonggou/service/demo.php');
        Log::write(date('Y-m-d H:i:s',time()),'test_async_0');
        $promise1 = $client->postAsync('http://192.168.9.18:5204/yangchonggou/service/demo.php');
        $promise = $client->postAsync('http://192.168.9.18:5204/yangchonggou/service/demo1.php');
        $client ->sendAsync($request);
        $promise1->then(
            function (ResponseInterface $res) {
                echo $res->getStatusCode() . "\n";
                echo $res->getBody()->getContents();
                return $res;
            },
            function (RequestException $e) {

                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
            }
        )->wait();
        $promise->then(
            function (ResponseInterface $res) {
                echo $res->getStatusCode() . "\n";
                echo $res->getBody()->getContents();
                return $res;
            },
            function (RequestException $e) {

                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
            }
        )->wait();

        echo date('Y-m-d H:i:s',time());
//        return jsonRes(0,$res);
    }

    public function demo(){
        sleep(1);
        Log::write(date('Y-m-d H:i:s',time()),'test_async_1');
    }

    public function demo1(){
        sleep(2);
        Log::write(date('Y-m-d H:i:s',time()),'test_async_2');
//        echo date('Y-m-d H:i:s',time()) . PHP_EOL;
    }
}