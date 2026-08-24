<?php
/**
 * 거래글 등록 — 희망가 추천 (판매완료 데이터) JSON API
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/lib/_trade_price_suggest.php';

header('Content-Type: application/json; charset=UTF-8');

$me = login_member();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    echo json_encode(['ok' => false, 'error' => '지원하지 않는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = trade_price_suggest_lookup([
    'card_name'       => $_GET['card_name'] ?? '',
    'set_name'        => $_GET['set_name'] ?? '',
    'card_number'     => $_GET['card_number'] ?? '',
    'language'        => $_GET['language'] ?? '',
    'grade'           => $_GET['grade'] ?? '',
    'condition'       => $_GET['condition'] ?? '',
    'card_kind'       => $_GET['card_kind'] ?? 'single',
    'grading_company' => $_GET['grading_company'] ?? '',
    'grading_score'   => $_GET['grading_score'] ?? '',
]);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
