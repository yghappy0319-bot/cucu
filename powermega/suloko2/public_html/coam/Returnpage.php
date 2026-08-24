<?php
	header("Pragma: No-Cache");

	function convertHexToBin( $str ) {
		if( function_exists( 'hex2bin' ) ){
			return hex2bin( $str );
		}

		$sbin = "";
		$len = strlen( $str );
		for ( $i = 0; $i < $len; $i += 2 ) {
			$sbin .= pack( "H*", substr( $str, $i, 2 ) );
		}

		return $sbin;
	}

	/******************************************************
	 * CPID		: 리플랜잇에서 제공해 드린 CPID
	 * CRYPTOKEY	: 리플랜잇에서 제공해 드린 암복호화 PW
	******************************************************/
	$CPID = "";
	$CRYPTOKEY = "";		//mKey
    $IVKEY = "";	//고정
	/**************************************************/

	/*$RET_STR = $_POST['DATA'];
	$RET_STR = urldecode($RET_STR);*/
	$RET_STR	=	"";

	//mcyrpt 라이브러리 설치 여부 확인
	if( function_exists("openssl_encrypt") ){
		$iv = convertHexToBin($IVKEY);
		$key = convertHexToBin($CRYPTOKEY);
		$RET_STR = base64_decode($RET_STR);
		//$RET_STR = openssl_decrypt($RET_STR, "aes-256-cbc", $key, true, $iv);

		$RET_STR = openssl_decrypt($RET_STR, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
	}
	else{
		$RET_STR = "**mcrypt library fail**";
	}

	//Log Example
	$Out = "";
	$Out .= "] DATA [";
	$Out .= $_POST['DATA'];
	$Out .= "] DECRYPT DATA [";
	$Out .= $RET_STR;
	$Out .= "]";

	echo($Out);

	/***************************************************
	 * Noti 성공 시 결제 완료에 대한 작업
	* - Noti의 결과에 따라 DB작업등의 코딩을 삽입하여 주십시오.
	* - ORDERID, AMOUNT 등 결제 거래내용에 대한 검증을 반드시 하시기 바랍니다.
	****************************************************/


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
<meta http-equiv="Content-Type" content="text/html; charset=euc-kr" />
<link href="./css/style.css" type="text/css" rel="stylesheet"  media="all" />
<title>*** 신용카드 결제 성공 UI ***</title>
</head>
<body>
<form name="form" >
	<!-- popSize 530x430  -->
	<div class="popWrap">
		<h1 class="logo"></h1>
		<div class="tit_area">
			<p class="tit"><img src="./img/tit05.gif" alt="결제완료 Complete" /></p>
		</div>
		<div class="box">
			<div class="boxTop">
				<div class="boxBtm" style="height:136px;">
					<p class="txt_info"><img src="./img/txt_com.gif" width="202" height="17" alt="결제가 완료되었습니다." /></p>
				</div>
			</div>
		</div>
		<p class="btn">
			<a href="#"><img src="./img/btn_confirm.gif" width="91" height="28" alt="확인" /></a>
		</p>
		<div class="popFoot">
			<div class="foot_top">
				<div class="foot_btm">
					<div class="noti_area">
						 신용카드결제를 이용해주셔서 감사합니다.
					</div>
				</div>
			</div>
		</div>
	</div>
</form>
</body>
</html>
