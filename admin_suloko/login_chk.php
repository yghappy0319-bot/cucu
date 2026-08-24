<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_slot.php";
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/GoogleAuthenticator.php";

  $user_id = $_POST['user_id'];
  $user_pw = $_POST['user_pw'];
  $mode    = $_POST['mode'];

  $RES = array(
    "error" => 1,
    "msg"   => "아이디/비밀번호/인증번호를 확인하세요"
  );

  // 기본값 검증
  if (empty($user_id)) {
    echo json_encode($RES);
    exit;
  }
  if (empty($user_pw)) {
    echo json_encode($RES);
    exit;
  }

  $user_id = xss_clean($user_id);
  $user_pw = xss_clean($user_pw);

  // 아이디, 비번만 확인
  $info = $db->get_data_one("SELECT COUNT(*) FROM STAFF WHERE USER_ID='{$user_id}' AND PASSWD=PASSWORD('{$user_pw}') AND STOPYN='N' ");
  if ($info == 0) {
    echo json_encode($RES);
    exit;
  }


  //_____________________아이디 비번 확인
  if ($mode == "step1") {

    $goto = admin_login($user_id);
    $RES["error"] = 0;
    $RES["msg"] = $goto;
    echo json_encode($RES);
    exit;

  }

  //_____________________OTP 확인
  if ($mode == "step2") {

    $goto = admin_login($user_id);
    $RES["error"] = 0;
    $RES["msg"] = $goto;
    echo json_encode($RES);

  }

  /*
  * 로그인 처리 함수
  */
  function admin_login($user_id) {
    global $db, $clientIpAddress;

    $info = $db->get_data("SELECT * FROM STAFF WHERE USER_ID='{$user_id}' AND STOPYN='N'");

    $S_login = array();
    $S_login['user_id']  = $info['USER_ID'];
    $S_login['level']    = $info['LEVEL'];
    $S_login['name']     = $info['NAME'];
    $S_login['reg_date'] = $info['REG_DATE'];
    $S_login['mode']     = $info['HP'];  // HP 컬럼을 계정의 실행 모드로 사용함
    $S_login['is_use']   = $info['STOPYN'];
    $S_login['ipaddr']   = $clientIpAddress;
    $S_login['agent']    = $_SERVER["HTTP_USER_AGENT"];
    admin_save_session_login($S_login);

    ## 로그인 로그 처리 (2023-01-27)
    $filename = "./log/loginLog_".$S_login['user_id'].".txt";
    $now = date('Y-m-d H:i:s ', time());
    $str = $now."\t".$S_login['ipaddr']."\t".$S_login['agent']."\n";
    $fp = fopen($filename, 'a+');
    fwrite($fp, $str);
    fclose($fp);

    ## 미국관리자의 경우 메인화면 변경 (SKL-496)
    if ($S_login['user_id'] == "usa") {
      return admin_url('blank.html');
    } else {
      return admin_url('/');
    }
  }
?>
