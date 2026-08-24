<?
include_once "../lib/function.php";
include_once "../_chk.php";
include_once $_SERVER['DOCUMENT_ROOT']."/lib/gmail_smtp_mail.php";

$sql = "select * from lr_member where id = '{$email_id}' and email = '{$email_address}' ";
$data = db_select($sql);

if(!$data['idx']){
  $data = array("status" => 0, "msg" => "정보가 존재하지 않습니다.");
  echo json_encode($data);
  exit;
}

$랜덤문자 = 랜덤문자숫자(10);

$to = $data['email'];
$toName = $data['id']."님";
$subject = '[메가파워월드]새로운 비밀번호로 변경하기';
$내용 = '<b><a href="https://www.megapower.world/member/new_pass.html?code='.$랜덤문자.'">새로운 비밀번호로 변경하기</a></b>';

로그_문자발송($data['idx'], 1, 1, $랜덤문자, $data['email'], $내용, $아이피, $pages);
db_query("insert into lr_new_pass set status = 0, midx = {$data['idx']}, code = '{$랜덤문자}', regdate = now() ");

$result = sendGmailSMTP($to, $toName, $subject, $내용);

if ($result === true) {
    $data = array("status" => 1, "msg" => "귀하의 이메일로 비밀번호를 다시 설정할 수 있는 링크를 발송하였습니다.\n받으신 메일을 통하여 비밀번호를 재설정하시길 바랍니다.");
    echo json_encode($data);
    exit;
} else {
    echo $result; // 에러 메시지 출력
}
