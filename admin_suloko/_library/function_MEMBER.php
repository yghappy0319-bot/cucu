<?php
// 정보 처리
function F_MEMBER($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("
      SELECT
        *,
        (select COMPANY from AGENT where USER_ID = MEMBER.AGENT_ID limit 1) as COMPANY
      FROM
        MEMBER
      WHERE
        MEMBER_NO = '{$_L['MEMBER_NO']}'
    ");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO MEMBER(
        MEMBER_NO,
        USER_ID,
        PASSWD,
        HP,
        NAME,
        BIRTH,
        EMAIL,
        POINT,
        CASH,
        IPOINT,
        LEVEL,
        KIOSK_NO,
        AGENT_ID,
        TOTPRICE,
        TOTCNT,
        AUTH,
        TOKEN,
        EASY,
        IS_MARKETING,
        BANK1,
        BANK2,
        BANK3,
        REG_DATE,
        LASTDATE
      ) VALUES (
        '{$_L['MEMBER_NO']}',
        '{$_L['USER_ID']}',
        PASSWORD('{$_L['PASSWD']}'),
        '{$_L['HP']}',
        '{$_L['NAME']}',
        '{$_L['BIRTH']}',
        '{$_L['EMAIL']}',
        '0',
        '0',
        '0',
        '1',
        '{$_L['KIOSK_NO']}',
        '{$_L['AGENT_ID']}',
        '{$_L['TOTPRICE']}',
        '{$_L['TOTCNT']}',
        '{$_L['AUTH']}',
        '{$_L['TOKEN']}',
        '{$_L['EASY']}',
        '{$_L['IS_MARKETING']}',
        '{$_L['BANK1']}',
        '{$_L['BANK2']}',
        '{$_L['BANK3']}',
        NOW(),
        ''
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['PASSWD']) $add_query .= "PASSWD = PASSWORD('{$_L['PASSWD']}'),";
    $query = "
      UPDATE
        MEMBER
      SET
        {$add_query}
        HP = '{$_L['HP']}',
        NAME = '{$_L['NAME']}',
        BIRTH = '{$_L['BIRTH']}',
        EASY = '{$_L['EASY']}'
      WHERE
        MEMBER_NO = '{$_L['MEMBER_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM MEMBER WHERE MEMBER_NO = '{$_L['MEMBER_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_MEMBER_list($_L) {
  global $db, $S_login;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    if ($S_login['level'] == 2) {
      if (strstr($_L['find_text'], '-') == false) {
        $add_query .= " AND replace({$_L['find_object']}, '-', '') = '{$_L['find_text']}' ";
      } else {
        $add_query .= " AND replace({$_L['find_object']}, ' ', '') = '{$_L['find_text']}' ";
      }
    } else {
      if (strstr($_L['find_text'], '-') == false) {
        $add_query .= " AND replace({$_L['find_object']}, '-', '') LIKE '%{$_L['find_text']}%' ";
      } else {
        $add_query .= " AND replace({$_L['find_object']}, ' ', '') LIKE '%{$_L['find_text']}%' ";
      }
    }
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

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']} ";
  } else {
    $order_query = "ORDER BY M.MEMBER_NO DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = "WHERE".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("
    SELECT count(*)
    FROM
      MEMBER AS M
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      M.MEMBER_NO AS MEMBER_NO,
      M.USER_ID AS USER_ID,
      M.NAME AS NAME,
      M.COUNTRY AS COUNTRY,
      M.GENDER AS GENDER,
      M.HP AS HP,
      M.LEVEL AS LEVEL,
      M.IS_MARKETING AS IS_MARKETING,
      M.CASH AS CASH,
      M.WINCASH AS WINCASH,
      IFNULL(POINT, 0) AS POINT,
      IFNULL(IPOINT, 0) AS IPOINT,
      M.REG_DATE AS REG_DATE,
      IFNULL(M.TOTCNT, 0) AS TOTCNT,
      IFNULL((SELECT SUM(PRICE) FROM CASH WHERE IS_USE = 'Y' AND USER_ID = M.USER_ID), 0) AS SUM_PRICE,
      IFNULL((SELECT SUM(CASH) FROM CASH WHERE IS_USE = 'Y' AND USER_ID = M.USER_ID), 0) AS SUM_CASH,
      IFNULL((SELECT SUM(WIN_MONEY) FROM ORDERS where USER_ID = M.USER_ID), 0) AS SUM_WIN_MONEY,
      IFNULL((SELECT SUM(CASH) FROM ORDERS where USER_ID = M.USER_ID), 0) AS SUM_ORDERS,
      M.BIRTH AS BIRTH,
      M.TOKEN AS IS_NOTIFICATION,
      M.LASTDATE AS LASTDATE
    FROM
      MEMBER AS M
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['MEMBER_NO'])) {
    $list['count'] = count($list['MEMBER_NO']);
  }
  return $list;
}

