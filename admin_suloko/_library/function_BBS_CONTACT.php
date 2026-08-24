<?php
// 정보 처리
function F_BBS($_L) {
  global $db;

  $add_query = "";
  $_L['price']  = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '".$_L['BBS_NO']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes_real($_L);

  if ($_L['mode'] == 'insert') {
    if ($_L['STATUS'] == '') {
      $_L['STATUS'] = "Y";
    }

    $query = "
      INSERT INTO BBS(
        BBS_NO,
        GUBUN,
        QNA_TYPE,
        SUBJECT,
        CONTENT,
        REPLY,
        REPLY_NAME,
        REPLY_DATE,
        REPLY_YN,
        NAME,
        USER_ID,
        HP,
        FILE1,
        FILE2,
        FWIDTH,
        FWIDTH_TYPE,
        YUTUBE,
        HIT,
        STATUS,
        REG_DATE
      ) VALUES (
        '".$_L['BBS_NO']."',
        '".$_L['GUBUN']."',
        '".$_L['QNA_TYPE']."',
        '".$_L['SUBJECT']."',
        '".$_L['CONTENT']."',
        '".$_L['REPLY']."',
        '".$_L['REPLY_NAME']."',
        '".$_L['REPLY_DATE']."',
        '".$_L['REPLY_YN']."',
        '".$_L['NAME']."',
        '".$_L['USER_ID']."',
        '".$_L['HP']."',
        '".$_L['FILE1']."',
        '".$_L['FILE2']."',
        '".$_L['FWIDTH']."',
        '".$_L['FWIDTH_TYPE']."',
        '".$_L['YUTUBE']."',
        '0',
        '".$_L['STATUS']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['STATUS']) {
      $add_query .= "STATUS = '".$_L['STATUS']."',";
    }

    $query = "
      UPDATE BBS SET
        ".$add_query."
        GUBUN = '".$_L['GUBUN']."',
        QNA_TYPE = '".$_L['QNA_TYPE']."',
        SUBJECT = '".$_L['SUBJECT']."',
        CONTENT = '".$_L['CONTENT']."',
        REPLY = '".$_L['REPLY']."',
        REPLY_NAME = '".$_L['REPLY_NAME']."',
        REPLY_DATE = '".$_L['REPLY_DATE']."',
        REPLY_YN = '".$_L['REPLY_YN']."',
        NAME = '".$_L['NAME']."',
        USER_ID = '".$_L['USER_ID']."',
        HP = '".$_L['HP']."',
        FILE1 = '".$_L['FILE1']."',
        FILE2 = '".$_L['FILE2']."',
        FWIDTH = '".$_L['FWIDTH']."',
        FWIDTH_TYPE = '".$_L['FWIDTH_TYPE']."',
        YUTUBE = '".$_L['YUTUBE']."',
        PIN = '".$_L['PIN']."'
      WHERE
        BBS_NO = '".$_L['BBS_NO']."'
      ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM BBS WHERE BBS_NO = '".$_L['BBS_NO']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_BBS_list($_L) {
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

  // 정렬 기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY ".$_L['order']." ";
  } else {
    $order_query = " ORDER BY PIN DESC, REG_DATE DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM BBS $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *, (SELECT MEMBER_NO FROM MEMBER WHERE USER_ID = A.USER_ID) AS MEMBER_NO
    FROM
      BBS A
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['BBS_NO'])) {
    $list['count'] = count($list['BBS_NO']);
  }
  return $list;
}
?>
