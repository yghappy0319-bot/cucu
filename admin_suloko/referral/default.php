<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == "read_order") {
    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='".$VAL['no']."'");
    $agent = $db->get_data("SELECT * FROM AGENT WHERE AGENT_NO='".$info['AGENT_NO']."'");

    $info['agent'] = $agent["COMPANY"];
    $info['ball_name'] = $ball_op[$info['GUBUN']];

    echo json_encode($info);
  } else if ($VAL['mode'] == "read_non_wining") {
    $info = $db->get_data("SELECT * FROM NON_WINING WHERE WINNING_NO ='".$VAL['no']."'");
    // $info['ball_name'] = $ball_op[$info['GUBUN']];
    echo json_encode($info);
  } else if ($VAL['mode'] == "as_view") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_Imglog.php";

    $html = '
      <div style="text-align: center;">
        <table class="table border text-nowrap table-striped">
          <tr>
            <td>게임구분</td>
            <td>게임1</td>
            <td>게임2</td>
            <td>게임3</td>
            <td>게임4</td>
            <td>게임5</td>
            <td>play_date</td>
            <td>연결</td>
          </tr>
          <tr><td colspan="8" style="color: red;">유사한 티켓이 없습니다.</td></tr>
        </table>
        <br>
        <img style="width: 60%;" src="'.$url1.'">
        <img style="width: 60%;" src="'.$url2.'">
        <img style="width: 60%;" src="'.$url3.'">
      </div>';

    if ($VAL['no'] == "") {
      echo json_encode(array("html"=>$html));
      exit;
    }

    $info = F_Imglog(array("mode"=>"read", "no"=>$VAL['no']));

    if (!isset($info['no'])) {
      echo json_encode(array("html"=>$html));
      exit;
    }

    $filenm = str_replace("thum_", "", $info['fileNM']);
    $gubun  = $info['codeLK'];
    $regd   = date("ymd",strtotime($info['regDate']));
    $regd2  = date("ymd",strtotime("-1 day ".$info['regDate']));
    $url1   = $s3_ticket_url.$regd."/".$filenm;
    $url2   = $s3_ticket_url.$filenm;
    $url3   = $s3_ticket_url.$regd2."/".$filenm;

    // 유사한 볼번호찾기
    $b1 = $info['ballD1ONE'];
    $b2 = $info['ballD1TWO'];
    $b3 = $info['ballD1THR'];
    $b4 = $info['ballD1FOR'];
    $b5 = $info['ballD1FIV'];
    $b6 = $info['ballP1'];

    $as1 = $b1.",".$b2.",".$b3.",";
    $as2 = ",".$b4.",".$b5.",".$b6;

    $add_query = " AND IMG_YN != 'Y' AND GUBUN='".$gubun."' AND (BALL1 LIKE '".$as1."%' OR BALL1 LIKE '%".$as2."') ";

    // $add_query = " AND GUBUN='".$info['GUBUN']."' AND (IMG_PATH='' OR IMG_PATH IS NULL)";

    // for ($s = 1; $s < 6; $s++) {
    //   if ($info['ballD'.$s.'ONE'] != "" && $info['ballD'.$s.'TWO'] != "" && $info['ballD'.$s.'FIV'] != "" && $info['ballP'.$s] != "") {
    //     $ball1 = intval($info['ballD'.$s.'ONE']).",".intval($info['ballD'.$s.'TWO']).",%,".intval($info['ballD'.$s.'FIV']).",".intval($info['ballP'.$s]);
    //     $ball2 = intval($info['ballD'.$s.'ONE']).",".intval($info['ballD'.$s.'TWO']).",%,".intval($info['ballP'.$s]);
    //     $ball3 = "%,".intval($info['ballD'.$s.'FOR']).",".intval($info['ballD'.$s.'FIV']).",".intval($info['ballP'.$s]);
    //     $add_query .= " AND (BALL".$s." LIKE '".$ball1."' OR BALL".$s." LIKE '".$ball2."' OR BALL".$s." LIKE '".$ball2."' OR BALL".$s." LIKE '".$ball3."')";
    //   }
    // }

    $list = $db->get_list("SELECT * FROM ORDERS WHERE 1 ".$add_query);

    if (!is_array($list)) {
      $list = array();
      $list['ORDERS_NO'] = array();
    }

    $html = '
      <div style="text-align: center;">
        <table class="table border text-nowrap table-striped">
          <tr>
            <td>게임구분</td>
            <td>게임1</td>
            <td>게임2</td>
            <td>게임3</td>
            <td>게임4</td>
            <td>게임5</td>
            <td>play_date</td>
            <td>연결</td>
          </tr>';

    for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
      $html .= '
        <tr>
          <td>'.$list['GUBUN'][$i].'</td>
          <td>'.$list['BALL1'][$i].'</td>
          <td>'.$list['BALL2'][$i].'</td>
          <td>'.$list['BALL3'][$i].'</td>
          <td>'.$list['BALL4'][$i].'</td>
          <td>'.$list['BALL5'][$i].'</td>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td><a style="cursor: pointer; color: blue; font-weight: bold;" class="asOkBtn" data-no="'.$list['ORDERS_NO'][$i].'" data-otseq="'.$VAL['no'].'">연결</a></td>
        </tr>';
    }

    if (count($list['ORDERS_NO']) == 0) {
      $html .= '<tr><td colspan="8" style="color: red;">유사한 티켓이 없습니다.</td></tr>';
    }

    $html .= '
        </table>
        <br>
        <img style="width: 30%;" src="'.$url1.'">
        <img style="width: 30%;" src="'.$url2.'">
        <img style="width: 30%;" src="'.$url3.'">
      </div>';

    echo json_encode(array("html"=>$html));
    exit;
  } else if ($VAL['mode'] == 'as_all_view') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_Imglog.php";

    if ($VAL['no'] == '') {
      echo json_encode(array("url1"=>"", "url2"=>"", "url3"=>"", "codeLK"=>""));
      exit;
    }

    $info = F_Imglog(array("mode"=>"read", "no"=>$VAL['no']));

    if (!isset($info['no'])) {
      echo json_encode(array("url1"=>"", "url2"=>"", "url3"=>"", "codeLK"=>""));
      exit;
    }

    $filenm = str_replace("thum_", "", $info['fileNM']);
    $gubun  = $info['codeLK'];
    $regd   = date("ymd", strtotime($info['regDate']));
    $regd2  = date("ymd", strtotime("-1 day ".$info['regDate']));
    $url1   = $s3_ticket_url.$regd."/".$filenm;
    $url2   = $s3_ticket_url.$filenm;
    $url3   = $s3_ticket_url.$regd2."/".$filenm;

    echo json_encode(array("url1"=>$url1, "url2"=>$url2, "url3"=>$url3, "codeLK"=>$gubun));
    exit;
  } else if ($VAL['mode'] == "as_search") {
    $ball1 = $VAL['ball1'];
    $codeLK = $VAL['codeLK'];

    // $list = $db->get_list("SELECT * FROM ORDERS WHERE BALL1 LIKE '".$ball1."%' AND GUBUN='".$codeLK."'");
    $list = $db->get_list("SELECT * FROM ORDERS WHERE BALL1 LIKE '".$ball1."%'");

    if (!is_array($list)) {
      $list = array();
      $list['ORDERS_NO'] = array();
    }

    $html = "";

    for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
      $html .= '
        <tr>
          <td>'.$list['GUBUN'][$i].'</td>
          <td>'.$list['BALL1'][$i].'</td>
          <td>'.$list['BALL2'][$i].'</td>
          <td>'.$list['BALL3'][$i].'</td>
          <td>'.$list['BALL4'][$i].'</td>
          <td>'.$list['BALL5'][$i].'</td>
          <td>'.$list['REG_DATE'][$i].'</td>';

      if ($list['IMG_PATH'][$i] == "") {
        $html .= '<td><a style="cursor: pointer; color: blue; font-weight: bold;" class="asOkBtn" data-no="'.$list['ORDERS_NO'][$i].'" data-otseq="'.$VAL['no'].'">연결</a></td>';
      } else {
        $html .= '<td><span style="color: black;">연동</span></td>';
      }
      $html .= '</tr>';
    }

    if (count($list['ORDERS_NO']) == 0) {
      $html .= '<tr><td colspan="8" style="color: red;">유사한 티켓이 없습니다.</td></tr>';
    }

    echo json_encode(array("html"=>$html));
    exit;
  } else if ($VAL['mode'] == 'margin_in') {
    if ($VAL['mGubun'] == 'MM') {
      setcookie("mmWidthPrint", $VAL['mWidth'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
      setcookie("mmTopPrint", $VAL['mTop'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
      setcookie("mmLeftPrint", $VAL['mLeft'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
    } else if ($VAL['mGubun'] == 'PB') {
      setcookie("pbWidthPrint", $VAL['mWidth'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
      setcookie("pbTopPrint", $VAL['mTop'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
      setcookie("pbLeftPrint", $VAL['mLeft'], time() + 1*365*24*3600, "/", $_SERVER['HTTP_HOST']);
    }

    $rtns = array("error"=>true);
    echo json_encode($rtns);
    exit;
  } else if ($VAL['mode'] == 'all_up_status') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";

    if (count($chk_no) > 0) {
      $cash = 10000;
      for ($i = 0; $i < count($chk_no); $i++) {
        $info = $db->get_data("SELECT * FROM NON_WINING WHERE WINNING_NO='".$chk_no[$i]."'");

        if ($info['STATUS'] == $VAL['up_status']) {
          continue;
        }

        $sql = "
          UPDATE
            NON_WINING
          SET
            STATUS = '".$VAL['up_status']."'
          WHERE
            WINNING_NO = '".$chk_no[$i]."'
        ";
        $db->query($sql);

        if ($VAL['up_status'] == 'D') {
          $order = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='".$info["ORDERS_NO"]."'");
          $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$order["USER_ID"]."'");

          // 캐시사용로그
          $cash_log = array();
          $cash_log['mode']        = "insert";
          $cash_log['CASH_NO']     = 0;
          $cash_log['ORDERS_NO']   = 0;
          $cash_log['WINNING_NO']  = $chk_no[$i];
          $cash_log['INVOCE_NO']   = 0;
          $cash_log['CASH_LOG_NO'] = 0;
          $cash_log['PRICE']       = 0;
          $cash_log['CASH']        = $cash;
          $cash_log['N_CASH']      = $mem['CASH'] + $cash;
          $cash_log['O_CASH']      = $mem['CASH'];
          $cash_log['USER_ID']     = $order['USER_ID'];
          $cash_log['MEMO']        = "낙첨복권배송비 - 취소";
          $cash_log['STATUS']      = "P";
          F_T_CASH_LOG($cash_log);

          $sql = "
            UPDATE
              MEMBER
            SET
              CASH = CASH+'".$cash."'
            WHERE
              USER_ID = '".$order["USER_ID"]."'
          ";
          $db->query($sql);
        }
      }
      echo json_encode(array("error"=>1, "msg"=>"변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"상태변경할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'all_ticked_del') {
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $info = F_ORDERS(array("mode"=>"read", "ORDERS_NO"=>$chk_no[$i]));

        $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$info['USER_ID']."'");

        $db->query("DELETE FROM T_CASH_LOG WHERE ORDERS_NO='".$chk_no[$i]."'");
        $db->query("DELETE FROM T_POINT_LOG WHERE ORDERS_NO='".$chk_no[$i]."'");
        $db->query("DELETE FROM T_IPOINT_LOG WHERE ORDERS_NO='".$chk_no[$i]."'");

        F_ORDERS(array("mode"=>"delete", "ORDERS_NO"=>$chk_no[$i]));

        $sql = "
          UPDATE
            MEMBER
          SET
            POINT=POINT+".$info['POINT'].",
            IPOINT=IPOINT+".$info['IPOINT'].",
            CASH=CASH+".$info['CASH'].",
            TOTPRICE=TOTPRICE-".$info['CASH'].",
            TOTCNT=TOTCNT-".$info['GAMECNT']."
          WHERE
            USER_ID = '".$info['USER_ID']."'
          ";
        $db->query($sql);

        $in_ipoint   = 0;
        $in_ipoint_p = 0;

        // 브라운 0.5, 실버2.5, 골드 5, 플래티넘 8 마일리지 적립
        switch($mem['LEVEL']) {
          case 2:
            $in_ipoint = ceil($info['PAYMENT'] / 100 * 2.5);
            break;
          case 3:
            $in_ipoint = ceil($info['PAYMENT'] / 100 * 5);
            break;
          case 4:
            $in_ipoint = ceil($info['PAYMENT'] / 100 * 8);
            break;
          default:
            $in_ipoint = ceil($info['PAYMENT'] / 100 * 0.5);
            break;
        }

        $sql = "
          UPDATE
            MEMBER
          SET
            IPOINT=IPOINT-".$in_ipoint."
          WHERE
            USER_ID = '".$info['USER_ID']."'
        ";
        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"삭제하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"삭제할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'win_is_use') {
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $sql = "UPDATE ORDERS SET IS_USE='".$VAL['type']."' WHERE ORDERS_NO='".$chk_no[$i]."'";
        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"수령 변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"변경할 항목이 없습니다."));
    }
  }
?>
