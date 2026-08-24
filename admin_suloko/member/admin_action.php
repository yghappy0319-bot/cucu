<?php
$s_type = "STAFF";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/GoogleAuthenticator.php";

F_admin_chk($S_login);

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

//=>	테이블명을 설정합니다.
$VAL =	$_POST;
$S_table_name	=	$s_type;

//구글OTP용 class
$ga = new PHPGangsta_GoogleAuthenticator();

// 관리자별 카드결제 숨김설정 (admin205 전용)
if ($mode == "update_staff_hide" || $mode == "update_admin204_hide") {
  if ($S_login['user_id'] != "admin205") {
    alert_print("[ERRER] 권한없음");
    history_go();
    exit;
  }

  $target_user_id = isset($VAL['TARGET_USER_ID']) ? trim($VAL['TARGET_USER_ID']) : '';
  // 하위호환: 기존 admin204 전용 모드
  if ($target_user_id === '' && $mode == "update_admin204_hide") {
    $target_user_id = 'admin204';
  }

  if ($target_user_id === '') {
    alert_print("[ERROR] 대상 관리자가 없습니다.");
    history_go();
    exit;
  }

  // 실제 존재하는 STAFF 인지 확인
  $staff_exists = (int)$db->get_data_one("SELECT COUNT(*) FROM STAFF WHERE USER_ID = '".addslashes($target_user_id)."'");
  if ($staff_exists < 1) {
    alert_print("[ERROR] 존재하지 않는 관리자입니다.");
    history_go();
    exit;
  }

  $cfg = array(
    'hide_card_wayup' => isset($VAL['hide_card_wayup']) ? $VAL['hide_card_wayup'] : 'N',
    'hide_card_120k'  => isset($VAL['hide_card_120k']) ? $VAL['hide_card_120k'] : 'N',
  );

  if (save_staff_hide_config($target_user_id, $cfg)) {
    alert_print("[OK] 숨김설정 저장 완료");
  } else {
    alert_print("[ERROR] 숨김설정 저장 실패");
  }

  $go = "./admin_add.html";
  if (!empty($VAL['STAFF_NO'])) {
    $go .= "?mode=update&no=".$VAL['STAFF_NO'];
  }
  meta_go($go);
  exit;
}

if ($mode == 'insert') {

  //SuperAdmin(level4) 외 계정 생성 금지
  if ($S_login['level'] < 4) {
    alert_print("[ERRER] 권한없음");
    history_go();
    exit;
  }

  //아이디 중복확인
  $Chk_id = $db->get_data_one("SELECT COUNT(*) FROM `{$S_table_name}` WHERE USER_ID = '{$VAL['USER_ID']}'");
  if ($Chk_id > 0) {
    alert_print("[ERRER] 아이디 중복");
    history_go();
    exit;
  }
  //비밀키 생성
  $VAL['SECRETKEY'] = $ga->createSecret();

} else {
	$info	=	$db->get_data("SELECT * FROM `{$S_table_name}` WHERE STAFF_NO='{$VAL['STAFF_NO']}'");
  if (!isset($info['USER_ID'])) {
    alert_print("[ERRER] 관리자 불일치");
    history_go();
    exit;
  }

  if ($VAL['PASSWD'] == "") {
    unset($VAL['PASSWD']);
  }
}

//OTP키 재발급 처리
if ($mode == "update_otp") {
  //비밀키 생성
  $VAL['SECRETKEY'] = $ga->createSecret();
  $func_name	= "F_".$s_type;
  $func_name($VAL);
  alert_print("[OK] 재발급 완료");
  meta_go("./admin_add.html?mode=update&no={$VAL['STAFF_NO']}");
  exit;
}

//OTP QR 메일발송 처리
if ($mode == "send_otp") {
  $qrCodeUrl = $ga->getQRCodeGoogleUrl("슈로코 ".$info['USER_ID'], $info['SECRETKEY']);
  $qrImage = "<img src='https://".$_SERVER['HTTP_HOST']."/member/qrOTP.php?images=".urlencode($qrCodeUrl)."' alt='' />";

  $TEMPLET_NO = 10;

  $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
  $to      = $info['EMAIL'];
  $subject = $EMAILTEMPLET['SUBJECT'];
  $content = $EMAILTEMPLET['CONTENT'];
  $content = str_replace('{admin_id}', $info['USER_ID'], $content);
  $content = str_replace('{qr_image}', $qrImage, $content);
  $type    = "2";

  if ($EMAILTEMPLET['STOPYN'] == 'N') {
    // mailer($fname, $fmail, $to, $subject, $content, $type, $info['NAME'], $info['STAFF_NO'], $TEMPLET_NO);
    // AWS SES 클라이언트 생성
    $sesClient = initializeSesClient();
    if ($sesClient) {
      sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $info['NAME'], $info['STAFF_NO'], $TEMPLET_NO);
    }
    alert_print("[OK] 메일 발송 완료");
  } else {
    alert_print("[ERROR] 메일 발송 실패");
  }
  meta_go("./admin_add.html?mode=update&no={$VAL['STAFF_NO']}");
  exit;
}

if (isset($VAL['PASSWD'])) {
  //비밀번호 검증
  if (!preg_match($pwRegexp, trim($VAL['PASSWD']))) {
    alert_print("[ERRER] 비밀번호는 8~20자의 영문과 숫자를 섞어 사용");
    history_go();
    exit;
  }
  if ($VAL['PASSWD'] != $VAL['VPASSWD']) {
    alert_print("[ERRER] 비밀번호 불일치");
    history_go();
    exit;
  }
}

//이메일 형식 확인
if (!filter_var($VAL['EMAIL'], FILTER_VALIDATE_EMAIL)) {
  alert_print("[ERRER] 이메일 형식 오류");
  history_go();
  exit;
}

if ($VAL['LEVEL'] == '') {
	$VAL['LEVEL'] = 1;
}

$func_name	= "F_".$s_type;

$func_name($VAL);

//meta_go("./admin.html");
meta_go("./admin_add.html?mode=update&no={$VAL['STAFF_NO']}");
?>
