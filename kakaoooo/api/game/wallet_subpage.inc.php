<?php
/**
 * 지갑 서브페이지 공통 — 인증·잔액 로드
 * · .퇴근 중이어도 가방 서브(스왑·양도)는 이용 가능
 * · off_work 는 참고용(바로가기 숨김 상태)이며 잠금에 쓰지 않음
 */
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
require_once __DIR__ . '/wallet_web.php';

function wallet_subpage_boot(): array {
    $code = '';
    if (!empty($GLOBALS['wallet_preauth']['code'])) {
        $code = (string)$GLOBALS['wallet_preauth']['code'];
    } else {
        $code = wallet_코드_해석();
    }

    $need_code = true;
    $off_work = false;
    $member = null;
    $nick = '';

    if ($code !== '') {
        wallet_odd_even_includes();
        $row = null;
        if (!empty($GLOBALS['wallet_preauth']['row'])) {
            $row = wallet_member_row_enrich($GLOBALS['wallet_preauth']['row'], $code);
        } else {
            $row = wallet_game_auth_row($code);
            if ($row) {
                $row = wallet_member_row_enrich($row, $code);
            }
        }
        if ($row) {
            $nick = wallet_nick_from_row($row);
            if ($nick === '') {
                $nick = trim((string)($row['name'] ?? ''));
            }
            wallet_코드_쿠키_저장($code);
            $need_code = false;
            $member = wallet_member_payload($row, $nick);
            $off_work = wallet_nick_is_퇴근($nick);
        }
    }

    return [
        'code' => $code,
        'need_code' => $need_code,
        'off_work' => $off_work,
        'member' => $member,
        'nick' => $nick,
        'q' => wallet_code_query($code),
        'api' => '/page/wallet.php',
    ];
}
