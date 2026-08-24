<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
header('Content-Type: application/json; charset=UTF-8');

if (!function_exists('프로필_공창알림_지역마스킹')) {
  /** 공창 알림용 — 지역(시•군) 값만 ***** 로 가림 (DB 저장값은 원본 유지) */
  function 프로필_공창알림_지역마스킹($content) {
    $masked = preg_replace(
      '/^((?:[🌸🤍🍭❄️🥕]\s*)?지역\s*\(\s*시\s*[•·・ㆍ･]?\s*군\s*\)\s*:\s*).*$/mu',
      '$1*****',
      (string)$content
    );
    return $masked !== null ? $masked : (string)$content;
  }
}

$name    = isset($_POST['name']) ? trim($_POST['name']) : '';
$gender  = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$content_원본 = $content;
$공창프로필 = 프로필_공창알림_지역마스킹($content_원본);

if (mb_strlen($name, 'UTF-8') !== 2) {
  echo json_encode(['success' => false, 'message' => '닉네임은 2글자로 입력해주세요.']);
  exit;
}
if ($gender !== '남' && $gender !== '여') {
  echo json_encode(['success' => false, 'message' => '성별을 선택해주세요.']);
  exit;
}
if ($content === '') {
  echo json_encode(['success' => false, 'message' => '프로필 내용을 입력해주세요.']);
  exit;
}

$gender_원본 = $gender;
$gender_int = ($gender === '남') ? 1 : 2;

$name_esc    = mysqli_real_escape_string($conn, $name);
$content_esc = mysqli_real_escape_string($conn, $content);

require_once __DIR__ . '/../api/function.php';
if (function_exists('색확정_컬럼보장')) {
  색확정_컬럼보장();
}

// 자동 신입등록된 기존 회원 → 닉 기준 프로필/성별 업데이트
$기존 = db_select("SELECT idx, num, code, IFNULL(`색확정`, 1) AS 색확정 FROM tb_member WHERE name = '{$name_esc}' AND status = 0 LIMIT 1");
if (!empty($기존['idx'])) {
  $결과 = db_query("UPDATE tb_member SET
    gender = {$gender_int},
    content = '{$content_esc}',
    welcome = 1
    WHERE name = '{$name_esc}' AND status = 0
    LIMIT 1");

  if (!$결과) {
    echo json_encode(['success' => false, 'message' => '프로필 업데이트 중 오류가 발생했습니다.']);
    exit;
  }

  db_query("INSERT INTO tb_member_profile SET name = '{$name_esc}', content = '{$content_esc}', regdate = NOW()");

  $가입본문 = "🌸 [ {$name} ] ({$gender_원본}) 프로필 업데이트 완료!\n이어서 색표도 골라보자 🎨";
  if ($공창프로필 !== '') {
    $가입본문 .= "\n\n" . $공창프로필;
  }
  $가입멘트 = addslashes($가입본문);
  db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$가입멘트}', leverage = 0, item = '{$name_esc}', regdate = NOW()");

  $코드 = function_exists('회원_접속코드_발급') ? 회원_접속코드_발급($name) : trim((string)($기존['code'] ?? ''));

  echo json_encode([
    'success' => true,
    'updated' => true,
    'num' => (int)($기존['num'] ?? 0),
    'code' => $코드,
    'message' => '기존 닉 프로필을 업데이트했어요.',
  ]);
  exit;
}

// 신규 회원 INSERT
$높번 = db_select("SELECT MAX(num) AS nmax FROM tb_member");
$다음번호 = (int)($높번['nmax'] ?? 0) + 1;
$신규코드 = 회원_6자리코드_생성();
$신규코드_esc = mysqli_real_escape_string($conn, $신규코드);

$sql = "INSERT INTO tb_member SET
  status = 0,
  code = '{$신규코드_esc}',
  couple = 2,
  `색확정` = 0,
  num = '{$다음번호}',
  gender = {$gender_int},
  name = '{$name_esc}',
  content = '{$content_esc}',
  welcome = 1,
  getto = 0,
  max_getto = 0,
  regdate = NOW()";
$result = db_query($sql);

if ($result) {
  db_query("INSERT INTO tb_member_profile SET name = '{$name_esc}', content = '{$content_esc}', regdate = NOW()");

  $가입본문 = "🌸 신입 [ {$name} ] ({$gender_원본}) 등록완료!\n환영합니다 🎉";
  if ($공창프로필 !== '') {
    $가입본문 .= "\n\n" . $공창프로필;
  }
  $가입멘트 = addslashes($가입본문);
  db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$가입멘트}', leverage = 0, item = '{$name_esc}', regdate = NOW()");

  echo json_encode([
    'success' => true,
    'updated' => false,
    'num' => $다음번호,
    'code' => $신규코드,
  ]);
} else {
  echo json_encode(['success' => false, 'message' => '회원가입 처리 중 오류가 발생했습니다.']);
}
