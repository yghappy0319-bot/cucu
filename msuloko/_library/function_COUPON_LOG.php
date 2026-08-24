<?php
// 정보 처리
function F_COUPON_LOG($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM COUPON_LOG WHERE LOG_NO = '".$_L['LOG_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO COUPON_LOG(
        LOG_NO,
        COUPON_NO,
        MEMBER_NO,
        LOG_MEMO,
        GUBUN,
        GRPCODE,
        ISTYPE,
        PATNER_ID,
        REG_DATE
      ) VALUES (
        '".$_L['LOG_NO']."',
        '".$_L['COUPON_NO']."',
        '".$_L['MEMBER_NO']."',
        '".$_L['LOG_MEMO']."',
        '".$_L['GUBUN']."',
        '".$_L['GRPCODE']."',
        '".$_L['ISTYPE']."',
        '".$_L['PATNER_ID']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '".$_L['file1']."',";

    $query = "
      UPDATE COUPON_LOG SET
        ".$add_query."
        COUPON_NO = '".$_L['COUPON_NO']."',
        MEMBER_NO = '".$_L['MEMBER_NO']."',
        LOG_MEMO  = '".$_L['LOG_MEMO']."',
        GUBUN     = '".$_L['GUBUN']."'
      WHERE
        LOG_NO = '".$_L['LOG_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM COUPON_LOG WHERE LOG_NO = '".$_L['LOG_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_COUPON_LOG_list($_L) {
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
    $order_query = " ORDER BY LOG_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM COUPON_LOG $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      C2.*,
      C.LOG_NO AS LOG_NO,
      C.LOG_MEMO AS LOG_MEMO,
      C.REG_DATE AS USE_DATE,
      (SELECT NAME FROM MEMBER WHERE MEMBER_NO=C.MEMBER_NO) AS NAME
    FROM
      COUPON_LOG AS C
        LEFT JOIN
      COUPON AS C2 ON C.COUPON_NO = C2.COUPON_NO
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
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
