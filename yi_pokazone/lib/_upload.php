<?php
/**
 * 이미지 업로드 헬퍼
 *  - 허용 확장자 : jpg / jpeg / png / gif / webp
 *  - 최대 파일크기 : 20MB
 *  - 저장 경로    : /uploads/trade/YYYY/MM/, /uploads/auction/YYYY/MM/ (S3 사용 시 버킷 동일 키)
 *  - 반환형태     : ['ok' => bool, 'items' => [ ['path', 'orig_name', 'size', 'mime'] ... ], 'error' => string]
 */

require_once __DIR__ . '/_s3.php';

const TR_UP_MAX_FILES   = 8;
const TR_UP_MAX_SIZE    = 20 * 1024 * 1024; // 20MB
const TR_UP_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const TR_UP_ALLOWED_MIME = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];
const TR_UP_BASE_DIR = 'uploads/trade'; // web root 기준
const AU_UP_BASE_DIR = 'uploads/auction';

const CM_UP_MAX_SIZE = 5 * 1024 * 1024;
const CM_UP_BASE_DIR = 'uploads/community';

const CP_UP_MAX_SIZE = 5 * 1024 * 1024;
const CP_UP_BASE_DIR = 'uploads/card_price';
const DR_UP_MAX_SIZE = 5 * 1024 * 1024;
const DR_UP_BASE_DIR = 'uploads/draw';
const PU_UP_MAX_SIZE = 5 * 1024 * 1024;
const PU_UP_BASE_DIR = 'uploads/popup';

const IQ_UP_MAX_FILES = 5;
const IQ_UP_BASE_DIR  = 'uploads/inquiry';

/**
 * 커뮤니티 에디터(Summernote 등) 단일 이미지 업로드
 *
 * @param array|null $file $_FILES['file'] 등 단일 업로드
 * @return array{ok: bool, path: string, error: string} path 는 DB 저장용 '/uploads/community/YYYY/MM/name.ext'
 */
function community_upload_editor_image(?array $file): array
{
    $fail = ['ok' => false, 'path' => '', 'error' => ''];

    if (empty($file) || ! isset($file['error'])) {
        $fail['error'] = '파일이 없습니다.';

        return $fail;
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        $fail['error'] = '파일을 선택해 주세요.';

        return $fail;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $fail['error'] = '파일 업로드 오류가 발생했습니다. (코드 ' . $err . ')';

        return $fail;
    }

    $name = (string) ($file['name'] ?? '');
    $tmp  = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > CM_UP_MAX_SIZE) {
        $fail['error'] = '파일 크기는 5MB 이하여야 합니다.';

        return $fail;
    }
    if (! is_uploaded_file($tmp)) {
        $fail['error'] = '잘못된 업로드 요청입니다.';

        return $fail;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (! in_array($ext, TR_UP_ALLOWED_EXT, true)) {
        $fail['error'] = '지원하지 않는 파일 형식입니다. (jpg/png/gif/webp 만 가능)';

        return $fail;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) @finfo_file($fi, $tmp);
            @finfo_close($fi);
        }
    }
    if ($mime === '') {
        $info = @getimagesize($tmp);
        if ($info && ! empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (! in_array($mime, TR_UP_ALLOWED_MIME, true)) {
        $fail['error'] = '이미지 파일만 업로드할 수 있습니다.';

        return $fail;
    }
    if (! @getimagesize($tmp)) {
        $fail['error'] = '손상되었거나 이미지가 아닌 파일입니다.';

        return $fail;
    }

    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        $fail['error'] = '서버 경로를 확인할 수 없습니다.';

        return $fail;
    }

    $sub_dir = date('Y') . '/' . date('m');
    $use_s3  = s3_should_use_for_base_dir(CM_UP_BASE_DIR);
    $abs_dir = $web_root . '/' . CM_UP_BASE_DIR . '/' . $sub_dir;
    if (!$use_s3 && ! is_dir($abs_dir)) {
        if (! @mkdir($abs_dir, 0775, true) && ! is_dir($abs_dir)) {
            $fail['error'] = '업로드 디렉터리 생성 실패';

            return $fail;
        }
    }

    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $rand = substr(md5(uniqid((string) mt_rand(), true)), 0, 16);
    }
    $new_name = date('YmdHis') . '_' . $rand . '.' . $ext;
    $abs_path = $abs_dir . '/' . $new_name;
    $web_path = '/' . CM_UP_BASE_DIR . '/' . $sub_dir . '/' . $new_name;

    if ($use_s3) {
        $up = s3_upload_file($tmp, $web_path, $mime);
        if (!$up['ok']) {
            $fail['error'] = $up['error'];

            return $fail;
        }
    } elseif (! @move_uploaded_file($tmp, $abs_path)) {
        $fail['error'] = '파일 저장 중 오류가 발생했습니다.';

        return $fail;
    } else {
        @chmod($abs_path, 0644);
    }

    return ['ok' => true, 'path' => $web_path, 'error' => ''];
}

