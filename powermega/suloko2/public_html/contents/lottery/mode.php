<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/session_start.php"; // session start
require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

$VAL = $_POST;
$RES = array("error" => False, "msg" => "");

if($VAL["mode"] == "auto_ball") {

  $ball = array();
  for($i = 0 ; $i <= 100 ; $i++) {
    $ball["ball{$i}"] = "";
  }

  $type = (isset($VAL["type"])) ? $VAL["type"] : "";

  if ($VAL["codeLK"] == "MM") {
    $numbers = num_range3($VAL["num"],$type);
  } else {
    $numbers = num_range2($VAL["num"],$type);
  }

  for($i = 0 ; $i <= 100 ; $i++) {
    $s++;
    if ($numbers[$i] != "") {
      $ball["ball".$s] = $numbers[$i];
    }
  }

  echo json_encode($ball);
  exit;


} else if ($VAL["mode"] == "my_ball_insert") {
  require_once $_SERVER["DOCUMENT_ROOT"]."/_library/function_MY_BALL.php";

  if (!isset($M_login["user_id"])) {
    $RES["msg"] = "로그인한 회원만 사용이 가능합니다.";
    echo json_encode($RES);
    exit;
  }

  $info = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$M_login['user_id']}' LIMIT 1");

  if ($info["USER_ID"] == "") {
    $RES["msg"] = "존재하지 않은 회원입니다.";
    echo json_encode($RES);
    exit;
  }

  $max_game_cnt = $max_gameCount;// 최대저장가능한 게임수
  $myball_cnt = 0;
  $new_cnt = 0;
  $mylist = $db->get_list("SELECT * FROM MY_BALL WHERE MEMBER_NO = '{$info['MEMBER_NO']}' AND GUBUN = '{$VAL['codeLK']}'");

  for($i = 0; $i < count($mylist["BALL_NO"]); $i++) {
    if (strlen($mylist["BALL1"][$i]) > 5) $myball_cnt++;
    if (strlen($mylist["BALL2"][$i]) > 5) $myball_cnt++;
    if (strlen($mylist["BALL3"][$i]) > 5) $myball_cnt++;
    if (strlen($mylist["BALL4"][$i]) > 5) $myball_cnt++;
    if (strlen($mylist["BALL5"][$i]) > 5) $myball_cnt++;
  }

  $ball_arr = explode("|",$VAL['balls']);
  $new_cnt = count($ball_arr);

  $tot_cnt = $myball_cnt + $new_cnt;
  if ($max_game_cnt < $tot_cnt) {
    $RES["msg"] = "최대 저장가능한 게임수는 ".$max_game_cnt."입니다. (현재:".$myball_cnt."게임 저장중)";
    echo json_encode($RES);
    exit;
  }

  $data = array();
  $data["mode"]  = "insert";
  $data["GUBUN"] = $VAL["codeLK"];
  $data["MEMBER_NO"] = $info["MEMBER_NO"];

  //ball number 재배열
  for($i = 0 ; $i < count($ball_arr) ; $i += 5) {
    for($j = 1 ; $j < 6 ; $j++) {
      if(($i + $j) <= count($ball_arr)) {
        $data["BALL".$j] = $ball_arr[($i + $j - 1)];
      } else {
        $data["BALL".$j] = "";
      }
    }
    F_MY_BALL($data);
  }

  $RES["msg"] = "저장하였습니다.";
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

  $RES["msg"] = $price_point;
  $RES["error"] = true;
  echo json_encode($RES);
  exit;

} else if ($VAL["mode"] == "my_ball") {
  if ($M_login["user_id"] == "") {
    $RES["msg"] = "로그인을 해주세요.";
    echo json_encode($RES);
    exit;
  }

  $html = "";

  $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");

  $list = $db->get_list("SELECT * FROM MY_BALL WHERE MEMBER_NO='".$mem['MEMBER_NO']."' AND GUBUN='".$VAL['codeLK']."'");

  if (!isset($list["BALL_NO"])) {
    $html .= '
      <tr>
        <td colspan="2" style="text-align: center;">나의 행운번호가 없습니다.</td>
      </tr>
    ';

    $RES["html"] = $html;
    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  }

  $tp = "power";

  if ($VAL["codeLK"] == "MM") {
    $tp = "mega";
  }

  for ($i = 0; $i < count($list["BALL_NO"]); $i++) {
    for ($j = 1; $j <= 5; $j++) {
      if ($list["BALL".$j][$i] == "") continue;
      $ball = explode(",", $list["BALL".$j][$i]);

      $html .= '
        <tr style="height: 60px;">
          <td>
            <input type="checkbox" name="chk_my_ball" class="chk_my_ball" value="'.$list['BALL'.$j][$i].'" style="-webkit-appearance: checkbox;">
          </td>
          <td>
            <ul class="list_ball clear" id="wininfo_balls">
              <li><span>'.$ball[0].'</span></li>
              <li><span>'.$ball[1].'</span></li>
              <li><span>'.$ball[2].'</span></li>
              <li><span>'.$ball[3].'</span></li>
              <li><span>'.$ball[4].'</span></li>
              <li class=" '.$tp.'"><span>'.$ball[5].'</span></li>
            </ul>
          </td>
        </tr>
      ';
    }
  }

  $RES["html"] = $html;
  $RES["error"] = true;
  echo json_encode($RES);
  exit;
} else if ($VAL["mode"] == "getMyNumber") {
  if ($M_login["user_id"] == "") {
    $RES["msg"] = "로그인을 해주세요.";
    echo json_encode($RES);
    exit;
  }

  $html = "";

  $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");
  $list = $db->get_list("SELECT * FROM MY_BALL WHERE MEMBER_NO='".$mem['MEMBER_NO']."' AND GUBUN='".$VAL['type']."'");

  if (empty($list)) {
    $html .= '
      <tr>
        <td colspan="2" style="text-align:center;">나의 행운번호가 없습니다.</td>
      </tr>
    ';

    $RES["html"] = $html;
    $RES["error"] = true;
    echo json_encode($RES);
    exit;
  }

  $specialBallStyle = ($VAL["type"] === "MM") ? "mega" : "power";

  foreach ($list as $ballData) {
    for ($i = 1; $i <= 5; $i++) {
      $ballKey = "BALL" . $j;
      if (empty($ballData[$ballKey])) continue;

      $ball = explode(",", $ballData[$ballKey]);
    }
  }

  for ($i = 0; $i < count($list["BALL_NO"]); $i++) {
    for ($j = 1; $j <= 5; $j++) {
      if ($list["BALL".$j][$i] == "") continue;
      $ball = explode(",", $list["BALL".$j][$i]);

      $html .= "
        <tr style='height: 60px;'>
          <td>
            <input type='checkbox' name='chk_my_ball' class='chk_my_ball' value='" . htmlspecialchars($ballData[$ballKey]) . "' style='-webkit-appearance: checkbox;'>
          </td>
          <td>
            <ul class='list_ball clear' id='wininfo_balls'>
              <li><span>" . htmlspecialchars($ball[0]) . "</span></li>
              <li><span>" . htmlspecialchars($ball[1]) . "</span></li>
              <li><span>" . htmlspecialchars($ball[2]) . "</span></li>
              <li><span>" . htmlspecialchars($ball[3]) . "</span></li>
              <li><span>" . htmlspecialchars($ball[4]) . "</span></li>
              <li class='" . htmlspecialchars($specialBallStyle) . "'><span>" . htmlspecialchars($ball[5]) . "</span></li>
            </ul>
          </td>
        </tr>
      ";
    }
  }

  $RES["html"] = $html;
  $RES["error"] = true;
  echo json_encode($RES);
  exit;
}
?>
