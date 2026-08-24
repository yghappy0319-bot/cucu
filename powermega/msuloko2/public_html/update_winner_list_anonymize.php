<?php
/**
 * MAIN_WINNER_LIST 테이블의 아이디·이름을 패턴에 맞게 일괄 변경 (익명화)
 *
 * 실행 방법:
 * 1) CLI: php update_winner_list_anonymize.php
 * 2) 브라우저: /update_winner_list_anonymize.php?key=winner_anonymize_2025
 *
 * 아래 $replacements 배열에서 원하는 아이디/이름으로 수정 후 실행하면 됩니다.
 * 43개 행 모두 서로 다른 아이디(및 USER_ID 마스크)가 적용됩니다.
 */

$run_key = 'winner_anonymize_2025'; // 브라우저 실행 시 비밀번호 (원하면 변경)
$is_cli = (php_sapi_name() === 'cli');
$has_key = isset($_GET['key']) && $_GET['key'] === $run_key;

if (!$is_cli && !$has_key) {
  header('Content-Type: text/html; charset=UTF-8');
  die('접근 불가. CLI에서 실행하거나 ?key=실행비밀번호 를 사용하세요.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/_common/config.php';

// 변경할 샘플 데이터 43개 (ID·USER_ID 모두 서로 다름)
// 패턴: ID, USER_ID(앞3자+****), NAME, FNAME(성), LNAME(이름 마지막 글자)
$replacements = [
  ['id' => 'jyy4433', 'name' => '장인영', 'fname' => '장', 'lname' => '영'],
  ['id' => 'gs2024', 'name' => '곽상현', 'fname' => '곽', 'lname' => '현'],
  ['id' => 'bounboun22', 'name' => '봉대철', 'fname' => '봉', 'lname' => '철'],
  ['id' => 'jds3347', 'name' => '진대석', 'fname' => '진', 'lname' => '석'],
  ['id' => 'cul4216', 'name' => '최철준', 'fname' => '최', 'lname' => '준'],
  ['id' => 'sjrealst9', 'name' => '성요한', 'fname' => '성', 'lname' => '한'],
  ['id' => 'parkjh', 'name' => '박지훈', 'fname' => '박', 'lname' => '훈'],
  ['id' => 'choims1', 'name' => '최민수', 'fname' => '최', 'lname' => '수'],
  ['id' => 'hanosh2', 'name' => '한소희', 'fname' => '한', 'lname' => '희'],
  ['id' => 'yunsy03', 'name' => '윤서연', 'fname' => '윤', 'lname' => '연'],
  ['id' => 'leedh04', 'name' => '이동훈', 'fname' => '이', 'lname' => '훈'],
  ['id' => 'kimjs05', 'name' => '김재석', 'fname' => '김', 'lname' => '석'],
  ['id' => 'jangmw6', 'name' => '장민우', 'fname' => '장', 'lname' => '우'],
  ['id' => 'songhy7', 'name' => '송현영', 'fname' => '송', 'lname' => '영'],
  ['id' => 'hongkj8', 'name' => '홍길동', 'fname' => '홍', 'lname' => '동'],
  ['id' => 'limsy09', 'name' => '임수진', 'fname' => '임', 'lname' => '진'],
  ['id' => 'choiyj10', 'name' => '최유진', 'fname' => '최', 'lname' => '진'],
  ['id' => 'kangdw11', 'name' => '강동원', 'fname' => '강', 'lname' => '원'],
  ['id' => 'seojh12', 'name' => '서준혁', 'fname' => '서', 'lname' => '혁'],
  ['id' => 'hanjm13', 'name' => '한지민', 'fname' => '한', 'lname' => '민'],
  ['id' => 'ryusw14', 'name' => '류승완', 'fname' => '류', 'lname' => '완'],
  ['id' => 'ohsh15', 'name' => '오세훈', 'fname' => '오', 'lname' => '훈'],
  ['id' => 'shinjy16', 'name' => '신지영', 'fname' => '신', 'lname' => '영'],
  ['id' => 'kwonhs17', 'name' => '권혜선', 'fname' => '권', 'lname' => '선'],
  ['id' => 'yoonjk18', 'name' => '윤정길', 'fname' => '윤', 'lname' => '길'],
  ['id' => 'baekms19', 'name' => '백민서', 'fname' => '백', 'lname' => '서'],
  ['id' => 'namdw20', 'name' => '남동우', 'fname' => '남', 'lname' => '우'],
  ['id' => 'jinhy21', 'name' => '진하늘', 'fname' => '진', 'lname' => '늘'],
  ['id' => 'moonys22', 'name' => '문유선', 'fname' => '문', 'lname' => '선'],
  ['id' => 'sonhm23', 'name' => '손혜미', 'fname' => '손', 'lname' => '미'],
  ['id' => 'jungkh24', 'name' => '정경훈', 'fname' => '정', 'lname' => '훈'],
  ['id' => 'leekj25', 'name' => '이규진', 'fname' => '이', 'lname' => '진'],
  ['id' => 'kimsw26', 'name' => '김성우', 'fname' => '김', 'lname' => '우'],
  ['id' => 'parkyj27', 'name' => '박예진', 'fname' => '박', 'lname' => '진'],
  ['id' => 'choedh28', 'name' => '최도훈', 'fname' => '최', 'lname' => '훈'],
  ['id' => 'hanjw29', 'name' => '한지원', 'fname' => '한', 'lname' => '원'],
  ['id' => 'imdh30', 'name' => '임동현', 'fname' => '임', 'lname' => '현'],
  ['id' => 'songjk31', 'name' => '송지훈', 'fname' => '송', 'lname' => '훈'],
  ['id' => 'hongsw32', 'name' => '홍서윤', 'fname' => '홍', 'lname' => '윤'],
  ['id' => 'janghy33', 'name' => '장현우', 'fname' => '장', 'lname' => '우'],
  ['id' => 'ryujs34', 'name' => '류지성', 'fname' => '류', 'lname' => '성'],
  ['id' => 'ohyj35', 'name' => '오유나', 'fname' => '오', 'lname' => '나'],
  ['id' => 'shinkh36', 'name' => '신경호', 'fname' => '신', 'lname' => '호'],
  ['id' => 'kwonjy37', 'name' => '권지영', 'fname' => '권', 'lname' => '영'],
  ['id' => 'yoonms38', 'name' => '윤민수', 'fname' => '윤', 'lname' => '수'],
  ['id' => 'baekhs39', 'name' => '백현서', 'fname' => '백', 'lname' => '서'],
  ['id' => 'namjy40', 'name' => '남지연', 'fname' => '남', 'lname' => '연'],
  ['id' => 'jindw41', 'name' => '진대영', 'fname' => '진', 'lname' => '영'],
  ['id' => 'moonsh42', 'name' => '문성훈', 'fname' => '문', 'lname' => '훈'],
  ['id' => 'sonkj43', 'name' => '손경재', 'fname' => '손', 'lname' => '재'],
];

// USER_ID를 ID 앞 3자 + **** 로 생성 (각각 다름)
foreach ($replacements as $i => &$r) {
  $r['user_id'] = substr($r['id'], 0, 3) . '****';
}
unset($r);

$list = $db->get_list("SELECT ID, USER_ID, NAME, FNAME, LNAME FROM MAIN_WINNER_LIST ORDER BY WIN_MONEY DESC");

if (empty($list) || !isset($list['ID'])) {
  $msg = 'MAIN_WINNER_LIST에 데이터가 없습니다.';
  if ($is_cli) {
    echo $msg . PHP_EOL;
  } else {
    header('Content-Type: text/html; charset=UTF-8');
    echo $msg;
  }
  exit;
}

$count = count($list['ID']);
$updated = 0;
$errors = [];

$escape = function ($s) use ($db) {
  return mysqli_real_escape_string($db->db_link, $s);
};

for ($i = 0; $i < $count; $i++) {
  $old_id = $escape($list['ID'][$i]);
  $idx = $i % count($replacements);
  $r = $replacements[$idx];

  $new_id    = $escape($r['id']);
  $new_uid   = $escape($r['user_id']);
  $new_name  = $escape($r['name']);
  $new_fname = $escape($r['fname']);
  $new_lname = $escape($r['lname']);

  $sql = "UPDATE MAIN_WINNER_LIST SET "
    . " ID = '{$new_id}', USER_ID = '{$new_uid}', NAME = '{$new_name}', FNAME = '{$new_fname}', LNAME = '{$new_lname}' "
    . " WHERE ID = '{$old_id}'";

  if ($db->query($sql)) {
    $updated++;
  } else {
    $errors[] = "ID {$old_id} 업데이트 실패";
  }
}

$msg = "처리 완료: {$updated}건 업데이트" . (count($errors) ? ', 오류 ' . count($errors) . '건' : '');
if ($is_cli) {
  echo $msg . PHP_EOL;
  if (!empty($errors)) {
    foreach ($errors as $e) echo ' - ' . $e . PHP_EOL;
  }
} else {
  header('Content-Type: text/html; charset=UTF-8');
  echo $msg;
  if (!empty($errors)) {
    echo '<ul>';
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul>';
  }
}
