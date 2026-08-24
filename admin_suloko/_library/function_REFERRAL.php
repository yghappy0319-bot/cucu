<?php
// 정보 처리
function F_PATNER($_L) {
  global $db;

  $add_query = "";

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM REFERRAL_PARTNER WHERE Idx = '".$_L['Idx']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO REFERRAL_PARTNER(
        Idx,
        USER_ID,
        PASSWD,
        COMPANY,
        BIZNUM,
        ADDRESS,
        QRCODE,
        CEO,
        HP,
        TEL,
        LAN,
        LOT,
        BANK,
        BANK2,
        BANK3,
        SDATE,
        EDATE,
        STOPYN,
        JUMP1,
        JUMP2,
        FILE1,
        REG_DATE
      ) VALUES (
        '".$_L['Idx']."',
        '".$_L['USER_ID']."',
        '".$_L['PASSWD']."',
        '".$_L['COMPANY']."',
        '".$_L['BIZNUM']."',
        '".$_L['ADDRESS']."',
        '".$_L['QRCODE']."',
        '".$_L['CEO']."',
        '".$_L['HP']."',
        '".$_L['TEL']."',
        '".$_L['LAN']."',
        '".$_L['LOT']."',
        '".$_L['BANK']."',
        '".$_L['BANK2']."',
        '".$_L['BANK3']."',
        '".$_L['SDATE']."',
        '".$_L['EDATE']."',
        '".$_L['STOPYN']."',
        '".$_L['JUMP1']."',
        '".$_L['JUMP2']."',
        '".$_L['FILE1']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if (isset($_L['FILE1'])) $add_query = "FILE1 = '".$_L['FILE1']."',";
    if (isset($_L['QRCODE'])) $add_query .= "QRCODE = '".$_L['QRCODE']."',";

    $query = "
      UPDATE REFERRAL_PARTNER SET
        ".$add_query."
        USER_ID = '".$_L['USER_ID']."',
        PASSWD  = '".$_L['PASSWD']."',
        COMPANY = '".$_L['COMPANY']."',
        BIZNUM  = '".$_L['BIZNUM']."',
        ADDRESS = '".$_L['ADDRESS']."',
        CEO     = '".$_L['CEO']."',
        HP      = '".$_L['HP']."',
        TEL     = '".$_L['TEL']."',
        LAN     = '".$_L['LAN']."',
        LOT     = '".$_L['LOT']."',
        BANK    = '".$_L['BANK']."',
        BANK2   = '".$_L['BANK2']."',
        BANK3   = '".$_L['BANK3']."',
        SDATE   = '".$_L['SDATE']."',
        EDATE   = '".$_L['EDATE']."',
        JUMP1   = '".$_L['JUMP1']."',
        JUMP2   = '".$_L['JUMP2']."'
      WHERE
        Idx = '".$_L['Idx']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM REFERRAL_PARTNER WHERE Idx = '".$_L['Idx']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_REF_PATNER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
  }
  if (isset($_L['add_query'])) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '".$_L['s_area']."' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY ".$_L['order']." ";
  } else {
    $order_query = " ORDER BY Idx DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = " WHERE ".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM REFERRAL_PARTNER $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      REFERRAL_PARTNER
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['Idx'])) {
    $list['count'] = count($list['Idx']);
  }
  return $list;
}
?>
