<?php
/**
 * 결제 모듈·DB 상태 점검 (JSON)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';

header('Content-Type: application/json; charset=UTF-8');

$me = login_member();
if (!$me) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = __DIR__ . '/lib/_trade_payment.php';
$lib_info = trade_chat_payment_lib_file_info($path);
$status   = trade_chat_payment_status();

$lib_build = (string) ($lib_info['build'] ?? '');
$lib_md5   = (string) ($lib_info['md5'] ?? '');

$pay_cols = trade_chat_payment_table_columns_status();

echo json_encode([
    'ok'    => !empty($status['ready']),
    'check' => [
        'php_version'        => PHP_VERSION,
        'payment_table'      => db_table_exists('tb_trade_payment'),
        'payment_msg_cols'   => trade_chat_msg_has_payment_columns(),
        'payment_table_cols' => !empty($pay_cols['ok']),
        'payment_table_missing_cols' => array_values((array) ($pay_cols['missing'] ?? [])),
        'db_ready'           => !empty($status['db_ready']),
        'lib_ok'             => !empty($status['lib_ok']),
        'payment_ready'      => !empty($status['ready']),
        'lib_file_exists'    => is_file($path),
        'lib_file_size'      => is_file($path) ? (int) filesize($path) : 0,
        'lib_file_mtime'     => is_file($path) ? (int) filemtime($path) : 0,
        'lib_file_md5'       => $lib_md5,
        'lib_build'          => $lib_build,
        'lib_build_expected' => '20260624l-php74',
        'lib_webhook_sig'    => (string) ($lib_info['webhook_sig'] ?? ''),
        'lib_webhook_line'   => (int) ($lib_info['webhook_sig_line'] ?? 0),
        'lib_inspect_issue'  => (string) ($lib_info['issue'] ?? ''),
        'lib_file_readable'  => is_readable($path),
        'has_bank_info_fn'   => function_exists('trade_payment_bank_info'),
        'has_tables_ready_fn'=> function_exists('trade_payment_tables_ready'),
        'payment_chat_only'  => !empty($status['chat_only']),
        'payment_sync_to_db' => !empty($status['sync_to_db']),
    ],
    'error' => (string) ($status['error'] ?? ''),
    'lib_fail' => trade_chat_payment_lib_fail_reason(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
