<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/card_price_buy_gate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/page/card_price.php');
}

$t = trim((string) ($_POST['t'] ?? ''));
if ($t === '') {
    alert_goto('요청 정보가 없습니다.', '/page/card_price.php');
}

$jump_flow = (string) ($_POST['jump_flow'] ?? '') === '1';
$force_buy = (string) ($_POST['sold_out_force'] ?? '') === '1';

$return_after_login = $jump_flow
    ? '/page/card_price_buy_jump.php?t=' . rawurlencode($t)
    : '/page/card_price_buy.php?t=' . rawurlencode($t);

$balance_fail = $jump_flow
    ? '/page/card_price_buy_jump.php?t=' . rawurlencode($t) . '&charge_err=1'
    : null;

$res = card_price_buy_try_purchase($t, $force_buy, $balance_fail);

if (!empty($res['need_login'])) {
    header('Location: /login.php?return=' . urlencode($return_after_login));
    exit;
}

if (empty($res['ok'])) {
    $a = $res['alert'] ?? ['msg' => '처리 중 오류가 발생했습니다.', 'url' => '/page/card_price.php'];
    $msg = isset($a['msg']) ? (string) $a['msg'] : '처리 중 오류가 발생했습니다.';
    $redir = array_key_exists('url', $a)
        ? ($a['url'] === null ? '' : (string) $a['url'])
        : '/page/card_price.php';
    alert_goto($msg, $redir);
}

header('Location: ' . ($res['dest'] ?? ''));
exit;
