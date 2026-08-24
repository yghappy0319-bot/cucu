<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  F_admin_chk($S_login);
  global $db;

  $VAL = $_POST;
  $RES = array("error" => true, "msg" => "");

  // SMS 로그 미결 처리
  if (trim($VAL['mode']) ==  "sms_bank_pending") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    $idx = explode(",", $VAL['no']);
    $status = explode(",", $VAL['status']);

    for ($i = 0; $i < count($idx); $i++) {
      if (trim($idx[$i]) == '') {
        continue;
      }
      $sql = "
        UPDATE
          CASH_SMS_LOG
        SET
          STATUS='N5',
          CHG_STAFF_ID = '{$S_login['user_id']}',
          CHG_REG_DATE = NOW(),
          LAST_STATUS = '{$status[$i]}'
        WHERE
          idx = {$idx[$i]}
      ";
      $db->query($sql);
    }
    $RES = array("error" => False, "msg" => "처리 되었습니다.");
    echo json_encode($RES);
    exit;
  // 문자 발송 횟수 저장
  } else if (trim($VAL['mode']) ==  "sms_sent_cnt") {
    $idx = explode(",", $VAL['idx']);

    $hp = $VAL['hp'];
    $no = $VAL['no'];

    $user_id = $db->get_data_one("SELECT USER_ID FROM MEMBER WHERE HP='{$hp}'");

    for ($i = 0; $i < count($idx); $i++) {
      if (trim($idx[$i]) == '') {
        continue;
      }

      $sql = "
        UPDATE
          CASH_SMS_LOG
        SET
          MSG_SENT_CNT = MSG_SENT_CNT + 1,
          MSG_SENT_MEMBER = '{$user_id}',
          MSG_SENT_TEMPLAT = '{$no}'
        WHERE
          idx = $idx[$i]";
      $db->query($sql);
    }
    $RES = array("error" => False, "msg" => "처리 되었습니다.");
    echo json_encode($RES);
    exit;
  } else if (trim($VAL['mode']) ==  "sms_bank_done") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    $query = "
      UPDATE
        CASH_SMS_LOG
      SET
        STATUS = 'N4',
        CHG_STAFF_ID = '{$S_login['user_id']}',
        CHG_REG_DATE = NOW(),
        LAST_STATUS = '{$VAL['status']}'
      WHERE
        idx = {$VAL['no']}
    ";
    $db->query($query);
    $RES = array("error" => False, "msg" => "종료 처리 되었습니다.");
    echo json_encode($RES);
    exit;
  } else if (trim($VAL['mode']) ==  "sms_bank_restoration") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    $query = "
      UPDATE
        CASH_SMS_LOG
      SET
        STATUS = (SELECT LAST_STATUS CASH_SMS_LOG WHERE idx = {$VAL['no']}),
        CHG_REG_DATE = null,
        CHG_STAFF_ID = null,
        LAST_STATUS = null
      WHERE
        idx = {$VAL['no']}
    ";
    $db->query($query);
    $RES = array("error" => False, "msg" => "복구 처리 되었습니다.");
    echo json_encode($RES);
    exit;
  }
?>
