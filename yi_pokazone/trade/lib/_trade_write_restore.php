<?php
/**
 * 거래글 작성 실패 시 입력값 복원
 */

function trade_write_draft_storage_key(string $mode, int $idx): string
{
    if ($mode === 'edit' && $idx > 0) {
        return 'pz_trade_write_draft_edit_' . $idx;
    }

    return 'pz_trade_write_draft_insert';
}

function trade_write_restore_key(string $mode, int $idx): string
{
    return ($mode === 'edit' && $idx > 0) ? ('edit:' . $idx) : 'insert';
}

/**
 * @return array<string, string>
 */
function trade_write_collect_old_from_post(): array
{
    $keep = [
        'mode', 'idx', 'tr_item_type', 'tr_type', 'tr_title',
        'tr_card_name_box', 'tr_box_qty', 'tr_card_name_card',
        'tr_set_name', 'tr_card_number', 'tr_language',
        'tr_grade', 'tr_condition', 'tr_card_kind',
        'tr_grading_company', 'tr_grading_score',
        'tr_price', 'tr_method', 'tr_shipping_fee', 'tr_region', 'tr_content',
    ];
    $old = [];
    foreach ($keep as $k) {
        if (!array_key_exists($k, $_POST)) {
            continue;
        }
        $val = $_POST[$k];
        if (is_array($val)) {
            continue;
        }
        $val = (string) $val;
        if ($k === 'tr_card_kind' && $val !== 'single' && $val !== 'graded') {
            continue;
        }
        $old[$k] = $val;
    }
    $idx  = (int) ($_POST['idx'] ?? 0);
    $mode = (string) ($_POST['mode'] ?? 'insert');
    $old['_key'] = trade_write_restore_key($mode, $idx);

    return $old;
}

function trade_write_old_is_useful(?array $old): bool
{
    if ($old === null) {
        return false;
    }
    $keys = [
        'tr_title', 'tr_card_name_card', 'tr_card_name_box',
        'tr_set_name', 'tr_card_number', 'tr_region', 'tr_content',
    ];
    foreach ($keys as $k) {
        $raw = trim(html_entity_decode(strip_tags((string) ($old[$k] ?? '')), ENT_QUOTES, 'UTF-8'));
        if ($raw !== '' && $raw !== '상세 설명을 입력해 주세요.') {
            return true;
        }
    }
    if ((int) ($old['tr_price'] ?? 0) > 0) {
        return true;
    }

    return false;
}

function trade_write_php_ini_bytes(string $val): int
{
    $val = strtoupper(trim($val));
    if ($val === '' || $val === '0') {
        return 0;
    }
    $n = (float) $val;
    $u = substr($val, -1);
    if ($u === 'G') {
        return (int) ($n * 1073741824);
    }
    if ($u === 'M') {
        return (int) ($n * 1048576);
    }
    if ($u === 'K') {
        return (int) ($n * 1024);
    }

    return (int) $n;
}

function trade_write_php_bytes_label(int $bytes): string
{
    if ($bytes < 1) {
        return '0';
    }
    if ($bytes >= 1048576) {
        $mb = $bytes / 1048576;
        $txt = (abs($mb - (int) $mb) < 0.05) ? (string) (int) $mb : number_format($mb, 1);
        return $txt . 'MB';
    }
    if ($bytes >= 1024) {
        return (string) (int) round($bytes / 1024) . 'KB';
    }

    return $bytes . 'B';
}

function trade_write_post_overflow_message(): string
{
    $sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $post_ini = (string) ini_get('post_max_size');
    $up_ini = (string) ini_get('upload_max_filesize');
    $post_bytes = trade_write_php_ini_bytes($post_ini);
    $parts = ['첨부 용량이 너무 큽니다.'];
    if ($sent > 0 && $post_bytes > 0) {
        $parts[] = '보낸 용량 약 ' . trade_write_php_bytes_label($sent)
            . ', 서버 요청 한도 ' . trade_write_php_bytes_label($post_bytes)
            . ' (post_max_size ' . $post_ini . ')';
    } elseif ($post_ini !== '') {
        $parts[] = '서버 요청 한도 ' . $post_ini;
    }
    if ($up_ini !== '') {
        $parts[] = '파일 1개 한도 ' . $up_ini;
    }
    $parts[] = '사진은 장당 20MB, 최대 8장입니다. 크기나 장수를 줄여 주세요.';

    return implode(' ', $parts);
}

function trade_write_post_overflowed(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len < 1) {
        return false;
    }

    return empty($_POST) && empty($_FILES);
}

/**
 * 세션 + sessionStorage 심고 작성 폼으로 이동 (alert 후 replace)
 */
function trade_write_fail_back(string $msg, bool $plant_browser_draft = true): void
{
    global $from_admin;

    $idx  = (int) ($_POST['idx'] ?? 0);
    $mode = (string) ($_POST['mode'] ?? 'insert');
    $old  = trade_write_collect_old_from_post();
    $useful = trade_write_old_is_useful($old);

    $_SESSION['pz_trade_write_err'] = $msg;
    if ($useful) {
        $_SESSION['pz_trade_write_old'] = $old;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $url = '/trade/trade_write.php';
    if ($mode === 'edit' && $idx > 0) {
        $url .= '?idx=' . $idx;
        if (!empty($from_admin)) {
            $url .= '&from=admin';
        }
        $url .= '&restore=1';
    } else {
        $url .= '?restore=1';
    }

    $draft_key = trade_write_draft_storage_key($mode, $idx);
    $plant = $plant_browser_draft && $useful;
    $payload = [
        'savedAt' => (int) round(microtime(true) * 1000),
        'fields'  => $old,
    ];

    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $payload_json = json_encode($payload, $flags);
    if ($payload_json === false) {
        unset($payload['fields']['tr_content']);
        $payload_json = json_encode($payload, $flags);
    }
    if ($payload_json === false) {
        $payload_json = 'null';
        $plant = false;
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>작성 내용 복원</title></head><body>';
    echo '<script>';
    echo '(function(){';
    echo 'var msg=' . json_encode($msg, $flags) . ';';
    echo 'var key=' . json_encode($draft_key, $flags) . ';';
    echo 'var url=' . json_encode($url, $flags) . ';';
    echo 'var plant=' . ($plant ? 'true' : 'false') . ';';
    echo 'var payload=' . $payload_json . ';';
    echo 'if(plant&&payload){try{sessionStorage.setItem(key,JSON.stringify(payload));}catch(e){try{if(payload.fields){delete payload.fields.tr_content;sessionStorage.setItem(key,JSON.stringify(payload));}}catch(e2){}}}';
    echo 'if(msg){alert(msg);}';
    echo 'location.replace(url);';
    echo '})();';
    echo '</script></body></html>';
    exit;
}
