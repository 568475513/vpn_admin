<?php

namespace App\Http\Controllers\Api;

use App\Components\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Models\Invite;
use App\Http\Models\User;
use App\Http\Models\UserLabel;
use App\Http\Models\UserSubscribe;
use App\Http\Models\UserSubscribeLog;
use App\Http\Models\SsNodeOnlineLog;
use App\Http\Models\Verify;
use App\Mail\activeUser;
use Illuminate\Http\Request;
use Mail;
use Redirect;
use Response;
use Cache;
use Hash;
use DB;
use Session;

/**
 * 登录接口
 *
 * Class LoginController
 *
 * @package App\Http\Controllers
 */
class LoginController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    // 登录返回订阅信息
    public function login(Request $request)
    {
        $username = trim($request->get('username'));
        $password = trim($request->get('password'));
        $cacheKey = 'request_times_' . md5(getClientIp());

        if (!$username || !$password) {
            //  Cache::increment($cacheKey);

            return Response::json(['status' => 'fail','data' => [],'message' => '请输入用户名和密码']);
        }

        // 连续请求失败15次，则封IP一小时
        /*  if (Cache::has($cacheKey)) {
              if (Cache::get($cacheKey) >= 100) {
                  return Response::json(['status' => 'fail','data' => [],'message' => '请求失败超限，禁止访问1小时']);
              }
          } else {
              Cache::put($cacheKey,1,60);
          }
          */

        $user = User::query()->where('username',$username)->where('status','>=',0)->first();
        if (!$user) {
            //   Cache::increment($cacheKey);

            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在或已被禁用']);
        } else if (!Hash::check($password,$user->password)) {
            return Response::json(['status' => 'fail','data' => [],'message' => '用户名或密码错误']);
        }

        DB::beginTransaction();
        try {
            // 如果未生成过订阅链接则生成一个
            $subscribe = UserSubscribe::query()->where('user_id',$user->id)->first();

            // 更新订阅链接访问次数
            $subscribe->increment('times',1);

            // 记录每次请求
            $this->log($subscribe->id,getClientIp(),'API访问');

            // 订阅链接
            $url = self::$systemConfig['subscribe_domain']?self::$systemConfig['subscribe_domain']:self::$systemConfig['website_url'];

            // 节点列表
            $userLabelIds = UserLabel::query()->where('user_id',$user->id)->pluck('label_id');
            if (empty($userLabelIds)) {
                return Response::json(['status' => 'fail','message' => '','data' => []]);
            }

            $nodeList = DB::table('ss_node')
                ->selectRaw('ss_node.*')
                ->leftJoin('ss_node_label','ss_node.id','=','ss_node_label.node_id')
                ->whereIn('ss_node_label.label_id',$userLabelIds)
                ->where('ss_node.status',1)
                ->groupBy('ss_node.id')
                ->orderBy('ss_node.sort','desc')
                ->orderBy('ss_node.id','asc')
                ->get();

           $c_nodes = collect();
            foreach ($nodeList as $node) {
            	$online_log = SsNodeOnlineLog::query()->where('node_id', $node->id)->where('log_time', '>=', strtotime("-5 minutes"))->orderBy('id', 'desc')->first();
            	$node->online_users = empty($online_log) ? 0 : $online_log->online_user;

              if( $node->single == 1 ){
                $temp_node = [
                    'name'          => $node->name,
                    'server'        => ($node->server == null)?'':$node->server,
                    'server_port'   => ($node->single_port == null)?'':$node->single_port,
                    'password'      => ($node->single_passwd == null)?'':$node->single_passwd,
                    'method'        => ($node->single_method == null)?'':$node->single_method,
                    'obfs'          => ($node->single_obfs == null)?'':$node->single_obfs,
                    'obfsparam'     => ($node->obfs_param == null)?'':$node->obfs_param,
                    'protocol'      => ($node->single_protocol == null)?'':$node->single_protocol,
                    'protocolparam' => $user->port.":".$user->passwd,
                    'flags'         => $url . '/assets/images/country/' . $node->country_code . '.png',
                    'group'         => $node->desc,
                ];
              } else {
                $temp_node = [
                    'name'          => $node->name,
                    'server'        => ($node->server == null)?'':$node->server,
                    'server_port'   => ($user->port == null)?'':$user->port,
                    'password'      => ($user->passwd == null)?'':$user->passwd,
                    'method'        => ($user->method == null)?'':$user->method,
                    'obfs'          => ($user->obfs == null)?'':$user->obfs,
                    'obfsparam'     => ($user->obfs_param == null)?'':$user->obfs_param,
                    'protocol'      => ($user->protocol == null)?'':$user->protocol,
                    'protocolparam' => ($user->protocol_param == null)?'':$user->protocol_param,
                    'flags'         => $url . '/assets/images/country/' . $node->country_code . '.png',
                    'group'         => $node->desc,
                ];
              }
                $c_nodes   = $c_nodes->push($temp_node);
            }

            $data = [
                'status'       => 1,
                'class'        => 0,
                'level'        => $user->agent_level,
                'expire_in'    => $user->expire_time,
                'invite_code'  => $user->invite_code?$user->invite_code:'',
                'text'         => '',
                'buy_link'     => '',
                'money'        => $user->balance?$user->balance:'0.00',
                'sspannelName' => 'ssrpanel',
                'usedTraffic'  => flowAutoShow($user->u + $user->d),
                'Traffic'      => flowAutoShow($user->transfer_enable),
                'all'          => 1,
                'residue'      => '',
                'nodes'        => $c_nodes,
                'link'         => $url . '/s/' . $subscribe->code,
                'total'        => $user->transfer_enable-($user->u + $user->d),
            ];

            DB::commit();

            $hbcode = trim($request->get('hbcode'));
            $hbtype = trim($request->get('hbtype'));
            if( empty($hbtype) || $hbtype == ''){
                $hbtype = 'wap';
            }
            if ($hbcode != '') {
                Cache::put('hb_' .$hbtype.'_'. $username,$hbcode,60 * 24 * 365);
            }
            
            return Response::json(['status' => 'success','data' => $data,'message' => '登录成功']);
        } catch (\Exception $e) {
            DB::rollBack();

            return Response::json(['status' => 'success','data' => [],'message' => '登录失败']);
        }
    }

    // 写入订阅访问日志
    private function log($subscribeId,$ip,$headers)
    {
        $log                 = new UserSubscribeLog();
        $log->sid            = $subscribeId;
        $log->request_ip     = $ip;
        $log->request_time   = date('Y-m-d H:i:s');
        $log->request_header = $headers;
        $log->save();
    }

    public function register(Request $request)
    {

        // 是否开启注册
        if (!self::$systemConfig['is_register']) {
            return Response::json(['status' => 'fail','data' => [],'message' => '系统维护，暂停注册']);
        }

        $username = trim($request->get('username'));
        $password = trim($request->get('password'));
        $code     = trim($request->get('jiqi_code'));
/*
        if( $code == ''){
            return Response::json(['status' => 'fail','data' => [],'message' => '设备错误,重新运行']);
        }

        $user = User::query()->where('jiqi_code',$code)->first();
        if ($user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码错误，请重新获取']);
        }
*/
        

        $get = Cache::get('sms_' . $username);
        if (FALSE === $get || $get == '') {
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码已过期']);
        }

        if ($get != $code) {
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码不正确']);
        }

        $user = User::query()->where('username',$username)->first();
        if ($user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号已被注册']);
        }

        // 如果需要邀请注册
        if (self::$systemConfig['is_invite_register']) {
            // 必须使用邀请码
            if (self::$systemConfig['is_invite_register'] == 2 && !$request->invite_code) {
                return Response::json(['status' => 'fail','data' => [],'message' => '请输入邀请码']);
            }

            // 校验邀请码合法性
            if ($request->invite_code) {
                $affArr = User::query()->where('invite_code',$request->invite_code)->first();
                if (!$affArr) {
                    return Response::json(['status' => 'fail','data' => [],'message' => '邀请码不可用，请重试']);
                }
            }
        }

        // 获取可用端口
        $port = self::$systemConfig['is_rand_port']?Helpers::getRandPort():Helpers::getOnlyPort();
        if ($port > self::$systemConfig['max_port']) {
            return Response::json(['status' => 'fail','data' => [],'message' => '系统不再接受新用户，请联系管理员']);
        }

        // 获取aff
        $referral_uid = isset($affArr['id'])?$affArr['id']:0;

        $transfer_enable = $referral_uid?(self::$systemConfig['default_traffic'] + self::$systemConfig['referral_traffic']) * 1048576:self::$systemConfig['default_traffic'] * 1048576;

        do {
            $invite_code = makeRandStr(6,TRUE);
            $has         = User::query()->where('invite_code',$invite_code)->first();
        } while ($has);

        // 创建新用户
        $user                  = new User();
        $user->username        = $username;
        $user->password        = Hash::make($password);
        $user->port            = $port;
        $user->passwd          = makeRandStr();
        $user->vmess_id        = createGuid();
        $user->transfer_enable = $transfer_enable;
        $user->method          = Helpers::getDefaultMethod();
        $user->protocol        = Helpers::getDefaultProtocol();
        $user->obfs            = Helpers::getDefaultObfs();
        $user->enable_time     = date('Y-m-d H:i:s');
        if ($referral_uid) {
            $user->expire_time = date('Y-m-d H:i:s',strtotime("+" . self::$systemConfig['referral_days'] . " days"));
        } else {
            $user->expire_time = date('Y-m-d H:i:s',strtotime("+" . self::$systemConfig['default_days'] . " days"));
        }
        $user->reg_ip       = getClientIp();
        $user->referral_uid = $referral_uid;
        $user->invite_code  = $invite_code;
        $user->jiqi_code   = $code;
        $user->save();

        // 注册失败，抛出异常
        if (!$user->id) {
            return Response::json(['status' => 'fail','data' => [],'message' => '注册失败，请联系管理员']);
        }

        // 生成订阅码
        $subscribe          = new UserSubscribe();
        $subscribe->user_id = $user->id;
        $subscribe->code    = Helpers::makeSubscribeCode();
        $subscribe->times   = 0;
        
        $subscribe->save();

        // 初始化默认标签
        if (strlen(self::$systemConfig['initial_labels_for_user'])) {
            $labels = explode(',',self::$systemConfig['initial_labels_for_user']);
            foreach ($labels as $label) {
                $userLabel           = new UserLabel();
                $userLabel->user_id  = $user->id;
                $userLabel->label_id = $label;
                $userLabel->save();
            }
        }

        if (self::$systemConfig['is_invite_register']) {
            if ($referral_uid) {
                $transfer_enable = self::$systemConfig['referral_traffic'] * 1048576;

                User::query()->where('id',$referral_uid)->increment('transfer_enable',$transfer_enable);
                User::query()->where('id',$referral_uid)->update(['status' => 1,'enable' => 1]);
            }

            User::query()->where('id',$user->id)->update(['status' => 1,'enable' => 1]);
        }

        User::query()->where('id',$user->id)->update(['status' => 1,'enable' => 1]);
        Cache::forget('sms_' . $username);
        return Response::json(['status' => 'success','data' => [],'message' => '注册成功']);
    }

    public function resetpass(Request $request)
    {

        // 是否开启重设密码
        if (!self::$systemConfig['is_reset_password']) {
            return Response::json(['status' => 'fail','data' => [],'message' => '系统未开启重置密码功能，请联系管理员']);
        }

        $username = trim($request->get('username'));
        $password = trim($request->get('password'));
        $code     = trim($request->get('jiqi_code'));

        $get = Cache::get('sms_' . $username);
        if (FALSE === $get || $get == '') {
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码已过期']);
        }

        if ($get != $code) {
            return Response::json(['status' => 'fail','data' => [],'message' => '验证码不正确']);
        }

        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        // 更新密码
        $ret = User::query()->where('id',$user->id)->update(['password' => Hash::make($password)]);
        if (!$ret) {
            return Response::json(['status' => 'fail','data' => [],'message' => '重设密码失败']);
        }
        Cache::forget('sms_' . $username);
        return Response::json(['status' => 'success','data' => [],'message' => '新密码设置成功，请自行登录']);

    }

    public function checkhb(Request $request)
    {
        $username = trim($request->get('username'));
        $hbcode   = trim($request->get('hbcode'));
        $hbtype = trim($request->get('hbtype'));
        if( empty($hbtype) || $hbtype == ''){
            $hbtype = 'wap';
        }
        $get      = Cache::get('hb_'.$hbtype.'_' . $username);
        if (FALSE == $get || $get == '') {
            return Response::json(['status' => 'success','data' => [],'message' => '心跳已过期或不存在']);
        }

        if ($get != $hbcode) {
            return Response::json(['status' => 'success','data' =>[],'message' => '心跳失败']);
        } else {
        	$user = User::query()->where('username',$username)->where('status','>=',0)->first();
        	
           $used = $user->u + $user->d;
           $total = $user->transfer_enable;
           $all = $total - $used;
           
            if( $all <= 82428800 ){
             	return Response::json(['status' => 'success','data' =>[],'message' => '流量用完']);
             }
             
        	$data = [
                'expire_in'    => $user->expire_time,
                'usedTraffic'  => flowAutoShow($used),
                'Traffic'      => flowAutoShow($total),
            ];
         
            
            return Response::json(['status' => 'success','data' =>$data,'message' => '心跳正确']);
        }
    }
}