/**
 * 카드 시세 상품 대표 이미지 (미선택 시 ok, path 빈 문자열)
 *
 * @param array|null $file $_FILES['cp_image']
 * @return array{ok: bool, path: string, error: string} path 는 DB 저장용 '/uploads/card_price/YYYY/MM/name.ext'
 */
function card_price_upload_product_image(?array $file): array
{
    $empty = ['ok' => true, 'path' => '', 'error' => ''];
    if (empty($file) || !isset($file['error'])) {
        return $empty;
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }

    $fail = ['ok' => false, 'path' => '', 'error' => ''];
    $err  = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $fail['error'] = '이미지 업로드 오류가 발생했습니다. (코드 ' . $err . ')';

        return $fail;
    }

    $name = (string) ($file['name'] ?? '');
    $tmp  = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > CP_UP_MAX_SIZE) {
        $fail['error'] = '이미지 파일은 5MB 이하여야 합니다.';

        return $fail;
    }
    if (!is_uploaded_file($tmp)) {
        $fail['error'] = '잘못된 업로드 요청입니다.';

        return $fail;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, TR_UP_ALLOWED_EXT, true)) {
        $fail['error'] = '지원하지 않는 파일 형식입니다. (jpg/png/gif/webp 만 가능)';

        return $fail;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) @finfo_file($fi, $tmp);
            @finfo_close($fi);
        }
    }
    if ($mime === '') {
        $info = @getimagesize($tmp);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (!in_array($mime, TR_UP_ALLOWED_MIME, true)) {
        $fail['error'] = '이미지 파일만 업로드할 수 있습니다.';

        return $fail;
    }
    if (!@getimagesize($tmp)) {
        $fail['error'] = '손상되었거나 이미지가 아닌 파일입니다.';

        return $fail;
    }

    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        $fail['error'] = '서버 경로를 확인할 수 없습니다.';

        return $fail;
    }

    $sub_dir = date('Y') . '/' . date('m');
    $abs_dir = $web_root . '/' . CP_UP_BASE_DIR . '/' . $sub_dir;
    if (!is_dir($abs_dir)) {
        if (!@mkdir($abs_dir, 0775, true) && !is_dir($abs_dir)) {
            $fail['error'] = '업로드 디렉터리 생성 실패';

            return $fail;
        }
    }

    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $rand = substr(md5(uniqid((string) mt_rand(), true)), 0, 16);
    }
    $new_name = date('YmdHis') . '_' . $rand . '.' . $ext;
    $abs_path = $abs_dir . '/' . $new_name;
    $web_path = '/' . CP_UP_BASE_DIR . '/' . $sub_dir . '/' . $new_name;

    if (!@move_uploaded_file($tmp, $abs_path)) {
        $fail['error'] = '파일 저장 중 오류가 발생했습니다.';

        return $fail;
    }
    @chmod($abs_path, 0644);

    return ['ok' => true, 'path' => $web_path, 'error' => ''];
}

/**
 * 레이어 팝업 이미지 업로드
 *
 * @param array|null $file $_FILES['pu_image_file']
 * @return array{ok: bool, path: string, error: string}
 */
