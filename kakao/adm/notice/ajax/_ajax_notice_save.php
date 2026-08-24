<?php
include_once __DIR__ . '/../../../common.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['midx']) || (int)$_SESSION['midx'] <= 0) {
    die('로그인이 필요합니다.');
}

$member = 로그인정보($_SESSION['midx']);
if (!isset($member['mb_no'])) {
    die('회원 정보를 확인할 수 없습니다.');
}

if ($member['mb_id'] !== 'admin') {
    die('저장 권한이 없습니다.');
}

global $conn;

$save_action = isset($_POST['save_action']) ? $_POST['save_action'] : 'insert';
$idx = isset($_POST['idx']) ? (int)$_POST['idx'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$is_pinned = isset($_POST['is_pinned']) ? (int)$_POST['is_pinned'] : 0;
$is_visible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;

if ($is_pinned !== 1) {
    $is_pinned = 0;
}
if ($is_visible !== 0) {
    $is_visible = 1;
}

if ($title === '') {
    die('제목을 입력해 주세요.');
}
if (mb_strlen($title, 'UTF-8') > 255) {
    die('제목은 255자 이내로 입력해 주세요.');
}
if ($content === '') {
    die('내용을 입력해 주세요.');
}

$title_esc = mysqli_real_escape_string($conn, $title);
$content_esc = mysqli_real_escape_string($conn, $content);
$writer_esc = mysqli_real_escape_string($conn, $member['mb_id']);

if ($save_action === 'update' && $idx > 0) {
    $row = db_select("select idx from tb_notice where idx = {$idx} limit 1 ");
    if (!$row || !isset($row['idx'])) {
        die('데이터를 찾을 수 없습니다.');
    }

    $sql = "update tb_notice set ";
    $sql .= "title = '{$title_esc}', ";
    $sql .= "content = '{$content_esc}', ";
    $sql .= "is_pinned = {$is_pinned}, ";
    $sql .= "is_visible = {$is_visible}, ";
    $sql .= "moddate = now() ";
    $sql .= "where idx = {$idx} limit 1";
} else {
    $sql = "insert into tb_notice set ";
    $sql .= "title = '{$title_esc}', ";
    $sql .= "content = '{$content_esc}', ";
    $sql .= "is_pinned = {$is_pinned}, ";
    $sql .= "is_visible = {$is_visible}, ";
    $sql .= "writer_id = '{$writer_esc}', ";
    $sql .= "regdate = now() ";
}

$result = db_query($sql);
if ($result) {
    echo '1';
} else {
    $err = mysqli_error($conn);
    echo '저장에 실패했습니다.' . ($err !== '' ? ' ' . $err : '');
}
