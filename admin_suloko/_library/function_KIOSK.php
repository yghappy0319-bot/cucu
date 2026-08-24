<?php
// 정보 처리
function F_KIOSK($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM KIOSK WHERE KIOSK_NO = '".$_L['KIOSK_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO KIOSK(
        KIOSK_NO,
        AGENT_NO,
        MAC,
        NAME,
        ANYDESK,
        ADDRESS,
        LAN,
        LOT,
        PHONE,
        STOPYN,
        REG_DATE
      ) VALUES (
        '".$_L['KIOSK_NO']."',
        '".$_L['AGENT_NO']."',
        '".$_L['MAC']."',
        '".$_L['NAME']."',
        '".$_L['ANYDESK']."',
        '".$_L['ADDRESS']."',
        '".$_L['LAN']."',
        '".$_L['LOT']."',
        '".$_L['PHONE']."',
        '".$_L['STOPYN']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1  = '".$_L['file1']."',";

    $query = "
      UPDATE KIOSK SET
        ".$add_query."
        AGENT_NO = '".$_L['AGENT_NO']."',
        MAC      = '".$_L['MAC']."',
        NAME     = '".$_L['NAME']."',
        ANYDESK  = '".$_L['ANYDESK']."',
        ADDRESS  = '".$_L['ADDRESS']."',
        LAN      = '".$_L['LAN']."',
        LOT      = '".$_L['LOT']."',
        PHONE    = '".$_L['PHONE']."',
        STOPYN   = '".$_L['STOPYN']."'
      WHERE
        KIOSK_NO = '".$_L['KIOSK_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM KIOSK WHERE KIOSK_NO = '".$_L['KIOSK_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_KIOSK_list($_L) {
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
    $order_query = " ORDER BY KIOSK_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM KIOSK $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      KIOSK
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['KIOSK_NO'])) {
    $list['count'] = count($list['KIOSK_NO']);
  }
  return $list;
}
?>