function popup_upload_image(?array $file): array
{
    $empty = ['ok' => true, 'path' => '', 'error' => ''];
    if (empty($file) || !isset($file['error'])) {
        return $empty;
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }

    $fail = ['ok' => false, 'path' => '', 'error' => ''];
    $err  = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $fail['error'] = '이미지 업로드 오류가 발생했습니다. (코드 ' . $err . ')';
        return $fail;
    }

    $name = (string) ($file['name'] ?? '');
    $tmp  = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > PU_UP_MAX_SIZE) {
        $fail['error'] = '이미지 파일은 5MB 이하여야 합니다.';
        return $fail;
    }
    if (!is_uploaded_file($tmp)) {
        $fail['error'] = '잘못된 업로드 요청입니다.';
        return $fail;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, TR_UP_ALLOWED_EXT, true)) {
        $fail['error'] = '지원하지 않는 파일 형식입니다. (jpg/png/gif/webp 만 가능)';
        return $fail;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) @finfo_file($fi, $tmp);
            @finfo_close($fi);
        }
    }
    if ($mime === '') {
        $info = @getimagesize($tmp);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (!in_array($mime, TR_UP_ALLOWED_MIME, true)) {
        $fail['error'] = '이미지 파일만 업로드할 수 있습니다.';
        return $fail;
    }
    if (!@getimagesize($tmp)) {
        $fail['error'] = '손상되었거나 이미지가 아닌 파일입니다.';
        return $fail;
    }

    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        $fail['error'] = '서버 경로를 확인할 수 없습니다.';
        return $fail;
    }

    $sub_dir = date('Y') . '/' . date('m');
    $abs_dir = $web_root . '/' . PU_UP_BASE_DIR . '/' . $sub_dir;
    if (!is_dir($abs_dir)) {
        if (!@mkdir($abs_dir, 0775, true) && !is_dir($abs_dir)) {
            $fail['error'] = '업로드 디렉터리 생성 실패';
            return $fail;
        }
    }

    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $rand = substr(md5(uniqid((string) mt_rand(), true)), 0, 16);
    }
    $new_name = date('YmdHis') . '_' . $rand . '.' . $ext;
    $abs_path = $abs_dir . '/' . $new_name;
    $web_path = '/' . PU_UP_BASE_DIR . '/' . $sub_dir . '/' . $new_name;

    if (!@move_uploaded_file($tmp, $abs_path)) {
        $fail['error'] = '파일 저장 중 오류가 발생했습니다.';
        return $fail;
    }
    @chmod($abs_path, 0644);

    return ['ok' => true, 'path' => $web_path, 'error' => ''];
}

/**
 * 뽑기 상품 대표 이미지 (미선택 시 ok, path 빈 문자열)
 *
 * @param array|null $file $_FILES['dp_image']
 * @return array{ok: bool, path: string, error: string} path 는 DB 저장용 '/uploads/draw/YYYY/MM/name.ext'
 */
