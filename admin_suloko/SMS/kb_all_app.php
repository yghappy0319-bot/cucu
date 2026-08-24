<?
  /* ------------------------------------------------------------------------
    무통장 자동 입금 처리
  ------------------------------------------------------------------------

### KB국민은행
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_telegram.php";
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_sms.php";

#error_reporting( E_ALL );
#ini_set( "display_errors", 1 );
*/

## telegram define
define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

#PROD
$_TELEGRAM_CHAT_ID = array('');


$SMS_BANK = "KB국민은행";

if (isset($_POST["msg"])) {
  $msg = trim($_POST["msg"]);
}

if ($msg) {
  syslog(LOG_DEBUG, "[SMS] START");
  syslog(LOG_DEBUG, "[SMS] ".$msg);

  ## 수신 SMS 정보
  $SMS = get_sms_receive_info_app($msg);
  $SMS_DATE = $SMS["SMS_DATE"];
  $SMS_NAME = $SMS["SMS_NAME"];
  $SMS_NAME_ORG = $SMS["SMS_NAME_ORG"];
  $SMS_PRICE = $SMS["SMS_PRICE"];
  $SMS_BANK_NUMBER = $SMS["SMS_BANK_NUMBER"];


  # 완료 처리 외의 건들 중에 동일 입금문자 여부 체크 (입금문자 당 1개 row)
  $num_chk = get_sms_count($SMS_BANK, $SMS_BANK_NUMBER, $SMS_DATE, $SMS_PRICE, $SMS_NAME, "");

  # 중복 입금문자 수신 알림
  if ($num_chk["CNT"] > 0) {

    syslog(LOG_DEBUG, "[SMS] 중복 입금문자 수신");
    syslog(LOG_DEBUG, "[SMS] END");

    $msg_tele = "<b>[중복 입금문자 수신]</b>\n";
    $msg_tele .= "<b>이미 수신(처리) 된 입금문자 수신</b>\n";
    $msg_tele .= $msg;

    foreach ($_TELEGRAM_CHAT_ID AS $_TELEGRAM_CHAT_ID_STR) {
        $_TELEGRAM_QUERY_STR    = array(
            'chat_id' => $_TELEGRAM_CHAT_ID_STR,
            'text'    => $msg_tele,
            'parse_mode' => "HTML"
        );
        telegramApiRequest("sendMessage", $_TELEGRAM_QUERY_STR);

        syslog(LOG_DEBUG, "[SMS] Telegram push 완료");
    }
    exit();
  }

  ## 자동 입금확인 CASH 로그 무조건 저장
  $fields = "SMS_BANK, SMS_BANK_NUMBER, SMS_DATE, SMS_PRICE, SMS_NAME, SMS_TXT, SMS_NAME_ORG ";
  $values = "'".$SMS_BANK."', '".$SMS_BANK_NUMBER."', '".$SMS_DATE."', '".$SMS_PRICE."', '".$SMS_NAME_ORG."', '".$msg."', '".$SMS_NAME_ORG."' ";
  $query = " INSERT INTO CASH_SMS_LOG (".$fields.") VALUES (".$values.") ";
  $db->query($query);

  ## SMS 내역 idx
  $log_tmp = get_sms_info($SMS_BANK, $SMS_BANK_NUMBER, $SMS_DATE, $SMS_PRICE, $SMS_NAME_ORG ,$msg);
  $log_idx = $log_tmp["idx"];

  ## 주문서 정보 갯수 확인
  $order_tmp = get_cash_count($SMS_NAME, $SMS_PRICE, "Y");
  $orderNum = $order_tmp["CNT"];

  ## 주문 처리
  if ($orderNum == 1) { // 정상 처리

    $STATUS = "Y"; // 모두 동일 (정상처리)

    ## 주문서 정보 및 회원 정보
    $tmp = get_cash_info($SMS_NAME, $SMS_PRICE, "N");
    $USER_ID = $tmp['USER_ID'];
    $PARTNER_ID = $tmp['PARTNER_ID'];
    $REG_DATE = $tmp['REG_DATE'];
    $CASH = $tmp['CASH'];
    $MEMBER_CASH = $tmp['MEMBER_CASH'];
    $HP = $tmp['HP'];
    $NAME = $tmp['NAME'];
    $EMAIL = $tmp['EMAIL'];
    $CASH_NO = $tmp['CASH_NO'];
    $MEMBER_NO = $tmp['MEMBER_NO'];

    ## 캐시 지급
    exec_member_cash($USER_ID, $PARTNER_ID, $REG_DATE, $CASH, $MEMBER_CASH, $CASH_NO, $SMS_PRICE);

    ## 회원 CASH 업데이트
    exec_member_cash_update($CASH, $USER_ID);
    syslog(LOG_DEBUG, "[SMS] 캐시지급 완료");

    ## 입금확인 SMS 문자 발송
    exec_send_sms("19", $HP, $fromHP, $MEMBER_NO, $NAME, $USER_ID);
    syslog(LOG_DEBUG, "[SMS] 충전완료 문자 전송완료");

    ## EMAIL 발송
    exec_send_email("6", $EMAIL, "2", $NAME, $MEMBER_NO);

  } elseif ($orderNum >= 2) {

      $STATUS = "N3"; // 동일 정보 주문서가 2개 이상
      $CASH_NO = "";
      $MEMBER_NO = "";
      $USER_ID="";
      $log_idx = "";

  } else { //입금자명,입금액 기준으로 매칭되는 주문서가 없을 경우

      # 입금자명만 기준으로 입금정보 존재 여부 체크하여 주문 정보 같이 PUSH
      $orderFrm = get_cash_count($SMS_NAME, "", "Y");

      if ($orderFrm['CNT'] > 0) {

        $STATUS = "N1";  // 입금자명 또는 입금액 일부 일치

        #주문서 상의 금액 확인 (메시지에 추가용도)
        $orderFrm = get_cash_info($SMS_NAME, "", "Y");
        $USER_ID = $orderFrm["USER_ID"];
        $ORDER_PRICE = $orderFrm["PRICE"];
        $ORDER_CASH = $orderFrm["CASH"];
        $ORDER_REG_DATE = $orderFrm["REG_DATE"];
        $CASH_NO = $orderFrm["CASH_NO"];
        $MEMBER_CASH = $orderFrm["MEMBER_CASH"];
        $MEMBER_PARTNER_ID = $orderFrm["MEMBER_PARTNER_ID"];
        $HP = $orderFrm['HP'];
        $NAME = $orderFrm['NAME'];
        $EMAIL = $orderFrm['EMAIL'];
        $MEMBER_NO = $orderFrm['MEMBER_NO'];

        #입금액 > 주문액 일 경우 입금액 기준으로 캐시지급 + 주문서 금액 수정 + 관리자메모 추가
        if ($SMS_PRICE > $ORDER_PRICE) {
          syslog(LOG_DEBUG, "[SMS] 입금액 > 주문액");

          # 입금액 기준으로 지급 캐시액 계산 (CASH_CONFIG 금액구간 우선)
          $SMS_PRICE_SUPPLY = calc_cash_supply_by_price($SMS_PRICE);
          $SMS_PRICE_VAT = $SMS_PRICE - $SMS_PRICE_SUPPLY; // 부가세
          $SMS_ITEMNAME = "슈로코 ".number_format($SMS_PRICE);

          # 캐시 지급처리
          exec_member_cash($USER_ID, $MEMBER_PARTNER_ID, $ORDER_REG_DATE, $SMS_PRICE_SUPPLY, $MEMBER_CASH, $CASH_NO, $SMS_PRICE);

          ## 회원 CASH 업데이트
          exec_member_cash_update($SMS_PRICE_SUPPLY, $USER_ID);
          syslog(LOG_DEBUG, "[SMS] 캐시지급 완료");

          ## 입금확인 SMS 문자 발송
          exec_send_sms("19", $HP, $fromHP, $MEMBER_NO, $NAME, $USER_ID);
          syslog(LOG_DEBUG, "[SMS] 충전완료 문자 전송완료");

          ## EMAIL 발송
          exec_send_email("6", $EMAIL, "2", $NAME, $MEMBER_NO);

          #주문서 수정 (지급캐시, 결제액, 구매상품명)
          $fields = " IS_USE='Y', CASH = '".$SMS_PRICE_SUPPLY."', PRICE = '".$SMS_PRICE."', ITEMNAME = '슈로코 ".number_format($SMS_PRICE_SUPPLY)."' ";
          exec_cash_update($CASH_NO, $USER_ID, $fields);

          #관리자 메모 추가
          $SMS_MEMO = "[자동 캐시지급 완료]\n";
          $SMS_MEMO .= "- 입금고유번호 : ".$log_idx."\n";
          $SMS_MEMO .= "- 입금일 : ".$SMS_DATE."\n";
          $SMS_MEMO .= "- 주문서상 금액 : ".number_format($ORDER_PRICE)." 원\n";
          $SMS_MEMO .= "- 실입금액 : ".number_format($SMS_PRICE)." 원\n";
          $SMS_MEMO .= "- 지급 캐시액 : ".number_format($SMS_PRICE_SUPPLY)." 캐시\n";
          $SMS_MEMO .= "\n";
          $SMS_MEMO .= "* 입금액 기준으로 캐시지급 완료되었습니다.\n";
          $SMS_MEMO .= "* 시스템에서 자동으로 작성된 메모입니다.\n";
          exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);

          $STATUS = "Y" ;
          syslog(LOG_DEBUG, "[SMS] 입금액>주문액,  N1->Y, 지급캐시: ".number_format($SMS_PRICE_SUPPLY));

          $isCashed = true;

        } else {
          #
          ## 입금액이 주문액과 다를 경우
          # - 수신 받은 입금자명 기준으로 입금된 누적금액과 신청된 금액과 합이 맞을 경우 캐시 지급처리
          #

          ## 주문서 정보 및 회원 정보
          $tmp = get_cash_info($SMS_NAME, "", "N");

          $USER_ID = $tmp['USER_ID'];
          $PARTNER_ID = $tmp['PARTNER_ID'];
          $REG_DATE = $tmp['REG_DATE'];
          $CASH = $tmp['CASH'];
          $MEMBER_CASH = $tmp['MEMBER_CASH'];
          $HP = $tmp['HP'];
          $NAME = $tmp['NAME'];
          $EMAIL = $tmp['EMAIL'];
          $CASH_NO = $tmp['CASH_NO'];
          $MEMBER_NO = $tmp['MEMBER_NO'];

          # 현재 주문액 다른 건과 현재 입금건과의 합을 구한다.
          $query = " SELECT SUM(SMS_PRICE) AS SMS_PRICE_TOTAL FROM CASH_SMS_LOG ";
          $query .= " WHERE STATUS = 'N1' ";
          $query .= " AND SMS_NAME = '".$SMS_NAME."' ";
          $tmp			=	$db->get_data($query);

          $SMS_PRICE_TOTAL = $tmp["SMS_PRICE_TOTAL"] + $SMS_PRICE; //미처리된 입금건 총금액 + 현재 수신된 입금액

          ## 전체 금액이 동일할 경우 자동 입금(주문서) + 다른 입금건 정상 처리
          if ($SMS_PRICE_TOTAL == $ORDER_PRICE) {

              syslog(LOG_DEBUG, "[SMS] 누적 입금액 동일");

            ## 자동 입금 처리
              # 캐시 지급 // log에는 누적 금액으로 표기
              exec_member_cash($USER_ID, $PARTNER_ID, $REG_DATE, $CASH, $MEMBER_CASH, $CASH_NO, $SMS_PRICE_TOTAL);

              # 회원 CASH 업데이트
              exec_member_cash_update($CASH, $USER_ID);
              syslog(LOG_DEBUG, "[SMS] 캐시지급 완료");

              # 입금확인 SMS 문자 발송
              exec_send_sms("19", $HP, $fromHP, $MEMBER_NO, $NAME, $USER_ID);
              syslog(LOG_DEBUG, "[SMS] 충전완료 문자 전송완료");

              # EMAIL 발송
              exec_send_email("6", $EMAIL, "2", $NAME, $MEMBER_NO);

              #주문서 수정 (상태 -> 완료)
              $fields = " IS_USE='Y' ";
              exec_cash_update($CASH_NO, $USER_ID, $fields);
              syslog(LOG_DEBUG, "[SMS] 주문서 입금확인처리 완료");

              # 입금내역 확인
              $query = " SELECT SMS_PRICE, SMS_DATE, idx FROM CASH_SMS_LOG ";
              $query .= " WHERE STATUS = 'N1' ";
              $query .= " AND SMS_NAME = '".$SMS_NAME."' ";
              $list = $db->get_list($query);

              $SMS_LOG = "- 입금정보 - \n";
              for ($i = 0; $i < count($list["idx"]); $i++) {
                 $SMS_LOG .= $list["SMS_DATE"][$i]." / ".number_format($list["SMS_PRICE"][$i])." 원\n";
              }
              $SMS_LOG .= $SMS_DATE." / ".number_format($SMS_PRICE)." 원\n";

              #관리자 메모 추가
              $SMS_MEMO = "[자동 캐시지급 완료]\n";
              $SMS_MEMO .= $SMS_LOG;
              $SMS_MEMO .= "\n";
              $SMS_MEMO .= "* 시스템에서 자동으로 작성된 메모입니다. *\n";
              exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);

              $STATUS = "Y" ;
              syslog(LOG_DEBUG, "[SMS] 여러번 입금,  N1->Y, 지급캐시: ".number_format($CASH));

              ## 다른 입금건 정상 처리
              $query = " UPDATE CASH_SMS_LOG SET STATUS = 'Y', CASH_NO = ".$CASH_NO ;
              $query .= " WHERE STATUS = 'N1' ";
              $query .= " AND SMS_NAME = '".$SMS_NAME."' ";
              $db->query($query);

              syslog(LOG_DEBUG, "[SMS] 다른 입금건 정상처리 상태 변경 완료");

          }
        }

      } else {
        $STATUS = "N2";  // 주문서 없이 입금만
      }
  }

  syslog(LOG_DEBUG, "[SMS] STATUS : $STATUS");

  ## 입금상태 처리
  exec_sms_update($STATUS, $CASH_NO, $MEMBER_NO, $USER_ID, $log_idx);

  ## 자동 입금이 아닌 다른 경우 텔레그램 push
  if ($STATUS != "Y") {

    #상태 별 내용
    switch ($STATUS) {
      case "N1":
        $STATUS_MSG = "입금액 다름";
      break;
      case "N2":
        $STATUS_MSG = "주문서 없이 입금만";
      break;
      case "N3":
        $STATUS_MSG = "2개이상 주문서";
      break;
    }

    $msg_tele = "<b>[".$STATUS_MSG."]</b>\n";
    $msg_tele .= "- 입금일시 : ".$SMS_DATE."\n";
    $msg_tele .= "- 입금액 : <u>".number_format($SMS_PRICE)." 원</u>\n";
    $msg_tele .= "- 입금자명 : ".$SMS_NAME."\n";
    $msg_tele .= "- 입금은행 : ".$SMS_BANK."\n";
    $msg_tele .= "- 입금계좌 : ".$SMS_BANK_NUMBER."\n";

    #입금액이 주문액과 다를 경우 주문서 내용추가
    if ($STATUS == "N1") {
      if ($isCashed) { // 입금액 > 주문액
        $msg_tele .= "\n";
        $msg_tele .= "❗<b>[자동 캐시지급 완료]</b>❗\n";
        $msg_tele .= "- 입금고유번호 : ".$log_idx."\n";
        $msg_tele .= "- 입금일 : ".$SMS_DATE."\n";
        $msg_tele .= "- 주문 일시 : ".$ORDER_REG_DATE." \n";
        $msg_tele .= "- 주문서 금액 : <u>".number_format($ORDER_PRICE)." 원</u>\n";
        $msg_tele .= "- 실제 입금액 : ".number_format($SMS_PRICE)." 원\n";
        $msg_tele .= "- 지급캐시 : <u>".number_format($SMS_PRICE_SUPPLY)." 캐시</u>\n";

      } else {

        # 해당 회원에게 부족 입금액 자동 SMS전송
        $DEPOSIT_PRICE = $ORDER_PRICE - $SMS_PRICE_TOTAL;
        exec_send_sms("48", $HP, $fromHP, $MEMBER_NO, $SMS_NAME, $USER_ID, number_format($ORDER_PRICE), number_format($SMS_PRICE_TOTAL), number_format($DEPOSIT_PRICE));
        syslog(LOG_DEBUG, "[SMS] 부족금액 문자 전송완료 / ".number_format($DEPOSIT_PRICE));


        #관리자 메모 추가
        $SMS_MEMO = "[부족액 문자전송 완료 by시스템]\n";
        $SMS_MEMO .= "- 발송일 : ".date("Y-m-d H:i:s")."\n";
        $SMS_MEMO .= "- 부족(요청)금액 : ".number_format($DEPOSIT_PRICE)." 원\n";
        $SMS_MEMO .= "- 누적 입금액 : ".number_format($SMS_PRICE_TOTAL)." 원\n";
        $SMS_MEMO .= "- 주문서 금액 : ".number_format($ORDER_PRICE)." 원\n";
        exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);

        #텔레그램 msg
        $msg_tele .= "\n";
        $msg_tele .= "[주문서 정보]\n";
        $msg_tele .= "- 주문 일시 : ".$ORDER_REG_DATE." \n";
        $msg_tele .= "- 아이디  : ".$USER_ID." \n";
        $msg_tele .= "- 누적 입금액 : ".number_format($SMS_PRICE_TOTAL)." 원\n";
        $msg_tele .= "- 주문서 금액 : <u>".number_format($ORDER_PRICE)." 원</u>\n";
        $msg_tele .= "- 부족 금액 : <u>".number_format($DEPOSIT_PRICE)." 원</u>\n\n";
        $msg_tele .= " * 추가입금 요청 문자 전송완료* \n";

      }
    }
    #$msg_tele .= "👉 <a href='https://supermmadmin010.suloko.com/'>바로가기</a>\n";
    #syslog(LOG_DEBUG, "[SMS] $msg_tele ");

    foreach ($_TELEGRAM_CHAT_ID AS $_TELEGRAM_CHAT_ID_STR) {
        $_TELEGRAM_QUERY_STR    = array(
            'chat_id' => $_TELEGRAM_CHAT_ID_STR,
            'text'    => $msg_tele,
            'parse_mode' => "HTML"
        );
        telegramApiRequest("sendMessage", $_TELEGRAM_QUERY_STR);

        syslog(LOG_DEBUG, "[SMS] Telegram push 완료");
    }
  }

  syslog(LOG_DEBUG, "[SMS] END");

} else {
  syslog(LOG_DEBUG, "[SMS] Invalid Request");
}

?>
