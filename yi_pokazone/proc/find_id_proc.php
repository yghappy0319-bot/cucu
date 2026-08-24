<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/find.php');
}

$mb_name  = trim($_POST['mb_name'] ?? '');
$mb_email = trim($_POST['mb_email'] ?? '');

if ($mb_name === '' || mb_strlen($mb_name) > 50) {
    alert_goto('이름을 올바르게 입력해 주세요.');
}
if (!filter_var($mb_email, FILTER_VALIDATE_EMAIL)) {
    alert_goto('이메일 형식이 올바르지 않습니다.');
}

$esc_name  = db_escape($mb_name);
$esc_email = db_escape($mb_email);

$rs = db_query(
    "SELECT mb_id FROM tb_member
     WHERE mb_name = '{$esc_name}' AND mb_email = '{$esc_email}' AND mb_status = 1
     LIMIT 2"
);

if (!$rs) {
    alert_goto('처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');
}

$rows = [];
while ($r = db_assoc($rs)) {
    $rows[] = $r;
}

if (count($rows) === 0) {
    alert_goto('일치하는 회원 정보가 없습니다. 입력 정보를 확인해 주세요.');
}

$mb_id = (string) ($rows[0]['mb_id'] ?? '');
$len   = strlen($mb_id);
if ($len <= 0) {
    alert_goto('일치하는 회원 정보가 없습니다. 입력 정보를 확인해 주세요.');
}

$visible = $len <= 4 ? 1 : ($len <= 6 ? 2 : 3);
$masked  = substr($mb_id, 0, $visible) . str_repeat('*', max(4, $len - $visible));

alert_goto('회원님의 아이디는 ' . $masked . ' 입니다. (일부만 표시)', '/login.php');
