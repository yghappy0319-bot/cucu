<?
#################################################
## 선입금 후 후주문서 작성 처리
#################################################

include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_telegram.php";
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_sms.php";

error_reporting( E_ALL );
ini_set( "display_errors", 1 );

$db->query("insert into CRON_LOG set FILE = '선입금 후 후주문서 작성 처리', REG_DATE = now() ");

//$sql = " INSERT INTO CRON_LOG SET FILE = 'cron_test', REG_DATE = now() ";
//$db->query($sql);

## telegram define
define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
$_TELEGRAM_CHAT_ID = array('');


$whereIs = " WHERE STATUS = 'N2' ";
$query = " SELECT SMS_NAME, SMS_NAME_ORG, SUM(SMS_PRICE) AS SMS_PRICE FROM CASH_SMS_LOG ";
$query .= $whereIs;
$query .= " GROUP BY SMS_NAME ";
$list = $db->get_list($query);

syslog(LOG_DEBUG, "[SMS-ORDER] START");

if (count($list["SMS_NAME"]) > 0) {

  for ($i=0; $i<count($list["SMS_NAME"]); $i++) {
    $SMS_NAME = $list["SMS_NAME"][$i];
    $SMS_PRICE = $list["SMS_PRICE"][$i];

    ## 예외처리 확인
    $tmp = get_exception($SMS_NAME);
    $SMS_NAME = $tmp["SMS_NAME"];
    $SMS_NAME_ORG = $tmp["SMS_NAME_ORG"];

    ## 주문서 정보 갯수 확인
    $order_tmp = get_cash_count($SMS_NAME, $SMS_PRICE, "Y");
    #$orderNum = $order_tmp["CNT"];

    if ($order_tmp["CNT"] == 1) {

      syslog(LOG_DEBUG, "[SMS-ORDER] 선입금 후 주문서 작성건 발생 - $SMS_NAME_ORG / ".number_format($SMS_PRICE));

      ## 주문서 정보 및 회원 정보
      $tmp = get_cash_info($SMS_NAME, $SMS_PRICE, "N");

      if (!is_array($tmp)) {
        syslog(LOG_DEBUG, "[SMS-ORDER] 대상 주문서 및 회원 정보 조회 실패 - NAME:{$SMS_NAME}, PRICE:{$SMS_PRICE}");
        continue;
      }

      $USER_ID = $tmp['USER_ID'];
      $PARTNER_ID = $tmp['PARTNER_ID'];
      $REG_DATE = $tmp['REG_DATE'];
      $CASH = $tmp['CASH'];
      $MEMBER_CASH = $tmp['MEMBER_CASH'];
      $MEMBER_POINT = $tmp['MEMBER_POINT'];
      $HP = $tmp['HP'];
      $NAME = $tmp['NAME'];
      $EMAIL = $tmp['EMAIL'];
      $CASH_NO = $tmp['CASH_NO'];
      $MEMBER_NO = $tmp['MEMBER_NO'];

      # 캐시 지급
      exec_member_cash($USER_ID, $PARTNER_ID, $REG_DATE, $CASH, $MEMBER_CASH, $MEMBER_POINT, $CASH_NO, $SMS_PRICE);

      # 회원 CASH 업데이트
      exec_member_cash_update($CASH, $USER_ID, $CASH_NO);
      syslog(LOG_DEBUG, "[SMS-ORDER] 캐시지급 완료 - $SMS_NAME / ".number_format($CASH));

      # 입금확인 SMS 문자 발송
      exec_send_sms("19", $HP, $fromHP, $MEMBER_NO, $NAME, $USER_ID);
      syslog(LOG_DEBUG, "[SMS-ORDER] 충전완료 문자 전송완료 - $SMS_NAME ");

      # EMAIL 발송
      exec_send_email("6", $EMAIL, "2", $NAME, $MEMBER_NO);

      #관리자 메모 추가
      $SMS_MEMO = "[자동 캐시지급 완료]\n";
      $SMS_MEMO .= "- 선입금 후 주문서 작성 건\n";
      $SMS_MEMO .= "- 주문번호 : ".$CASH_NO."\n";
      $SMS_MEMO .= "- 지급캐시액 : ".number_format($CASH)." 원\n";
      $SMS_MEMO .= "- 총 입금액 : ".number_format($SMS_PRICE)." 원\n";
      $SMS_MEMO .= "\n";
      $SMS_MEMO .= "* 시스템에서 자동으로 작성된 메모입니다.\n";
      exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);

      # 입금상태 업데이트를 위한 해당 SMS 로그 확인
      $query = " SELECT IDX FROM CASH_SMS_LOG " ;
      $query .= " WHERE SMS_NAME = '".$SMS_NAME_ORG."' ";
      $query .= " AND STATUS = 'N2' ";
      $list_tmp = $db->get_list($query);

      for($k=0; $k<count($list_tmp["IDX"]); $k++) {
        $log_idx = $list_tmp["IDX"][$k];

        # 입금상태 처리
        $STATUS = "Y";
        exec_sms_update($STATUS, $CASH_NO, $MEMBER_NO, $USER_ID, $log_idx);
        syslog(LOG_DEBUG, "[SMS-ORDER] 로그 업데이트 완료 - $SMS_NAME_ORG / $log_idx");
      }
    } else { //입금액과 주문액이 안 맞을 경우 회원에게 추가입금 요청

      # 입금자명만 기준으로 입금정보 존재 여부 체크하여 주문 정보 같이 PUSH
      $orderFrm = get_cash_count($SMS_NAME_ORG, "", "Y");
      if ($orderFrm['CNT'] > 0) {

          ## 주문서 정보 및 회원 정보
          $tmp = get_cash_info($SMS_NAME, "", "N");

          if (!is_array($tmp)) {
            syslog(LOG_DEBUG, "[SMS-ORDER] 대상 주문서 및 회원 정보 조회 실패 2 - NAME:{$SMS_NAME}");
            continue;
          }

          $USER_ID = $tmp['USER_ID'];
          $PARTNER_ID = $tmp['PARTNER_ID'];
          $REG_DATE = $tmp['REG_DATE'];
          $CASH = $tmp['CASH'];
          $MEMBER_CASH = $tmp['MEMBER_CASH'];
          $MEMBER_POINT = $tmp['MEMBER_POINT'];
          $HP = $tmp['HP'];
          $NAME = $tmp['NAME'];
          $EMAIL = $tmp['EMAIL'];
          $CASH_NO = $tmp['CASH_NO'];
          $MEMBER_NO = $tmp['MEMBER_NO'];
          $ORDER_PRICE = $tmp['PRICE'];
          $IS_EVENT_USER = $tmp['IS_EVENT_USER'];

          if (empty($ORDER_PRICE) || $ORDER_PRICE == "") {
            // 주문금액이 없는 경우 다음으로..
            continue;
          }

          $DEPOSIT_PRICE = $ORDER_PRICE - $SMS_PRICE; //미처리된 입금건 총금액 + 현재 수신된 입금액

          // 1. 입금액 < 주문서 금액
          // 2. 입금액 > 주문서 금액
          if ($DEPOSIT_PRICE > 0 ) { // 1. 입금액 < 주문서 금액

            syslog(LOG_DEBUG, "[SMS-ORDER] 선입금 후 주문서 작성건 발생(부족) - $SMS_NAME / $USER_ID / 입금액 : ".number_format($SMS_PRICE));

            # 1회만 발송
            $cnt = get_sms_deposit($SMS_NAME, $ORDER_PRICE, $SMS_PRICE, $USER_ID);
            if ($cnt <= 0) {
              # 해당 회원에게 부족 입금액 자동 SMS전송
              exec_send_sms("48", $HP, $fromHP, $MEMBER_NO, $SMS_NAME, $USER_ID, number_format($ORDER_PRICE), number_format($SMS_PRICE), number_format($DEPOSIT_PRICE));
              syslog(LOG_DEBUG, "[SMS-ORDER] 부족금액 문자 전송완료 - $SMS_NAME / $USER_ID / 입금액 : ".number_format($DEPOSIT_PRICE));

              #관리자 메모 추가
              $SMS_MEMO = "[부족액 문자전송 완료 by시스템]\n";
              $SMS_MEMO .= "- 상태 : 선입금 후 주문서 작성\n";
              $SMS_MEMO .= "- 주문번호 : ".$CASH_NO."\n";
              $SMS_MEMO .= "- 발송일 : ".date("Y-m-d H:i:s")."\n";
              $SMS_MEMO .= "- 부족(요청)금액 : ".number_format($DEPOSIT_PRICE)." 원\n";
              $SMS_MEMO .= "- 누적 입금액 : ".number_format($SMS_PRICE)." 원\n";
              $SMS_MEMO .= "- 주문서 금액 : ".number_format($ORDER_PRICE)." 원\n";
              exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);

              # 부족액 SMS 로그 저장
              exec_sms_deposit($SMS_NAME, $ORDER_PRICE, $SMS_PRICE, $USER_ID);
            } else {
               syslog(LOG_DEBUG, "[SMS-ORDER] 여전히 부족 - SMS 미발송, 부족액 : ".number_format($DEPOSIT_PRICE));
            }

          } else { // 2. 입금액 > 주문서 금액
            if ($ORDER_PRICE < $SMS_PRICE) {

              syslog(LOG_DEBUG, "[SMS-ORDER] 입금액(${SMS_PRICE}) > 주문액(${ORDER_PRICE}) - ${SMS_NAME}");

              # SMS 입금건 확인
              $query = " SELECT IDX, SMS_DATE FROM CASH_SMS_LOG " ;
              $query .= " WHERE SMS_NAME = '".$SMS_NAME."' ";
              $query .= " AND SMS_PRICE = '".$SMS_PRICE."' ";
              $query .= " AND STATUS = 'N2' ";
              $list_tmp = $db->get_data($query);
              $SMS_IDX = $list_tmp["idx"];
              $SMS_DATE = $list_tmp["SMS_DATE"];


              # 입금액 기준으로 지급 캐시액 계산 (CASH_CONFIG 금액구간 우선)
              $SMS_PRICE_SUPPLY = calc_cash_supply_by_price($SMS_PRICE, $IS_EVENT_USER === true);
              $SMS_PRICE_VAT = $SMS_PRICE - $SMS_PRICE_SUPPLY; // 부가세
              $SMS_ITEMNAME = "슈로코 ".number_format($SMS_PRICE);

              # 캐시 지급처리
              exec_member_cash($USER_ID, $MEMBER_PARTNER_ID, $ORDER_REG_DATE, $SMS_PRICE_SUPPLY, $MEMBER_CASH, $MEMBER_POINT, $CASH_NO, $SMS_PRICE);

              ## 회원 CASH 업데이트
              exec_member_cash_update($SMS_PRICE_SUPPLY, $USER_ID, $CASH_NO);
              syslog(LOG_DEBUG, "[SMS-ORDER] 캐시지급 완료 - ${SMS_NAME}");

              ## 입금확인 SMS 문자 발송
              exec_send_sms("19", $HP, $fromHP, $MEMBER_NO, $NAME, $USER_ID);
              syslog(LOG_DEBUG, "[SMS-ORDER] 충전완료 문자 전송완료 - ${SMS_NAME}");

              ## EMAIL 발송
              exec_send_email("6", $EMAIL, "2", $NAME, $MEMBER_NO);

              #주문서 수정 (지급캐시, 결제액, 구매상품명)
              $fields = " IS_USE='Y', CASH = '".$SMS_PRICE_SUPPLY."', PRICE = '".$SMS_PRICE."', ITEMNAME = '슈로코 ".number_format($SMS_PRICE_SUPPLY)."' ";
              exec_cash_update($CASH_NO, $USER_ID, $fields);

              #관리자 메모 추가
              $SMS_MEMO = "[자동 캐시지급 완료]\n";
              $SMS_MEMO .= "- 입금액 > 주문액 건\n";
              $SMS_MEMO .= "- 캐시지급일 : ".date("Y-m-d H:i:s")."\n";
              $SMS_MEMO .= "- 주문서상 금액 : ".number_format($ORDER_PRICE)." 원\n";
              $SMS_MEMO .= "- 실입금액(누적) : ".number_format($SMS_PRICE)." 원\n";
              $SMS_MEMO .= "- 지급 캐시액 : ".number_format($SMS_PRICE_SUPPLY)." 캐시\n";
              $SMS_MEMO .= "\n";
              $SMS_MEMO .= "* 입금액 기준으로 캐시지급 완료되었습니다.\n";
              $SMS_MEMO .= "* 시스템에서 자동으로 작성된 메모입니다.\n";
              exec_order_admin_memo($SMS_MEMO, $CASH_NO, $USER_ID);


              # 입금상태 업데이트를 위한 해당 SMS 로그 확인
              $query = " SELECT IDX, SMS_DATE FROM CASH_SMS_LOG " ;
              $query .= " WHERE SMS_NAME = '".$SMS_NAME."' ";
              $query .= " AND STATUS = 'N2' ";
              $list_tmp = $db->get_list($query);

              for($k=0; $k<count($list_tmp["IDX"]); $k++) {
                $log_idx = $list_tmp["IDX"][$k];
                $SMS_DATE = $list_tmp["SMS_DATE"][$k];

                # 입금상태 처리
                $STATUS = "Y";
                exec_sms_update($STATUS, $CASH_NO, $MEMBER_NO, $USER_ID, $log_idx);
                syslog(LOG_DEBUG, "[SMS-ORDER] 로그 업데이트 완료 - $SMS_NAME / $log_idx");
              }

              syslog(LOG_DEBUG, "[SMS-ORDER] 입금액>주문액 / $SMS_NAME / N2->Y / 지급캐시: ".number_format($SMS_PRICE_SUPPLY));
            }
          }
      }
    }

  }

} else {
  syslog(LOG_DEBUG, "[SMS-ORDER] 일치건 없음");
}

syslog(LOG_DEBUG, "[SMS-ORDER] END");
?>
