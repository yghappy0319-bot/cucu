<?php
// 정보 처리
function F_Imglog($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM Imglog WHERE no = '".$_L['no']."'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO Imglog(
        no,
        fileNM,
        isStatus,
        isMsg,
        is_image,
        codeLK,
        drawNum,
        codeNum,
        play_date,
        ballD1ONE,
        ballD1TWO,
        ballD1THR,
        ballD1FOR,
        ballD1FIV,
        ballP1,
        ballD2ONE,
        ballD2TWO,
        ballD2THR,
        ballD2FOR,
        ballD2FIV,
        ballP2,
        ballD3ONE,
        ballD3TWO,
        ballD3THR,
        ballD3FOR,
        ballD3FIV,
        ballP3,
        ballD4ONE,
        ballD4TWO,
        ballD4THR,
        ballD4FOR,
        ballD4FIV,
        ballP4,
        ballD5ONE,
        ballD5TWO,
        ballD5THR,
        ballD5FOR,
        ballD5FIV,
        ballP5,
        is_use,
        regDate
      ) VALUES (
        '".$_L['no']."',
        '".$_L['fileNM']."',
        '".$_L['isStatus']."',
        '".$_L['isMsg']."',
        '".$_L['is_image']."',
        '".$_L['codeLK']."',
        '".$_L['drawNum']."',
        '".$_L['codeNum']."',
        '".$_L['play_date']."',
        '".$_L['ballD1ONE']."',
        '".$_L['ballD1TWO']."',
        '".$_L['ballD1THR']."',
        '".$_L['ballD1FOR']."',
        '".$_L['ballD1FIV']."',
        '".$_L['ballP1']."',
        '".$_L['ballD2ONE']."',
        '".$_L['ballD2TWO']."',
        '".$_L['ballD2THR']."',
        '".$_L['ballD2FOR']."',
        '".$_L['ballD2FIV']."',
        '".$_L['ballP2']."',
        '".$_L['ballD3ONE']."',
        '".$_L['ballD3TWO']."',
        '".$_L['ballD3THR']."',
        '".$_L['ballD3FOR']."',
        '".$_L['ballD3FIV']."',
        '".$_L['ballP3']."',
        '".$_L['ballD4ONE']."',
        '".$_L['ballD4TWO']."',
        '".$_L['ballD4THR']."',
        '".$_L['ballD4FOR']."',
        '".$_L['ballD4FIV']."',
        '".$_L['ballP4']."',
        '".$_L['ballD5ONE']."',
        '".$_L['ballD5TWO']."',
        '".$_L['ballD5THR']."',
        '".$_L['ballD5FOR']."',
        '".$_L['ballD5FIV']."',
        '".$_L['ballP5']."',
        '".$_L['is_use']."',
        NOW()
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '".$_L['file1']."',";

    $query = "
      UPDATE Imglog SET
        ".$add_query."
        fileNM    = '".$_L['fileNM']."',
        isStatus  = '".$_L['isStatus']."',
        isMsg     = '".$_L['isMsg']."',
        is_image  = '".$_L['is_image']."',
        codeLK    = '".$_L['codeLK']."',
        drawNum   = '".$_L['drawNum']."',
        codeNum   = '".$_L['codeNum']."',
        play_date = '".$_L['play_date']."',
        ballD1ONE = '".$_L['ballD1ONE']."',
        ballD1TWO = '".$_L['ballD1TWO']."',
        ballD1THR = '".$_L['ballD1THR']."',
        ballD1FOR = '".$_L['ballD1FOR']."',
        ballD1FIV = '".$_L['ballD1FIV']."',
        ballP1    = '".$_L['ballP1']."',
        ballD2ONE = '".$_L['ballD2ONE']."',
        ballD2TWO = '".$_L['ballD2TWO']."',
        ballD2THR = '".$_L['ballD2THR']."',
        ballD2FOR = '".$_L['ballD2FOR']."',
        ballD2FIV = '".$_L['ballD2FIV']."',
        ballP2    = '".$_L['ballP2']."',
        ballD3ONE = '".$_L['ballD3ONE']."',
        ballD3TWO = '".$_L['ballD3TWO']."',
        ballD3THR = '".$_L['ballD3THR']."',
        ballD3FOR = '".$_L['ballD3FOR']."',
        ballD3FIV = '".$_L['ballD3FIV']."',
        ballP3    = '".$_L['ballP3']."',
        ballD4ONE = '".$_L['ballD4ONE']."',
        ballD4TWO = '".$_L['ballD4TWO']."',
        ballD4THR = '".$_L['ballD4THR']."',
        ballD4FOR = '".$_L['ballD4FOR']."',
        ballD4FIV = '".$_L['ballD4FIV']."',
        ballP4    = '".$_L['ballP4']."',
        ballD5ONE = '".$_L['ballD5ONE']."',
        ballD5TWO = '".$_L['ballD5TWO']."',
        ballD5THR = '".$_L['ballD5THR']."',
        ballD5FOR = '".$_L['ballD5FOR']."',
        ballD5FIV = '".$_L['ballD5FIV']."',
        ballP5    = '".$_L['ballP5']."',
        is_use    = '".$_L['is_use']."'
      WHERE
        no = '".$_L['no']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM Imglog WHERE no = '".$_L['no']."'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_Imglog_list($_L) {
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
    $order_query = " ORDER BY no DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM Imglog $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      Imglog
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['no'])) {
    $list['count'] = count($list['no']);
  }
  return $list;
}
?>
