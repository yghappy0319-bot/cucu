<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/webpush.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/push_broadcast.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!webpush_is_ready()) {
    alert_goto('웹 푸시가 설정되지 않았습니다. 전체 푸시 화면의 안내를 확인해 주세요.', '/admin/push_broadcast.php');
}

$pb_title = trim((string) ($_POST['pb_title'] ?? ''));
$pb_body  = trim((string) ($_POST['pb_body'] ?? ''));
$pb_url   = trim((string) ($_POST['pb_url'] ?? ''));

if ($pb_title === '' || mb_strlen($pb_title) > 120) {
    alert_goto('제목을 1~120자로 입력해 주세요.', '/admin/push_broadcast.php');
}
if ($pb_body === '' || mb_strlen($pb_body) > 500) {
    alert_goto('내용을 1~500자로 입력해 주세요.', '/admin/push_broadcast.php');
}

if (webpush_subscription_total() < 1) {
    alert_goto('저장된 푸시 구독이 없습니다.', '/admin/push_broadcast.php');
}

$open_path = $pb_url !== '' ? $pb_url : '/';
$open_url  = seo_abs_url($open_path);
if ($open_url === '') {
    alert_goto('이동 URL 을 해석할 수 없습니다. / 부터 시작하는 경로 또는 https:// 주소를 입력해 주세요.', '/admin/push_broadcast.php');
}

$notify_tag = 'pz-bc-' . str_replace('.', '', uniqid('', true));

$payload = json_encode([
    'title' => $pb_title,
    'body'  => $pb_body,
    'url'   => $open_url,
    'tag'   => $notify_tag,
], JSON_UNESCAPED_UNICODE);

if ($payload === false) {
    alert_goto('메시지 인코딩에 실패했습니다.', '/admin/push_broadcast.php');
}

@set_time_limit(600);

$stats = [];
$ok    = webpush_deliver_payload_broadcast($payload, $stats);

$att = (int) ($stats['attempted'] ?? 0);
$suc = (int) ($stats['success'] ?? 0);
$fail = (int) ($stats['failed'] ?? 0);

if (!$ok || $att < 1) {
    alert_goto('전송에 실패했거나 대상이 없었습니다. 구독·VAPID·서버 아웃바운드를 확인해 주세요.', '/admin/push_broadcast.php');
}

$msg = sprintf('전체 푸시를 보냈습니다. 시도 %d건, 성공 %d건, 실패 %d건.', $att, $suc, $fail);
alert_goto($msg, '/admin/push_broadcast.php');
