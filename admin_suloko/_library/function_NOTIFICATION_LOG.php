<?php
function F_NOTIFICATION_LOG($_L) {
  global $db;

  $add_query = "";

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO NOTIFICATION_LOG(
        TYPE,
        USER_ID,
        TOKEN,
        TOPIC,
        TITLE,
        CONTENT,
        URL,
        IMG_URL,
        IS_SUCCESS,
        ERROR_CODE,
        ERROR_MESSAGE
      ) VALUES (
        '{$_L['TYPE']}',
        ".(is_null($_L['USER_ID']) ? "NULL" : "'".$_L['USER_ID']."'") .",
        ".(is_null($_L['TOKEN']) ? "NULL" : "'".$_L['TOKEN']."'") .",
        ".(is_null($_L['TOPIC']) ? "NULL" : "'".$_L['TOPIC']."'") .",
        '{$_L['TITLE']}',
        '{$_L['CONTENT']}',
        '{$_L['URL']}',
        '{$_L['IMG_URL']}',
        '{$_L['IS_SUCCESS']}',
        ".(is_null($_L['ERROR_CODE']) ? "NULL" : "'".$_L['ERROR_CODE']."'") .",
        ".(is_null($_L['ERROR_MESSAGE']) ? "NULL" : "'".addslashes($_L['ERROR_MESSAGE'])."'") ."
      )
    ";
  }
  $db->query($query);
}

/**
 * 푸시 알림 로그 목록
 */
function F_NOTIFICATION_LOG_LIST($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L["wheres"]) ? $_L["wheres"] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
  }

  if (isset($_L["add_query"])) {
    $add_query .= stripslashes($_L["add_query"]);
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬
  if ($_L["order"] != null) {
    $order_query = " ORDER BY {$_L['order']}";
  } else {
    $order_query = " ORDER BY CREATED_AT DESC";
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
  $page_info["total"] = $db->get_data_one("
    SELECT
      COUNT(*)
    FROM
      NOTIFICATION_LOG
    $where_query
  ");

  $query = "
    SELECT
      IDX,
      TYPE,
      USER_ID,
      TOPIC,
      TITLE,
      CONTENT,
      IS_SUCCESS,
      ERROR_CODE,
      ERROR_MESSAGE,
      CREATED_AT
    FROM
      NOTIFICATION_LOG
    $where_query
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
