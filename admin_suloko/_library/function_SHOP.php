<?php

function F_PRODUCT($_L) {
    global $db;

    $add_query = "";
    if ($_L['mode'] == 'read') {
        $info = $db->get_data("SELECT * FROM PRODUCT WHERE idx = '{$_L['IDX']}'");
        $info = F_strip_slashes($info);
        return $info;
    }

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == 'insert') {
        $query = "
          INSERT INTO PRODUCT(
            status,
            product_name,
            original_price,
            product_price,
            content1,
            content2,                              
            product_img,
            etc1,
            etc2,
            etc3,
            etc4,
            etc5,
            etc6,
            sort,
            regdate
          ) VALUES (
            '".trim($_L['status'])."',
            '".trim($_L['product_name'])."',
            '".trim($_L['original_price'])."',
            '".trim($_L['product_price'])."',
            '".trim($_L['content1'])."',
            '".trim($_L['content2'])."',
            '".trim($_L['product_img'])."',
            '".trim($_L['etc1'])."',
            '".trim($_L['etc2'])."',
            '".trim($_L['etc3'])."',
            '".trim($_L['etc4'])."',
            '".trim($_L['etc5'])."',
            '".trim($_L['etc6'])."',
            '".trim($_L['sort'])."',
            now()
          )
        ";

    }

    if ($_L['mode'] == 'update') {
        $query = "
      UPDATE PRODUCT SET
        status       = '".trim($_L['status'])."',
        product_name       = '".trim($_L['product_name'])."',
        original_price      = '".trim($_L['original_price'])."', 
        product_price     = '".trim($_L['product_price'])."',
        content1     = '".trim($_L['content1'])."',
        content2     = '".trim($_L['content2'])."',
        product_img = '".trim($_L['product_img'])."',
        etc1     = '".trim($_L['etc1'])."',
        etc2     = '".trim($_L['etc2'])."',
        etc3     = '".trim($_L['etc3'])."',
        etc4     = '".trim($_L['etc4'])."',
        etc5     = '".trim($_L['etc5'])."',
        etc6     = '".trim($_L['etc6'])."',
        sort     = '".trim($_L['sort'])."'
      WHERE
        idx = '".trim($_L['IDX'])."'
    ";
    }

    if ($_L['mode'] == 'delete') {
        $query = "DELETE FROM PRODUCT WHERE idx = '".trim($_L['IDX'])."'";
    }

    $res = $db->query($query);
    return $res;
}


function F_PRODUCT_list($_L) {
    global $db;
    $tablename = "PRODUCT";
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
        $order_query = " ORDER BY idx DESC ";
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
    $page_info['total'] = $db->get_data_one("SELECT count(*) FROM {$tablename} {$where_query}");

    // 위의 조건에 따라 목록 가져오기
    $query = "
    SELECT *
    FROM {$tablename}
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