function draw_upload_product_image(?array $file): array
{
    $empty = ['ok' => true, 'path' => '', 'error' => ''];
    if (empty($file) || !isset($file['error'])) {
        return $empty;
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }

    $fail = ['ok' => false, 'path' => '', 'error' => ''];
    $err  = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        $fail['error'] = '이미지 업로드 오류가 발생했습니다. (코드 ' . $err . ')';
        return $fail;
    }

    $name = (string) ($file['name'] ?? '');
    $tmp  = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > DR_UP_MAX_SIZE) {
        $fail['error'] = '이미지 파일은 5MB 이하여야 합니다.';
        return $fail;
    }
    if (!is_uploaded_file($tmp)) {
        $fail['error'] = '잘못된 업로드 요청입니다.';
        return $fail;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, TR_UP_ALLOWED_EXT, true)) {
        $fail['error'] = '지원하지 않는 파일 형식입니다. (jpg/png/gif/webp 만 가능)';
        return $fail;
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string) @finfo_file($fi, $tmp);
            @finfo_close($fi);
        }
    }
    if ($mime === '') {
        $info = @getimagesize($tmp);
        if ($info && !empty($info['mime'])) {
            $mime = $info['mime'];
        }
    }
    if (!in_array($mime, TR_UP_ALLOWED_MIME, true)) {
        $fail['error'] = '이미지 파일만 업로드할 수 있습니다.';
        return $fail;
    }
    if (!@getimagesize($tmp)) {
        $fail['error'] = '손상되었거나 이미지가 아닌 파일입니다.';
        return $fail;
    }

    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        $fail['error'] = '서버 경로를 확인할 수 없습니다.';
        return $fail;
    }

    $sub_dir = date('Y') . '/' . date('m');
    $abs_dir = $web_root . '/' . DR_UP_BASE_DIR . '/' . $sub_dir;
    if (!is_dir($abs_dir)) {
        if (!@mkdir($abs_dir, 0775, true) && !is_dir($abs_dir)) {
            $fail['error'] = '업로드 디렉터리 생성 실패';
            return $fail;
        }
    }

    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $rand = substr(md5(uniqid((string) mt_rand(), true)), 0, 16);
    }
    $new_name = date('YmdHis') . '_' . $rand . '.' . $ext;
    $abs_path = $abs_dir . '/' . $new_name;
    $web_path = '/' . DR_UP_BASE_DIR . '/' . $sub_dir . '/' . $new_name;

    if (!@move_uploaded_file($tmp, $abs_path)) {
        $fail['error'] = '파일 저장 중 오류가 발생했습니다.';
        return $fail;
    }
    @chmod($abs_path, 0644);

    return ['ok' => true, 'path' => $web_path, 'error' => ''];
}

/**
 * 뽑기 상품 추가 카드 이미지 다중 업로드
 *
 * @param array|null $files_field $_FILES['dp_cards'] 형태
 * @return array{ok: bool, items: list<array{path: string, orig_name: string, size: int, mime: string}>, error: string}
 */
function draw_upload_product_cards($files_field): array
{
    return pz_upload_images_multi($files_field, DR_UP_BASE_DIR, 500);
}

/**
 * 단일/다중 $_FILES 필드를 다중 업로드 형식으로 통일
 *
 * @param array<string, mixed>|null $files_field
 * @return array<string, mixed>|null
 */
function pz_normalize_files_field($files_field) {
    if (empty($files_field) || !isset($files_field['name'])) {
        return null;
    }

    if (!is_array($files_field['name'])) {
        return [
            'name'     => [(string) $files_field['name']],
            'type'     => [(string) ($files_field['type'] ?? '')],
            'tmp_name' => [(string) ($files_field['tmp_name'] ?? '')],
            'error'    => [(int) ($files_field['error'] ?? UPLOAD_ERR_NO_FILE)],
            'size'     => [(int) ($files_field['size'] ?? 0)],
        ];
    }

    return $files_field;
}

/**
 * 다중 이미지 업로드 (공통)
 *
 * @param array  $files_field $_FILES['...'] 형태
 * @param string $base_dir    웹루트 기준 저장 디렉터리 (예: uploads/trade)
 * @param int    $max_files
 * @return array{ok: bool, items: list<array{path: string, orig_name: string, size: int, mime: string}>, error: string}
 */
