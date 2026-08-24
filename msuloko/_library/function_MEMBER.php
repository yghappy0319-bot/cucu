<?php
  // 정보 처리
  function F_MEMBER($_L) {
    global $db;

    $add_query = "";
    $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

    if ($_L['mode'] == 'read') {
      $info = $db->get_data("
        SELECT
          *,
          (select COMPANY from AGENT where USER_ID = MEMBER.AGENT_ID limit 1) as COMPANY
        FROM
          MEMBER
        WHERE
          MEMBER_NO = '".$_L['MEMBER_NO']."'
        ");
      $info = F_strip_slashes($info);
      return $info;
    }

    $_L = F_add_slashes($_L);

    if ($_L['mode'] == 'insert') {
      $query = "
        INSERT INTO MEMBER(
          MEMBER_NO,
          USER_ID,
          PASSWD,
          HP,
          NAME,
          ENAME,
          BIRTH,
          COUNTRY,
          GENDER,
          EMAIL,
          POINT,
          CASH,
          IPOINT,
          LEVEL,
          KIOSK_NO,
          AGENT_ID,
          TOTPRICE,
          TOTCNT,
          AUTH,
          EASY,
          IS_MARKETING,
          BANK1,
          BANK2,
          BANK3,
          PARTNER_ID,
          REG_IP,
          IN_TYPE,
          REG_DATE,
          REFERRAL_NO,
          LASTDATE
        ) VALUES (
          '".$_L['MEMBER_NO']."',
          '".$_L['USER_ID']."',
          PASSWORD('".$_L['PASSWD']."'),
          '".$_L['HP']."',
          '".$_L['NAME']."',
          '".$_L['ENAME']."',
          '".$_L['BIRTH']."',
          '".$_L['COUNTRY']."',
          '".$_L['GENDER']."',
          '".$_L['EMAIL']."',
          '0',
          '0',
          '0',
          '1',
          '".$_L['KIOSK_NO']."',
          '".$_L['AGENT_ID']."',
          '".$_L['TOTPRICE']."',
          '".$_L['TOTCNT']."',
          '".$_L['AUTH']."',
          '".$_L['EASY']."',
          '".$_L['IS_MARKETING']."',
          '".$_L['BANK1']."',
          '".$_L['BANK2']."',
          '".$_L['BANK3']."',
          '".$_L['PARTNER_ID']."',
          '".$_L['REG_IP']."',
          'Mobile',
          NOW(),
          '".$_L['REFERRAL_NO']."',
          NOW()
        )
      ";
    }

    if ($_L['mode'] == 'update') {
      if ($_L['PASSWD']) $add_query .= "PASSWD = PASSWORD('{$_L['PASSWD']}'),";
      if ($_L['NAME']) $add_query .= "NAME = '{$_L['NAME']}',";
      if ($_L['BIRTH']) $add_query .= "BIRTH = '{$_L['BIRTH']}',";
      if ($_L['HP']) $add_query .= "HP = '{$_L['HP']}',";
      if ($_L['COUNTRY']) $add_query .= "COUNTRY = '{$_L['COUNTRY']}',";
      if ($_L['GENDER']) $add_query .= "GENDER = '{$_L['GENDER']}',";
      if ($_L['ENAME']) $add_query .= "ENAME = '{$_L['ENAME']}',";
      if ($_L['EMAIL']) $add_query .= "EMAIL = '{$_L['EMAIL']}',";
      $query = "
        UPDATE
          MEMBER
        SET
          {$add_query}
          IS_MARKETING = IF('{$_L['IS_MARKETING']}' = 'Y', '{$_L['IS_MARKETING']}', 'N')
        WHERE
          MEMBER_NO = '{$_L['MEMBER_NO']}'
      ";
    }

    if ($_L['mode'] == 'delete') {
      $query = "DELETE FROM MEMBER WHERE MEMBER_NO = '".$_L['MEMBER_NO']."'";
    }

    $db->query($query);
  }

  // 목록 불러오기
  function F_MEMBER_list($_L) {
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
    $page_info['total'] = $db->get_data_one("SELECT count(*) FROM MEMBER $where_query");

    // 위의 조건에 따라 목록 가져오기
    $query = "
      SELECT
        *,
        (select COMPANY from AGENT where USER_ID = MEMBER.AGENT_ID limit 1) as COMPANY
      FROM
        MEMBER
      $where_query
      $order_query
      LIMIT ".$count_now.",".$page_info['row']."
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
