<?php
/**
 * 일회 실행: member.mb_hp_cert 컬럼 추가
 * 실행 후 이 파일은 삭제하세요.
 */
include_once __DIR__ . '/../common.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die("로그인이 필요합니다.\n");
}

$member = 로그인정보($_SESSION['midx']);
if (!$member || ($member['mb_id'] ?? '') !== 'admin') {
    die("관리자만 실행할 수 있습니다.\n");
}

$col = db_select("SHOW COLUMNS FROM member LIKE 'mb_hp_cert'");
if ($col && !empty($col['Field'])) {
    echo "이미 존재합니다: mb_hp_cert\n";
    echo "Type: " . ($col['Type'] ?? '') . "\n";
    echo "Default: " . ($col['Default'] ?? '') . "\n";
    exit;
}

$ok = db_query("ALTER TABLE member ADD COLUMN mb_hp_cert TINYINT(1) NOT NULL DEFAULT 0 COMMENT '휴대폰인증여부' AFTER mb_hp");
if ($ok) {
    echo "추가 완료: member.mb_hp_cert\n";
} else {
    global $conn;
    echo "추가 실패: " . mysqli_error($conn) . "\n";
}

$check = db_select("SHOW COLUMNS FROM member LIKE 'mb_hp_cert'");
var_export($check);
echo "\n";