// 목록 불러오기
function F_MEMBER_JOIN_CASH_list($_L) {
  global $db, $S_login;

  $add_query = "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    if ($S_login['level'] == 2) {
      if (strstr($_L['find_text'], '-') == false) {
        $add_query .= " AND replace({$_L['find_object']}, '-', '') = '{$_L['find_text']}' ";
      } else {
        $add_query .= " AND replace({$_L['find_object']}, ' ', '') = '{$_L['find_text']}' ";
      }
    } else {
      if (strstr($_L['find_text'], '-') == false) {
        $add_query .= " AND replace({$_L['find_object']}, '-', '') LIKE '%{$_L['find_text']}%' ";
      } else {
        $add_query .= " AND replace({$_L['find_object']}, ' ', '') LIKE '%{$_L['find_text']}%' ";
      }
    }
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']} ";
  } else {
    $order_query = "ORDER BY M.MEMBER_NO DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = "WHERE".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("
    SELECT count(*)
    FROM
      MEMBER AS M
      LEFT OUTER JOIN (SELECT USER_ID AS CASE_USERID, MAX(CON_REG_DATE) AS CON_REG_DATE FROM CASH WHERE IS_USE = 'Y' GROUP BY USER_ID) C
        ON M.USER_ID = C.CASE_USERID
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      M.MEMBER_NO AS MEMBER_NO,
      M.USER_ID AS USER_ID,
      M.NAME AS NAME,
      M.HP AS HP,
      M.IS_MARKETING AS IS_MARKETING,
      M.TOKEN AS IS_NOTIFICATION,
      M.LASTDATE AS LASTDATE,
      C.CON_REG_DATE AS CASHDATE
    FROM
      MEMBER AS M
      LEFT OUTER JOIN (SELECT USER_ID AS CASE_USERID, MAX(CON_REG_DATE) AS CON_REG_DATE FROM CASH WHERE IS_USE = 'Y' GROUP BY USER_ID) C
        ON M.USER_ID = C.CASE_USERID
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['MEMBER_NO'])) {
    $list['count'] = count($list['MEMBER_NO']);
  }
  return $list;
}

// 목록 불러오기
function F_MEMBER_with_UM_PARTNER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND replace(".$_L['find_object'].", ' ', '') LIKE '%{$_L['find_text']}%' ";
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

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY {$_L['order']} ";
  } else {
    $order_query = " ORDER BY MEMBER_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM MEMBER M $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      M.*,
      UP.NAME AS PARTNER_NAME,
      C.CASH AS CASH,
      C.PRICE AS PRICE,
      C.REG_DATE AS CASH_DATE
    FROM
      MEMBER M
        INNER JOIN UM_PARTNER UP ON M.PARTNER_ID = UP.PARTNER_ID
        LEFT OUTER JOIN (SELECT USER_ID,CASH,PRICE,REG_DATE,PARTNER_ID FROM CASH WHERE LENGTH(PARTNER_ID) > 0) C ON M.USER_ID = C.USER_ID
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['MEMBER_NO'])) {
    $list['count'] = count($list['MEMBER_NO']);
  }
  return $list;
}
?>
