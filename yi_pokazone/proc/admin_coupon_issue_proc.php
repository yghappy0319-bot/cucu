<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_coupon.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/coupons.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

$result = coupon_admin_issue($_POST);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '쿠폰 발행에 실패했습니다.'), '/admin/coupon_issue.php');
}

$count = (int) ($result['issued'] ?? 0);
alert_goto(
    '쿠폰이 ' . number_format($count) . '명에게 발행되었습니다.',
    '/admin/coupons.php?issued=1&count=' . $count
);
