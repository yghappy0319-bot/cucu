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

  $html .= '
      <table class="tb_type2" id="list_box">
        <thead>
          <caption>
            <div class="hide">
              최근 구매내역에 대한 정보를 제공하는 표
            </div>
          </caption>
          <colgroup>
            <col width="25%">
            <col width="">
          </colgroup>
        </thead>
        <tbody id="box'.$info['ORDERS_NO'].'">
          <tr>
            <td colspan="2" style="text-align: center;">
              <div style="max-height: 420px; width: 350px; overflow: scroll;">'.$ticketTag.'</div>
            </td>
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
