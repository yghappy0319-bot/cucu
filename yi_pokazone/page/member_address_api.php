<?php
/**
 * 회원 배송지 주소록 JSON API
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_address.php';

header('Content-Type: application/json; charset=UTF-8');

function member_address_api_exit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$me = login_member();
if (!$me) {
    member_address_api_exit(['ok' => false, 'error' => '로그인이 필요합니다.']);
}

$mb_idx = (int) $me['mb_idx'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!member_address_table_ready()) {
    member_address_api_exit([
        'ok'    => false,
        'error' => '배송지 테이블이 없습니다. sql/tb_member_address.sql 을 적용해 주세요.',
    ]);
}

if ($method === 'GET') {
    $rows = member_address_list($mb_idx);
    $out  = [];
    foreach ($rows as $row) {
        $out[] = member_address_row_to_json($row);
    }
    member_address_api_exit([
        'ok'       => true,
        'items'    => $out,
        'max'      => MEMBER_ADDRESS_MAX,
        'count'    => count($out),
    ]);
}

if ($method !== 'POST') {
    member_address_api_exit(['ok' => false, 'error' => '지원하지 않는 요청입니다.']);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw !== false ? $raw : '', true);
if (!is_array($in)) {
    $in = $_POST;
}

$action = trim((string) ($in['action'] ?? ''));

if ($action === 'save') {
    $addr_idx = (int) ($in['addr_idx'] ?? 0);
    $result   = member_address_save($mb_idx, $in, $addr_idx);
    if (!$result['ok']) {
        member_address_api_exit($result);
    }
    $saved = member_address_get($mb_idx, (int) $result['addr_idx']);
    member_address_api_exit([
        'ok'   => true,
        'item' => $saved ? member_address_row_to_json($saved) : null,
    ]);
}

if ($action === 'delete') {
    $addr_idx = (int) ($in['addr_idx'] ?? 0);
    $result   = member_address_delete($mb_idx, $addr_idx);
    member_address_api_exit($result);
}

if ($action === 'set_default') {
    $addr_idx = (int) ($in['addr_idx'] ?? 0);
    $result   = member_address_set_default($mb_idx, $addr_idx);
    member_address_api_exit($result);
}

member_address_api_exit(['ok' => false, 'error' => '알 수 없는 action 입니다.']);
