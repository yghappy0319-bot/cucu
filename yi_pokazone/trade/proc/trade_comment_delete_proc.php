<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_comment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$me = login_member();
$ad = login_admin();

$tc_idx = (int) ($_POST['tc_idx'] ?? 0);
$return = trim((string) ($_POST['return'] ?? ''));

$actor_mb = $me ? (int) $me['mb_idx'] : 0;
$is_mod   = ($me && (int) $me['mb_level'] >= 9) || (bool) $ad;

$result = trade_comment_delete($tc_idx, $actor_mb, $is_mod);
if (empty($result['ok'])) {
    $fallback = !empty($result['tr_idx'])
        ? '/trade/trade_view.php?idx=' . (int) $result['tr_idx']
        : '/trade/trade.php';
    alert_goto((string) ($result['error'] ?? '삭제에 실패했습니다.'), $fallback);
}

$tr_idx = (int) ($result['tr_idx'] ?? 0);
$redir  = '/trade/trade_view.php?idx=' . $tr_idx . '#comments';
if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#]*$#', $return)) {
    $redir = $return . (strpos($return, '#') !== false ? '' : '#comments');
}

header('Location: ' . $redir);
exit;
