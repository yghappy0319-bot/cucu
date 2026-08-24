<?php
  $session_start_samesite = "Y";

  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

  //***** 카드 OTBILL수기결제 요청 *****/

  // 결제 이벤트 대상 확인
  $cash_op = select_event_cash_op($M_login['user_id']);

  if (!isset($cash_op[$_POST['cno']])) {
    alert_print("상품을 선택해 주세요.");
    meta_go("./cash-charge.html");
    exit;
  }

  $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' LIMIT 1");

  $product = $cash_op[$_POST['cno']];

  if ($mem['USER_ID'] == '') {
    alert_print("존재하지 않은 회원입니다.");
    meta_go("./cash-charge.html");
    exit;
  }

  // pg 선택 (확율 방식)
  // 삼성, 현대의 경우는 코엠이 선택될 경우 실패코드에 따라 웨이업으로 다시 결제 시도
  // $pg = selectPG($pg_rate);

  // [임시] 60,000 ~ 300,000 상품 결제 시 코엠으로 결제 되도록 적용
  $pg = "wayup";

  $pgAmount = trim($_POST["AMOUNT"]);

  //if ($pgAmount >= 66000 && $pgAmount <= 330000) {

  //}

  $pg = "wayup";
  if ($pg == "coam") {
    //COAM keyin 결제
    require_once $_SERVER['DOCUMENT_ROOT']."/contents/mypage/cash_coam_result.php";
  } else if ($pg == "wayup") {
    //WAYUP keyin 결제
    require_once $_SERVER['DOCUMENT_ROOT']."/contents/mypage/cash_wayup_result.php";
  } else if ($pg == "wizzpay") {
    //WIZZPAY keyin 결제
    require_once $_SERVER['DOCUMENT_ROOT']."/contents/mypage/cash_wizzpay_result.php";
  } else {
    alert_print("PG사 오류");
    meta_go("./cash-charge.html");
    exit;
  }

  $patner['USER_ID'] = "";

  if ($mem['PATNER_NO'] != '') {
    $patner = $db->get_data("SELECT * FROM PATNER WHERE PATNER_NO='".$mem['PATNER_NO']."'");

    if (!isset($patner['USER_ID'])) {
      $patner['USER_ID'] = "";
    }
  }

  # 파트너 첫결제
  if ($mem['PARTNER_ID']) {
    $RESULT_PARTNER_ID = exec_partner_firstsale($mem['USER_ID'] , $mem['PARTNER_ID'] , $mem['REG_DATE']);
  }

  # 파트너 summary 처리
  if ($RESULT_PARTNER_ID) {
    exec_partner_summary($mem['PARTNER_ID'] , "PAY");
  }

  $VAL = array();
  $VAL['mode'] = "insert";
  $VAL['MEMBER_NO'] = $mem['MEMBER_NO'];
  $VAL['USER_ID'] = $mem['USER_ID'];
  $VAL['IS_USE'] = "Y";
  $VAL['CASH'] = $product['cash'];
  //$VAL['IPOINT'] = $cash_op[$vals['cno']]['ipoint'];
  $VAL['IPOINT'] = 0;
  $VAL['PRICE'] = $product['price'];
  $VAL['AUTHNUM'] = $CARDAUTHNO;//카드승인번호
  $VAL['CARDCD'] = $CARDCODE;//카드사코드
  $VAL['ITEMNAME'] = $ITEMNAME;//상품명
  $VAL['CARDNAME'] = $CARDNAME;//카드사 명
  $VAL['CARDNO'] = $CARDNO;//카드번호
  $VAL['TID'] = $TID;//코엠 거래 키
  $VAL['PATNER_ID'] = $patner['USER_ID'];
  $VAL['SELLER_GUBUN'] = 'PC';
  $VAL['QUOTA'] = $QUOTA;//할부개월수
  $VAL['IN_IP'] = $ip_address;
  $VAL['PARTNER_ID'] = $RESULT_PARTNER_ID;
  $VAL['PG_ID'] = $pg;
  $VAL['CASH_CNT'] = $db->get_data_one("SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = {$mem['MEMBER_NO']} AND IS_USE='Y'") + 1; //회원의 결제수

  $CASH_NO = F_CASH($VAL);

  if (!is_numeric($CASH_NO)) {
    syslog(LOG_DEBUG, "[CARD PAYMENT FAILED] ".print_r($VAL,true));
    exit;
  }

  $cash_log = array();
  $cash_log['mode'] = "insert";
  $cash_log['CASH_NO'] = $CASH_NO;
  $cash_log['ORDERS_NO'] = 0;
  $cash_log['WINNING_NO'] = 0;
  $cash_log['INVOCE_NO'] = 0;
  $cash_log['CASH_LOG_NO'] = 0;
  $cash_log['PRICE'] = $product['price'];
  $cash_log['CASH'] = $product['cash'];
  $cash_log['N_CASH'] = $mem['CASH'] + $product['cash'];
  $cash_log['O_CASH'] = $mem['CASH'];
  $cash_log['USER_ID'] = $mem['USER_ID'];
  $cash_log['MEMO'] = "캐시구매충전";
  $cash_log['STATUS'] = "P";
  $cash_log['IN_IP'] = $ip_address;
  $cash_log['PARTNER_ID'] = $RESULT_PARTNER_ID;
  F_T_CASH_LOG($cash_log);

  $db->query("UPDATE MEMBER SET CASH=CASH+".$VAL['CASH']." WHERE USER_ID='".$mem['USER_ID']."'");

  // 보너스 포인트 처리
  if ($product['point'] > 0) {
    $point_log = array();
    $point_log["mode"] = "insert";
    $point_log["COUPON_NO"] = 0;
    $point_log["ORDERS_NO"] = 0;
    $point_log["CASH_LOG_NO"] = $CASH_NO;
    $point_log["POINT"] = $product['point'];
    $point_log["N_POINT"] = $mem["POINT"] + $product['point'];
    $point_log["O_POINT"] = $mem["POINT"];
    $point_log["USER_ID"] = $mem['USER_ID'];
    $point_log["MEMO"] = "캐시충전보너스";
    $point_log["STATUS"] = "P";
    F_T_POINT_LOG($point_log);

    $db->query("UPDATE MEMBER SET POINT=POINT+".$product['point']." WHERE USER_ID='".$mem['USER_ID']."'");
  }


  $TEMPLET_NO = 9;
  $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
  $from = $fromHP;
  $to = $mem["HP"];
  $CODESK = "S";
  $MEMBER_NO = $mem['MEMBER_NO'];
  $MEMBER_NAME = $mem['NAME'];
  $SUBJECT = $SMSTEMPLET['SUBJECT'];
  $SENDMSG = $SMSTEMPLET['CONTENT'];
  $SENDMSG = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
  $SENDMSG = addslashes($SENDMSG);

  $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");

  if ($mem['EMAIL'] != '') {
    $TEMPLET_NO = 2;

    $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
    $to = $mem['EMAIL'];
    $subject = $EMAILTEMPLET['SUBJECT'];
    $content = $EMAILTEMPLET['CONTENT'];
    $type = "2";

    if ($EMAILTEMPLET['STOPYN'] == 'N') {
      // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
      // AWS SES 클라이언트 생성
      $sesClient = initializeSesClient();
      if ($sesClient) {
        sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
      }
    }
  }
  //alert_print("구매하였습니다.");
  //meta_go("/contents/mypage/cash-charge.html");
