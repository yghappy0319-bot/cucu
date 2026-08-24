<?php
// 정보 처리
function F_WININFO($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM WININFO WHERE WININFO_NO = '".$_L['WININFO_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO WININFO(
        WININFO_NO,
        GUBUN,
        PLAYDATE,
        DRAWNUM,
        TIME_S,
        TIME_E,
        YUTUBE,
        PRIZ1,
        PRIZ2,
        PRIZ3,
        PRIZ4,
        PRIZ5,
        PRIZ6,
        PRIZ7,
        PRIZ8,
        PRIZ9,
        PRIZCNT1,
        PRIZCNT2,
        PRIZCNT3,
        PRIZCNT4,
        PRIZCNT5,
        PRIZCNT6,
        PRIZCNT7,
        PRIZCNT8,
        PRIZCNT9,
        REG_DATE
      ) VALUES (
        '".$_L['WININFO_NO']."',
        '".$_L['GUBUN']."',
        '".$_L['PLAYDATE']."',
        '".$_L['DRAWNUM']."',
        '".$_L['TIME_S']."',
        '".$_L['TIME_E']."',
        '".$_L['YUTUBE']."',
        '".$_L['PRIZ1']."',
        '".$_L['PRIZ2']."',
        '".$_L['PRIZ3']."',
        '".$_L['PRIZ4']."',
        '".$_L['PRIZ5']."',
        '".$_L['PRIZ6']."',
        '".$_L['PRIZ7']."',
        '".$_L['PRIZ8']."',
        '".$_L['PRIZ9']."',
        '".$_L['PRIZCNT1']."',
        '".$_L['PRIZCNT2']."',
        '".$_L['PRIZCNT3']."',
        '".$_L['PRIZCNT4']."',
        '".$_L['PRIZCNT5']."',
        '".$_L['PRIZCNT6']."',
        '".$_L['PRIZCNT7']."',
        '".$_L['PRIZCNT8']."',
        '".$_L['PRIZCNT9']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '".$_L['file1']."',";

    $query = "
      UPDATE WININFO SET
        ".$add_query."
        GUBUN    = '".$_L['GUBUN']."',
        PLAYDATE = '".$_L['PLAYDATE']."',
        DRAWNUM  = '".$_L['DRAWNUM']."',
        TIME_S   = '".$_L['TIME_S']."',
        TIME_E   = '".$_L['TIME_E']."',
        YUTUBE   = '".$_L['YUTUBE']."',
        PRIZ1    = '".$_L['PRIZ1']."',
        PRIZ2    = '".$_L['PRIZ2']."',
        PRIZ3    = '".$_L['PRIZ3']."',
        PRIZ4    = '".$_L['PRIZ4']."',
        PRIZ5    = '".$_L['PRIZ5']."',
        PRIZ6    = '".$_L['PRIZ6']."',
        PRIZ7    = '".$_L['PRIZ7']."',
        PRIZ8    = '".$_L['PRIZ8']."',
        PRIZ9    = '".$_L['PRIZ9']."',
        PRIZCNT1 = '".$_L['PRIZCNT1']."',
        PRIZCNT2 = '".$_L['PRIZCNT2']."',
        PRIZCNT3 = '".$_L['PRIZCNT3']."',
        PRIZCNT4 = '".$_L['PRIZCNT4']."',
        PRIZCNT5 = '".$_L['PRIZCNT5']."',
        PRIZCNT6 = '".$_L['PRIZCNT6']."',
        PRIZCNT7 = '".$_L['PRIZCNT7']."',
        PRIZCNT8 = '".$_L['PRIZCNT8']."',
        PRIZCNT9 = '".$_L['PRIZCNT9']."'
      WHERE
        WININFO_NO = '".$_L['WININFO_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM WININFO WHERE WININFO_NO = '".$_L['WININFO_NO']."'";
  }

  $db->query($query);
}

//  목록 불러오기
function F_WININFO_list($_L) {
  global $db;

  $add_query = "";
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
    $order_query = " ORDER BY WININFO_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM WININFO $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      WININFO
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['WININFO_NO'])) {
    $list['count'] = count($list['WININFO_NO']);
  }
  return $list;
}
?>
