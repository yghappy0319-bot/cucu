<?php
  #################################################
  ## 무통장 신청 후 입금 없이 16시간 이상 경과된 건 취소 처리
  #################################################

  require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';

  $longTermSql = "
    SELECT
      C.CASH_NO,
      C.MEMBER_NO,
      M.NAME,
      M.HP,
      C.CASH,
      C.PRICE
    FROM
      CASH AS C
        LEFT JOIN
      MEMBER AS M ON M.USER_ID = C.USER_ID
        LEFT JOIN
      CASH_BANK_MEMO AS B ON B.CASH_NO = C.CASH_NO
    WHERE
      CARDNAME = '무통장' AND IS_USE = 'N' AND TIMESTAMPDIFF(HOUR, C.REG_DATE, NOW()) >= 16 AND B.MEMO IS NULL
  ";
  $list = $db->get_list($longTermSql);

  if (count($list['CASH_NO']) <= 0) {
    exit;
  }

  for ($i = 0; $i < count($list['CASH_NO']); $i++) {
    $query = "UPDATE CASH SET IS_USE='C', STAFF_ID='auto', CANCAL_DATE = NOW() WHERE CASH_NO = '{$list['CASH_NO'][$i]}'";
    $db->query($query);

    ## SMS
    $TEMPLET_NO  = 23;
    $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

    $from        = $fromHP;
    $to          = $list['HP'][$i];
    $CODESK      = "S";
    $MEMBER_NO   = $list['MEMBER_NO'][$i];
    $MEMBER_NAME = $list['NAME'][$i];

    $SUBJECT     = $SMSTEMPLET['SUBJECT'];
    $SENDMSG     = $SMSTEMPLET['CONTENT'];

    $SENDMSG     = str_replace('{USER_NAME}', $MEMBER_NAME, $SENDMSG);
    $SENDMSG     = str_replace('{CASH}', number_format($list['CASH'][$i]), $SENDMSG);
    $SENDMSG     = str_replace('{PRICE}', number_format($list['PRICE'][$i]), $SENDMSG);
    $SENDMSG     = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");
  }
?>
