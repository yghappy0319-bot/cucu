<?php
/**
 * 거래게시판 목록 — 무한 스크롤용 JSON API
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_public.php';
require_once __DIR__ . '/lib/_trade_board.php';

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    echo json_encode(['ok' => false, 'error' => '지원하지 않는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filters = trade_board_parse_filters($_GET);
$me = login_member();

if (!empty($filters['mine_only']) && !$me) {
    echo json_encode([
        'ok'    => false,
        'login' => true,
        'error' => '로그인이 필요합니다.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$page_no = max(1, (int) ($_GET['p'] ?? 1));
$result = trade_board_fetch_page($filters, $page_no, $me);

$has_wish = db_table_exists('tb_trade_like');
$user_wishes = ($has_wish && $me)
    ? trade_user_wished_map(array_column($result['rows'], 'tr_idx'), (int) $me['mb_idx'])
    : [];

$html = trade_board_render_items_html($result['rows'], $has_wish, $me, $user_wishes);

echo json_encode([
    'ok'         => true,
    'page'       => $result['page_no'],
    'total'      => $result['total'],
    'total_page' => $result['total_page'],
    'has_more'   => $result['has_more'],
    'html'       => $html,
], JSON_UNESCAPED_UNICODE);
