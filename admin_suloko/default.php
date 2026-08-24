<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == "search_date") {
    $schdate = $VAL['schdate'];

    $time_set = setBetweenDate($schdate);

    $rtns['errNo'] = 0;
    $rtns['sdate'] = $time_set['sdate'];
    $rtns['edate'] = $time_set['edate'];

    echo json_encode($rtns);
    exit;
  } else if ($VAL['mode'] == "check_hp_member_all") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_MEMBER.php";

    if (count($chk_no) > 0) {
      $k = 0;
      $d = 0;
      $html = "";

      for ($i = 0; $i < count($chk_no); $i++) {
        $mb = F_MEMBER(array("mode"=>"read", "MEMBER_NO"=>$chk_no[$i]));

        if ($mb['HP'] != '') {
          $k++;
          $html .= '<tr>
                      <td>'.$k.'</td>
                      <td style="text-align:center;">'.$mb['NAME'].'</td>
                      <td>'.$mb['HP'].'</td>
                      <td><a class="btn btn-danger sms_hp_del" style="padding:0;cursor:pointer;color:#fff;">삭제</a></td>
                    </tr>';
        } else {
          $d++;
        }
      }
      echo json_encode(array("error"=>1, "html"=>$html, "msg"=>$d."명은 전화번호가 없습니다.", "cnt"=>$k, "dcnt"=>$d));
    } else {
    // echo json_encode(array("error"=>0,"msg"=>"수신번호가 없습니다."));
    }
    exit;
  } else if ($VAL['mode'] == "hp_member_all") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_MEMBER.php";

    $k = 0;
    $d = 0;
    $html = "";

    $list = $db->get_list("SELECT * FROM MEMBER");

    for ($i = 0; $i < count($list['MEMBER_NO']); $i++) {
      if ($list['HP'][$i] != '') {
        $k++;
        $html .= '<tr>
                    <td>'.$k.'</td>
                    <td style="text-align:center;">'.$list['NAME'][$i].'</td>
                    <td>'.$list['HP'][$i].'</td>
                    <td><a class="btn btn-danger sms_hp_del" style="padding:0;cursor:pointer;color:#fff;">삭제</a></td>
                  </tr>';
      } else {
        $d++;
      }
    }
    echo json_encode(array("error"=>1, "html"=>$html, "msg"=>$d."명은 전화번호가 없습니다.", "cnt"=>$k, "dcnt"=>$d));
    exit;
  } else if ($VAL['mode'] == "check_hp_member") {
    if ($VAL['hp'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"수신번호를 입력해 주세요."));
      exit;
    }
    $mb = $db->get_data("SELECT * FROM MEMBER WHERE HP='{$VAL['hp']}'");

    if (!isset($mb['MEMBER_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"등록되지 않은 회원입니다."));
      exit;
    }

    $VAL['no']++;
    $html = '<tr>
              <td>'.$VAL['no'].'</td>
              <td style="text-align:center;">'.$mb['NAME'].'</td>
              <td>'.$mb['HP'].'</td>
              <td><a class="btn btn-danger sms_hp_del" style="padding:0;cursor:pointer;color:#fff;">삭제</a></td>
            </tr>';
    echo json_encode(array("error"=>1, "html"=>$html));
    exit;
  } else if ($VAL['mode'] == "change_sms_templet") {
    if ($VAL['no'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"템플릿을 선택해 주세요."));
      exit;
    }

    $info = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$VAL['no']}'");

    if (!isset($info['TEMPLET_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"등록되지 않은 템플릿입니다."));
      exit;
    }

    $subject = $info['SUBJECT'];
    $content = $info['CONTENT'];

    echo json_encode(array("error"=>1, "subject"=>$subject, "content"=>$content));
    exit;
  } else if ($VAL['mode'] == 'sms_send') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $mem_arr = $VAL["mem_arr"];

    if ($VAL['sms_no'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"발신번호를 선택해 주세요."));
      exit;
    }

    if ($VAL['subject'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"제목을 입력해 주세요."));
      exit;
    }

    if ($VAL['content'] == '') {
      echo json_encode(array("error"=>0,"msg"=>"내용을 입력해 주세요."));
      exit;
    }

    // if ($VAL['all_mem'] == "Y") {
    //   $list = $db->get_list("SELECT HP FROM MEMBER");
    //   $mem_arr = $list["HP"];
    // }

    if (count($mem_arr) <= 0) {
      echo json_encode(array("error"=>0, "msg"=>"수신할 번호를 선택해 주세요."));
      exit;
    }

    // 발신번호 세팅
    $sms = $db->get_data("SELECT SENDER, HP FROM SMSCONFIG WHERE SMS_NO='{$VAL['sms_no']}'");
    $sms_sender = $sms['SENDER'];
    $fromHP = $sms['HP'];
    unset($sms);

    foreach ($mem_arr as $HP) {
      $mem         = $db->get_data("SELECT * FROM MEMBER WHERE HP='{$HP}'");
      $from        = $fromHP;
      $to          = $HP;
      $CODESK      = "S";
      $TEMPLET_NO  = $VAL['templet_no'];
      $MEMBER_NO   = $mem['MEMBER_NO'];
      $MEMBER_NAME = $mem['NAME'];
      $auth_num    = str_rand(6, "0123456789");
      $SUBJECT     = $subject;
      $SENDMSG     = $VAL['content'];
      $SUBJECT     = str_replace('{PHONENO}', $to, $SUBJECT);
      $SENDMSG     = addslashes($SENDMSG);
      $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
    }
    echo json_encode(array("error"=>1, "msg"=>"발송하였습니다."));
    exit;

  } else if ($VAL['mode'] == "load_first_sales_data") {
    $sdate    = date("Y-m-d")." 00:00:00";
    $y_sdate  = date("Y-m-d", strtotime("-1 day"))." 00:00:00";
    $yn_edate = date("Y-m-d H:i:s", strtotime("-1 day"));

    /* 오늘 첫결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS FIRST, COUNT(*) AS FIRST_CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND C.REG_DATE > '{$sdate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1
    ";

    /* 첫결제 기준을 최초결제 이후 1일간으로 변경
    $query  = "SELECT SUM(PRICE) AS FIRST, COUNT(*) AS FIRST_CNT
                FROM CASH AS C
                WHERE IS_USE='Y' AND C.REG_DATE > '".$sdate."'
                  AND DATEDIFF((SELECT MIN(REG_DATE) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE),C.REG_DATE) = 0";
    */

    $first_sales = $db->get_data($query);

    /* 오늘 재결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS RECUR, COUNT(*) AS RECUR_CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND C.REG_DATE > '{$sdate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) > 1
    ";

    /* 첫결제 기준을 최초결제 이후 1일간으로 변경
    $query  = "SELECT SUM(PRICE) AS RECUR, COUNT(*) AS RECUR_CNT
                FROM CASH AS C
                WHERE IS_USE='Y' AND C.REG_DATE > '".$sdate."'
                  AND DATEDIFF((SELECT MIN(REG_DATE) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE),C.REG_DATE) < 0";
    */

    $recur_sales = $db->get_data($query);

    $sum_sales     = $first_sales["FIRST"] + $recur_sales["RECUR"];
    $first_sales_p = $first_sales["FIRST"] / $sum_sales * 100;
    $recur_sales_p = $recur_sales["RECUR"] / $sum_sales * 100;

    /* 전일 첫결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS FIRST, COUNT(*) AS FIRST_CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND C.REG_DATE BETWEEN '{$y_sdate}' AND '{$yn_edate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1
    ";

    /* 전일 첫결제 기준을 최초결제 이후 1일간으로 변경
    $query  = "SELECT SUM(PRICE) AS FIRST, COUNT(*) AS FIRST_CNT
                FROM CASH AS C
                WHERE IS_USE='Y' AND C.REG_DATE BETWEEN '".$y_sdate."' AND '".$yn_edate."'
                  AND DATEDIFF((SELECT MIN(REG_DATE) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE),C.REG_DATE) = 0";
    */
    $y_first_sales = $db->get_data($query);

    /* 전일 재결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS RECUR, COUNT(*) AS RECUR_CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND C.REG_DATE BETWEEN '{$y_sdate}' AND '{$yn_edate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) > 1
    ";

    /* 전일 재결제 기준을 최초결제 이후 1일간으로 변경
    $query        = "SELECT SUM(PRICE) AS RECUR, COUNT(*) AS RECUR_CNT
                      FROM CASH AS C
                      WHERE IS_USE='Y' AND C.REG_DATE BETWEEN '".$y_sdate."' AND '".$yn_edate."'
                        AND DATEDIFF((SELECT MIN(REG_DATE) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE),C.REG_DATE) < 0";
    */
    $y_recur_sales = $db->get_data($query);

    $y_sum_sales     = $y_first_sales["FIRST"] + $y_recur_sales["RECUR"];
    $y_first_sales_p = $y_first_sales["FIRST"] / $y_sum_sales * 100;
    $y_recur_sales_p = $y_recur_sales["RECUR"] / $y_sum_sales * 100;

    /* 오늘의 첫결제 / 재결제 비율 */
    $p_first_sales = get_Percent($y_first_sales["FIRST"], $first_sales['FIRST']);
    $p_y_sales     = get_Percent($y_recur_sales["RECUR"], $recur_sales["RECUR"]);

    /* return value */
    $RES['first_sales']       = number_format($first_sales['FIRST']);
    $RES['first_sales_p']     = number_format($first_sales_p, 2);
    $RES['first_sales_cnt']   = number_format($first_sales['FIRST_CNT']);

    $RES['recur_sales']       = number_format($recur_sales['RECUR']);
    $RES['recur_sales_p']     = number_format($recur_sales_p, 2);
    $RES['recur_sales_cnt']   = number_format($recur_sales['RECUR_CNT']);

    $RES['y_first_sales']     = number_format($y_first_sales['FIRST']);
    $RES['y_first_sales_p']   = number_format($y_first_sales_p, 2);
    $RES['p_first_sales']     = index_act(number_format($p_first_sales['N']), "");
    $RES['y_first_sales_cnt'] = number_format($y_first_sales['FIRST_CNT']);

    $RES['y_recur_sales']     = number_format($y_recur_sales['RECUR']);
    $RES['y_recur_sales_p']   = number_format($y_recur_sales_p, 2);
    $RES['p_recur_sales']     = index_act(number_format($p_y_sales['N']), "");
    $RES['y_recur_sales_cnt'] = number_format($y_recur_sales['RECUR_CNT']);

    $RES['yn_edate']          = $yn_edate;

    echo json_encode(array("error"=>1, "RES"=>$RES));
    exit;

  } else if ($VAL['mode'] == "load_refer_first_sales_data") {
    $sdate0   = date("Y-m-1")." 00:00:00";
    $sdate    = date("Y-m-d")." 00:00:00";
    $edate    = date("Y-m-d")." 23:59:59";
    $y_sdate  = date("Y-m-d", strtotime("-1 day"))." 00:00:00";
    $y_edate  = date("Y-m-d", strtotime("-1 day"))." 23:59:59";
    $yn_edate = date("Y-m-d H:i:s", strtotime("-1 day"));

    /* IDX=14 로 고정 SLK-609 */
    /* 오늘 첫결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS PRICE, COUNT(*) AS CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND `REFERRAL_NO` = 14
        AND C.REG_DATE > '{$sdate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1
    ";
    $now_refer_first_order = $db->get_data($query);

    /* 전일 첫결제 금액, 건수 */
    $query = "
      SELECT SUM(PRICE) AS PRICE, COUNT(*) AS CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND `REFERRAL_NO` = 14
        AND C.REG_DATE BETWEEN '{$y_sdate}' AND '{$y_edate}'
        AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1
    ";
    $yes_refer_first_order = $db->get_data($query);

    $yes_refer_first_order_count_1  = get_Percent($yes_refer_first_order['CNT'], $now_refer_first_order['CNT']);
    $yes_refer_first_order_amount_1 = get_Percent($yes_refer_first_order['PRICE'], $now_refer_first_order['PRICE']);

    /* 당월 첫결제 금액, 건수 */
    /* 변경 쿼리가 당월에 경우만 시간이 더 오래 걸린다...
    $query  = "SELECT SUM(PRICE) AS PRICE, COUNT(*) AS CNT
                FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
                WHERE IS_USE='Y' AND `REFERRAL_NO` > 0 AND C.REG_DATE BETWEEN '".$sdate0."' AND '".$edate."'
                  AND (SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1";
    $month_refer_first_order = $db->get_data($query);
    */
    $query = "
      SELECT SUM( IF((SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1,1,0)) AS CNT
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND `REFERRAL_NO` = 14
        AND C.REG_DATE BETWEEN '{$sdate0}' AND '{$edate}'";
    $month_refer_first_order_count = $db->get_data($query);

    $query = "
      SELECT SUM( IF((SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = C.MEMBER_NO AND IS_USE='Y' AND REG_DATE <= C.REG_DATE) = 1,PRICE,0)) AS PRICE
      FROM CASH AS C INNER JOIN MEMBER AS M ON C.MEMBER_NO = M.MEMBER_NO
      WHERE IS_USE='Y'
        AND `REFERRAL_NO` = 14
        AND C.REG_DATE BETWEEN '{$sdate0}' AND '{$edate}'";
    $month_refer_first_order_amount = $db->get_data($query);

    /* return value */
    $RES['now_refer_first_order_count']    = number_format($now_refer_first_order['CNT']);
    $RES['now_refer_first_order_amount']   = number_format($now_refer_first_order['PRICE']);
    $RES['yes_refer_first_order_count']    = number_format($yes_refer_first_order['CNT']);
    $RES['yes_refer_first_order_amount']   = number_format($yes_refer_first_order['PRICE']);
    $RES['yes_refer_first_order_count_1']  = index_act(number_format($yes_refer_first_order_count_1["N"]), "");
    $RES['yes_refer_first_order_amount_1'] = index_act(number_format($yes_refer_first_order_amount_1["N"]), "");
    $RES['month_refer_first_order_count']  = number_format($month_refer_first_order_count['CNT']);
    $RES['month_refer_first_order_amount'] = number_format($month_refer_first_order_amount['PRICE']);
    $RES['yn_edate'] = $yn_edate;

    echo json_encode(array("error"=>1, "RES"=>$RES));
    exit;

  } else if ($VAL['mode'] == "send_push") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTIFICATION_LOG.php";

    $result = sendFcmPush($VAL['topic'], $VAL['subject'], $VAL['content'], $ICON_URL, $ACTION_URL);

    // 발송 로그 저장
    $res = json_decode($result);

    // 발송 결과 처리
    if (is_null($res)) { // curl 오류
      $is_success = 'N';
      $error_code = "500";
      $message    = $result; // 오류메세지를 리턴받음
    } else if (isset($res->error)) { // push 실패시
      $is_success = 'N';
      $error_code = $res->error->code;
      $message    = $res->error->status;
    } else {
      $is_success = 'Y';
      $error_code = NULL;
      $message    = NULL;
    }

    // 로그 저장
    $push_log = array();
    $push_log['mode'] = 'insert';
    $push_log['TYPE'] = 'M';
    $push_log['TOKEN'] = 0;
    $push_log['TOPIC'] = $VAL['topic'];
    $push_log['TITLE'] = $VAL['subject'];
    $push_log['CONTENT'] = $VAL['content'];
    $push_log['URL'] = $ACTION_URL;
    $push_log['IMG_URL'] = $ICON_URL;
    $push_log['IS_SUCCESS'] = $is_success;
    $push_log['ERROR_CODE'] = $error_code;
    $push_log['ERROR_MESSAGE'] = $message;
    F_NOTIFICATION_LOG($push_log);

    if ($is_success == 'Y') {
      echo json_encode(array("error"=>0, "msg"=>"푸시 발송에 성공 하였습니다."));
    } else {
      echo json_encode(array("error"=>1, "msg"=>"푸시 발송 실패!!"));
    }

    exit;

  } else if ($VAL['mode'] == "save_push_reservation") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTI_RESERVATION.php";

    if ($VAL['topic'] == '' || $VAL['subject'] == '' || $VAL['content'] == '' || $VAL['r_datetime'] == '') {
      echo json_encode(array("error"=>1,"msg"=>"필수값이 누락되었습니다."));
      exit;
    }

    $noti = array();
    $noti['mode'] = 'insert';
    $noti['STAFF_ID']    = $S_login['user_id'];
    $noti['RESERVED_AT'] = trim($VAL['r_datetime']);
    $noti['TYPE']        = "M";
    $noti['TOPIC']       = trim($VAL['topic']);
    $noti['TITLE']       = trim($VAL['subject']);
    $noti['CONTENT']     = trim($VAL['content']);

    $res = F_NOTI_RESERVATION($noti);

    if ($res) {
      echo json_encode(array("error"=>0, "msg"=>"푸시 발송이 예약 되었습니다."));
    } else {
      echo json_encode(array("error"=>1, "msg"=>"푸시 발송 예약 실패!!"));
    }

    exit;
  }
?>
