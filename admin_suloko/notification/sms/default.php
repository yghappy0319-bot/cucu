<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;
  $RES = array("error" => False, "msg"=>"");

  if ($VAL['mode'] == 'resend_sms') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    if ($VAL['no'] == '') {
      echo json_encode(array("error" => 0, "msg" => "재발송할 내용을 선택해 주세요."));
      exit;
    }

    $info = $db->get_data("SELECT * FROM SMSSENDLOG WHERE LOG_NO='{$VAL['no']}'");

    $from        = $fromHP;
    $to          = $info['TOHP'];
    $CODESK      = "S";
    $TEMPLET_NO  = $info['TEMPLET_NO'];
    $MEMBER_NO   = $info['MEMBER_NO'];
    $MEMBER_NAME = $info['MEMBER_NAME'];
    $SUBJECT     = $info['SUBJECT'];
    $SENDMSG     = $info['SENDMSG'];
    $SUBJECT     = addslashes($SUBJECT);
    $SENDMSG     = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");

    echo json_encode(array("error" => 1, "msg" => "발송하였습니다."));
    exit;
  } else if ($VAL['mode'] == 'sms_add_sender') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SMSCONFIG.php";

    $SENDER       = trim($VAL["SENDER"]);
    $HP           = trim($VAL["HP"]);
    $HOMEPAGE_USE = ($VAL["HOMEPAGE_USE"] == "Y" ? "Y" : "N");
    $IS_USE       = ($VAL["IS_USE"] == "Y" ? "Y" : "N");

    if (isset($HOMEPAGE_USE) == false) $HOMEPAGE_USE = "N";

    if (!isset($SENDER) || strlen($SENDER) <= 0) {
      $RES['msg'] = "제공업체 오류";
      echo json_encode($RES);
      exit;
    }

    if (!isset($HP) || strlen($HP) <= 0) {
      $RES['msg'] = "발신번호 오류";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS CNT FROM SMSCONFIG WHERE HP = '{$HP}' AND SENDER = '{$SENDER}'");

    if ($info['CNT'] > 0) {
      $RES['msg'] = "해당 제공업체의 이미 등록된 번호입니다.";
      echo json_encode($RES);
      exit;
    }

    $data = array();
    $data['mode']         = "insert";
    $data['SENDER']       = $SENDER;
    $data['HP']           = $HP;
    $data['HOMEPAGE_USE'] = $HOMEPAGE_USE;
    $data['IS_USE']       = $IS_USE;

    F_SMSCONFIG($data);

    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == 'sms_update_sender') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SMSCONFIG.php";

    $SENDER       = trim($VAL["SENDER"]);
    $HP           = trim($VAL["HP"]);
    $HOMEPAGE_USE = ($VAL["HOMEPAGE_USE"] == "Y" ? "Y" : "N");
    $IS_USE       = ($VAL["IS_USE"] == "Y" ? "Y" : "N");
    $SMS_NO       = trim($VAL["SMS_NO"]);

    if (isset($HOMEPAGE_USE) == false) $HOMEPAGE_USE = "N";

    if (!isset($SENDER) || strlen($SENDER) <= 0) {
      $RES['msg'] = "제공업체 오류";
      echo json_encode($RES);
      exit;
    }

    if (!isset($HP) || strlen($HP) <= 0) {
      $RES['msg'] = "발신번호 오류";
      echo json_encode($RES);
      exit;
    }

    if (!isset($SMS_NO) || $SMS_NO <= 0) {
      $RES['msg'] = "설정 코유값 오류";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS CNT FROM SMSCONFIG WHERE HP = '{$HP}' AND SENDER = '{$SENDER}' AND SMS_NO != '{$SMS_NO}'");

    if ($info['CNT'] > 0) {
      $RES['msg'] = "해당 제공업체의 이미 등록된 번호입니다.";
      echo json_encode($RES);
      exit;
    }

    $data = array();
    $data['mode']         = "update";
    $data['SENDER']       = $SENDER;
    $data['HP']           = $HP;
    $data['HOMEPAGE_USE'] = $HOMEPAGE_USE;
    $data['IS_USE']       = $IS_USE;
    $data['SMS_NO']       = $SMS_NO;

    F_SMSCONFIG($data);

    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == 'sms_delete_sender') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SMSCONFIG.php";

    $SMS_NO = trim($VAL["no"]);

    if (!isset($SMS_NO) || $SMS_NO <= 0) {
      $RES['msg'] = "설정 코드값 오류";
      echo json_encode($RES);
      exit;
    }

    $data = array();
    $data['mode']   = "delete";
    $data['SMS_NO'] = $SMS_NO;

    F_SMSCONFIG($data);

    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  }
?>
