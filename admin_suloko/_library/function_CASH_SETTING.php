<?php
// 정보 처리
function F_UM_BANNER($_L) {
    global $db;

    $add_query = "";

    if ($_L['mode'] == 'read') {
        $info = $db->get_data("SELECT * FROM UM_BANNER WHERE IDX = '{$_L['IDX']}'");
        $info = F_strip_slashes($info);
        return $info;
    }

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == 'insert') {
        $query = "
      INSERT INTO UM_BANNER(
        TYPE,
        SIZE,
        STATUS,
        BANNER_URL
      ) VALUES (
        '".trim($_L['TYPE'])."',
        '".trim($_L['SIZE'])."',
        '".trim($_L['STATUS'])."',
        '".trim($_L['BANNER_URL'])."'
      )
    ";
        echo $query;
        exit;
    }

    if ($_L['mode'] == 'update') {
        $query = "
      UPDATE UM_BANNER SET
        TYPE       = '".trim($_L['TYPE'])."',
        SIZE       = '".trim($_L['SIZE'])."',
        STATUS     = '".trim($_L['STATUS'])."',
        BANNER_URL = '".trim($_L['BANNER_URL'])."'
      WHERE
        IDX = '".trim($_L['IDX'])."'
    ";
    }

    if ($_L['mode'] == 'delete') {
        $query = "DELETE FROM UM_BANNER WHERE IDX = '".trim($_L['IDX'])."'";
    }

    $res = $db->query($query);
    return $res;
}

// 목록 불러오기
function F_CASH_SETTING_list($_L) {
    global $db;

    $add_query = "";
    $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
    $_L = F_add_slashes($_L);

    if ($_L['find_object'] != null && $_L['find_text'] != null) {
        $add_query .= " AND ".$_L['find_object']." LIKE  '%".$_L['find_text']."%' ";
    }
    if (isset($_L['add_query'])) {
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
    $page_info['total'] = $db->get_data_one("SELECT count(*) FROM CASH_CONFIG {$where_query}");

    // 위의 조건에 따라 목록 가져오기
    $query = "
    SELECT *
    FROM CASH_CONFIG
    {$where_query}
    {$order_query}
    LIMIT {$count_now}, {$page_info['row']}
  ";
    $list = $db->get_list($query);

    $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
    $list['total'] = $page_info['total'];
    $list['row'] = $_L['row'];
    $list['count'] = 0;

    if (!isset($list['IDX'])) {
        $list['IDX'] = array();
    }

    if (is_array($list['IDX'])) {
        $list['count']= count($list['IDX']);
    }

    return $list;
}
?>
