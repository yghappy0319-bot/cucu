<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  $VAL = $_POST;

  if ($VAL['mode'] == 'all_del') {
    if ($VAL['gubun'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"잘못된 접근입니다."));
      exit;
    }

    $s_type = $VAL['gubun'];
    $func_name = "F_".$s_type;

    include $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $VAL = array("mode"=>"delete", $s_type."_NO"=>$chk_no[$i]);
        $info = $func_name(array("mode"=>"read", $s_type."_NO"=>$chk_no[$i]));

        for ($s = 1; $s < 10; $s++) {
          if ($info['file'.$s] == '') {
            continue;
          }
          $link = $_SERVER['DOCUMENT_ROOT']."/upload/".$s_type."/".$info['file'.$s];
          @unlink($link);
        }
        $func_name($VAL);
      }
      echo json_encode(array("error"=>1,"msg"=>"삭제하였습니다."));
    } else {
      echo json_encode(array("error"=>0,"msg"=>"삭제할 항목이 없습니다."));
    }
  } else if ( $VAL['mode'] == 'all_out_member_del') {
    if ($VAL['gubun'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"잘못된 접근입니다."));
      exit;
    }
    $s_type = $VAL['gubun'];
    $func_name = "F_".$s_type;

    include $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $VAL = array("mode"=>"delete", "MEMBER_NO"=>$chk_no[$i]);
        $info = $func_name(array("mode"=>"read", "MEMBER_NO"=>$chk_no[$i]));

        for ($s = 1; $s < 10; $s++) {
          if ($info['file'.$s] == '') {
            continue;
          }
          $link = $_SERVER['DOCUMENT_ROOT']."/upload/".$s_type."/".$info['file'.$s];
          @unlink($link);
        }
        $func_name($VAL);
      }
      echo json_encode(array("error"=>1,"msg"=>"삭제하였습니다."));
    } else {
      echo json_encode(array("error"=>0,"msg"=>"삭제할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'search_member') {
    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"회원을 선택해 주세요."));
      exit;
    }
    $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");
    if (!isset($info['USER_ID'])) {
      echo json_encode(array("error"=>0, "msg"=>"존재하지 않은 회원입니다."));
      exit;
    }
    echo json_encode(array("error"=>1,"msg"=>""));
  } else if ($VAL['mode'] == 'in_member_price') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH_LOG.php";
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_IPOINT_LOG.php";

    if ($VAL['cash'] == '') $VAL['cash'] = 0;
    if ($VAL['point'] == '') $VAL['point'] = 0;
    if ($VAL['ipoint'] == '') $VAL['ipoint'] = 0;

    $VAL['cash'] = filter_var($VAL['cash'], FILTER_SANITIZE_NUMBER_INT);
    $VAL['point'] = filter_var($VAL['point'], FILTER_SANITIZE_NUMBER_INT);
    $VAL['ipoint'] = filter_var($VAL['ipoint'], FILTER_SANITIZE_NUMBER_INT);

    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");

    if ($VAL['cash'] < 0 || $VAL['point'] < 0 || $VAL['ipoint'] < 0) {
      echo json_encode(array("error"=>0, "msg"=>"정확한 금액을 입력해 주세요."));
      exit;
    }

    if ($mem['CASH'] < $VAL['cash'] && $VAL['type'] == "-") {
      echo json_encode(array("error"=>0, "msg"=>"감소할 캐시가 현재 회원이 보유하고 있는 캐시를 초과하였습니다."));
      exit;
    }

    if ($mem['POINT'] < $VAL['point'] && $VAL['type'] == "-") {
      echo json_encode(array("error"=>0, "msg"=>"감소할 보유포인크가 현재 회원이 보유하고 있는 캐시를 초과하였습니다."));
      exit;
    }

    if ($mem['IPOINT'] < $VAL['ipoint'] && $VAL['type'] == "-") {
      echo json_encode(array("error"=>0, "msg"=>"감소할 마일리지가 현재 회원이 보유하고 있는 캐시를 초과하였습니다."));
      exit;
    }

    $log = array();
    $log['mode'] = "insert";
    $log['CASH'] = $VAL['cash'];
    $log['IPOINT'] = $VAL['ipoint'];
    $log['POINT'] = $VAL['point'];
    $log['USER_ID'] = $VAL['user_id'];
    $log['STAFF_ID'] = $S_login['user_id'];
    $log['IN_IP'] = $ip_address;
    $log['STATUS'] = ($VAL['type']=="+") ? "P" : "M";
    $log['LOG_NO'] = $db->get_data_one("SELECT MAX(LOG_NO) as LOG_NO FROM CASH_LOG") + 1;
    F_CASH_LOG($log);

    if ($VAL['cash'] > 0) {
      //캐시사용로그
      $cash_log = array();
      $cash_log['mode'] = "insert";
      $cash_log['CASH_NO'] = 0;
      $cash_log['ORDERS_NO'] = 0;
      $cash_log['WINNING_NO'] = 0;
      $cash_log['INVOCE_NO'] = 0;
      $cash_log['CASH_LOG_NO'] = $log['LOG_NO'];
      $cash_log['PRICE'] = 0;
      $cash_log['CASH'] = $VAL['cash'];
      $cash_log['O_CASH'] = $mem['CASH'];
      $cash_log['USER_ID'] = $mem['USER_ID'];

      if ($VAL['type'] == '+') {
        $cash_log['MEMO'] = $S_login['user_id']."관리자 캐시추가";
        $cash_log['STATUS'] = "P";
        $cash_log['N_CASH'] = $mem['CASH'] + $VAL['cash'];
      } else {
        $cash_log['MEMO'] = $S_login['user_id']."관리자 캐시감소";
        $cash_log['STATUS'] = "M";
        $cash_log['N_CASH'] = $mem['CASH'] - $VAL['cash'];
      }
      F_T_CASH_LOG($cash_log);
    }

    if ($VAL['point'] > 0) {
      //보유포인트사용로그
      $point_log = array();
      $point_log['mode'] = "insert";
      $point_log['COUPON_NO'] = 0;
      $point_log['ORDERS_NO'] = 0;
      $point_log['CASH_LOG_NO'] = $log['LOG_NO'];
      $point_log['POINT'] = $VAL['point'];
      $point_log['O_POINT'] = $mem['POINT'];
      $point_log['USER_ID'] = $mem['USER_ID'];

      if ($VAL['type'] == '+') {
        $point_log['MEMO'] = $S_login['user_id']."관리자 보유포인트 추가";
        $point_log['STATUS'] = "P";
        $point_log['N_POINT'] = $mem['POINT'] + $VAL['point'];
      } else {
        $point_log['MEMO'] = $S_login['user_id']."관리자 보유포인트 감소";
        $point_log['STATUS'] = "M";
        $point_log['N_POINT'] = $mem['POINT'] - $VAL['point'];
      }
      F_T_POINT_LOG($point_log);
    }

    if ($VAL['ipoint'] > 0) {
      //마일리지사용로그
      $ipoint_log = array();
      $ipoint_log['mode'] = "insert";
      $ipoint_log['ORDERS_NO'] = $VAL['ORDERS_NO'];
      $ipoint_log['CASH_LOG_NO'] = $log['LOG_NO'];
      $ipoint_log['WINNING_NO'] = 0;
      $ipoint_log['IPOINT'] = $VAL['ipoint'];
      $ipoint_log['O_IPOINT'] = $mem['IPOINT'];
      $ipoint_log['USER_ID'] = $mem['USER_ID'];

      if ($VAL['type'] == '+') {
        $ipoint_log['MEMO'] = $S_login['user_id']."관리자 마일리지 추가";
        $ipoint_log['STATUS'] = "P";
        $ipoint_log['N_IPOINT'] = $mem['IPOINT'] + $VAL['ipoint'];
      } else {
        $ipoint_log['MEMO'] = $S_login['user_id']."관리자 마일리지 감소";
        $ipoint_log['STATUS'] = "M";
        $ipoint_log['N_IPOINT'] = $mem['IPOINT'] - $VAL['ipoint'];
      }
      F_T_IPOINT_LOG($ipoint_log);
    }

    if ($VAL['type'] == '+') {
      $sql = "
        UPDATE
          MEMBER
        SET
          POINT=POINT+".$VAL['point'].",
          IPOINT=IPOINT+".$VAL['ipoint'].",
          CASH=CASH+".$VAL['cash']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    } else {
      $sql = "
        UPDATE
          MEMBER
        SET
          POINT=POINT-".$VAL['point'].",
          IPOINT=IPOINT-".$VAL['ipoint'].",
          CASH=CASH-".$VAL['cash']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    }
    $db->query($sql);
    echo json_encode(array("error"=>1,"msg"=>"등록하였습니다."));
  } else if ($VAL['mode'] == "check_hp_member") {
    if ($VAL['hp'] == '') {
      echo json_encode(array("error"=>0,"msg"=>"수신번호를 입력해 주세요."));
      exit;
    }
    $mb = $db->get_data("SELECT * FROM MEMBER WHERE HP='".$VAL['hp']."'");

    if (!isset($mb['MEMBER_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"등록되지 않은 회원입니다."));
      exit;
    }

    $VAL['no']++;

    $html = '<tr>
              <td>'.$VAL['no'].'</td>
              <td>'.$mb['NAME'].'</td>
              <td>'.$mb['HP'].'</td>
              <td><a class="btn btn-danger sms_hp_del" style="padding: 0; cursor: pointer; color: #fff;">삭제</a></td>
            </tr>';
    echo json_encode(array("error"=>1, "html"=>$html));
    exit;
  } else if ($VAL['mode'] == 'sms_send') {
    $mem_arr= $VAL["mem_arr"];

    if ($VAL['subject'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"제목을 입력해 주세요."));
      exit;
    }

    if ($VAL['content'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"내용을 입력해 주세요."));
      exit;
    }

    if ($VAL['all_mem'] == "Y") {
      $list = $db->get_list("SELECT HP FROM MEMBER WHERE 1");
      $mem_arr = $list["HP"];
    }

    if (count($mem_arr) <= 0) {
      echo json_encode(array("error"=>0, "msg"=>"수신할 번호를 선택해 주세요."));
      exit;
    }

    foreach ($mem_arr as $HP) {
      $mem = $db->get_data("SELECT * FROM MEMBER WHERE HP='".$HP."'");
      $from = $fromHP;
      $to = $HP;
      $CODESK = "S";
      $MEMBER_NO = $mem['MEMBER_NO'];
      $MEMBER_NAME = $mem['NAME'];
      $auth_num = str_rand(6,"0123456789");
      $SUBJECT = $subject;
      $SENDMSG = $VAL['content'];
      $SUBJECT = str_replace('{PHONENO}', $to, $SUBJECT);
      $SENDMSG = addslashes($SENDMSG);
      $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
    }

    echo json_encode(array("error"=>1, "msg"=>"발송하였습니다."));
    exit;

  } else if ($VAL['mode'] == 'cancel_staff_cash_price') {
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");

    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $info = $db->get_data("SELECT * FROM CASH_LOG WHERE USER_ID='".$VAL['user_id']."' AND STAFF_ID='".$S_login['user_id']."' AND CASH > 0 ORDER BY REG_DATE DESC LIMIT 0, 1");

    if (!isset($info['LOG_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $sql = "DELETE FROM T_CASH_LOG WHERE CASH_LOG_NO='".$info['LOG_NO']."' AND STATUS='".$info['STATUS']."' AND USER_ID='".$info['USER_ID']."'";
    $db->query($sql);

    $sql2 = "DELETE FROM CASH_LOG WHERE LOG_NO='".$info['LOG_NO']."'";
    $db->query($sql2);

    if ($info['STATUS'] == 'M') {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          CASH=CASH+".$info['CASH']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    } else {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          CASH=CASH-".$info['CASH']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    }
    $db->query($sql3);

    echo json_encode(array("error"=>1, "msg"=>"실행취소 처리하였습니다."));
    exit;
  } else if ($VAL['mode'] == 'cancel_staff_ipoint_price') {
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");

    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $info = $db->get_data("SELECT * FROM CASH_LOG WHERE USER_ID='".$VAL['user_id']."' AND STAFF_ID='".$S_login['user_id']."' AND IPOINT > 0 ORDER BY REG_DATE DESC LIMIT 0, 1");

    if (!isset($info['LOG_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $sql = "DELETE FROM T_IPOINT_LOG WHERE CASH_LOG_NO='".$info['LOG_NO']."' AND STATUS='".$info['STATUS']."' AND USER_ID='".$info['USER_ID']."'";
    $db->query($sql);

    $sql2 = "DELETE FROM CASH_LOG WHERE LOG_NO='".$info['LOG_NO']."'";
    $db->query($sql2);

    if ($info['STATUS'] == 'M') {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          IPOINT=IPOINT+".$info['IPOINT']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    } else {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          IPOINT=IPOINT-".$info['IPOINT']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    }
    $db->query($sql3);

    echo json_encode(array("error"=>1, "msg"=>"실행취소 처리하였습니다."));
    exit;
  } else if ($VAL['mode'] == 'cancel_staff_point_price') {
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");

    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $info = $db->get_data("SELECT * FROM CASH_LOG WHERE USER_ID='".$VAL['user_id']."' AND STAFF_ID='".$S_login['user_id']."' AND POINT > 0 ORDER BY REG_DATE DESC LIMIT 0, 1");

    if (!isset($info['LOG_NO'])) {
      echo json_encode(array("error"=>0, "msg"=>"실행취소 실패하였습니다."));
      exit;
    }

    $sql = "DELETE FROM T_POINT_LOG WHERE CASH_LOG_NO='".$info['LOG_NO']."' AND STATUS='".$info['STATUS']."' AND USER_ID='".$info['USER_ID']."'";
    $db->query($sql);

    $sql2 = "DELETE FROM CASH_LOG WHERE LOG_NO='".$info['LOG_NO']."'";
    $db->query($sql2);

    if ($info['STATUS'] == 'M') {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          POINT=POINT+".$info['POINT']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    } else {
      $sql3 = "
        UPDATE
          MEMBER
        SET
          POINT=POINT-".$info['POINT']."
        WHERE
          USER_ID = '".$VAL['user_id']."'
      ";
    }

    $db->query($sql3);

    echo json_encode(array("error"=>1, "msg"=>"실행취소 처리하였습니다."));
    exit;
  }
?>
