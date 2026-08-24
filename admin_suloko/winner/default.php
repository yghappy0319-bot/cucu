<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_WCASH_LOG.php";

  $VAL = $_POST;

  if ($VAL['mode'] == 'win_sms') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_MEMBER.php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $info = F_ORDERS(array("mode"=>"read", "ORDERS_NO"=>$chk_no[$i]));
        $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

        //________당첨등수 처리
        $win_rank = array();
        for ($rank_cnt = 1; $rank_cnt <= 5; $rank_cnt++) {
          if ($info['WIN'.$rank_cnt] > 0) array_push($win_rank, $info['WIN'.$rank_cnt]."등");
        }
        $win_rank_str = implode(",", $win_rank);
        //________당첨등수 처리

        $TEMPLET_NO = 17;
        $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

        $from        = $fromHP;
        $to          = $info["HP"];
        $CODESK      = "S";
        $MEMBER_NO   = $mem['MEMBER_NO'];
        $MEMBER_NAME = $mem['NAME'];
        $SUBJECT     = $SMSTEMPLET['SUBJECT'];
        $SENDMSG     = $SMSTEMPLET['CONTENT'];
        $SENDMSG     = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG); //아이디
        $SENDMSG     = str_replace('{USERNAME}', $mem['NAME'], $SENDMSG);  //이름
        $SENDMSG     = str_replace('{REGDATE}', $info['REG_DATE'], $SENDMSG);  //구매일
        $SENDMSG     = str_replace('{LOTTONAME}', $ball_op[$info['GUBUN']], $SENDMSG);  //게임구분
        $SENDMSG     = str_replace('{WINRANK}', $win_rank_str, $SENDMSG);  //당첨등수
        $SENDMSG     = str_replace('{WINMONEY}', number_format($info['WIN_MONEY'])."원", $SENDMSG); //당첨금액
        $SENDMSG     = addslashes($SENDMSG);

        $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
      }
      echo json_encode(array("error"=>1, "msg"=>"문자발송하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"문자발송할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'win_is_use') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $sql = "UPDATE ORDERS SET IS_USE='{$VAL['type']}' WHERE ORDERS_NO='{$chk_no[$i]}'";
        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"수령 변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"변경할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'read_order') {
    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='{$VAL['no']}'");
    $info['ball_name'] = $ball_op[$info['GUBUN']];
    echo json_encode($info);
  } else if ($VAL['mode'] == 'all_status') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    if ($VAL['status'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"잘못된 접근입니다."));
    }

    $TEMPLET_NO = 6;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

    $from = $fromHP;

    if (count($chk_no) > 0) {
      for ($i = 0 ; $i < count($chk_no) ; $i++) {
        $type = "완료";
        $info = $db->get_data("SELECT * FROM INVOCE WHERE INVOCE_NO='{$chk_no[$i]}'");
        $mem  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");
        $sql  = "UPDATE INVOCE SET STATUS='{$VAL['status']}', PROC_DATE=NOW() WHERE INVOCE_NO='{$chk_no[$i]}'";
        $db->query($sql);

        if ($VAL['status'] == 'D') {
          $type  =  "취소";
          // 환급신청로그(차감)
          $wcash_log = array();
          $wcash_log['mode']      = "insert";
          $wcash_log['ORDERS_NO'] = 0;
          $wcash_log['INVOCE_NO'] = $chk_no[$i];
          $wcash_log['CASH_LOG_NO'] = 0;
          $wcash_log['PRICE']     = $info['MONEY2'];
          $wcash_log['WCASH']     = $info['MONEY'];
          $wcash_log['N_WCASH']   = $mem['WINCASH'] + $info['MONEY'];
          $wcash_log['O_WCASH']   = $mem['WINCASH'];
          $wcash_log['USER_ID']   = $mem['USER_ID'];
          $wcash_log['MEMO']      = "당첨금출금신청-취소";
          $wcash_log['STATUS']    = "P";

          F_T_WCASH_LOG($wcash_log);

          $sql2 = "
            UPDATE
              MEMBER
            SET
              WINCASH = WINCASH + {$info['MONEY']}
            WHERE
              USER_ID = '{$info['USER_ID']}'
          ";
          $db->query($sql2);
          // SMS 발송여부
          $isSendSMS = false;
        } else {
          $isSendSMS = true;
        }

        // 환급취소일 경우 SMS 미발송 (2023-01-25)
        if ($isSendSMS) {
          $to          = $mem["HP"];
          $CODESK      = "S";
          $MEMBER_NO   = $mem['MEMBER_NO'];
          $MEMBER_NAME = $mem['NAME'];

          $SUBJECT = $SMSTEMPLET['SUBJECT'];
          $SENDMSG = $SMSTEMPLET['CONTENT'];
          $SENDMSG = str_replace('{MEMBERNAME}', $mem['NAME'], $SENDMSG);
          $SENDMSG = str_replace('{CASHTYPE}', $type, $SENDMSG);
          $SENDMSG = str_replace('{APPCASH}', number_format($info['MONEY']), $SENDMSG);

          $SENDMSG = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
          $SENDMSG = addslashes($SENDMSG);

          $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");
        }
      }

      echo json_encode(array("error"=>1, "msg"=>"환급{$type} 처리하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"환급할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'send_sms_invoce_error') { // 취소안내 SMS발송 후 취소
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    // 발송건과 회원정보가 일치하지 않을경우
    if (count($VAL['chk_no']) != count($VAL['chk_mem'])) {
      echo json_encode(array("error"=>1, "msg"=>"잘못된 접근입니다."));
    }

    $TEMPLET_NO = 25;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

    $from = $fromHP;

    if (count($chk_no) > 0) {
      for ($i = 0 ; $i < count($chk_no) ; $i++) {
        $info = $db->get_data("SELECT * FROM INVOCE WHERE INVOCE_NO='{$chk_no[$i]}'");
        $mem  = $db->get_data("SELECT * FROM MEMBER WHERE MEMBER_NO='{$chk_mem[$i]}'");
        $sql  = "UPDATE INVOCE SET STATUS='D', PROC_DATE=NOW() WHERE INVOCE_NO='{$chk_no[$i]}'";
        $db->query($sql);

        // 환급신청로그(차감)
        $wcash_log = array();
        $wcash_log['mode']      = "insert";
        $wcash_log['ORDERS_NO'] = 0;
        $wcash_log['INVOCE_NO'] = $chk_no[$i];
        $wcash_log['CASH_LOG_NO'] = 0;
        $wcash_log['PRICE']     = $info['MONEY2'];
        $wcash_log['WCASH']     = $info['MONEY'];
        $wcash_log['N_WCASH']   = $mem['WINCASH'] + $info['MONEY'];
        $wcash_log['O_WCASH']   = $mem['WINCASH'];
        $wcash_log['USER_ID']   = $mem['USER_ID'];
        $wcash_log['MEMO']      = "당첨금출금신청-취소";
        $wcash_log['STATUS']    = "P";
        F_T_WCASH_LOG($wcash_log);

        // 회원 당첨금 복구
        $sql2 = "
          UPDATE
            MEMBER
          SET
            WINCASH = WINCASH + {$info['MONEY']}
          WHERE
            USER_ID = '{$info['USER_ID']}'
        ";
        $db->query($sql2);

        // 취소안내 SMS발송
        $to          = $mem["HP"];
        $CODESK      = "S";
        $MEMBER_NO   = $mem['MEMBER_NO'];
        $MEMBER_NAME = $mem['NAME'];

        $SUBJECT = $SMSTEMPLET['SUBJECT'];
        $SENDMSG = $SMSTEMPLET['CONTENT'];
        $SENDMSG = str_replace('{ACCOUNTNAME}', $info['BANK3'], $SENDMSG); //입금자명
        $SENDMSG = str_replace('{BANKNAME}', $info['BANK1'], $SENDMSG);  //은행명
        $SENDMSG = str_replace('{ACCOUNTNUM}', $info['BANK2'], $SENDMSG); //계좌번호
        $SENDMSG = addslashes($SENDMSG);

        $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");
      }
      echo json_encode(array("error"=>0, "msg"=>"안내SMS 발송하였습니다."));
    } else {
      echo json_encode(array("error"=>1, "msg"=>"안내SMS 발송 대상이 없습니다."));
    }
    exit;
  } else if ($VAL['mode'] == "getDrawnum") {
    $gubun = $VAL['type'];
    $today = date("Y-m-d");
    $query = "
      SELECT DRAWNUM FROM WININFO WHERE GUBUN='{$gubun}' AND PLAYDATE <= '{$today}' AND BALL1 IS NOT NULL ORDER BY PLAYDATE DESC
    ";
    $wininfo = $db->get_list($query);
    echo json_encode($wininfo['DRAWNUM']);
    exit;
  }
?>
