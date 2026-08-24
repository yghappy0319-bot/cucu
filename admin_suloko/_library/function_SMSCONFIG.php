<?php
// 정보 처리
function F_SMSCONFIG($_L) {
  global $db;

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM SMSCONFIG WHERE SMS_NO = '".$_L['SMS_NO']."'");
    $info = F_strip_slashes($info);
    return  $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    // 홈페이지 대표번호 선택시 전체 초기화
    if ($_L['HOMEPAGE_USE'] == "Y") {
      $query = "UPDATE SMSCONFIG SET HOMEPAGE_USE = 'N'";
      $db->query($query);
    }

    $query = "
      INSERT INTO SMSCONFIG(
        SENDER,
        HP,
        HOMEPAGE_USE,
        IS_USE
      ) VALUES (
        '".$_L['SENDER']."',
        '".$_L['HP']."',
        '".$_L['HOMEPAGE_USE']."',
        '".$_L['IS_USE']."'
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    // 홈페이지 대표번호 선택시 전체 초기화
    if ($_L['HOMEPAGE_USE'] == "Y") {
      $query = "UPDATE SMSCONFIG SET HOMEPAGE_USE = 'N'";
      $db->query($query);
    }

    $query = "
      UPDATE SMSCONFIG SET
        SENDER       = '".$_L['SENDER']."',
        HP           = '".$_L['HP']."',
        HOMEPAGE_USE = '".$_L['HOMEPAGE_USE']."',
        IS_USE       = '".$_L['IS_USE']."',
        REG_DATE     = NOW()
      WHERE
        SMS_NO = '".$_L['SMS_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM SMSCONFIG WHERE SMS_NO = '".$_L['SMS_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_SMSCONFIG_list($_L) {
  global $db;

  $add_query = "";

  if ($_L['add_query']) {
    $add_query .= "WHERE " . stripslashes($_L['add_query']);
  }

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      SMSCONFIG
    ".$add_query."
    ORDER BY REG_DATE DESC
  ";
  $list = $db->get_list($query);

  return $list;
}
?>
