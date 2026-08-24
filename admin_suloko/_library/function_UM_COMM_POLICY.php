<?php
// 정보 처리
function F_UM_COMM_POLICY($_L) {
  global $db;

  $add_query = "";
  $_L['IDX'] = preg_replace("/[^0-9\-]/", "", $_L['IDX']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM UM_COMM_POLICY WHERE IDX = {$_L['IDX']}");
    // $info = F_strip_slashes($info);
    return $info;
  }

  // $_L = F_add_slashes($_L); -> 배열안의 배열이 있는 형태는 처리가 안됨

  // 기본설정일 경우 처리
  $old_default_Y = 0;
  if ($_L['DEFAULT_YN'] == "Y") {
    $info = $db->get_data("SELECT IDX FROM UM_COMM_POLICY WHERE DEFAULT_YN = 'Y' LIMIT 1");
    if ($_L['IDX'] != $info['IDX']) $old_default_Y = $info['IDX'];
    unset($info);
  }

  if ($_L['mode'] == 'insert') {
    // insert policy
    $query = "
      INSERT INTO UM_COMM_POLICY (
        NAME,
        STATUS,
        DEFAULT_YN,
        FIRST_SALE,
        MILEAGE,
        PAYMENT_RATE,
        SUBSCRIBER,
        AVERAGE_SUBSCRIBER,
        AMOUNT
      ) VALUES (
        '".$_L['NAME']."',
        '".$_L['STATUS']."',
        '".$_L['DEFAULT_YN']."',
        ".(is_null($_L['FIRST_SALE']) ? "NULL" : "'".$_L['FIRST_SALE']."'") .",
        ".(is_null($_L['MILEAGE']) ? "NULL" : "'".$_L['MILEAGE']."'") .",
        ".(is_null($_L['PAYMENT_RATE']) ? "NULL" : "'".$_L['PAYMENT_RATE']."'") .",
        ".(is_null($_L['SUBSCRIBER']) ? "NULL" : "'".$_L['SUBSCRIBER']."'") .",
        ".(is_null($_L['AVERAGE_SUBSCRIBER']) ? "NULL" : "'".$_L['AVERAGE_SUBSCRIBER']."'") .",
        ".(is_null($_L['AMOUNT']) ? "NULL" : "'".$_L['AMOUNT']."'") ."
      )
    ";
    $last_idx = $db->query_id($query);

    // insert unit
    if ($last_idx) {
      for ($i = 0; $i < count($_L['UNIT_PAYMENT_RATE']); $i++) {
        $query = "
          INSERT INTO UM_COMM_UNIT (
            COMM_POLICY_IDX,
            PAYMENT_RATE,
            UNIT_PRICE
          ) VALUES (
            '{$last_idx}',
            '{$_L['UNIT_PAYMENT_RATE'][$i]}',
            '{$_L['UNIT_PRICE'][$i]}'
          )
        ";
        $res = $db->query($query);
      }
    }
  }

  if ($_L['mode'] == 'update') {
    // update policy
    $query = "
      UPDATE UM_COMM_POLICY SET
        NAME               = '".$_L['NAME']."',
        STATUS             = '".$_L['STATUS']."',
        DEFAULT_YN         = '".$_L['DEFAULT_YN']."',
        FIRST_SALE         = ".(is_null($_L['FIRST_SALE']) ? "NULL" : "'".$_L['FIRST_SALE']."'") .",
        MILEAGE            = ".(is_null($_L['MILEAGE']) ? "NULL" : "'".$_L['MILEAGE']."'") .",
        PAYMENT_RATE       = ".(is_null($_L['PAYMENT_RATE']) ? "NULL" : "'".$_L['PAYMENT_RATE']."'") .",
        SUBSCRIBER         = ".(is_null($_L['SUBSCRIBER']) ? "NULL" : "'".$_L['SUBSCRIBER']."'") .",
        AVERAGE_SUBSCRIBER = ".(is_null($_L['AVERAGE_SUBSCRIBER']) ? "NULL" : "'".$_L['AVERAGE_SUBSCRIBER']."'") .",
        AMOUNT             = ".(is_null($_L['AMOUNT']) ? "NULL" : "'".$_L['AMOUNT']."'") ."
      WHERE
        IDX = '".$_L['IDX']."'
    ";
    $db->query_id($query);

    // update unit
    for ($i = 0; $i < count($_L['UNIT_IDX']); $i++) {
      $query = "
        UPDATE UM_COMM_UNIT SET
          PAYMENT_RATE = '{$_L['UNIT_PAYMENT_RATE'][$i]}',
          UNIT_PRICE   = '{$_L['UNIT_PRICE'][$i]}'
        WHERE
          IDX = '{$_L['UNIT_IDX'][$i]}'
      ";
      $res = $db->query($query);
    }
  }

  if ($_L['mode'] == 'delete') {
    for ($i = 0; $i < count($_L['CHK_IDX']); $i++) {
      // delete policy
      $query = "DELETE FROM UM_COMM_POLICY WHERE IDX = '{$_L['CHK_IDX'][$i]}'";
      $res = $db->query($query);
      // delete unit
      $query = "DELETE FROM UM_COMM_UNIT WHERE COMM_POLICY_IDX = '{$_L['CHK_IDX'][$i]}'";
      $res = $db->query($query);
    }
  }

  // 기존 설정 업데이트
  if ($old_default_Y != 0) {
    $db->query("UPDATE UM_COMM_POLICY SET DEFAULT_YN = 'N' WHERE IDX = {$old_default_Y}");
  }

  return $res;
}

// 목록 불러오기
function F_UM_COMM_POLICY_list($_L) {
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM UM_COMM_POLICY {$where_query} ");

  // 위의 조건에 따라 목록 가져오기
  $query  = "
    SELECT *, (SELECT COUNT(*) FROM UM_PARTNER WHERE COMM_POLICY_IDX = U.IDX) AS PARTNER_CNT
    FROM UM_COMM_POLICY U
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

function F_UM_COMM_UNIT_list($IDX) {
  global $db;

  $IDX  = preg_replace("/[^0-9\-]/", "", $IDX);
  $list = $db->get_list("SELECT * FROM UM_COMM_UNIT WHERE COMM_POLICY_IDX = {$IDX} ORDER BY PAYMENT_RATE ASC");
  return $list;
}
?>