?>

<!DOCTYPE html>
<html lang="ko">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id="></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '');
    </script>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>슈로코</title>

    <meta name="description" content="미국로또구매대행 미국복권 복권구매대행 파워볼 메가밀리언 슈로코 superlottokorea 해외복권, 미국로또, 미국로또구매대행">
    <meta name="keywords" content="메가밀리언 Mega Millions, 파워볼 PowerBall, 미국복권 온라인 구매대행, superlottokorea,  슈로코, 해외복권, 미국로또, 미국로또구매대행">

    <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    <script src="/common/plugin/jquery-ui.js"></script>

    <!-- Dable 스크립트 시작 -->
    <script>
      (function(d,a,b,l,e,_) {
        d[b]=d[b]||function(){(d[b].q=d[b].q||[]).push(arguments)};e=a.createElement(l);
        e.async=1;e.charset='utf-8';e.src='//static.dable.io/dist/dablena.min.js';
        _=a.getElementsByTagName(l)[0];_.parentNode.insertBefore(e,_);
      })(window, document, 'dablena', 'script');
      dablena('init', '');
      dablena('track', 'PageView');
    </script>
    <!-- Dable 스크립트 종료 -->
  </head>
  <body>
  </body>

  <script>
    alert("캐시 충전이 완료 되었습니다!");
    location.href = "/contents/mypage/cash-charge.html";
  </script>

  <!-- Dable 스크립트 시작 -->
  <script>
    (function(d,a,b,l,e,_) {
      d[b]=d[b]||function(){(d[b].q=d[b].q||[]).push(arguments)};e=a.createElement(l);
      e.async=1;e.charset='utf-8';e.src='//static.dable.io/dist/dablena.min.js';
      _=a.getElementsByTagName(l)[0];_.parentNode.insertBefore(e,_);
    })(window, document, 'dablena', 'script');
    dablena('init', '');
    dablena('track', 'Purchase', {value: '15000', currency: 'KRW'});
  </script>
  <!-- Dable 스크립트 종료 -->
</html>