function pz_upload_images_multi($files_field, string $base_dir, int $max_files = TR_UP_MAX_FILES) {
    $result = ['ok' => true, 'items' => [], 'error' => ''];

    $files_field = pz_normalize_files_field($files_field);
    if ($files_field === null) {
        return $result; // 첨부 없음 = 정상
    }

    $names = $files_field['name'];
    $count = count($names);

    if ($count > $max_files) {
        return ['ok' => false, 'items' => [], 'error' => '이미지는 최대 ' . $max_files . '장까지 업로드 가능합니다.'];
    }

    // 저장 디렉토리 생성 (로컬 저장 시에만)
    $web_root = realpath(__DIR__ . '/..');
    $sub_dir  = date('Y') . '/' . date('m');
    $use_s3   = s3_should_use_for_base_dir(trim($base_dir, '/'));
    $abs_dir  = $web_root . '/' . trim($base_dir, '/') . '/' . $sub_dir;
    if (!$use_s3) {
        if (!is_dir($abs_dir)) {
            if (!@mkdir($abs_dir, 0775, true) && !is_dir($abs_dir)) {
                return ['ok' => false, 'items' => [], 'error' => '업로드 디렉토리 생성 실패'];
            }
        }
    }

    for ($i = 0; $i < $count; $i++) {
        $err  = (int) ($files_field['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $name = (string) ($files_field['name'][$i] ?? '');
        $tmp  = (string) ($files_field['tmp_name'][$i] ?? '');
        $size = (int) ($files_field['size'][$i] ?? 0);

        if ($err === UPLOAD_ERR_NO_FILE || $name === '') continue;

        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'items' => [], 'error' => '파일 업로드 오류가 발생했습니다. (코드 ' . $err . ')'];
        }
        if ($size <= 0 || $size > TR_UP_MAX_SIZE) {
            $mb = (int) max(1, (int) round(TR_UP_MAX_SIZE / 1048576));
            return ['ok' => false, 'items' => [], 'error' => '파일 크기는 ' . $mb . 'MB 이하여야 합니다. (' . htmlspecialchars($name) . ')'];
        }
        if (!is_uploaded_file($tmp)) {
            return ['ok' => false, 'items' => [], 'error' => '잘못된 업로드 요청입니다.'];
        }

        // 확장자 체크
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, TR_UP_ALLOWED_EXT, true)) {
            return ['ok' => false, 'items' => [], 'error' => '지원하지 않는 파일 형식입니다. (jpg/png/gif/webp 만 가능)'];
        }

        // MIME 체크 (finfo 우선, 없으면 getimagesize 로 대체)
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string)@finfo_file($fi, $tmp);
                @finfo_close($fi);
            }
        }
        if ($mime === '') {
            $info = @getimagesize($tmp);
            if ($info && !empty($info['mime'])) $mime = $info['mime'];
        }
        if (!in_array($mime, TR_UP_ALLOWED_MIME, true)) {
            return ['ok' => false, 'items' => [], 'error' => '이미지 파일만 업로드할 수 있습니다.'];
        }
        // 진짜 이미지인지 한번 더 확인
        if (!@getimagesize($tmp)) {
            return ['ok' => false, 'items' => [], 'error' => '손상되었거나 이미지가 아닌 파일입니다.'];
        }

        // 안전한 파일명
        try {
            $rand = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $rand = substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
        }
        $new_name = date('YmdHis') . '_' . $rand . '.' . $ext;
        $abs_path = $abs_dir . '/' . $new_name;
        $web_path = '/' . trim($base_dir, '/') . '/' . $sub_dir . '/' . $new_name;

        if ($use_s3) {
            $up = s3_upload_file($tmp, $web_path, $mime);
            if (!$up['ok']) {
                if (s3_should_use_for_base_dir(trim($base_dir, '/'))) {
                    s3_rollback_uploaded_paths(array_column($result['items'], 'path'));
                } else {
                    foreach ($result['items'] as $prev) {
                        $full = $web_root . $prev['path'];
                        if (is_file($full)) @unlink($full);
                    }
                }
                return ['ok' => false, 'items' => [], 'error' => $up['error']];
            }
        } elseif (!@move_uploaded_file($tmp, $abs_path)) {
            // 이미 저장된 이전 파일 정리
            foreach ($result['items'] as $prev) {
                $full = $web_root . $prev['path'];
                if (is_file($full)) @unlink($full);
            }
            return ['ok' => false, 'items' => [], 'error' => '파일 저장 중 오류가 발생했습니다.'];
        } else {
            @chmod($abs_path, 0644);
        }

        $result['items'][] = [
            'path'      => $web_path,
            'orig_name' => mb_substr($name, 0, 255),
            'size'      => $size,
            'mime'      => $mime,
        ];
    }

    return $result;
}

/**
 * 거래글 다중 이미지 업로드
 *
 * @param array $files_field $_FILES['tr_images'] 형태
 * @return array
 */
function trade_upload_images($files_field) {
    return pz_upload_images_multi($files_field, TR_UP_BASE_DIR);
}

/**
 * 경매글 다중 이미지 업로드
 *
 * @param array $files_field $_FILES['au_images'] 형태
 * @return array
 */
