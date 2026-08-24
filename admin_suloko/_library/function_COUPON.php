<?php
// 정보 처리
function F_COUPON($_L) {
  global $db;

  $add_query = "";

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM COUPON WHERE COUPON_NO = '".$_L['COUPON_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    // 유효기간 추가 (SLK-1295)
    if (!empty($_L['EXPIRED'])) {
      $query = "INSERT INTO `COUPON_EXPIRED` (`NUM`, `EXPIRED_AT`) VALUES ('".$_L['NUM']."', '".$_L['EXPIRED']."')";
      $db->query($query);
    }
    $query = "
      INSERT INTO COUPON(
        COUPON_NO,
        NUM,
        NUM_CNT,
        GUBUN,
        TIME_CNT,
        TITLE,
        PRICE,
        GRPCODE,
        ONOFF,
        PATNER_ID,
        REG_DATE
      ) VALUES (
        '".$_L['COUPON_NO']."',
        '".$_L['NUM']."',
        '".$_L['NUM_CNT']."',
        '".$_L['GUBUN']."',
        '".$_L['TIME_CNT']."',
        '".$_L['TITLE']."',
        '".$_L['PRICE']."',
        '".$_L['GRPCODE']."',
        '".$_L['ONOFF']."',
        '".$_L['PATNER_ID']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    // 유효기간 추가 (SLK-1295)
    if (!empty($_L['EXPIRED'])) {
      $query = "UPDATE `COUPON_EXPIRED` SET `EXPIRED_AT` = '".$_L['EXPIRED']."' WHERE `NUM` = (SELECT `NUM` FROM `COUPON` WHERE `COUPON_NO` = '{$_L['COUPON_NO']}')";
      $db->query($query);
    }
    $query = "
      UPDATE COUPON SET
        ".$add_query."
        TIME_CNT  = '".$_L['TIME_CNT']."',
        TITLE     = '".$_L['TITLE']."',
        PRICE     = '".$_L['PRICE']."',
        GRPCODE   = '".$_L['GRPCODE']."',
        TIME_CNT  = '".$_L['TIME_CNT']."',
        PATNER_ID = '".$_L['PATNER_ID']."'
      WHERE
        COUPON_NO = '".$_L['COUPON_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    // 유효기간 추가 (SLK-1295)
    $query = "DELETE FROM `COUPON_EXPIRED` WHERE `NUM` = (SELECT `NUM` FROM `COUPON` WHERE `COUPON_NO` = '{$_L['COUPON_NO']}')";
    $db->query($query);

    $query = "DELETE FROM COUPON WHERE COUPON_NO = '".$_L['COUPON_NO']."'";
  }

   $db->query($query);
}

// 목록 불러오기
function F_COUPON_list($_L) {
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
    $order_query = " ORDER BY COUPON_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM COUPON $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *,
      (select COUNT(*) AS TCNT from COUPON_LOG where COUPON_NO = COUPON.COUPON_NO) as TCNT
    FROM
      COUPON
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['COUPON_NO'])) {
    $list['count'] = count($list['COUPON_NO']);
  }
  return $list;
}
?>
