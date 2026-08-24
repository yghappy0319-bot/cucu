<?php
	header("Pragma: No-Cache");

	//***** �ſ�ī�� ���� ��� *****

	/*[ �ʼ� ������ ]***************************************/
	$CPID = "";
	$CRYPTOKEY = "";		//mKey
    $IVKEY = "";	//����
	$CCURL = "https://pgweb.replanit.co.kr/isppay/isp_cancel_action.asp"

	/**************************************************
	 * ���� ����
	**************************************************/
	$TID = ""; //���� �Ϸ� TID
	$AMOUNT = "1000" ;

	/**************************************************
	 * ��� ����
	**************************************************/
	$CANCELTYPE = "C" //C:��ü���, P:�κ����


	/**************************************************
	 * ��� ó��
	**************************************************/
	$textstr = $CPID . $TID . $AMOUNT . $CANCELTYPE

	$DN_CONNECT_TIMEOUT = 5000;
	$DN_TIMEOUT = 30000; //max-time setting.

	$ERC_NETWORK_ERROR = "-1";
	$ERM_NETWORK = "Network Error";

	$iv = convertHexToBin($IVKEY);
	$key = convertHexToBin($CRYPTOKEY);
	$EncText = openssl_encrypt($textstr, "aes-256-cbc", $key, true, $iv);
	$EncText = base64_encode($EncText);

	$EncText = urlencode( $EncText );
	$REQ_STR = "CPID=".$CPID."&TID=".$TID."&AMOUNT=".$AMOUNT."&CANCELTYPE=".$CANCELTYPE."&DATA=".$EncText;

	$ch = curl_init();
	curl_setopt( $ch,CURLOPT_POST,1 );
	curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,0 );
	curl_setopt( $ch,CURLOPT_CONNECTTIMEOUT,$DN_CONNECT_TIMEOUT );
	curl_setopt( $ch,CURLOPT_TIMEOUT,$DN_TIMEOUT );
	curl_setopt( $ch,CURLOPT_URL,$CCURL );
	curl_setopt( $ch,CURLOPT_HTTPHEADER, array("Content-type:application/x-www-form-urlencoded; charset=euc-kr"));
	curl_setopt( $ch,CURLOPT_POSTFIELDS,$REQ_STR );
	curl_setopt( $ch,CURLOPT_RETURNTRANSFER,1 );
	curl_setopt( $ch,CURLINFO_HEADER_OUT,1 );
	//curl_setopt( $ch,CURLOPT_SSLVERSION, 'all' ); //ssl ���� ������ �߻��� ��� �ּ��� �����ϰ� 6( TLSv1.2) �Ǵ� 1(TLSv1)�� ����

	$RES_STR = curl_exec($ch);

	if( ($CURL_VAL=curl_errno($ch)) != 0 )
	{
		$RES_STR = "RETURNCODE=".$ERC_NETWORK_ERROR."&RETURNMSG=".$ERM_NETWORK."(" . $CURL_VAL . ":" . curl_error($ch) . ")";
	}

	if( $Debug )
	{
		$CURL_MSG = "";
		if( function_exists("curl_strerror") ){
			$CURL_MSG = curl_strerror($CURL_VAL);
		}
		else if( function_exists("curl_error") ){
			$CURL_MSG = curl_error($ch);
		}

		echo "REQ[" . $REQ_STR . "]<BR>";
		echo "RET[" . $CURL_VAL . ":" . $CURL_MSG . "]<BR>";
		echo "RES[" . urldecode($RES_STR) . "]<BR>";
		echo "<BR>" . print_r(curl_getinfo($ch));
		exit();
	}

	curl_close($ch);

	$RES_DATA = str2data( $RES_STR );
	if( isset($RES_DATA["DATA"]) ){
		$RES_DATA = str2data( $RES_DATA["DATA"]  );
	}


	if ( $RES_DATA['RETURNCODE'] == "0000" ) {
		// ���� ���� �� �۾� ����
		echo urldecode( data2str( $RES_DATA ) );
	}
	else{
		// ���� ���� �� �۾� ����
		echo urldecode( data2str( $RES_DATA ) );
	}


	/**************************************************
	 * �Լ�
	**************************************************/

	function str2data($str){
		$data = array(); //return variable
		$in = "";

		if((string)$str == "Array"){
			for($i=0; $i<count($str);$i++){
				$in .= $str[$i];
			}
		}else{
			$in = $str;
		}

		$pairs = explode("&", $in);

		foreach($pairs as $line){
			$parsed = explode("=", $line, 2);

			if(count($parsed) == 2){
				$data[$parsed[0]] = urldecode( $parsed[1] );
			}
		}

		return $data;
	}

	function data2str($data){

		$pairs = array();
		foreach($data as $key => $value){
			array_push($pairs, $key . '=' . urlencode($value));
		}

		return implode('&', $pairs);
	}

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
