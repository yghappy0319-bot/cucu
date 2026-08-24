<?php
require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../lib/_trade_comment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$me = login_member();
if (!$me) {
    alert_goto('로그인 후 댓글을 작성할 수 있습니다.', '/login.php');
}

$tr_idx  = (int) ($_POST['tr_idx'] ?? 0);
$parent  = (int) ($_POST['tc_parent_idx'] ?? 0);
$content = (string) ($_POST['tc_content'] ?? '');
$return  = trim((string) ($_POST['return'] ?? ''));

$view_url = '/trade/trade_view.php?idx=' . max(1, $tr_idx);

$result = trade_comment_submit($tr_idx, (int) $me['mb_idx'], $content, $parent);
if (empty($result['ok'])) {
    alert_goto((string) ($result['error'] ?? '등록에 실패했습니다.'), $view_url);
}

$redir = $view_url . '#comments';
if ($return !== '' && preg_match('#^/[a-zA-Z0-9_./?=&\-#]*$#', $return)) {
    $redir = $return . (strpos($return, '#') !== false ? '' : '#comments');
}

$is_reply = !empty($result['is_reply']);
$charged  = (int) ($result['charged'] ?? 0);
$msg = $is_reply ? '답글이 등록되었습니다.' : '댓글이 등록되었습니다.';
if ($charged > 0) {
    $msg .= ' ' . number_format($charged) . 'P가 차감되었습니다.';
}
alert_goto($msg, $redir);
