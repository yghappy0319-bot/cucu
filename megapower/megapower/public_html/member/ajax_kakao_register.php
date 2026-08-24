<?php
include_once "../lib/function.php";
$deviceType = PC모바일체크();

if($user_id){

  $user_id = "kakao_".$user_id;
  $sql = "select count(*) as cnt from lr_member where id = '{$user_id}' ";
  $user = db_select($sql);
  if($user['cnt']>0){ //아이디가 있으면
    session_start();
    $sql = "select * from lr_member where id = '{$user_id}' ";
    $user = db_select($sql);
    $_SESSION['midx'] = $user['idx'];
    db_query("update lr_member set last_login_date = now() where idx = {$user['idx']} ");
    if($user['phone']==""){
      $_data = array("status" => "register"); //연락처 입력이 안되어있는경우 회원가입창으로
      die(json_encode($_data));
    }else{
      $_data = array("status" => "ok");
      die(json_encode($_data));
    }

  }else{
    $_SESSION['kakao_id'] = $user_id;
    $_SESSION['kakao_email'] = "";

    $_data = array("status" => "register");
    die(json_encode($_data));

  }

}
