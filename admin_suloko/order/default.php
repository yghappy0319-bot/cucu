<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == "read_order") {
    $info  = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='{$VAL['no']}'");
    $agent = $db->get_data("SELECT * FROM AGENT WHERE AGENT_NO='{$info['AGENT_NO']}'");

    $info['agent']     = $agent["COMPANY"];
    $info['ball_name'] = $ball_op[$info['GUBUN']];

    echo json_encode($info);
  } else if ($VAL['mode'] == "read_non_wining") {
    $info = $db->get_data("SELECT * FROM NON_WINING WHERE WINNING_NO ='{$VAL['no']}'");
    //$info['ball_name']  =  $ball_op[$info['GUBUN']];
    echo json_encode($info);
  } else if ($VAL['mode'] == "as_view") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_Imglog.php";

    $html = '
      <div style="text-align:center">
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
          <tr><td colspan="8" style="color:red;">유사한 티켓이 없습니다.</td></tr>
        </table>
        <br>
        <img style="width:60%;" src="'.$url1.'">
        <img style="width:60%;" src="'.$url2.'">
      </div>';

    if ($VAL['no'] == '') {
      echo json_encode(array("html" => $html));
      exit;
    }

    $info = F_Imglog(array("mode" => "read", "no" => $VAL['no']));

    if (!isset($info['no'])) {
      echo json_encode(array("html" => $html));
      exit;
    }

    $filenm = str_replace("thum_", "", $info['fileNM']);
    $gubun  = $info['codeLK'];
    $regd   = date("ymd", strtotime($info['regDate']));
    $url1   = "https://img.suloko.com/scan3/scan1_end/".$regd."/".$filenm;
    $url2   = "https://img.suloko.com/scan1/fail/".$filenm;

    // 유사한 볼번호찾기
    $b1     = $info['ballD1ONE'];
    $b2     = $info['ballD1TWO'];
    $b3     = $info['ballD1THR'];
    $b4     = $info['ballD1FOR'];
    $b5     = $info['ballD1FIV'];
    $b6     = $info['ballP1'];

    $as1    = $b1.",".$b2.",".$b3.",";
    $as2    = ",".$b4.",".$b5.",".$b6;

    $add_query = " AND IMG_YN != 'Y' AND PRINT_YN = 'Y' AND GUBUN='".$gubun."' AND (BALL1 LIKE '".$as1."%' OR BALL1 LIKE '%".$as2."') AND REG_DATE > (CURDATE()-INTERVAL 3 DAY)";
    $list = $db->get_list("SELECT * FROM ORDERS WHERE 1 ".$add_query);

    if (!is_array($list)) {
      $list = array();
      $list['ORDERS_NO'] = array();
    }

    $html = '
      <div style="text-align: center;">
        <div class="text-start text-danger">* 출력완료 티켓 중 A게임 앞번호 3개 또는 뒷번호 3개(파워볼포함)가 일치하는 티켓을 자동으로 검색합니다.</div>
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
      $alert_gamecnt = "";
      if ($list['GAMECNT'][$i] != $gamecnt) {
        $alert_gamecnt = "<span class='small text-danger blink' style=''>게임수 불일치</span><br>";
      }
      $html .= '
          <tr>
            <td>'.$list['GUBUN'][$i].'</td>
            <td>'.$list['BALL1'][$i].'</td>
            <td>'.$list['BALL2'][$i].'</td>
            <td>'.$list['BALL3'][$i].'</td>
            <td>'.$list['BALL4'][$i].'</td>
            <td>'.$list['BALL5'][$i].'</td>
            <td>'.$list['REG_DATE'][$i].'</td>
            <td>'.$alert_gamecnt.'<a style="cursor:pointer;color:blue;font-weight:bold;" class="asOkBtn" data-no="'.$list['ORDERS_NO'][$i].'" data-otseq="'.$VAL['no'].'">연결</a></td>
          </tr>';
    }

    if (count($list['ORDERS_NO']) == 0) {
      $html .= '<tr><td colspan="8" style="color:red;">유사한 티켓이 없습니다.</td></tr>';
    }

    $html .= '
        </table>
        <br>
        <img style="width:30%;" src="'.$url1.'" title="업로드이미지">
        <img style="width:30%;" src="'.$url2.'" title="처리실패이미지">
      </div>';

    echo json_encode(array("html" => $html));
    exit;
  } else if ($VAL['mode'] == 'as_all_view') {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_Imglog.php";

    if ($VAL['no'] == '') {
      echo json_encode(array("url1" => "", "url2" => "", "codeLK" => ""));
      exit;
    }

    $info = F_Imglog(array("mode" => "read", "no" => $VAL['no']));

    if (!isset($info['no'])) {
      echo json_encode(array("url1" => "", "url2" => "", "codeLK" => ""));
      exit;
    }

    $filenm = str_replace("thum_", "", $info['fileNM']);
    $gubun  = $info['codeLK'];
    $regd   = date("ymd", strtotime($info['regDate']));
    $regd2  = date("ymd", strtotime("-1 day ".$info['regDate']));
    $url1   = "https://img.suloko.com/scan3/scan1_end/".$regd."/".$filenm;
    $url2   = "https://img.suloko.com/scan1/fail/".$filenm;

    echo json_encode(array("url1" => $url1, "url2" => $url2, "codeLK" => $gubun));
    exit;
  } else if ($VAL['mode'] == "as_search") {
    $ball1  = $VAL['ball1'];
    $codeLK = $VAL['codeLK'];
    $gamecnt = $VAL['gamecnt'];

    $list = $db->get_list("SELECT * FROM ORDERS WHERE BALL1 LIKE '".$ball1."%' AND PRINT_YN = 'Y' AND REG_DATE > (CURDATE()-INTERVAL 3 DAY)");

    if (!is_array($list)) {
      $list = array();
      $list['ORDERS_NO'] = array();
    }

    $html = '';
    for ($i = 0; $i < count($list['ORDERS_NO']); $i++) {
      $alert_gamecnt = "";
      $alert_codeLK = "";
      $html .= '
        <tr>
          <td>'.$list['GUBUN'][$i].'</td>
          <td>'.$list['BALL1'][$i].'</td>
          <td>'.$list['BALL2'][$i].'</td>
          <td>'.$list['BALL3'][$i].'</td>
          <td>'.$list['BALL4'][$i].'</td>
          <td>'.$list['BALL5'][$i].'</td>
          <td>'.$list['REG_DATE'][$i].'</td>';

      if ($list['GAMECNT'][$i] != $gamecnt) {
        $alert_gamecnt = "<span class='small text-danger blink' style=''>게임수 불일치</span><br>";
      }

      if ($list['GUBUN'][$i] != $codeLK) {
        $alert_codeLK = "<span class='small text-blue blink' style=''>게임구분 불일치</span><br>";
      }

      if ($list['IMG_PATH'][$i] == '') {
        $html .= '<td>'.$alert_codeLK.$alert_gamecnt.'<a style="cursor:pointer;color:blue;font-weight:bold;" class="asOkBtn" data-no="'.$list['ORDERS_NO'][$i].'" data-otseq="'.$VAL['no'].'">연결</a></td>';
      } else {
        $html .= '<td><span style="color:black;">연동완료</span></td>';
      }
      $html .= '</tr>';
    }

    if (count($list['ORDERS_NO']) == 0) {
      $html .= '<tr><td colspan="8" style="color:red;">유사한 티켓이 없습니다.</td></tr>';
    }

    echo json_encode(array("html" => $html));
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

    $rtns = array("error" => true);
    echo json_encode($rtns);
    exit;
  } else if ($VAL['mode'] == 'all_up_status') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";

    if (count($chk_no) > 0) {
      $cash = 10000;
      for ($i = 0; $i < count($chk_no); $i++) {
        $info = $db->get_data("SELECT * FROM NON_WINING WHERE WINNING_NO='{$chk_no[$i]}'");

        if ($info['STATUS'] == $VAL['up_status']) {
          continue;
        }

        if ($VAL['up_status'] == 'D') {
          // USA 취소 요청
          $postData = json_encode([
            clientId => $usClientId,
            orderNo  => $info["ORDERS_NO"],
          ]);
          $path = "delivery/cancel";
          $result = json_decode(sendRequestToUSAServer($path, $postData), true);

          if ($result["code"] !== "0000") {
            echo json_encode(array("error"=>0, "msg"=>"취소에 실패했습니다."));
            exit;
          }

          $order = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='{$info["ORDERS_NO"]}'");
          $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$order["USER_ID"]}'");

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

          $sql = "UPDATE MEMBER SET CASH = CASH + '".$cash."' WHERE USER_ID = '".$order["USER_ID"]."'";
          $db->query($sql);
        }

        if($VAL['up_status']=="H"){
          $sql = "UPDATE NON_WINING SET ADMIN_STATUS = '{$VAL['up_status']}' WHERE WINNING_NO = '{$chk_no[$i]}'";
        }else{
          $sql = "UPDATE NON_WINING SET STATUS = '{$VAL['up_status']}' WHERE WINNING_NO = '{$chk_no[$i]}'";
        }

        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"상태변경할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'all_ticked_del') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    include $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";

    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {

        // USA 취소 요청
        $postData = json_encode([
          clientId => $usClientId,
          orderNo  => $chk_no[$i],
        ]);
        $path = "ticket/cancel";
//        $result = json_decode(sendRequestToUSAServer($path, $postData), true);
//
//        if ($result["code"] === "ERROR1008") {
//          echo json_encode(array("error"=>0, "msg"=>"구매가 진행되어 취소할 수 없습니다."));
//          exit;
//        } else if ($result["code"] !== "0000") {
//          echo json_encode(array("error"=>0, "msg"=>"취소에 실패했습니다. 관리자에게 확인 요청 바랍니다."));
//          exit;
//        }

        $info = F_ORDERS(array("mode"=>"read", "ORDERS_NO"=>$chk_no[$i]));
        $mem  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

        $db->query("DELETE FROM T_CASH_LOG WHERE ORDERS_NO='{$chk_no[$i]}'");
        $db->query("DELETE FROM T_WCASH_LOG WHERE ORDERS_NO='{$chk_no[$i]}'");
        $db->query("DELETE FROM T_POINT_LOG WHERE ORDERS_NO='{$chk_no[$i]}'");
        $db->query("DELETE FROM T_IPOINT_LOG WHERE ORDERS_NO='{$chk_no[$i]}'");

        F_ORDERS(array("mode"=>"delete", "ORDERS_NO"=>$chk_no[$i]));

        $sql = "
          UPDATE
            MEMBER
          SET
            POINT    = POINT + {$info['POINT']},
            IPOINT   = IPOINT + {$info['IPOINT']},
            CASH     = CASH + {$info['CASH']},
            WINCASH  = WINCASH + {$info['WINCASH']},
            TOTPRICE = TOTPRICE - {$info['CASH']},
            TOTCNT   = TOTCNT - {$info['GAMECNT']}
          WHERE
            USER_ID = '{$info['USER_ID']}'
        ";
        $db->query($sql);

        $in_ipoint   = 0;
        $in_ipoint_p = 0;

        // 브라운 0.5, 실버2.5, 골드 5, 플래티넘 8 마일리지 적립
        // 차감기준 변경 PAYMENT -> CASH [SLK-315]
        // 당첨금이 대상에 포함 [SLK-749]
        switch($mem['LEVEL']) {
          case 2:
            $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 2.5);
            break;
          case 3:
            $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 5);
            break;
          case 4:
            $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 8);
            break;
          default:
            $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 0.5);
            break;
        }

        $sql = "
          UPDATE
            MEMBER
          SET
            IPOINT = IPOINT - {$in_ipoint}
          WHERE
            USER_ID = '{$info['USER_ID']}'
        ";
        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"취소하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"취소할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'win_is_use') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $sql = "UPDATE ORDERS SET IS_USE='".$VAL['type']."' WHERE ORDERS_NO='".$chk_no[$i]."'";
        $db->query($sql);
      }
      echo json_encode(array("error"=>1, "msg"=>"수령 변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"변경할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'search_order_memo') { //SLK-497
      $info = $db->get_data("SELECT USER_ID, PRICE, (SELECT NAME FROM MEMBER WHERE USER_ID=C.USER_ID) AS NAME FROM CASH AS C WHERE CASH_NO='".$VAL['no']."'");

      if ($info["USER_ID"]) {
        $info_memo = $db->get_data("SELECT MEMO, REG_DATE, STATUS FROM CASH_BANK_MEMO WHERE CASH_NO='".$VAL['no']."'");
        if (!$info_memo) {
          $info_memo['MEMO'] = "";
          $info_memo['REG_DATE'] = "-";
          $info_memo['CASH_NO'] = $VAL['no'];
          $info_memo['STATUS'] = "N";
        } else {
          $info_memo['MEMO'] = $info_memo['MEMO'];
          $info_memo['REG_DATE'] = $info_memo['REG_DATE'];
          $info_memo['CASH_NO'] = $VAL['no'];
          $info_memo['STATUS'] = $info_memo['STATUS'];
        }
        $info_memo['USER'] = $info["NAME"]." / ₩".number_format($info['PRICE']);
        echo json_encode(array("error"=>1, "MEMO"=>$info_memo['MEMO'], "REG_DATE"=>$info_memo['REG_DATE'], "CASH_NO"=>$info_memo['CASH_NO'], "USER_ID"=>$info['USER_ID'], "STATUS"=>$info_memo['STATUS'], "USER"=>$info_memo['USER']));
      } else {
        echo json_encode(array("error"=>0, "msg"=>"무통장 입금건이 없습니다."));
      }
    } else if ($VAL['mode'] == 'insert_order_memo') { //SLK-497
      include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
      $info_memo = $db->get_data("SELECT COUNT(*) AS CNT FROM CASH_BANK_MEMO WHERE CASH_NO='".$VAL['CASH_NO']."'");

      if ($info_memo["CNT"]) {
        $query = "UPDATE CASH_BANK_MEMO SET MEMO = '".addslashes($VAL['memo'])."', STATUS = '".$VAL['STATUS']."', REG_DATE = now() WHERE CASH_NO = ".$VAL['CASH_NO'];
      } else {
        $query = " INSERT INTO CASH_BANK_MEMO (CASH_NO, MEMO, USER_ID, STATUS, REG_DATE) VALUES ('".$VAL['CASH_NO']."', '".addslashes($VAL['memo'])."', '".$VAL['USER_ID']."', '".$VAL['STATUS']."', now())";
      }
      $db->query($query);
      echo json_encode(array("error"=>1, "msg"=>"메모가 저장완료 되었습니다."));
  } else if ($VAL['mode'] == 'insert_order_memo_admin') { // SLK-515
      include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
      $query = " INSERT INTO CASH_BANK_MEMO (MEMO, USER_ID, STATUS, REG_DATE, IS_ADMIN) VALUES ('".addslashes($VAL['memo'])."', '', 'Y', now(), 'Y')";
      $db->query($query);
      echo json_encode(array("error"=>1, "msg"=>"메모를 수정했습니다."));
  } else if ($VAL['mode'] == 'list_order_memo_admin') { // SLK-515
      $html = '<div class="col-md-12" style="text-align: center; padding: 0;">
                <table class="table border table-striped">
                  <tr>
                    <td>작성일</td>
                    <td>내용</td>
                    <td>삭제</td>
                  </tr>';
      $list = $db->get_list("SELECT * FROM CASH_BANK_MEMO WHERE IS_ADMIN ='Y' ORDER BY IDX DESC");

      for ($i = 0; $i < count($list['IDX']); $i++) {
        $html .= '<tr>
                    <td>'.$list['REG_DATE'][$i].'</td>
                    <td tyle="display: inline-block; white-space: nowrap; overflow: hidden;">'.nl2br($list['MEMO'][$i]).'</td>
                    <td><a class="btn btn-danger asOkBtn" style="color: #FFFFFF;" data-no="'.$list['IDX'][$i].'">삭제</a></td>
                  </tr>';
      }

      if (count($list['MEMO']) == 0) {
        $html .= '<tr><td colspan="3" >작성된 내용이 없습니다.</td></tr>';
      }
      $html .= '</table></div>';
      echo json_encode(array("error"=>1, "page_html"=>$html));
  } else if ($VAL['mode'] == 'delete_order_memo_admin') { //SLK-515
      include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
      $info_memo = $db->get_data("SELECT COUNT(*) AS CNT FROM CASH_BANK_MEMO WHERE IDX='".$VAL['no']."'");
      if ($info_memo["CNT"]) {
        $query = "DELETE FROM CASH_BANK_MEMO WHERE IDX = ".$VAL['no'];
        $db->query($query);
      }
      echo json_encode(array("error"=>1, "msg"=>"메모를 삭제했습니다."));
  } else if ($VAL['mode'] == 'list_order_memo') { //SLK-524

      $html = '<div style="text-align: center;">
                <table  class="table border table-striped" style="width: 100%; table-layout: fixed;">
                  <tr>
                    <td>성명</td>
                    <td>결제금액</td>
                    <td>주문상태</td>
                    <td>결제일</td>
                    <!--td>메모내용</td-->
                    <td>메모작성일</td>
                    <td>메모상태</td>
                  </tr>';

      $query = "
        SELECT
          c.PRICE, c.REG_DATE, c.CASH_NO, c.IS_USE,
          m.NAME AS NAME,
          b.MEMO, b.STATUS, b.REG_DATE AS REG_DATE_MEMO
        FROM
          CASH AS c
            LEFT JOIN
          MEMBER AS m ON m.USER_ID=c.USER_ID
            LEFT JOIN
          CASH_BANK_MEMO AS b ON c.CASH_NO=b.CASH_NO
        WHERE CARDNAME = '무통장' AND b.IDX != ''
        ORDER BY c.REG_DATE DESC
        LIMIT 20
      ";
      $list = $db->get_list($query);

      for ($i =0; $i < count($list['PRICE']); $i++) {
        if ($list['STATUS'][$i] == "Y") {
          $STATUS = "완료";
        } else {
          $STATUS = "<font color='blue'>미완료</font>";
        }
        if ($list['IS_USE'][$i] == "Y") {
          $IS_USE = "입금확인완료";
        } else if ($list['IS_USE'][$i] == "N") {
          $IS_USE = "입금대기";
        } else {
          $IS_USE = "<font color='blue'>입금취소</font>";
        }

        $html .= '
          <tr style=”word-break: break-all;”>
            <td style="width: 10%; padding: 5px;">'.$list['NAME'][$i].'</td>
            <td style="width: 10%; padding: 5px;">₩ '.number_format($list['PRICE'][$i]).'</td>
            <td style="width: 10%; padding: 5px;">'.$IS_USE.'</td>
            <td style="width: 10%; padding: 5px;">'.$list['REG_DATE'][$i].'</td>
            <td style="width: 10%; padding: 5px;">'.$list['REG_DATE_MEMO'][$i].'</td>
            <td style="width: 10%; padding: 5px;">'.$STATUS.'</td>
          </tr>
          <tr></tr>
          <tr>
            <td colspan="6" style="text-align: left; padding-left: 20px;">'.nl2br($list['MEMO'][$i]).'</td>
          </tr>';
      }
      if (count($list['MEMO']) == 0) {
        $html .= '<tr><td colspan="3" >작성된 내용이 없습니다.</td></tr>';
      }
      $html .= '</table></div>';

      echo json_encode(array("error" => 1, "page_html" => $html));
  } else if ($VAL[mode] == 'apply_cash_receipt') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER[DOCUMENT_ROOT]."/_library/function_CASH_RECEIPT.php";

    $CASH_NO = $VAL[no];

    if (!isset($CASH_NO) || strlen($CASH_NO) < 1) {
      $RES[msg] = "현금 영수증 신청에 실패했습니다.";
      echo json_encode($RES);
      exit;
    }

    F_APPLY_CASH_RECEIPT(array(
      "mode"    => "update",
      "CASH_NO" => $CASH_NO
    ));

    $RES[html] = "";
    $RES[error] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL[mode] == 'all_apply_cash_receipt') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER[DOCUMENT_ROOT]."/_library/function_CASH_RECEIPT.php";

    $CASH_NOS = $VAL[chk_no];

    $sql = "SELECT CASH_NO FROM CASH_RECEIPT WHERE STATUS != 'Y' AND CASH_NO IN ($CASH_NOS)";
    $list = $db->get_list($sql);

    foreach ($list["CASH_NO"] as $CASH_NO) {
      F_APPLY_CASH_RECEIPT(array(
        "mode"    => "update",
        "CASH_NO" => $CASH_NO
      ));
    }

    $RES[html] = "";
    $RES[error] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL[mode] == 'delete_cash_receipt') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER[DOCUMENT_ROOT]."/_library/function_CASH_RECEIPT.php";

    $CASH_NO = $VAL[no];

    if (!isset($CASH_NO) || strlen($CASH_NO) < 1) {
      echo json_encode(array("error" => 0, "msg" => "삭제할 항목이 없습니다."));
      exit;
    }

    F_DELETE_CASH_RECEIPT(array(
      "mode"    => "delete",
      "CASH_NO" => $CASH_NO
    ));

    echo json_encode(array("error" => 1, "msg" => "삭제하였습니다."));
    exit;
  } else if ($VAL['mode'] == 'all_del') {
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

      echo json_encode(array("error"=>1, "msg"=>"삭제하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"삭제할 항목이 없습니다."));
    }
  } else if ($VAL['mode'] == 'insert_order_bank_admin') { // 관리자 무통장 입금 내역 추가
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    include "../_library/function_CASH.php";

    if ($VAL['user_id'] == '') {
      echo json_encode(array("error"=>0, "msg"=>"잘못된 접근입니다."));
      exit;
    }

      $mem = $db->get_data("SELECT MEMBER_NO, USER_ID FROM MEMBER WHERE USER_ID='".$VAL['user_id']."'");
      if ($mem['MEMBER_NO'] ) {
        // 충전캐쉬 처리 (CASH_CONFIG 금액구간 우선)
        $order_price = str_replace(',', '', $VAL['order_price']);
        $order_cash = calc_cash_supply_by_price($order_price);

        $ITEMNAME = "슈로코 (".number_format($order_cash).")";
        $ip_address = "0.0.0.0"; // 관리자 수동입력 확인용

        // CASH 저장
        $VAL['mode']         = "insert";
        $VAL['MEMBER_NO']    = $mem['MEMBER_NO'];
        $VAL['USER_ID']      = $mem['USER_ID'];
        $VAL['IS_USE']       = "N";
        $VAL['IPOINT']       = 0;
        $VAL['CASH']         = $order_cash;
        $VAL['PRICE']        = $order_price;
        $VAL['ITEMNAME']     = $ITEMNAME; // 상품명
        $VAL['CARDNAME']     = "무통장";
        $VAL['PATNER_ID']    = "";
        $VAL['SELLER_GUBUN'] = 'PC';
        $VAL['CHK_SECOND']   = "";
        $VAL['IN_IP']        = $ip_address;
        $VAL['PARTNER_ID']   = "";  // 무통장입금일 경우에는 입금완료 시에만 처리(관리자)
        $VAL['CASH_NO']      = $db->get_data_one("SELECT MAX(CASH_NO) as CASH_NO FROM CASH") + 1;

        // 결제데이터 입력
        F_CASH($VAL);

        echo json_encode(array("error"=>1));
      } else {
        echo json_encode(array("error"=>0, "msg"=>"회원이 없습니다."));
    }
    exit;
  } else if ($VAL['mode'] == 'all_ticked_hide') { // 숨김여부 업데이트
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    if (count($chk_no) > 0) {
      for ($i = 0; $i < count($chk_no); $i++) {
        $sql = "UPDATE ORDERS SET HIDE_YN = ".($VAL['status'] == 'Y' ? "'Y'" : "NULL")." WHERE ORDERS_NO='".$chk_no[$i]."'";
        $db->query($sql);
      }

      echo json_encode(array("error"=>1 ,"msg"=>"숨김상태를 변경하였습니다."));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"변경할 항목이 없습니다."));
    }

    exit;
  } else if ($VAL['mode'] == 'cancel_bank') { // 무통장완료건 취소처리
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";

    if ($VAL['no'] == '' || $VAL['IS_USE_N'] == '') {
      echo json_encode(array("error"=>1, "msg"=>"필수값이 누락되었습니다."));
      exit;
    }

    if ($VAL['IS_USE_N'] == 'C2' && $VAL['cancel_price'] <= 0) {
      echo json_encode(array("error"=>1, "msg"=>"필수값이 누락되었습니다2."));
      exit;
    }

    // 결제건 정보
    $cash = $db->get_data("SELECT * FROM CASH WHERE CASH_NO = '{$VAL['no']}'");

    if (empty($cash['CASH_NO'])) {
      echo json_encode(array("error"=>1, "msg"=>"결제건이 존재하지 않습니다."));
      exit;
    }

    // 회원 정보
    $mem = $db->get_data("SELECT * FROM MEMBER WHERE MEMBER_NO = '{$cash['MEMBER_NO']}'");

    if (empty($mem['MEMBER_NO'])) {
      echo json_encode(array("error"=>1, "msg"=>"회원이 존재하지 않습니다."));
      exit;
    }


    // 취소, 대기 처리
    if ($VAL['IS_USE_N'] == "C" || $VAL['IS_USE_N'] == "N") {
      if ($mem['CASH'] < $cash['CASH']) {
        echo json_encode(array("error"=>1, "msg"=>"차감할 캐시가 부족합니다."));
        exit;
      }

      $포인트쿼리 = "select * from CASH_CONFIG where CASH = {$cash['CASH']} ";
      $지급포인트 = $db->get_data($포인트쿼리);
      $POINT = !empty($지급포인트['POINT']) ? (int)$지급포인트['POINT'] : 0;
      $deductPoint = min((int)$mem['POINT'], $POINT);
      if ($deductPoint < 0) {
        $deductPoint = 0;
      }

      // 보너스업 이벤트 보너스포인트 차감 (보유분까지만)
      if ($deductPoint > 0) {
        //보유포인트차감로그
        $point_log = array();
        $point_log['mode'] = "insert";
        $point_log['COUPON_NO'] = 0;
        $point_log['ORDERS_NO'] = 0;
        $point_log['CASH_LOG_NO'] = $cash['CASH_NO'];
        $point_log['POINT'] = $deductPoint;
        $point_log["N_POINT"] = $mem['POINT'] - $deductPoint;
        $point_log['O_POINT'] = $mem['POINT'];
        $point_log['USER_ID'] = $mem['USER_ID'];
        $point_log['MEMO'] = "캐시충전보너스 차감";
        $point_log['STATUS'] = "M";

        F_T_POINT_LOG($point_log);

        // MEMBER POINT 차감
        $query = "
          UPDATE
            MEMBER
          SET
            POINT = POINT - '{$deductPoint}'
          WHERE
            MEMBER_NO='{$mem['MEMBER_NO']}'
        ";
        $db->query($query);
      }

      // 캐시사용로그
      $cash_log = array();
      $cash_log['mode']        = "insert";
      $cash_log['CASH_NO']     = $VAL['no'];
      $cash_log['ORDERS_NO']   = 0;
      $cash_log['WINNING_NO']  = 0;
      $cash_log['INVOCE_NO']   = 0;
      $cash_log['CASH_LOG_NO'] = 0;
      $cash_log['PRICE']       = $cash['PRICE'];
      $cash_log['CASH']        = $cash['CASH'];
      $cash_log['N_CASH']      = $mem['CASH'] - $cash['CASH'];
      $cash_log['O_CASH']      = $mem['CASH'];
      $cash_log['USER_ID']     = $mem['USER_ID'];
      $cash_log['MEMO']        = $S_login['user_id']."캐시구매취소(무통장)";
      $cash_log['STATUS']      = "M";

      F_T_CASH_LOG($cash_log);

      // MEMBER CASH 차감
      $query = "
        UPDATE
          MEMBER
        SET
          CASH = CASH - '{$cash['CASH']}'
        WHERE
          MEMBER_NO='{$mem['MEMBER_NO']}'
      ";
      $db->query($query);

      // CASH Update
      $query = "
        UPDATE
          CASH
        SET
          IS_USE = '{$VAL['IS_USE_N']}',
          STAFF_ID = '{$S_login['user_id']}',
          CANCAL_DATE = NOW()
        WHERE
          CASH_NO = '{$VAL['no']}'
      ";
      $db->query($query);

      //_______________첫결제
      //취소건중 첫결제건인 경우
      if ($VAL['IS_USE_N'] == "C" && $cash['CASH_CNT'] == 1) {
        //다른 결제건이 있는지 확인하여 다른 결제건이 있을경우 제일 처음 결제건을 첫결제로 변경
        $CASH_NO = $db->get_data_one("SELECT CASH_NO FROM CASH WHERE MEMBER_NO = {$mem[MEMBER_NO]} AND IS_USE='Y' ORDER BY CASH_CNT ASC LIMIT 1");
        if ($CASH_NO) {
          $db->query("UPDATE CASH SET CASH_CNT = 1 WHERE CASH_NO = $CASH_NO");
        }
      }

      #SLK-1440 결제금액이벤트 대상 결제건이면 초기화
      // if ($cash['CASH']  >= 30000) {
      //   $query = "UPDATE `MEMBER_EVENT_250310` SET `CASH_NO` = NULL, `CASH` = 0 WHERE (`USER_ID` = '{$mem['USER_ID']}') AND (`CASH_NO` = '{$VAL['no']}') AND (`EVENT_AT` > NOW())";
      //   $db->query($query);
      // }
      ## 이벤트 내용 초기화
      $query = "UPDATE `MEMBER_EVENT_250513_a` SET `CASH_NO` = NULL, `CASH` = 0 WHERE (`USER_ID` = '{$mem['USER_ID']}') AND (`CASH_NO` = '{$VAL['no']}')";
      $db->query($query);
      if ($cash['CASH'] >= 10000) {
        $query = "UPDATE `MEMBER_EVENT_250513_b1` SET `CASH_NO` = NULL, `CASH` = 0 WHERE (`USER_ID` = '{$mem['USER_ID']}') AND (`CASH_NO` = '{$VAL['no']}')";
        $db->query($query);
      }
      if ($cash['CASH'] >= 30000) {
        $query = "UPDATE `MEMBER_EVENT_250513_b2` SET `CASH_NO` = NULL, `CASH` = 0 WHERE (`USER_ID` = '{$mem['USER_ID']}') AND (`CASH_NO` = '{$VAL['no']}')";
        $db->query($query);
      }
      if ($cash['CASH'] >= 60000) {
        $query = "UPDATE `MEMBER_EVENT_250513_b3` SET `CASH_NO` = NULL, `CASH` = 0 WHERE (`USER_ID` = '{$mem['USER_ID']}') AND (`CASH_NO` = '{$VAL['no']}')";
        $db->query($query);
      }

      $RES['msg'] = "결제건이 캐시 전체 차감 후 ".($VAL['IS_USE_N'] == "C" ? "취소" : "대기")."상태로 처리 되었습니다.";


    // 부분 취소
    } else if ($VAL['IS_USE_N'] == "C2") {
      $CANCEL_PRICE = preg_replace("/[^0-9]/", "", $VAL['cancel_price']); // 부분 취소 금액
      $CANCEL_CASH  = calc_cash_supply_by_price($CANCEL_PRICE); // 부분 취소 캐시

      if ($mem['CASH'] < $CANCEL_CASH) {
        echo json_encode(array("error"=>1, "msg"=>"차감할 캐시가 부족합니다."));
        exit;
      }

      // 캐시사용로그
      $cash_log = array();
      $cash_log['mode']        = "insert";
      $cash_log['CASH_NO']     = $VAL['no'];
      $cash_log['ORDERS_NO']   = 0;
      $cash_log['WINNING_NO']  = 0;
      $cash_log['INVOCE_NO']   = 0;
      $cash_log['CASH_LOG_NO'] = 0;
      $cash_log['PRICE']       = $CANCEL_PRICE;
      $cash_log['CASH']        = $CANCEL_CASH;
      $cash_log['N_CASH']      = $mem['CASH'] - $CANCEL_CASH;
      $cash_log['O_CASH']      = $mem['CASH'];
      $cash_log['USER_ID']     = $mem['USER_ID'];
      $cash_log['MEMO']        = $S_login['user_id']."캐시구매부분취소(무통장)";
      $cash_log['STATUS']      = "M";

      F_T_CASH_LOG($cash_log);

      // MEMBER CASH 차감
      $query = "
        UPDATE
          MEMBER
        SET
          CASH = CASH - '{$CANCEL_CASH}'
        WHERE
          MEMBER_NO='{$mem['MEMBER_NO']}'
      ";
      $db->query($query);

      // CASH Update
      $query = "
        UPDATE
          CASH
        SET
          PRICE = PRICE - '{$CANCEL_PRICE}',
          CASH = CASH - '{$CANCEL_CASH}',
          STAFF_ID = '{$S_login['user_id']}',
          CON_REG_DATE = NOW()
        WHERE
          CASH_NO = '{$VAL['no']}'
      ";
      $db->query($query);

      $RES['msg'] = "결제건이 캐시 부분 차감 처리 되었습니다.";

    } else {
      echo json_encode(array("error"=>1, "msg"=>"비정상적인 접근입니다."));
      exit;
    }

    $RES['error'] = false;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == 'bank_status_n') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $cash = $db->get_data("SELECT * FROM CASH WHERE CASH_NO = '{$VAL['no']}'");

    if (empty($cash['CASH_NO'])) {
      echo json_encode(array("error"=>1, "msg"=>"결제건이 존재하지 않습니다."));
      exit;
    }

    if ($cash['IS_USE'] != "C") {
      echo json_encode(array("error"=>1, "msg"=>"결제건이 취소 상태가 아닙니다."));
      exit;
    }

    $query = "
      UPDATE
        CASH
      SET
        IS_USE = 'N',
        STAFF_ID = '{$S_login['user_id']}',
        CANCAL_DATE=NOW()
      WHERE
        CASH_NO = '{$VAL['no']}'
    ";
    $db->query($query);

    $RES['msg'] = "대기상태로 변경하였습니다.";
    $RES['error'] = false;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == 'search_order_memo_sms') { //SLK-989
    $info = $db->get_data("SELECT idx, SMS_DATE, SMS_NAME, SMS_PRICE, REG_DATE, STATUS FROM CASH_SMS_LOG AS C WHERE idx='".$VAL['no']."'");

    if ($info["idx"]) {
      $info_memo  =  $db->get_data("SELECT SMS_IDX, MEMO, REG_DATE, STATUS, CHG_STAFF_ID FROM CASH_SMS_MEMO WHERE SMS_IDX='".$VAL['no']."'");
      if (!$info_memo) {
        $info_memo['MEMO'] = "";
        $info_memo['REG_DATE'] = "-";
        $info_memo['CASH_NO'] = $VAL['no'];
        $info_memo['STATUS'] = "N";
        $info_memo['SMS_STATUS'] = $info['STATUS'];
      } else {
        $info_memo['MEMO'] = $info_memo['MEMO'];
        $info_memo['REG_DATE'] = $info_memo['REG_DATE'];
        $info_memo['REG_DATE'] .= " / ".$info_memo['CHG_STAFF_ID'];
        $info_memo['CASH_NO'] = $VAL['no'];
        $info_memo['STATUS'] = $info_memo['STATUS'];
        $info_memo['SMS_STATUS'] = $info['STATUS'];
      }
      $info_memo['USER'] = "입금자명 : ".$info["SMS_NAME"]." / 입금액 : ₩".number_format($info['SMS_PRICE']);
      $info_memo['USER'] .= " / 입금일 : ".$info['REG_DATE'];
      echo json_encode(array("error"=>1, "MEMO"=>$info_memo['MEMO'], "REG_DATE"=>$info_memo['REG_DATE'], "SMS_STATUS"=>$info_memo['SMS_STATUS'], "CHG_STAFF_ID"=>$info_memo['CHG_STAFF_ID'], "STATUS"=>$info_memo['STATUS'], "USER"=>$info_memo['USER'], "SMS_idx"=>$info_memo['SMS_idx']));
    } else {
      echo json_encode(array("error"=>0, "msg"=>"무통장 입금건이 없습니다."));
    }
  } else if ($VAL['mode'] == 'insert_order_memo_sms') { // SLK-989
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $info_memo = $db->get_data("SELECT COUNT(*) AS CNT FROM CASH_SMS_MEMO WHERE SMS_IDX='".$VAL['idx']."'");

    if ($info_memo["CNT"]) {
      $fields = " MEMO = '".addslashes($VAL['memo'])."', STATUS = '".$VAL['STATUS']."', REG_DATE = now(), CHG_STAFF_ID = '{$S_login['user_id']}' ";
      $query = "UPDATE CASH_SMS_MEMO SET ".$fields." WHERE SMS_IDX = ".$VAL['idx'];
    } else {
      $query = "INSERT INTO CASH_SMS_MEMO (SMS_IDX, MEMO, STATUS, REG_DATE, CHG_STAFF_ID) VALUES ('".$VAL['idx']."', '".addslashes($VAL['memo'])."', '".$VAL['STATUS']."', now(), '{$S_login['user_id']}') ";
    }
    $db->query($query);

    // SMS STATUS 변경
    $query = "UPDATE CASH_SMS_LOG SET STATUS = '".$VAL['SMS_STATUS']."'  ";
    $query .= " WHERE idx = ".$VAL['idx'];
    $db->query($query);
    echo json_encode(array("error"=>1, "msg"=>"메모가 저장완료 되었습니다."));
  } else if ($VAL['mode'] == 'list_order_memo_admin_sms') { // SLK-989
    $html = '
      <div class="col-md-12" style="text-align: center; padding: 0;">
        <table class="table border table-striped">
          <tr>
            <td>작성일</td>
            <td>내용</td>
            <td>삭제</td>
          </tr>';

    $list = $db->get_list("SELECT * FROM CASH_SMS_MEMO WHERE ORDER BY IDX DESC");
    for ($i = 0; $i < count($list['IDX']); $i++) {
      $html .= '
        <tr>
          <td>'.$list['REG_DATE'][$i].'</td>
          <td tyle="display : inline-block; white-space: nowrap; overflow: hidden;">'.nl2br($list['MEMO'][$i]).'</td>
          <td><a class="btn btn-danger asOkBtn" style="color: #FFFFFF;" data-no="'.$list['IDX'][$i].'">삭제</a></td>
        </tr>';
    }
    if (count($list['MEMO']) == 0) {
      $html .= '<tr><td colspan="3">작성된 내용이 없습니다.</td></tr>';
    }
    $html .= '</table></div>';

    echo json_encode(array("error"=>1, "page_html"=>$html));
  } else if ($VAL['mode'] == 'insert_sms_exception') { // SLK-987
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $query = "INSERT INTO CASH_SMS_EXCEPTION (SMS_FROM_NAME, SMS_TO_NAME, STAFF_ID) VALUES ('".trim($VAL['SMS_FROM_NAME'])."', '".trim($VAL['SMS_TO_NAME'])."', '".$S_login['user_id']."' ) ";
    $db->query($query);
    echo json_encode(array("error"=>1, "msg"=>"저장완료 되었습니다."));
  } else if ($VAL['mode'] == 'list_sms_exception') { // SLK-987
    $html = '
      <div class="col-md-12" style="text-align: center; padding: 0;">
        <table class="table border table-striped">
          <tr>
            <td>실입금자명</td>
            <td>변경 할 회원명</td>
            <td>등록일</td>
            <td>삭제</td>
          </tr>';

      $list = $db->get_list("SELECT * FROM CASH_SMS_EXCEPTION ORDER BY idx DESC");

      for ($i = 0; $i < count($list['idx']); $i++) {
        $html .= '
          <tr>
            <td>'.$list['SMS_FROM_NAME'][$i].'</td>
            <td>'.$list['SMS_TO_NAME'][$i].'</td>
            <td>'.$list['REG_DATE'][$i].' ('.$list['STAFF_ID'][$i].')</td>
            <td><a class="btn btn-danger asOkBtn" style="color: #FFFFFF;" data-no="'.$list['idx'][$i].'">삭제</a></td>
          </tr>';
      }
      if (count($list['SMS_FROM_NAME']) == 0) {
        $html .= '<tr><td colspan="4">작성된 내용이 없습니다.</td></tr>';
      }
      $html .= '</table></div>';

      echo json_encode(array("error"=>1, "page_html"=>$html));
  } else if ($VAL['mode'] == 'delete_sms_exception') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $info_memo = $db->get_data("SELECT COUNT(*) AS CNT FROM CASH_SMS_EXCEPTION WHERE idx='".$VAL['no']."'");
    if ($info_memo["CNT"]) {
      $query = "DELETE FROM CASH_SMS_EXCEPTION WHERE idx = ".$VAL['no'];
      $db->query($query);
    }

    echo json_encode(array("error"=>1, "msg"=>"예외처리가 삭제완료 되었습니다."));
  }
?>
