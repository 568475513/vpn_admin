<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
    <META HTTP-EQUIV="Cache-Control" CONTENT="no-cache">
    <META HTTP-EQUIV="Expires" CONTENT="0">
    <META HTTP-EQUIV="Cache" CONTENT="no-cache">
    @if($type == 'wap')
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=0.5, maximum-scale=2.0, user-scalable=yes" />
    @endif

    <title>支付中心</title>
    <style type="text/css">
        body, p {
            margin: 0;
        }
        ul {
            margin: 0;
            padding: 0;
        }
        body {
            font: 12px/1.5 \5FAE\8F6F\96C5\9ED1, tahoma, arial, \5b8b\4f53, sans-serif;
            outline: none;
        }
        ul {
            list-style: none;
        }
        a {
            text-decoration: none;
            outline: 0 none;
            color: #a3a3a3;
        }
        img {
            border: 0;
        }
        i {
            font-style: normal;
        }
        .fix:after {
            visibility: hidden;
            display: block;
            font-size: 0;
            content: ".";
            clear: both;
            height: 0;
        }
        .fix {
            *zoom: 1;
        }
        #qrcode {
            width: 120px;
            height: 120px;
        }
        /* pay */
        .fl {
            float: left;
        }
        .xl_pop_pay {
            width: 750px;
            height: 450px;
            position: fixed;
            padding-top: 20px;
            padding-left: 20px;
            background: #fff;
        }
        .pay_box {
            position: relative;
            padding: 0 0 0 80px;
            min-height: 30px;
        }
        .pay_box_tit {
            position: absolute;
            line-height: 30px;
            left: 0;
            top: 0;
            width: 50px;
            text-align: right;
            padding: 0 10px 0 0;
        }
        .ico_p_cho {
            width: 15px;
            height: 15px;
            position: absolute;
            bottom: 0;
            right: 0;
            background-position: 0 -30px;
            display: none;
        }
        .cho .ico_p_cho {
            display: block;
        }
        .pay_box .type_des {
            background: #e9f3f8;
            padding: 0 0 0 10px;
            height: 24px;
            line-height: 24px;
            color: #a3a3a3;
            margin: 6px 0;
            position: relative;
        }
        .fc_light {
            color: #ff4848;
        }
        .vou_box {
            margin: 0 0 4px;
        }
        .pay_time_list {
            padding: 0 0 10px;
        }
        .pay_time_list ul {
            margin: 0 0 0 -10px;
        }
        .pay_time_list li {
            float: left;
            width: 129px;
            height: 95px;
            border: solid 1px #ccc;
            margin: 0 0 0 10px;
            position: relative;
            padding: 0 9px;
            text-align: center;
            cursor: pointer;
        }
        .pay_time_list .time {
            line-height: 32px;
            border-bottom: dashed 1px #d9d9d9;
            text-align: center;
            font-size: 14px;
            color: #000;
        }
        .price_total .num {
            color: #ff4848;
            font-size: 20px;
        }
        .pay_time_list .average {
            line-height: 2;
            color: #000;
        }
        .pay_time_list .cho {
            border-color: #ff4848;
            background: #fff6f6;
        }
        .pay_time_list li:hover {
            border-color: #ff4848;
            z-index: 1;
        }
        .pay_time_list li:hover .average, .pay_time_list li:hover .time {
            color: #ff4848;
        }
        .pay_time_list li:hover .time {
            border-color: #ff4848
        }
        .ico_label {
            background-position: -70px -30px;
            width: 18px;
            height: 22px;
            line-height: 20px;
            text-align: center;
            color: #ff8800;
            display: block;
            position: absolute;
            left: 6px;
            top: -1px;
        }
        .price_total {
            margin: 0 0 10px;
            height: 30px;
        }
        /* weixin */
        .link_refresh {
            width: 150px;
            text-align: center;
            display: inline-block;
        }
        .link_refresh:hover {
            color: #409cf7
        }
        .pay_com {
            position: relative;
        }
        .pay_code {
            text-align: left;
            position: relative;
        }
        .pay_code_com {
            width: 120px;
            height: 120px;
            overflow: hidden;
            position: relative;
        }
        .pay_code_com img {
            width: 100%;
        }
        .pay_code .link_refresh {
            width: 120px;
        }
        .pay_code_tips {
            width: 220px;
            position: absolute;
            top: 20px;
            left: 140px;
        }
        .pay_code_des {
            margin: 5px 0 0;
            font-size: 14px;
        }
        .fc_light {
            color: #ff4848
        }
        .fc_gray {
            color: #a0a0a0
        }
        .mask-code {
            width: 100%;
            height: 100%;
            display: block;
            text-align: center;
            color: #fff;
            background: #333;
            background: rgba(0, 0, 0, .3);
            top: 0;
            left: 0;
            position: absolute;
            font-size: 14px;
            z-index: 2
        }
        .code-fail {
            margin: 30px 0 10px;
        }
        .btn-f-re {
            width: 70px;
            height: 26px;
            line-height: 26px;
            background: #2690f8;
            color: #fff;
            display: inline-block;
        }
        .balance {
           
        }

    </style>
