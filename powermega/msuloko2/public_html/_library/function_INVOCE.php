<?php
// 정보 처리
function F_INVOCE($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM INVOCE WHERE INVOCE_NO = '".$_L['INVOCE_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO INVOCE(
        INVOCE_NO,
        USER_ID,
        MONEY,
        MONEY2,
        BANK1,
        BANK2,
        BANK3,
        STATUS,
        PROC_DATE,
        REG_DATE
      ) VALUES (
        '".$_L['INVOCE_NO']."',
        '".$_L['USER_ID']."',
        '".$_L['MONEY']."',
        '".$_L['MONEY2']."',
        '".$_L['BANK1']."',
        '".$_L['BANK2']."',
        '".$_L['BANK3']."',
        '".$_L['STATUS']."',
        '".$_L['PROC_DATE']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '".$_L['file1']."',";

    $query = "
      UPDATE INVOCE SET
        ".$add_query."
        USER_ID = '".$_L['USER_ID']."',
        MONEY = '".$_L['MONEY']."',
        MONEY2 = '".$_L['MONEY2']."',
        BANK1 = '".$_L['BANK1']."',
        BANK2 = '".$_L['BANK2']."',
        BANK3 = '".$_L['BANK3']."',
        STATUS = '".$_L['STATUS']."',
        PROC_DATE = '".$_L['PROC_DATE']."'
      WHERE
        INVOCE_NO = '".$_L['INVOCE_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM INVOCE WHERE INVOCE_NO = '".$_L['INVOCE_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_INVOCE_list($_L) {
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
    $order_query = " ORDER BY INVOCE_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM INVOCE $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      INVOCE
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['INVOCE_NO'])) {
    $list['count'] = count($list['INVOCE_NO']);
  }
  return $list;
}
?>
