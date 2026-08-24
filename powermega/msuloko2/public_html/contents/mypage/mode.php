<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

$VAL = $_POST;

$RES = array("error" => False, "msg" => "");

if ($VAL["mode"] == "my_lotto_list") {

  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_ORDERS.php";

  $add_query = " AND USER_ID='{$M_login['user_id']}'";

  if ($VAL["hide_yn"] == "Y") {
    $add_query .= " AND SIGN_YN = '' AND HIDE_YN = 'Y' ";
  } else {
    $add_query .= " AND SIGN_YN = '' AND HIDE_YN IS NULL ";
  }

  $html = "";
  $limit = 20;
  $find_text = "";
  $find_object = "";
  $n_time = date("H");

  if ($sdate != "" && $edate != "") {
    $sDate = $sdate." 00:00:01";
    $eDate = $edate." 23:59:59";
  } else {
    $sDate = date("Y-m-d", strtotime("-3 months"))." 00:00:01";
    $eDate = date("Y-m-d")." 23:59:59";
    $date_gubun = "3M";
  }

  $add_query .= " AND REG_DATE BETWEEN '".$sDate."' AND '".$eDate."'";

  if ($VAL["gubun"] != "" && $VAL["gubun"] != "all") {
    $find_object = "GUBUN";
    $find_text = $VAL["gubun"];
  }

  if ($VAL["win_yn"] != "") {
    $add_query .= " AND WIN_YN='".$VAL['win_yn']."'";
  }

  $condition = array(
      "row"         => $limit,
      "page"        => $VAL["page"],
      "order"       => $VAL["order"],
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
  );

  $list = F_ORDERS_list($condition);

  if ($list["total"] <= 0) {
    $list["ORDERS_NO"] = array();
  }

  for ($i = 0; $i < count($list["ORDERS_NO"]); $i++) {
    $win_price = 0;
    $scan = "구매 진행중";
    $win_cnt = 0;
    $playDate = date("Y-m-d", strtotime($list["PLAYDATE"][$i]."+1 day"));

    if ($list["WIN_YN"][$i] == "R") {
      $tp = "추첨전";
      if ($list["PRINT_YN"][$i] == "Y") {
        $tp = "추첨전";
      }
      if ($list["IMG_YN"][$i] == "Y") {
        $tp = "추첨전";
      }
    } else if ($list["WIN_YN"][$i] == "Y") {
      //$tp = "당첨";
      $tp = "";
      for ($w = 1; $w <= 5; $w++) {
        if ($list["WIN".$w][$i] != "0") {
          if ($tp != "") {
            $tp .= ",";
          }
          $tp .= $list["WIN".$w][$i]."등 당첨";
        }
      }
    } else {
      $tp = "낙첨";
    }

    if ($list["WIN_MONEY_USD"][$i] > 0) {
      $win_price = $list["WIN_MONEY_USD"][$i];
    }

    $메가배수 = 1;

    if ($list['GUBUN'][$i] == "MM") {
      $multiplier = $db->get_data("select * from ORDER_MULTIPLIER where ORDERS_NO = {$list['ORDERS_NO'][$i]} ");

      if($list['WIN1'][$i] > 0){
        if ($list['BALL1'][$i]) $메가배수 *= $multiplier['MULTI_A'];
      }else if($list['WIN2'][$i] > 0){
        if ($list['BALL2'][$i]) $메가배수 *= $multiplier['MULTI_B'];
      }else if($list['WIN3'][$i] > 0){
        if ($list['BALL3'][$i]) $메가배수 *= $multiplier['MULTI_C'];
      }else if($list['WIN4'][$i] > 0){
        if ($list['BALL4'][$i]) $메가배수 *= $multiplier['MULTI_D'];
      }else if($list['WIN5'][$i] > 0){
        if ($list['BALL5'][$i]) $메가배수 *= $multiplier['MULTI_E'];
      }

    }

    $win_price = $win_price * $메가배수;

    if ($playDate == date("Y-m-d") && $list["WIN_MONEY_YN"][$i] != "Y" && $list["WIN_YN"][$i] != "R"  && $n_time >= $draw_time_num) {
      $win_price = "집계중";
    }

    if ($playDate == date("Y-m-d") && $list["WIN_YN"][$i] == "R" && $n_time >= $draw_time_num) {
      $tp = "집계중";
    }

    if ($list["PRINT"][$i] == 1) {
      $ticketImg = $list["IMG_PATH"][$i];
      $ticketImgDate = $list["IMG_DATE"][$i];
      $ticketTime = strtotime($ticketImgDate);

      if (strpos($ticketImg, "/") === false) {
        $ticketTime = strtotime($ticketImgDate);
        $ticketDir = date('ymd', $ticketTime);
        $ticketUrl = $ticketImageURI . $ticketDir . "/". $ticketImg . "";
      } else {
        $ticketDir = "";
        $ticketUrl = $ticketImageURI . $ticketImg . "";
      }
      $scan = "<a class='btn_popup scan_read' id='btn_scan' data-url='".$ticketUrl."' data-no='".$list["ORDERS_NO"][$i]."'>스캔본 확인<p class='bx_link'></p></a>";
    }

    $cla = "power";
    $multi = ["MULTI_1" => "", "MULTI_2" => "","MULTI_3" => "","MULTI_4" => "","MULTI_5" => ""];
    if ($list["GUBUN"][$i] == "MM") {
      $cla = "mega";
      if ($list["DRAWNUM"][$i] >= 2066) {
        if ($list["PRINT"][$i] == 1) {
          $multi_data = $db->get_data("SELECT MULTI_A as MULTI_1, MULTI_B as MULTI_2, MULTI_C as MULTI_3, MULTI_D as MULTI_4, MULTI_E as MULTI_5 FROM ORDER_MULTIPLIER WHERE ORDERS_NO = '{$list['ORDERS_NO'][$i]}' LIMIT 1");
          if (is_array($multi)) {
            $multi = array_map(function ($num) {
              return $num > 0 ? "<div class='multi'>{$num}X</div>" : "";
            }, $multi_data);
          }
        }

      }
    }

    for ($s = 1; $s <= 5; $s++) {
      if ($list["WIN".$s][$i] > 0) {
        $win_cnt++;
      }
    }

    $win_balls = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = '".$list["GUBUN"][$i]."' AND PLAYDATE = '".$list["PLAYDATE"][$i]."'");
    $w_ball = array($win_balls["BALL1"], $win_balls["BALL2"], $win_balls["BALL3"], $win_balls["BALL4"], $win_balls["BALL5"]);

    $html .= '
      <tbody id="box'.$list['ORDERS_NO'][$i].'">
        <tr>
          <th>상품(복권)명</th>
          <td>'.$ball_op[$list['GUBUN'][$i]].' / '.$list['DRAWNUM'][$i].'회차</td>
        </tr>
        <tr>
          <th>추첨일시</th>
          <td>'.date("Y년 m월 d일", strtotime($list['PLAYDATE'][$i]." +1 day")) . get_dst_drawtime(date("Y-m-d", strtotime($list['PLAYDATE'][$i]." +1 day"))).'</td>
        </tr>
        <tr>
          <th>구매대행 신청일시</th>
          <td>'.date("Y-m-d H:i", strtotime($list['REG_DATE'][$i])).'</td>
        </tr>';

    if ($win_balls["BALL1"] == 0) {
      $html .= '
        <tr>
          <th>당첨번호</th>
          <td style="padding-right:0px;">
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
        <td style="padding-right: 0px;" class="ball'.$list['ORDERS_NO'][$i].'">';

    for ($s = 1; $s < 6; $s++) {
      if ($list["BALL".$s][$i] != "") {
        $ball = explode(",", $list["BALL".$s][$i]);
        $c_ball0 = "";
        $c_ball1 = "";
        $c_ball2 = "";
        $c_ball3 = "";
        $c_ball4 = "";
        $c_ball5 = "";

        if (in_array($ball[0],$w_ball)) {
          $c_ball0 = "ball_ani";
        }

        if (in_array($ball[1],$w_ball)) {
          $c_ball1 = "ball_ani";
        }

        if (in_array($ball[2],$w_ball)) {
          $c_ball2 = "ball_ani";
        }

        if (in_array($ball[3],$w_ball)) {
          $c_ball3 = "ball_ani";
        }

        if (in_array($ball[4],$w_ball)) {
          $c_ball4 = "ball_ani";
        }

        if ($ball[5] == $win_balls["BALLP"]) {
          $c_ball5 = "ball_ani";
        }

        $html .= '
          <ul class="list_ball clear">
            <li><span class='.$c_ball0.'>'.$ball[0].'</span></li>
            <li><span class='.$c_ball1.'>'.$ball[1].'</span></li>
            <li><span class='.$c_ball2.'>'.$ball[2].'</span></li>
            <li><span class='.$c_ball3.'>'.$ball[3].'</span></li>
            <li><span class='.$c_ball4.'>'.$ball[4].'</span></li>
            <li class="'.$cla.'"><span class='.$c_ball5.'>'.$ball[5].'</span></li>
            '.$multi["MULTI_".$s].'
          </ul>'
        ;
      }
    }

    $html .= '
          </td>
        </tr>
        <tr>
          <th>당첨유무</th>
          <td>'.$tp.'</td>
        </tr>
        <tr>
          <th>복권스캔본</th>
          <td>'.$scan.'</td>
        </tr>
        <tr>
          <th>당첨 게임수</th>
          <td>'.$win_cnt.' 게임</td>
        </tr>
        <tr>
          <th>당첨금</th>
          <td class="f_GmarketSans fw500">'."$ ".number_format($win_price).'</td>
        </tr>';
    if ($win_balls["BALL1"] != 0 && $list['HIDE_YN'][$i] == '') {
      $html .= '
        <tr>
          <th>구매 숨김</th>
          <td><button type="button" class="btn_type1 btn_navy btn del_btn" style="width:100px;height:30px" data-no="'.$list['ORDERS_NO'][$i].'">구매 내역 숨김</button></td>
        </tr>';
    } else if ($win_balls["BALL1"] != 0 && $list['HIDE_YN'][$i] == 'Y') {
      $html .= '
        <tr>
          <th>숨김 복구</th>
          <td><button type="button" class="btn_type1 btn_navy btn restore_btn" style="width:100px;height:30px" data-no="'.$list['ORDERS_NO'][$i].'">숨김 내역 복구</button></td>
        </tr>';
    }
    $html .= '</tbody>';
  }

  $RES["html"] = $html;
  echo json_encode($RES);
  exit;


} else if ($VAL["mode"] == "my_lotto_list_image") {
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_ORDERS.php";

  ## 데이터 리스트업의 기준을 스캔본만이 아닌 전체 구매내역으로 표기 (SLK-477)
  $add_query = " AND USER_ID = '".$M_login['user_id']."' AND SIGN_YN = '' AND HIDE_YN IS NULL ";

  $html = "";
  $limit = 8;
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
      "row"         => $limit,
      "page"        => $VAL["page"],
      "order"       => $VAL["order"],
      "find_text"   => $find_text,
      "find_object" => $find_object,
      "add_query"   => $add_query,
  );

  $list = F_ORDERS_list($condition);

  if ($list["total"] <= 0) {
    $list["ORDERS_NO"] = array();
  }

  for ($i = 0; $i < count($list["ORDERS_NO"]); $i++) {
    $scan = "";

    if ($list["WIN_MONEY"][$i] > 0) {
      $tp = "당첨";
    } else {
      $tp = "미당첨";
    }

    if ($list["WIN_YN"][$i] == "R") {
      $tp = "미추첨";
    }

    // 데이터 리스트업의 기준을 스캔본만이 아닌 전체 구매내역으로 표기 (SLK-477)
    if ($list["PRINT"][$i] == 1) {
      $ticketImg = $list["IMG_PATH"][$i];
      $ticketImgDate = $list["IMG_DATE"][$i];

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

      $ticketUrlInfo = "
        <img src='{$ticketUrl}' style='width: 75%;' onerror=\"this.style.display='none';\" />
        <img src='{$ticketBackUrl}' style='width: 75%;' onerror=\"this.style.display='none';\" />
      ";

    } else {
      $ticketUrlInfo = "<center>구매 진행 중 입니다!</center>";
    }

    $cla = "power";
    $multi = ["MULTI_1" => "", "MULTI_2" => "","MULTI_3" => "","MULTI_4" => "","MULTI_5" => ""];

    if ($list["GUBUN"][$i] == "MM") {
      $cla = "mega";

      if ($list["DRAWNUM"][$i] >= 2066) {
        if ($list["PRINT"][$i] == 1) {
          $multi_data = $db->get_data("SELECT MULTI_A as MULTI_1, MULTI_B as MULTI_2, MULTI_C as MULTI_3, MULTI_D as MULTI_4, MULTI_E as MULTI_5 FROM ORDER_MULTIPLIER WHERE ORDERS_NO = '{$list['ORDERS_NO'][$i]}' LIMIT 1");
          if (is_array($multi)) {
            $multi = array_map(function ($num) {
              return $num > 0 ? "<div class='multi'>{$num}X</div>" : "";
            }, $multi_data);
          }
        }
      }
    }

    $html .= '
      <tbody id="box'.$list['ORDERS_NO'][$i].'">
        <tr>
          <th>상품(복권)명 </th>
          <td>'.$ball_op[$list['GUBUN'][$i]]." ".number_format($list['DRAWNUM'][$i]).'회차</td>
        </tr>
        <tr>
          <th>구매번호</th>
          <td style="padding-right: 0px;">';

    for ($s = 1; $s < 6; $s++) {
      if ($list["BALL".$s][$i] != "") {
        $ball = explode(",", $list["BALL".$s][$i]);

        $html .= '
          <div class="wrap_ball" style="margin-bottom: 2px;">
            <ul class="list_ball clear">
              <li><span>'.$ball[0].'</span></li>
              <li><span>'.$ball[1].'</span></li>
              <li><span>'.$ball[2].'</span></li>
              <li><span>'.$ball[3].'</span></li>
              <li><span>'.$ball[4].'</span></li>
              <li class="'.$cla.'"><span>'.$ball[5].'</span></li>
            </ul>
            '.$multi["MULTI_".$s].'
          </div>';
      }
    }

    $html .= '
          </td>
        </tr>
        <tr>
          <td colspan="2" style="text-align: center;">'.$ticketUrlInfo.'</td>
        </tr>
      </tbody>';
  }

  $RES["html"] = $html;
  echo json_encode($RES);
  exit;

} else if ($VAL["mode"] == "my_lotto_hide") {
  if ($VAL['no'] == '') {
    $RES["error"] = true;
    $RES["msg"] = "선택된 내역이 없습니다.";
    echo json_encode($RES);
    exit;
  }

  // check
  $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM ORDERS WHERE USER_ID='{$M_login['user_id']}' AND ORDERS_NO = '{$VAL['no']}'");

  if ($info == 0) {
    exit;
  }

  $query = "UPDATE ORDERS SET HIDE_YN = 'Y' WHERE ORDERS_NO = '{$VAL['no']}'";
  $db->query($query);

  echo json_encode($RES);
  exit;
} else if ($VAL["mode"] == "my_lotto_restore") {

  if ($VAL['no'] == '') {
    $RES["error"] = true;
    $RES["msg"] = "선택된 내역이 없습니다.";
    echo json_encode($RES);
    exit;
  }

  $info = $db->get_data_one("SELECT COUNT(*) AS cnt FROM ORDERS WHERE USER_ID='{$M_login['user_id']}' AND ORDERS_NO = '{$VAL['no']}'");
  if ($info == 0) {
    exit;
  }

  $query = "UPDATE ORDERS SET HIDE_YN = NULL WHERE ORDERS_NO = '{$VAL['no']}'";
  $db->query($query);

  echo json_encode($RES);
  exit;
}
?>
