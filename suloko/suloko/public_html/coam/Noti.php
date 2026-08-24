<?php
	error_reporting(0);

	header("Pragma: No-Cache");
	/******************************************************
	 * CPID		: ���÷��տ��� ������ �帰 CPID
	 * CRYPTOKEY	: ���÷��տ��� ������ �帰 �Ϻ�ȣȭ PW
	******************************************************/
	$CPID = "";
	$CRYPTOKEY = "";		//mKey
    $IVKEY = "";	//����
	/**************************************************/

	$RET_STR = $_POST['DATA'];
	$RET_STR = urldecode($RET_STR);

	//mcyrpt ���̺귯�� ��ġ ���� Ȯ��
	if( function_exists("mcrypt_encrypt") ){
		$iv = convertHexToBin($IVKEY);
		$key = convertHexToBin($CRYPTOKEY);
		$RET_STR = base64_decode($RET_STR);
		$RET_STR = openssl_decrypt($RET_STR, "aes-256-cbc", $key, true, $iv);
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


	echo("OK");

	/***************************************************
	* Noti ���� �� ���� �Ϸῡ ���� �۾�
	* - Noti�� ����� ���� DB�۾����� �ڵ��� �����Ͽ� �ֽʽÿ�.
	* - ORDERID, AMOUNT �� ���� �ŷ����뿡 ���� ������ �ݵ�� �Ͻñ� �ٶ��ϴ�.
	****************************************************/

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
