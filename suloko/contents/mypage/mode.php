<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";
$VAL = $_POST;

$RES = array("error" => false, "msg" => "");

if ($VAL["mode"] == "read_orders") {
  $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='{$no}'");
  $ticketUrl = "";

  if ($info["PRINT"] == 1) {
    $ticketImg = $info["IMG_PATH"];
    $ticketImgDate = $info["IMG_DATE"];

    //앞뒷면 구분 로직 추가 (3Dsystem)
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

    $ticketTag = "
        <img src='{$ticketUrl}' alt='' style='width: 250px;' onerror=\"this.style.display='none';\" />
        <img src='{$ticketBackUrl}' alt='' style='width: 250px;' onerror=\"this.style.display='none';\" />
      ";

  } else {
    $ticketTag = "<br><strong>스캔본 업로드 준비 중입니다.</strong><br><br>";
  }

  $win_balls = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = '{$info['GUBUN']}' AND PLAYDATE = '{$info['PLAYDATE']}'");
  $w_ball = array($win_balls["BALL1"], $win_balls["BALL2"], $win_balls["BALL3"], $win_balls["BALL4"], $win_balls["BALL5"]);

  $cla = "power";
  $multi = ["MULTI_1" => "", "MULTI_2" => "","MULTI_3" => "","MULTI_4" => "","MULTI_5" => ""];

  if ($info["GUBUN"] == "MM") {
    $cla = "mega";

    if ($info["DRAWNUM"] >= 2066) {
      if ($info["PRINT"] == 1) {
        $multi_data = $db->get_data("SELECT MULTI_A as MULTI_1, MULTI_B as MULTI_2, MULTI_C as MULTI_3, MULTI_D as MULTI_4, MULTI_E as MULTI_5 FROM ORDER_MULTIPLIER WHERE ORDERS_NO = '{$info['ORDERS_NO']}' LIMIT 1");
        if (is_array($multi)) {
          $multi = array_map(function ($num) {
            return $num > 0 ? "<div class='multi'>{$num}X</div>" : "";
          }, $multi_data);
        }
      }

    }
  }

  // 구매내역 요약 정보 (목록 테이블과 동일)
  $n_time = date("G");
  $playDate = date("Y-m-d", strtotime($info['PLAYDATE']."+1 day"));
  $tp = "낙첨";
  $win_price = 0;
  $win_cnt = 0;

  if ($info['WIN_YN'] == 'R') {
    $tp = "추첨전";
  } else if ($info['WIN_YN'] == 'Y') {
    $tp = "당첨";
  }

  if ($info['WIN_MONEY_USD'] > 0) {
    $win_price = $info['WIN_MONEY_USD'];
  }

  $메가배수 = 1;
  if ($info['GUBUN'] == "MM") {
    $multiplier = $db->get_data("SELECT * FROM ORDER_MULTIPLIER WHERE ORDERS_NO = {$info['ORDERS_NO']}");
    if ($info['WIN1'] > 0) {
      if ($info['BALL1']) $메가배수 *= $multiplier['MULTI_A'];
    } else if ($info['WIN2'] > 0) {
      if ($info['BALL2']) $메가배수 *= $multiplier['MULTI_B'];
    } else if ($info['WIN3'] > 0) {
      if ($info['BALL3']) $메가배수 *= $multiplier['MULTI_C'];
    } else if ($info['WIN4'] > 0) {
      if ($info['BALL4']) $메가배수 *= $multiplier['MULTI_D'];
    } else if ($info['WIN5'] > 0) {
      if ($info['BALL5']) $메가배수 *= $multiplier['MULTI_E'];
    }
  }
  $win_price = $win_price * $메가배수;

  if ($playDate == date("Y-m-d") && $info['WIN_MONEY_YN'] != 'Y' && $info['WIN_YN'] != 'R' && $n_time >= $draw_time_num) {
    $win_price = "집계중";
  }

  if ($playDate == date("Y-m-d") && $info['WIN_YN'] == 'R' && $n_time >= $draw_time_num) {
    $tp = "집계중";
  }

  for ($s = 1; $s <= 5; $s++) {
    if ($info['WIN'.$s] > 0) {
      $win_cnt++;
    }
  }

  $gubun_name = $ball_op[$info['GUBUN']];
  $reg_date = date("Y-m-d H:i", strtotime($info['REG_DATE']));
  $draw_date = date("Y년 m월 d일", strtotime($info['PLAYDATE']." +1 day"))." ".get_dst_drawtime(date("Y-m-d", strtotime($info['PLAYDATE']." +1 day")));
  $win_money_txt = is_numeric($win_price) ? "$ ".number_format($win_cnt * $win_price) : $win_price;

  $html .= '
      <table class="tb_type2" id="list_box">
        <thead>
          <caption>
            <div class="hide">
              최근 구매내역에 대한 정보를 제공하는 표
            </div>
          </caption>
          <colgroup>
            <col width="35%">
            <col width="">
          </colgroup>
        </thead>
        <tbody id="box'.$info['ORDERS_NO'].'">
          <tr>
            <th>복권 스캔본</th>
            <td style="text-align: center;">
              <div style="max-height: 420px; width: 350px; overflow: scroll; margin: 0 auto;">'.$ticketTag.'</div>
            </td>
          </tr>
          <tr>
            <th>복권명</th>
            <td style="text-align: left;">'.$gubun_name.'</td>
          </tr>
          <tr>
            <th>회차</th>
            <td style="text-align: left;">'.$info['DRAWNUM'].'</td>
          </tr>
          <tr>
            <th>게임수</th>
            <td style="text-align: left;">'.$info['GAMECNT'].'게임</td>
          </tr>
          <tr>
            <th>구매대행 신청일시</th>
            <td style="text-align: left;">'.$reg_date.'</td>
          </tr>
          <tr>
            <th>추첨일시</th>
            <td style="text-align: left;">'.$draw_date.'</td>
          </tr>
          <tr>
            <th>당첨유무</th>
            <td style="text-align: left;">'.$tp.'</td>
          </tr>
          <tr>
            <th>당첨게임수</th>
            <td style="text-align: left;">'.$win_cnt.'</td>
          </tr>
          <tr>
            <th>당첨금</th>
            <td style="text-align: left;">'.$win_money_txt.'</td>
          </tr>';

  if ($win_balls["BALL1"] == 0) {
    $html .= '
        <tr>
          <th>당첨번호</th>
          <td style="padding-right:0px;text-align: left;">
            추첨전
          </td>
        </tr>';
  } else {
    $html .= '
        <tr>
          <th>당첨번호</th>
            <td style="padding-right: 0px;">
              <div class="wrap_ball" style="margin-bottom: 2px;">
                <ul class="list_ball clear">
                  <li><span>'.$win_balls['BALL1'].'</span></li>
                  <li><span>'.$win_balls['BALL2'].'</span></li>
                  <li><span>'.$win_balls['BALL3'].'</span></li>
                  <li><span>'.$win_balls['BALL4'].'</span></li>
                  <li><span>'.$win_balls['BALL5'].'</span></li>
                  <li class="'.$cla.'"><span>'.$win_balls['BALLP'].'</span></li>
                </ul>
              </div>
            </td>
        </tr>';
  }

  $html .= '
      <tr>
        <th>구매번호</th>
        <td style="padding-right:0px;" class="tdFlex ball'.$info['ORDERS_NO'].'">';

  for ($s = 1; $s < 6; $s++) {
    if ($info["BALL".$s] != "") {
      $ball = explode(",", $info["BALL".$s]);
      $c_ball0 = "";
      $c_ball1 = "";
      $c_ball2 = "";
      $c_ball3 = "";
      $c_ball4 = "";
      $c_ball5 = "";

      if (in_array($ball[0],$w_ball)) {
        $c_ball0 = "ball_ani_pc";
      }

      if (in_array($ball[1],$w_ball)) {
        $c_ball1 = "ball_ani_pc";
      }

      if (in_array($ball[2],$w_ball)) {
        $c_ball2 = "ball_ani_pc";
      }

      if (in_array($ball[3],$w_ball)) {
        $c_ball3 = "ball_ani_pc";
      }

      if (in_array($ball[4],$w_ball)) {
        $c_ball4 = "ball_ani_pc";
      }

      if ($ball[5] == $win_balls["BALLP"]) {
        $c_ball5 = "ball_ani_pc";
      }

      $html .= '
          <div class="wrap_ball" style="margin-bottom: 2px;">
            <ul class="list_ball clear">
              <li><span class='.$c_ball0.'>'.$ball[0].'</span></li>
              <li><span class='.$c_ball1.'>'.$ball[1].'</span></li>
              <li><span class='.$c_ball2.'>'.$ball[2].'</span></li>
              <li><span class='.$c_ball3.'>'.$ball[3].'</span></li>
              <li><span class='.$c_ball4.'>'.$ball[4].'</span></li>
              <li class="'.$cla.'"><span class='.$c_ball5.'>'.$ball[5].'</span></li>
            </ul>
            '.$multi["MULTI_".$s].'
          </div>';
    }
  }

  $html .= '</td></tr>';

  //추첨결과
  if ($info["WIN_YN"] != "R") {
    $html .= '
      <tr>
        <th>추첨결과</th>
        <td style="padding-right:0px;">
          <div style="text-align: left;font-weight: bold;">';

    if ($info["WIN_YN"] == "N") { //낙첨
      $html .= '낙첨';
    } else { //당첨
      $win_arr = array();
      for($w = 1 ; $w < 6 ; $w++) {
        if ($info["WIN".$w] != 0) {
          array_push($win_arr,$info["WIN".$w] . "등");
        }
      }
      $html .= implode(",",$win_arr) . " 당첨";
    }
    $html .= '</div></td></tr>';
  }

  $html .= '</tbody></table>';

  echo json_encode(array("html" => $html));
  exit;
} else if ($VAL["mode"] == "delete_orders") {
  if($VAL['chk_no'] == '') {
    $RES["error"] = true;
    $RES["msg"] = "선택된 내역이 없습니다.";
    echo json_encode($RES);
    exit;
  }

  $order_nos = explode(",", $VAL['chk_no']);

  for($i = 0; $i < count($order_nos); $i++) {
    $order_no = $order_nos[$i];

    if(trim($order_no) == '') {
      continue;
    }

    //check
    $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM ORDERS WHERE USER_ID='{$M_login['user_id']}' AND ORDERS_NO = '{$order_no}'");

    if($info == 0) {
      continue;
    }

    $query = "UPDATE ORDERS SET HIDE_YN = 'Y' WHERE ORDERS_NO = '{$order_no}'";
    $db->query($query);

  }

  $RES["msg"] = "선택하신 구매내역이 숨김 처리되었습니다.";
  echo json_encode($RES);
  exit;
} else if ($VAL["mode"] == "restore_orders") {
  if($VAL['chk_no'] == '') {
    $RES["error"] = true;
    $RES["msg"] = "선택된 내역이 없습니다.";
    echo json_encode($RES);
    exit;
  }

  $order_nos = explode(",", $VAL['chk_no']);

  for($i = 0; $i < count($order_nos); $i++) {
    $order_no = $order_nos[$i];

    if(trim($order_no) == '') {
      continue;
    }

    //check
    $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM ORDERS WHERE USER_ID='{$M_login['user_id']}' AND ORDERS_NO = '{$order_no}'");

    if($info == 0) {
      continue;
    }

    $query = "UPDATE ORDERS SET HIDE_YN = NULL WHERE ORDERS_NO = '{$order_no}'";
    $db->query($query);

  }

  $RES["msg"] = "선택하신 구매내역이 복구되었습니다.";
  echo json_encode($RES);
  exit;
} else if ($VAL["mode"] == "get_ticket_images") {
  $query = "SELECT IMG_PATH, IMG_DATE FROM ORDERS WHERE UNIQNUM='{$no}'";
  $info = $db->get_list($query);

  if (!isset($info["IMG_PATH"])) {
    $query = "SELECT IMG_PATH, IMG_DATE FROM ORDERS WHERE ORDERS_NO='{$no}'";
    $info = $db->get_list($query);
  }

  $html = "";

  for ($i = 0; $i < count($info["IMG_PATH"]); $i++) {
    $ticketImg = $info['IMG_PATH'][$i];
    $ticketImgDate = $info['IMG_DATE'][$i];

    //앞뒷면 구분 로직 추가 (3Dsystem)
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
        <li class='swiper-slide' style='overflow-y: scroll; height: 550px;'>
          <img src='{$ticketUrl}' style='width: 300px;' class='bb' onerror=\"this.style.display='none';\" />
          <img src='{$ticketBackUrl}' style='width: 300px;' onerror=\"this.style.display='none';\" />
        </li>
      ";
  }

  echo json_encode(array("html" => $html));
  exit;
}
?>
