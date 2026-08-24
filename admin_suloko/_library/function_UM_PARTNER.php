<?php
// 정보 처리
function F_UM_PARTNER($_L) {
  global $db;

  $add_query = "";
  $_L['IDX'] = preg_replace("/[^0-9\-]/", "", $_L['IDX']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM UM_PARTNER WHERE IDX = {$_L['IDX']}");
    // $info = F_strip_slashes($info);
    return $info;
  }

  // $_L = F_add_slashes($_L); -> 배열안의 배열이 있는 형태는 처리가 안됨

  /*
  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO UM_PARTNER (
        COMM_POLICY_IDX,
        PARTNER_ID,
        PASSWD,
        NAME,
        TEL,
        EMAIL,
        BANK_NAME,
        BANK_ACCOUNT_NUM,
        PASS_BOOK_URL,
        ID_CARD_URL,
        IP,
        STATUS
      ) VALUES (
        '".$_L['COMM_POLICY_IDX']."',
        '".$_L['PARTNER_ID']."',
        PASSWORD('".$_L['PASSWD']."'),
        '".$_L['NAME']."',
        '".$_L['TEL']."',
        '".$_L['EMAIL']."',
        '".$_L['BANK_NAME']."',
        '".$_L['BANK_ACCOUNT_NUM']."',
        '".$_L['PASS_BOOK_URL']."',
        '".$_L['ID_CARD_URL']."',
        '".$_L['IP']."',
        '".$_L['DEFAULT_YN']."',
        '".$_L['NAME']."',
        'Y'
      )
    ";
  }
  */

  if ($_L['mode'] == 'update') {
    $add_query = "";
    if (trim($_L['PASSWD']) != '') $add_query = ",PASSWD = PASSWORD('".trim($_L['PASSWD'])."')";

    $query = "
      UPDATE UM_PARTNER SET
        COMM_POLICY_IDX   = '".trim($_L['COMM_POLICY_IDX'])."',
        NAME              = '".trim($_L['NAME'])."',
        TEL               = '".trim($_L['TEL'])."',
        EMAIL             = '".trim($_L['EMAIL'])."',
        BANK_NAME         = '".trim($_L['BANK_NAME'])."',
        BANK_ACCOUNT_NUM  = '".trim($_L['BANK_ACCOUNT_NUM'])."',
        STATUS            = '".trim($_L['STATUS'])."'
        {$add_query}
      WHERE
        IDX = '".trim($_L['IDX'])."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM UM_PARTNER WHERE IDX = '{$_L['IDX']}'";
  }

  $res = $db->query($query);
  return $res;
}

// 목록 불러오기
function F_UM_PARTNER_list($_L) {
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
    $order_query = " ORDER BY IDX DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM UM_PARTNER {$where_query} ");

  // 위의 조건에 따라 목록 가져오기
  $query  = "
    SELECT *
    FROM UM_PARTNER
    {$where_query}
    {$order_query}
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
