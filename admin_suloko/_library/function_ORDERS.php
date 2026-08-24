<?php

function getWeightedRandomResult(array $probabilities) {
  $validProbabilities = array_filter($probabilities, function ($p) {
    return $p > 0;
  });

  if (empty($validProbabilities)) {
    return 2; // 유효한 확률이 없으면 null 반환 또는 다른 처리
  }

  $rand = mt_rand(1, array_sum($validProbabilities));
  $cumulativeProbability = 0;

  foreach ($validProbabilities as $value => $probability) {
    $cumulativeProbability += $probability;
    if ($rand <= $cumulativeProbability) {
      return $value;
    }
  }

  // 이 부분은 원래 확률 합이 100이 아닌 경우를 대비한 안전 장치
  return array_key_last($validProbabilities);
}

function getValidRandomLeverage(array $probabilities) {
  while (true) {
    $result = getWeightedRandomResult($probabilities);
    if ($result !== null && $probabilities[$result] > 0) {
      return $result;
    }
    // $probabilities 배열의 모든 값이 0 이하인 경우 무한 루프를 방지하기 위한 안전 장치
    if (count(array_filter($probabilities, function ($p) { return $p > 0; })) === 0) {
      return 2; // 또는 다른 기본값이나 오류 처리
    }
  }
}


