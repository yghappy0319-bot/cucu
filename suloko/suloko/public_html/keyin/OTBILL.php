<?php
	//header("Pragma: No-Cache");
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<?
	include("./inc/function.php");

	//***** 카드 OTBILL수기결제 요청 *****

	/*[ 필수 데이터 ]***************************************/
	$REQ_DATA = array();


	/**************************************************
	 * 결제 정보
	**************************************************/
	//주문자정보
	$ITEMNAME		=	ICONV("UTF-8", "EUC-KR", "상품명");		//	상품명 한글
	$TEST_AMOUNT	=	"100";									//	결제금액
	$ORDERID		=	rand(11111,99999);						//	주문번호
	$USERNAME		=	ICONV("UTF-8", "EUC-KR", "");		//	주문자명
	$USEREMAIL		=	"sun@samtle.com";						//	이메일주소
	//	신용카드 정보
	$QUOTA			= "00";					//할부개월수. 일시불:00, 2개월:02...
	$BILLINFO		= "";	//카드번호 (이병창농협)
	$EXPIREPERIOD	= "";				//유효기간 YYMM
	$CARDPWD		= "";					//비밀번호 앞 2자리
	$CARDAUTH		= "";				//생년월일 YYMMDD




	$REQ_DATA["AMOUNT"] = $TEST_AMOUNT;
	$REQ_DATA["CURRENCY"] = "410";			//	외환표시
	$REQ_DATA["ITEMNAME"] = $ITEMNAME;

	$REQ_DATA["ORDERID"] = $ORDERID;

	/**************************************************
	 * 고객 정보
	**************************************************/
	$REQ_DATA["USERNAME"] = $USERNAME;
//	echo $REQ_DATA["USERNAME"];
//	exit;
	$REQ_DATA["USEREMAIL"] = $USEREMAIL;

	/**************************************************
	 * 카드 정보
	**************************************************/
	$REQ_DATA["QUOTA"]			= $QUOTA;			//할부개월수. 일시불:00, 2개월:02...
	$REQ_DATA["BILLINFO"]		= $BILLINFO;		//카드번호
	$REQ_DATA["EXPIREPERIOD"]	= $EXPIREPERIOD;	//유효기간 YYMM
	$REQ_DATA["CARDPWD"]		= $CARDPWD;			//비밀번호 앞 2자리
	$REQ_DATA["CARDAUTH"]		= $CARDAUTH;		//생년월일 YYMMDD

	/**************************************************
	 * 기본 정보
	 **************************************************/
	$REQ_DATA["TXTYPE"] = "OTBILL";
	$REQ_DATA["SERVICETYPE"] = "KEYIN";

	$RES_DATA = CallCredit($REQ_DATA, false);

	if ( $RES_DATA['RETURNCODE'] == "0000" ) {
		// 결제 성공 시 작업 진행
		echo urldecode( data2str( $RES_DATA ) );
	}
	else{
		// 결제 실패 시 작업 진행
		echo urldecode( data2str( $RES_DATA ) );
	}

?>
