<?php
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
    if ($VAL["tel"] == "") {
      $RES["msg"] = "연락처를 정확히 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='".$VAL['tel']."' AND USER_ID != '".$M_login['user_id']."'");

    if ($info["cnt"] > 0) {
      $RES["msg"] = "이미 가입한 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    $TEMPLET_NO = 18;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

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
      $time_set_MM["price"] = str_replace(",", "", $time_set_MM["price"]);
      $time_set_MM["price"] = str_replace(".", "", $time_set_MM["price"]);
      $time_set_MM["price2"] = number_format($time_set_MM["price"]);
      $time_set_MM["price"] = substr($time_set_MM["price"], 0, -8);
      $time_set_MM["price"] = getNumberStringKorean($time_set_MM["price"]);
      $time_set_MM["priceDollarMillion"] = number_format(floor($time_set_MM["priceDollar"] / 1000000));
      $time_set_MM["priceDollar"] = number_format($time_set_MM["priceDollar"]);
      $time_set_MM["secondMinPrize"] = getNumberStringKorean(substr(2000000 * $time_set_MM["won_rate"],0,-8));
      $time_set_MM["secondMaxPrize"] = getNumberStringKorean(substr(10000000 * $time_set_MM["won_rate"],0,-8));
      //Case1. 예상당첨금
      $time_set_MM["allprice_est"] = $time_set_MM["price_est"];
      $time_set_MM["price_est"] = str_replace(",", "", $time_set_MM["price_est"]);
      $time_set_MM["price_est"] = str_replace(".", "", $time_set_MM["price_est"]);
      $time_set_MM["price_est"] = substr($time_set_MM["price_est"], 0, -8);
      $time_set_MM["price_est"] = getNumberStringKorean($time_set_MM["price_est"]);
      //Case2. 초기화당첨금
      $time_set_MM["allprice_fst"] = $time_set_MM["price_fst"];
      $time_set_MM["price_fst"] = str_replace(",", "", $time_set_MM["price_fst"]);
      $time_set_MM["price_fst"] = str_replace(".", "", $time_set_MM["price_fst"]);
      $time_set_MM["price_fst"] = substr($time_set_MM["price_fst"], 0, -8);
      $time_set_MM["price_fst"] = getNumberStringKorean($time_set_MM["price_fst"]);
    }

    if ($time_set_PB["price"] != "") {
      $time_set_PB["allprice"] = $time_set_PB["price"];
      $time_set_PB["price"] = str_replace(",", "", $time_set_PB["price"]);
      $time_set_PB["price"] = str_replace(".", "", $time_set_PB["price"]);
      $time_set_PB["price2"] = number_format($time_set_PB["price"]);
      $time_set_PB["price"] = substr($time_set_PB["price"], 0, -8);
      $time_set_PB["price"] = getNumberStringKorean($time_set_PB["price"]);
      $time_set_PB["priceDollarMillion"] = number_format(floor($time_set_PB["priceDollar"] / 1000000));
      $time_set_PB["priceDollar"] = number_format($time_set_PB["priceDollar"]);
      $time_set_PB["secondPrize"] = getNumberStringKorean(substr(1000000 * $time_set_PB["won_rate"],0,-8));
      $time_set_PB["thirdPrize"] = number_format(substr(50000 * $time_set_PB["won_rate"],0,-4));
      //Case1. 예상당첨금
      $time_set_PB["allprice_est"] = $time_set_PB["price_est"];
      $time_set_PB["price_est"] = str_replace(",", "", $time_set_PB["price_est"]);
      $time_set_PB["price_est"] = str_replace(".", "", $time_set_PB["price_est"]);
      $time_set_PB["price_est"] = substr($time_set_PB["price_est"], 0, -8);
      $time_set_PB["price_est"] = getNumberStringKorean($time_set_PB["price_est"]);
      //Case2. 초기화당첨금
      $time_set_PB["allprice_fst"] = $time_set_PB["price_fst"];
      $time_set_PB["price_fst"] = str_replace(",", "", $time_set_PB["price_fst"]);
      $time_set_PB["price_fst"] = str_replace(".", "", $time_set_PB["price_fst"]);
      $time_set_PB["price_fst"] = substr($time_set_PB["price_fst"], 0, -8);
      $time_set_PB["price_fst"] = getNumberStringKorean($time_set_PB["price_fst"]);
    }

    echo json_encode(array("MM"=>$time_set_MM, "PB"=>$time_set_PB));
    exit;

  } else if ($VAL["mode"] == "my_lotto_number_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MY_BALL.php";

    if (!isset($M_login["user_id"])) {
      $RES["html"] = "";
      echo json_encode($RES);
      exit;
    }

    $mb = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");

    $add_query = " AND MEMBER_NO='".$mb['MEMBER_NO']."'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    $condition = array(
      "row" => $limit,
      "page" => $VAL["page"],
      "order" => $VAL["order"],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_MY_BALL_list($condition);

    if ($list["total"] <= 0) {
      $list["BALL_NO"] = array();
    }

    for ($i = 0; $i < count($list["BALL_NO"]); $i++) {
      $cla = "power";

      if ($list["GUBUN"][$i] == "MM") {
        $cla = "mega";
      }

      for ($s = 1; $s < 6; $s++) {
        if ($list["BALL".$s][$i] != "") {
          $tp = "mt20";
          if ($i == 0 && $s == 1) {
            $tp = "";
          }

          $html .= '
            <table class="tb_type2 bt '.$tp.'">
              <caption>
                <div class="hide">
                  복권 등록 내역에 대한 정보를 제공하는 표
                </div>
              </caption>
              <colgroup>
                <col width="">
                <col width="">
              </colgroup>
              <tbody>
                <tr>
                  <th>복권명</th>
                  <td>'.$ball_op[$list['GUBUN'][$i]].'</td>
                </tr>
                <tr>
                  <th>
                    <span class="color_red">나의 복권 번호</span>
                  </th>
                  <td>';
          $ball = explode(",", $list["BALL".$s][$i]);
          $html .= '
            <div class="wrap_ball">
              <ul class="list_ball clear">
                <li><span>'.$ball[0].'</span></li>
                <li><span>'.$ball[1].'</span></li>
                <li><span>'.$ball[2].'</span></li>
                <li><span>'.$ball[3].'</span></li>
                <li><span>'.$ball[4].'</span></li>
                <li class="'.$cla.'"><span>'.$ball[5].'</span></li>
              </ul>
            </div>';
          $html .= '
                  </td>
                </tr>
                <tr>
                  <th>등록일</th>
                  <td>'.date("Y-m-d H:i", strtotime($list['REG_DATE'][$i])).'
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <button type="button"
                            style="border: 2px solid #f00; height: 1rem; padding: 0 0.5rem; font-size: var(--px22); border-radius: 0.3rem; background-color: #FF0000; color: #FFFFFF;"
                            onclick="del_my_number('.$list['BALL_NO'][$i].','.$s.')">번호삭제</button>
                  </td>
                </tr>
              </tbody>
            </table>';
        }
      }
    }

    $RES["html"] = $html;
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

    // $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID=' ".$M_login['user_id']."' ");
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
  } else if ($VAL['mode'] == "my_cash_list") {
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";

    $add_query = " AND c.USER_ID='{$M_login['user_id']}'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    if ($VAL[sdate] != "" && $VAL[edate] != "") {
      $add_query .= " AND c.REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL['page'],
      "order" => $VAL['order'],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_CASH_list($condition);

    if ($list['total'] <= 0) {
      $list['CASH_NO'] = array();
    }

    for ($i = 0; $i < count($list['CASH_NO']); $i++) {
      $no = $list['total'] - (($page - 1) * $list['row']) - $i;
      $receiptSt = "-";

      if ($list['CARDNAME'][$i] == "무통장") {
        $pay_type = "무통장";

        if ($list['IS_USE'][$i] == "Y") {
          $tp = "<strong>충전완료</strong>";

          // 현금영수증 신청
          $reference_date = strtotime("2023-03-22 00:00:00");
          if ($list['RECEIPT_ST'][$i] == 'Y') {
            $receiptSt = "<strong>발급완료</strong>";
          } else if ($list['RECEIPT_ST'][$i] == 'N') {
            $receiptSt = "<strong>발급중</strong>";
          } else {
            $laterthan = strtotime($list['REG_DATE'][$i]) >= $reference_date;
            if ($laterthan) {
              $receiptSt = "<button type='button' class='btn_gray apply_cash_receipt'
                                    style='padding: 0 0.8rem; height: 1.5rem; background-color: gray; font-weight: 600;'
                                    data-no='".$list['CASH_NO'][$i]."'>발급 신청</button>";
            }
          }
        } else if ($list['IS_USE'][$i] == "C") {
          $tp = "<font color='#ff0000'>입금취소</font>";
        } else {
          $PRICE = number_format($list['PRICE'][$i]);
          $tp = "
            <button type='button' class='btn_popup btn_type1 btn_bank_waiting' id='btn_pay_bank' data-price='{$PRICE}'>입금대기</button>
            <button type='button' class='btn_type1 btn_bank_cancel bank_cancel' data-no='{$list['CASH_NO'][$i]}'>신청취소</button>
          ";
        }
      } else {
        $pay_type = "신용카드";
        $receiptSt = "-";

        if ($list['IS_USE'][$i] == "Y") {
          $tp = "<strong>승인</strong>";
        } else if ($list['IS_USE'][$i] == "N") {
          $tp = "<strong>취소</strong>";
        } else if ($list['IS_USE'][$i] == "C") {
          $tp = "<font color='#ff0000'>신청취소</font>";
        } else {
          $tp = "
            <strong>대기</strong><br>
            <button type='button' class='btn_type1 btn_bank_cancel card_cancel' data-no='{$list['CASH_NO'][$i]}' style='margin-top:0.3rem;margin-left:0;'>결제충전 취소</button>
          ";
        }
      }

      // 관리자 수동 입력
      $is_admin = "";
      if ($list['IN_IP'][$i] == "0.0.0.0") {
        $is_admin = "<a href='#' id='tooltip' data-tooltip='관리자가 등록한 내역입니다.'><svg width=\"13\" height=\"13\" fill=\"currentColor\" class=\"bi bi-exclamation-circle\" viewBox=\"0 0 16 16\"> <path d=\"M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z\"/> <path d=\"M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z\"/> </svg></a>";
      }

      // 상태가 취소인 경우 CONTENT 표시
      $cancel_content = "";
      if ($list['IS_USE'][$i] == "N" && $list['CONTENT'][$i] != "") {
        $cancel_content = "<span style=\"font-size: 12px;\">({$list['CONTENT'][$i]})</span>";
      }

      $html .= '
        <tbody>
          <tr>
            <th>결제일시</th>
            <td>'.$is_admin.' '.$list['REG_DATE'][$i].'</td>
          </tr>
          <tr>
            <th>결제수단</th>
            <td>'.$pay_type.'</td>
          </tr>
          <tr class="color_red">
            <th>충전캐시</th>
            <td>'.number_format($list['CASH'][$i]).'캐시</td>
          </tr>
          <tr class="color_red">
            <th>결제금액</th>
            <td>'.number_format($list['PRICE'][$i]).'원</td>
          </tr>
          <tr>
            <th>상태</th>
            <td>'.$tp.$cancel_content.'</td>
          </tr>
          <tr>
            <th>현금영수증 발급</th>
            <td>'.$receiptSt.'</td>
          </tr>
        </tbody>';
    }

    $RES[html] = $html;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_invoce_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_INVOCE.php";

    $add_query = " AND USER_ID='{$M_login['user_id']}'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    if ($VAL["sdate"] != "" && $VAL["edate"] != "") {
      $add_query .= " AND REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL["page"],
      "order" => $VAL["order"],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_INVOCE_list($condition);

    if ($list["total"] <= 0) {
      $list["INVOCE_NO"] = array();
    }

    $mb = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}' LIMIT 1");

    for ($i = 0; $i < count($list["INVOCE_NO"]); $i++) {
      $no = $list["total"] - (($page-1)*$list["row"]) - $i;

      if ($list["MONEY"][$i] == "") {
        $list["MONEY"][$i] = 0;
      }

      if ($list["MONEY2"][$i] == "") {
        $list["MONEY2"][$i] = 0;
      }

      $invoice = $list["MONEY"][$i] - $list["MONEY2"][$i];

      $tp = "대기";

      if ($list["STATUS"][$i] == "Y") {
        $tp = "승인";
      } else if ($list["STATUS"][$i] == "D") {
        $tp = "취소 <span class='color_red3'>(계좌정보 오류)</span>";
      }

      $html .= '
        <tbody>
          <tr>
            <th>번호</th>
            <td>'.$no.'</td>
          </tr>
          <tr>
            <th>출금신청일시</th>
            <td>'.date("Y년 m월 d일 H:i", strtotime($list['REG_DATE'][$i])).'</td>
          </tr>
          <tr class="color_red">
            <th>출금요청 캐시</th>
            <td>'.number_format($list['MONEY2'][$i]).'캐시</td>
          </tr>
          <tr>
            <th>환급수수료</th>
            <td>'.number_format($invoice).'캐시</td>
          </tr>
          <tr>
            <th>입금은행</th>
            <td>'.$list['BANK1'][$i].'</td>
          </tr>
          <tr>
            <th>계좌번호</th>
            <td>'.$list['BANK2'][$i].'</td>
          </tr>
          <tr>
            <th>예금주</th>
            <td>'.$list['BANK3'][$i].'</td>
          </tr>
          <tr>
            <th>상태</th>
            <td>'.$tp.'</td>
          </tr>
        </tbody>';
    }

    $RES["html"] = $html;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_wcash_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_T_WCASH_LOG.php";

    $add_query = " AND USER_ID='{$M_login['user_id']}' AND MEMO IN ('당첨금사용', '당첨금적립' ,'쇼핑상품구매')";
    $html      = "";
    $limit     = 20;

    if ($VAL["sdate"] != "" && $VAL["edate"] != "") {
      $add_query .= " AND REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row"         => $limit,
      "page"        => $VAL["page"],
      "order"       => "",
      "find_text"   => "",
      "find_object" => "",
      "add_query"   => $add_query,
    );
    $list = F_T_WCASH_LOG_list($condition);

    if ($list["total"] <= 0) {
      $list["LOG_NO"] = array();
    }

    $mb = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$M_login['user_id']}' LIMIT 1");

    for ($i = 0; $i < count($list["LOG_NO"]); $i++) {
      $no = $list["total"] - (($page-1)*$list["row"]) - $i;
      $WCASH_M = 0;
      $WCASH_P = 0;

      if ($list["WCASH"][$i] == "") {
        $list["WCASH"][$i] = 0;
      }

      if ($list["STATUS"][$i] == "M") {
        $WCASH_M = $list["WCASH"][$i];
      } else if ($list["STATUS"][$i] == "P") {
        $WCASH_P = $list["WCASH"][$i];
      }

      $html .= '
        <tbody>
          <tr>
            <th>번호</th>
            <td>'.$no.'</td>
          </tr>
          <tr>
            <th>등록일시</th>
            <td>'.date("Y년 m월 d일 H:i", strtotime($list['REG_DATE'][$i])).'</td>
          </tr>
          <tr class="color_red">
            <th>적립 당첨금</th>
            <td>'.(($WCASH_P > 0) ? number_format($WCASH_P)."원" : "-").'</td>
          </tr>
          <tr>
            <th>사용 당첨금</th>
            <td>'.(($WCASH_M > 0) ? number_format($WCASH_M)."원" : "-").'</td>
          </tr>
        </tbody>
      ';
    }

    $RES["html"] = $html;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_bbs_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_BBS.php";

    $add_query = " AND GUBUN='QNA' AND USER_ID='".$M_login['user_id']."'";
    $html = "";
    $limit = 10;
    $find_text = "";
    $find_object = "";

    $condition = array(
      "row" => $limit,
      "page" => $VAL["page"],
      "order" => $VAL["order"],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );
    $list = F_BBS_list($condition);

    if ($list["total"] <= 0) {
      $list["BBS_NO"] = array();
    }

    for ($i = 0; $i < count($list["BBS_NO"]); $i++) {
      $no = $list["total"] - (($page-1)*$list["row"]) - $i;

      $tp = "<p class='fw600'>답변대기 [보기]</p>";

      if ($list["REPLY_YN"][$i] == "Y") {
        $tp = "<p class='color_red fw600'>답변완료 [보기]</p>";
        // 답변완료건에 대해 읽은날짜(USER_READ_DATE) 업데이트
        // $query2 = "UPDATE BBS SET USER_READ_DATE = NOW() WHERE GUBUN = 'QNA' AND BBS_NO = '{$list["BBS_NO"][$i]}'";
        // $db->query($query2);
      }

      $html .= '
        <div class="wrap_tb bt ls50 lh10">
          <table class="tb_type2">
            <caption>
              <div class="hide">
                1:1 문의 내역에 대한 정보를 제공하는 표
              </div>
            </caption>
            <colgroup>
              <col width="">
              <col width="">
            </colgroup>
            <tbody>
              <tr>
                <th>번호</th>
                <td>'.$no.'</td>
              </tr>
              <tr>
                <th>분류</th>
                <td>'.$list['QNA_TYPE'][$i].'</td>
              </tr>
              <tr>
                <th>제목</th>
                <td>'.$list['SUBJECT'][$i].'</td>
              </tr>
              <tr>
                <th>등록일</th>
                <td>'.$list['REG_DATE'][$i].'</td>
              </tr>
              <tr>
                <th>상태</th>
                <td class="reply_on" data-no="'.$list['BBS_NO'][$i].'">'.$tp.'</td>
              </tr>
            </tbody>
          </table>
          <div class="bx_answer bx_gray" style="display: none;">
            <div class="wrap_q">
              <p class="title"><strong>문의내용 [원문]</strong></p>
              <p>'.nl2br($list['CONTENT'][$i]).'</p>
            </div>
      ';

      if ($list["REPLY_YN"][$i] == "Y") {
        $html .= '
            <div class="wrap_a">
              <p class="title color_red pb5"><strong>질문에 대한 답변입니다.</strong></p>
              '.nl2br($list['REPLY'][$i]).'
            </div>
            <!--<div class="clear">
              <button class="btn_navy right">닫기</button>
            </div>-->
        ';
      }
      $html .= '</div></div>';
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
      $RES["msg"] = "존재하지 않는 회원입니다.";
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

    $info = $db->get_data("SELECT COUNT(*) AS cnt FROM MEMBER WHERE HP='".$VAL['tel']."' AND NAME='".$VAL['name']."'");

    if ($info["cnt"] <= 0) {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    $TEMPLET_NO = 18;
    $SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

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

    $RES["msg"] = "비밀번호 변경하였습니다.";
    $RES["error"] = true;
    echo json_encode($RES);
    exit;

  } else if ($VAL["mode"] == "my_point_chk") {
    $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");
    $price_point = $price_point1;

    if ($VAL["gamecnt"] > 1) {
      $price_point = $price_point2;
    }

    if ($info["USER_ID"] == "") {
      $RES["msg"] = "존재하지 않은 회원입니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["POINT"] <= 0) {
      $RES["msg"] = "포인트가 부족합니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["POINT"] < $price_point) {
      $price_point = $info["POINT"];
    }

    $RES["msg"]  = $price_point;
    $RES["error"] = true;
    echo json_encode($RES);
  } else if ($VAL["mode"] == "my_price_chk") {
    $info  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$M_login['user_id']}' LIMIT 1");
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
      $RES["msg"] = "입력한 당첨금, 마일리지, 보너스포인트가</br>결제금액을 초과하였습니다.";
      echo json_encode($RES);
      exit;
    }

    if ($info["CASH"] < $payment) {
      // $RES["msg"] = "캐시가 부족합니다.</br>캐시충전버튼을 통해 충전을 진행해주세요.";
      $RES["msg"] = "캐시가 부족합니다.</br>캐시 충전페이지로 이동합니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["msg"] = "";
    $RES["error"] = true;
    echo json_encode($RES);
  } else if ($VAL["mode"] == "search_date") {
    $schdate = $VAL["schdate"];

    if ($schdate == "1D") {
      $toTime  = time();
    } else {
      $toTime  = time();
    }
    switch($schdate) {
      case "1D": $timeS = $toTime - (0); break;
      case "2D": $timeS = $toTime - (86400); break;
      case "YD":
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
      $RES["msg"] = "정확한 쿠폰번호를 입력해 주세요.";
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

    $count = $db->get_data("SELECT COUNT(*) AS cnt FROM `COUPON_LOG` WHERE `MEMBER_NO`='".$member['MEMBER_NO']."'");

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

    $patner = $db->get_data("SELECT * FROM PATNER WHERE USER_ID='".$info['PATNER_ID']."'");
    $partnerNo = (isset($patner['PATNER_NO'])) ? $patner['PATNER_NO'] : 0;
    $add_query = " PATNER_NO='".$partnerNo."', ";

    //보유포인트사용로그
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
        USER_ID = '".$M_login['user_id']."'";

    $db->query($sql);

    $RES["error"] = true;
    $RES["msg"]  = "VIP쿠폰이 정상 등록 되었습니다. Good Luck!!!";
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
    $RES["msg"] = ".";
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "my_wininfo_list") {
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_WININFO.php";

    $html = "";

    //환율
    $won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."' LIMIT 1");

    if (!isset($won["WON"])) {
      $won = $db->get_data("SELECT * FROM EXCHANGE WHERE 1 ORDER BY DATE DESC LIMIT 1");
    }

    $date = date("Y-m-d");
    $end_date = "2022-04-10";

    if ($VAL["gubun"] == "MM") {
      $end_date = "2022-03-15";
    }

    if (!$limit) $limit = 20;
    $add_query = " AND GUBUN='".$VAL['gubun']."' AND PLAYDATE < '".$date."'  AND PLAYDATE > '".$end_date."'";
    $PARAM = "";

    $condition = array(
      "row" => $limit,
      "page" => $VAL["page"],
      "order" => " PLAYDATE DESC",
      "find_text" => "",
      "find_object" => "",
      "add_query" => $add_query,
    );

    $list = F_WININFO_list($condition);

    $cla = "power";

    if ($VAL["gubun"] == "MM") {
      $cla = "mega";
    }

    $now = date("Y-m-d",strtotime("-1 day"));
    $n_time = date("H");

    for ($i = 0; $i < count($list["WININFO_NO"]); $i++) {
      $playDate = date("Y-m-d", strtotime($list["PLAYDATE"][$i]."+1 day"));
      if ($list["PRIZ1"][$i] < 19000000 && $list["PRIZ1"][$i] > 0) {
        $k = $i - 1;
        $list["PRIZ1"][$i] = $list["price"][$k];
      }
      $list["price"][$i] = $list["PRIZ1"][$i];
      $list["PRIZ1"][$i] = $list["PRIZ1"][$i] * $won["WON"];
      $list["allprice"][$i] = $list["PRIZ1"][$i];

      if ($list["PRIZ1"][$i] > 0) {
        $list["PRIZ1"][$i] = number_format($list["PRIZ1"][$i]);
        $list["PRIZ1"][$i] = str_replace(",", "", $list["PRIZ1"][$i]);
        $list["PRIZ1"][$i] = str_replace(".", "", $list["PRIZ1"][$i]);
        $list["PRIZ1"][$i] = substr($list["PRIZ1"][$i], 0, -8);
      }

      $weekee = date("w", strtotime($list["TIME_E"][$i]));
      $weeks = $week_op[$weekee];

      $list["TIME_E"][$i] = date("Y년 m월 d일 ",strtotime($list["TIME_E"][$i])).$weeks." ".get_dst_drawtime(date("Y-m-d",strtotime($list['TIME_E'][$i])));
      $win_tp = win_tp($list["IS_TYPE"][$i], $list["PLAYDATE"][$i], $list["BALLP"][$i], $list["PRIZCNT9"][$i],$draw_time_num);

      $win_end = "1등 당첨 이월";
      $win_end_price = '
        <p>$ <span>'.number_format($list['price'][$i]).'</span>  /
          <span class="color_red">'.getNumberStringKorean($list['PRIZ1'][$i]).'원</span>
        </p>';
      $win_p = "1등 당첨 이월";
      $html .= '
        <tbody>
          <tr>
            <th>회차</th>
            <td><strong>'.$list["DRAWNUM"][$i].'</strong></td>
          </tr>
          <tr>
            <th>추첨일<br />(한국시간)</th>
            <td>'.$list['TIME_E'][$i].'</td>
          </tr>
          <tr>
            <th><span class="color_red">추첨번호</span></th>
            <td style="padding-right: 0px;">
              <div class="wrap_ball" style="margin-bottom: 2px;">
                <ul class="list_ball clear">
                  <li><span>'.$list['BALL1'][$i].'</span></li>
                  <li><span>'.$list['BALL2'][$i].'</span></li>
                  <li><span>'.$list['BALL3'][$i].'</span></li>
                  <li><span>'.$list['BALL4'][$i].'</span></li>
                  <li><span>'.$list['BALL5'][$i].'</span></li>
                  <li class="'.$cla.'"><span>'.$list['BALLP'][$i].'</span></li>
                </ul>
              </div>
            </td>
          </tr>
          <tr>
            <th>1등 당첨금</th>
            <td>'.$win_end_price.'</td>
          </tr>
          <tr>
            <th>추첨결과</th>
            <td>'.$win_tp[0].'</td>
          </tr>';

      if ($list["YUTUBE"][$i] != "") {
        $html .= '
          <tr>
            <th>추첨방송</th>
            <td>
              <a class="btn_popup show_draw_youtube" data-youtube="'.$list['YUTUBE'][$i].'" data-draw="'.$list["DRAWNUM"][$i].'" id="btn_video">
                <div class="bx_link ico_video"></div>방송보기
              </a>
            </td>
          </tr>';
      } else {
        $html .= '
          <tr>
            <th>추첨방송</th>
            <td><a>영상준비중입니다.</a></td>
          </tr>';
      }

      if ($list["PRIZ2"][$i] > 0) {
        $html .= '
          <tr>
            <th>상세보기</th>
            <td>
              <a class="btn_popup draw_detail_btn" data-no="'.$list["WININFO_NO"][$i].'" id="btn_draw">
                <div class="bx_link ico_detail"></div>상세보기
              </a>
            </td>
          </tr>';
        } else {
          $html .= '<tr><th>상세보기</th><td>'.$win_tp[0].'</td></tr>';
        }
        $html .= '</tbody>';
    }

    $RES["html"] = $html;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "winner_detail") {
    $RES["html"] = "";

    $info = $db->get_data("SELECT * FROM WININFO WHERE WININFO_NO='".$VAL['no']."' LIMIT 1");

    $info["cla"] = "power";

    if ($info["GUBUN"] == 'MM') {
      $info["cla"] = "mega";
    }

    $weekee = date("w", strtotime($info["TIME_E"]));
    $weeks = $week_op[$weekee];
    $info["TIME_E"] = date("Y년 m월 d일 ", strtotime($info["TIME_E"])).$weeks." ".get_dst_drawtime(date("Y-m-d",strtotime($info["TIME_E"])));

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
  } else if ($VAL["mode"] == "bbs_list") {
    include $_SERVER["DOCUMENT_ROOT"]."/_library/function_BBS.php";

    if (!$page) $page = 1;
    if (!$limit) $limit = 10;
    if (!$VAL["order"]) $VAL["order"] = " REG_DATE DESC";

    $add_query = " AND GUBUN='".$VAL["gubun"]."'";
    $find_object = $VAL["find_object"];
    $find_text = $VAL["find_text"];
    $order = $VAL["order"];

    if ($find_object == "all" && $find_text != "") {
      $add_query .= " AND SUBJECT like '%".$find_text."%' OR CONTENT like '%".$find_text."%'";
      $find_object = "";
    }

    if (isset($VAL["qtype"])) {
      if ($VAL["qtype"] != "") {
        $add_query .= " AND QNA_TYPE='".$VAL['qtype']."'";
      }
    }

    $list = F_BBS_list(array(
      "row" => $limit,
      "page" => $page,
      "order" => $order,
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    ));

    if ($list["total"] == 0) {
      $list["BBS_NO"] = array();
    }

    $html = "";

    if ($VAL["gubun"] == "NEWS") {
      for ($i = 0; $i < count($list["BBS_NO"]); $i++) {
        $no = $list["total"] - (($page-1) * $list["row"]) - $i;

        $html .= '
          <li>
            <a href="./news_detail.php?no='.$list['BBS_NO'][$i].'">
              <div>'.$list['SUBJECT'][$i].'</div>
              <ul>
                <li>
                  <span>날짜</span>
                  <p>'.date("Y-m-d",strtotime($list['REG_DATE'][$i])).'</p>
                </li>
                <li>
                  <span>조회</span>
                  <p>'.number_format($list['HIT'][$i]).'</p>
                </li>
              </ul>
            </a>
          </li>';
      }
    } else if ($VAL["gubun"] == "FAQ") {
      for ($i = 0; $i < count($list["BBS_NO"]); $i++) {
        $no = $list["total"] - (($page-1) * $list["row"]) - $i;

        $html .= '
          <li>
            <a>
              <div>
                <p>['.$faq_op[$list['QNA_TYPE'][$i]].']'.$list['SUBJECT'][$i].'</p>
              </div>
            </a>
            <div>
              <div>
                <p>'.nl2br($list['CONTENT'][$i]).'</p>
              </div>
            </div>
          </li>';
      }
    }

    $RES["html"] = $html;
    echo json_encode($RES);
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
  } else if ($VAL["mode"] == "card_cancel") {
    /*
    신용카드 결제충전 신청 취소 (결제 승인 전 대기건)
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

    $cash = $db->get_data("SELECT IS_USE, CARDNAME, USER_ID FROM CASH WHERE CASH_NO = '{$CASH_NO}'");

    if (!isset($cash["USER_ID"]) || $cash["USER_ID"] != $M_login["user_id"]) {
      $RES["msg"] = "취소할 수 없는 요청건입니다.";
      echo json_encode($RES);
      exit;
    }

    if ($cash["CARDNAME"] != "카드") {
      $RES["msg"] = "신용카드 결제충전 건만 취소할 수 있습니다.";
      echo json_encode($RES);
      exit;
    }

    if ($cash["IS_USE"] == "Y") {
      $RES["msg"] = "승인된 건은 취소할 수 없습니다.";
      echo json_encode($RES);
      exit;
    } else if ($cash["IS_USE"] == "C") {
      $RES["msg"] = "이미 취소 처리되었습니다.";
      echo json_encode($RES);
      exit;
    } else if ($cash["IS_USE"] != "") {
      $RES["msg"] = "취소할 수 없는 상태입니다.";
      echo json_encode($RES);
      exit;
    }

    $cash_info = $db->get_data("SELECT PRICE FROM CASH WHERE CASH_NO = '{$CASH_NO}'");
    $cash_log = $db->get_data("SELECT CARD_JUNAME, CARD_NUMBER FROM T_CASH_LOG WHERE CASH_NO = '{$CASH_NO}' ORDER BY LOG_NO DESC LIMIT 1");

    F_CASH(array(
      "mode" => "card_cancel",
      "CASH_NO" => $CASH_NO,
      "USER_ID" => $M_login["user_id"]
    ));

    $card_digits = preg_replace('/\D/', '', $cash_log['CARD_NUMBER']);
    $card_len = strlen($card_digits);
    if ($card_len == 16) {
      $card_formatted = substr($card_digits, 0, 4) . '-' . substr($card_digits, 4, 4) . '-' . substr($card_digits, 8, 4) . '-' . substr($card_digits, 12, 4);
    } elseif ($card_len == 15) {
      $card_formatted = substr($card_digits, 0, 4) . '-' . substr($card_digits, 4, 4) . '-' . substr($card_digits, 8, 4) . '-' . substr($card_digits, 12, 3);
    } else {
      $card_formatted = $cash_log['CARD_NUMBER'];
    }

    $telegram_message = "[카드 결제충전 취소 mo]\n";
    $telegram_message .= "카드주명 : {$cash_log['CARD_JUNAME']}\n";
    $telegram_message .= "금액: {$cash_info['PRICE']}\n";
    $telegram_message .= "카드번호: {$card_formatted}\n";
    $telegram_message .= "취소일 : ".date("Y-m-d H:i:s")."\n";
    $token = "8574625673:AAFbaSw7BBFpqiUB10IGOLznrULl-_EyJSg";
    sendTelegramGroup("-1003883329404", $telegram_message, $token);

    $RES["html"] = "";
    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == "apply_cash_receipt") {
    /**
     * 현금영수증 신청
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    require_once $_SERVER['DOCUMENT_ROOT'].'/_library/function_CASH_RECEIPT.php';
    $CASH_NO = $VAL[no];

    $CASH = $db->get_data("SELECT IS_USE AS ST FROM super.CASH WHERE CASH_NO='{$CASH_NO}'");

    if (!isset($CASH_NO) || strlen($CASH_NO) < 1 || $CASH['ST'] != 'Y') {
      $RES['msg'] = '현금 영수증 신청에 실패했습니다.';
      echo json_encode($RES);
      exit;
    }

    F_CASH_RECEIPT(array(
      'mode' => 'insert',
      'CASH_NO' => $CASH_NO,
      'USER_ID' => $M_login['user_id']
    ));

    $RES['html'] = "";
    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == "my_cash_use_list") {
    /**
     * 캐시 사용내역
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";

    $add_query = " AND C.USER_ID='{$M_login[user_id]}'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    // 티켓구매사용, 낙첨복권배송비
    $add_query .= " AND C.MEMO in ('티켓구매사용', '낙첨복권배송비', '쇼핑상품구매')";

    // 사용 캐시가 0보다 큰 경우
    $add_query .= " AND C.CASH > 0";

    // 취소 티켓 제외
    $add_query .= " AND O.SIGN_YN != 'C'";

    if ($VAL['sdate'] != "" && $VAL['edate'] != "") {
      $add_query .= " AND C.REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL['page'],
      "order" => $VAL['order'],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_T_CASH_LOG_LIST($condition);

    if ($list['total'] <= 0) {
      $list['CASH_NO'] = array();
    }

    for ($i = 0; $i < count($list['LOG_NO']); $i++) {
      $no = $list['total'] - (($page - 1) * $list[row]) - $i;

      $MEMO = $list['MEMO'][$i];
      $IMG = "-";

      if ($MEMO == "티켓구매사용") {
        $TYPE = "파워볼";
        if ($list['GUBUN'][$i] == "MM") {
          $TYPE = "메가밀리언";
        }
        $MEMO = $TYPE." 구매";

        $IMG = "<button class='btn_popup color_red scanImgSwiper' id='btn_scanImgSwiper' data-no='{$list['ORDERS_NO'][$i]}'>스캔본 확인</button>";
        if ($list['PRINT'][$i] != 1) {
          $IMG = '구매 진행 중';
        }
      }

      $html .= '
        <tbody>
          <tr>
            <th>사용일시</th>
            <td>'.$list['REG_DATE'][$i].'</td>
          </tr>
          <tr>
            <th>사용캐시</th>
            <td>'.number_format($list['CASH'][$i]).' 캐시</td>
          </tr>
          <tr>
            <th>사용내역</th>
            <td>'.$MEMO.'</td>
          </tr>
      ';

      if (strpos($IMG, '-') == 0) {
        $html .= '</tbody>';
      } else {
        $html .= '
            <tr>
              <th>확인</th>
              <td>'.$IMG.'</td>
            </tr>
          </tbody>
        ';
      }
    }
    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
    exit;

  } else if ($VAL['mode'] == "my_shop_order_list") {
    /**
     * 슈로코몰 주문내역
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_SHOP_ORDERS.php";

    $add_query = " AND midx ='{$M_login[midx]}' and view_status = 0  ";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    if ($VAL['sdate'] != "" && $VAL['edate'] != "") {
      $add_query .= " AND regdate BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
        "row" => $limit,
        "page" => $VAL['page'],
        "order" => $VAL['order'],
        "find_text" => $find_text,
        "find_object" => $find_object,
        "add_query" => $add_query,
    );

    $list = F_SHOP_ORDER_list($condition);

    if ($list['total'] <= 0) {
      $list['CASH_NO'] = array();
    }

    for ($i = 0; $i < count($list['idx']); $i++) {
      $no = $list['total'] - (($page - 1) * $list[row]) - $i;

      $html .= '
        <tbody>
          <tr>
            <th>번호</th>
            <td>'.$no.'</td>
          </tr>
          <tr>
            <th>상품명</th>
            <td>'.$list['product_name'][$i].'</td>
          </tr>
          <tr>
            <th>구매금액</th>
            <td>'.number_format($list['order_price'][$i]).'</td>
          </tr>
          <tr>
            <th>배송비</th>
            <td>'.number_format($list['order_delivery'][$i]).'</td>
          </tr>
          <tr>
            <th>구매자명</th>
            <td>'.$list['order_name'][$i].' / '.$list['order_phone'][$i].'</td>
          </tr>
          <tr>
            <th>받는곳주소</th>
            <td>'.$list['order_zipcode'][$i].' '.$list['order_address1'][$i].' '.$list['order_address2'][$i].'</td>
          </tr>          
          
          
          
          <tr>
            <th>상태</th>
            <td>'.주문상태($list['status'][$i]).'</td>
          </tr>
          <tr>
            <th>주문일자</th>
            <td>'.$list['regdate'][$i].'</td>
          </tr>                              
      ';

      if (strpos($IMG, '-') == 0) {
        $html .= '</tbody>';
      } else {
        $html .= '
            <tr>
              <th>확인</th>
              <td>'.$IMG.'</td>
            </tr>
          </tbody>
        ';
      }
    }
    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
    exit;

  } else if ($VAL["mode"] == "mileage_history") {
    /**
     * 마일리지 사용 내역
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_T_IPOINT_LOG.php";

    $add_query = " AND P.USER_ID='{$M_login[user_id]}'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    // 취소 티켓 제외
    $add_query .= " AND O.SIGN_YN != 'C'";

    if ($VAL['sdate'] != "" && $VAL['edate'] != "") {
      $add_query .= " AND P.REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL['page'],
      "order" => $VAL['order'],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_T_IPOINT_LOG_LIST($condition);

    if ($list['total'] <= 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0; $i < count($list['LOG_NO']); $i++) {
      $no = $list[total] - (($page - 1) * $list[row]) - $i;

      $MEMO = $list['MEMO'][$i];
      $IMG = "-";

      if ($MEMO == "티켓구매사용") {
        $TYPE = "파워볼";
        if ($list['GUBUN'][$i] == "MM") {
          $TYPE = "메가밀리언";
        }
        $MEMO = $TYPE." 구매";

        $IMG = "<button class='btn_popup color_red scanImgSwiper' id='btn_scanImgSwiper' data-no='{$list['ORDERS_NO'][$i]}'>스캔본 확인</button>";
        if (empty($list['IMG_PATH'][$i])) {
          $IMG = '구매 진행 중';
        }
      }

      if (strpos($MEMO, '회원레벨') > 0) {
        $percent = substr($MEMO, 33);
        switch($percent) {
          case "0.5%":
            $MEMO = "브라운 ".$percent." 적립";
            break;
          case "2.5%":
            $MEMO = "실버 ".$percent." 적립";
            break;
          case "5%":
            $MEMO = "골드 ".$percent." 적립";
            break;
          case "8%":
            $MEMO = "플래티넘 ".$percent." 적립";
            break;
        }
      }

      if (strpos($MEMO, '관리자 마일리지 추가') > 0) {
        $MEMO = '관리자 마일리지 지급';
      } else if (strpos($MEMO, '관리자 마일리지 감소') > 0) {
        $MEMO = '관리자 마일리지 차감';
      }

      $IPOINT_P = 0;
      $IPOINT_M = 0;

      if ($list['STATUS'][$i] == "P") {
        $IPOINT_P = $list['IPOINT'][$i];
      } else if ($list['STATUS'][$i] == "M") {
        $IPOINT_M = $list['IPOINT'][$i];
      }

      $html .= '
        <tbody>
          <tr>
            <th>등록일시</th>
            <td>'.$list['REG_DATE'][$i].'</td>
          </tr>
          <tr class="color_red">
            <th>적립 마일리지</th>
            <td>'.(($IPOINT_P > 0) ? number_format($IPOINT_P)."M" : "-").'</td>
          </tr>
          <tr>
            <th>사용 마일리지</th>
            <td>'.(($IPOINT_M > 0) ? number_format($IPOINT_M)."M" : "-").'</td>
          </tr>
          <tr>
            <th>내용</th>
            <td>'.$MEMO.'</td>
          </tr>
        ';

      if (strpos($IMG, '-') == 0) {
        $html .= '</tbody>';
      } else {
        $html .= '
            <tr>
              <th>확인</th>
              <td>'.$IMG.'</td>
            </tr>
          </tbody>
        ';
      }
    }
    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "point_history") {
    /**
     * 포인트 사용 내역
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }
    require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_T_POINT_LOG.php";

    $add_query = " AND P.USER_ID='{$M_login[user_id]}'";
    $html = "";
    $limit = 20;
    $find_text = "";
    $find_object = "";

    // 취소 티켓 제외
    $add_query .= "AND (P.ORDERS_NO = 0 OR O.SIGN_YN != 'C')";

    if ($VAL['sdate'] != "" && $VAL['edate'] != "") {
      $add_query .= " AND P.REG_DATE BETWEEN '{$VAL['sdate']} 00:00:00' AND '{$VAL['edate']} 23:59:59'";
    }

    $condition = array(
      "row" => $limit,
      "page" => $VAL['page'],
      "order" => $VAL['order'],
      "find_text" => $find_text,
      "find_object" => $find_object,
      "add_query" => $add_query,
    );

    $list = F_T_POINT_LOG_LIST($condition);

    if ($list['total'] <= 0) {
      $list['LOG_NO'] = array();
    }

    for ($i = 0; $i < count($list['LOG_NO']); $i++) {
      $no = $list[total] - (($page - 1) * $list[row]) - $i;

      $MEMO = $list['MEMO'][$i];

      if (strpos($MEMO, '회원레벨') > 0) {
        $percent = substr($MEMO, 33);
        switch($percent) {
          case "0.5%":
            $MEMO = "브라운 ".$percent." 적립";
            break;
          case "2.5%":
            $MEMO = "실버 ".$percent." 적립";
            break;
          case "5%":
            $MEMO = "골드 ".$percent." 적립";
            break;
          case "8%":
            $MEMO = "플래티넘 ".$percent." 적립";
            break;
        }
      }

      if (strpos($MEMO, '관리자 보유포인트 추가') > 0) {
        $MEMO = '관리자 보너스포인트 지급';
      } else if (strpos($MEMO, '관리자 보유포인트 감소') > 0) {
        $MEMO = '관리자 보너스포인트 차감';
      }

      $POINT_P = 0;
      $POINT_M = 0;

      if ($list['STATUS'][$i] == "P") {
        $POINT_P = $list['POINT'][$i];
      } else if ($list['STATUS'][$i] == "M") {
        $POINT_M = $list['POINT'][$i];
      }

      $html .= '
        <tbody>
          <tr>
            <th>등록일시</th>
            <td>'.$list['REG_DATE'][$i].'</td>
          </tr>
          <tr class="color_red">
            <th>적립 보너스포인트</th>
            <td>'.(($POINT_P > 0) ? number_format($POINT_P)."P" : "-").'</td>
          </tr>
          <tr>
            <th>사용 보너스포인트</th>
            <td>'.(($POINT_M > 0) ? number_format($POINT_M)."P" : "-").'</td>
          </tr>
          <tr>
            <th>내용</th>
            <td>'.$MEMO.'</td>
          </tr>
        </tbody>
      ';
    }
    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "app_sms") {
    $from = $fromHP;
    $to = $VAL["hp"];
    $CODESK = "S";
    $TEMPLET_NO = 0;
    $MEMBER_NO = 0;
    $MEMBER_NAME = "슈로코3.0 설치 URL";

    if ($to == "") {
      $RES["error"] = true;
      $RES["msg"] = "정확한 연락처를 입력해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $SUBJECT = "슈로코3.0 설치 URL";
    $SENDMSG = "슈로코3.0 앱은 아래의 링크를 클릭하여 크롬 브라우져를 통해 슈로코사이트 접속해서 설치할 수 있습니다.\r\nwww.suroko.co.kr";

    $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"");

    if ($sms->result_code != 1) {
      $RES["error"] = true;
      $RES["msg"] = "문자발송 실패하였습니다.";
      echo json_encode($RES);
      exit;
    }

    $RES["msg"]  = "전송되었습니다.";
    echo json_encode($RES);
    exit;
  } else if ($VAL["mode"] == "get_ticket_images") {
    /**
     * Get Ticket images
     */
    if (!isset($M_login['user_id'])) {
      $RES['msg'] = "로그인해 주세요.";
      echo json_encode($RES);
      exit;
    }

    $query = "SELECT IMG_PATH, IMG_DATE FROM ORDERS WHERE UNIQNUM='{$no}'";
    $info = $db->get_list($query);

    if ($info['PRINT'] == 1 ) {
      $query = "SELECT IMG_PATH, IMG_DATE FROM ORDERS WHERE ORDERS_NO='{$no}'";
      $info = $db->get_list($query);
    }

    $html = "";

    for ($i = 0; $i < count($info['IMG_PATH']); $i++) {
      $ticketImg = $info['IMG_PATH'][$i];
      $ticketImgDate = $info['IMG_DATE'][$i];

      // 앞뒷면 구분 로직 추가 (3Dsystem)
      if (substr(pathinfo($ticketImg, PATHINFO_FILENAME),-1,1) == "a") {
        $ticketBackImg = (substr(pathinfo($ticketImg, PATHINFO_FILENAME),0,-1)) . "b.jpg";
      } else {
        $ticketBackImg = (pathinfo($ticketImg, PATHINFO_FILENAME) + 1).".jpg";
      }

      if (strpos($ticketImg, "/") === false) {
        $ticketTime = strtotime($ticketImgDate);
        $ticketDir = date("ymd", $ticketTime);
        $ticketUrl = $ticketImageURI.$ticketDir."/".$ticketImg;
        $ticketBackUrl = $ticketImageURI.$ticketDir."/back/".$ticketBackImg;
      } else {
        $ticketDir = "";
        $ticketUrl = $ticketImageURI."/". $ticketImg."";
        $ticketBackUrl = $ticketImageURI."/back/".$ticketBackImg;
      }

      $html .= "
        <li class='swiper-slide' style='overflow-y: scroll; height: 450px;'>
          <img src='{$ticketUrl}' style='width: 270px;' onerror=\"this.style.display='none';\" />
          <img src='{$ticketBackUrl}' style='width: 270px;' onerror=\"this.style.display='none';\" />
        </li>
      ";
    }

    echo json_encode(array("html" => $html));
    exit;
  } else if ($VAL['mode'] == 'getWinners') {
    /**
     * 당첨 정보 가져오기
     */
    $list = $db->get_list("SELECT * FROM MAIN_WINNER_LIST ORDER BY WIN_MONEY DESC");

    for ($i = 0; $i < count($list['ID']); $i++) {
      $html .= '
        <li>
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

      $html .= '
          </div>
          <div class="name_prize_wrap">
            <p class="name">'.$list['FNAME'][$i].'*'.$list['LNAME'][$i].' <span class="id">'.$list['USER_ID'][$i].'</span></p>
            <p class="prize">$ '.$list['WIN_MONEY'][$i].'</p>
          </div>
        </li>
      ';
    }

    $RES['html'] = $html;
    $RES['error'] = true;
    echo json_encode($RES);
  }
?>
