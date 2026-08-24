<?php
// 정보 처리
function F_CASH_RECEIPT_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L["wheres"]) ? $_L["wheres"] : "";
  $_L = F_add_slashes($_L);

  if ($_L["find_object"] != null && $_L["find_text"] != null) {
    $add_query .= " AND ".$_L['find_object']." LIKE '%".$_L['find_text']."%' ";
  }
  if ($_L["add_query"]) {
    $add_query .= stripslashes($_L["add_query"]);
  }
  if (isset($_L["s_area"])) {
    $add_query .= " AND area = '".$_L['s_area']."' ";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L["order"] != null) {
    $order_query = "ORDER BY {$_L['order']} ";
  } else {
    $order_query = "ORDER BY R.REG_DATE DESC ";
  }

  // 페이지 네비게이션
  if (!$_L["page"]) {
    $_L["page"] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= "WHERE".substr($add_query, 4, $querylen-4);
  }

  $page_info["cur"] = $_L["page"];
  $page_info["row"] = $_L["row"];
  $count_now = $page_info["row"] * ($page_info["cur"] - 1);
  $top_rows = $_L["page"] * $_L["row"];
  $page_info["total"] = $db->get_data_one("
    SELECT
      COUNT(*)
    FROM
      CASH_RECEIPT AS R
        LEFT JOIN
      MEMBER AS M ON M.USER_ID = R.USER_ID
        LEFT JOIN
      CASH AS C ON C.CASH_NO = R.CASH_NO
    $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      R.IDX AS IDX,
      R.CASH_NO AS CASH_NO,
      M.NAME AS NAME,
      R.USER_ID AS USER_ID,
      M.HP AS HP,
      C.PRICE AS PRICE,
      R.STATUS AS STATUS,
      C.REG_DATE AS CASH_REG_DATE,
      R.REG_DATE AS REG_DATE,
      R.UPDATE_DATE AS UPDATE_DATE
    FROM
      CASH_RECEIPT AS R
        LEFT JOIN
      MEMBER AS M ON M.USER_ID = R.USER_ID
        LEFT JOIN
      CASH AS C ON C.CASH_NO = R.CASH_NO
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list["page_string"] = print_page_num($page_info); // 페이지 번호 출력
  $list["total"] = $page_info["total"];
  $list["row"] = $_L["row"];
  $list["count"] = 0;

  if (is_array($list["CASH_NO"])) {
    $list["count"] = count($list["CASH_NO"]);
  }
  return $list;
}

/**
 * 현금영수증 발행 완료 처리
 */
function F_APPLY_CASH_RECEIPT($_L) {
  global $db;

  $_L = F_add_slashes($_L);

  if ($_L["mode"] == "update") {
    $query = "UPDATE CASH_RECEIPT SET STATUS = 'Y' WHERE CASH_NO = '{$_L['CASH_NO']}'";
  }

  $db->query($query);
}

/**
 * 현금영수증 신청 삭제
 */
function F_DELETE_CASH_RECEIPT($_L) {
  global $db;

  $_L = F_add_slashes($_L);

  if ($_L["mode"] == "delete") {
    $query = "DELETE FROM CASH_RECEIPT WHERE CASH_NO = '{$_L['CASH_NO']}'";
  }

  $db->query($query);
}
?>
