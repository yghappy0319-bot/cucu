<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == 'all_del') {
    if ($VAL['gubun'] == '') {
      echo json_encode(array("error" => 0, "msg" => "잘못된 접근입니다."));
      exit;
    }

    $s_type = $VAL['gubun'];
    $func_name = "F_".$s_type;

    include $_SERVER['DOCUMENT_ROOT']."/_library/function_".$s_type.".php";
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $VAL = array("mode" => "delete", $s_type."_NO" => $chk_no[$i]);
        $info = $func_name(array("mode" => "read", $s_type."_NO" => $chk_no[$i]));
        for ($s = 1; $s < 10; $s++) {
          if ($info['file'.$s] == '') {
            continue;
          }
          $link = $_SERVER['DOCUMENT_ROOT']."/upload/".$s_type."/".$info['file'.$s];
          @unlink($link);
        }
        $func_name($VAL);
      }
      echo json_encode(array("error" => 1, "msg" => "삭제 하였습니다."));
    } else {
      echo json_encode(array("error" => 0, "msg" => "삭제할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == "read_bbs") {
    $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '".$VAL['no']."'");
    echo json_encode($info);
  } else if ($VAL['mode'] == "in_bbs") {
    $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '".$VAL['no']."'");
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$info['USER_ID']."'");
    $VAL['content'] = addslashes($VAL['content']);

    if ($info['GUBUN'] == 'UM_QNA') {
      $sql = "
        UPDATE BBS SET
          REPLY = '".$VAL['content']."',
          REPLY_NAME = '".$S_login['name']."',
          REPLY_DATE = NOW()
        WHERE
          BBS_NO = '".$VAL['no']."'
      ";
    } else {
      $sql = "
        UPDATE BBS SET
          REPLY = '".$VAL['content']."',
          REPLY_NAME = '".$S_login['name']."',
          REPLY_DATE = NOW(),
          REPLY_YN = 'Y'
        WHERE
          BBS_NO = '".$VAL['no']."'
      ";
    }
    $db->query($sql);
    echo json_encode(array("error" => true));
  } else if ($VAL['mode'] == "up_bbs_stat") {
    if ($VAL['no'] != "") {
      $info = $db->get_data("SELECT * FROM BBS WHERE BBS_NO = '".$VAL['no']."'");
      $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID ='".$info['USER_ID']."'");

      $db->query("UPDATE BBS SET REPLY_YN = '".$VAL['st']."' WHERE BBS_NO='".$VAL['no']."'");

      if ($info['GUBUN'] == 'QNA' && $mem['EMAIL'] != '' && $VAL['st'] == "Y") {
        $TEMPLET_NO = 4;

        $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO = '".$TEMPLET_NO."'");
        $to = $mem['EMAIL'];
        $subject = $EMAILTEMPLET['SUBJECT'];
        $content = $EMAILTEMPLET['CONTENT'];
        $content = str_replace('{{문의내용}}', $info['CONTENT'], $content);
        $content = str_replace('{{답변내용}}', $info['REPLY'], $content);
        $type = "2";

        if ($EMAILTEMPLET['STOPYN'] == 'N') {
          // mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
          // AWS SES 클라이언트 생성
          $sesClient = initializeSesClient();
          if ($sesClient) {
            sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
          }
        }
      }
      echo json_encode(array("error" => 1, "msg" => "상태를 변경하였습니다."));
    } else {
      echo json_encode(array("error" => 0, "msg" => "변경 항목이 없습니다."));
    }
  }
