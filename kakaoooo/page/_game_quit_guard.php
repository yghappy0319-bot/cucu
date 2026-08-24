<?php
/**
 * 게임포기 중 잠긴 페이지 진입 차단 → 가방으로 안내
 * 사용: require 후 게임포기_페이지가드($nick, $code);
 */
if (!function_exists('게임포기_페이지가드')) {
    function 게임포기_페이지가드($nick, $code = '', $label = ''): void {
        if (!function_exists('게임포기_바로가기숨김인가')) {
            if (!function_exists('tb_member_게임포기_컬럼_보장') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
                include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
            }
        }
        if (!function_exists('게임포기_바로가기숨김인가')) {
            return;
        }
        $nick = trim((string)$nick);
        if ($nick === '' || !게임포기_바로가기숨김인가($nick)) {
            return;
        }
        $q = $code !== '' ? ('?code=' . rawurlencode((string)$code)) : '';
        $label = trim((string)$label);
        $title = $label !== '' ? $label : '이 메뉴';
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>게임포기</title></head><body style="margin:0;min-height:100dvh;display:grid;place-items:center;';
        echo 'background:#0b0d12;color:#e8eef6;font-family:sans-serif;padding:24px;text-align:center">';
        echo '<div style="max-width:360px">';
        echo '<p style="margin:0 0 10px;font-size:28px">⏸</p>';
        echo '<p style="margin:0 0 8px;font-weight:700;font-size:18px">게임포기 중</p>';
        echo '<p style="margin:0 0 18px;color:#9aa3b2;line-height:1.5">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '는 게임포기 상태에서는 이용할 수 없어요.<br>가방에서 게임시작 후 이용해 주세요.</p>';
        echo '<a href="/page/wallet.php' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:12px 18px;border-radius:12px;background:rgba(94,234,212,0.15);'
            . 'color:#5eead4;text-decoration:none;font-weight:700">← 가방으로</a>';
        echo '</div></body></html>';
        exit;
    }
}
