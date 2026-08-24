<?php
// 정보 처리
function F_EMAILTEMPLET($_L) {
  global $db;

  $add_query = "";

  if (isset($_L['price'])) {
    $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);
  }

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO = '".$_L['TEMPLET_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  $field = "TEMPLET_NO,SUBJECT,CONTENT,STAFFID,STOPYN,REG_DATE";
  $f_arr = explode(",", $field);

  foreach ($f_arr as $v) {
    if (!isset($_L[$v])) $_L[$v] = "";
  }

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO EMAILTEMPLET(
        TEMPLET_NO,
        SUBJECT,
        CONTENT,
        STAFFID,
        STOPYN,
        REG_DATE
      ) VALUES (
        '".$_L['TEMPLET_NO']."',
        '".$_L['SUBJECT']."',
        '".$_L['CONTENT']."',
        '".$_L['STAFFID']."',
        '".$_L['STOPYN']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['FILE1'] != '') $add_query .= "FILE1 = '".$_L['FILE1']."',";
    if ($_L['FILE2'] != '') $add_query .= "FILE2 = '".$_L['FILE2']."',";

    $query = "
      UPDATE EMAILTEMPLET SET
        ".$add_query."
        SUBJECT = '".$_L['SUBJECT']."',
        CONTENT = '".$_L['CONTENT']."',
        STAFFID = '".$_L['STAFFID']."',
        STOPYN  = '".$_L['STOPYN']."'
      WHERE
        TEMPLET_NO = '".$_L['TEMPLET_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM EMAILTEMPLET WHERE TEMPLET_NO = '".$_L['TEMPLET_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_EMAILTEMPLET_list($_L) {
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
    $order_query = " ORDER BY TEMPLET_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM EMAILTEMPLET $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      EMAILTEMPLET
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (!isset($list['TEMPLET_NO'])) {
    $list['TEMPLET_NO'] = array();
  }

  if (is_array($list['TEMPLET_NO'])) {
    $list['count'] = count($list['TEMPLET_NO']);
  }
  return $list;
}
?>
