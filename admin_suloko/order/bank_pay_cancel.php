<?php
  ## 무통장 입금내역 삭제 (미입급건)
  ## SLK-337
  ## SLK-411
  include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

  include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

  $VAL = $_POST;

  if ($VAL['ALL_NO'] == '') {
    alert_print("선택항목이 없습니다.");
    meta_go("/order/payment_list_bank.html");
  }

  $cash_nos = explode(",", $VAL['ALL_NO']);

  for ($i = 0; $i < count($cash_nos); $i++) {
    $cash_no = $cash_nos[$i];

    if (trim($cash_no) == '') {
      continue;
    }

    $info = $db->get_data("SELECT USER_ID, CASH, PRICE, IS_USE FROM CASH WHERE CASH_NO='{$cash_no}'");
    $mem  = $db->get_data("SELECT NAME, HP, MEMBER_NO FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

    //_______대기,취소건 삭제 처리
    if ($SMS_YN == 'D') {
      if ($S_login['level'] > 2) {
        if ($info['IS_USE'] == "Y") {
          alert_print("[{$cash_no}] 결제건은 삭제할 수 없습니다.");
          continue;
        }

        $query = "UPDATE CASH SET IS_USE='D', STAFF_ID='{$S_login['user_id']}', CANCAL_DATE = NOW() WHERE CASH_NO = '{$cash_no}'";
        $db->query($query);
      }
    //_______대기 건 취소 처리
    } else {
      if ($info['IS_USE'] != "N") {
        continue;
      }

      // $query = "DELETE FROM CASH WHERE CASH_NO =".$cash_no." AND USER_ID ='".$info['USER_ID']."'";
      $query = "UPDATE CASH SET IS_USE='C', STAFF_ID='{$S_login['user_id']}', CANCAL_DATE = NOW() WHERE CASH_NO = '{$cash_no}'";
      $db->query($query);

      if ($SMS_YN == 'Y') {
        ## SMS
        $TEMPLET_NO  = 23;
        $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

        $from        = $fromHP;
        $to          = $mem["HP"];
        $CODESK      = "S";
        $MEMBER_NO   = $mem['MEMBER_NO'];
        $MEMBER_NAME = $mem['NAME'];

        $SUBJECT     = $SMSTEMPLET['SUBJECT'];
        $SENDMSG     = $SMSTEMPLET['CONTENT'];

        $SENDMSG     = str_replace('{USER_NAME}', $MEMBER_NAME, $SENDMSG);
        $SENDMSG     = str_replace('{CASH}', number_format($info['CASH']), $SENDMSG);
        $SENDMSG     = str_replace('{PRICE}', number_format($info['PRICE']), $SENDMSG);
        $SENDMSG     = addslashes($SENDMSG);

        $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");
      }
    }
  }

  if ($SMS_YN == 'D') {
    alert_print("선택하신 내역을 삭제 완료 되었습니다.");
  } else if ($SMS_YN == 'Y') {
    alert_print("선택하신 내역이 입금취소 및 문자전송 완료 되었습니다.");
  } else {
    alert_print("선택하신 내역이 입금취소 완료 되었습니다.");
  }
  meta_go("/order/payment_list_bank.html?".$PARAM);
?>