// 정보 처리
function F_ORDERS($_L) {
  global $db;

  $add_query = "";
  $_L['price'] = preg_replace("/[^0-9\-]/", "", $_L['price']);

  if ($_L['mode'] == 'read') {
    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO = '{$_L['ORDERS_NO']}'");
    $info = F_strip_slashes($info);
    return  $info;
  }

  $_L = F_add_slashes($_L);

  if ($_L['mode'] == 'insert') {
    $query = "
      INSERT INTO ORDERS(
        ORDERS_NO,
        USER_ID,
        KIOSK_NO,
        AGENT_NO,
        AGENT_NO2,
        PATNER_NO,
        GOODS_NO,
        HP,
        EMAIL,
        PAYMENT,
        CASH,
        WINCASH,
        POINT,
        IPOINT,
        VAT,
        AUTHNUM,
        CARDCD,
        UNIQNUM,
        GUBUN,
        GUBUNO,
        DRAWNUM,
        PLAYDATE,
        GAMECNT,
        BALL1,
        BALL2,
        BALL3,
        BALL4,
        BALL5,
        CODE1,
        IMG_PATH,
        IMG_YN,
        IMG_DATE,
        PRINT_YN,
        SIGN_YN,
        WIN_YN,
        WIN1,
        WIN2,
        WIN3,
        WIN4,
        WIN5,
        WIN_MONEY,
        WIN_MONEY_USD,
        INVOCE_YN,
        WIN_MONEY_YN,
        SELLER_GUBUN,
        DATE,
        REG_DATE
      ) VALUES (
        '{$_L['ORDERS_NO']}',
        '{$_L['USER_ID']}',
        '{$_L['KIOSK_NO']}',
        '{$_L['AGENT_NO']}',
        '{$_L['AGENT_NO2']}',
        '{$_L['PATNER_NO']}',
        '{$_L['GOODS_NO']}',
        '{$_L['HP']}',
        '{$_L['EMAIL']}',
        '{$_L['PAYMENT']}',
        '{$_L['CASH']}',
        '{$_L['WINCASH']}',
        '{$_L['POINT']}',
        '{$_L['IPOINT']}',
        '{$_L['VAT']}',
        '{$_L['AUTHNUM']}',
        '{$_L['CARDCD']}',
        '{$_L['UNIQNUM']}',
        '{$_L['GUBUN']}',
        '{$_L['GUBUNO']}',
        '{$_L['DRAWNUM']}',
        '{$_L['PLAYDATE']}',
        '{$_L['GAMECNT']}',
        '{$_L['BALL1']}',
        '{$_L['BALL2']}',
        '{$_L['BALL3']}',
        '{$_L['BALL4']}',
        '{$_L['BALL5']}',
        '{$_L['CODE1']}',
        '{$_L['IMG_PATH']}',
        '{$_L['IMG_YN']}',
        '{$_L['IMG_DATE']}',
        '{$_L['PRINT_YN']}',
        '{$_L['SIGN_YN']}',
        '{$_L['WIN_YN']}',
        '{$_L['WIN1']}',
        '{$_L['WIN2']}',
        '{$_L['WIN3']}',
        '{$_L['WIN4']}',
        '{$_L['WIN5']}',
        '{$_L['WIN_MONEY']}',
        '{$_L['WIN_MONEY_USD']}',
        '{$_L['INVOCE_YN']}',
        '',
        '{$_L['SELLER_GUBUN']}',
        '{$_L['DATE']}',
        NOW()
      )";


    if($_L['GUBUN']=="MM"){ //메가밀리언만

      $balls = [];
      for ($i = 1; $i <= 5; $i++) {
        if (!empty($_L["BALL{$i}"])) {
          $balls[] = $_L["BALL{$i}"];
        }
      }
      $count = count($balls);

        $ORDER_MEGA_MULTIPLIER = "INSERT INTO ORDER_MULTIPLIER SET ";
        $ORDER_MEGA_MULTIPLIER.= "ORDERS_NO = {$_L['ORDERS_NO']} ";

      for($a=1;$a <=$count;$a++){
        $config2 = $db->get_data("select * from SITE");
        $probabilities = [
            2 => $config2['레버리지2'],
            3 => $config2['레버리지3'],
            4 => $config2['레버리지4'],
            5 => $config2['레버리지5'],
            10 => $config2['레버리지10'],
        ];

        $randomResult = getValidRandomLeverage($probabilities);
        //echo $config['레버리지'.$randomResult]."\n";
        if($config2['레버리지2'] < 0){
          $db->query("update SITE set 레버리지2 = 0");
        }
        if($config2['레버리지3'] < 0){
          $db->query("update SITE set 레버리지3 = 0");
        }
        if($config2['레버리지4'] < 0){
          $db->query("update SITE set 레버리지4 = 0");
        }
        if($config2['레버리지5'] < 0){
          $db->query("update SITE set 레버리지5 = 0");
        }
        if($config2['레버리지10'] < 0){
          $db->query("update SITE set 레버리지10 = 0");
        }

        $map = [
            1 => "A",
            2 => "B",
            3 => "C",
            4 => "D",
            5 => "E",
        ];

        $alpha = isset($map[$a]) ? $map[$a] : null; // 값 없으면 null

        $ORDER_MEGA_MULTIPLIER.= ", MULTI_".$alpha." = {$randomResult} ";
        syslog(LOG_DEBUG, $ORDER_MEGA_MULTIPLIER);


        // $레버리지 = db_select('select 레버리지2, 레버리지3, 레버리지4, 레버리지5, 레버리지10 from lr_config ');
        $csql = "update SITE set 레버리지{$randomResult} = 레버리지{$randomResult} - 1";
        $db->query($csql);

        $config3 = $db->get_data("select * from SITE");;
        if($config3['레버리지2'] <= 0 and $config3['레버리지3'] <= 0 and $config3['레버리지4'] <= 0 and
            $config3['레버리지5'] <= 0 and $config3['레버리지10'] <= 0){

          $config_sql = "update SITE set ";
          $config_sql.= "레버리지2 = {$config3['set레버리지2']}, ";
          $config_sql.= "레버리지3 = {$config3['set레버리지3']}, ";
          $config_sql.= "레버리지4 = {$config3['set레버리지4']}, ";
          $config_sql.= "레버리지5 = {$config3['set레버리지5']}, ";
          $config_sql.= "레버리지10 = {$config3['set레버리지10']} ";
          $db->query($config_sql);
        }
      }

      syslog(LOG_DEBUG, $ORDER_MEGA_MULTIPLIER);
      $db->query($ORDER_MEGA_MULTIPLIER);



    }


  }

  if ($_L['mode'] == 'update') {
    if ($_L['file1']) $add_query .= "file1 = '{$_L['file1']}',";

    $query = "
      UPDATE ORDERS SET
        {$add_query}
        USER_ID       = '{$_L['USER_ID']}',
        KIOSK_NO      = '{$_L['KIOSK_NO']}',
        AGENT_NO      = '{$_L['AGENT_NO']}',
        AGENT_NO2     = '{$_L['AGENT_NO2']}',
        PATNER_NO     = '{$_L['PATNER_NO']}',
        GOODS_NO      = '{$_L['GOODS_NO']}',
        HP            = '{$_L['HP']}',
        EMAIL         = '{$_L['EMAIL']}',
        PAYMENT       = '{$_L['PAYMENT']}',
        VAT           = '{$_L['VAT']}',
        AUTHNUM       = '{$_L['AUTHNUM']}',
        CARDCD        = '{$_L['CARDCD']}',
        UNIQNUM       = '{$_L['UNIQNUM']}',
        GUBUN         = '{$_L['GUBUN']}',
        GUBUNO        = '{$_L['GUBUNO']}',
        DRAWNUM       = '{$_L['DRAWNUM']}',
        PLAYDATE      = '{$_L['PLAYDATE']}',
        GAMECNT       = '{$_L['GAMECNT']}',
        BALL1         = '{$_L['BALL1']}',
        BALL2         = '{$_L['BALL2']}',
        BALL3         = '{$_L['BALL3']}',
        BALL4         = '{$_L['BALL4']}',
        BALL5         = '{$_L['BALL5']}',
        IMG_PATH      = '{$_L['IMG_PATH']}',
        IMG_YN        = '{$_L['IMG_YN']}',
        IMG_DATE      = '{$_L['IMG_DATE']}',
        PRINT_YN      = '{$_L['PRINT_YN']}',
        SIGN_YN       = '{$_L['SIGN_YN']}',
        WIN_YN        = '{$_L['WIN_YN']}',
        WIN1          = '{$_L['WIN1']}',
        WIN2          = '{$_L['WIN2']}',
        WIN3          = '{$_L['WIN3']}',
        WIN4          = '{$_L['WIN4']}',
        WIN5          = '{$_L['WIN5']}',
        WIN_MONEY     = '{$_L['WIN_MONEY']}',
        WIN_MONEY_USD = '{$_L['WIN_MONEY_USD']}',
        INVOCE_YN     = '{$_L['INVOCE_YN']}',
        WIN_MONEY_YN  = '{$_L['WIN_MONEY_YN']}',
        SELLER_GUBUN  = '{$_L['SELLER_GUBUN']}',
        DATE          = '{$_L['DATE']}'
      WHERE
        ORDERS_NO = '{$_L['ORDERS_NO']}'
    ";
  }

  if ($_L['mode'] == 'delete') {
    // $query = "DELETE FROM ORDERS WHERE ORDERS_NO = '{$_L['ORDERS_NO']}'";
    $query = "UPDATE ORDERS SET SIGN_YN = 'C' WHERE ORDERS_NO = '{$_L['ORDERS_NO']}'";
  }

  $db->query($query);
}

