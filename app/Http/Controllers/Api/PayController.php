<?php

namespace App\Http\Controllers\Api;


use App\Components\AlipaySubmit;
use App\Components\Helpers;
use App\Components\Yzy;
use App\Http\Controllers\Controller;
use App\Http\Models\Coupon;
use App\Http\Models\Goods;
use App\Http\Models\GoodsLabel;
use App\Http\Models\Order;
use App\Http\Models\Payment;
use App\Http\Models\User;
use App\Http\Models\UserLabel;
use Auth;
use DB;
use Illuminate\Http\Request;
use Log;
use Payment\Client\Charge;
use Response;
use Validator;

class PayController extends Controller
{

    protected static $systemConfig;

    function __construct()
    {
        self::$systemConfig = Helpers::systemConfig();
    }
    
    // 创建支付单
    public function create(Request $request)
    {

        $username = trim($request->get('username'));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $goods_id = intval($request->get('goods_id'));

        $goods = Goods::query()->where('status', 1)->where('id', $goods_id)->first();
        if (!$goods) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：商品或服务已下架']);
        }

        // 判断是否开启有赞云支付
        if (!self::$systemConfig['is_youzan'] && !self::$systemConfig['is_alipay'] && !self::$systemConfig['is_f2fpay'] && !self::$systemConfig['is_stripepay'] && !self::$systemConfig['is_wechatpay']) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：系统并未开启在线支付功能']);
        }

        // 判断是否存在同个商品的未支付订单
