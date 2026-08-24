<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_public.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/member_shop_settings.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/member_shop_settings.php'));
}

$mb_idx = (int) $me['mb_idx'];
$raw    = (string) ($_POST['mb_shop_intro'] ?? '');
$result = member_shop_intro_save($mb_idx, $raw);

if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '저장에 실패했습니다.'), '/page/member_shop_settings.php');
}

alert_goto('상점 소개를 저장했습니다.', '/page/member_shop_settings.php');
