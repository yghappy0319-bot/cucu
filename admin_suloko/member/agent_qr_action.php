<?php
$s_type = "PAYMENTQRCODE";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

//=>	테이블명을 설정합니다.
$VAL				=	$_GET;
$S_table_name		=	$s_type;

$VAL['mode']		=	"insert";
$VAL['PATNER_NO']	=	$VAL['patner_no'];

for($i=0;$i<100;$i++){
	$VAL['QRCODE']	=	date("ymdHis").str_rand(4);

	$qrMsg	=	"https://m.suloko.com/qr_check.php?QRCODE=".$VAL['QRCODE'];

	include $_SERVER['DOCUMENT_ROOT']."/phpqrcode/payment_qrcode_add.php";

	$VAL['CODEIMG']		=	$qr_image_url;



	F_PAYMENTQRCODE($VAL);

}




alert_print("결제 QR등록하였습니다.");
meta_go("agent_qr.html");
