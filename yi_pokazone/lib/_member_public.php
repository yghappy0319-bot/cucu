<?php
/**
 * 판매자 상점 (공개 회원 페이지 · 상점 설정)
 */

if (!defined('MEMBER_SHOP_INTRO_MAX')) {
    define('MEMBER_SHOP_INTRO_MAX', 500);
}

function member_profile_url(int $mb_idx): string
{
    $mb_idx = max(0, $mb_idx);
    if ($mb_idx < 1) {
        return '';
    }

    return '/page/member_profile.php?mb_idx=' . $mb_idx;
}

function member_shop_intro_column_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!db_table_exists('tb_member')) {
        $ready = false;

        return false;
    }
    $ready = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_member LIKE 'mb_shop_intro'"));

    return $ready;
}

/**
 * 공개 가능한 회원 정보 (정상 회원만)
 *
 * @return array{mb_idx:int,mb_nick:string,mb_created_at:string,mb_shop_intro:string}|null
 */
function member_public_get(int $mb_idx): ?array
{
    $mb_idx = max(0, $mb_idx);
    if ($mb_idx < 1 || !db_table_exists('tb_member')) {
        return null;
    }

    $intro_sel = member_shop_intro_column_ready()
        ? 'mb_shop_intro'
        : "'' AS mb_shop_intro";

    $row = db_assoc(db_query("
        SELECT mb_idx, mb_nick, mb_created_at, mb_status, {$intro_sel}
        FROM tb_member
        WHERE mb_idx = {$mb_idx}
        LIMIT 1
    "));
    if (!$row) {
        return null;
    }
    if ((int) ($row['mb_status'] ?? 0) !== 1) {
        return null;
    }

    return [
        'mb_idx'         => (int) $row['mb_idx'],
        'mb_nick'        => (string) ($row['mb_nick'] ?? ''),
        'mb_created_at'  => (string) ($row['mb_created_at'] ?? ''),
        'mb_shop_intro'  => trim((string) ($row['mb_shop_intro'] ?? '')),
    ];
}

/**
 * @return array{ok:bool,error?:string,intro?:string}
 */
function member_shop_intro_parse(string $raw): array
{
    $intro = trim(str_replace("\r\n", "\n", $raw));
    $intro = preg_replace("/\n{3,}/u", "\n\n", $intro) ?? $intro;
    if (mb_strlen($intro) > MEMBER_SHOP_INTRO_MAX) {
        return [
            'ok'    => false,
            'error' => '상점 소개는 ' . MEMBER_SHOP_INTRO_MAX . '자 이내로 입력해 주세요.',
        ];
    }

    return ['ok' => true, 'intro' => $intro];
}

/**
 * @return array{ok:bool,error?:string,intro?:string}
 */
function member_shop_intro_save(int $mb_idx, string $raw): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error];
    };

    if ($mb_idx < 1) {
        return $fail('잘못된 요청입니다.');
    }
    if (!member_shop_intro_column_ready()) {
        return $fail('상점 소개 기능을 사용하려면 sql/migrate_tb_member_shop_intro.sql 을 적용해 주세요.');
    }

    $parsed = member_shop_intro_parse($raw);
    if (!$parsed['ok']) {
        return $parsed;
    }
    $intro = (string) ($parsed['intro'] ?? '');
    $esc   = db_escape($intro);
    $sql   = $intro === ''
        ? "UPDATE tb_member SET mb_shop_intro = NULL, mb_updated_at = NOW() WHERE mb_idx = {$mb_idx} LIMIT 1"
        : "UPDATE tb_member SET mb_shop_intro = '{$esc}', mb_updated_at = NOW() WHERE mb_idx = {$mb_idx} LIMIT 1";

    if (!db_query($sql)) {
        return $fail('상점 소개 저장 중 오류가 발생했습니다.');
    }

    return ['ok' => true, 'intro' => $intro];
}

/**
 * 닉네임 → 판매자 상점 링크 HTML (mb_idx 없거나 탈퇴면 텍스트만)
 */
function member_nick_link_html(int $mb_idx, ?string $nick, string $class = 'member-nick-link'): string
{
    $label = trim((string) $nick);
    if ($label === '') {
        $label = '(탈퇴)';
    }
    $esc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $url = member_profile_url($mb_idx);
    if ($url === '' || $mb_idx < 1) {
        return $esc;
    }
    $cls = trim($class);
    $cls_attr = $cls !== '' ? ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"' : '';

    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $cls_attr . '>' . $esc . '</a>';
}
