<?php
namespace app\model;
use think\Env;
use think\Log;

date_default_timezone_set ( 'PRC' );
class BeeSender{
	public $bee = NULL;
	private $bee_server = 'http://bee.chessvans.com:8000/data';
	private $bee_list = array();
	/**
	 * 构造函数
	 * @param int $app_id 应用ID
	 * @param string $identity 身份（用户中心用user_center 业务大厅根据对应项目自定义）
	 * @param string $ip IP地址
	 * @param boolean $is_debug 是否为测试
	 */
	function __construct($app_id,$identity,$ip,$is_debug){
        
		$mode = Env::get('is_online');
		$this->bee = new Bee($mode);
		$this->bee->data = new BeeData($identity,$ip);
		$this->bee->data->product = $app_id;

	}
	
	private function getMTime(){
    	$a = explode(' ', microtime());
		$b = round(floatval($a[0]),3);
		$c = explode('.', strval($b));
    	$d = '0';
    	if(count($c) == 2){
        	$d = $c[1];
        }
		while(strlen($d) < 3){
			$d .= '0';
		}
    
    	return time().$d;
    }

	/**
	 * 提交到大数据
	 * @param string $event 事件类型
	 * @param array $context 根据需求提交的数据
	 * @return boolean
	 */
	public function send($event,$context){
    	$this->bee->data->time = date(DATE_ISO8601);
        $this->bee->data->event = $event;
        $this->bee->data->context = $context;
    	$s_time = $this->getMTime();
        $response_json = $this->post($this->bee_server, json_encode($this->bee));
    	$t_time = $this->getMTime();
        $this->log('bee_response:'.strlen(json_encode($this->bee)).'|'.($t_time - $s_time));
        $response = json_decode($response_json);
        if($response->status == 'success' && $response->code == 0){
            return TRUE;
        }
        $this->log('response_error:'.$response_json);
        return FALSE;
	}

	/**
	 * 暂存到提交列表
	 * @param string $event 事件类型
	 * @param array $context 根据需求提交的数据
	 * @return NULL
	 */
	 public function add_batch($event,$context){
        $this->bee->data->time = date(DATE_ISO8601);
        $this->bee->data->event = $event;
        $this->bee->data->context = $context;

        $bee = $this->bee;

        $this->bee_list[] = $bee;
     }
        
        /**
	 * 批量提交
	 * @return boolean
	 */
    public function batch_send(){
        $s_time = $this->getMTime();
        $response_json = $this->post($this->bee_server, json_encode($this->bee_list));
        $t_time = $this->getMTime();
        $this->log('batch_response:'.strlen(json_encode($this->bee_list)).'|'.($t_time - $s_time));
        $response = json_decode($response_json);
        if($response->status == 'success' && $response->code == 0){
            return TRUE;
        }
        $this->log('response_error:'.$response_json);
        return FALSE;
    }

	private function post($url,$data){
		$curl = curl_init (); // 启动一个CURL会话
		curl_setopt ( $curl, CURLOPT_URL, $url ); // 要访问的地址
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYPEER, 0 ); // 对认证证书来源的检查
		curl_setopt ( $curl, CURLOPT_SSL_VERIFYHOST, 2 ); // 从证书中检查SSL加密算法是否存在
		curl_setopt ( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0' ); // 模拟用户使用的浏览器
		curl_setopt ( $curl, CURLOPT_FOLLOWLOCATION, 1 ); // 使用自动跳转
		curl_setopt ( $curl, CURLOPT_AUTOREFERER, 1 ); // 自动设置Referer
		curl_setopt ( $curl, CURLOPT_POST, 1 ); // 发送一个常规的Post请求
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, $data ); // Post提交的数据包
		curl_setopt ( $curl, CURLOPT_TIMEOUT, 10 ); // 设置超时限制防止死循环
		curl_setopt ( $curl, CURLOPT_HEADER, 0 ); // 显示返回的Header区域内容
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 ); // 获取的信息以文件流的形式返回
    	curl_setopt ( $curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    	curl_setopt ( $curl, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt ( $curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); 
		$tmpInfo = curl_exec ( $curl ); // 执行操作
		if (curl_errno ( $curl )) {
			$this->log('request_error:' . curl_error ( $curl ));
		}
		curl_close ( $curl ); // 关键CURL会话
		return $tmpInfo; // 返回数据
	}
	
	private function log($data){
		$month = date('Ym');
        if(!is_dir(__DIR__.'/'.$month)){
            mkdir(__DIR__.'/'.$month);
        }
        $file_name = __DIR__.'/'.$month.'/'.date('Ymd');
        $f = fopen($file_name,'a');
        fwrite($f,date('Y-m-d H:i:s',time())."\r\n".$data."\r\n");
        fclose($f);
	}
}


class Bee{
	public $mode = '';
	public $type = '';
	public $data = NULL;
	
	const MODE_PRODUCTION = 'production';
	const MODE_DEBUG = 'debug';
	
	const TYPE_OPERATION = 'operation';
	const TYPE_MAINTENANCE = 'maintenance';
	
	function __construct($mode){
		$this->mode = self::MODE_DEBUG;
		if($mode){
		    $this->mode = self::MODE_PRODUCTION;
		}
		//$this->mode = $mode;
		$this->type = self::TYPE_OPERATION;
	}
}

class BeeData{
	public $platform = 'server';
	public $identity = 'unknown';
	public $language = 'php';
	public $id = '';
	public $sdk_version = '1.0.0';
	public $time = '';
	public $product = '';
	public $event = '';
	public $context = NULL;
	
	function __construct($identity,$ip){
		$this->identity = $identity;
		$this->id = $identity.'_'.$ip;
	}
}
?>
