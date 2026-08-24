<?php 
	header("Pragma: No-Cache");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=euc-kr">
<link href="./css/style.css" type="text/css" rel="stylesheet"
	media="screen" />
<title>*** 신용카드 결제 요청 ***</title>
<script language="javascript">
	 function confirmEvent() {

		window.name = "myOpener";
		var win;
		var iLeft = (window.screen.width / 2) - (Number(815) / 2);
			var iTop = (window.screen.height / 2) - (Number(600) / 2);
		var features = "menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=no,location=no";
		features += ",left=" + iLeft + ",top=" + iTop + ",width=" + 815 + ",height=" + 600;
		win = window.open("", "pcPop", features);
		win.focus();
		
		document.form.action = "./Ready.php";
        document.form.method = "POST";
		document.form.target = "pcPop";
		document.form.submit();

    }
	
	function init_orderid(){
		var today = new Date();
		var year  = today.getFullYear();
		var month = today.getMonth() + 1;
		var date  = today.getDate();
		var time  = today.getTime();
	
		if(parseInt(month) < 10) {
			month = "0" + month;
		}
		
		if(parseInt(date) < 10) {
			date = "0" + date;
		}
		
		var order_idxx = "R_" + year + "" + month + "" + date + "" + time;
	
		document.form.ORDERID.value = order_idxx;
	}
</script>
</head>
<body onload="javascript:init_orderid();">
	<form name="form">
		<div class="paymentPop">
			<div class="titArea">
				<span class="txtArea"><emclass="point">[결제요청]</em> 이 페이지는 신용카드결제를 요청하는 페이지입니다.</span>
			</div>
			<div class="contenBox">
				<div class="grayBox">
					<div class="grayBox_top">
						<div class="grayBox_btm">
							이 페이지는 신용카드결제를 요청하는 페이지입니다. <br>

						</div>
					</div>
				</div>
				<div class="payInfo">
					<p class="payTitle">신용카드 주문정보</p>
					<div class="payDev">
						<dl>
							<dt>
* 주문번호
							</dt>
							<dd>
								<input type="text" class="it1" value="" name="ORDERID" />
							</dd>
						</dl>
						<dl>
							<dt>
* 상품명
							<dd>
								<input type="text" class="it1" value="TestItem" name="ITEMNAME" />
							</dd>
						</dl>
						<dl>
							<dt>
* 주문자명
							</dt>
							<dd>
								<input type="text" class="it" value="XXX" name="USERNAME" />
							</dd>
						</dl>
						<dl>
							<dt>
* 결제금액
							</dt>
							<dd>
								<input type="text" class="it3" value="1000" name="AMOUNT" />
							</dd>
						</dl>
						<dl>
							<dt>
* BypassValuse
							</dt>
							<dd>
								<input type="text" class="it1" value="this=is;a=test;bypass=value" name="BYPASSVALUE" />
							</dd>
						</dl>
						<dl>
							<dt>
* 결과 URL(실패포함)
							</dt>
							<dd>
								<input type="text" class="it1" value="https://test-pgweb.replanit.co.kr/php/Success.asp" name="RETURNURL" />
							</dd>
						</dl>

					</div>
				</div>
				<div class="btnSet">
					<a href="#"><img src="./img/btn_payment.gif" width="112"
						height="27" alt="결제요청" onclick="javascript:confirmEvent();" /></a> 
				</div>
			</div>
		</div>
	</form>
</body>
</html>