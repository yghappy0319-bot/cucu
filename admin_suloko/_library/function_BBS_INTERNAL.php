<?php
// 정보 처리
function F_BBS_INTERNAL($_L) {
  global $db;

  // sql injection 처리
  foreach ($_L as $k => $v) {
    $_L[$k] = trim(xss_clean($v));
  }

  $add_query = "";

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM BBS_INTERNAL WHERE BBS_INTERNAL_NO = '".$_L['BBS_INTERNAL_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes_real($_L);

  if ($_L['mode'] == 'insert') {
    if ($_L['STATUS'] == '') $_L['STATUS'] = "Y";

    $query = "
      INSERT INTO BBS_INTERNAL(
        BBS_INTERNAL_NO,
        GUBUN,
        SUBJECT,
        CONTENT,
        NAME,
        USER_ID,
        STATUS,
        PIN,
        REG_DATE
      ) VALUES (
        '".$_L['BBS_INTERNAL_NO']."',
        '".$_L['GUBUN']."',
        '".$_L['SUBJECT']."',
        '".$_L['CONTENT']."',
        '".$_L['NAME']."',
        '".$_L['USER_ID']."',
        '".$_L['STATUS']."',
        '".$_L['PIN']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['STATUS']) $add_query .= "STATUS = '".$_L['STATUS']."',";
    $query = "
      UPDATE BBS_INTERNAL SET
        ".$add_query."
        GUBUN   = '".$_L['GUBUN']."',
        SUBJECT = '".$_L['SUBJECT']."',
        CONTENT = '".$_L['CONTENT']."',
        NAME    = '".$_L['NAME']."',
        USER_ID = '".$_L['USER_ID']."'
      WHERE
      BBS_INTERNAL_NO = '".$_L['BBS_INTERNAL_NO']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM BBS_INTERNAL WHERE BBS_INTERNAL_NO = '".$_L['BBS_INTERNAL_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_BBS_INTERNAL_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
  }
  // 구분
  if ($_L['find_object_type'] != null ) {
    $add_query .= " AND QNA_TYPE = '".$_L['find_object_type']."' ";
  }
  // 답변유무
  if ($_L['find_object_reply'] != null ) {
    $add_query .= " AND `REPLY_YN` = '".$_L['find_object_reply']."' ";
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
    $order_query = " ORDER BY PIN ASC, REG_DATE DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM BBS_INTERNAL $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *, (SELECT MEMBER_NO FROM MEMBER WHERE USER_ID = A.USER_ID) AS MEMBER_NO
    FROM
      BBS_INTERNAL A
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['BBS_INTERNAL_NO'])) {
    $list['count'] = count($list['BBS_INTERNAL_NO']);
  }
  return $list;
}
?>
