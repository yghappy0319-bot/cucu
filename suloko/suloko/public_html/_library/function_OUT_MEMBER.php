<?php
// 정보 처리
function F_OUT_MEMBER($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM OUT_MEMBER WHERE MEMBER_NO = '{$_L['MEMBER_NO']}'");
    $info = F_strip_slashes($info);
    return $info;
  }

  $_L = F_add_slashes($_L);
  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO OUT_MEMBER(
        USER_ID,
        PASSWD,
        HP,
        NAME,
        BIRTH,
        COUNTRY,
        GENDER,
        EMAIL,
        POINT,
        CASH,
        WINCASH,
        IPOINT,
        KIOSK_NO,
        AGENT_ID,
        TOTPRICE,
        PATNER_NO,
        TOTCNT,
        AUTH,
        TOKEN,
        EASY,
        IS_MARKETING,
        OUT_MEMO,
        BANK1,
        BANK2,
        BANK3,
        MMBALL1,
        MMBALL2,
        MMBALL3,
        MMBALL4,
        MMBALL5,
        PBBALL1,
        PBBALL2,
        PBBALL3,
        PBBALL4,
        PBBALL5,
        REG_DATE,
        PARTNER_ID,
        LASTDATE
      ) VALUES (
        '{$_L['USER_ID']}',
        '{$_L['PASSWD']}',
        '{$_L['HP']}',
        '{$_L['NAME']}',
        '{$_L['BIRTH']}',
        '{$_L['COUNTRY']}',
        '{$_L['GENDER']}',
        '{$_L['EMAIL']}',
        '{$_L['POINT']}',
        '{$_L['CASH']}',
        '{$_L['WINCASH']}',
        '{$_L['IPOINT']}',
        '{$_L['KIOSK_NO']}',
        '{$_L['AGENT_ID']}',
        '{$_L['TOTPRICE']}',
        '{$_L['PATNER_NO']}',
        '{$_L['TOTCNT']}',
        '{$_L['AUTH']}',
        '{$_L['TOKEN']}',
        '{$_L['EASY']}',
        '{$_L['IS_MARKETING']}',
        '{$_L['OUT_MEMO']}',
        '{$_L['BANK1']}',
        '{$_L['BANK2']}',
        '{$_L['BANK3']}',
        '{$_L['MMBALL1']}',
        '{$_L['MMBALL2']}',
        '{$_L['MMBALL3']}',
        '{$_L['MMBALL4']}',
        '{$_L['MMBALL5']}',
        '{$_L['PBBALL1']}',
        '{$_L['PBBALL2']}',
        '{$_L['PBBALL3']}',
        '{$_L['PBBALL4']}',
        '{$_L['PBBALL5']}',
        NOW(),
        '{$_L['PARTNER_ID']}',
        '{$_L['LASTDATE']}'
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '{$_L['file1']}',";

    $query = "
      UPDATE OUT_MEMBER SET
        {$add_query}
        USER_ID      = '{$_L['USER_ID']}',
        PASSWD       = '{$_L['PASSWD']}',
        HP           = '{$_L['HP']}',
        NAME         = '{$_L['NAME']}',
        BIRTH        = '{$_L['BIRTH']}',
        EMAIL        = '{$_L['EMAIL']}',
        POINT        = '{$_L['POINT']}',
        CASH         = '{$_L['CASH']}',
        IPOINT       = '{$_L['IPOINT']}',
        KIOSK_NO     = '{$_L['KIOSK_NO']}',
        AGENT_ID     = '{$_L['AGENT_ID']}',
        TOTPRICE     = '{$_L['TOTPRICE']}',
        PATNER_NO    = '{$_L['PATNER_NO']}',
        TOTCNT       = '{$_L['TOTCNT']}',
        AUTH         = '{$_L['AUTH']}',
        TOKEN        = '{$_L['TOKEN']}',
        EASY         = '{$_L['EASY']}',
        IS_MARKETING = '{$_L['IS_MARKETING']}',
        OUT_MEMO     = '{$_L['OUT_MEMO']}',
        BANK1        = '{$_L['BANK1']}',
        BANK2        = '{$_L['BANK2']}',
        BANK3        = '{$_L['BANK3']}',
        MMBALL1      = '{$_L['MMBALL1']}',
        MMBALL2      = '{$_L['MMBALL2']}',
        MMBALL3      = '{$_L['MMBALL3']}',
        MMBALL4      = '{$_L['MMBALL4']}',
        MMBALL5      = '{$_L['MMBALL5']}',
        PBBALL1      = '{$_L['PBBALL1']}',
        PBBALL2      = '{$_L['PBBALL2']}',
        PBBALL3      = '{$_L['PBBALL3']}',
        PBBALL4      = '{$_L['PBBALL4']}',
        PBBALL5      = '{$_L['PBBALL5']}',
        LASTDATE     = '{$_L['LASTDATE']}'
      WHERE
        MEMBER_NO = '{$_L['MEMBER_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM OUT_MEMBER WHERE MEMBER_NO = '{$_L['MEMBER_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_OUT_MEMBER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬 기준
  if ($_L['order'] != null) {
    $order_query = " ORDER BY {$_L['order']} ";
  } else {
    $order_query = " ORDER BY MEMBER_NO DESC ";
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM OUT_MEMBER $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *
    FROM
      OUT_MEMBER
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['MEMBER_NO'])) {
    $list['count'] = count($list['MEMBER_NO']);
  }
  return $list;
}
?>
