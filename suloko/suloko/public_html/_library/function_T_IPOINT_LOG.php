<?php
// 정보 처리
function F_T_IPOINT_LOG($_L) {
  global $db;

  $add_query = "";

  if (isset($_L['price'])) {
    $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);
  }

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM T_IPOINT_LOG WHERE LOG_NO = '{$_L['LOG_NO']}'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  $field = "LOG_NO,ORDERS_NO,WINNING_NO,CASH_LOG_NO,IPOINT,N_IPOINT,O_IPOINT,USER_ID,MEMO,STATUS,REG_DATE";
  $f_arr = explode(",", $field);

  foreach ($f_arr as $v) {
    if (!isset($_L[$v])) $_L[$v] = "";
  }

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO T_IPOINT_LOG(
        LOG_NO,
        ORDERS_NO,
        WINNING_NO,
        CASH_LOG_NO,
        IPOINT,
        N_IPOINT,
        O_IPOINT,
        USER_ID,
        MEMO,
        STATUS,
        REG_DATE
      ) VALUES (
        '{$_L['LOG_NO']}',
        '{$_L['ORDERS_NO']}',
        '{$_L['WINNING_NO']}',
        '{$_L['CASH_LOG_NO']}',
        '{$_L['IPOINT']}',
        '{$_L['N_IPOINT']}',
        '{$_L['O_IPOINT']}',
        '{$_L['USER_ID']}',
        '{$_L['MEMO']}',
        '{$_L['STATUS']}',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['FILE1'] != '') $add_query .= "FILE1 = '{$_L['FILE1']}',";
    if ($_L['FILE2'] != '') $add_query .= "FILE2 = '{$_L['FILE2']}',";

    $query = "
      UPDATE T_IPOINT_LOG SET
        {$add_query}
        ORDERS_NO   = '{$_L['ORDERS_NO']}',
        WINNING_NO  = '{$_L['WINNING_NO']}',
        CASH_LOG_NO = '{$_L['CASH_LOG_NO']}',
        IPOINT      = '{$_L['IPOINT']}',
        N_IPOINT    = '{$_L['N_IPOINT']}',
        O_IPOINT    = '{$_L['O_IPOINT']}',
        USER_ID     = '{$_L['USER_ID']}',
        MEMO        = '{$_L['MEMO']}',
        STATUS      = '{$_L['STATUS']}'
      WHERE
        LOG_NO = '{$_L['LOG_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM T_IPOINT_LOG WHERE LOG_NO = '{$_L['LOG_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_T_IPOINT_LOG_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%'";
  }
  if (isset($_L['add_query'])) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}'";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY {$_L['order']}";
  } else {
    $order_query = " ORDER BY P.LOG_NO DESC ";
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
      T_IPOINT_LOG AS P
        LEFT JOIN
      ORDERS AS O ON O.ORDERS_NO = P.ORDERS_NO
    $where_query
  ");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      P.*,
      O.GUBUN,
      O.IMG_PATH
    FROM
      T_IPOINT_LOG AS P
        LEFT JOIN
      ORDERS AS O ON O.ORDERS_NO = P.ORDERS_NO
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (!isset($list['LOG_NO'])) {
    $list['LOG_NO'] = array();
  }

  if (is_array($list['LOG_NO'])) {
    $list['count'] = count($list['LOG_NO']);
  }

  return $list;
}
?>
