<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
  $VAL = $_POST;

  $RES = array("error" => True, "msg" => "");

  if ($VAL["mode"] == "signup") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER.php";
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER_LOG.php";

    $VAL['REG_ID']      = xss_clean($VAL['REG_ID']);
    $VAL['REG_PHONE']   = xss_clean($VAL['REG_PHONE']);
    $VAL['REG_EMAIL']   = xss_clean($VAL['REG_EMAIL']);
    $VAL['REG_PWD_CHK'] = xss_clean($VAL['REG_PWD_CHK']);
    $VAL['REG_NAME']    = xss_clean($VAL['REG_NAME']);
    $VAL['REG_ENAME']   = xss_clean($VAL['REG_ENAME']);
    $VAL['REG_BIRTH']   = xss_clean($VAL['REG_BIRTH']);
    $VAL['REG_COUNTRY'] = xss_clean($VAL['REG_COUNTRY']);
    $VAL['REG_GENDER']  = xss_clean($VAL['REG_GENDER']);

    // 아이디 검사
    if ($VAL["REG_ID"] == "") {
      $RES["msg"] = "아이디를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 아이디 확인
    $info = $db->get_data_one("
      SELECT SUM(CNT) AS CNT
      FROM ((
        SELECT COUNT(USER_ID) AS CNT
        FROM MEMBER
        WHERE USER_ID = '{$VAL['REG_ID']}'
      ) UNION ALL (
        SELECT COUNT(USER_ID) AS CNT
        FROM OUT_MEMBER
        WHERE USER_ID = '{$VAL['REG_ID']}')) AS A
    ");

    if ($info > 0) {
      $RES["msg"] = "사용할 수 없는 아이디입니다.";
      echo json_encode($RES);
      exit;
    }

    // 탈퇴 회원 확인(탈퇴 후 180일 경과 유무 확인)
    $out_info = $db->get_data_one("
      SELECT COUNT(*) AS CNT
      FROM OUT_MEMBER
      WHERE
        HP = '{$VAL['REG_PHONE']}'
          AND
        TIMESTAMPDIFF(DAY, REG_DATE, NOW()) <= 180
      LIMIT 1
    ");

    if ($out_info > 0) {
      $RES["msg"] = "사용할 수 없는 연락처입니다.";
      echo json_encode($RES);
      exit;
    }

    // 이메일 확인
    if ($VAL["REG_EMAIL"] == "") {
      $RES["msg"] = "이메일을 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 국적 선택 여부
    if ($VAL["REG_COUNTRY"] == "") {
      $RES["msg"] = "국적을 선택해 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 이름 확인
    if ($VAL["REG_COUNTRY"] == "kr") {
      if (!preg_match($koreanRegexp, trim($VAL["REG_NAME"]))) {
        $RES["msg"] = "이름은 한글로 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }
    } else {
      if (!preg_match($englishRegexp, trim($VAL["REG_NAME"]))) {
        $RES["msg"] = "외국인은 이름을 영어로 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }
    }

    // 생년월일 확인
    if ($VAL["REG_BIRTH"] == "") {
      $RES["msg"] = "생년월일을 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if (strlen($VAL["REG_BIRTH"]) != 8) {
      $RES["msg"] = "생년월일을 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $birth_y = substr($VAL["REG_BIRTH"], 0, 4);
    $birth_m = substr($VAL["REG_BIRTH"], 4, 2);
    $birth_d = substr($VAL["REG_BIRTH"], 6, 2);

    $VAL["REG_BIRTH"] = $birth_y."-".$birth_m."-".$birth_d;
    $isBirth          = isAdult($birth_y, $birth_m, $birth_d);

    if (!$isBirth) {
      $RES["msg"] = "19세 미만은 회원가입을 할 수 없습니다.";
      echo json_encode($RES);
      exit;
    }

    // 성별 선택 여부
    if ($VAL["REG_GENDER"] == "") {
      $RES["msg"] = "성별을 선택해 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 연락처 확인
    if ($VAL["REG_PHONE"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($VAL["AUTH_PHONE"] != "true") {
      $RES["msg"] = "휴대폰 번호가 인증되지 않았습니다.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='{$VAL['REG_PHONE']}'");

    if ($info["cnt"] > 0) {
      $RES["msg"] = "이미 가입한 연락처입니다.";
      echo json_encode($RES);
      exit;
    }

    // 비밀번호 확인
    if ($VAL["REG_PWD_CHK"] == "") {
      $RES["msg"] = "비밀번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 비밀번호 일치여부
    if ($VAL["REG_PWD"] != $VAL["REG_PWD_CHK"]) {
      $RES["msg"] = "입력하신 비밀번호가 동일하지 않습니다.";
      echo json_encode($RES);
      exit;
    }

    // 비밀번호 검증
    if (!preg_match($pwRegexp, trim($VAL["REG_PWD_CHK"]))) {
      $RES["msg"] = "비밀번호는 8~20자의 영문과 숫자를 섞어주세요.";
      echo json_encode($RES);
      exit;
    }

    // 추천인 처리 SLK-677
    // 추천인 코드가 없을 경우에만 SESSION의 PARTNER_ID로 처리
    $RECOMID = trim($VAL["REG_RECOMID"]);
    $RECOMID_IS_EXIST = exec_partner_is_exist($RECOMID); // 추천인 아이디 여부 체크
    if ($RECOMID) {
      if ($RECOMID_IS_EXIST) {
        $_PARTNER_ID = $RECOMID;
      } else {
        $_PARTNER_ID = "";
      }
    } else {
      $_PARTNER_ID = $_SESSION["PARTNER_ID"];
    }

    $data = array();
    $data["mode"]         = "insert";
    $data["USER_ID"]      = trim($VAL["REG_ID"]);
    $data["EMAIL"]        = (trim($VAL["REG_EMAIL"]) == "parter@dummy.tmp" ? "" : trim($VAL["REG_EMAIL"]));
    $data["PASSWD"]       = trim($VAL["REG_PWD_CHK"]);
    $data["HP"]           = trim($VAL["REG_PHONE"]);
    $data["NAME"]         = trim($VAL["REG_NAME"]);
    $data["ENAME"]        = trim($VAL["REG_ENAME"]);
    $data["BIRTH"]        = trim($VAL["REG_BIRTH"]);
    $data["COUNTRY"]      = trim($VAL["REG_COUNTRY"]);
    $data["GENDER"]       = trim($VAL["REG_GENDER"]);
    $data["INIP"]         = $ip_address;
    $data["POINT"]        = 0;
    $data["TOTPRICE"]     = 0;
    $data["TOTCNT"]       = 0;
    $data["IS_MARKETING"] = (isset($VAL["REG_MARKETING"])) ? $VAL["REG_MARKETING"] : "N";
    $data["LOG_NO"]       = $db->get_data_one("SELECT MAX(LOG_NO) as LOG_NO FROM MEMBER_LOG") + 1;
    $data["PARTNER_ID"]   = $_PARTNER_ID;
    $data["LEVEL"]        = 0;
    $data["PATNER_NO"]    = 0;
    F_MEMBER_LOG($data);

    $data["MEMBER_NO"] = $db->get_data_one("SELECT MAX(MEMBER_NO) as MEMBER_NO FROM MEMBER") + 1;
    $data["REG_IP"]    = $ip_address;
    $data["KIOSK_NO"]  = 0;
    F_MEMBER($data);

    // 파트너(추천인)이 존재할 경우에만 처리
    if ($RECOMID_IS_EXIST) {
      // summary 처리
      exec_partner_summary($_PARTNER_ID, "REG");

      // 추천인 마일리지 처리
      exec_partner_recomment_mileage($_PARTNER_ID, trim($VAL["REG_ID"]));
    }

    // 가입 축하 SMS 발송
    $TEMPLET_NO  = 14;
    $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO = '{$TEMPLET_NO}'");
    $from        = $fromHP;
    $to          = $VAL["HP"];
    $CODESK      = "S";
    $MEMBER_NO   = $data["MEMBER_NO"];
    $MEMBER_NAME = $data["NAME"];
    $SUBJECT     = $SMSTEMPLET["SUBJECT"];
    $SENDMSG     = $SMSTEMPLET["CONTENT"];
    $SENDMSG     = str_replace("{USERID}", $data["USER_ID"], $SENDMSG);
    $SENDMSG     = addslashes($SENDMSG);
    $sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    $RES["error"] = False;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "idDuplicateCheck") {
    // 아이디 검사
    if ($VAL["id"] == "") {
      $RES["msg"] = "아이디를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $VAL['id'] = xss_clean($VAL['id']);

    // 아이디 확인
    $info = $db->get_data_one("
      SELECT SUM(CNT) AS CNT
      FROM ((
        SELECT COUNT(USER_ID) AS CNT
        FROM MEMBER
        WHERE USER_ID = '{$VAL['id']}'
      ) UNION ALL (
        SELECT COUNT(USER_ID) AS CNT
        FROM OUT_MEMBER
        WHERE USER_ID = '{$VAL['id']}')) AS A
    ");

    if ($info > 0) {
      $RES["msg"] = "사용할 수 없는 아이디입니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = False;
    $RES["msg"] = "사용 가능한 아이디입니다.";
    echo json_encode($RES);
    exit;
  }
?>