// 목록 불러오기
function F_ORDERS_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    if ($_L['find_object'] == 'BALL1') {
      $add_query .= " AND {$_L['find_object']} LIKE '{$_L['find_text']}%' ";
    } else {
      $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%' ";
    }
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

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']}";
  } else {
    $order_query = "ORDER BY ORDERS_NO DESC";
  }

  // 페이지 네비게이션
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = "WHERE ".substr($add_query, 4, $querylen-4);
  } else {
    // 미국 관리자는 1,2등 당첨금 제외
    if ($S_login['level'] == 1) {
      $where_query = "WHERE `WIN_MONEY_USD` < 1000001";
    }
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows = $_L['page'] * $_L['row'];
  $page_info['total'] = $db->get_data_one("SELECT count(*) FROM ORDERS $where_query");

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      *,
      (SELECT COMPANY FROM AGENT WHERE AGENT_NO = ORDERS.AGENT_NO LIMIT 1) AS COMPANY,
      (SELECT NAME FROM MEMBER WHERE USER_ID = ORDERS.USER_ID LIMIT 1) AS NAME
    FROM
      ORDERS
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['total'] = $page_info['total'];
  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['ORDERS_NO'])) {
    $list['count'] = count($list['ORDERS_NO']);
  }
  return $list;
}