</head>

<body>
@if($type == 'wap')
    <div class="xl_pop_pay" id="xl_pop_pay" style="width: 250px;height: auto;position: static">
@else
            <div class="xl_pop_pay" id="xl_pop_pay">
@endif
    <div class="pay_box" id="open-type-div">
        <span class="pay_box_tit">登录账户</span>
        <div class="pay_type fix">
            <p class="price_total fl"><b class="num" id="login_name">{{$user->username}}</b></p>
            @if($type != 'wap')
                <p class="price_total fl" style="float: right;margin-right: 147px;"><b class="num" id="balance">余额：{{$user->balance}} 元</b></p>
            @endif

        </div>
    </div>
    @if($type == 'wap')
    <div class="pay_box ">
        <span class="pay_box_tit">余额</span>
        <div class="pay_type fix">
            <p class="price_total fl"><b class="num" >{{$user->balance}} 元</b></p>
        </div>
    </div>

        <style>
            .pay_time_list li {
                margin-bottom: 5px;
            }
        </style>
    @else
              <br>
    @endif

    <div class="pay_box ">
        <span class="pay_box_tit">开通会员</span>
        <div class="pay_time_list" id="pay-time-div">
            <ul class="fix">

                @if($list->isEmpty())
                    <div class="col-md-12" style="text-align: center;">
                        <h2>暂无基础套餐</h2>
                    </div>
                @else
                    @foreach($list as $key => $goods)
                        <li class="goods @if($key ==2) cho @endif" data-price="{{$goods->price}}" data-id="{{$goods->id}}">
                            <a href="javascript:;">
                                <p class="time">{{$goods->name}}</p>
                                <p class="time">时长：{{$goods->days}}天</p>
                                <p class="average">{{$goods->desc}}</p>
                                @if($goods->is_hot)
                                    <span class="ico_label">荐</span>
                                @endif
                                <i class="ico_p_cho"></i>
                            </a>
                        </li>
                    @endforeach
                @endif

				@if($lista->isEmpty())
                    <div class="col-md-12" style="text-align: center;">
                        <h2>暂无流量包</h2>
                    </div>
                @else
                    @foreach($lista as $key => $goods)
                        <li class="goods" data-price="{{$goods->price}}" data-id="{{$goods->id}}}">
                            <a href="javascript:;">
                                <p class="time">{{$goods->name}}</p>
                                <p class="time">时长：{{$goods->days}}天</p>
                                <p class="average">{{$goods->desc}}</p>
                                @if($goods->is_hot)
                                    <span class="ico_label">荐</span>
                                @endif
                                <i class="ico_p_cho"></i>
                            </a>
                        </li>
                    @endforeach
                @endif
            </ul>
            @if($type != 'wap')
                        <p class="type_des" id="tip-describe" style="">购买套餐后关闭客户端,在重新登录客户端就可以正常使用了</p>
                            @endif


        </div>
    </div>


    <div class="pay_box" id="totalmoney-div" style="">
        <span class="pay_box_tit">实付金额</span>
        <div class="vou_box fix">
            <p class="price_total fl"><b class="num" id="pay-money">0</b>元人民币
            @if($type != 'wap')
                    <span class="fc_gray" style="display: none;" id="money-tip">(<span class="fc_light">按汇率6.8估算，实际扣款以支付宝实时汇率为准</span>)</span>
            @endif

            </p>
        </div>
    </div>

                @if($type == 'wap')
	                <div class="pay_box hidebox" id="select-pay-box" style="">
	                    <span class="pay_box_tit" id="pay-way-label">支付宝</span>
	                        <div class="pay_code_tips" style="position: static">
	                            <a href="javascript:;" id="payurl_alipay" style="color: blue;font-size: 18px" class="pay_now" data-paytype="alipay"><img src="/assets/zfb.png" width="140" height="40" alt=""/></a>
	                        </div>
	                </div>
	               <!-- <div class="pay_box hidebox" id="select-pay-box" style="">
	                    <span class="pay_box_tit" id="pay-way-label">微信支付</span>
	                        <div class="pay_code_tips" style="position: static">
	                            <a href="javascript:;" id="payurl_wechat" style="color: blue;font-size: 18px" class="pay_now" data-paytype="wechat"><img src="/assets/wx.png" width="140" height="40" alt=""/></a>
	                        </div>
	                </div>-->
	              <div class="pay_box hidebox" id="select-pay-box" style="">
	                    <span class="pay_box_tit" id="pay-way-label">余额支付</span>
	                        <div class="pay_code_tips balance" style="position: static">
	                            <img src="/assets/balance.png" width="140" height="40" alt=""/>
	                        </div>
	                    <p style="margin-bottom: 20px;"></p>
	                </div>
                @else
               
                <div class="pay_box hidebox" id="select-pay-box" style="">
	                    <span class="pay_box_tit" id="pay-way-label">立即支付</span>
	                        <div style="float:left">
	                            <a href="javascript:;" id="payurl_alipay" style="color: blue;font-size: 18px" class="pay_now" data-paytype="alipay"><img src="/assets/zfb.png" width="140" height="40" alt=""/></a>
	                        </div>
	                        <div style="float:left">
	                            <a href="javascript:;" id="payurl_wechat" style="color: blue;font-size: 18px" class="pay_now" data-paytype="wechat"><img src="/assets/wx.png" width="140" height="40" alt=""/></a>
	                        </div>
	                        <div>
	                            <a href="javascript:;" id="payurl_balance" style="color: blue;font-size: 18px" class="balance"><img src="/assets/balance.png" width="140" height="40" alt=""/></a>
	                        </div>
	                         <!--<div>
	                            <a href="javascript:;"  style="color: blue;font-size: 18px" class="balance" data-paytype="balance"><img src="/assets/balance.png" width="140" height="40" alt=""/></a>
	                        </div>-->
	                        
	                </div>
	            <div class="pay_box hidebox" id="show-pay_qrcode" style="display:none">
                    <span class="pay_box_tit" id="pay-way-label">扫码</span>
                    <div class="pay_com pay_code">
                        <div class="pay_code_com">
                            <div id="qrcode"></div>
                        </div>
                    </div>
                </div>

	         <!--       
              <img src="/assets/balance.png" width="140" height="40" alt="" style="left: 500px;top: 300px;position: fixed;" class="balance"/>-->
                @endif
