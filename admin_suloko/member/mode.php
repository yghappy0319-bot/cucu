<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  $VAL = $_POST;

  if ($VAL['mode'] == 'getMemberDetail') {
    if ($VAL['no'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"회원을 선택해 주세요."));
      exit;
    }

    $user_id = $db->get_data_one("SELECT USER_ID FROM MEMBER WHERE MEMBER_NO='{$VAL['no']}' LIMIT 1");

    if (!isset($user_id)) {
      // USER_ID로 조회 추가
      $user_id = $db->get_data_one("SELECT USER_ID FROM MEMBER WHERE USER_ID='{$VAL['no']}' LIMIT 1");
    }

    if (!isset($user_id)) {
      echo json_encode(array("error"=>0, "msg"=>"존재하지 않은 회원입니다."));
      exit;
    }

    $info = $db->get_data("
      SELECT *
      FROM
        MEMBER AS M
          LEFT JOIN
        (SELECT USER_ID AS ID, SUM(PRICE) AS PRICE FROM CASH WHERE IS_USE = 'Y' GROUP BY ID) AS C ON M.USER_ID = C.ID
      WHERE
        M.USER_ID='{$user_id}' LIMIT 1
    ");

    $info['error'] = 1;
    $info['msg'] = "";
    $info['country_html'] = option_make($country_op, $info['COUNTRY']);
    $info['gender_html'] = option_make($gender_op, $info['GENDER']);
    $info['level_html'] = option_make($mlevel_op, $info['LEVEL']);
    $info['marketing_html'] = option_make($yn_op, $info['IS_MARKETING']);
    $info['CASH'] = number_format($info['CASH']);
    $info['WINCASH'] = number_format($info['WINCASH']);
    $info['IPOINT'] = number_format($info['IPOINT']);
    $info['POINT'] = number_format($info['POINT']);
    $info['PRICE'] = number_format($info['PRICE']);

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberGameHistory') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;
    $add_query = " AND o.USER_ID='{$VAL['user_id']}' AND o.Sign_YN != 'C'";

    $list = F_ORDERS_MEMBER_LIST(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => "",
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if (!isset($list['ORDERS_NO'])) {
      $list['ORDERS_NO'] = array();
    }

    for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
      $no = $list['total'] - (($page - 1) * $list['row']) - $i;
      $ball_html = "";
      $win_chk = "낙첨";
      $scanImgYn = "Y";

      for ($s = 1; $s < 6; $s++) {
        if ($list['BALL'.$s][$i] != "") {
          $ball = explode(",", $list['BALL'.$s][$i]);
          $ball_html .= $ball[0].",".$ball[1].",".$ball[2].",".$ball[3].",".$ball[4]." / ".$ball[5]."<br>";
        }
      }

      if ($list['WIN_YN'][$i] == "Y") {
        $win_chk = "당첨";
      } else if ($list['WIN_YN'][$i] == "R") {
        $win_chk = "미추첨";
      }

      if ($list['WIN_MONEY_YN'][$i] == "") {
        $list['WIN_MONEY_YN'][$i] = "N";
      }

      if ($list['IMG_YN'][$i] == "") {
        $scanImgYn = "N";
      }

      $rank = "";
      for ($k = 1; $k <= 5; $k++) {
        $tmp = "WIN".$k;
        if ($list[$tmp][$i]) {
          $rank .= $list[$tmp][$i]."등 ";
        }
      }

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.$ball_op[$list['GUBUN'][$i]].'</td>
          <td>'.$ball_html.'</td>
          <td>'.$gubuno_op[$list['GUBUNO'][$i]].'</td>
          <td>'.$rank .$win_chk.'</td>
          <td>'.number_format($list['WIN_MONEY'][$i]).'</td>
          <td>'.$scanImgYn.'</td>
        </tr>
      ';
    }

    if (count($list['ORDERS_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="8">내역이 없습니다</td></tr>';
    }

    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberCardHistory') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;
    $add_query = " AND c.USER_ID='{$VAL['user_id']}'";

    $list = F_CASH_MEMBER_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] <= 0) {
      $list['CASH_NO'] = array();
    }

    for ($i = 0; $i < count($list['CASH_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;
      $tp	=	"승인";
      if ($list['IS_USE'][$i] == 'N') {
        $tp	=	"취소";
      }
      // 할부 기간
      $quota = "일시불";
      if ($list['QUOTA'][$i] != '00') {
        $quota = $list['QUOTA'][$i] .= "개월";
      }

      $NAME = $list['NAME'][$i];
      $HP   = $list['HP'][$i];

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.number_format($list['PRICE'][$i]).'</td>
          <td>'.$list['CARDNAME'][$i].'</td>
          <td>'.$list['CARDNO'][$i].'</td>
          <td>'.$list['AUTHNUM'][$i].'</td>
          <td>'.$quota.'</td>
          <td>'.$list['SELLER_GUBUN'][$i].'</td>
          <td>'.$tp.'</td>
        </tr>
      ';
    }

    if (count($list['CASH_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="9">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberBankHistory') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND c.USER_ID='{$VAL['user_id']}'";

    $list = F_CASH_MEMBER_list_BANK(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] <= 0) {
      $list['CASH_NO'] = array();
    }

    for ($i = 0; $i < count($list['CASH_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      $tp = "완료";
      if ($list["IS_USE"][$i] == "N") {
        $tp = "대기";
      } else if ($list["IS_USE"][$i] == "C") {
        $tp = "취소";
      }

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.(($list["IS_USE"][$i] == "Y") ? $list["CON_REG_DATE"][$i] : (($list["IS_USE"][$i] == "C") ? $list["CANCAL_DATE"][$i] : "")).'</td>
          <td>'.number_format($list['PRICE'][$i]).'</td>
          <td>'.number_format($list["CASH"][$i]).'</td>
          <td>'.$list['SELLER_GUBUN'][$i].'</td>
          <td>'.$tp.'</td>
        </tr>
      ';
    }

    if (count($list['CASH_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="7">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberCashHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND m.USER_ID='{$VAL['user_id']}' AND (o.SIGN_YN IS NULL OR o.SIGN_YN != 'C')";

    $list = F_T_CASH_LOG_join_ORDERS_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0; $i < count($list['CASH_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      $price  = $list['PRICE'][$i];  // 총 충전금액
      $cash   = $list['CASH'][$i];   // 적립캐시
      $o_cash = $list['O_CASH'][$i]; // 보유캐시
      $n_cash = $list['N_CASH'][$i]; // 잔여캐시

      $MEMO = $list['MEMO'][$i];
      // 메모 추가 정보
      if ($list['ORDERS_NO'][$i] > 0) {
        if ($list['CASH'][$i] != ($list['GAMECNT'][$i] * 6000)) {
          $list['GAMECNT'][$i] = $db->get_data_one("SELECT SUM(GAMECNT) FROM ORDERS WHERE UNIQNUM = '{$list['ORDERS_NO'][$i]}'");
        }
        $MEMO .= "<br>(".$ball_op[$list['GUBUN'][$i]].", ".$list['DRAWNUM'][$i]."회차, ".$list['GAMECNT'][$i]."게임)";
      }

      if ($list['CASH_NO'][$i] > 0) {
        if ($list['STATUS'][$i] == "M") {
          $price = "-".number_format($price);
        } else {
          $price = number_format($price);
        }
      } else {
        $price = "-";
      }

      $pcash = "";
      $mcash = "";
      if ($list['STATUS'][$i] == 'P') $pcash = "+".number_format($cash);
      if ($list['STATUS'][$i] == 'M') $mcash = "-".number_format($cash);

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td class="text-end">'.$price.'</td>
          <td class="text-end">'.number_format($o_cash).'</td>
          <td class="text-end text-red">'.$pcash.'</td>
          <td class="text-end text-blue">'.$mcash.'</td>
          <td class="text-end number-font">'.$n_cash.'</td>
          <td>'.$MEMO.'</td>
        </tr>
      ';
    }

    if (count($list['LOG_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="8">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberWinmoneyHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_WCASH_LOG.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND m.USER_ID='{$VAL['user_id']}' AND o.SIGN_YN != 'C'";

    $list = F_T_WCASH_LOG_join_ORDERS_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0 ; $i < count($list['LOG_NO']) ; $i++) {
      $no     = $list['total'] - (($page-1) * $list['row']) - $i;

      $cash   = $list['WCASH'][$i];   // 사용당첨금
      $o_cash = $list['O_WCASH'][$i]; // 지급,차감전당첨금
      $n_cash = $list['N_WCASH'][$i]; // 지급,차감후당첨금
      $order  = $list['ORDERS_NO'][$i] > 0 ? $list['ORDERS_NO'][$i] : "";
      $invoce = $list['INVOCE_NO'][$i] > 0 ? $list['INVOCE_NO'][$i] : "";

      $MEMO = $list['MEMO'][$i];
      // 메모 추가 정보
      if ($list['ORDERS_NO'][$i] > 0) {
        $MEMO .= "<br>(".$ball_op[$list['GUBUN'][$i]].", ".$list['DRAWNUM'][$i]."회차, ".$list['GAMECNT'][$i]."게임)";
      }

      $pcash = "";
      $mcash = "";
      if ($list['STATUS'][$i] == 'P') $pcash = "+".number_format($cash);
      if ($list['STATUS'][$i] == 'M') $mcash = "-".number_format($cash);

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.$order.$invoce.'</td>
          <td class="text-end">'.number_format($o_cash).'</td>
          <td class="text-end text-red">'.$pcash.'</td>
          <td class="text-end text-blue">'.$mcash.'</td>
          <td class="text-end number-font">'.$n_cash.'</td>
          <td>'.$MEMO.'</td>
        </tr>
      ';
    }

    if (count($list['LOG_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="8">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberMileageHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_IPOINT_LOG.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND m.USER_ID='{$VAL['user_id']}' AND o.SIGN_YN != 'C'";

    $list = F_T_IPOINT_LOG_join_ORDERS_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0; $i < count($list['LOG_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      $ipoint   = $list['IPOINT'][$i];   // 적립,사용마일리지
      $o_ipoint = $list['O_IPOINT'][$i]; // 보유마일리지
      $n_ipoint = $list['N_IPOINT'][$i]; // 잔여마일리지

      $MEMO = $list['MEMO'][$i];
      // 메모 추가 정보
      if ($list['ORDERS_NO'][$i] > 0) {
        $MEMO .= "<br>(".$ball_op[$list['GUBUN'][$i]].", ".$list['DRAWNUM'][$i]."회차, ".$list['GAMECNT'][$i]."게임)";
      }

      $pipoint = "";
      $mipoint = "";
      if ($list['STATUS'][$i] == 'P') $pipoint = "+".number_format($ipoint);
      if ($list['STATUS'][$i] == 'M') $mipoint = "-".number_format($ipoint);

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td class="text-end">'.number_format($o_ipoint).'</td>
          <td class="text-end text-red">'.$pipoint.'</td>
          <td class="text-end text-blue">'.$mipoint.'</td>
          <td class="text-end number-font">'.number_format($n_ipoint).'</td>
          <td>'.$MEMO.'</td>
        </tr>
      ';
    }

    if (count($list['LOG_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="7">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);

  } else if ($VAL['mode'] == 'getMemberPointHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND m.USER_ID='{$VAL['user_id']}' AND (l.ORDERS_NO = 0 OR o.SIGN_YN != 'C')";

    $list = F_T_POINT_LOG_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0; $i < count($list['LOG_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      $coupon = $db->get_data("SELECT * FROM COUPON WHERE COUPON_NO='{$list['COUPON_NO'][$i]}'");

      $coupon_grpcode = $coupon['GRPCODE'];
      $coupon_name = $coupon['NUM'];
      $coupon_price = number_format($coupon['PRICE']);
      $mem_point = number_format($list['N_POINT'][$i]);

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.$coupon_grpcode.'</td>
          <td>'.$coupon_name.'</td>
          <td class="text-end">'.number_format($list['POINT'][$i]).'</td>
          <td class="text-end">'.$mem_point.'</td>
          <td>'.$list['MEMO'][$i].'</td>
        </tr>
      ';
    }

    if (count($list['LOG_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="7">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);
  } else if ($VAL['mode'] == 'getMemberRefundHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_INVOCE.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND I.USER_ID='{$VAL['user_id']}'";

    $list = F_INVOCE_list(array(
      "row"         => $limit,
      "page"        => $page,
      "order"       => $order,
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['INVOCE_NO'] = array();
    }

    for ($i = 0; $i < count($list['INVOCE_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      if ($list['MONEY'][$i] == '') {
        $list['MONEY'][$i] = 0;
      }

      if ($list['MONEY2'][$i] == '') {
        $list['MONEY2'][$i] = 0;
      }

      $invoice = $list['MONEY'][$i] - $list['MONEY2'][$i];

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td class="text-end">'.number_format($list['MONEY'][$i]).'</td>
          <td class="text-end">'.number_format($invoice).'</td>
          <td class="text-end">'.number_format($list['MONEY2'][$i]).'</td>
          <td>'.$list['BANK3'][$i].'</td>
          <td>'.$list['BANK1'][$i].'</td>
          <td>'.$list['BANK2'][$i].'</td>
          <td class="text-end">'.$withdraw_op[$list['STATUS'][$i]].'</td>
        </tr>
      ';
    }

    if (count($list['INVOCE_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="9">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);
  } else if ($VAL['mode'] == 'getMemberQnaHistory') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_BBS.php";

    $list_html = "";
    $page_html = "";

    $page = 1;
    $page = ($VAL['page']!='') ? $VAL['page'] : 1;
    $limit = 5;

    $add_query = " AND USER_ID='{$VAL['user_id']}'";

    $list = F_BBS_list(array(
      "row"               => $limit,
      "page"              => $page,
      "order"             => $order,
      "find_text"         => $find_text,
      "find_object"       => $find_object,
      "find_object_type"  => $find_object_type,
      "find_object_reply" => $find_object_reply,
      "add_query"         => $add_query,
    ));

    if ($list['total'] == 0) {
      $list['BBS_NO'] = array();
    }

    for ($i = 0; $i < count($list['BBS_NO']); $i++) {
      $no = $list['total'] - (($page-1) * $list['row']) - $i;

      $isReply = "미등록";
      if ($list['REPLY'][$i]) {
        $isReply = "등록";
      }

      if ($list['REPLY_YN'][$i] == "N") {
        $isOpen = "미공개";
      } else {
        $isOpen = "공개";
      }

      $list_html .= '
        <tr class="bg-white text-center">
          <td>'.$no.'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td>'.$list['QNA_TYPE'][$i].'</td>
          <td class="text-start" data-bs-toggle="tooltip" title="'.$list['SUBJECT'][$i].'">'.str_han_cut_utf($list['SUBJECT'][$i], 10).'</td>
          <td class="text-start" data-bs-toggle="tooltip" title="'.$list['CONTENT'][$i].'">'.str_han_cut_utf($list['CONTENT'][$i], 20).'</td>
          <td>'.$isOpen.'</td>
          <td>'.$isReply.'</td>
          <td class="text-start" data-bs-toggle="tooltip" title="'.$list['REPLY'][$i].'">'.str_han_cut_utf($list['REPLY'][$i], 20).'</td>
          <td>'.$list['USER_READ_DATE'][$i].'</td>
        </tr>
      ';
    }

    if (count($list['BBS_NO']) > 0) {
      $page_html = $list['page_string_v2'];
    } else {
      $list_html .= '<tr class="text-center"><td colspan="9">내역이 없습니다</td></tr>';
    }

    $info = array();
    $info['page_html'] = $page_html;
    $info['list_html'] = $list_html;

    echo json_encode($info);
  } else if ($VAL['mode'] == "updateMemberInfo") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    $data = json_decode($_POST['data'], true);

    $member = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$data['USER_ID']}'");

    $query = "
      UPDATE
        MEMBER
      SET
        NAME = '{$data['NAME']}',
        BIRTH = '{$data['BIRTH']}',
        COUNTRY = '{$data['COUNTRY']}',
        GENDER = '{$data['GENDER']}',
        HP = '{$data['HP']}',
        LEVEL = {$data['LEVEL']},
        EMAIL = '{$data['EMAIL']}',
        IS_MARKETING = '{$data['IS_MARKETING']}'
      WHERE
        USER_ID = '{$data['USER_ID']}'
      ";
    $db->query($query);

    // 변경 이력 저장
    $changes = array();
    foreach ($data as $key => $value) {
      if ($key !== "USER_ID") {
        if (isset($member[$key]) && $member[$key] != $value) {
          $changes[$key] = array("old" => $member[$key], "new" => $value);
        }
      }
    }

    if (!empty($changes)) {
      foreach ($changes as $field => $change) {
        $sql = "
          INSERT INTO MEMBER_CHANGE_LOG (
            USER_ID, STAFF_ID, FIELD, OLD_VALUE, NEW_VALUE
          ) VALUES (
            '{$data['USER_ID']}', '{$S_login['user_id']}', '{$field}', '{$change['old']}', '{$change['new']}'
          )
        ";
        $db->query($sql);
      }
    }

    echo json_encode(array("error"=>1, "msg"=>"수정했습니다."));
    exit;
  } else if ($VAL['mode'] == "updateMemberPassword") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"아이디를 확인해 주세요."));
      exit;
    }

    if ($VAL['pwd'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"비밀번호를 생성해 주세요."));
      exit;
    }

    $sql = "
      UPDATE MEMBER
      SET PASSWD = PASSWORD('{$VAL['pwd']}')
      WHERE USER_ID = '{$VAL['user_id']}'
    ";
    $db->query($sql);

    // 패스워드 변경 내역 저장
    $sql = "
      INSERT INTO MEMBER_CHANGE_LOG (
        USER_ID, STAFF_ID, FIELD, OLD_VALUE, NEW_VALUE
      ) VALUES (
        '{$VAL['user_id']}', '{$S_login['user_id']}', 'PASSWD', '******', '******'
      )
    ";
    $db->query($sql);

    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$VAL['user_id']}' LIMIT 1");
    $TEMPLET_NO = 38;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");
    $from = $fromHP;
    $to = $mem["HP"];
    $CODESK = "S";
    $MEMBER_NO = $mem['MEMBER_NO'];
    $MEMBER_NAME = $mem['NAME'];
    $SUBJECT = $SMSTEMPLET['SUBJECT'];
    $SENDMSG = $SMSTEMPLET['CONTENT'];
    $SENDMSG = str_replace('{{MEMBER_NAME}}', $MEMBER_NAME, $SENDMSG);
    $SENDMSG = str_replace('{{MEMBER_PWD}}', $VAL['pwd'], $SENDMSG);
    $SENDMSG = addslashes($SENDMSG);
    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    echo json_encode(array("error"=>1, "msg"=>"비밀번호를 전송했습니다."));
    exit;
  }
?>
