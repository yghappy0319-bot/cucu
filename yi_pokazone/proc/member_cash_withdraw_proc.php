<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';
require_once __DIR__ . '/../lib/_telegram.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_withdraw.php'));
}

$mb_idx  = (int) $me['mb_idx'];
$amount  = (int) preg_replace('/\D/', '', (string) ($_POST['cw_amount'] ?? ''));
$pay_idx = (int) ($_POST['pay_idx'] ?? 0);

$return = '/page/mypage_cash_withdraw.php';
if ($pay_idx > 0) {
    $return .= '?pay_idx=' . $pay_idx;
}

$result = member_cash_withdraw_apply($mb_idx, $amount, ['pay_idx' => $pay_idx]);
if (!$result['ok']) {
    alert_goto($result['error'] ?? '출금 신청에 실패했습니다.', $return);
}

$cw_idx = (int) ($result['cw_idx'] ?? 0);
$gross  = (int) ($result['gross'] ?? $amount);
$net    = (int) ($result['net'] ?? $gross);

$bank = $holder = $account = '';
if ($cw_idx > 0 && member_cash_withdraw_table_ready()) {
    $wrow = db_assoc(db_query("
        SELECT cw_bank, cw_holder, cw_account
        FROM tb_cash_withdraw
        WHERE cw_idx = {$cw_idx}
        LIMIT 1
    "));
    if ($wrow) {
        $bank    = (string) ($wrow['cw_bank'] ?? '');
        $holder  = (string) ($wrow['cw_holder'] ?? '');
        $account = (string) ($wrow['cw_account'] ?? '');
    }
}

telegram_notify_cash_withdraw([
    'cw_idx'  => $cw_idx,
    'mb_idx'  => $mb_idx,
    'mb_id'   => (string) ($me['mb_id'] ?? ''),
    'mb_nick' => (string) ($me['mb_nick'] ?? ''),
    'gross'   => $gross,
    'net'     => $net,
    'bank'    => $bank,
    'holder'  => $holder,
    'account' => $account,
]);

$msg = '출금 신청이 접수되었습니다.'
    . "\n출금액 ₩" . number_format($net)
    . "\n영업일 기준 1~3일 내 등록된 정산계좌로 입금됩니다.";
alert_goto($msg, '/page/mypage_cash_withdraw.php');
