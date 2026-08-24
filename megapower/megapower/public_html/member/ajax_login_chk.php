<?php
include_once "../lib/function.php";
include_once "../_chk.php";
  //$_SESSION['midx'] = 35;
if($member_id){
  $formattedNumber = 휴대폰번호_이메일_영문숫자패턴(trim($member_id));

  $sql = "select * from lr_member where (id = '{$formattedNumber}' or email = '{$formattedNumber}' or phone = '{$formattedNumber}') and cut_off = 0 ";
  $user = db_select($sql);

  if(!$user['idx']){
    $_data = array("msg" => "가입된 정보가 없습니다.");
    die(json_encode($_data));
  }

  if(password_verify($member_pass, $user['password'])){
    //로그인 성공
    $_SESSION['midx'] = $user['idx'];
    $_SESSION['info_pass_chk'] = 0;
    db_query("update lr_member set last_login_date = now() where idx = {$user['idx']} ");

    // if($user['new_pass']){
    //   $_SESSION['new_pass'] = $user['idx'];
    //   $rurl = "/new/new_pass.html";
    // }
    db_query("insert into lr_login_log set midx = {$user['idx']}, regdate = now() ");
    $_data = array("status" => "ok", "rurl" => $rurl);
    if($autologin==1){
      $expire_time = time() + 7 * 24 * 60 * 60; // 7일 동안 유지

       // /setcookie("auto_login", "1", $expire_time, "/");
       setcookie("save_id", $member_id, $expire_time, "/");
       //setcookie("lky_pass", $member_pass, $expire_time, "/");
    }

    die(json_encode($_data));
  }else{
    $_data = array("msg" => "로그인에 실패하였습니다.");
    die(json_encode($_data));
  }

}else{
  session_destroy();
  header("Location: /");
}
