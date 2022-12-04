<?php

namespace App\Http\Controllers\Api;

use App\Components\Helpers;
use App\Components\ServerChan;
use App\Components\tronapi;
use App\Http\Controllers\Controller;
use App\Http\Models\Article;
use App\Http\Models\Goods;
use App\Http\Models\Coupon;
use App\Http\Models\Ticket;
use App\Http\Models\TicketReply;
use App\Http\Models\User;
use App\Http\Models\UserDeviceLog;
use App\Mail\newTicket;
use App\Mail\replyTicket;
use App\Http\Models\UserBalanceLog;
use Auth;
use Illuminate\Http\Request;
use Mail;
use Response;
use Cache;
use Hash;
use DB;


class UserController extends Controller
{
    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }

    public function brand(Request $request)
    {

        return Response::view('api.brand');
    }

    public function help(Request $request)
    {

        return Response::view('api.help');
    }

    public function notice(Request $request)
    {
        $notice = Article::type(2)->orderBy('id','desc')->first();
        return Response::json(['status' => 'success','data' => $notice,'message' => '']);
    }

    // 签到
    public function signin(Request $request)
    {

        // 系统开启登录加积分功能才可以签到
        if (!self::$systemConfig['is_checkin']) {
            return Response::json(['status' => 'fail','data' => [],'message' => '系统未开启签到功能']);
        }

        $username = trim($request->get('username'));

        if (!$username) {
            return Response::json(['status' => 'fail','data' => [],'message' => '请输入用户名和密码']);
        }

        $user = User::query()->where('username',$username)->where('status','>=',0)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在或已被禁用']);
        }

        // 已签到过，验证是否有效
        if (Cache::has('userCheckIn_' . $user->id)) {
            return Response::json(['status' => 'fail','data' => [],'message' => '已经签到过了，明天再来吧']);
        }

        $traffic = mt_rand(self::$systemConfig['min_rand_traffic'],self::$systemConfig['max_rand_traffic']);
        $ret     = $user->increment('transfer_enable',$traffic * 1048576);
        if (!$ret) {
            return Response::json(['status' => 'fail','data' => [],'message' => '签到失败，系统异常']);
        }

        // 写入用户流量变动记录
        Helpers::addUserTrafficModifyLog($user->id,0,$user->transfer_enable,$user->transfer_enable + $traffic * 1048576,'[签到]');

        // 多久后可以再签到
        $ttl = self::$systemConfig['traffic_limit_time']?self::$systemConfig['traffic_limit_time']:1440;
        Cache::put('userCheckIn_' . $user->id,'1',$ttl);

        return Response::json(['status' => 'success','data' => [],'message' => '签到成功，系统送您 ' . $traffic . 'M 流量']);
    }

    public function ticket(Request $request)
    {
        $username = trim($request->get('username'));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $list = Ticket::query()->where('user_id',$user->id)->orderBy('id','desc')->paginate(10,['id','content'])->appends($request->except('page'));

        foreach ($list as &$v) {
            $reply      = TicketReply::query()->where('ticket_id',$v->id)->orderBy('id','asc')->get(['user_id','content']);
            $data = [];
            foreach ($reply as $l) {
                if ($l['user_id'] == $user->id) {
                    $tmp = [
                        'is_admin'   => 0,
                        'content' => $l['content'],
                    ];
                } else {
                    $tmp = [
                        'is_admin'   => 1,
                        'content' => $l['content'],
                    ];
                }
                $data[] = $tmp;
            }
            $v['reply'] = $data;
        }
        return Response::json(['status' => 'success','data' => $list->items(),'message' => '']);
    }

    public function ticketadd(Request $request)
    {
        $username = trim($request->get('username'));
        $content  = clean($request->get('content'));
        $title    = $content;
        $content  = str_replace("eval","",str_replace("atob","",$content));

        if (empty($title) || empty($content)) {
            return Response::json(['status' => 'fail','data' => '','message' => '请输入标题和内容']);
        }

        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $ticket = Ticket::query()->where('user_id',$user->id)->whereIn('status',[0,1])->first();

        if ($ticket) {
            $obj            = new TicketReply();
            $obj->ticket_id = $ticket->id;
            $obj->user_id   = $user->id;
            $obj->content   = $title . "\r\n" . $content;
            $obj->save();

            if ($obj->id) {
                // 重新打开工单
                $ticket->status = 0;
                $ticket->save();

                $title   = "工单回复提醒";
                $content = "标题：【" . $ticket->title . "】<br>用户回复：" . $content;

                // 发邮件通知管理员
                if (self::$systemConfig['crash_warning_email']) {
                    $logId = Helpers::addEmailLog(self::$systemConfig['crash_warning_email'],$title,$content);
                    Mail::to(self::$systemConfig['crash_warning_email'])->send(new replyTicket($logId,$title,$content));
                }

                //ServerChan::send($title, $content);

                return Response::json(['status' => 'success','data' => '','message' => '回复成功']);
            } else {
                return Response::json(['status' => 'fail','data' => '','message' => '回复失败']);
            }

        } else {
            $obj          = new Ticket();
            $obj->user_id = $user->id;
            $obj->title   = $title;
            $obj->content = $content;
            $obj->status  = 0;
            $obj->save();

            if ($obj->id) {
                $emailTitle = "新工单提醒";
                $content    = "标题：【" . $title . "】<br>内容：" . $content;

                // 发邮件通知管理员
                if (self::$systemConfig['crash_warning_email']) {
                    $logId = Helpers::addEmailLog(self::$systemConfig['crash_warning_email'],$emailTitle,$content);
                    Mail::to(self::$systemConfig['crash_warning_email'])->send(new newTicket($logId,$emailTitle,$content));
                }

                //ServerChan::send($emailTitle,$content);

                return Response::json(['status' => 'success','data' => '','message' => '提交成功']);
            } else {
                return Response::json(['status' => 'fail','data' => '','message' => '提交失败']);
            }

        }

    }


    public function getgoods(Request $request)
    {
        $username = trim($request->get('username'));

        $is_vip = trim($request->get('is_vip',1));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            echo '账号不存在，请重试';
            exit;
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $view['user'] = $user;

        $view['type'] = $request->get('type');

        $view['list'] = Goods::where('is_vip',$is_vip)->type(2)->limit(12)->get();

        $view['lista'] = Goods::where('is_vip',$is_vip)->type(1)->limit(12)->get();

        $view['skey'] = self::$systemConfig['stripepay_publish_key'];

        $view['is_vip'] = $is_vip;

        $view['url'] = $request->url()."?username=".$username.'&type='.$request->get('type','pc');

        return Response::view('api.goods',$view);
    }

    public function chargecoupon(Request $request){

        $username = trim($request->get('username'));
         // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            echo '账号不存在，请重试';
            exit;
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $coupon_sn = trim($request->get('coupon_sn'));

        $coupon = Coupon::query()->where('status', 0)->where('type', 3)->where('sn', $coupon_sn)->first();
            if (!$coupon) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '充值卡不存在']);
            }


        $coupon = Coupon::type(3)->where('sn', $coupon_sn)->where('status', 0)->first();
        if (!$coupon) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '该券不可用']);
        }

        DB::beginTransaction();
        try {

            $amount = $coupon->amount*100;
             $log = new UserBalanceLog();
            $log->user_id = $user->id;
            $log->order_id = 0;
            $log->before = $user->balance;
            $log->after = $user->balance + $amount;
            $log->amount = $amount;
            $log->desc = '用户手动充值 - [充值券：' .$coupon_sn . ']';
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();

            // 余额充值
            $user->increment('balance', $amount);

            // 更改卡券状态
            $coupon->order_id = 0;
            $coupon->user_id = $user->id;
            $coupon->status = 1;
            $coupon->save();

            // 写入卡券日志
            Helpers::addCouponLog($coupon->id, 0, 0, '账户余额充值使用');

            DB::commit();

            return Response::json(['status' => 'success', 'data' => '', 'message' => '充值成功']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '充值失败']);
        }

    }


    public function goodslist(Request $request)
    {

        $list = Goods::all();

        return Response::json(['status' => 'success', 'data' => $list, 'message' => '']);
    }

    //公告列表
    public function articleList(Request $request){
        $req = $request->all();
        $page = isset($req['page'])?$req['page']:1;
        $page_size = isset($req['page_size'])?$req['page_size']:0;

        $List = Article::type(2)->orderBy('sort', 'desc')->orderBy('id', 'desc')->limit($page*$page_size)->paginate($page_size)->toArray();
        return Response::json($List);
    }

    //公告详情
    public function articleInfo(Request $request){
        $info = Article::query()->findOrFail($request->id);
        return Response::json($info);
    }

    //用户设备日志记录
    public function addDeviceLog(Request $request){

        $req = $request->all();
        $userId = isset($req['UserID'])?$req['UserID']:'';
        if($userId == ''){
            return Response::json(['code'=>-1, 'msg'=>'no user info' ,'data'=>''],200);
        }
        $DeviceType = isset($req['DeviceType'])?$req['DeviceType']:0;
        $DeviceName = isset($req['DeviceName'])?$req['DeviceName']:'';
        $DeviceModel = isset($req['DeviceModel'])?$req['DeviceModel']:'';
        $DeviceIMEI = isset($req['DeviceIMEI'])?$req['DeviceIMEI']:'';

        $in = false;
        //判断用户登陆设备是否在最近三组设备
        $imei = UserDeviceLog::where(['UserID'=>$userId])->select('DeviceIMEI')->orderBy('LoginTime','desc')->limit(3)->get()->toArray();
        foreach ($imei as $k=>$v){
            if($DeviceIMEI == $v['DeviceIMEI']){
                $in = true;
            }
        }

        if (!$in && count($imei)>=3){
            return Response::json(['code'=>-2, 'msg'=>'please remove your device' ,'data'=>''],200);
        }

        $insert = [
            'UserID' => $userId,
            'DeviceType' => $DeviceType,
            'DeviceName' => $DeviceName,
            'DeviceModel' => $DeviceModel,
            'DeviceIMEI'  => $DeviceIMEI,
            'LoginTime'   => date('Y-m-d H:i:s', time())
        ];

        $res = UserDeviceLog::insert($insert);
        if ($res){
            return Response::json(['code'=>0, 'msg'=>'ok' ,'data'=>''],200);
        }else{
            return Response::json(['code'=>-1, 'msg'=>'add log err' ,'data'=>''],200);
        }
    }

    //用户设备列表
    public function deviceList(Request $request){
        $req = $request->all();
        $userId = isset($req['UserID'])?$req['UserID']:'';
        if($userId == ''){
            return Response::json(['code'=>-1, 'msg'=>'no user info' ,'data'=>''],200);
        }
        $res = UserDeviceLog::where(['UserID'=>$userId])->orderBy('LoginTime','desc')->get()->toArray();
        if ($res){
            return Response::json(['code'=>0, 'msg'=>'ok' ,'data'=>$res],200);
        }else{
            return Response::json(['code'=>-1, 'msg'=>'get list err' ,'data'=>''],200);
        }
    }

    //设备解除列表
    public function deviceRemove(Request $request){
        $req = $request->all();
        $id = isset($req['id'])?$req['id']:'';
        if($id == ''){
            return Response::json(['code'=>-1, 'msg'=>'no device info' ,'data'=>''],200);
        }
        $res = UserDeviceLog::where(['ID'=>$id])->delete();
        if ($res){
            return Response::json(['code'=>0, 'msg'=>'ok' ,'data'=>''],200);
        }else{
            return Response::json(['code'=>-1, 'msg'=>'delete log err' ,'data'=>''],200);
        }
    }

    //发送邮箱验证码
    public function sendEmail(Request $request){

        $req = $request->all();
        $email = isset($req['email'])?$req['email']:'';
        if ($email==''){
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '缺失必要参数']);
        }
        $userId = isset($req['UserID'])?$req['UserID']:'';
        if ($userId==''){
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '缺失必要参数']);
        }
        $subject = "Email Verify";
        $randCode = rand(100000,999999);
        session($userId.$randCode);

        $message = "验证码：$randCode 请你在30分钟内输入。请勿告诉他人，如非本人操作，请忽略此信息。";
        $from = "someonelse@example.com";
        $headers = "From: $from";
        $res = mail($email,$subject,$message,$headers);
        if($res){
            return Response::json(['status' => 'success', 'data' => '', 'message' => '发送成功']);
        }else{
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '邮箱验证码发送失败']);
        }
    }


    //订单创建接口
    public function createTronOrder(Request $request){
        $publicKey = '919213B10030491DA6527F06673C84B2';
        $privateKey = 'F9BF357AB43F4262ADCE2446DCD1C1E4';

        $client = new tronapi\Tronapi($publicKey, $privateKey);

        /* =====================================================================
        订单创建
        接口地址：https://doc.tronapi.com/api/transaction/create.html
        ===================================================================== */

        $amount = 100;
        $currency = 'CNY';
        $coinCode = 'USDT';
        $orderId = '123456';
        $productName = 'test';
        $customerId = 'testname';
        $notifyUrl = 'http://www.vpn.com:8101/api/createTronOrder';
        $redirectUrl = 'http://www.vpn.com:8101/api/createTronOrder';

        $transactionData = $client->transaction->create(
            $amount,
            $currency,
            $coinCode,
            $orderId,
            $customerId,
            $productName,
            $notifyUrl,
            $redirectUrl
        );

    }
}
