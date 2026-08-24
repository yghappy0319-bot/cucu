<?php
header("Content-Type:text/html; charset=utf-8;");
/*
****************************************************************************************
* <취소요청 파라미터>
* 취소시 전달하는 파라미터입니다.
* 샘플페이지에서는 기본(필수) 파라미터만 예시되어 있으며,
* 추가 가능한 옵션 파라미터는 연동메뉴얼을 참고하세요.
****************************************************************************************
*/
$tid        = $info['TID'];        // 거래 ID
$ordNo      = '';        // 주문 번호
$canAmt     = ($VAL['IS_USE_N'] == "P") ? $VAL['cancel_price'] : $info['PRICE'];      // 취소 금액
$partCanFlg = ($VAL['IS_USE_N'] == "P") ? 1 : 0;    // 부분취소여부 0:전체취소, 1:부분취소
$notiUrl    = '';      // NOTI URL
$canMsg     = '고객요청';            // 취소 사유

$mid        = 'suloko002m'; //발급받은 상점아이디
$merchantKey = 'OdR/KRgx9ZqjX2Xyx7X7qlud+Z1pP92AJahmvpTF6oqQUsy6ncHwDiUEhD5iUOl59VoxdElKuGBGON3QYddD8A=='; // 상점키


/*
*******************************************************
* <해쉬암호화> (수정하지 마세요)
* SHA-256 해쉬암호화는 거래 위변조를 막기위한 방법입니다.
*******************************************************
*/
$ediDate = date("YmdHis");
$encData = bin2hex(hash('sha256', $mid . $ediDate . $canAmt . $merchantKey, true));

/*
****************************************************************************************
* <취소 요청>
* 취소 사유(CancelMsg) 와 같이 한글 텍스트가 필요한 파라미터는 euc-kr encoding 처리가 필요합니다.
****************************************************************************************
*/

try{
  $data = Array(
    'tid' => $tid,
    'mid' => $mid,
    'ordNo' => $ordNo,
    'canAmt' => $canAmt,
    'canMsg' => iconv("UTF-8", "UTF-8", $canMsg),
    'partCanFlg' => $partCanFlg,
    'notiUrl' => $notiUrl,
    'ediDate' => $ediDate,
    'charSet' => 'utf-8',
    'encData' => $encData
  );
  $response = reqPost($data, "https://api.wayup.co.kr/payment.cancel");

  $res = json_decode($response);

  if ($res->resultCd == "0000") {
    // 결제 성공 시 작업 진행
    syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] RES_DATA ".print_r($response,true));

    $RETURNMSG = $res->resultMsg;

  } else {
    // 결제 실패 시 작업 진행
    // Debug용 로그 저장
    syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] REQ_DATA ".print_r($data,true));
    syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] RES_DATA ".print_r($response,true));

    // 실패 메세지 출력
    alert_print("[취소실패] ".$res->resultMsg);
    meta_go("/order/payment_list.html?{$VAL['PARAM']}");
    exit;
  }

} catch(Exception $e) {
  // 실패처리
  $e->getMessage();
  $ResultCode = "9999";
  $ResultMsg = "통신실패";

  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] REQ_DATA ".print_r($data,true));
  syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] RES_DATA ".print_r($e,true));

  // 실패 메세지 출력
  alert_print("[취소실패] ".$ResultMsg);
  meta_go("/order/payment_list.html?{$VAL['PARAM']}");
  exit;
}

?>
