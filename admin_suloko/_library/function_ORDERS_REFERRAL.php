<?php
// 정보 처리
function F_ORDERS($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO = '".$_L['ORDERS_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO ORDERS(
        ORDERS_NO,
        USER_ID,
        KIOSK_NO,
        AGENT_NO,
        AGENT_NO2,
        PATNER_NO,
        GOODS_NO,
        HP,
        EMAIL,
        PAYMENT,
        VAT,
        AUTHNUM,
        CARDCD,
        UNIQNUM,
        GUBUN,
        GUBUNO,
        DRAWNUM,
        PLAYDATE,
        GAMECNT,
        BALL1,
        BALL2,
        BALL3,
        BALL4,
        BALL5,
        IMG_PATH,
        IMG_YN,
        IMG_DATE,
        PRINT_YN,
        SIGN_YN,
        WIN_YN,
        WIN1,
        WIN2,
        WIN3,
        WIN4,
        WIN5,
        WIN_MONEY,
        WIN_MONEY_USD,
        INVOCE_YN,
        WIN_MONEY_YN,
        SELLER_GUBUN,
        DATE,
        REG_DATE
      ) VALUES (
        '".$_L['ORDERS_NO']."',
        '".$_L['USER_ID']."',
        '".$_L['KIOSK_NO']."',
        '".$_L['AGENT_NO']."',
        '".$_L['AGENT_NO2']."',
        '".$_L['PATNER_NO']."',
        '".$_L['GOODS_NO']."',
        '".$_L['HP']."',
        '".$_L['EMAIL']."',
        '".$_L['PAYMENT']."',
        '".$_L['VAT']."',
        '".$_L['AUTHNUM']."',
        '".$_L['CARDCD']."',
        '".$_L['UNIQNUM']."',
        '".$_L['GUBUN']."',
        '".$_L['GUBUNO']."',
        '".$_L['DRAWNUM']."',
        '".$_L['PLAYDATE']."',
        '".$_L['GAMECNT']."',
        '".$_L['BALL1']."',
        '".$_L['BALL2']."',
        '".$_L['BALL3']."',
        '".$_L['BALL4']."',
        '".$_L['BALL5']."',
        '".$_L['IMG_PATH']."',
        '".$_L['IMG_YN']."',
        '".$_L['IMG_DATE']."',
        '".$_L['PRINT_YN']."',
        '".$_L['SIGN_YN']."',
        '".$_L['WIN_YN']."',
        '".$_L['WIN1']."',
        '".$_L['WIN2']."',
        '".$_L['WIN3']."',
        '".$_L['WIN4']."',
        '".$_L['WIN5']."',
        '".$_L['WIN_MONEY']."',
        '".$_L['WIN_MONEY_USD']."',
        '".$_L['INVOCE_YN']."',
        'N',
        '".$_L['SELLER_GUBUN']."',
        '".$_L['DATE']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '".$_L['file1']."',";

    $query = "
      UPDATE ORDERS SET
        ".$add_query."
        USER_ID       = '".$_L['USER_ID']."',
        KIOSK_NO      = '".$_L['KIOSK_NO']."',
        AGENT_NO      = '".$_L['AGENT_NO']."',
        AGENT_NO2     = '".$_L['AGENT_NO2']."',
        PATNER_NO     = '".$_L['PATNER_NO']."',
        GOODS_NO      = '".$_L['GOODS_NO']."',
        HP            = '".$_L['HP']."',
        EMAIL         = '".$_L['EMAIL']."',
        PAYMENT       = '".$_L['PAYMENT']."',
        VAT           = '".$_L['VAT']."',
        AUTHNUM       = '".$_L['AUTHNUM']."',
        CARDCD        = '".$_L['CARDCD']."',
        UNIQNUM       = '".$_L['UNIQNUM']."',
        GUBUN         = '".$_L['GUBUN']."',
        GUBUNO        = '".$_L['GUBUNO']."',
        DRAWNUM       = '".$_L['DRAWNUM']."',
        PLAYDATE      = '".$_L['PLAYDATE']."',
        GAMECNT       = '".$_L['GAMECNT']."',
        BALL1         = '".$_L['BALL1']."',
        BALL2         = '".$_L['BALL2']."',
        BALL3         = '".$_L['BALL3']."',
        BALL4         = '".$_L['BALL4']."',
        BALL5         = '".$_L['BALL5']."',
        IMG_PATH      = '".$_L['IMG_PATH']."',
        IMG_YN        = '".$_L['IMG_YN']."',
        IMG_DATE      = '".$_L['IMG_DATE']."',
        PRINT_YN      = '".$_L['PRINT_YN']."',
        SIGN_YN       = '".$_L['SIGN_YN']."',
        WIN_YN        = '".$_L['WIN_YN']."',
        WIN1          = '".$_L['WIN1']."',
        WIN2          = '".$_L['WIN2']."',
        WIN3          = '".$_L['WIN3']."',
        WIN4          = '".$_L['WIN4']."',
        WIN5          = '".$_L['WIN5']."',
        WIN_MONEY     = '".$_L['WIN_MONEY']."',
        WIN_MONEY_USD = '".$_L['WIN_MONEY_USD']."',
        INVOCE_YN     = '".$_L['INVOCE_YN']."',
        WIN_MONEY_YN  = '".$_L['WIN_MONEY_YN']."',
        SELLER_GUBUN  = '".$_L['SELLER_GUBUN']."',
        DATE          = '".$_L['DATE']."'
      WHERE
        ORDERS_NO = '".$_L['ORDERS_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM ORDERS WHERE ORDERS_NO = '".$_L['ORDERS_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_ORDERS_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
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
    $order_query = " ORDER BY ORDERS_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM ORDERS $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *,
      (SELECT COMPANY FROM AGENT WHERE AGENT_NO = ORDERS.AGENT_NO LIMIT 1) AS COMPANY,
      (SELECT NAME FROM MEMBER WHERE USER_ID = ORDERS.USER_ID LIMIT 1) AS NAME
    FROM
      ORDERS
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['ORDERS_NO'])) {
    $list['count'] = count($list['ORDERS_NO']);
  }
  return $list;
}

