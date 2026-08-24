<?php
// 정보 처리
function F_UM_SUMMARY($_L) {
  global $db;

  $add_query = "";
  $_L['IDX'] = preg_replace("/[^0-9\-]/", "", $_L['IDX']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM UM_SUMMARY WHERE IDX = {$_L['IDX']}");
    // $info = F_strip_slashes($info);
    return $info;
  }

  // $_L = F_add_slashes($_L); -> 배열안의 배열이 있는 형태는 처리가 안됨

  /*
  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO UM_SUMMARY (
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
    // update policy
    $query = "
      UPDATE UM_SUMMARY SET
        COMM_POLICY_IDX   = '".$_L['COMM_POLICY_IDX']."',
        PASSWD            = PASSWORD('".$_L['PASSWD']."'),
        NAME              = '".$_L['NAME']."',
        TEL               = '".$_L['TEL']."',
        EMAIL             = '".$_L['EMAIL']."',
        BANK_NAME         = '".$_L['BANK_NAME']."',
        BANK_ACCOUNT_NUM  = '".$_L['BANK_ACCOUNT_NUM']."',
        PASS_BOOK_URL     = '".$_L['PASS_BOOK_URL']."',
        ID_CARD_URL       = '".$_L['ID_CARD_URL']."',
        STATUS            = '".$_L['STATUS']."'
      WHERE
        IDX = '".$_L['IDX']."'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM UM_SUMMARY WHERE IDX = '{$_L['IDX']}'";
  }

  $res = $db->query($query);
  return $res;
}

// 목록 불러오기
function F_UM_SUMMARY_list($_L) {
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
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM UM_SUMMARY {$where_query} ");

  // 위의 조건에 따라 목록 가져오기
  $query  = "
    SELECT *
    FROM UM_SUMMARY
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

function F_UM_SUMMARY_by_partner($PARTNER_ID,$COMM_POLICY_IDX) {
  global $db;

  // SUMMEARY 정보
  $query = "
    SELECT
      SUM(VISIT_CNT) AS VISIT_CNT,
      SUM(MEMBER_CNT) AS MEMBER_CNT,
      SUM(OMEMBER_CNT) AS OMEMBER_CNT,
      SUM(CASH_CNT) AS CASH_CNT,
      SUM(CCASH_CNT) AS CCASH_CNT
    FROM UM_SUMMARY
    WHERE PARTNER_ID = '{$PARTNER_ID}'
  ";
  $summ = $db->get_data($query);

  $memb_cnt = $summ["MEMBER_CNT"] - $summ["OMEMBER_CNT"]; // 가입수
  $cash_cnt = $summ["CASH_CNT"] - $summ["CCASH_CNT"]; // 결제수

  if ($summ["VISIT_CNT"] > 0) {
    $memb_rate = round($memb_cnt / $summ["VISIT_CNT"] * 100, 0); // 가입율
  } else {
    $memb_rate = 0;
  }

  if ($memb_rate > 0) {
    $cash_rate = round($cash_cnt / $memb_cnt * 100, 0); // 결제율
  } else {
    $cash_rate = 0;
  }

  // 현재 적용 요율
  $query = "
    SELECT
      MAX(PAYMENT_RATE) AS COMM_RATE,
      MAX(UNIT_PRICE) AS COMM_UNIT
    FROM UM_COMM_UNIT
    WHERE COMM_POLICY_IDX = {$COMM_POLICY_IDX} AND PAYMENT_RATE <= {$cash_rate}
  ";
  $comm_unit = $db->get_data($query);

  $RES['VISIT_CNT'] = (is_null($summ["VISIT_CNT"])) ? 0 : $summ["VISIT_CNT"];
  $RES['MEMBER_CNT'] = (is_null($summ["MEMBER_CNT"])) ? 0 : $memb_cnt;
  $RES['CASH_CNT'] = (is_null($summ["CASH_CNT"])) ? 0 : $cash_cnt;
  $RES['MEMBER_RATE'] = $memb_rate;
  $RES['CASH_RATE'] = $cash_rate;
  $RES['COMM_RATE'] = (is_null($comm_unit["COMM_RATE"])) ? "ERR" : $comm_unit["COMM_RATE"];
  $RES['COMM_UNIT'] = (is_null($comm_unit["COMM_UNIT"])) ? 0 : $comm_unit["COMM_UNIT"];
  $RES['COMM_TOTAL'] = $RES['COMM_UNIT'] * $cash_cnt;

  return $RES;
}
?>
