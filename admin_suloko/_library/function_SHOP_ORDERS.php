<?php
// 목록 불러오기

function SHOP_ORDER($_L){
    global $db;
      if ($_L['mode'] == 'read') {
        $info = $db->get_data("SELECT * FROM PRODUCT_ORDERS WHERE idx = '{$_L['IDX']}'");
        $info = F_strip_slashes($info);
        return $info;
    }

    if ($_L['mode'] == 'update') {
        $query = "
          UPDATE PRODUCT_ORDERS SET
            status       = '".trim($_L['status'])."',
            order_name       = '".trim($_L['order_name'])."',
            order_phone     = '".trim($_L['order_phone'])."',
            order_zipcode     = '".trim($_L['order_zipcode'])."',
            order_address1     = '".trim($_L['order_address1'])."',
            order_address2 = '".trim($_L['order_address2'])."'
          WHERE
            idx = '".trim($_L['IDX'])."'
        ";
    }

    if ($_L['mode'] == 'delete') {
        $query = "DELETE FROM PRODUCT_ORDERS WHERE idx = '".trim($_L['IDX'])."'";
    }

    $res = $db->query($query);
    return $res;
}


function F_SHOP_ORDER_list($_L) {
    global $db;

    $add_query = "";
    $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
    $_L = F_add_slashes($_L);

    if ($_L['find_object'] != null && $_L['find_text'] != null) {
        $add_query .= " AND ".$_L['find_object']." LIKE '%".$_L['find_text']."%' ";
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
        $order_query = " ORDER BY regdate DESC ";
    }

    // 페이지 네비게이션 표시
    if (!$_L['page']) {
        $_L['page'] = 1;
    }

    if ($add_query) {
        $where_query = " WHERE ".$add_query;
    }

    $page_info['cur'] = $_L['page'];
    $page_info['row'] = $_L['row'];
    $count_now = $page_info['row'] * ($page_info['cur'] - 1);
    $top_rows  = $_L['page'] * $_L['row'];
    $page_info['total'] = $db->get_data_one("SELECT count(*) FROM PRODUCT_ORDERS $where_query");

    // 위의 조건에 따라 목록 가져오기
    $query = "
    SELECT
      *
    FROM
      PRODUCT_ORDERS
    $where_query
    $order_query
    LIMIT ".$count_now.",".$page_info['row']."
  ";
    echo $query;

    $list = $db->get_list($query);

    $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
    $list['total'] = $page_info['total'];
    $list['row'] = $_L['row'];
    $list['count'] = 0;

    if (!isset($list['idx'])) {
        $list['idx'] = array();
    }

    if (is_array($list['idx'])) {
        $list['count'] = count($list['idx']);
    }
    return $list;
}

function 주문상태($status){
    if($status==1){
        return "배송중";
    }else if($status==2){
        return "배송완료";
    }else{
        return "배송준비중";
    }
}

?>
