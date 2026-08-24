<?php
define('PZ_API_JSON', true);

require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash_charge.php';

header('Content-Type: application/json; charset=UTF-8');

$json = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$me = login_member();
if (!$me) {
    $json(['ok' => false, 'error' => '로그인이 필요합니다.'], 401);
}

$mb_idx = (int) $me['mb_idx'];
$raw    = trim((string) ($_GET['ids'] ?? ''));
$ids    = [];
if ($raw !== '') {
    foreach (explode(',', $raw) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
}

$result = member_cash_charge_status_for_member($mb_idx, $ids);
if (empty($result['ok'])) {
    $json([
        'ok'    => false,
        'error' => (string) ($result['error'] ?? '상태 조회에 실패했습니다.'),
    ], 422);
}

$json([
    'ok'           => true,
    'cash_balance' => (int) ($result['cash_balance'] ?? 0),
    'items'        => $result['items'] ?? [],
]);
