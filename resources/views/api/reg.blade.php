<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>手机网站</title>
<meta name="description" content="手机网站">
<meta name="Keywords" content="手机网站">
<meta content="initial-scale=1.0,user-scalable=no,maximum-scale=1,width=device-width" name="viewport" />
<link type="text/css" rel="stylesheet" href="/assets/reg/css/basic.css" />
<script type="text/javascript" src="/assets/reg/js/jquery-1.7.2.min.js"></script>
<!--加载样式-->
<script>
$(window).load(function() {
	$("#status").fadeOut();
	$("#preloader").delay(350).fadeOut("slow");
})
</script>
</head>

<body>
<div class="w">
  <!--页面加载 开始-->
  <div id="preloader">
    <div id="status">
      <p class="center-text">加载中…<em>网速有点不给力哦!</em> </p>
    </div>
  </div>
  <!--页面加载 结束-->
  <!--header 开始-->
  <header>
    <div class="header">

    <a class="new-a-back" href="#"> <span>EasyLink</span> </a>
      <h2>注册</h2>
    <a class="new-a-jd" id="trigger-overlay" href="javascript:void(0)"> <span>导航菜单</span> </a>
    </div>
  </header>
  <!--header 结束-->
  <div class="page">
    <div class="main">
      <form id="frm_login" method="post" action="">

        <div class="item item-username">
          <input id="email" class="txt-input txt-username" type="text" placeholder="请输入邮箱" value="" name="username">
          <b class="input-close" style="display:none;"></b> </div>
        <div class="item item-password">
          <input id="password" class="txt-input txt-password ciphertext" type="password" placeholder="请输入密码" name="password" style="display: inline;">
          <input id="ptext" class="txt-input txt-password plaintext" type="text" placeholder="请输入密码" style="display: none;" name="ptext">
          <b class="tp-btn btn-off"></b> </div>
        <div class="item item-password">
          <input id="password_PwdTwo" class="txt-input txt-password_PwdTwo ciphertext_PwdTwo" type="password" placeholder="确认密码" name="password_PwdTwo" style="display: inline;">
          <input id="ptext_PwdTwo" class="txt-input txt-password_PwdTwo plaintext_PwdTwo" type="text" placeholder="确认密码" style="display: none;" name="ptext_PwdTwo">
          <b class="tp-btn_PwdTwo btn-off_PwdTwo"></b> </div>
        <div class="item item-captcha">
          <div class="input-info">
            <input id="validateCode" class="txt-input txt-captcha" type="text" placeholder="验证码" autocomplete="off" maxlength="6" size="11">
            <b id="validateCodeclose" class="input-close" onclick="validateCodeclose();" style="display: block;"></b> <span id="captcha-img"> <img id="code" src="/assets/reg/images/code.jpg" style="width:63px;height:25px;" onclick="closeAndFlush();"> </span> </div>
          <div class="err-tips"> 注册即视为同意 <a target="_blank" href="#">用户服务协议</a> </div>
        </div>
		<div class="item item-username">
          <input id="invitecode" class="txt-input txt-username" type="text" placeholder="邀请码" value="52896" readonly="true"  name="username">
          <b class="input-close" style="display: none;"></b> </div>
        <div class="ui-btn-wrap"> <a class="ui-btn-lg ui-btn-primary" href="#">用户注册</a> </div>
      </form>
    </div>
    <script type="text/javascript" >
    $(function() {
		$(".input-close").hide();
		displayPwd();
		displayPwd_PwdTwo();
		remember();
		showActionError();
		autoHeight_login();
		dispValidateCode();
		displayClearBtn();
		setTimeout(displayClearBtn, 200 ); //延迟显示,应对浏览器记住密码
	});

	//是否显示清除按钮
	function displayClearBtn(){
		if(document.getElementById("username").value != ''){
			$("#username").siblings(".input-close").show();
		}
		if(document.getElementById("password").value != ''){
			$(".ciphertext").siblings(".input-close").show();
		}
		if(document.getElementById("password_PwdTwo").value != ''){
			$(".ciphertext_PwdTwo").siblings(".input-close").show();
		}
	}

	//清除input内容
    $('.input-close').click(function(e){
		$(e.target).parent().find(":input").val("");
		$(e.target).hide();
		$($(e.target).parent().find(":input")).each(function(i){
			if(this.id=="ptext" || this.id=="password"){
				$("#password").val('');
				$("#ptext").val('');
			}
			if(this.id=="ptext_PwdTwo" || this.id=="password_PwdTwo"){
				$("#password_PwdTwo").val('');
				$("#ptext_PwdTwo").val('');
			}
         });
    });

	//设置password字段的值
	$('.txt-password').bind('input',function(){
		$('#password').val($(this).val());
	});
	$('.txt-password_PwdTwo').bind('input',function(){
		$('#password_PwdTwo').val($(this).val());
	});

	//显隐密码切换
	function displayPwd(){
    	$(".tp-btn").toggle(
          function(){
            $(this).addClass("btn-on");
			var textInput = $(this).siblings(".plaintext");
    		var pwdInput = $(this).siblings(".ciphertext");
			pwdInput.hide();
			textInput.val(pwdInput.val()).show().focusEnd();
          },
          function(){
		  	$(this).removeClass("btn-on");
		  	var textInput = $(this).siblings(".plaintext");
    		var pwdInput = $(this).siblings(".ciphertext");
            textInput.hide();
			pwdInput.val(textInput.val()).show().focusEnd();
          }
    	);
	}
	//显隐密码切换
	function displayPwd_PwdTwo(){
    	$(".tp-btn_PwdTwo").toggle(
          function(){
            $(this).addClass("btn-on_PwdTwo");
			var textInput = $(this).siblings(".plaintext_PwdTwo");
    		var pwdInput = $(this).siblings(".ciphertext_PwdTwo");
			pwdInput.hide();
			textInput.val(pwdInput.val()).show().focusEnd();
          },
          function(){
		  	$(this).removeClass("btn-on_PwdTwo");
		  	var textInput = $(this).siblings(".plaintext_PwdTwo");
    		var pwdInput = $(this).siblings(".ciphertext_PwdTwo");
            textInput.hide();
			pwdInput.val(textInput.val()).show().focusEnd();
          }
    	);
	}

	//监控用户输入
	$(":input").bind('input propertychange', function() {
		if($(this).val()!=""){
			$(this).siblings(".input-close").show();
		}else{
			$(this).siblings(".input-close").hide();
		}
    });
</script>
  </div>
  <!--footer 开始
  <div class="footer">
    <nav>
      <ul class="footer_menu">
        <li><a href="tel:13888888888"><img src="/assets/reg/images/plugmenu1.png">
          <label>联系我们</label>
          </a></li>
        <li><a href="#"><img src="/assets/reg/images/plugmenu4.png">
          <label>服务中心</label>
          </a></li>
        <li class="home"><a href="#"></a></li>
        <li><a href="#"><img src="/assets/reg/images/plugmenu3.png">
          <label>热门活动</label>
          </a></li>
        <li><a href="#"><img src="/assets/reg/images/plugmenu2.png">
          <label>搜索车辆</label>
          </a></li>
      </ul>
    </nav>
  </div>-->
  <!--footer end-->
</div>
</body>
</html>
