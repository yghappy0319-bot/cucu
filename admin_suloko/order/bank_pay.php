<?php
  // error_reporting( E_ALL );
  // ini_set( "display_errors", 1 );
  header("Pragma: No-Cache");
  include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
  include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
  include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

  include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

  $VAL = $_POST;

  if ($VAL['CASH_NO'] == '' || $VAL['CASH_NO'] <= 0) {
    alert_print("결제번호오류");
    meta_go("/order/payment_list_bank.html?".$PARAM);
    exit;
  }

  // SLK-557 결제금액/캐시 변경 처리
  if ($VAL['MODE'] == "PRICE_UPDATE") {
    $N_PRICE = preg_replace("/[^0-9]/", "", $VAL['N_PRICE']);
    $N_CASH  = calc_cash_supply_by_price($N_PRICE);

    /*
    if ($N_PRICE < 1000) {
      alert_print("결제금액이 1000원 이하입니다. 다시 확인 부탁드립니다.");
      meta_go("/order/payment_list_bank.html?".$PARAM);
      exit;
    }
    */

    $query = "UPDATE CASH SET PRICE='{$N_PRICE}', CASH='{$N_CASH}', STAFF_ID='{$S_login['user_id']}' WHERE CASH_NO='".$VAL['CASH_NO']."' AND IS_USE = 'N'";
    $db->query($query);
  }

  $info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO='{$VAL['CASH_NO']}'");
  $mem  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

  if ($info['IS_USE'] != 'N') {
    alert_print("해당건({$VAL['CASH_NO']})이 대기 상태가 아닙니다.");
    meta_go("/order/payment_list_bank.html?".$PARAM);
    exit;
  }

  # 파트너 첫결제
  if ($mem['PARTNER_ID']) {
    $RESULT_PARTNER_ID = exec_partner_firstsale($mem['USER_ID'], $mem['PARTNER_ID'], $mem['REG_DATE']);
  }

  # 파트너 summary 처리
  if ($RESULT_PARTNER_ID) {
    exec_partner_summary($mem['PARTNER_ID'], "PAY");
  }

  // 회원의 결제수
  $CASH_CNT = $db->get_data_one("SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = {$mem['MEMBER_NO']} AND IS_USE='Y'") + 1;

  ## CASH table UPDATE
  $query = "UPDATE CASH SET IS_USE = 'Y', CON_REG_DATE = NOW(), STAFF_ID='{$S_login['user_id']}', PARTNER_ID = '{$RESULT_PARTNER_ID}', CASH_CNT = {$CASH_CNT}";
  $query .= " WHERE USER_ID = '{$info['USER_ID']}' ";
  $query .= " AND CASH_NO = '{$VAL['CASH_NO']}'";
  $db->query($query);


  ## T_CASH_LOG INSERT
  $_L['CASH_NO']     = $VAL['CASH_NO'];
  $_L['ORDERS_NO']   = 0;
  $_L['WINNING_NO']  = 0;
  $_L['INVOCE_NO']   = 0;
  $_L['CASH_LOG_NO'] = 0;
  $_L['PRICE']       = $info['PRICE'];
  $_L['CASH']        = $info['CASH'];
  $_L['N_CASH']      = $mem['CASH'] + $info['CASH']; //지급(차감) 후 캐시
  $_L['O_CASH']      = $mem['CASH']; // 기브(차감) 전 캐시
  $_L['USER_ID']     = $mem['USER_ID'];
  $_L['MEMO']        = "캐시구매충전(무통장)";
  $_L['STATUS']      = "P";

  $query = "
    INSERT INTO T_CASH_LOG(
      CASH_NO,
      ORDERS_NO,
      WINNING_NO,
      INVOCE_NO,
      CASH_LOG_NO,
      PRICE,
      CASH,
      N_CASH,
      O_CASH,
      USER_ID,
      MEMO,
      STATUS,
      REG_DATE
    ) VALUES (
      '".$_L['CASH_NO']."',
      '".$_L['ORDERS_NO']."',
      '".$_L['WINNING_NO']."',
      '".$_L['INVOCE_NO']."',
      '".$_L['CASH_LOG_NO']."',
      '".$_L['PRICE']."',
      '".$_L['CASH']."',
      '".$_L['N_CASH']."',
      '".$_L['O_CASH']."',
      '".$_L['USER_ID']."',
      '".$_L['MEMO']."',
      '".$_L['STATUS']."',
      NOW()
    )
  ";
  $db->query($query);

  $지급포인트 = get_cash_config_by_cash($info['CASH']);
  $grantPoint = !empty($지급포인트['POINT']) ? (int)$지급포인트['POINT'] : 0;

  ## MEMBER UPDATE
  $query = "UPDATE MEMBER SET CASH = CASH + ".$info['CASH'];
  if ($grantPoint > 0) {
    $query .= ", POINT = POINT + {$grantPoint} ";
  }

  $query .= " WHERE USER_ID = '".$info['USER_ID']."' ";
  $db->query($query);

  $point_log = array();
  $point_log["mode"] = "insert";
  $point_log["COUPON_NO"] = 0;
  $point_log["ORDERS_NO"] = 0;
  $point_log["CASH_LOG_NO"] = $VAL['CASH_NO'];
  $point_log["POINT"] = $grantPoint;
  $point_log["N_POINT"] = $mem['POINT'] + $grantPoint;
  $point_log["O_POINT"] = $mem['POINT'];
  $point_log["USER_ID"] = $mem['USER_ID'];
  $point_log["MEMO"] = "캐시충전보너스";
  $point_log["STATUS"] = "P";
  if ($point_log["POINT"] > 0) {
    F_T_POINT_LOG($point_log);
  }

  ## SMS
  $TEMPLET_NO   = 19;
  $SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

  $from         = $fromHP;
  $to           = $mem["HP"];
  $CODESK       = "S";
  $MEMBER_NO    = $mem['MEMBER_NO'];
  $MEMBER_NAME  = $mem['NAME'];

  $SUBJECT      = $SMSTEMPLET['SUBJECT'];
  $SENDMSG      = $SMSTEMPLET['CONTENT'];

  $SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
  $SENDMSG      = addslashes($SENDMSG);
  $sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");

  ## EMAIL
  $TEMPLET_NO   = 6;
  $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
  $to           = $mem['EMAIL'];
  $subject      = $EMAILTEMPLET['SUBJECT'];
  $content      = $EMAILTEMPLET['CONTENT'];
  $type         = "2";

  if ($EMAILTEMPLET['STOPYN'] == 'N') {
    // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
    // AWS SES 클라이언트 생성
    $sesClient = initializeSesClient();
    if ($sesClient) {
      sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
    }
  }

  $msg = "입금 처리가 완료 되었습니다.";
  alert_print($msg);
  meta_go("/order/payment_list_bank.html?".$PARAM);
?>
