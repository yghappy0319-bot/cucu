<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_MEMBER.php";

  $VAL = $_POST;

  $user_phone = xss_clean($VAL['USER_PHONE']);

  if ($VAL["COUNTRY"] == "") {
    alert_print("국적을 선택해 주세요.");
    history_go(-1);
    exit;
  }

  // 이름 검사
  if ($VAL["COUNTRY"] == "kr") {
    if (!preg_match($koreanRegexp, trim($VAL["NAME"]))) {
      alert_print("이름은 한글로 입력해 주세요.");
      history_go(-1);
      exit;
    }
  } else {
    if (!preg_match($englishRegexp, trim($VAL["NAME"]))) {
      alert_print("외국인은 이름을 영어로 입력해 주세요.");
      history_go(-1);
      exit;
    }
  }

  // 생년월일 확인
  if ($VAL["BIRTH"] == "") {
    alert_print("생년월일을 입력해 주세요.");
    history_go(-1);
    exit;
  }

  if (strlen($VAL["BIRTH"]) != 8) {
    alert_print("생년월일을 정확히 입력해 주세요.");
    history_go(-1);
    exit;
  }

  $birth_y = substr($VAL["BIRTH"], 0, 4);
  $birth_m = substr($VAL["BIRTH"], 4, 2);
  $birth_d = substr($VAL["BIRTH"], 6, 2);

  $VAL["BIRTH"] = $birth_y."-".$birth_m."-".$birth_d;
  $isBirth      = isAdult($birth_y, $birth_m, $birth_d);

  if (!$isBirth) {
    alert_print("19세 미만은 복권을 구매하거나 당첨금을 수령할 수 없습니다.");
    history_go(-1);
    exit;
  }

  if ($VAL["GENDER"] == "") {
    alert_print("성별을 선택해 주세요.");
    history_go(-1);
    exit;
  }

  // 이메일 검사
  if ($VAL["EMAIL"] == "") {
    alert_print("이메일을 입력해 주세요.");
    history_go(-1);
    exit;
  }

  if ($user_phone == '') {
    alert_print("연락처를 정확히 입력해 주세요.");
    history_go(-1);
    exit;
  }

  if ($VAL["AUTH_PHONE"] != "true") {
    alert_print("휴대폰 번호가 인증되지 않았습니다.");
    history_go(-1);
    exit;
  }

  $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP = '{$user_phone}' AND USER_ID != '{$M_login['user_id']}'");
  if ($info > 0) {
    alert_print("이미 가입한 연락처입니다.");
    meta_go('my-info.html');
    exit;
  }

  // 탈퇴회원 휴대폰 여부
  $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM OUT_MEMBER WHERE HP = '{$user_phone}'");
  if ($info > 0) {
    alert_print("등록할 수 없는 휴대폰 번호입니다.");
    history_go(-1);
    exit;
  }

  // 비밀번호 변경 시
  if ($VAL['PWD_CHK']) {
    // 비밀번호 확인
    if ($VAL["PWD_CHK"] == "") {
      alert_print("비밀번호를 정확히 입력해 주세요.");
      history_go(-1);
      exit;
    }

    // 비밀번호 일치여부
    if ($VAL["PWD"] != $VAL["PWD_CHK"]) {
      alert_print("입력하신 비밀번호가 동일하지 않습니다.");
      history_go(-1);
      exit;
    }

    // 비밀번호 검증
    if (!preg_match($pwRegexp, trim($VAL["PWD_CHK"]))) {
      alert_print("비밀번호는 8~20자의 영문과 숫자를 섞어주세요.");
      history_go(-1);
      exit;
    }
  }

  $member = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}'");

  $data = array();
  $data['mode']         = "update";
  $data['NAME']         = xss_clean($VAL['NAME']);
  $data['BIRTH']        = xss_clean($VAL['BIRTH']);
  $data['PASSWD']       = $VAL['PWD_CHK'];
  $data['HP']           = $user_phone;
  $data['COUNTRY']      = xss_clean($VAL['COUNTRY']);
  $data['GENDER']       = xss_clean($VAL['GENDER']);
  $data['ENAME']        = xss_clean($VAL['ENAME']);
  $data['EMAIL']        = xss_clean($VAL['EMAIL']);
  $data['IS_MARKETING'] = empty($VAL['IS_MARKETING']) ? 'N' : xss_clean($VAL['IS_MARKETING']);
  $data['MEMBER_NO']    = $member["MEMBER_NO"];
  F_MEMBER($data);

  // 변경 이력 저장
  $changes = array();
  foreach ($data as $key => $value) {
    if ($key === "PASSWD") {
      if (!empty($VAL['PWD_CHK'])) {
        $newPasswordHash = $db->get_data_one("SELECT PASSWORD('{$value}') AS password_hash");

        if ($member['PASSWD'] != $newPasswordHash) {
          $changes[$key] = array("old" => "******", "new" => "******");
        }
      }

    } else if ($key !== "mode" && $key !== "MEMBER_NO") {
      if (isset($member[$key]) && $member[$key] != $value) {
        $changes[$key] = array("old" => $member[$key], "new" => $value);
      }
    }
  }

  if (!empty($changes)) {
    foreach ($changes as $field => $change) {
      $sql = "
        INSERT INTO MEMBER_CHANGE_LOG (
          USER_ID, FIELD, OLD_VALUE, NEW_VALUE
        ) VALUES (
          '{$M_login['user_id']}', '{$field}', '{$change['old']}', '{$change['new']}'
        )
      ";
      $db->query($sql);
    }
  }

  alert_print("회원 정보를 수정했습니다.");

  // session update
  $M_login['name'] = $data['NAME'];
  $M_login['hp']   = $data['HP'];
  session_register("M_login");

  meta_go("/contents/mypage/mypage.html");
?>
