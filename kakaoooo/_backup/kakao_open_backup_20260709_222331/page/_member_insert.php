<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
header('Content-Type: application/json; charset=UTF-8');

$name    = isset($_POST['name']) ? trim($_POST['name']) : '';
$gender  = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$content_원본 = $content;

if (mb_strlen($name, 'UTF-8') !== 2) {
  echo json_encode(['success' => false, 'message' => '닉네임은 2글자로 입력해주세요.']);
  exit;
}
if ($gender !== '남' && $gender !== '여') {
  echo json_encode(['success' => false, 'message' => '성별을 선택해주세요.']);
  exit;
}

$gender_원본 = $gender;
$gender  = ($gender === '남') ? 1 : 2;

$name    = mysqli_real_escape_string($conn, $name);
$content = mysqli_real_escape_string($conn, $content);

$중복 = db_select("select count(*) as cnt from tb_member where name = '{$name}' and status = 0 ");
if ($중복 && $중복['cnt'] > 0) {
  echo json_encode(['success' => false, 'message' => '이미 사용 중인 닉네임입니다.']);
  exit;
}

$높번 = db_select("select max(num) as nmax from tb_member");
$다음번호 = (int)($높번['nmax'] ?? 0) + 1;
require_once __DIR__ . '/../api/function.php';
$신규코드 = 회원_6자리코드_생성();
$신규코드_esc = mysqli_real_escape_string($conn, $신규코드);

$sql = "insert into tb_member set
  status = 0,
  code = '{$신규코드_esc}',
  couple = 2,
  num = '{$다음번호}',
  gender = {$gender},
  name = '{$name}',
  content = '{$content}',
  getto = 0,
  max_getto = 0,
  regdate = now() ";
$result = db_query($sql);

if ($result) {
  if ($content !== '') {
    db_query("insert into tb_member_profile set name = '{$name}', content = '{$content}', regdate = now() ");
  }

  $가입본문 = "🌸 신입 [ {$name} ] ({$gender_원본}) 등록완료!\n환영합니다 🎉";
  if ($content_원본 !== '') {
    $가입본문 .= "\n\n" . $content_원본;
  }
  $가입멘트 = addslashes($가입본문);
  $name_esc = addslashes($name);
  db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$가입멘트}', leverage = 0, item = '{$name_esc}', regdate = NOW()");

  echo json_encode(['success' => true, 'num' => $다음번호]);
} else {
  echo json_encode(['success' => false, 'message' => '회원가입 처리 중 오류가 발생했습니다.']);
}
