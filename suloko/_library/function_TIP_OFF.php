<?php
// 보이스피싱 제보
function F_TIP_OFF($_L) {
  global $db;

  $add_query = "";

  $_L = F_add_slashes($_L);

  if ($_L["mode"] == "insert") {
    $query = "
      INSERT INTO TIP_OFF(
        USER_ID,
        SUBJECT,
        CONTENT,
        ORIGIN_FILE1,
        ORIGIN_FILE2,
        ORIGIN_FILE3,
        FILE1,
        FILE2,
        FILE3
      ) VALUES (
        '{$_L['USER_ID']}',
        '{$_L['SUBJECT']}',
        '{$_L['CONTENT']}',
        ".(is_null($_L['REAL_FILENAME1']) ? "NULL" : "'".$_L['REAL_FILENAME1']."'") .",
        ".(is_null($_L['REAL_FILENAME2']) ? "NULL" : "'".$_L['REAL_FILENAME2']."'") .",
        ".(is_null($_L['REAL_FILENAME3']) ? "NULL" : "'".$_L['REAL_FILENAME3']."'") .",
        ".(is_null($_L['FILE1']) ? "NULL" : "'".$_L['FILE1']."'") .",
        ".(is_null($_L['FILE2']) ? "NULL" : "'".$_L['FILE2']."'") .",
        ".(is_null($_L['FILE3']) ? "NULL" : "'".$_L['FILE3']."'") ."
      )
    ";
  }

  $db->query($query);
}

function F_TIP_OFF_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }

  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']} ";
  } else {
    $order_query = "ORDER BY CREATED_AT DESC";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM TIP_OFF $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT *
    FROM
      TIP_OFF
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['IDX'])) {
    $list['count'] = count($list['IDX']);
  }
  return $list;
}
?>
