<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

  $VAL = $_POST;

  if ($VAL['mode'] == 'ball_no') {
    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    $ball1 = (isset($info['BALL1'])) ? $info['BALL1'] : 0;
    $ball2 = (isset($info['BALL2'])) ? $info['BALL2'] : 0;
    $ball3 = (isset($info['BALL3'])) ? $info['BALL3'] : 0;
    $ball4 = (isset($info['BALL4'])) ? $info['BALL4'] : 0;
    $ball5 = (isset($info['BALL5'])) ? $info['BALL5'] : 0;
    $ballp = (isset($info['BALLP'])) ? $info['BALLP'] : 0;
    $check = false;

    if (isset($info['WININFO_NO'])) {
      $check = true;
    }

    $return = array(
      "error" => $check,
      "ball1" => $ball1,
      "ball2" => $ball2,
      "ball3" => $ball3,
      "ball4" => $ball4,
      "ball5" => $ball5,
      "ballp" => $ballp,
    );

    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'ball_no_in') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $return = array("error"=>false, "msg"=>"");

    $play_date = (isset($VAL['play_date'])) ? $VAL['play_date'] : "";
    $ball1 = (isset($VAL['ball1'])) ? $VAL['ball1'] : 0;
    $ball2 = (isset($VAL['ball2'])) ? $VAL['ball2'] : 0;
    $ball3 = (isset($VAL['ball3'])) ? $VAL['ball3'] : 0;
    $ball4 = (isset($VAL['ball4'])) ? $VAL['ball4'] : 0;
    $ball5 = (isset($VAL['ball5'])) ? $VAL['ball5'] : 0;
    $ballp = (isset($VAL['ballp'])) ? $VAL['ballp'] : 0;

    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    if (isset($info['WININFO_NO'])) {
    } else {
      $return['msg'] = "추첨일자를 정확히 입력해 주세요.";
      echo json_encode($return);
      exit;
    }

    $sql = "
      UPDATE WININFO SET
        BALL1 = '{$ball1}',
        BALL2 = '{$ball2}',
        BALL3 = '{$ball3}',
        BALL4 = '{$ball4}',
        BALL5 = '{$ball5}',
        BALLp = '{$ballp}'
      WHERE
        PLAYDATE = '{$VAL['play_date']}'
    ";
    $db->query($sql);

    $return['error'] = true;
    $return['msg'] = "당첨볼을 등록하였습니다.";
    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'wininfo_price') {
    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    $priz1 = (isset($info['PRIZ1'])) ? number_format($info['PRIZ1']) : 0;
    $priz2 = (isset($info['PRIZ2'])) ? number_format($info['PRIZ2']) : 0;
    $priz3 = (isset($info['PRIZ3'])) ? number_format($info['PRIZ3']) : 0;
    $priz4 = (isset($info['PRIZ4'])) ? number_format($info['PRIZ4']) : 0;
    $priz5 = (isset($info['PRIZ5'])) ? number_format($info['PRIZ5']) : 0;
    $priz6 = (isset($info['PRIZ6'])) ? number_format($info['PRIZ6']) : 0;
    $priz7 = (isset($info['PRIZ7'])) ? number_format($info['PRIZ7']) : 0;
    $priz8 = (isset($info['PRIZ8'])) ? number_format($info['PRIZ8']) : 0;
    $priz9 = (isset($info['PRIZ9'])) ? number_format($info['PRIZ9']) : 0;
    $is_type = (isset($info['IS_TYPE'])) ? $info['IS_TYPE'] : "N";
    $gubun = (isset($info['GUBUN'])) ? $ball_op[$info['GUBUN']] : "";
    $check = false;

    if (isset($info['WININFO_NO'])) {
      $check = true;
    }

    $return = array(
      "error"   => $check,
      "priz1"   => $priz1,
      "priz2"   => $priz2,
      "priz3"   => $priz3,
      "priz4"   => $priz4,
      "priz5"   => $priz5,
      "priz6"   => $priz6,
      "priz7"   => $priz7,
      "priz8"   => $priz8,
      "priz9"   => $priz9,
      "gubun"   => $gubun,
      "is_type" => $is_type,
    );

    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'ball_priz_in') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $return = array("error"=>false, "msg"=>"");

    $play_date = (isset($VAL['play_date'])) ? $VAL['play_date'] : "";
    $priz1 = (isset($VAL['priz1'])) ? $VAL['priz1'] : 0;
    $priz2 = (isset($VAL['priz2'])) ? $VAL['priz2'] : 0;
    $priz3 = (isset($VAL['priz3'])) ? $VAL['priz3'] : 0;
    $priz4 = (isset($VAL['priz4'])) ? $VAL['priz4'] : 0;
    $priz5 = (isset($VAL['priz5'])) ? $VAL['priz5'] : 0;
    $priz6 = (isset($VAL['priz6'])) ? $VAL['priz6'] : 0;
    $priz7 = (isset($VAL['priz7'])) ? $VAL['priz7'] : 0;
    $priz8 = (isset($VAL['priz8'])) ? $VAL['priz8'] : 0;
    $priz9 = (isset($VAL['priz9'])) ? $VAL['priz9'] : 0;
    $is_type = (isset($VAL['is_type'])) ? $VAL['is_type'] : "N";

    $priz1 = filter_var($priz1, FILTER_SANITIZE_NUMBER_INT);
    $priz2 = filter_var($priz2, FILTER_SANITIZE_NUMBER_INT);
    $priz3 = filter_var($priz3, FILTER_SANITIZE_NUMBER_INT);
    $priz4 = filter_var($priz4, FILTER_SANITIZE_NUMBER_INT);
    $priz5 = filter_var($priz5, FILTER_SANITIZE_NUMBER_INT);
    $priz6 = filter_var($priz6, FILTER_SANITIZE_NUMBER_INT);
    $priz7 = filter_var($priz7, FILTER_SANITIZE_NUMBER_INT);
    $priz8 = filter_var($priz8, FILTER_SANITIZE_NUMBER_INT);
    $priz9 = filter_var($priz9, FILTER_SANITIZE_NUMBER_INT);

    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    if (isset($info['WININFO_NO'])) {
    } else {
      $return['msg'] = "추첨일자를 정확히 입력해 주세요.";
      echo json_encode($return);
      exit;
    }

    $sql = "
      UPDATE WININFO SET
        PRIZ1 = '{$priz1}',
        PRIZ2 = '{$priz2}',
        PRIZ3 = '{$priz3}',
        PRIZ4 = '{$priz4}',
        PRIZ5 = '{$priz5}',
        PRIZ6 = '{$priz6}',
        PRIZ7 = '{$priz7}',
        PRIZ8 = '{$priz8}',
        PRIZ9 = '{$priz9}',
        IS_TYPE = '{$is_type}'
      WHERE
        PLAYDATE = '{$VAL['play_date']}'
    ";
    $db->query($sql);

    $return['error'] = true;
    $return['msg']  = "당첨금을 등록하였습니다.";
    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'wininfo_prizcnt') {
    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    $prizcnt1 = (isset($info['PRIZCNT1'])) ? number_format($info['PRIZCNT1']) : 0;
    $prizcnt2 = (isset($info['PRIZCNT2'])) ? number_format($info['PRIZCNT2']) : 0;
    $prizcnt3 = (isset($info['PRIZCNT3'])) ? number_format($info['PRIZCNT3']) : 0;
    $prizcnt4 = (isset($info['PRIZCNT4'])) ? number_format($info['PRIZCNT4']) : 0;
    $prizcnt5 = (isset($info['PRIZCNT5'])) ? number_format($info['PRIZCNT5']) : 0;
    $prizcnt6 = (isset($info['PRIZCNT6'])) ? number_format($info['PRIZCNT6']) : 0;
    $prizcnt7 = (isset($info['PRIZCNT7'])) ? number_format($info['PRIZCNT7']) : 0;
    $prizcnt8 = (isset($info['PRIZCNT8'])) ? number_format($info['PRIZCNT8']) : 0;
    $prizcnt9 = (isset($info['PRIZCNT9'])) ? number_format($info['PRIZCNT9']) : 0;
    $gubun = (isset($info['GUBUN'])) ? $ball_op[$info['GUBUN']] : "";
    $check = false;

    if (isset($info['WININFO_NO'])) {
      $check = true;
    }

    $return = array(
      "error"    => $check,
      "prizcnt1" => $prizcnt1,
      "prizcnt2" => $prizcnt2,
      "prizcnt3" => $prizcnt3,
      "prizcnt4" => $prizcnt4,
      "prizcnt5" => $prizcnt5,
      "prizcnt6" => $prizcnt6,
      "prizcnt7" => $prizcnt7,
      "prizcnt8" => $prizcnt8,
      "prizcnt9" => $prizcnt9,
      "gubun"    => $gubun,
    );

    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'ball_prizcnt_in') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $return  = array("error"=>false, "msg"=>"");

    $play_date = (isset($VAL['play_date'])) ? $VAL['play_date'] : "";
    $prizcnt1 = (isset($VAL['prizcnt1'])) ? $VAL['prizcnt1'] : 0;
    $prizcnt2 = (isset($VAL['prizcnt2'])) ? $VAL['prizcnt2'] : 0;
    $prizcnt3 = (isset($VAL['prizcnt3'])) ? $VAL['prizcnt3'] : 0;
    $prizcnt4 = (isset($VAL['prizcnt4'])) ? $VAL['prizcnt4'] : 0;
    $prizcnt5 = (isset($VAL['prizcnt5'])) ? $VAL['prizcnt5'] : 0;
    $prizcnt6 = (isset($VAL['prizcnt6'])) ? $VAL['prizcnt6'] : 0;
    $prizcnt7 = (isset($VAL['prizcnt7'])) ? $VAL['prizcnt7'] : 0;
    $prizcnt8 = (isset($VAL['prizcnt8'])) ? $VAL['prizcnt8'] : 0;
    $prizcnt9 = (isset($VAL['prizcnt9'])) ? $VAL['prizcnt9'] : 0;

    $prizcnt1 = filter_var($prizcnt1, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt2 = filter_var($prizcnt2, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt3 = filter_var($prizcnt3, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt4 = filter_var($prizcnt4, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt5 = filter_var($prizcnt5, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt6 = filter_var($prizcnt6, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt7 = filter_var($prizcnt7, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt8 = filter_var($prizcnt8, FILTER_SANITIZE_NUMBER_INT);
    $prizcnt9 = filter_var($prizcnt9, FILTER_SANITIZE_NUMBER_INT);

    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    if (isset($info['WININFO_NO'])) {
    } else {
      $return['msg'] = "추첨일자를 정확히 입력해 주세요.";
      echo json_encode($return);
      exit;
    }

    $sql = "
      UPDATE WININFO SET
        PRIZCNT1 = '{$prizcnt1}',
        PRIZCNT2 = '{$prizcnt2}',
        PRIZCNT3 = '{$prizcnt3}',
        PRIZCNT4 = '{$prizcnt4}',
        PRIZCNT5 = '{$prizcnt5}',
        PRIZCNT6 = '{$prizcnt6}',
        PRIZCNT7 = '{$prizcnt7}',
        PRIZCNT8 = '{$prizcnt8}',
        PRIZCNT9 = '{$prizcnt9}'
      WHERE
        PLAYDATE = '{$VAL['play_date']}'
    ";
    $db->query($sql);

    $return['error'] = true;
    $return['msg'] = "당첨금을 등록하였습니다.";
    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'wininfo_yutube') {
    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    $youtube = (isset($info['YUTUBE'])) ? number_format($info['YUTUBE']) : "";
    $gubun = (isset($info['GUBUN'])) ? $ball_op[$info['GUBUN']] : "";
    $check = false;

    if (isset($info['WININFO_NO'])) {
      $check = true;
    }

    $kor_play_date = date("Y-m-d", strtotime($info['PLAYDATE']." +1 day"));

    $return = array(
      "error"         => $check,
      "yutube"        => $youtube,
      "gubun"         => $gubun,
      "kor_play_date" => $kor_play_date,
    );

    echo json_encode($return);
    exit;
  } else if ($VAL['mode'] == 'btn-yutube-in') {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-0.php"; // 권한체크
    $return = array("error"=>false, "msg"=>"");

    $youtube = (isset($VAL['yutube'])) ? $VAL['yutube'] : "";
    $VAL['play_date'] = (isset($VAL['play_date'])) ? date("Y-m-d", strtotime($VAL['play_date']." -1 day")) : "";

    $play_date = $VAL['play_date'];

    $info = $db->get_data("SELECT * FROM WININFO WHERE PLAYDATE='{$VAL['play_date']}'");

    if (isset($info['WININFO_NO'])) {
    } else {
      $return['msg'] = "추첨일자를 정확히 입력해 주세요.";
      echo json_encode($return);
      exit;
    }

    $sql = "
      UPDATE WININFO SET
        YUTUBE = '{$youtube}'
      WHERE
        PLAYDATE = '{$VAL['play_date']}'
    ";
    $db->query($sql);

    $return['error'] = true;
    $return['msg']  = "추첨영상을 등록하였습니다.";
    echo json_encode($return);
    exit;
  }
?>
