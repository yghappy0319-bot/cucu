<?php
function F_SMSSENDLOG($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM SMSSENDLOG WHERE LOG_NO = '{$_L['LOG_NO']}'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO SMSSENDLOG(
        LOG_NO,
        CODESK,
        TOHP,
        TEMPLET_NO,
        MEMBER_NO,
        MEMBER_NAME,
        SUBJECT,
        SENDMSG,
        REG_DATE
      ) VALUES (
        '{$_L['LOG_NO']}',
        '{$_L['CODESK']}',
        '{$_L['TOHP']}',
        '{$_L['TEMPLET_NO']}',
        '{$_L['MEMBER_NO']}',
        '{$_L['MEMBER_NAME']}',
        '{$_L['SUBJECT']}',
        '{$_L['SENDMSG']}',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '{$_L['file1']}',";
    $query = "
      UPDATE SMSSENDLOG SET
        {$add_query}
        CODESK      = '{$_L['CODESK']}',
        TOHP        = '{$_L['TOHP']}',
        TEMPLET_NO  = '{$_L['TEMPLET_NO']}',
        MEMBER_NO   = '{$_L['MEMBER_NO']}',
        MEMBER_NAME = '{$_L['MEMBER_NAME']}',
        SUBJECT     = '{$_L['SUBJECT']}',
        SENDMSG     = '{$_L['SENDMSG']}'
      WHERE
        LOG_NO = '{$_L['LOG_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM SMSSENDLOG WHERE LOG_NO = '{$_L['LOG_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_SMSSENDLOG_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE  '%{$_L['find_text']}%'";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if ($wheres) {
  $add_query .= $wheres;
  }

  // 정렬
  if ($_L['order'] != null) {
    $order_query = " ORDER BY {$_L['order']}";
  } else {
    $order_query = " ORDER BY LOG_NO DESC";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }
  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = "WHERE ".substr($add_query, 4, $querylen-4);
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM SMSSENDLOG $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      LOG_NO,
      CODESK,
      TOHP,
      MEMBER_NAME,
      SUBJECT,
      SENDMSG,
      REG_DATE
    FROM
      SMSSENDLOG
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['LOG_NO'])) {
    $list['count'] = count($list['LOG_NO']);
  }
  return $list;
}
?>
