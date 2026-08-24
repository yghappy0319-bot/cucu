<?php
// 정보 처리
function F_CASH($_L) {
  global $db;

  $add_query = "";

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO  = '".$_L['CASH_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);
  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO CASH(
        MEMBER_NO,
        USER_ID,
        USER_NAME,
        CASH,
        IPOINT,
        PRICE,
        TID,
        ITEMNAME,
        CARDNAME,
        CARDNO,
        AUTHNUM,
        CARDCD,
        SELLER_GUBUN,
        IS_USE,
        PATNER_ID,
        REG_DATE,
        CON_REG_DATE,
        IN_IP,
        PARTNER_ID,
        QUOTA,
        CASH_CNT,
        PG_ID
      ) VALUES (
        '".$_L['MEMBER_NO']."',
        '".$_L['USER_ID']."',
        '".$_L['USER_NAME']."',
        '".$_L['CASH']."',
        '".$_L['IPOINT']."',
        '".$_L['PRICE']."',
        '".$_L['TID']."',
        '".$_L['ITEMNAME']."',
        '".$_L['CARDNAME']."',
        '".$_L['CARDNO']."',
        '".$_L['AUTHNUM']."',
        '".$_L['CARDCD']."',
        '".$_L['SELLER_GUBUN']."',
        '".$_L['IS_USE']."',
        '".$_L['PATNER_ID']."',
        NOW(),
        ".(($_L['CARDNAME'] == "무통장") ? "NULL" : "NOW()").",
        '".$_L['IN_IP']."',
        '".$_L['PARTNER_ID']."',
        '".$_L['QUOTA']."',
        '".$_L['CASH_CNT']."',
        '".$_L['PG_ID']."'
      )
    ";
    $result = $db->query_id($query);
    return $result;
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1  = '".$_L['file1']."',";

    $query  = "
      UPDATE
        CASH
      SET
        ".$add_query."
        MEMBER_NO    = '".$_L['MEMBER_NO']."',
        CASH         = '".$_L['CASH']."',
        IPOINT       = '".$_L['IPOINT']."',
        PRICE        = '".$_L['PRICE']."',
        TID          = '".$_L['TID']."',
        ITEMNAME     = '".$_L['ITEMNAME']."',
        CARDNAME     = '".$_L['CARDNAME']."',
        CARDNO       = '".$_L['CARDNO']."',
        AUTHNUM      = '".$_L['AUTHNUM']."',
        CARDCD       = '".$_L['CARDCD']."',
        SELLER_GUBUN = '".$_L['SELLER_GUBUN']."'
      WHERE
        CASH_NO = '".$_L['CASH_NO']."'
    ";
    $db->query($query);
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM CASH WHERE CASH_NO = '".$_L['CASH_NO']."'";
    $db->query($query);
  }

  if ($_L['mode'] == 'bank_cancel') {
    $query = "
      UPDATE CASH SET
        IS_USE = 'C',
        STAFF_ID = '{$_L['USER_ID']}',
        CANCAL_DATE = NOW()
      WHERE
        CASH_NO = '{$_L['CASH_NO']}' AND IS_USE = 'N'
    ";
    $db->query($query);
  }

  if ($_L['mode'] == 'card_cancel') {
    $query = "
      UPDATE CASH SET
        IS_USE = 'C',
        STAFF_ID = '{$_L['USER_ID']}',
        CANCAL_DATE = NOW()
      WHERE
        CASH_NO = '{$_L['CASH_NO']}' AND USER_ID = '{$_L['USER_ID']}' AND CARDNAME = '카드' AND IS_USE = ''
    ";
    $db->query($query);
  }
}

// 중복방지를 위해 입력 직전 동일값 데이터 유무 확인
function F_CASH_insert_check($_L) {
  global $db;

  $_L = F_add_slashes($_L);

  $query = "
    SELECT
      COUNT(CASH_NO) AS CNT
    FROM
      CASH
    WHERE
      MEMBER_NO = '".$_L['MEMBER_NO']."' AND
      USER_ID = '".$_L['USER_ID']."' AND
      CASH = '".$_L['CASH']."' AND
      IPOINT = '".$_L['IPOINT']."' AND
      PRICE = '".$_L['PRICE']."' AND
      TID = '".$_L['TID']."' AND
      ITEMNAME = '".$_L['ITEMNAME']."' AND
      CARDNAME = '".$_L['CARDNAME']."' AND
      CARDNO = '".$_L['CARDNO']."' AND
      AUTHNUM = '".$_L['AUTHNUM']."' AND
      CARDCD = '".$_L['CARDCD']."' AND
      SELLER_GUBUN = '".$_L['SELLER_GUBUN']."' AND
      IS_USE = '".$_L['IS_USE']."' AND
      PATNER_ID = '".$_L['PATNER_ID']."' AND
      REG_DATE > DATE_ADD(NOW(), INTERVAL -".$_L['CHK_SECOND']." SECOND)
  ";
  $info = $db->get_data($query);
  return $info;
}

// 목록 불러오기
function F_CASH_list($_L) {
  global $db;

  $add_query = " AND IS_USE != 'D'";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE '%".$_L['find_text']."%' ";
  }
  if ($_L['add_query']) {
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
    $order_query = " ORDER BY c.CASH_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("
    SELECT
      count(*)
    FROM
      CASH AS c
        LEFT JOIN
      CASH_RECEIPT AS r ON r.CASH_NO = c.CASH_NO
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      c.*,
      r.STATUS AS RECEIPT_ST
    FROM
      CASH AS c
        LEFT JOIN
      CASH_RECEIPT AS r ON r.CASH_NO = c.CASH_NO
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['CASH_NO'])) {
    $list['count'] = count($list['CASH_NO']);
  }
  return $list;
}

//  목록 불러오기
function F_CASH_MEMBER_list($_L) {
  global $db;

  $add_query = " AND IS_USE != 'D'";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE '%".$_L['find_text']."%' ";
  }
  if ($_L['add_query']) {
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
    $order_query = " ORDER BY c.REG_DATE DESC ";
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
  $page_info['total'] = $db->get_data_one("
    SELECT
      count(*)
    FROM
      CASH AS c
        LEFT JOIN
      MEMBER AS m ON m.USER_ID=c.USER_ID
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      c.*,
      m.NAME AS NAME,
      m.HP AS HP
    FROM
      CASH AS c
        LEFT JOIN
      MEMBER AS m ON m.USER_ID=c.USER_ID
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['CASH_NO'])) {
    $list['count'] = count($list['CASH_NO']);
  }
  return $list;
}
?>
