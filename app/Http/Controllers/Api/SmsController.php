<?php

namespace App\Http\Controllers\Api;

use App\Components\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Response;
use Cache;
use Hash;
use DB;

class SmsController extends Controller
{

    protected static $systemConfig;

    protected static $statusStr = [
        "0"  => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词",
    ];

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }
    
    public function send1(Request $request)
    {
        $username = trim($request->get('username'));
        if (!$username) {
            return Response::json(['status' => 'fail','data' => [],'message' => '请输入用户名']);
        }

        $code = rand(1000,9999);

        $smsapi  = "https://u.smsyun.cc/sms-partner/access/a17043769647/sendsms";
        $postArr = array (
			'smstype' =>'4',//短信发送发送
			'clientid'  =>  'b06qz6',
			'password' => md5('12345678'),
			'mobile' => $username,
			'content' => "【SSClouds】您的验证码为" . $code . "，在10分钟内有效。" ,
			'sendtime'=>date('Y-m-d H:i:s'),
			'extend'=>'00',
			'uid'=>'00'
        );
		$result = $this->curlPost($smsapi, $postArr);
		
		$result=json_decode($result);
        if ($result->data[0]->code===0) {
             Cache::put('sms_' . $username,$code,10);
            return Response::json(['status' => 'success','data' => [],'message' => '短信发送成功,一分钟后没收到短信在重试']);
        }else{
             return Response::json(['status' => 'fail','data' => [],'message' => $result->data[0]->msg ]);
        }

        if ($result['code'] == 0) {
            Cache::put('sms_' . $username,$code,10);
            return Response::json(['status' => 'success','data' => [],'message' => '短信发送成功,一分钟后没收到短信在重试']);
        } else {
            return Response::json(['status' => 'fail','data' => [],'message' => $result['msg'] ]);
        }
    }

    public function send(Request $request)
    {
    	
    	$ip = md5($request->ip());
        
        $key = 'sendSms_' . $ip.'_'.date('Ymd');
        
        if (Cache::has($key) ) {
            if(Cache::get($key) >= 3){
                return Response::json(['status' => 'fail','data' => [],'message' => '短信上限']);
            } 
            Cache::increment($key);
        } else {
            Cache::put($key,1,86400);
        }
        
        $username = trim($request->get('username'));
        if (!$username) {
            return Response::json(['status' => 'fail','data' => [],'message' => '请输入用户名']);
        }
        
        $time = $request->get('time');
        $md5 =  $request->get('md5');
        $time2 = time();
        if($md5  != md5($time.'wyjsq')){
             return Response::json(['status' => 'fail','data' => [],'message' => '发送失败,请重试']);
        }

        $code = rand(100000,999999);

        $smsapi  = "http://api.smsbao.com/";
        $user    = "blackhk"; //短信平台帐号
        $pass    = md5("m3300469"); //短信平台密码
        $content = "【leilin】您的验证码为" . $code . "，在10分钟内有效。";//要发送的短信内容
        $phone   = $username;//要发送短信的手机号码
        $sendurl = $smsapi . "sms?u=" . $user . "&p=" . $pass . "&m=" . $phone . "&c=" . urlencode($content);
        $result  = file_get_contents($sendurl);
        if ($result == 0) {
            Cache::put('sms_' . $username,$code,10);
            return Response::json(['status' => 'success','data' => [],'message' => '短信发送成功']);
        } else {
        	if( isset( self::$statusStr[$result] ) ){
        		$msg = self::$statusStr[$result];
        	} else {
        		$msg = '未知错误！';
        	}
            return Response::json(['status' => 'fail','data' => [],'message' => $msg]);
        }
    }

    public function check(Request $request)
    {
        $username = trim($request->get('username'));
        $code     = trim($request->get('code'));
        $get = Cache::get('sms_'.$username);
        if(false === $get || $get == ''){
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码已过期']);
        }

        if( $get != $code ){
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码不正确']);
        } else {
            return Response::json(['status' => 'success','data' => [],'message' => '验证码正确']);
        }
    }

    /**
	 * 通过CURL发送HTTP请求
	 * @param string $url  //请求URL
	 * @param array $postFields //请求参数 
	 * @return mixed
	 */
	private function curlPost($url,$postFields){
		
		$postFields = json_encode($postFields);
	//	echo $postFields.'<br>';
		//echo $postFields;
		$ch = curl_init ();
		curl_setopt( $ch, CURLOPT_URL, $url ); 
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Accept-Encoding: identity',
			'Content-Length: ' . strlen($postFields),
			'Accept:application/json',
			'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
			)
		);

		//curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
         	curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
       		curl_setopt( $ch, CURLOPT_TIMEOUT,60); 
        	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
		$ret = curl_exec ( $ch );
		

    

        if (false == $ret) {
            $result = curl_error(  $ch);
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);

            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
		return $result;
	}
	
}