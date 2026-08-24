<?php
require_once __DIR__ . '/../lib/_function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/admin/settings.php');
}

$ad = login_admin();
if (!$ad) {
    alert_goto('로그인이 필요합니다.', '/admin/login.php');
}

if (!site_setting_table_ready()) {
    alert_goto('tb_site_setting 테이블을 먼저 생성해 주세요. (sql/tb_site_setting.sql)', '/admin/settings.php');
}

$site_name        = trim((string) ($_POST['site_name'] ?? ''));
$site_tagline     = trim((string) ($_POST['site_tagline'] ?? ''));
$contact_email    = trim((string) ($_POST['contact_email'] ?? ''));
$footer_notice    = trim((string) ($_POST['footer_notice'] ?? ''));
$trade_fee        = (int) ($_POST['trade_platform_fee_percent'] ?? 5);
$auction_fee      = (int) ($_POST['auction_platform_fee_percent'] ?? 5);

if ($site_name === '' || mb_strlen($site_name) > 60) {
    alert_goto('사이트 이름을 1~60자로 입력해 주세요.', '/admin/settings.php');
}
if ($trade_fee < 0 || $trade_fee > 100) {
    alert_goto('거래게시판 수수료는 0~100% 사이로 입력해 주세요.', '/admin/settings.php');
}
if ($auction_fee < 0 || $auction_fee > 100) {
    alert_goto('경매 수수료는 0~100% 사이로 입력해 주세요.', '/admin/settings.php');
}

site_setting_set('site_name', $site_name);
site_setting_set('site_tagline', mb_substr($site_tagline, 0, 200));
site_setting_set('contact_email', mb_substr($contact_email, 0, 100));
site_setting_set('footer_notice', mb_substr($footer_notice, 0, 500));
site_setting_set('trade_platform_fee_percent', (string) $trade_fee);
site_setting_set('auction_platform_fee_percent', (string) $auction_fee);

alert_goto('설정을 저장했습니다.', '/admin/settings.php');
