<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == 'all_del') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
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
          if ($info['file'.$s] == '') { continue; }

          $link = $_SERVER['DOCUMENT_ROOT']."/upload/".$s_type."/".$info['file'.$s];
          @unlink($link);
        }
        $func_name($VAL);
      }
      echo json_encode(array("error"=>1, "msg"=>"삭제했습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"삭제할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == "all_hide") {
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $db->query("UPDATE BBS SET HIDDEN = '1' WHERE BBS_NO = '{$chk_no[$i]}'");
      }
      echo json_encode(array("error"=>1, "msg"=>"처리되었습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"항목이 없습니다."));
    }
  } else if ($VAL['mode'] == "read_bbs") {
    $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '{$VAL['no']}'");
    echo json_encode($info);
  } else if ($VAL['mode'] == "in_bbs") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '{$VAL['no']}'");
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$info['USER_ID']}'");
    $VAL['content'] = addslashes($VAL['content']);

    if ($info['GUBUN'] == 'QNA') {
      $sql = "
        UPDATE BBS SET
          REPLY  = '{$VAL['content']}',
          REPLY_NAME = '{$S_login['name']}',
          REPLY_DATE = NOW()
        WHERE
          BBS_NO = '{$VAL['no']}'
      ";
    } else {
      $sql = "
        UPDATE BBS SET
          REPLY  = '{$VAL['content']}',
          REPLY_NAME = '{$S_login['name']}',
          REPLY_DATE = NOW(),
          REPLY_YN = 'Y'
        WHERE
          BBS_NO = '{$VAL['no']}'
      ";
    }
    $db->query($sql);
    echo json_encode(array("error"=>true));
  } else if ($VAL['mode'] == "up_bbs_stat") {
    if ($VAL['no'] != "") {
      $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '{$VAL['no']}'");
      $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

      if ($S_login['level'] != 4) {
        echo json_encode(array("error"=>0, "msg"=>"상태 변경 권한 오류"));
        exit;
      }

      $db->query("
        UPDATE BBS
        SET
          REPLY_YN='{$VAL['st']}',
          STATE_CHG_ID = '{$S_login['user_id']}',
          STATE_CHG_DATE = NOW()
        WHERE BBS_NO='{$VAL['no']}'
      ");

      // EMAIL 답변 등록 안내
      if ($info['GUBUN'] == 'QNA' && $mem['EMAIL'] != '' && $VAL['st'] == "Y") {
        $TEMPLET_NO = 4;

        $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

        $to      = $mem['EMAIL'];
        $subject = $EMAILTEMPLET['SUBJECT'];
        $content = $EMAILTEMPLET['CONTENT'];
        $content = str_replace('{{문의내용}}', $info['CONTENT'], $content);
        $content = str_replace('{{답변내용}}', $info['REPLY'], $content);
        $type    = "2";

        if ($EMAILTEMPLET['STOPYN'] == 'N') {
          // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
          // AWS SES 클라이언트 생성
          $sesClient = initializeSesClient();
          if ($sesClient) {
            sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
          }
        }
      }

      // SMS 답변 등록 안내
      if ($info['GUBUN'] == 'QNA' && $mem['EMAIL'] != '' && $VAL['st'] == "Y") {
        ## SMS
        $TEMPLET_NO  = 49;
        $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

        $from        = $fromHP;
        $to          = $mem["HP"];
        $CODESK      = "S";
        $MEMBER_NO   = $mem['MEMBER_NO'];
        $MEMBER_NAME = $mem['NAME'];

        $SUBJECT     = $SMSTEMPLET['SUBJECT'];
        $SENDMSG     = $SMSTEMPLET['CONTENT'];
        $SENDMSG     = addslashes($SENDMSG);

        $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "SMS");
      }
      echo json_encode(array("error"=>1, "msg"=>"상태를 변경했습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"변경 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == "banner_notice_up") {
    if ($VAL['no'] != "") {
      $VAL['subject'] = addslashes($VAL['subject']);
      $query = "
        UPDATE
          BANNER_NOTICE
        SET
          SUBJECT = '{$VAL['subject']}',
          LINK = '{$VAL['link']}',
          IS_USE = '{$VAL['isuse']}',
          LASTDATE = NOW()
        WHERE
          BANNER_NO = '{$VAL['no']}'
      ";
      $db->query($query);
      echo json_encode(array("error"=>1, "msg"=>"긴급 공지사항을 저장했습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"긴급 공지가 지정되지 않았습니다."));
    }
  }
?>
