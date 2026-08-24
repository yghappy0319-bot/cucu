<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') alert_goto('잘못된 접근입니다.', '/page/partner.php');

$allowed_type = ['general', 'ad', 'shop', 'creator', 'media'];

$type     = trim($_POST['pt_type']    ?? 'general');
$company  = trim($_POST['pt_company'] ?? '');
$manager  = trim($_POST['pt_manager'] ?? '');
$phone    = trim($_POST['pt_phone']   ?? '');
$email    = trim($_POST['pt_email']   ?? '');
$title    = trim($_POST['pt_title']   ?? '');
$content  = (string)($_POST['pt_content'] ?? '');
$agree    = !empty($_POST['agree']);

if (!in_array($type, $allowed_type, true))            alert_goto('제휴 유형이 올바르지 않습니다.');
if ($company === '' || mb_strlen($company) > 100)     alert_goto('회사/단체명을 1~100자 이내로 입력해 주세요.');
if ($manager === '' || mb_strlen($manager) > 30)      alert_goto('담당자명을 1~30자 이내로 입력해 주세요.');
if ($phone === ''   || mb_strlen($phone)   > 30)      alert_goto('연락처를 1~30자 이내로 입력해 주세요.');
if (!preg_match('/^[0-9+\-\s()]{7,}$/', $phone))      alert_goto('올바른 연락처를 입력해 주세요.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)
    alert_goto('올바른 이메일을 입력해 주세요.');
if ($title === '' || mb_strlen($title) > 200)         alert_goto('제목을 1~200자 이내로 입력해 주세요.');
if (trim($content) === '')                            alert_goto('제안 내용을 입력해 주세요.');
if (mb_strlen($content) > 20000)                      alert_goto('제안 내용은 20,000자 이내로 작성해 주세요.');
if (!$agree)                                          alert_goto('개인정보 수집 및 이용에 동의해 주세요.');

$esc_type    = db_escape($type);
$esc_company = db_escape($company);
$esc_manager = db_escape($manager);
$esc_phone   = db_escape($phone);
$esc_email   = db_escape($email);
$esc_title   = db_escape($title);
$esc_content = db_escape($content);
$esc_ip      = db_escape(get_client_ip());

$sql = "
    INSERT INTO tb_partner
        (pt_type, pt_company, pt_manager, pt_phone, pt_email, pt_title, pt_content,
         pt_status, pt_ip, pt_created_at)
    VALUES
        ('{$esc_type}', '{$esc_company}', '{$esc_manager}', '{$esc_phone}', '{$esc_email}',
         '{$esc_title}', '{$esc_content}', 1, '{$esc_ip}', NOW())
";
if (!db_query($sql)) alert_goto('제안 접수 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');

alert_goto('제안이 정상적으로 접수되었습니다. 영업일 기준 3일 이내 회신드리겠습니다.', '/');
