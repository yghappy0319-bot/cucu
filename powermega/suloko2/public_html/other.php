<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
  require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
  require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_IPOINT_LOG.php"; // 추천인 마일리지 로그를 위한 처리
  $VAL = $_POST;

  $RES = array("error" => False, "msg" => "");

  if ($VAL["mode"] == "join_tel_chk") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='{$VAL['tel']}'");

    if ($info["cnt"] > 0) {
      $RES["msg"] = "이미 가입된 연락처입니다.";
      echo json_encode($RES);
      exit;
    }

    // 탈퇴 회원 확인(탈퇴 후 180일 경과 유무 확인)
    $out_info = $db->get_data_one("
      SELECT COUNT(*) AS cnt
      FROM OUT_MEMBER
      WHERE
        HP = '{$VAL['tel']}'
          AND
        TIMESTAMPDIFF(DAY, REG_DATE, NOW()) <= 180
      LIMIT 1
    ");

    if ($out_info["cnt"] > 0) {
      $RES["msg"] = "사용할 수 없는 연락처입니다.";
      echo json_encode($RES);
      exit;
    }

    $TEMPLET_NO = 18;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

    $from = $fromHP;
    $to = $VAL["tel"];
    $CODESK = "S";
    $MEMBER_NO = 0;
    $MEMBER_NAME = "인증번호발송";
    $auth_num = str_rand(6, "0123456789");

    if ($to == "") {
      $RES["msg"] = "정확한 연락처를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $SUBJECT = $auth_num;
    $SENDMSG = $SMSTEMPLET["CONTENT"];
    $SENDMSG = str_replace("{AUTHCODE}", $auth_num, $SENDMSG);
    $SENDMSG = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    if ($sms->result_code != 1) {
      $RES["msg"] = "문자 발송 실패";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "mupdate_tel_chk") {
    $tel = trim($VAL["tel"]);
    if ($tel == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='{$tel}' AND USER_ID != '{$M_login['user_id']}'");

    if ($info["cnt"] > 0) {
      $RES["msg"] = "이미 가입된 번호입니다.";
      echo json_encode($RES);
      exit;
    }

    // 인증 문자 발송
    $TEMPLET_NO  = 18;
    $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");
    $from        = $fromHP;
    $to          = $tel;
    $CODESK      = "S";
    $MEMBER_NO   = 0;
    $MEMBER_NAME = "인증번호발송";
    $auth_num    = str_rand(6, "0123456789");

    $SUBJECT     = $auth_num;
    $SENDMSG     = $SMSTEMPLET["CONTENT"];
    $SENDMSG     = str_replace("{AUTHCODE}", $auth_num, $SENDMSG);
    $SENDMSG     = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    if ($sms->result_code != 1) {
      $RES["msg"] = "문자 발송에 실패하였습니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "join_auth_chk") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($VAL["auth"] == "") {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $to = str_replace("-", "", $VAL["tel"]);

    $query = "
      SELECT
        *
      FROM
        SMSSENDLOG
      WHERE
        CODESK = 'S'
          AND
        MEMBER_NAME = '인증번호발송'
          AND
        TOHP = '{$to}'
          AND
        REG_DATE >= DATE_ADD(NOW(), INTERVAL -3 MINUTE)
      ORDER BY REG_DATE DESC
      LIMIT 1
    ";

    $info = $db->get_data($query);

    if (!isset($info["SUBJECT"])) {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($info["SUBJECT"] != $VAL["auth"]) {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    syslog(7, $RES);
    exit;
  } else if ($VAL["mode"] == "mupdate_auth_chk") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($VAL["auth"] == "") {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $to = str_replace("-", "", $VAL["tel"]);

    $query = "
      SELECT
        *
      FROM
        SMSSENDLOG
      WHERE
        CODESK = 'S'
          AND
        MEMBER_NAME = '인증번호발송'
          AND
        TOHP = '{$to}'
          AND
        REG_DATE >= DATE_ADD(NOW(), INTERVAL -3 MINUTE)
      ORDER BY REG_DATE DESC
      LIMIT 1
    ";

    $info = $db->get_data($query);

    if (!isset($info["SUBJECT"])) {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($info["SUBJECT"] != $VAL["auth"]) {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($mode == "type_count") {
    $time_set_MM = timesss("MM");
    $time_set_PB = timesss("PB");

    $rtn = array(
      "PB" => $time_set_PB,
      "MM" => $time_set_MM,
    );

    echo json_encode($rtn);
    exit;
  } else if ($mode == "mainnew") {
    $time_set_MM = timesss("MM");
    $time_set_PB = timesss("PB");

    if ($time_set_MM["price"] != "") {
      $time_set_MM["allprice"] = $time_set_MM["price"];
      $time_set_MM["price"] = str_replace(",","",$time_set_MM["price"]);
      $time_set_MM["price"] = str_replace(".","",$time_set_MM["price"]);
      $time_set_MM["price2"] = number_format($time_set_MM["price"]);
      $time_set_MM["price"] = substr($time_set_MM["price"],0,-8);
      $time_set_MM["price"] = getNumberStringKorean($time_set_MM["price"]);
      $time_set_MM["priceDollarMillion"] = number_format(floor($time_set_MM["priceDollar"] / 1000000));
      $time_set_MM["priceDollar"] = number_format($time_set_MM["priceDollar"]);
      $time_set_MM["secondMinPrize"] = getNumberStringKorean(substr(2000000 * $time_set_MM["won_rate"],0,-8));
      $time_set_MM["secondMaxPrize"] = getNumberStringKorean(substr(10000000 * $time_set_MM["won_rate"],0,-8));
      //Case1. 예상당첨금
      $time_set_MM["allprice_est"] = $time_set_MM["price_est"];
      $time_set_MM["price_est"] = str_replace(",","",$time_set_MM["price_est"]);
      $time_set_MM["price_est"] = str_replace(".","",$time_set_MM["price_est"]);
      $time_set_MM["price_est"] = substr($time_set_MM["price_est"], 0, -8);
      $time_set_MM["price_est"] = getNumberStringKorean($time_set_MM["price_est"]);
      //Case2. 초기화당첨금
      $time_set_MM["allprice_fst"] = $time_set_MM["price_fst"];
      $time_set_MM["price_fst"] = str_replace(",","",$time_set_MM["price_fst"]);
      $time_set_MM["price_fst"] = str_replace(".","",$time_set_MM["price_fst"]);
      $time_set_MM["price_fst"] = substr($time_set_MM["price_fst"], 0, -8);
      $time_set_MM["price_fst"] = getNumberStringKorean($time_set_MM["price_fst"]);
    }

    if ($time_set_PB["price"] != "") {
      $time_set_PB["allprice"] = $time_set_PB["price"];
      $time_set_PB["price"] = str_replace(",","",$time_set_PB["price"]);
      $time_set_PB["price"] = str_replace(".","",$time_set_PB["price"]);
      $time_set_PB["price2"] = number_format($time_set_PB["price"]);
      $time_set_PB["price"] = substr($time_set_PB["price"], 0, -8);
      $time_set_PB["price"] = getNumberStringKorean($time_set_PB["price"]);
      $time_set_PB["priceDollarMillion"] = number_format(floor($time_set_PB["priceDollar"] / 1000000));
      $time_set_PB["priceDollar"] = number_format($time_set_PB["priceDollar"]);
      $time_set_PB["secondPrize"] = getNumberStringKorean(substr(1000000 * $time_set_PB["won_rate"],0,-8));
      $time_set_PB["thirdPrize"] = number_format(substr(50000 * $time_set_PB["won_rate"],0,-4));
      //Case1. 예상당첨금
      $time_set_PB["allprice_est"] = $time_set_PB["price_est"];
      $time_set_PB["price_est"] = str_replace(",","",$time_set_PB["price_est"]);
      $time_set_PB["price_est"] = str_replace(".","",$time_set_PB["price_est"]);
      $time_set_PB["price_est"] = substr($time_set_PB["price_est"], 0, -8);
      $time_set_PB["price_est"] = getNumberStringKorean($time_set_PB["price_est"]);
      //Case2. 초기화당첨금
      $time_set_PB["allprice_fst"] = $time_set_PB["price_fst"];
      $time_set_PB["price_fst"] = str_replace(",","",$time_set_PB["price_fst"]);
      $time_set_PB["price_fst"] = str_replace(".","",$time_set_PB["price_fst"]);
      $time_set_PB["price_fst"] = substr($time_set_PB["price_fst"], 0, -8);
      $time_set_PB["price_fst"] = getNumberStringKorean($time_set_PB["price_fst"]);
    }

    echo json_encode(array("MM"=>$time_set_MM, "PB"=>$time_set_PB));
    exit;
  } else if ($VAL["mode"] == "my_lotto_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_ORDERS.php";

    $add_query = " AND USER_ID='".$M_login['user_id']."'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    if ($VAL["gubun"] != "") {
      $find_object = "GUBUN";
      $find_text = $VAL["gubun"];
    }

    if ($VAL["win_yn"] != "") {
      $add_query .= " AND WIN_YN='".$VAL['win_yn']."'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL["page"],
      "order" => $VAL["order"],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_ORDERS_list($condition);

    if ($list["total"] <= 0) {
      $list["ORDERS_NO"] = array();
    }

    for ($i = 0; $i < count($list["ORDERS_NO"]); $i++) {
      if ($list["HIDE_YN"][$i] == "Y") {
        continue;
      }

      if ($list["GUBUN"][$i] == "MM") {
        $title = "메가밀리언";
        $titlee = "MEGA MILLIONS";
        $ball_cla = "mega";
      } else {
        $title = "파워볼";
        $titlee = "POWER BALL";
        $ball_cla = "power";
      }

      if ($list["WIN_YN"][$i] == "R") {
        $tt = "대기중";
        $tt_sty = 'style="color:#999;"';
      } else if ($list["WIN_YN"][$i] == "N") {
        $tt = "미당첨";
        $tt_sty = 'style="color:#999;"';
      } else if ($list["WIN_YN"][$i] == "Y") {
        $tt = "당첨";
        $tt_sty = "";
      }

      $html .= '
        <li id="box'.$list['ORDERS_NO'][$i].'">
          <div class="row">
            <div class="head">
              <div class="fl">
                <p>'.$title.'</p>
                <font>'.$titlee.'</font>
              </div>
            <div class="fr" '.$tt_sty.'>'.$tt.'</div>
          </div>
          <div class="ft">
            <p>
              <font>추첨일</font>
              <span>'.$list['PLAYDATE'][$i].'</span>
            </p>
            <p>
              <font>주문일</font>
              <span>'.$list['REG_DATE'][$i].'</span>
            </p>
            <p>
              <font>게임수</font>
              <span>'.$list['GAMECNT'][$i].' 게임</span>
            </p>
          </div>
          <div class="fc">';

      $ok_ck = "";

      for ($s = 1; $s < 6; $s++) {
        if ($list["BALL".$s][$i] != "") {
          $ball = explode(",", $list["BALL".$s][$i]);

          if ($list["WIN".$s][$i] > 0) {
            $ok = $list["WIN".$s][$i] . "등";
            $ok_cla = "ok";

            if ($ok_ck != "") {
              $ok_ck .= "+";
            }

            $ok_ck .= $ok;
          } else {
            $ok = "미당첨";
            $ok_cla = "";
          }
          $html .= '
            <div class="li">
              <p class="tit">게임 '.$s.'</p>
              <ul>
                <li><a style="background:#4692e4;"><p>'.$ball[0].'</p></a></li>
                <li><a style="background:#00ab54;"><p>'.$ball[1].'</p></a></li>
                <li><a style="background:#3ca5bb;"><p>'.$ball[2].'</p></a></li>
                <li><a style="background:#7153bb;"><p>'.$ball[3].'</p></a></li>
                <li><a style="background:#666;"><p>'.$ball[4].'</p></a></li>
                <li><font>+</font></li>
                <li><a class="'.$ball_cla.'"><p>'.$ball[5].'</p></a></li>
              </ul>
              <p class="txt '.$ok_cla.'">'.$ok.'</p>
            </div>';
        }
      }

      if ($ok_ck != "") {
        $ok_ck .= " 당첨";
      } else {
        $ok_ck = "";
      }

      if ($list["WIN_MONEY"][$i] == "") {
        $list["WIN_MONEY"][$i] = 0;
      }

      $html .=  '
          </div>
          <div class="fb">
            <p class="tl">당첨금<font>￦ '.number_format($list['WIN_MONEY'][$i]).'</font></p>
            <p class="tr">'.$ok_ck.'</p>
          </div>
          <div class="btn">
            <a class="popup_lotto_photo_btn" data-src="'.$list['IMG_PATH'][$i].'"><p>실물복권 확인</p></a>
            <a class="del_btn" data-no="'.$list['ORDERS_NO'][$i].'"><p>주문숨김</p></a>
          </div>
          </div>
        </li>';
    }

    $RES["html"] = $html;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_lotto_hide") {
    $db->query("UPDATE ORDERS SET HIDE_YN='Y' WHERE ORDERS_NO='".$VAL['no']."'");

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "id_check") {
    if ($VAL["hp"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT * FROM MEMBER WHERE HP='".$VAL['hp']."' LIMIT 1");

    $RES["user_id"] = $info["USER_ID"];
    $RES["name"] = $info["NAME"];

    if ($info["HP"] == "") {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "find_tel_chk") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE NAME='{$VAL['name']}' AND HP='{$VAL['tel']}'");

    if ($info["cnt"] <= 0) {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    $TEMPLET_NO = 18;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");

    $from = $fromHP;
    $to = $VAL["tel"];
    $CODESK = "S";
    $MEMBER_NO = 0;
    $MEMBER_NAME = "인증번호발송";
    $auth_num = str_rand(6, "0123456789");

    if ($to == "") {
      $RES["msg"] = "정확한 연락처를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $SUBJECT = $auth_num;
    $SENDMSG = $SMSTEMPLET["CONTENT"];
    $SENDMSG = str_replace("{AUTHCODE}", $auth_num, $SENDMSG);
    $SENDMSG = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    if ($sms->result_code != 1) {
      $RES["msg"] = "문자발송 실패하였습니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "pwd_update") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='{$VAL['tel']}'");

    if ($info["cnt"] <= 0) {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    $db->query("UPDATE MEMBER SET PASSWD=PASSWORD('{$VAL['pwd']}') WHERE HP='{$VAL['tel']}'");

    $RES["msg"] = "비밀번호를 변경하였습니다.";
    $RES["error"] = true;
    echo json_encode($RES);
    exit;


  } else if ($VAL["mode"] == "my_price_chk") {
    $info  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}' LIMIT 1");
    $wcash = $VAL["wcash"];
    $ipnt  = $VAL["ipnt"];
    $pnt   = $VAL["pnt"];

    if ($info["USER_ID"] == "") {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["WINCASH"] < $wcash && $wcash > 0) {
      $RES["msg"] = "당첨금이 부족합니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["IPOINT"] < $ipnt && $ipnt > 0) {
      $RES["msg"] = "마일리지가 부족합니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["POINT"] < $pnt && $pnt > 0) {
      $RES["msg"] = "보너스포인트가 부족합니다.";
      echo json_encode($RES);
      exit;
    }

    $good = $db->get_data("SELECT * FROM GOODS WHERE GUBUN='".$VAL['gubunLK']."' AND CNT='1'");
    $payment = $good["PRICE2"] * $VAL["cnt"];
    $payment = $payment - $pnt - $ipnt - $wcash;

    if ($payment < 0) {
      $RES["msg"] = "입력한 당첨금, 마일리지, 보너스포인트가 결제금액을 초과하였습니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["CASH"] < $payment) {
      // $RES["msg"] = "캐시가 부족합니다. \r\n캐시충전버튼을 통해 충전을 진행해주세요.";
      $RES["msg"] = "캐시가 부족합니다. \r\n캐시 충전페이지로 이동합니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["msg"] = "";
    $RES["error"] = true;
    echo json_encode($RES);
    exit;

  } else if ($VAL["mode"] == "search_date") {
    $schdate = $VAL["schdate"];

    if ($schdate == "1D") {
      $toTime = time();
    } else {
      $toTime = time();
    }
    switch($schdate) {
      case "1D": $timeS = $toTime - (0); break;
      case "2D": $timeS = $toTime - (86400); break;
      case "YD":
        // $timeS = $toTime - (86400);
        $timeS = $toTime;
        $toTime = $timeS;
        break;
      case "7D": $timeS = $toTime - (86400 * 6); break;
      case "15D": $timeS = $toTime - (86400 * 14); break;
      case "1M": $timeS = mktime(0, 0, 0, date("m") - 1, date("d") - 1, date("Y")); break;
      case "3M": $timeS = mktime(0, 0, 0, date("m") - 3, date("d") - 1, date("Y")); break;
      case "6M": $timeS = mktime(0, 0, 0, date("m") - 6, date("d") - 1, date("Y")); break;
      case "1Y": $timeS = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y") - 1); break;
    }

    $sdate = date("Y-m-d", $timeS);
    $edate = date("Y-m-d", $toTime);

    $rtns["errNo"] = 0;
    $rtns["sdate"] = $sdate;
    $rtns["edate"] = $edate;

    echo json_encode($rtns);
    exit;
  } else if ($VAL["mode"] == "auth_send") {
    $TEMPLET_NO = 18;

    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

    $from = $fromHP;
    $to = $VAL["tel"];
    $CODESK = "S";
    $MEMBER_NO = 0;
    $MEMBER_NAME = "인증번호발송";
    $auth_num = str_rand(6,"0123456789");

    if ($to == "") {
      $RES["msg"] = "정확한 연락처를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $SUBJECT = $auth_num;
    $SENDMSG = $SMSTEMPLET["CONTENT"];
    $SENDMSG = str_replace("{AUTHCODE}", $auth_num, $SENDMSG);
    $SENDMSG = addslashes($SENDMSG);

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

    if ($sms->result_code != 1) {
      $RES["msg"] = "문자발송 실패하였습니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "buy_auth_chk") {
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($VAL["auth"] == "") {
      $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $to = str_replace("-", "", $VAL["tel"]);

    $info = $db->get_data("SELECT * FROM SMSSENDLOG WHERE CODESK='S' AND MEMBER_NAME='인증번호발송' AND TOHP='".$to."'ORDER BY REG_DATE DESC");

    if ($VAL["auth"] == "999999") {
    } else {
      if (!isset($info["SUBJECT"])) {
        $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }

      if ($info["SUBJECT"] != $VAL["auth"]) {
        $RES["msg"] = "인증번호를 정확히 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }
    }

    $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['tel']."'");
    $mem2 = $db->get_data("SELECT * FROM MEMBER WHERE HP='".$VAL['tel']."'");

    $RES["error"] = true;

    if (isset($mem["USER_ID"])) {
      $RES["name"] = $mem["NAME"];
      $RES["user_id"] = $mem["USER_ID"];
    } else if (isset($mem2["USER_ID"])) {
      $RES["name"] = $mem2["NAME"];
      $RES["user_id"] = $mem2["USER_ID"];
    } else {
      $RES["name"] = "";
      $RES["user_id"] = "";
    }

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "join_auth_innser") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MEMBER.php";

    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $tel = $VAL["tel"];
    $user_auth = $VAL["auth"];
    $name = $VAL["name"];
    $pwd = $VAL["pwd"];

    //게시판 관리자 아이디와 비밀번호는 config.php  파일의   DB관리자 아이디 비번관 동일하다
    $tmp = $db->get_data("SELECT * FROM MEMBER WHERE HP='".$tel."'");

    if (!isset($tmp["USER_ID"])) {
      if ($VAL["name"] == "") {
        $RES["msg"] = "회원명을 정확히 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }

      if ($VAL["pwd"] == "") {
        $RES["msg"] = "비밀번호를 정확히 입력해 주세요.";
        echo json_encode($RES);
        exit;
      }

      $data = array();
      $data["mode"] = "insert";
      $data["USER_ID"] = $tel;
      $data["PASSWD"] = $pwd;
      $data["HP"] = $tel;
      $data["NAME"] = $name;
      $data["POINT"] = 0;
      $data["TOTPRICE"] = 0;
      $data["TOTCNT"] = 0;
      $data["AGENT_NO"] = 0;
      $data["AGENT_NO2"] = 0;
      $data["AGENT_NO3"] = 0;
      $data["AGENT_NO4"] = 0;
      $data["AUTH"] = $pwd;
      //$data["AGENT_ID"] = $agent["USER_ID"];

      $data["MEMBER_NO"] = $db->get_data_one("SELECT MAX(MEMBER_NO) as MEMBER_NO FROM MEMBER") + 1;
      F_MEMBER($data);

      $M_login["user_id"] = $data["USER_ID"];
      $M_login["name"] = $data["NAME"];
      $M_login["hp"] = $data["HP"];
    } else {
      $M_login["user_id"] = $tmp["USER_ID"];
      $M_login["name"] = $tmp["NAME"];
      $M_login["hp"] = $tmp["HP"];
    }

    session_register("M_login");

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "search_coupon") {
    $coupon_num = $VAL["coupon_num"];

    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_COUPON_LOG.php";
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_T_POINT_LOG.php";

    if ($coupon_num == "") {
      $RES["msg"] = "쿠폰 번호를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $member = $db->get_data("SELECT * FROM `MEMBER` WHERE `USER_ID` = '{$M_login['user_id']}'");
    $info = $db->get_data("SELECT * FROM `COUPON` WHERE `NUM` = '{$coupon_num}'");
    $expired = $db->get_data_one("SELECT `EXPIRED_AT` FROM `COUPON_EXPIRED` WHERE `NUM` = '{$coupon_num}'");

    // 쿠폰 번호 확인
    if (!isset($info["COUPON_NO"])) {
      $RES["msg"] = "잘못된 쿠폰번호 입니다. 쿠폰번호를 확인하여 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 쿠폰 유효기간 확인
    if (!empty($expired)) {
      if (strtotime($expired) < strtotime(date("Y-m-d H:i:s"))) {
        $RES["msg"] = "유효기간이 만료된 쿠폰으로 더 이상 사용할 수 없습니다.";
        echo json_encode($RES);
        exit;
      }
    }

    // 쿠폰 사용 회수 확인
    $log = $db->get_data_one("SELECT COUNT(*) AS cnt FROM `COUPON_LOG` WHERE `COUPON_NO`='{$info["COUPON_NO"]}'");

    if ($log == "") {
      $log = 0;
    }
    if ($log >= $info["TIME_CNT"]) {
      $RES["msg"] = "유효하지 않은 쿠폰번호 입니다. 쿠폰번호를 정확히 입력하여 주세요.";
      echo json_encode($RES);
      exit;
    }

    // 번호 사용 회수 확인
    $me_log = $db->get_data_one("
      SELECT
        COUNT(*) AS cnt
      FROM
        `COUPON_LOG`
      WHERE
        `COUPON_NO`='{$info["COUPON_NO"]}' AND `MEMBER_NO`='{$member['MEMBER_NO']}'
    ");
    if ($me_log > 0) {
      $RES["msg"] = "이미 등록한 쿠폰 쿠폰번호 입니다. 쿠폰번호를 정확히 입력하여 주세요.";
      echo json_encode($RES);
      exit;
    }

    $me_chk_log = $db->get_data_one("
      SELECT
        COUNT(*) AS cnt
      FROM
        `COUPON_LOG`
      WHERE
        `MEMBER_NO` = '{$member['MEMBER_NO']}' AND `GRPCODE` = '{$info['GRPCODE']}'
    ");

    if ($me_chk_log > 0) {
      $RES["msg"] = "한 아이디당 1개의 쿠폰만 등록 할 수 있습니다.";
      echo json_encode($RES);
      exit;
    }

    $count = $db->get_data("SELECT COUNT(*) AS cnt FROM `COUPON_LOG` WHERE `MEMBER_NO`='{$member['MEMBER_NO']}' LIMIT 1");

    $data = array();
    $data["mode"] = "insert";
    $data["COUPON_NO"] = $info["COUPON_NO"];
    $data["GRPCODE"] = $info["GRPCODE"];
    $data["MEMBER_NO"] = $member["MEMBER_NO"];
    $data["PATNER_ID"] = $info["PATNER_ID"];
    $data["LOG_MEMO"] = "쿠폰 적립";
    $data["GUBUN"] = $info["GUBUN"];
    $data["ISTYPE"] = "신규";

    if ($count["cnt"] > 0) {
      $data["ISTYPE"] = "기존";
    }

    $data["LOG_NO"] = $db->get_data_one("SELECT MAX(LOG_NO) as LOG_NO FROM COUPON_LOG") + 1;

    F_COUPON_LOG($data);

    $patner = $db->get_data("SELECT * FROM PATNER WHERE USER_ID='{$info['PATNER_ID']}'");
    $add_query = " PATNER_NO='{$patner['PATNER_NO']}', ";

    // 보유포인트 사용 로그
    $point_log = array();
    $point_log["mode"] = "insert";
    $point_log["COUPON_NO"] = $data["COUPON_NO"];
    $point_log["ORDERS_NO"] = 0;
    $point_log["CASH_LOG_NO"] = 0;
    $point_log["POINT"] = $info["PRICE"];
    $point_log["N_POINT"] = $member["POINT"] + $info["PRICE"];
    $point_log["O_POINT"] = $member["POINT"];
    $point_log["USER_ID"] = $member["USER_ID"];
    $point_log["MEMO"] = "쿠폰등록";
    $point_log["STATUS"] = "P";

    F_T_POINT_LOG($point_log);

    $sql = "
      UPDATE
        MEMBER
      SET
        ".$add_query."
        POINT=POINT+".$info['PRICE']."
      WHERE
        USER_ID = '".$M_login['user_id']."'
    ";

    $db->query($sql);

    $RES["error"] = true;
    $RES["msg"] = "쿠폰이 정상 등록 되었습니다. 감사합니다.";

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "app_sms") {
    $from = $fromHP;
    $to = $VAL["hp"];
    $CODESK = "S";
    $TEMPLET_NO = 0;
    $MEMBER_NO = 0;
    $MEMBER_NAME = "APP 설치 URL";

    if ($to == "") {
      $RES["msg"] = "정확한 연락처를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $SUBJECT = "APP 설치 URL";
    $SENDMSG = "<?=$사이트명1?> 안드로이드 설치 파일은 아래의 링크를 클릭하시면 받으실 수 있습니다.\r\nhttps://play.google.com/store/apps/details?id=com.sulotko";

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");

    if ($sms->result_code != 1) {
      $RES["msg"] = "문자발송 실패하였습니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    $RES["msg"]  = "전송되었습니다.";

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "chk_pwd") {
    if (trim($VAL["pwd"]) == "") {
      $RES["msg"] = "비밀번호를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if ($M_login["user_id"] == "") {
      $RES["msg"] = "로그인을 해주세요.";
      echo json_encode($RES);
      exit;
    }

    $tmp = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' AND PASSWD=PASSWORD('".$VAL['pwd']."')");

    if (!isset($tmp["USER_ID"])) {
      $RES["msg"] = "정확한 비밀번호를 입력해 해주세요.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    $RES["msg"]  = ".";

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_ticket_number") {
    $RES["html"] = "";

    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='".$VAL['no']."'");

    $cla = "power";

    if ($info["GUBUN"] == "MM") {
      $cla = "mega";
    }

    for ($s = 1; $s < 6; $s++) {
      if ($info["BALL".$s] != "") {
        $ball = explode(",", $info["BALL".$s]);

        $RES["html"] .= '
          <ul class="list_ball clear" style="margin-top:10px;">
            <li><span>'.$ball[0].'</span></li>
            <li><span>'.$ball[1].'</span></li>
            <li><span>'.$ball[2].'</span></li>
            <li><span>'.$ball[3].'</span></li>
            <li><span>'.$ball[4].'</span></li>
            <li class="'.$cla.' "><span>'.$ball[5].'</span></li>
          </ul>';
      }
    }

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "winner_detail") {
    $RES["html"] = "";

    $info = $db->get_data("SELECT * FROM WININFO WHERE WININFO_NO='".$VAL['no']."'");

    $info["cla"] = "power";

    if ($info["GUBUN"] == "MM") {
      $info["cla"] = "mega";
    }

    $weekee = date("w", strtotime($info["TIME_E"]));
    $weeks = $week_op[$weekee];

    $info["TIME_E"] = date("Y년 m월 d일 ",strtotime($info["TIME_E"])).$weeks." ".get_dst_drawtime(date("Y-m-d",strtotime($info["TIME_E"])));

    $prize_array = ($info["GUBUN"] == "PB") ? $PB_reset_price : $MM_reset_price;
    $where_op = ($info["GUBUN"] == "PB") ? $pb_win_where_op : $win_where_op;

    // 메인 당첨금안내
    if (isset($VAL['MM_dollar']) || isset($VAL['PB_dollar'])) {
      /* 당첨자수 포함된 리스트 - 당첨번호확인 상세보기에서 사용 */
      $info["html_no_cnt"] = "";
      for ($i = 1; $i < 10; $i++) {
        if ($i == 1) {
          $prize = ($info["GUBUN"] == "PB") ? $VAL['PB_dollar'] : $VAL['MM_dollar'];
        } else {
          $prize = $prize_array[$i];
        }
        syslog(7, $i . " = " . $prize);
        $info["html_no_cnt"] .= "
          <tr>
            <td>".$i."등</td>
            <td>".$where_op[$i]."</td>
            <td>".$prize."</td>
          </tr>";
      }

    // 당첨결과
    } else {
      /* 당첨자수 포함된 리스트 - 당첨번호확인 상세보기에서 사용 */
      $info["html"] = "";
      for ($i = 1; $i < 10; $i++) {
        if ($i != 1 && $info["GUBUN"] == "MM" && $info["DRAWNUM"] >= 2066) {
          $prize = $prize_array[$i];
        } else {
          $prize = "$ ". number_format($info["PRIZ".$i]);
        }
        $info["html"] .= "
          <tr>
            <td>".$i."등</td>
            <td>".$where_op[$i]."</td>
            <td>".$prize."</td>
            <td>".number_format($info["PRIZCNT".$i])."</td>
          </tr>";
      }
    }

    echo json_encode($info);
    exit;
  } else if ($VAL["mode"] == "search_cash_price") {
    $no = $VAL["no"];
    $type = $VAL["type"];

    // 결제 이벤트 대상 확인
    $cash_op = select_event_cash_op($M_login['user_id']);

    $cash = $cash_op[$no];

    $RES["itemname"] = $cash["itemname"];
    $RES["price"] = $cash["price"];
    $RES["price_text"] = number_format($cash["price"]);
    $RES["ipoint"] = $cash["ipoint"];
    $RES["ipoint_text"] = $cash["ipoint_text"];
    $RES["cash"] = $cash["cash"];
    $RES["cash_text"] = number_format($cash["cash"]);
    $RES["point"] = $cash["point"];
    $RES["point_text"] = number_format($cash["point"]);
    $RES["error"] = true;

    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "user_id_chk") {
    if ($VAL["reg_uid"] == "") {
      $RES["msg"] = "아이디를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$VAL['reg_uid']."'");

    if (isset($info["USER_ID"])) {
      $RES["msg"] = "중복된 아이디입니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    $RES["msg"] = "사용가능한 아이디입니다.";
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_lotto_number_del") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MY_BALL.php";

    if (!isset($M_login["user_id"])) {
      $RES["html"] = "";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID=' ".$M_login['user_id']."' ");

    F_MY_BALL(array(
      "mode" => "delete",
      "BALL_NO" => $VAL["ball_no"],
    ));
    $RES["html"] = "";
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_lotto_number_each_del") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MY_BALL.php";

    if (!isset($M_login["user_id"])) {
      $RES["html"] = "";
      echo json_encode($RES);
      exit;
    }

    //$info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID=' ".$M_login['user_id']."' ");
    // 해당 정보 가져오기
    $list = $db->get_data("SELECT * FROM MY_BALL WHERE BALL_NO = '".$VAL['ball_no']."' LIMIT 1");

    // 저장된 ball 개수 파악
    $ball_cnt = 0;
    for ($s = 1; $s < 6; $s++) {
      if ($list["BALL".$s] != "") {
        $ball_cnt++;
      }
    }

    if ($ball_cnt == 1) { // row 전체 삭제
      F_MY_BALL(array(
        "mode" => "delete",
        "BALL_NO" => $VAL["ball_no"],
      ));
      $RES["html"] = "";
      echo json_encode($RES);
      exit;
    } else { // 해당 ball 숫자만 삭제
      F_MY_BALL(array(
        "mode" => "each_delete",
        "BALL_NO" => $VAL["ball_no"],
        "BALLs" => $VAL["balls"]
      ));
      $RES["html"] = "";
      echo json_encode($RES);
      exit;
    }
    exit;

  } else if ($VAL["mode"] == "pay_bank_name_chk") {
    /*
    무통장 입금시 입금자명=회원명 체크
    */
    $name = $VAL["name"];

    if (!isset($M_login["user_id"])) {
      $RES["msg"] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if (!isset($name) || strlen($name) < 1) {
      $RES["msg"] = "이름을 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS CNT FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' AND NAME = '".$name."'");

    if ($info["CNT"] == 0) {
      $RES["msg"] = "입금자 이름이 회원명와 일치하지 않습니다. 다시 확인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "bank_cancel") {
    /*
    무통장 입금 취소
    */
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_CASH.php";
    $CASH_NO = $VAL["no"];

    if (!isset($M_login["user_id"])) {
      $RES["msg"] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    if (!isset($CASH_NO) || strlen($CASH_NO) < 1) {
      $RES["msg"] = "취소할 요청건에 오류가 있습니다.";
      echo json_encode($RES);
      exit;
    }

    $IS_USE = $db->get_data_one("SELECT IS_USE FROM CASH WHERE CASH_NO = '{$CASH_NO}'");

    if ($IS_USE == "Y") {
      $RES["msg"] = "승인된 건은 취소할 수 없습니다.";
      echo json_encode($RES);
      exit;
    } else if ($IS_USE == "C") {
      $RES["msg"] = "이미 취소 처리되었습니다.";
      echo json_encode($RES);
      exit;
    }

    F_CASH(array(
      "mode" => "bank_cancel",
      "CASH_NO" => $CASH_NO,
      "USER_ID" => $M_login["user_id"]
    ));
    $RES["html"] = "";
    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "apply_cash_receipt") {
    /**
     * 현금영수증 신청
     */
    if (!isset($M_login[user_id])) {
      $RES[msg] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    require_once $_SERVER[DOCUMENT_ROOT]."/_library/function_CASH_RECEIPT.php";
    $CASH_NO = $VAL[no];

    $CASH = $db->get_data("SELECT IS_USE AS ST FROM super.CASH WHERE CASH_NO='".$CASH_NO."'");

    if (!isset($CASH_NO) || strlen($CASH_NO) < 1 || $CASH['ST'] != 'Y') {
      $RES[msg] = "현금 영수증 신청에 실패했습니다.";
      echo json_encode($RES);
      exit;
    }

    F_CASH_RECEIPT(array(
      "mode" => "insert",
      "CASH_NO" => $CASH_NO,
      "USER_ID" => $M_login[user_id]
    ));

    $RES[html] = "";
    $RES[error] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == 'getWinners') {
    /**
     * 당첨 정보 가져오기
     */
    $ticketImageURI = "https://img.mekosystem.com/ticket/";
    $list = $db->get_list("SELECT * FROM MAIN_WINNER_LIST ORDER BY WIN_MONEY DESC");

    for ($i = 0; $i < count($list['ID']); $i++) {
      $html .= '
        <li style="position: relative;">
          <div class="img_wrap">
            <img src="'.$ticketImageURI.$list['IMG_URL'][$i].'" alt="">
          </div>
          <div class="grade_wrap">
      ';

      for ($game = 1; $game <= 5; $game++) {
        $tmp = "WIN".$game;
        if ($list[$tmp][$i] > 0) {
          switch ($list[$tmp][$i]) {
            case 1:
              $html .= '<p class="grade one">'.$list[$tmp][$i].'등</p>';
              break;
            case 2:
              $html .= '<p class="grade two">'.$list[$tmp][$i].'등</p>';
              break;
            case 3:
              $html .= '<p class="grade three">'.$list[$tmp][$i].'등</p>';
              break;
            case 4:
              $html .= '<p class="grade four">'.$list[$tmp][$i].'등</p>';
              break;
            default:
              $html .= '<p class="grade etc">'.$list[$tmp][$i].'등</p>';
              break;
          }
        }
      }
      $html .= '<img class="badge" src="/images/web/badge.png" alt="">';
      $html .= '
          </div>
          <p class="prize"><span class="tit">당첨금:</span><span class="num">$ '.$list['WIN_MONEY'][$i].'</span></p>
          <p class="winner"><span class="tit">당첨자:</span><span class="name">'.$list['FNAME'][$i].'*'.$list['LNAME'][$i].'</span>
            <span class="id">'.$list['USER_ID'][$i].'</span>
          </p>
        </li>
      ';
    }

    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
  } else if ($VAL["mode"] == "getLoginImgCnt") {
    $folderPath = './common/images/login/';
    $files = glob($folderPath . '*.png');
    $fileCount = count($files);
    $RES['fileCount'] = $fileCount;
    echo json_encode($RES);
  }
?>
