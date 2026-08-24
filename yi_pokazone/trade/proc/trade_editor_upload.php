<?php
define('PZ_API_JSON', true);

require_once __DIR__ . '/../../lib/_function.php';
require_once __DIR__ . '/../../lib/_upload.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => '잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!login_member()) {
    echo json_encode(['ok' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$r = community_upload_editor_image($_FILES['image'] ?? null);
if (!$r['ok']) {
    echo json_encode(['ok' => false, 'error' => $r['error']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'url' => $r['url']], JSON_UNESCAPED_UNICODE);