function auction_upload_images($files_field) {
    return pz_upload_images_multi($files_field, AU_UP_BASE_DIR);
}

/**
 * 1:1 문의 첨부 이미지 업로드 (최대 5개)
 *
 * @param array|null $files_field $_FILES['iq_files'] 형태
 * @return array
 */
function inquiry_upload_files($files_field) {
    return pz_upload_images_multi($files_field, IQ_UP_BASE_DIR, IQ_UP_MAX_FILES);
}

/**
 * 거래 채팅용 단일 이미지 (미선택 시 ok, path 빈 문자열)
 *
 * @param array|null $file $_FILES['msg_image']
 * @return array{ok: bool, path: string, error: string}
 */
function trade_upload_chat_image(?array $file): array
{
    $empty = ['ok' => true, 'path' => '', 'error' => ''];
    if (empty($file) || !isset($file['error'])) {
        return $empty;
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $empty;
    }

    $wrapped = [
        'name'     => [$file['name'] ?? ''],
        'type'     => [$file['type'] ?? ''],
        'tmp_name' => [$file['tmp_name'] ?? ''],
        'error'    => [$file['error'] ?? UPLOAD_ERR_NO_FILE],
        'size'     => [$file['size'] ?? 0],
    ];
    $u = trade_upload_images($wrapped);
    if (!$u['ok']) {
        return ['ok' => false, 'path' => '', 'error' => $u['error']];
    }
    if (empty($u['items'])) {
        return $empty;
    }

    return ['ok' => true, 'path' => (string) ($u['items'][0]['path'] ?? ''), 'error' => ''];
}

/**
 * 업로드 디렉터리 파일 삭제 (로컬 + S3)
 */
function pz_remove_upload_files(array $web_paths, string $base_dir): void
{
    $web_root = realpath(__DIR__ . '/..');
    foreach ($web_paths as $p) {
        if (!is_string($p) || $p === '') {
            continue;
        }
        if (strpos($p, '/' . $base_dir . '/') !== 0) {
            continue;
        }
        if (s3_should_use_for_base_dir($base_dir) && s3_is_managed_web_path($p)) {
            s3_delete_web_path($p);
        }
        if ($web_root !== false) {
            $abs = $web_root . $p;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
}

/**
 * 거래 업로드 파일 삭제 (웹경로 배열)
 */
function trade_remove_files(array $web_paths)
{
    pz_remove_upload_files($web_paths, TR_UP_BASE_DIR);
}

/**
 * 경매 업로드 파일 삭제 (웹경로 배열)
 */
function auction_remove_files(array $web_paths): void
{
    pz_remove_upload_files($web_paths, AU_UP_BASE_DIR);
}

/**
 * 커뮤니티 업로드 파일 삭제 (웹경로 배열)
 */
function community_remove_files(array $web_paths): void
{
    pz_remove_upload_files($web_paths, CM_UP_BASE_DIR);
}

/**
 * 1:1 문의 첨부 파일 삭제 (웹경로 배열)
 */
function inquiry_remove_files(array $web_paths): void
{
    pz_remove_upload_files($web_paths, IQ_UP_BASE_DIR);
}

/**
 * 카드 시세 업로드 디렉터리 파일만 삭제 (웹경로 배열)
 */
function card_price_remove_files(array $web_paths): void
{
    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        return;
    }
    foreach ($web_paths as $p) {
        if (!is_string($p) || $p === '') {
            continue;
        }
        if (strpos($p, '/' . CP_UP_BASE_DIR . '/') !== 0) {
            continue;
        }
        $abs = $web_root . $p;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

/**
 * 뽑기 업로드 디렉터리 파일만 삭제 (웹경로 배열)
 */
function draw_remove_files(array $web_paths): void
{
    $web_root = realpath(__DIR__ . '/..');
    if ($web_root === false) {
        return;
    }
    foreach ($web_paths as $p) {
        if (!is_string($p) || $p === '') {
            continue;
        }
        if (strpos($p, '/' . DR_UP_BASE_DIR . '/') !== 0) {
            continue;
        }
        $abs = $web_root . $p;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
