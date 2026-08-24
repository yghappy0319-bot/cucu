<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once "../_chk.php";
$deviceType = PC모바일체크();

$sql = "select count(*) as cnt from lr_member where phone = '{$phone}'  ";
$폰번호중복검사 = db_select($sql);
if($폰번호중복검사['cnt']>0){
  die("phone_overlap");
}

if(!$name){
  die("이름을 입력해주세요.");
}
if(!$phone){
  die("휴대폰 번호를 입력해주세요.");
}

if($recommender){
  $sql_추천인 = "select count(*) as cnt from lr_member where code = '{$recommender}' ";
  $추천인결과 = db_select($sql_추천인);
  if($추천인결과['cnt']==0){
    die("recommender_id_null");
  }else{
    $_SESSION['code'] = "";
  }
}

if($phone){
  $randomLetter = 랜덤소문자한자리();
  $뒷자리4자리 = substr($phone, -4);
  $my_code = $randomLetter.$뒷자리4자리;
}

$sql = "insert into lr_member set ";
$sql.= "social = 1,";
$sql.= "id = '{$kakao_id}', ";
$sql.= "code = '{$my_code}', ";
$sql.= "email = '{$kakao_email}', ";
$sql.= "name = '{$name}', ";
$sql.= "recommender = '{$recommender}',";
$sql.= "phone = '{$phone}', ";
$sql.= "ads = '{$_SESSION['ads']}',";
$sql.= "access = '{$deviceType}',";
$sql.= "last_login_date = now(), ";
if($marketing>0){
  $sql.= "marketing = 1,";
}
$sql.= "regdate = now() ";
$result = db_query($sql);
$_midx = mysqli_insert_id($conn);
if($result){
  $추천인idx =  db_select("select idx from lr_member where code = '{$recommender}' ");
  if($추천인idx['idx']){
    추천코드포인트($_midx,3000,'추천_받은분'); //추천인 있는 회원에게 지급
    추천코드포인트($추천인idx['idx'],3000,'추천인_채택'); //추천인 있는 회원에게 지급
  }

  if($회원가입시_관리자_문자발송){
    $content = "카카오회원가입\n";
    $content.= "{$name}\n";
    $content.= date("Y-m-d H:i:s");
    if($알리고문자발송){
      SMS_SEND($관리자연락처, "회원가입", $content);
    }else{
      $문자 = 문자설정('회원가입', 1);
      $가입완료문자제목 = $문자['title'];
      $가입완료문자 = $문자['content'];
      //SMS_SEND($phone, $가입완료문자제목, $가입완료문자);
      자동문자(0,$phone,'카카오회원가입',$가입완료문자제목,$가입완료문자);
      자동문자(0, '01022934444','카카오회원가입', '카카오회원가입',$content);
    }
  }

  echo $result;
}else{
  echo "error";
}
