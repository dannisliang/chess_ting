<?php
/**
 * Created by PhpStorm.
 * User: 杨腾飞
 * Date: 2019/3/26
 * Time: 17:58
 */

namespace app\controller;


use think\Request;

class ProceseImage
{
    /**
     * 处理图片
     * @return string|\think\response\Json
     */
    public function getImage(){

        $request = Request::instance();
        $method = $request->method();//获取上传方式
        if ($method == 'GET') {
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
}