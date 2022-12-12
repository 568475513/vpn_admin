<?php

// 后端WEBAPI
Route::group(['namespace' => 'Api\WebApi'], function () {
    // SSR后端WEBAPI V1版
    Route::group(['prefix' => 'web/v1'], function () {
        Route::get('node/{node}', 'SSRController@getNodeInfo'); // 获取节点信息
        Route::post('nodeStatus/{node}', 'BaseController@setNodeStatus'); // 上报节点心跳信息
        Route::post('nodeOnline/{node}', 'BaseController@setNodeOnline'); // 上报节点在线人数
        Route::get('userList/{node}', 'SSRController@getUserList'); // 获取节点可用的用户列表
        Route::post('userTraffic/{node}', 'BaseController@setUserTraffic'); // 上报用户流量日志
        Route::get('nodeRule/{node}', 'BaseController@getNodeRule'); // 获取节点的审计规则
        Route::post('trigger/{node}', 'BaseController@addRuleLog'); // 上报用户触发的审计规则记录
    });

});

Route::group(['namespace' => 'Api'],function () {
    Route::resource('yzy','YzyController');
    Route::resource('alipay','AlipayController');
    Route::resource('f2fpay','F2fpayController');
    Route::resource('Stripepay','StripepayController');
    // 定制客户端
    Route::any('login','LoginController@login');
    Route::any('register','LoginController@register');
    Route::any('resetpass','LoginController@resetpass');
    Route::any('checkhb','LoginController@checkhb');
    Route::any('register_html','LoginController@register_html');

    Route::any('signin','UserController@signin');
    Route::any('brand','UserController@brand');
    Route::any('help','UserController@help');
    Route::any('notice','UserController@notice');

    Route::any('ticket','UserController@ticket');
    Route::any('ticketadd','UserController@ticketadd');
    Route::any('getgoods','UserController@getgoods');
    Route::any('goodslist','UserController@goodslist');
    Route::any('chargecoupon','UserController@chargecoupon');
    // 充值卡

    // 短信验证码
    Route::any('sendsms','SmsController@send');
    Route::any('checksms','SmsController@check');

    Route::any('pay','PayController@create');
    Route::any('pay_result','PayController@result');
    Route::any('pay_wechat','PayController@wechatpay');
    Route::any('paystripe','PayController@paystripe');
    Route::any('checkorder','PayController@check');
	Route::any('paybalance','PayController@balance');

    // PING检测
    Route::get('ping','PingController@ping');

      //　游戏管理
    Route::any('gamelist','GameController@index');
    Route::any('gameinfo','GameController@info');

    // IOS支付
    Route::any('createorder','PayController@createorder');
    Route::any('iospaycallback','PayController@iospaycallback');

    //U支付回调
    Route::any('UPayCallBack','PayController@uPayCallBack');

    Route::any("articleList", "UserController@articleList"); // 公告列表
    Route::any("articleInfo", "UserController@articleInfo"); // 公告列表
    Route::any('goodslist','UserController@goodslist');//套餐列表
    Route::any('devicelog','UserController@addDeviceLog');//用户设备添加记录
    Route::any('devicelist','UserController@deviceList');//设备列表
    Route::any('deviceremove','UserController@deviceRemove');//设备解绑
    Route::any('sendEmail','UserController@sendEmail');//发送邮箱验证信息
    Route::any('createTronOrder','UserController@createTronOrder');//发送邮箱验证信息
    Route::any('companyInformation','UserController@companyInformation');//公司信息
});
