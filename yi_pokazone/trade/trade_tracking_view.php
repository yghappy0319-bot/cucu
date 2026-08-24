<?php
/**
 * 배송조회 템플릿 (구매자·판매자 전용, 새 창)
 */
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_trade_payment.php';
require_once __DIR__ . '/../lib/_sweettracker.php';

$me = login_member();
$pay_idx = (int) ($_GET['pay_idx'] ?? 0);
$return_ship = '/trade/trade_payment_ship.php?pay_idx=' . $pay_idx;

if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode($return_ship));
}
if ($pay_idx < 1) {
    alert_goto('잘못된 접근입니다.', '/trade/trade_messages.php');
}
if (!sweettracker_ready()) {
    alert_goto('배송조회 API가 설정되지 않았습니다.', $return_ship);
}

$access = trade_payment_ship_page_access($pay_idx, (int) $me['mb_idx']);
if (empty($access['ok'])) {
    alert_goto((string) ($access['error'] ?? '접근할 수 없습니다.'), '/trade/trade_messages.php');
}

$row = (array) ($access['row'] ?? []);
if (!trade_payment_has_tracking($row)) {
    alert_goto('등록된 운송장이 없습니다.', $return_ship);
}

$code = trim((string) ($row['pay_courier_code'] ?? ''));
if ($code === '') {
    $code = sweettracker_resolve_company_code((string) ($row['pay_courier_name'] ?? ''));
}
if ($code === '') {
    alert_goto('택배사 코드를 확인할 수 없습니다. 택배사를 다시 선택해 주세요.', $return_ship);
}

$invoice = preg_replace('/\s+/', '', (string) ($row['pay_tracking_no'] ?? ''));
$api_key = sweettracker_api_key();
$template = sweettracker_template_id();
$action = 'https://info.sweettracker.co.kr/tracking/' . $template;

$page = 'trade_payment_ship';
$title = '배송조회';
$meta_noindex = true;
include __DIR__ . '/../include/header.php';
?>
<section class="sweettracker-redirect">
    <div class="container">
        <p class="sweettracker-redirect__msg">배송조회 페이지로 이동합니다…</p>
        <form id="sweettracker-redirect-form" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <input type="hidden" name="t_key" value="<?php echo htmlspecialchars($api_key, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="t_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="t_invoice" value="<?php echo htmlspecialchars($invoice, ENT_QUOTES, 'UTF-8'); ?>">
            <noscript>
                <button type="submit" class="btn btn-primary">배송조회 계속</button>
            </noscript>
        </form>
    </div>
</section>
<script>
(function () {
    var f = document.getElementById('sweettracker-redirect-form');
    if (f) {
        f.submit();
    }
})();
</script>
<?php include __DIR__ . '/../include/footer.php'; ?>
