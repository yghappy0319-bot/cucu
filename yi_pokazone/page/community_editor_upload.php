<?php
/**
 * 커뮤니티 Summernote 이미지 업로드 (JSON)
 */
define('PZ_API_JSON', true);
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_upload.php';

header('Content-Type: application/json; charset=UTF-8');

function community_editor_json_exit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    community_editor_json_exit(['ok' => false, 'message' => '잘못된 요청입니다.']);
}

$me = login_member();
if (! $me) {
    community_editor_json_exit(['ok' => false, 'message' => '로그인이 필요합니다.']);
}

$file = null;
if (! empty($_FILES['file']) && is_array($_FILES['file'])) {
    $file = $_FILES['file'];
} elseif (! empty($_FILES['image']) && is_array($_FILES['image'])) {
    $file = $_FILES['image'];
}

$up = community_upload_editor_image($file);
if (! $up['ok']) {
    community_editor_json_exit(['ok' => false, 'message' => $up['error'] !== '' ? $up['error'] : '업로드에 실패했습니다.']);
}

$url = public_url($up['path']);
community_editor_json_exit(['ok' => true, 'url' => $url]);
