<?php
session_start();
	header("Pragma: No-Cache");

	header("Content-Type: text/html; charset=EUC-KR");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
<meta http-equiv="Content-Type" content="text/html; charset=euc-kr">
<link href="./css/style.css" type="text/css" rel="stylesheet"  media="all" />
<title>*** �ſ�ī�� ���� ***</title>
</head>
<body>
<?php

	/**************************************************
	 * ���� ����
	**************************************************/
	$AMOUNT = $_POST["AMOUNT"];
	$ITEMNAME = $_POST["ITEMNAME"];
	$ORDERID = $_POST["ORDERID"];

	/**************************************************
	 * ���� ����
	**************************************************/
	$USERNAME =iconv("UTF-8","EUC-KR",$_POST["USERNAME"]); // ������ �̸�
	$USERAGENT = "WP"; // WP : PC , WM : Mobile Web , WA : Mobile App(Android) , WI : Mobile App(IOS)


	/**************************************************
	 * URL ����
	**************************************************/
	$RETURNURL = $_POST["RETURNURL"];
	$BYPASSVALUE = $_POST["BYPASSVALUE"]; // BILL���� �Ǵ� Noti���� �������� ��. '&'�� ����� ��� ���� �߸��ԵǹǷ� ����.

	/******************************************************
	 * CPID		: ���÷��տ��� ������ �帰 CPID
	 * CRYPTOKEY	: ���÷��տ��� ������ �帰 �Ϻ�ȣȭ PW
	******************************************************/
//CPID :
//mKey :

	$CPID = "";
	$CRYPTOKEY = "";		//mKey

	/*�׽�Ʈ�����*/
	//$CPID = "ispmpi01";
	//$CRYPTOKEY = "17703153586bad50bd6215ea78da17a1c54db3cff8bc1d3330f898b11b39b4d9";		//mKey
	/*�׽�Ʈ�볡*/
    $IVKEY = "";	//����
	$STARTURL = "https://pgweb.coam.co.kr/isppay/";
	/**************************************************/

	$textstr = $CPID . $AMOUNT . $ORDERID;

	$iv = convertHexToBin($IVKEY);
	$key = convertHexToBin($CRYPTOKEY);
	$EncText = openssl_encrypt($textstr, "aes-256-cbc", $key, true, $iv);
	$EncText = base64_encode($EncText);


?>
<form name="form" ACTION="<?= $STARTURL ?>" METHOD="POST" >
<input TYPE="HIDDEN" NAME="CPID"  	VALUE="<?= $CPID ?>">
<input TYPE="HIDDEN" NAME="AMOUNT"  	VALUE="<?= $AMOUNT ?>">
<input TYPE="HIDDEN" NAME="ORDERID"  	VALUE="<?= $ORDERID ?>">
<input TYPE="HIDDEN" NAME="ITEMNAME"  	VALUE="<?= $ITEMNAME ?>">
<input TYPE="HIDDEN" NAME="USERAGENT"  	VALUE="<?= $USERAGENT ?>">
<input TYPE="HIDDEN" NAME="BYPASSVALUE"  	VALUE="<?= $BYPASSVALUE ?>">
<input TYPE="HIDDEN" NAME="RETURNURL"  	VALUE="<?= $RETURNURL ?>">
<input TYPE="HIDDEN" NAME="DATA"  	VALUE="<?= $EncText ?>">
<input TYPE="HIDDEN" NAME="USERNAME"  	VALUE="<?= $USERNAME ?>">
</form>
<script>
	document.form.submit();
</script>
</form>
</body>
</html>
<?php

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
?>