// 목록 불러오기
function F_ORDERS_MEMBER_list($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    if ($_L['find_object'] == 'o.BALL1') {
      $add_query .= " AND {$_L['find_object']} LIKE '{$_L['find_text']}%'";
    } else {
      $add_query .= " AND {$_L['find_object']} LIKE '%{$_L['find_text']}%'";
    }
  }

  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}'";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']}";
  } else {
    $order_query = "ORDER BY ORDERS_NO DESC";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = " WHERE ".substr($add_query, 4, $querylen-4);
  } else {
    // 미국 관리자는 1,2등 당첨금 제외
    if ($S_login['level'] == 1) {
      $where_query = "WHERE `WIN_MONEY_USD` < 1000001";
    }
  }

  $page_info['cur'] = $_L['page'];
  $page_info['row'] = $_L['row'];
  $count_now = $page_info['row'] * ($page_info['cur'] - 1);
  $top_rows =  $_L['page'] * $_L['row'];
  // 전체 카운트 쿼리시 속도 차이 문제로 조건 상태에 따라 쿼리를 분리함
  if (strpos($where_query, "m.")) {
    $query = "SELECT count(*) FROM ORDERS AS o LEFT OUTER JOIN MEMBER AS m ON m.USER_ID = o.USER_ID $where_query";
  } else {
    $query = "SELECT count(*) FROM ORDERS AS o $where_query";
  }
  $page_info['total'] = $db->get_data_one($query);

  // 위의 조건에 따라 목록 가져오기
  $query = "
    SELECT
      o.*,
      (SELECT COMPANY FROM AGENT WHERE AGENT_NO = o.AGENT_NO LIMIT 1) AS COMPANY,
      m.NAME AS NAME
    FROM
      ORDERS AS o
        LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
    $order_query
    LIMIT {$count_now}, {$page_info['row']}
  ";
  $list = $db->get_list($query);

  $list['row'] = $_L['row'];
  $list['count'] = 0;

  if (is_array($list['ORDERS_NO'])) {
    $list['count'] = count($list['ORDERS_NO']);
  }

  $list['total'] = $page_info['total'];
  $list['page_string'] = print_page_num($page_info); // 페이지 번호 출력
  $list['page_string_v2'] = print_page_num1($page_info); // 페이지 번호 출력 (modal)
  return $list;
}

// summary 불러오기 (SLK-465)
function F_ORDERS_MEMBER_list_summary($_L) {
  global $db;

  $add_query = "";
  $wheres = isset($_L['wheres']) ? $_L['wheres'] : "";
  $_L = F_add_slashes($_L);

  if ($_L['find_object'] != null && $_L['find_text'] != null) {
    $add_query .= " AND {$_L['find_object']} LIKE  '%{$_L['find_text']}%'";
  }
  if ($_L['add_query']) {
    $add_query .= stripslashes($_L['add_query']);
  }
  if (isset($_L['s_area'])) {
    $add_query .= " AND area = '{$_L['s_area']}'";
  }
  if ($wheres) {
    $add_query .= $wheres;
  }

  // 정렬기준
  if ($_L['order'] != null) {
    $order_query = "ORDER BY {$_L['order']}";
  } else {
    $order_query = "ORDER BY ORDERS_NO DESC ";
  }

  // 페이지 네비게이션 표시
  if (!$_L['page']) {
    $_L['page'] = 1;
  }

  if ($add_query) {
    $querylen = strlen($add_query);
    $where_query = " WHERE ".substr($add_query, 4, $querylen-4);
  }

  $query = "
    SELECT
      COUNT(*) AS totalCNT_ticket, SUM(GAMECNT) AS totalCNT_games
    FROM
      ORDERS AS o
    LEFT JOIN
      MEMBER AS m ON m.USER_ID = o.USER_ID
    $where_query
  ";
  $list = $db->get_data($query);

  $info['totalCNT_ticket'] = $list['totalCNT_ticket'];
  $info['totalCNT_games'] = $list['totalCNT_games'];
  return $info;
}
?>
