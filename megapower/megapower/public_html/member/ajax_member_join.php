<?php
include_once "../lib/function.php";
include_once "../lib/config.php";
include_once $_SERVER['DOCUMENT_ROOT']."/lib/gmail_smtp_mail.php";

function validateUsername($input) {
    $pattern = '/^[a-z0-9]+$/';
    $length = strlen($input);
    return preg_match($pattern, $input) && $length >= 6;
}
$id = $user_id;
if (!validateUsername($id)) {
    die("no_id"); //영어와숫자만 입력가능
}

$sql = "select count(*) as cnt from lr_member where id = '{$id}'  ";
$아이디중복검사 = db_select($sql);
if($아이디중복검사['cnt']>0){
  die("id_overlap");
}
$sql = "select count(*) as cnt from lr_member where phone = '{$phone}'  ";
$폰번호중복검사 = db_select($sql);
if($폰번호중복검사['cnt']>0){
  die("phone_overlap");
}

$sql = "select count(*) as cnt from lr_member where email = '{$email}'  ";
$이메일중복검사 = db_select($sql);
if($이메일중복검사['cnt']>0){
  die("email_overlap");
}

if(!$password){
  die("password_null");
}
if($recommender){
  $sql_추천인 = "select count(*) as cnt from lr_member where (code = '{$recommender}' or id = '{$recommender}') and code != '' ";
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

$sql = "select idx, code from lr_member where (code = '{$recommender}' or id = '{$recommender}') and code != '' ";
$추천인idx =  db_select($sql);

$birthday = $birth_yy."-".$birth_mm."-".$birth_dd;

$sql = "insert into lr_member set ";
$sql.= "social = 0,";
$sql.= "id = '{$id}', ";
$sql.= "code = '{$my_code}', ";
$sql.= "email = '{$email}', ";
$_password = encryptPassword($password);
$sql.= "password = '{$_password}', ";
$sql.= "recommender = '{$추천인idx['code']}',";
$sql.= "name = '".문자열공백제거($member_name)."', ";
$sql.= "birthday = '{$birthday}', ";
$sql.= "phone = '{$phone}', ";
$sql.= "phone_auth = 1,";
$sql.= "ads = '{$ads}',";
$sql.= "access = '{$access}',";
$sql.= "push = '{$push}', ";
if($marketing>0){
  $sql.= "marketing = 1,";
}
$sql.= "regdate = now() ";
$result = db_query($sql);
$_midx = mysqli_insert_id($conn);

if($result){
  쿠폰만들기("할인쿠폰", $_midx, "50% 할인쿠폰", 50, "+1 month", $추천인idx['id']);
  //$content = "[메가파워]회원가입을 축하드립니다. 아이디 {$id} 으로 50%할인쿠폰이 자동 지급 되었습니다.\n바로가기 www.megapower.world";

$content = "
[메가파워월드]({$id})님 회원가입을 축하드립니다.\n
이용안내 가이드
*가입 시 아이디와 비밀번호를 잊어버리지 않게하기 위해 따로 메모해두시는 것을 권장 드립니다.

*안내 문자는 메가파워월드에 회원가입하시고 가입 시 마케팅동의를 해주신분들께만 발송되는 안내 메세지 입니다.

*안내문자를 더 이상 받고 싶지 않은 회원은 회원탈퇴 및 홈페이지 고객센터 1:1문의 게시글에 꼭 남겨주시기 바랍니다.

*정보입력을 잘못 입력하여 가입하신 경우 마이페이지->정보수정에서 수정이 가능합니다

*이벤트 정보
-가입시 50%할인쿠폰이 자동 지급됩니다.
-역대급 당첨금 역대급 할인 중!
-홈페이지 상단 카테고리 이벤트&쿠폰 항상 확인해주세요.

*캐시충전 페이지에서 드리는 보너스 포인트는 매달 바뀔 수 있습니다.
-----------------------------------------
*바로가기 링크정보

->홈페이지 바로가기
https://megalotto.world/

->구매방법 가이드
https://megapower.world/buy/service_buy.html

->이벤트 쿠폰 발급 바로가기
https://powerlotto.world/member/coupon.html

->쿠폰&포인트 사용방법
https://megapower.world/member/view.html?gubun=notice&idx=146

->앱,바로가기 설치방법
https://powerlotto.world/member/view.html?gubun=notice&idx=139

*아이디/비번 찾기가 안되는 회원들은 1:1문의로 요청사항과 함께 내용을 꼭 남겨주세요.

->아이디/비번 찾기
https://megapower.world/member/idpass.html

*캐시충전 유의사항: 캐시충전 페이지에서 신청 시에 입력된 성함과 금액이 일치하게 보내셔야 캐시충전이 완료됩니다. 실 수 할 경우 반드시 1:1문의에 요청바랍니다.
";

  SMS_SEND($phone, "회원가입완료", $content);
  로그_문자발송($_midx, 1, 0, 0, $phone, $content, "", "회원가입쿠폰");



  $contnet = addslashes($내용);
  로그_문자발송($_midx, 1, 1, 0, $email, $contnet, $아이피, '회원가입쿠폰');




  $logchk = db_select("select * from lr_sms_log where page = '회원가입인증' and phone = '{$phone}' order by regdate limit 1  ");
  if($logchk['idx']){ //인증문자 발송후 가입까지 이뤄졌는지
    db_query("update lr_sms_log set joins = 1 where idx = {$logchk['idx']} ");
  }

  if($추천인idx['idx']){
    쿠폰만들기("할인쿠폰", $_midx, "추천인 50% 할인쿠폰", 50, "+1 month", $추천인idx['id']);
    쿠폰만들기("할인쿠폰", $추천인idx['idx'], "추천인 50% 할인쿠폰", 50, "+1 month", $추천인idx['id']);

    // 추천코드포인트($_midx, 3000,'추천_받은분'); //추천인 있는 회원에게 지급
    // 추천코드포인트($추천인idx['idx'], 3000,'추천인_채택'); //추천인 있는 회원에게 지급
    db_query("insert into lr_member_recommend set midx = {$추천인idx['idx']}, ridx = {$_midx}, regdate = now()  ");
  }

  //
  // if($회원가입시_관리자_문자발송){
  //   $content = "일반회원가입\n";
  //   $content.= "{$name}\n";
  //   $content.= date("Y-m-d H:i:s");
  //   SMS_SEND($관리자연락처, "회원가입", $content);
  //   if($알리고문자발송){
  //     $문자 = 문자설정('회원가입', 1);
  //     $가입완료문자제목 = $문자['title'];
  //     $가입완료문자 = $문자['content'];
  //     SMS_SEND($phone, $가입완료문자제목, $가입완료문자);
  //   }else{
  //     // if($result2->result_code != 1){
  //     자동문자(0,$phone,'회원가입',$가입완료문자제목,$가입완료문자);
  //     자동문자(0, $관리자연락처,'일반회원가입', '일반회원가입',$content);
  //   }
  // }
  echo $result;
}else{
  echo "error";
}