/*        $existsOrder = Order::query()->where('user_id',$user->id)->where('status', 0)->where('goods_id', $goods_id)->exists();
        if ($existsOrder) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：尚有未支付的订单，请先去支付']);
        }*/

        $amount = $goods->price;
        // 价格异常判断
        if ($amount < 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价异常']);
        } elseif ($amount == 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价为0，无需使用在线支付']);
        }

        // 验证账号是否存在有效期更长的套餐
        if ($goods->type == 2) {
            $existOrderList = Order::query()->where('user_id',$user->id)
                ->with(['goods'])
                ->whereHas('goods', function ($q) {
                    $q->where('type', 2);
                })
                ->where('is_expire', 0)
                ->where('status', 2)
                ->get();

            foreach ($existOrderList as $vo) {
                if ($vo->goods->days > $goods->days) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您已存在有效期更长的套餐，只能购买包年套餐或流量包']);
                }
            }
        }
        
       /* if ($goods->type == 1) {
            $existOrders = Order::query()->where('user_id',$user->id)
                ->with(['goods'])
                ->whereHas('goods', function ($q) {
                    $q->where('type', 2);
                })
                ->where('is_expire', 0)
                ->where('status', 2)
                ->count();
            if ($existOrders<=0) {
                return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：请先购买包月或者包年套餐，在购买流量包']);
            }
        }*/

        DB::beginTransaction();
        try {
            $orderSn = date('ymdHis') . mt_rand(100000, 999999);
            $sn = makeRandStr(12);

            // 支付方式
            if (self::$systemConfig['is_youzan']) {
                $pay_way = 2;
            } elseif (self::$systemConfig['is_alipay']) {
                $pay_way = 4;
            } elseif (self::$systemConfig['is_f2fpay']) {
                $pay_way = 5;
            } elseif (self::$systemConfig['is_stripepay']) {
                $pay_way = 6;
            }elseif (self::$systemConfig['is_wechatpay']) {
                $pay_way = 7;
            }

            // 生成订单
            $order = new Order();
            $order->order_sn = $orderSn;
            $order->user_id = $user->id;
            $order->goods_id = $goods_id;
            $order->coupon_id = !empty($coupon) ? $coupon->id : 0;
            $order->origin_amount = $goods->price;
            $order->amount = $amount;
            $order->expire_at = date("Y-m-d H:i:s", strtotime("+" . $goods->days . " days"));
            $order->is_expire = 0;
            $order->pay_way = $pay_way;
            $order->status = 0;
            $order->save();

            // 生成支付单
            if (self::$systemConfig['is_youzan']) {
                $yzy = new Yzy();
                $result = $yzy->createQrCode($goods->name, $amount * 100, $orderSn);
                if (isset($result['error_response'])) {
                    Log::error('【有赞云】创建二维码失败：' . $result['error_response']['msg']);

                    throw new \Exception($result['error_response']['msg']);
                }
            } elseif (self::$systemConfig['is_alipay']) {
                $parameter = [
                    "service"        => "create_forex_trade", // WAP:create_forex_trade_wap ,即时到帐:create_forex_trade
                    "partner"        => self::$systemConfig['alipay_partner'],
                    "notify_url"     => self::$systemConfig['website_url'] . "/api/alipay", // 异步回调接口
                    "return_url"     => self::$systemConfig['website_url'],
                    "out_trade_no"   => $orderSn,  // 订单号
                    "subject"        => "Package", // 订单名称
                    //"total_fee"      => $amount, // 金额
                    "rmb_fee"        => $amount,   // 使用RMB标价，不再使用总金额
                    "body"           => "",        // 商品描述，可为空
                    "currency"       => self::$systemConfig['alipay_currency'], // 结算币种
                    "product_code"   => "NEW_OVERSEAS_SELLER",
                    "_input_charset" => "utf-8"
                ];

                // 建立请求
                $alipaySubmit = new AlipaySubmit(self::$systemConfig['alipay_sign_type'], self::$systemConfig['alipay_partner'], self::$systemConfig['alipay_key'], self::$systemConfig['alipay_private_key']);
                $result = $alipaySubmit->buildRequestForm($parameter, "post", "确认");
            } elseif (self::$systemConfig['is_f2fpay']) {
                // TODO：goods表里增加一个字段用于自定义商品付款时展示的商品名称，
                // TODO：这里增加一个随机商品列表，根据goods的价格随机取值
                $result = Charge::run("ali_qr", [
                    'use_sandbox'     => false,
                    "partner"         => self::$systemConfig['f2fpay_app_id'],
                    'app_id'          => self::$systemConfig['f2fpay_app_id'],
                    'sign_type'       => 'RSA2',
                    'ali_public_key'  => self::$systemConfig['f2fpay_public_key'],
                    'rsa_private_key' => self::$systemConfig['f2fpay_private_key'],
                    'notify_url'      => self::$systemConfig['website_url'] . "/api/f2fpay", // 异步回调接口
                    'return_url'      => self::$systemConfig['website_url'],
                    'return_raw'      => false
                ], [
                    'body'     => '',
                    'subject'  => self::$systemConfig['f2fpay_subject_name'],
                    'order_no' => $orderSn,
                    'amount'   => $amount,
                ]);
            }  else if( self::$systemConfig['is_stripepay'] ){

				\Stripe\Stripe::setApiKey(self::$systemConfig['stripepay_api_key']);
				
				$paytype = $request->get('paytype')??"wechat";
				
				$stripe_info = 	\Stripe\Source::create([
					  "type" => $paytype,
					  "currency" => "HKD",
					  "amount" => $amount*100,
					  "redirect" => [
						    "return_url" => self::$systemConfig['website_url'].'/api/pay_result'
						  ]
					]);

                $payment = new Payment();
                $payment->sn = $stripe_info['id'];
                $payment->user_id = $user->id;
                $payment->oid = $order->oid;
                $payment->order_sn = $orderSn;
                $payment->pay_way = $paytype=="alipay"?2:1;
                $payment->amount = $amount;
                $payment->qr_id = 0;
                $payment->status = 0;
                $payment->save();
                DB::commit();
                
                if( $paytype == "wechat"){
                	$to_url = $stripe_info['wechat']['qr_code_url'];
                } else {
                	$to_url = $stripe_info['redirect']['url'];
                }
        
                return Response::json(['status' => 'success', 'data' => ['amount'=>$amount*100,'orderSn'=>$payment->order_sn,'url'=>$to_url], 'message' => '创建订单成功']);
            }else if( self::$systemConfig['is_wechatpay'] ){

			    
                $payment = new Payment();
                $payment->sn = $orderSn;
                $payment->user_id = $user->id;
                $payment->oid = $order->oid;
                $payment->order_sn = $orderSn;
                $payment->pay_way = 1;
                $payment->amount = $amount;
                $payment->qr_id = 0;
                $payment->status = 0;
                $payment->save();
                DB::commit();
                
                $result = $this->httpRequest('http://test.8iack.eu.org/addons/epay/api/submit?amount='.$amount.'&type=wechat&method=scan&out_trade_no='.$orderSn.'&notifyurl='.self::$systemConfig['website_url'].'/api/pay_wechat', []);
                
                if( $result['return_code'] == 'SUCCESS'){
                    $to_url = $result['code_url'];
                    return Response::json(['status' => 'success', 'data' => ['amount'=>$amount*100,'orderSn'=>$payment->order_sn,'url'=>$to_url], 'message' => '创建订单成功']);
                } else {
                    throw new \Exception('创建订单失败：'.$result['return_msg']);
                }
                
            }

            $payment = new Payment();
            $payment->sn = $sn;
            $payment->user_id = $user->id;
            $payment->oid = $order->oid;
            $payment->order_sn = $orderSn;
            $payment->pay_way = 1;
            $payment->amount = $amount;
            if (self::$systemConfig['is_youzan']) {
                $payment->qr_id = $result['response']['qr_id'];
                $payment->qr_url = $result['response']['qr_url'];
                $payment->qr_code = $result['response']['qr_code'];
                $payment->qr_local_url = $this->base64ImageSaver($result['response']['qr_code']);
            } elseif (self::$systemConfig['is_alipay']) {
                $payment->qr_code = $result;
            } elseif (self::$systemConfig['is_f2fpay']) {
                $payment->qr_code = $result;
                $payment->qr_url = 'https://www.vmgirls.com/qr/?m=3&e=H&p=6&url=' . $result ;
                $payment->qr_local_url = $payment->qr_url;
            }
            $payment->status = 0;
            $payment->save();

            DB::commit();

            if (self::$systemConfig['is_alipay'] || self::$systemConfig['is_f2fpay']) { // Alipay返回支付信息
                return Response::json(['status' => 'success', 'data' => ['url'=>$result,'sn'=>$sn,'qr_url'=>$payment->qr_url], 'message' => '创建订单成功']);
            } else {
                return Response::json(['status' => 'success', 'data' => $sn, 'message' => '创建订单成功，正在转到付款页面，请稍后']);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('创建支付订单失败：' . $e->getMessage());

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建订单失败：' . $e->getMessage()]);
        }
    }
    
    
    public function wechatpay(Request $request)
    {
        Log::error('微信支付回调：' . json_encode($request));
        $order_id = $request->input('out_trade_no');
        
         if (true) {
            // 商户订单号
            $data = [];
            $data['out_trade_no'] = $order_id;
            $order = Order::query()->with(['user'])->where('order_sn', $data['out_trade_no'])->first();
            if($order && $order->status != 2){
                 $this->tradePaid($data);
                 $user = User::query()->where('id', $order->user_id)->first(); // 重新取出user信息
                 echo 'SUCCESS';
                 exit;
            }
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '已支付完成']);
        }
        
    }


    public function balance(Request $request){

        $username = trim($request->get('username'));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $goods_id = intval($request->get('goods_id'));

        $goods = Goods::query()->where('status', 1)->where('id', $goods_id)->first();
        if (!$goods) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：商品或服务已下架']);
        }

        $amount = $goods->price;
        // 价格异常判断
        if ($amount < 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：订单总价异常']);
        }

        // 验证账号余额是否充足
        if ($user->balance < $amount) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您的余额不足，邀请好友即可免费获取余额']);
        }

        // 验证账号是否存在有效期更长的套餐
      /*  if ($goods->type == 2) {
            $existOrderList = Order::query()->where('user_id',$user->id)
                ->with(['goods'])
                ->whereHas('goods', function ($q) {
                    $q->where('type', 2);
                })
                ->where('is_expire', 0)
                ->where('status', 2)
                ->get();

            foreach ($existOrderList as $vo) {
                if ($vo->goods->days > $goods->days) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您已存在有效期更长的套餐，只能购买包年套餐或流量包']);
                }
            }
        } */

        DB::beginTransaction();
        try {
            // 生成订单
            $order = new Order();
            $order->order_sn = date('ymdHis') . mt_rand(100000, 999999);
            $order->user_id = $user->id;
            $order->goods_id = $goods_id;
            $order->coupon_id = !empty($coupon) ? $coupon->id : 0;
            $order->origin_amount = $goods->price;
            $order->amount = $amount;
            $order->expire_at = date("Y-m-d H:i:s", strtotime("+" . $goods->days . " days"));
            $order->is_expire = 0;
            $order->pay_way = 1;
            $order->status = 2;
            $order->save();

            // 扣余额
            User::query()->where('id', $user->id)->decrement('balance', $amount * 100);

            // 记录余额操作日志
            $this->addUserBalanceLog($user->id, $order->oid, $user->balance, $user->balance - $amount, -1 * $amount, '购买服务：' . $goods->name);


            // 如果买的是套餐，则先将之前购买的所有套餐置都无效，并扣掉之前所有套餐的流量，重置用户已用流量为0
            if ($goods->type == 2) {
                $existOrderList = Order::query()
                    ->with(['goods'])
                    ->whereHas('goods', function ($q) {
                        $q->where('type', 2);
                    })
                    ->where('user_id', $order->user_id)
                    ->where('oid', '<>', $order->oid)
                    ->where('is_expire', 0)
                    ->where('status', 2)
                    ->get();

                foreach ($existOrderList as $vo) {
                    Order::query()->where('oid', $vo->oid)->update(['is_expire' => 1]);

                    // 先判断，防止手动扣减过流量的用户流量被扣成负数
                    if ($order->user->transfer_enable - $vo->goods->traffic * 1048576 <= 0) {
                        // 写入用户流量变动记录
                        Helpers::addUserTrafficModifyLog($user->id, $order->oid, 0, 0, '[余额支付]用户购买套餐，先扣减之前套餐的流量(扣完)');

                        User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0, 'transfer_enable' => 0]);
                    } else {
                        // 写入用户流量变动记录
                        $user = User::query()->where('id', $user->id)->first(); // 重新取出user信息
                        Helpers::addUserTrafficModifyLog($user->id, $order->oid, $user->transfer_enable, ($user->transfer_enable - $vo->goods->traffic * 1048576), '[余额支付]用户购买套餐，先扣减之前套餐的流量(未扣完)');

                        User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0]);
                        User::query()->where('id', $order->user_id)->decrement('transfer_enable', $vo->goods->traffic * 1048576);
                    }
                }
            }

            // 写入用户流量变动记录
            $user = User::query()->where('id', $user->id)->first(); // 重新取出user信息
            Helpers::addUserTrafficModifyLog($user->id, $order->oid, $user->transfer_enable, ($user->transfer_enable + $goods->traffic * 1048576), '[余额支付]用户购买商品，加上流量');

            // 把商品的流量加到账号上
            User::query()->where('id', $user->id)->increment('transfer_enable', $goods->traffic * 1048576);

            // 计算账号过期时间
               if( $order->user->expire_time < date('Y-m-d')){
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days"));
                } else {
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days",strtotime($order->user->expire_time)  ));
                }

            // 套餐就改流量重置日，流量包不改
            if ($goods->type == 2) {
                if (date('m') == 2 && date('d') == 29) {
                    $traffic_reset_day = 28;
                } else {
                    $traffic_reset_day = date('d') == 31 ? 30 : abs(date('d'));
                }
                User::query()->where('id', $order->user_id)->update(['traffic_reset_day' => $traffic_reset_day, 'expire_time' => $expireTime, 'enable' => 1]);
            } else {
                User::query()->where('id', $order->user_id)->update(['expire_time' => $expireTime, 'enable' => 1]);
            }

            // 写入用户标签
            if ($goods->label) {
                // 用户默认标签
                $defaultLabels = [];
                if (self::$systemConfig['initial_labels_for_user']) {
                    $defaultLabels = explode(',', self::$systemConfig['initial_labels_for_user']);
                }

                // 取出现有的标签
                $userLabels = UserLabel::query()->where('user_id', $user->id)->pluck('label_id')->toArray();
                $goodsLabels = GoodsLabel::query()->where('goods_id', $goods_id)->pluck('label_id')->toArray();

                // 标签去重
                $newUserLabels = array_values(array_unique(array_merge($userLabels, $goodsLabels, $defaultLabels)));

                // 删除用户所有标签
                UserLabel::query()->where('user_id', $user->id)->delete();

                // 生成标签
                foreach ($newUserLabels as $vo) {
                    $obj = new UserLabel();
                    $obj->user_id = $user->id;
                    $obj->label_id = $vo;
                    $obj->save();
                }
            }

            // 写入返利日志
            if ($user->referral_uid) {
                $this->addReferralLog($user->id, $user->referral_uid, $order->oid, $amount, $amount * self::$systemConfig['referral_percent']);
            }

            // 取消重复返利
            User::query()->where('id', $order->user_id)->update(['referral_uid' => 0]);

            DB::commit();

            return Response::json(['status' => 'success', 'data' => '', 'message' => '支付成功']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('支付订单失败：' . $e->getMessage());

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：' . $e->getMessage()]);
        }

    }


	public function paystripe(Request $request)
    {

        $orderSn = trim($request->get('orderSn'));
        $payment = Payment::query()->where('order_sn',$orderSn)->first();
        if (!$payment) {
            return Response::json(['status' => 'fail','data' => [],'message' => '订单不存在']);
        }
        $payment->sn = trim($request->get('sn'));
        $payment->save();
        return Response::json(['status' => 'success', 'data' => '', 'message' => '']);
    }
    
    
    // 获取订单支付状态
    public function check(Request $request)
    {

        $username = trim($request->get('username'));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $validator = Validator::make($request->all(), [
            'sn' => 'required|exists:payment,order_sn'
        ], [
            'sn.required' => '请求失败：缺少sn',
            'sn.exists'   => '支付失败：支付单不存在'
        ]);

        if ($validator->fails()) {
            return Response::json(['status' => 'error', 'data' => '', 'message' => $validator->getMessageBag()->first()]);
        }

        $payment = Payment::query()->where('user_id',$user->id)->where('order_sn', $request->sn)->first();
        if ($payment->status > 0) {
            return Response::json(['status' => 'success', 'data' => '', 'message' => '支付成功']);
        } elseif ($payment->status < 0) {
            return Response::json(['status' => 'error', 'data' => '', 'message' => '订单超时未支付，已自动关闭']);
        } else {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '等待支付']);
        }
    }
    
    
    public function result()
    {
    	
    	return Response::view('api.payresult');
    	
    }
    
    
    
    
    
    
    // 创建IOS支付单
    public function createorder(Request $request)
    {

        $username = trim($request->get('username'));
        // 查找账号
        $user = User::query()->where('username',$username)->first();
        if (!$user) {
            return Response::json(['status' => 'fail','data' => [],'message' => '账号不存在，请重试']);
        }

        $goods_id = intval($request->get('goods_id'));

        $goods = Goods::query()->where('status', 1)->where('id', $goods_id)->first();
        if (!$goods) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：商品或服务已下架']);
        }

        $amount = $goods->price;
        // 价格异常判断
        if ($amount < 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价异常']);
        } elseif ($amount == 0) {
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建支付单失败：订单总价为0，无需使用在线支付']);
        }

        // 验证账号是否存在有效期更长的套餐
       /* if ($goods->type == 2) {
            $existOrderList = Order::query()->where('user_id',$user->id)
                ->with(['goods'])
                ->whereHas('goods', function ($q) {
                    $q->where('type', 2);
                })
                ->where('is_expire', 0)
                ->where('status', 2)
                ->get();

            foreach ($existOrderList as $vo) {
                if ($vo->goods->days > $goods->days) {
                    return Response::json(['status' => 'fail', 'data' => '', 'message' => '支付失败：您已存在有效期更长的套餐，只能购买包年套餐或流量包']);
                }
            }
        }*/
        
        DB::beginTransaction();
        try {
            $orderSn = date('ymdHis') . mt_rand(100000, 999999);
            $sn = makeRandStr(12);

            // 支付方式
            $pay_way = 11;

            // 生成订单
            $order = new Order();
            $order->order_sn = $orderSn;
            $order->user_id = $user->id;
            $order->goods_id = $goods_id;
            $order->coupon_id = !empty($coupon) ? $coupon->id : 0;
            $order->origin_amount = $goods->price;
            $order->amount = $amount;
            $order->expire_at = date("Y-m-d H:i:s", strtotime("+" . $goods->days . " days"));
            $order->is_expire = 0;
            $order->pay_way = $pay_way;
            $order->status = 0;
            $order->save();

            DB::commit();

            return Response::json(['status' => 'success', 'data' => ['order_id'=>$orderSn], 'message' => '创建订单成功']);
            
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('创建支付订单失败：' . $e->getMessage());

            return Response::json(['status' => 'fail', 'data' => '', 'message' => '创建订单失败：' . $e->getMessage()]);
        }
    }
    
    
    public function iospaycallback(Request $request)
    {

        //苹果内购的验证收据
        $receipt_data = $request->input('apple_receipt');
        $order_id = $request->input('order_id');

        if(empty($receipt_data)){
             return Response::json(['status' => 'fail', 'data' => '', 'message' => '参数不正确']);
        }
        // 获取校验结果
        $POSTFIELDS = '{"receipt-data":"'. $receipt_data .'"}';
        $result = $this->httpRequest('https://sandbox.itunes.apple.com/verifyReceipt', $POSTFIELDS);
     
        if (!$result || !is_array($result) || !isset($result['status'])) {
             return Response::json(['status' => 'fail', 'data' => '', 'message' => '获取数据失败，请重试']);
        }
        //如果校验失败
        if ($result['status'] != 0) {
             return Response::json(['status' => 'fail', 'data' => '', 'message' => 'miss [apple_receipt]']);
        }
        if ($result['status'] == 0) {
            // 商户订单号
            $data = [];
            $data['out_trade_no'] = $order_id;
            $order = Order::query()->with(['user'])->where('order_sn', $data['out_trade_no'])->first();
            if($order && $order->status != 2){
                 $this->tradePaid($data);
                 $user = User::query()->where('id', $order->user_id)->first(); // 重新取出user信息
                return Response::json(['status' => 'success', 'message' =>'成功','data'=>['expire_time'=>$user->expire_time,'order_id'=>$order_id]]);
            }
            return Response::json(['status' => 'fail', 'data' => '', 'message' => '已支付完成']);
        }
    }
    
    protected function httpRequest($url, $postData = array(), $json = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        $info = curl_getinfo($ch);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($json) {
            return json_decode($data, true);
        } else {
            return $data;
        }
    }    


 // 交易支付
    private function tradePaid($msg)
    {
        
        Log::info('【IOS内购】回调交易支付');

        // 处理订单
        DB::beginTransaction();
        try {
           
            // 更新订单
            $order = Order::query()->with(['user'])->where('order_sn', $msg['out_trade_no'])->first();
            $order->status = 2;
            $order->save();

            $goods = Goods::query()->where('id', $order->goods_id)->first();

            // 商品为流量或者套餐
            if ($goods->type <= 2) {
                // 如果买的是套餐，则先将之前购买的所有套餐置都无效，并扣掉之前所有套餐的流量，重置用户已用流量为0
                if ($goods->type == 2) {
                    $existOrderList = Order::query()
                        ->with(['goods'])
                        ->whereHas('goods', function ($q) {
                            $q->where('type', 2);
                        })
                        ->where('user_id', $order->user_id)
                        ->where('oid', '<>', $order->oid)
                        ->where('is_expire', 0)
                        ->where('status', 2)
                        ->get();

                    foreach ($existOrderList as $vo) {
                        Order::query()->where('oid', $vo->oid)->update(['is_expire' => 1]);

                        // 先判断，防止手动扣减过流量的用户流量被扣成负数
                        if ($order->user->transfer_enable - $vo->goods->traffic * 1048576 <= 0) {
                            // 写入用户流量变动记录
                            Helpers::addUserTrafficModifyLog($order->user_id, $order->oid, 0, 0, '[在线支付]用户购买套餐，先扣减之前套餐的流量(扣完)');

                            User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0, 'transfer_enable' => 0]);
                        } else {
                            // 写入用户流量变动记录
                            $user = User::query()->where('id', $order->user_id)->first(); // 重新取出user信息
                            Helpers::addUserTrafficModifyLog($order->user_id, $order->oid, $user->transfer_enable, ($user->transfer_enable - $vo->goods->traffic * 1048576), '[在线支付]用户购买套餐，先扣减之前套餐的流量(未扣完)');

                            User::query()->where('id', $order->user_id)->update(['u' => 0, 'd' => 0]);
                            User::query()->where('id', $order->user_id)->decrement('transfer_enable', $vo->goods->traffic * 1048576);
                        }
                    }
                }

                // 写入用户流量变动记录
                $user = User::query()->where('id', $order->user_id)->first(); // 重新取出user信息
                Helpers::addUserTrafficModifyLog($order->user_id, $order->oid, $user->transfer_enable, ($user->transfer_enable + $goods->traffic * 1048576), '[在线支付]用户购买商品，加上流量');

                // 把商品的流量加到账号上
                User::query()->where('id', $order->user_id)->increment('transfer_enable', $goods->traffic * 1048576);
                
                 // 计算账号过期时间
                if( $order->user->expire_time < date('Y-m-d')){
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days"));
                } else {
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days",strtotime($order->user->expire_time)  ));
                }
                /*
                if ($order->user->expire_time < date('Y-m-d', strtotime("+" . $goods->days . " days"))) {
                    $expireTime = date('Y-m-d', strtotime("+" . $goods->days . " days"));
                } else {
                    $expireTime = $order->user->expire_time;
                }
                */

                // 套餐就改流量重置日，流量包不改
                if ($goods->type == 2) {
                    if (date('m') == 2 && date('d') == 29) {
                        $traffic_reset_day = 28;
                    } else {
                        $traffic_reset_day = date('d') == 31 ? 30 : abs(date('d'));
                    }
                    User::query()->where('id', $order->user_id)->update(['traffic_reset_day' => $traffic_reset_day, 'expire_time' => $expireTime, 'enable' => 1]);
                } else {
                    User::query()->where('id', $order->user_id)->update(['expire_time' => $expireTime, 'enable' => 1]);
                }

                // 写入用户标签
                if ($goods->label) {
                    // 用户默认标签
                    $defaultLabels = [];
                    if (self::$systemConfig['initial_labels_for_user']) {
                        $defaultLabels = explode(',', self::$systemConfig['initial_labels_for_user']);
                    }

                    // 取出现有的标签
                    $userLabels = UserLabel::query()->where('user_id', $order->user_id)->pluck('label_id')->toArray();
                    $goodsLabels = GoodsLabel::query()->where('goods_id', $order->goods_id)->pluck('label_id')->toArray();

                    // 标签去重
                    $newUserLabels = array_values(array_unique(array_merge($userLabels, $goodsLabels, $defaultLabels)));

                    // 删除用户所有标签
                    UserLabel::query()->where('user_id', $order->user_id)->delete();

                    // 生成标签
                    foreach ($newUserLabels as $vo) {
                        $obj = new UserLabel();
                        $obj->user_id = $order->user_id;
                        $obj->label_id = $vo;
                        $obj->save();
                    }
                }

                // 写入返利日志
                if ($order->user->referral_uid) {
                    $this->addReferralLog($order->user_id, $order->user->referral_uid, $order->oid, $order->amount, $order->amount * self::$systemConfig['referral_percent']);
                }

                // 取消重复返利
                User::query()->where('id', $order->user_id)->update(['referral_uid' => 0]);
            } elseif ($goods->type == 3) { // 商品为在线充值
                User::query()->where('id', $order->user_id)->increment('balance', $goods->price * 100);

                // 余额变动记录日志
                $this->addUserBalanceLog($order->user_id, $order->oid, $order->user->balance, $order->user->balance + $goods->price, $goods->price, '用户在线充值');
            }

            // 自动提号机：如果order的email值不为空
            if ($order->email) {
                $title = '自动发送账号信息';
                $content = [
                    'order_sn'      => $order->order_sn,
                    'goods_name'    => $order->goods->name,
                    'goods_traffic' => flowAutoShow($order->goods->traffic * 1048576),
                    'port'          => $order->user->port,
                    'passwd'        => $order->user->passwd,
                    'method'        => $order->user->method,
                    //'protocol'       => $order->user->protocol,
                    //'protocol_param' => $order->user->protocol_param,
                    //'obfs'           => $order->user->obfs,
                    //'obfs_param'     => $order->user->obfs_param,
                    'created_at'    => $order->created_at->toDateTimeString(),
                    'expire_at'     => $order->expire_at
                ];

                // 获取可用节点列表
                $labels = UserLabel::query()->where('user_id', $order->user_id)->get()->pluck('label_id');
                $nodeIds = SsNodeLabel::query()->whereIn('label_id', $labels)->get()->pluck('node_id');
                $nodeList = SsNode::query()->whereIn('id', $nodeIds)->orderBy('sort', 'desc')->orderBy('id', 'desc')->get()->toArray();
                $content['serverList'] = $nodeList;

                $logId = Helpers::addEmailLog($order->email, $title, json_encode($content));
                Mail::to($order->email)->send(new sendUserInfo($logId, $content));
            }

            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::info('【IOS内购】回调更新支付单和订单异常：' . $e->getMessage());
        }
        
    }
    
}