// 목록 불러오기
function F_ORDERS_MEMBER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
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
    $order_query = " ORDER BY ORDERS_NO DESC ";
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
  $query = "
    SELECT
      count(*)
    FROM
      ORDERS AS o
        LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
  ";
  $page_info['total'] = $db->get_data_one($query);

  // 위의 조건에 따라 목록 가져오기
  $query  = "
    SELECT
      o.*,
      (SELECT COMPANY FROM AGENT WHERE AGENT_NO = o.AGENT_NO LIMIT 1) AS COMPANY,
      m.NAME AS NAME
    FROM
      ORDERS AS o
        LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['ORDERS_NO'])) {
    $list['count'] = count($list['ORDERS_NO']);
  }

  // $page_info['total'] = count($list['ORDERS_NO']);
  $list['total'] = $page_info['total'];
  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력

  return $list;
}

// 목록 불러오기
function F_ORDERS_MEMBER2_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
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
    $order_query = " ORDER BY ORDERS_NO DESC ";
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
  $query = "
    SELECT
      count(*)
    FROM
      ORDERS AS o
        LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
  ";
  $page_info['total'] = $db->get_data_one($query);

  // 위의 조건에 따라 목록 가져오기
  $query  = "
    SELECT
      o.*,
      (SELECT COMPANY FROM AGENT WHERE AGENT_NO = o.AGENT_NO LIMIT 1) AS COMPANY ,
      m.NAME AS NAME
    FROM
      ORDERS AS o
        LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['ORDERS_NO'])) {
    $list['count'] = count($list['ORDERS_NO']);
  }

  // $page_info['total'] = count($list['ORDERS_NO']);
  $list['total'] = $page_info['total'];
  $list['page_string'] = print_page_num1($page_info); // 페이지 번호 출력

  return $list;
}
?>
