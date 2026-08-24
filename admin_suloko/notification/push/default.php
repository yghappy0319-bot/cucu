<?php
  require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
  include $_SERVER['DOCUMENT_ROOT']."/_library/function_TOPIC.php";

  $VAL = $_POST;
  $RES = array("error" => False, "msg" => "");

  if ($VAL['mode'] ==  "add_topic") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    if ($VAL['NAME'] == '') {
      echo json_encode(array("error" => true, "msg" => "아이디를 작성해주세요."));
      exit;
    }
    if ($VAL['TITLE'] == '') {
      echo json_encode(array("error" => true, "msg" => "제목을 작성해주세요."));
      exit;
    }
    if ($VAL['DESCRIPTION'] == '') {
      echo json_encode(array("error" => true, "msg" => "설명을 작성해주세요."));
      exit;
    }

    $topic = array();
    $topic['mode']        = "insert";
    $topic['TITLE']       = trim($VAL['TITLE']);
    $topic['NAME']        = trim($VAL['NAME']);
    $topic['DESCRIPTION'] = trim($VAL['DESCRIPTION']);
    $topic['STATUS']      = $VAL['STATUS'];
    $topic['SORT']        = $VAL['SORT'];

    F_TOPIC($topic);
    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] == "update_topic") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    if ($VAL['NAME'] == '') {
      echo json_encode(array("error" => true, "msg" => "아이디를 작성해주세요."));
      exit;
    }
    if ($VAL['TITLE'] == '') {
      echo json_encode(array("error" => true, "msg" => "제목을 작성해주세요."));
      exit;
    }
    if ($VAL['DESCRIPTION'] == '') {
      echo json_encode(array("error" => true, "msg" => "설명을 작성해주세요."));
      exit;
    }

    $topic['mode']        = "update";
    $topic['IDX']         = trim($VAL['IDX']);
    $topic['TITLE']       = trim($VAL['TITLE']);
    $topic['NAME']        = trim($VAL['NAME']);
    $topic['DESCRIPTION'] = trim($VAL['DESCRIPTION']);
    $topic['STATUS']      = $VAL['STATUS'];
    $topic['SORT']        = $VAL['SORT'];

    F_TOPIC($topic);

    echo json_encode($RES);
    exit;
  } else if ($VAL['mode'] ==  "delete_topic") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    $IDX = trim($VAL["no"]);

    if (!isset($IDX) || $IDX <= 0) {
      $RES['error']	=	true;
      $RES['msg']	=	"잘못된 접근입니다.";
      echo json_encode($RES);
      exit;
    }

    $info = $db->get_data("SELECT COUNT(*) AS CNT FROM TOPIC WHERE IDX = '{$IDX}' AND BASIC = 'Y'");

    if ($info['CNT'] > 0) {
      echo json_encode(array("error" => true, "msg" => "기본으로 설정된 구독은 삭제할 수 없습니다."));
      exit;
    }

    $topic = array();
    $topic['mode'] = "delete";
    $topic['IDX']  = $IDX;

    F_TOPIC($topic);

    echo json_encode($RES);
    exit;

  } else if ($VAL['mode'] == "update_push_reservation") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTI_RESERVATION.php";

    if ($VAL['IDX'] == '' || $VAL['r_date'] == '' || $VAL['r_hour'] == '' || $VAL['r_min'] == '') {
      echo json_encode(array("error"=>1,"msg"=>"필수값이 누락되었습니다."));
      exit;
    }

    if ($VAL['TITLE'] == '') {
      echo json_encode(array("error"=>1,"msg"=>"제목이 누락되었습니다."));
      exit;
    }

    if ($VAL['CONTENT'] == '') {
      echo json_encode(array("error"=>1,"msg"=>"내용이 누락되었습니다."));
      exit;
    }

    $RESERVED_AT = $VAL['r_date'] . " " . $VAL['r_hour'] . ":" . $VAL['r_min'] . ":00";

    if (strtotime($RESERVED_AT) <= strtotime(date("Y-m-d H:i:s"))) {
      echo json_encode(array("error"=>1,"msg"=>"예약시간이 현재시간보다 이전입니다."));
      exit;
    }

    $noti = array();
    $noti['mode'] = 'update';
    $noti['STAFF_ID']    = $S_login['user_id'];
    $noti['RESERVED_AT'] = trim($RESERVED_AT);
    $noti['TITLE']       = trim($VAL['TITLE']);
    $noti['USER_ID']     = trim($VAL['USER_ID']);
    $noti['TOPIC']       = trim($VAL['TOPIC']);
    $noti['CONTENT']     = trim($VAL['CONTENT']);
    $noti['IDX']         = trim($VAL['IDX']);

    $res = F_NOTI_RESERVATION($noti);

    if ($res) {
      echo json_encode(array("error"=>0,"msg"=>"예약 변경 되었습니다."));
    } else {
      echo json_encode(array("error"=>1,"msg"=>"예약 변경 실패!!"));
    }

    exit;

  } else if ($VAL['mode'] == "delete_push_reservation") {
    include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly-json-1.php"; // 권한체크
    require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NOTI_RESERVATION.php";

    if ($VAL['no'] == '') {
      echo json_encode(array("error"=>1,"msg"=>"필수값이 누락되었습니다."));
      exit;
    }

    $noti = array();
    $noti['mode'] = 'delete';
    $noti['IDX']  = trim($VAL['no']);

    $res = F_NOTI_RESERVATION($noti);

    if ($res) {
      echo json_encode(array("error"=>0,"msg"=>"예약이 삭제 되었습니다."));
    } else {
      echo json_encode(array("error"=>1,"msg"=>"예약 삭제 실패!!"));
    }

    exit;
  }
?>
