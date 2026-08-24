<?php
// 정보 처리
function F_CASH($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO = '{$_L['CASH_NO']}' ");
    $info = F_strip_slashes($info);
    return  $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO CASH(
        CASH_NO,
        MEMBER_NO,
        USER_ID,
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
        IN_IP,
        REG_DATE
      ) VALUES (
        '{$_L['CASH_NO']}',
        '{$_L['MEMBER_NO']}',
        '{$_L['USER_ID']}',
        '{$_L['CASH']}',
        '{$_L['IPOINT']}',
        '{$_L['PRICE']}',
        '{$_L['TID']}',
        '{$_L['ITEMNAME']}',
        '{$_L['CARDNAME']}',
        '{$_L['CARDNO']}',
        '{$_L['AUTHNUM']}',
        '{$_L['CARDCD']}',
        '{$_L['SELLER_GUBUN']}',
        '{$_L['IS_USE']}',
        '{$_L['IN_IP']}',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '{$_L['file1']}',";
    $query = "
      UPDATE CASH SET
        {$add_query}
        MEMBER_NO = '{$_L['MEMBER_NO']}',
        CASH = '{$_L['CASH']}',
        IPOINT = '{$_L['IPOINT']}',
        PRICE = '{$_L['PRICE']}',
        TID = '{$_L['TID']}',
        ITEMNAME = '{$_L['ITEMNAME']}',
        CARDNAME = '{$_L['CARDNAME']}',
        CARDNO = '{$_L['CARDNO']}',
        AUTHNUM = '{$_L['AUTHNUM']}',
        CARDCD = '{$_L['CARDCD']}',
        SELLER_GUBUN = '{$_L['SELLER_GUBUN']}'
      WHERE
        CASH_NO = '{$_L['CASH_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM CASH WHERE CASH_NO = '{$_L['CASH_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_CASH_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬 기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY {$_L['order']} ";
  } else {
    $order_query = " ORDER BY CASH_NO DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  // 카드결제만 필터링 (2023-01-25)
  $where_query = " WHERE CARDNAME != '무통장'";

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= " AND ".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM CASH $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      CASH
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
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

// 목록 불러오기
function F_CASH_MEMBER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬 기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']} ";
  } else {
    $order_query = "ORDER BY c.CASH_NO DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  // 카드결제만 필터링 (2023-01-25)
  $where_query = " WHERE CARDNAME != '무통장' AND c.IS_USE != 'D'";

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= " AND".substr($add_query, 4, $querylen-4);
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
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['page_string_v2'] = print_page_num1($page_info); // 페이지 번호 출력 (modal)
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['CASH_NO'])) {
    $list['count'] = count($list['CASH_NO']);
  }
  return $list;
}

// 목록 불러오기 - 무통장 입금 2023-01-25
function F_CASH_MEMBER_list_BANK($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L["order"] != null) {
    $order_query = "ORDER BY {$_L['order']}";
  } else {
    $order_query = "ORDER BY REG_DATE DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  // 무통장 필터링 (2023-01-25) 삭제 건 제외
  $where_query = "WHERE CARDNAME='무통장' AND IS_USE!='D'";

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= " AND".substr($add_query, 4, $querylen-4);
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
        LEFT JOIN
      CASH_BANK_MEMO AS b ON b.CASH_NO=c.CASH_NO
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      c.*,
      m.NAME AS NAME,
      m.HP AS HP,
      m.MEMBER_NO AS MEMBER_NO,
      b.MEMO, b.STATUS
    FROM
      CASH AS c
        LEFT JOIN
      MEMBER AS m ON m.USER_ID=c.USER_ID
        LEFT JOIN
      CASH_BANK_MEMO AS b ON b.CASH_NO=c.CASH_NO
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['page_string_v2'] = print_page_num1($page_info); // 페이지 번호 출력 (modal)
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['CASH_NO'])) {
    $list['count'] = count($list['CASH_NO']);
  }
  return $list;
}

// 목록 불러오기 - 무통장 자동입금 로그
function F_CASH_MEMBER_list_BANK_SMS($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L["order"] != null) {
    $order_query = "ORDER BY {$_L['order']}";
  } else {
    $order_query = "ORDER BY REG_DATE DESC";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= "WHERE".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM CASH_SMS_LOG $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      idx,
      IFNULL((SELECT REG_DATE FROM CASH WHERE CASH_NO = B.CASH_NO), '-') AS CASH_REG_DATE,
      SMS_DATE,
      (SELECT PRICE FROM CASH WHERE CASH_NO = B.CASH_NO) AS CASH_PRICE,
      SMS_PRICE,
      SMS_NAME,
      SMS_TXT,
      MEMBER_NO,
      USER_ID,
      (SELECT HP FROM MEMBER WHERE USER_ID = B.USER_ID) AS HP,
      IFNULL(USER_ID, '-'),
      STATUS,
      REG_DATE,
      CHG_REG_DATE,
      CHG_STAFF_ID,
      MSG_SENT_CNT,
      MSG_SENT_MEMBER,
      IFNULL((SELECT IDX FROM CASH_SMS_MEMO WHERE SMS_IDX = B.idx), '-') AS SMS_ADMIN_MEMO
    FROM
      CASH_SMS_LOG AS B
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";

  //echo $query;
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['REG_DATE'])) {
    $list['count'] = count($list['REG_DATE']);
  }
  return $list;
}
?>
