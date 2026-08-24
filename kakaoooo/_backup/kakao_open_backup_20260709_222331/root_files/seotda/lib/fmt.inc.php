<?php

if (!function_exists('seotda_fmt_game')) {
    function seotda_fmt_game($n) {
        $n = (int)$n;
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($n, '냥');
        }
        if (function_exists('구매가_축약표시')) {
            return 구매가_축약표시($n, '냥');
        }
        return number_format($n) . '냥';
    }
}

if (!function_exists('seotda_vault_add')) {
    /** 무승부 등 — config.tax 금고 적립 */
    function seotda_vault_add($amount) {
        $amount = max(0, (int)$amount);
        if ($amount <= 0) {
            return;
        }
        @db_query("UPDATE config SET tax = tax + {$amount}");
    }
}

if (!function_exists('seotda_lotto_add')) {
    /** 무승부 등 — 진행 중 로또 회차(tb_game_lotto) 적립 */
    function seotda_lotto_add($amount) {
        static $cached_lotto_drow = null;
        $amount = max(0, (int)$amount);
        if ($amount <= 0) {
            return 0;
        }
        if ($cached_lotto_drow === null) {
            $완료회차행 = @db_select("
                SELECT IFNULL(MAX(drow), 0) AS max_drow
                FROM tb_game_lotto_result
                WHERE status = 1
            ");
            $cached_lotto_drow = (int)($완료회차행['max_drow'] ?? 0);
        }
        $진행회차 = $cached_lotto_drow + 1;
        $cached_lotto_drow = $진행회차;
        db_query("
            INSERT INTO tb_game_lotto
            SET nick = '섯다무승부',
                drow = {$진행회차},
                num1 = 0,
                num2 = 0,
                num3 = 0,
                amount = {$amount},
                status = 0,
                regdate = NOW()
        ");
        return $amount;
    }
}

/** 무승부 — 금액의 25% 금고 · 25% 로또 · 50% 환급 (나머지 1냥은 환급) */
if (!function_exists('seotda_draw_split')) {
    /** @return array{vault:int,lotto:int,refund:int} */
    function seotda_draw_split(int $total): array {
        $total = max(0, $total);
        $vault = (int)floor($total / 4);
        $lotto = (int)floor($total / 4);
        return [
            'vault' => $vault,
            'lotto' => $lotto,
            'refund' => $total - $vault - $lotto,
        ];
    }
}

/** PvP 무승부 — 판돈 25% 금고 · 25% 로또 · 50%를 양쪽에 균등 환급 */
if (!function_exists('seotda_draw_split_pvp')) {
    /** @return array{vault:int,lotto:int,host_refund:int,guest_refund:int} */
    function seotda_draw_split_pvp(int $pot): array {
        $split = seotda_draw_split($pot);
        $each = (int)floor($split['refund'] / 2);
        $remainder = $split['refund'] - ($each * 2);
        return [
            'vault' => $split['vault'],
            'lotto' => $split['lotto'],
            'host_refund' => $each + $remainder,
            'guest_refund' => $each,
        ];
    }
}

if (!function_exists('seotda_card_label')) {
    function seotda_card_label($month) {
        $m = (int)$month;
        if ($m < 1 || $m > 10) {
            return '?';
        }
        return $m . '월';
    }
}
