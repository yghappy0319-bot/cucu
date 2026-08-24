<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';
require_once __DIR__ . '/../lib/_telegram.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') alert_goto('잘못된 접근입니다.', '/page/inquiry.php');

$me = login_member();
if (!$me) alert_goto('로그인이 필요합니다.', '/login.php');

$mode = $_POST['mode'] ?? 'insert';

$allowed_cat = ['account', 'trade', 'community', 'payment', 'report', 'etc'];
$cat_labels  = [
    'account'   => '계정/회원',
    'trade'     => '거래',
    'community' => '커뮤니티',
    'payment'   => '결제/환불',
    'report'    => '신고/사기',
    'etc'       => '기타',
];

if ($mode === 'answer') {
    // 관리자 답변
    if ((int)$me['mb_level'] < 9) alert_goto('관리자만 답변을 작성할 수 있습니다.', '/page/inquiry.php');

    $idx     = (int)($_POST['idx'] ?? 0);
    $status  = (int)($_POST['iq_status'] ?? 1);
    $answer  = trim($_POST['iq_answer'] ?? '');

    if ($idx <= 0)                       alert_goto('잘못된 접근입니다.', '/page/inquiry.php');
    if (!in_array($status, [1,2,3], true)) $status = 1;
    if ($answer === '')                  alert_goto('답변 내용을 입력해 주세요.');
    if (mb_strlen($answer) > 20000)      alert_goto('답변은 20,000자 이내로 작성해 주세요.');

    $rs = db_query("SELECT iq_idx FROM tb_inquiry WHERE iq_idx = {$idx} AND iq_status <> 9 LIMIT 1");
    if (!db_assoc($rs)) alert_goto('존재하지 않는 문의입니다.', '/page/inquiry.php');

    $esc_ans = db_escape($answer);
    $sql = "
        UPDATE tb_inquiry SET
            iq_answer      = '{$esc_ans}',
            iq_status      = {$status},
            iq_answered_at = NOW(),
            iq_updated_at  = NOW()
        WHERE iq_idx = {$idx}
    ";
    if (!db_query($sql)) alert_goto('답변 저장 중 오류가 발생했습니다.');
    alert_goto('답변이 저장되었습니다.', '/page/inquiry_view.php?idx=' . $idx);
}

// 신규 문의
$category = trim($_POST['iq_category'] ?? 'etc');
$name     = trim($_POST['iq_name']     ?? '');
$email    = trim($_POST['iq_email']    ?? '');
$title    = trim($_POST['iq_title']    ?? '');
$content  = (string)($_POST['iq_content'] ?? '');

if (!in_array($category, $allowed_cat, true))      alert_goto('문의 유형이 올바르지 않습니다.');
if ($name === '' || mb_strlen($name) > 30)         alert_goto('이름/닉네임을 1~30자 이내로 입력해 주세요.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    alert_goto('올바른 이메일을 입력해 주세요.');
if (mb_strlen($email) > 100)                       alert_goto('이메일이 너무 깁니다.');
if ($title === '' || mb_strlen($title) > 200)      alert_goto('제목을 1~200자 이내로 입력해 주세요.');
if (trim($content) === '')                         alert_goto('내용을 입력해 주세요.');
if (mb_strlen($content) > 20000)                   alert_goto('내용은 20,000자 이내로 작성해 주세요.');

$upload_result = inquiry_upload_files($_FILES['iq_files'] ?? null);
if (!$upload_result['ok']) {
    alert_goto($upload_result['error']);
}
$new_files = $upload_result['items'];
$rollback_files = function () use (&$new_files) {
    if (!empty($new_files)) {
        inquiry_remove_files(array_column($new_files, 'path'));
    }
};

if (!empty($new_files) && !db_table_exists('tb_inquiry_file')) {
    $rollback_files();
    alert_goto('첨부파일 기능을 사용하려면 DB에 sql/migrate_tb_inquiry_file.sql 을 적용해 주세요.');
}

$esc_cat     = db_escape($category);
$esc_name    = db_escape($name);
$esc_email   = db_escape($email);
$esc_title   = db_escape($title);
$esc_content = db_escape($content);
$esc_ip      = db_escape(get_client_ip());
$mb_idx      = (int)$me['mb_idx'];

$sql = "
    INSERT INTO tb_inquiry
        (mb_idx, iq_category, iq_name, iq_email, iq_title, iq_content,
         iq_is_private, iq_status, iq_ip, iq_created_at, iq_updated_at)
    VALUES
        ({$mb_idx}, '{$esc_cat}', '{$esc_name}', '{$esc_email}', '{$esc_title}', '{$esc_content}',
         1, 1, '{$esc_ip}', NOW(), NOW())
";
if (!db_query($sql)) {
    $rollback_files();
    alert_goto('문의 접수 중 오류가 발생했습니다.');
}
$new_idx = (int)db_insert_id();

if (!empty($new_files) && db_table_exists('tb_inquiry_file')) {
    foreach ($new_files as $ord => $file) {
        $sql_file = "
            INSERT INTO tb_inquiry_file
                (iq_idx, if_path, if_orig_name, if_size, if_mime, if_order, if_created_at)
            VALUES
                ({$new_idx},
                 '" . db_escape($file['path']) . "',
                 '" . db_escape($file['orig_name']) . "',
                 " . (int)$file['size'] . ",
                 '" . db_escape($file['mime']) . "',
                 {$ord},
                 NOW())
        ";
        if (!db_query($sql_file)) {
            db_query("DELETE FROM tb_inquiry_file WHERE iq_idx = {$new_idx}");
            $rollback_files();
            db_query("UPDATE tb_inquiry SET iq_status = 9 WHERE iq_idx = {$new_idx}");
            alert_goto('첨부파일 저장 중 오류가 발생했습니다.');
        }
    }
}

telegram_notify_inquiry([
    'iq_idx'          => $new_idx,
    'category'        => $category,
    'category_label'  => $cat_labels[$category] ?? $category,
    'name'            => $name,
    'email'           => $email,
    'title'           => $title,
    'content'         => $content,
    'mb_idx'          => $mb_idx,
]);

alert_goto('문의가 접수되었습니다. 영업일 기준 24시간 이내에 답변드리겠습니다.', '/page/inquiry_view.php?idx=' . $new_idx);
