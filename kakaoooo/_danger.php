<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';

header('Content-Type: text/plain; charset=UTF-8');

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : (isset($name) ? trim((string)$name) : '');
$content = isset($_POST['content']) ? (string)$_POST['content'] : (isset($content) ? (string)$content : '');

if ($name === '') {
    echo '0';
    exit;
}

global $conn;
if (!($conn instanceof mysqli)) {
    echo '0';
    exit;
}

// 테이블 없으면 생성
@mysqli_query($conn, "
  CREATE TABLE IF NOT EXISTS tb_danger_member (
    idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(32) NOT NULL DEFAULT '',
    profile MEDIUMTEXT NULL,
    regdate DATETIME NOT NULL,
    PRIMARY KEY (idx),
    KEY ix_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$name_esc = mysqli_real_escape_string($conn, $name);
$content_esc = mysqli_real_escape_string($conn, $content);

$sql = "
  INSERT INTO tb_danger_member
    SET name = '{$name_esc}',
        profile = '{$content_esc}',
        regdate = NOW()
";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo '1';
    exit;
}

// 실패 시에도 프론트에서 보이게
$err = trim((string)mysqli_error($conn));
echo '0' . ($err !== '' ? '|' . $err : '');