</div>
</body>
<script src="https://cdn.bootcss.com/jquery/1.12.1/jquery.min.js"></script>
<script src="https://cdn.bootcss.com/jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="https://js.stripe.com/v3/"></script>
<script src="https://www.layuicdn.com/layer/layer.js"></script>
<script>


		$('#pay-money').text($('.cho').data('price'));
		
    $('.balance').on('click',function () {

        $('#pay-money').text($('.cho').data('price'));
        var goods_id = $('.cho').data('id');
      $.ajax({
            url: '/api/paybalance',
            data: {goods_id: goods_id,username: '{{$user->username}}'},
            dataType: 'json',
            type: 'POST',
            success: function (res) {
                if(res.status != 'success')
                {
                    alert(res.message);
                    return false;
                } else {
                    alert("支付成功，请重新登录客户端");
                    return false;
                }
            }
        });
    })

    $('.goods').on('click',function () {
        $('.goods').removeClass('cho');
        $(this).addClass('cho');
		$('#pay-money').text($('.cho').data('price'));
    })
    
    $('.pay_now').on('click',function() {
    	var paytype = $(this).data('paytype');
    	@if($type != 'wap')
	    	if( paytype != 'wechat'){
	    		$("#show-pay_qrcode").hide();
	    	}
    	@endif
    	getCode( paytype );
    })

    
    var orderSn = '';
    var checkTimer = '';
    
    function getCode( type ) {
    	layer.load('请求支付中……');
        var goods_id = $('.cho').data('id');
        $.ajax({
            url: '/api/pay',
            data: {goods_id: goods_id,username: '{{$user->username}}',paytype:type},
            dataType: 'json',
            type: 'POST',
            success: function (res) {

                if(res.status != 'success')
                {
                    alert(res.message);
                    return false;
                }

                orderSn = res.data.orderSn;
                paynow(type,res.data.amount);
            }
        });
    }
    
    function paynow(type,amount) {
        var stripe = Stripe('{{$skey}}');
        var elements = stripe.elements();
        stripe.createSource({
            type: type,
            amount: parseInt(amount),
            currency: 'hkd', // usd, eur,
            redirect: {
                return_url: 'https://www.zhijiasu.com/api/pay_result'
            },
        }).then(function (response) {
            if (response.error) {
                alert(response.error.message);
            } else {
                $.ajax({
                    type:"POST",
                    url:"/api/paystripe",
                    dataType:"json",
                    data:{
                        orderSn: orderSn,
                        sn: response.source.id
                    },
                    success:function(res){
                        if(res.status != 'success')
                        {
                            alert(res.message);
                            return false;
                        }
                        processStripeResponse(type,response.source);
                    },
                    error:function(){
                        alert(res.message);
                    }
                });
            }

        });
    }
    
    function processStripeResponse(type,source) {
    	layer.closeAll('loading');
        if( type == 'wechat') {
            @if($type == 'wap')
            	window.location.href = source.wechat.qr_code_url;
            @else
            clearInterval(checkTimer);
            $('#qrcode').html('');
            $('#qrcode').qrcode({
                render: "canvas",
                width: 120,
                height: 120,
                text: source.wechat.qr_code_url
            });
            $("#show-pay_qrcode").show();
            checkOrder();
            @endif
        } else {
            window.location.href = source.redirect.url;
        }

    }
    
    function checkOrder() {
        checkTimer = setInterval(function(){
            $.ajax({
                url: '/api/checkorder',
                data: {sn: orderSn,username: '{{$user->username}}'},
                dataType: 'json',
                type: 'POST',
                success: function (res) {
                    if(res.status == 'success')
                    {
                        clearInterval(checkTimer);
                        alert("支付成功，请重新登录客户端");
                        return false;
                    }
                }
            });

        }, 3000);
    }

</script>