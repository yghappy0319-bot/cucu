<?php
function F_TOPIC($_L) {
  global $db;

  $add_query = "";

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO TOPIC(
        NAME,
        TITLE,
        DESCRIPTION,
        STATUS,
        SORT
      ) VALUES (
        '{$_L['NAME']}',
        '{$_L['TITLE']}',
        '{$_L['DESCRIPTION']}',
        '{$_L['STATUS']}',
        '{$_L['SORT']}'
      )
    ";
  }

  if ($_L['mode'] == 'update') {
    $query = "
      UPDATE TOPIC SET
        NAME        = '{$_L['NAME']}',
        TITLE       = '{$_L['TITLE']}',
        DESCRIPTION = '{$_L['DESCRIPTION']}',
        STATUS      = '{$_L['STATUS']}',
        SORT        = ".(empty($_L['SORT']) ? "NULL" : "'{$_L['SORT']}'")."
      WHERE
        IDX = '{$_L['IDX']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    $query = "DELETE FROM TOPIC WHERE IDX = {$_L['IDX']}";
  }

  $db->query($query);
}

/**
 * 구독(Topic) 목록
 */
function F_TOPIC_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L["wheres"]) ? $_L["wheres"] : "";
  $_L = F_add_slashes($_L);

  if ($_L["add_query"]) {
    $add_query .= stripslashes($_L["add_query"]);
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L["order"] != null) {
    $order_query = " ORDER BY ".$_L['order']." ";
  } else {
    $order_query = " ORDER BY T.CREATED_AT ASC";
  }

  // 페이지 네비게이션
  if (!$_L["page"]) {
    $_L["page"] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query .= " WHERE ".substr($add_query, 4, $querylen-4);
  }

  $page_info["cur"] = $_L["page"];
  $page_info["row"] = $_L["row"];
  $count_now = $page_info["row"] * ($page_info["cur"] - 1);
  $top_rows = $_L["page"] * $_L["row"];
  $page_info["total"] = $db->get_data_one("SELECT COUNT(*) FROM TOPIC AS T $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      T.IDX,
      T.NAME,
      T.TITLE,
      T.BASIC,
      T.STATUS,
      T.DESCRIPTION,
      IFNULL(T.SORT, '-') AS SORT,
      T.CREATED_AT,
      T.UPDATED_AT,
      COUNT(M.TOPIC_IDX) AS TOKEN_CNT
    FROM
      TOPIC AS T
        LEFT JOIN
      (
        SELECT
          TOPIC_IDX
        FROM
          MEMBER_TOPIC
        WHERE STATUS = 'Y'
      ) AS M ON M.TOPIC_IDX = T.IDX
    $where_query
    GROUP BY T.IDX
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list["page_string"] = print_page_num($page_info); // 페이지 번호 출력
  $list["total"] = $page_info["total"];
  $list["row"] = $_L["row"];
  $list["count"] = 0;

  if (is_array($list["IDX"])) {
    $list["count"] = count($list["IDX"]);
  }
  return $list;
}
?>
