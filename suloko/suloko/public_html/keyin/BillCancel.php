<?php
	header("Pragma: No-Cache");
	include("./inc/function.php");

	//***** ī�� OTBILL������� ��� *****

	//RETURNCODE=0000&RETURNMSG=����&ORDERID=77002&ITEMNAME=��ǰ��_77002&AMOUNT=100&USERNAME=�̺�â&TID=202205201429265371713400&TRANDATE=220520142928&TRANTIME=142928&CARDCODE=0800&CARDNAME=����&CARDNO=941116******7651"A=00&CARDAUTHNO=41669124

//RETURNCODE=0000&RETURNMSG=����&ORDERID=67478&ITEMNAME=��ǰ��_67478&AMOUNT=100&USERNAME=�̺�â&TID=202205201453272663443400&TRANDATE=220520145328&TRANTIME=145328&CARDCODE=0800&CARDNAME=����&CARDNO=941116******7651"A=00&CARDAUTHNO=41948035

//RETURNCODE=0000&RETURNMSG=����&ORDERID=26295&ITEMNAME=��ǰ��_26295&AMOUNT=100&USERNAME=�̺�â&TID=202205201920574228654400&TRANDATE=220520192058&TRANTIME=192058&CARDCODE=0800&CARDNAME=����&CARDNO=941116******7651"A=00&CARDAUTHNO=45185905

	$tid		=	"";
	$money		=	"100";
	$cpid		=	"";

	/*[ �ʼ� ������ ]***************************************/
	$REQ_DATA = array();

	/**************************************************
	 * CP ����
	**************************************************/
//	$REQ_DATA["CPID"] = $cpid;

	/**************************************************
	 * ���� ����
	**************************************************/
	$REQ_DATA["TID"] = $tid; //���� �Ϸ� TID

	/**************************************************
	 * �⺻ ����
	**************************************************/
	$REQ_DATA["AMOUNT"] = $money;

	/**************************************************
	 * ��� ����
	**************************************************/
	$REQ_DATA["CANCELDESC"] = "Item not delivered";

	/**************************************************
	 * �⺻ ����
	 **************************************************/
	$REQ_DATA["TXTYPE"] = "CANCEL";
	$REQ_DATA["SERVICETYPE"] = "KEYIN";
	print "<pre>";
	print_r($REQ_DATA);
	print "</pre>";
	echo "<br><br><br>";

	$RES_DATA = CallCredit($REQ_DATA, false);

	if ( $RES_DATA['RETURNCODE'] == "0000" ) {
		// ��� ���� �� �۾� ����
		echo urldecode( data2str( $RES_DATA ) );
	}
	else{
		// ��� ���� �� �۾� ����
		echo urldecode( data2str( $RES_DATA ) );
	}

?>
