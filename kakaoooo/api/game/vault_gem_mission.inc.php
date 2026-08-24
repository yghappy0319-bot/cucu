<?php
/**
 * 개인금고 겜냥 전체미션
 * — 친구들(전원)이 개인금고에 맡긴 게임냥 합 / 전체 게임냥 비율
 * — 10% 예치 → 전원 마켓 5% 할인
 * — 30% 예치 → 10% 할인
 * — 50% 예치 → 15% 할인
 */

if (!function_exists('gv_겜냥미션_티어표')) {
    /** @return list<array{need_pct:int,discount_pct:int,label:string}> */
    function gv_겜냥미션_티어표(): array {
        return [
            ['need_pct' => 10, 'discount_pct' => 5, 'label' => '전체 게임냥 10% 예치 → 마켓 5% 할인'],
            ['need_pct' => 30, 'discount_pct' => 10, 'label' => '전체 게임냥 30% 예치 → 마켓 10% 할인'],
            ['need_pct' => 50, 'discount_pct' => 15, 'label' => '전체 게임냥 50% 예치 → 마켓 15% 할인'],
        ];
    }
}

if (!function_exists('gv_겜냥미션_정수')) {
    function gv_겜냥미션_정수($v): string {
        if (function_exists('냥_정수문자열')) {
            return 냥_정수문자열($v);
        }
        if (function_exists('gv_냥')) {
            return gv_냥($v);
        }
        $s = trim((string)$v);
        if (strpos($s, '.') !== false) {
            $s = explode('.', $s, 2)[0];
        }
        $s = preg_replace('/\D/', '', $s);
        return ltrim((string)$s, '0') ?: '0';
    }
}

if (!function_exists('gv_겜냥미션_예치합')) {
    /**
     * 개인금고 자유예치·7일잠금 게임냥 합 (status=0 · currency=point)
     * ※ 은/금/백금괴 액면(해 단위)은 제외 — 미션 예치는 현금 예치만
     */
    function gv_겜냥미션_예치합(): string {
        $bust = (int)($GLOBALS['_gv_gem_mission_bust'] ?? 0);
        static $cache = null;
        static $cacheAt = 0;
        static $cacheBust = -1;
        $now = time();
        if ($cache !== null && $cacheBust === $bust && ($now - $cacheAt) < 5) {
            return $cache;
        }
        // CONCAT('N',…) — mysqli float/과학적표기 차단
        $row = @db_select("
            SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))), 0) AS CHAR)) AS s
            FROM tb_gold_bar
            WHERE status = 0
              AND IFNULL(currency, 'point') = 'point'
              AND IFNULL(bar_kind, '') IN (
                'cash', 'vault', '전액', '전액예치', '자유',
                'lock7', 'lock', '잠금', '7일잠금'
              )
        ");
        $raw = $row['s'] ?? 'N0';
        if (function_exists('냥_금액원문_정규화')) {
            $cache = gv_겜냥미션_정수(냥_금액원문_정규화($raw));
        } else {
            $cache = gv_겜냥미션_정수($raw);
        }
        $cacheAt = $now;
        $cacheBust = $bust;
        return $cache;
    }
}

if (!function_exists('gv_겜냥미션_지갑합')) {
    /** 회원 보유 게임냥 합 (status=0 · floor) — 채팅 「전체 게임냥」과 동일 기준 */
    function gv_겜냥미션_지갑합(): string {
        $bust = (int)($GLOBALS['_gv_gem_mission_bust'] ?? 0);
        static $cache = null;
        static $cacheAt = 0;
        static $cacheBust = -1;
        $now = time();
        if ($cache !== null && $cacheBust === $bust && ($now - $cacheAt) < 5) {
            return $cache;
        }
        $row = @db_select("
            SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point,0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS s
            FROM tb_member
            WHERE status = 0
        ");
        $raw = $row['s'] ?? 'N0';
        if (function_exists('냥_금액원문_정규화')) {
            $cache = gv_겜냥미션_정수(냥_금액원문_정규화($raw));
        } else {
            $cache = gv_겜냥미션_정수($raw);
        }
        $cacheAt = $now;
        $cacheBust = $bust;
        return $cache;
    }
}

if (!function_exists('gv_겜냥미션_전체겜냥')) {
    /**
     * 전체 게임냥 = 지갑 합 + 금고 겜냥 예치 합
     * (예치분은 지갑에서 빠지므로 합쳐야 전체 풀이 유지됨)
     */
    function gv_겜냥미션_전체겜냥(): string {
        $wallet = gv_겜냥미션_지갑합();
        $vault = gv_겜냥미션_예치합();
        if (function_exists('bcadd')) {
            return bcadd($wallet, $vault, 0);
        }
        if (function_exists('gv_더하기')) {
            return gv_더하기($wallet, $vault);
        }
        return (string)(((int)$wallet) + ((int)$vault));
    }
}

if (!function_exists('gv_겜냥미션_비율bp')) {
    /** 예치 비율 · basis points (10000 = 100%) */
    function gv_겜냥미션_비율bp(): int {
        $dep = gv_겜냥미션_예치합();
        $tot = gv_겜냥미션_전체겜냥();
        if ($dep === '0' || $tot === '0') {
            return 0;
        }
        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
            if (bccomp($tot, '0', 0) <= 0) {
                return 0;
            }
            $bp = bcdiv(bcmul($dep, '10000', 0), $tot, 0);
            return max(0, min(10000, (int)$bp));
        }
        $d = (float)$dep;
        $t = (float)$tot;
        if ($t <= 0) {
            return 0;
        }
        return max(0, min(10000, (int)floor(($d / $t) * 10000)));
    }
}

if (!function_exists('gv_겜냥미션_마켓할인율')) {
    /** @return int 0|5|10|15 */
    function gv_겜냥미션_마켓할인율(): int {
        $bp = gv_겜냥미션_비율bp(); // 1000=10%, 3000=30%, 5000=50%
        $rate = 0;
        foreach (gv_겜냥미션_티어표() as $tier) {
            $needBp = ((int)$tier['need_pct']) * 100;
            if ($bp >= $needBp) {
                $rate = (int)$tier['discount_pct'];
            }
        }
        return $rate;
    }
}

if (!function_exists('gv_겜냥미션_표시')) {
    /** 채팅 「전체 게임냥」과 동일 · 조/억/만 표기 */
    function gv_겜냥미션_표시($v): string {
        if (function_exists('랭킹_게임냥표시')) {
            return 랭킹_게임냥표시($v, '냥');
        }
        if (function_exists('게임냥_안전표시')) {
            return 게임냥_안전표시($v, '냥');
        }
        if (function_exists('gv_냥표시')) {
            $s = gv_냥표시($v);
            return (strpos($s, '냥') !== false) ? $s : ($s . '냥');
        }
        $n = gv_겜냥미션_정수($v);
        if ($n === '0') {
            return '0냥';
        }
        $rev = strrev($n);
        $parts = str_split($rev, 3);
        return strrev(implode(',', $parts)) . '냥';
    }
}

if (!function_exists('gv_겜냥미션_캐시초기화')) {
    function gv_겜냥미션_캐시초기화(): void {
        $GLOBALS['_gv_gem_mission_bust'] = time();
    }
}

if (!function_exists('gv_겜냥미션_현황')) {
    /**
     * UI/API용 전체미션 현황
     * @return array<string,mixed>
     */
    function gv_겜냥미션_현황(): array {
        $deposited = gv_겜냥미션_예치합();
        $total = gv_겜냥미션_전체겜냥();
        $bp = gv_겜냥미션_비율bp();
        $ratioPct = round($bp / 100, 2);
        $discount = gv_겜냥미션_마켓할인율();

        $tiers = [];
        $nextNeed = null;
        $nextDisc = null;
        foreach (gv_겜냥미션_티어표() as $tier) {
            $need = (int)$tier['need_pct'];
            $disc = (int)$tier['discount_pct'];
            $reached = ($bp >= $need * 100);
            $tiers[] = [
                'need_pct' => $need,
                'discount_pct' => $disc,
                'label' => (string)$tier['label'],
                'reached' => $reached,
            ];
            if (!$reached && $nextNeed === null) {
                $nextNeed = $need;
                $nextDisc = $disc;
            }
        }

        $remainToNext = '0';
        $remainToNextDisp = '';
        if ($nextNeed !== null && function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcsub') && function_exists('bccomp')) {
            $needAmt = bcdiv(bcmul($total, (string)$nextNeed, 0), '100', 0);
            if (bccomp($needAmt, $deposited, 0) > 0) {
                $remainToNext = bcsub($needAmt, $deposited, 0);
            }
            $remainToNextDisp = gv_겜냥미션_표시($remainToNext);
        }

        $barPct = max(0, min(100, (int)round($ratioPct)));

        return [
            'deposited' => $deposited,
            'deposited_disp' => gv_겜냥미션_표시($deposited),
            'total' => $total,
            'total_disp' => gv_겜냥미션_표시($total),
            'ratio_bp' => $bp,
            'ratio_pct' => $ratioPct,
            'ratio_disp' => rtrim(rtrim(number_format($ratioPct, 2, '.', ''), '0'), '.') . '%',
            'discount_pct' => $discount,
            'tiers' => $tiers,
            'next_need_pct' => $nextNeed,
            'next_discount_pct' => $nextDisc,
            'remain_to_next' => $remainToNext,
            'remain_to_next_disp' => $remainToNextDisp,
            'bar_pct' => $barPct,
            'active' => $discount > 0,
            'title' => '겜냥 전체미션',
            'subtitle' => '친구들이 개인금고에 맡긴 게임냥 비율로 전원 마켓 할인',
            'reward_label' => '마켓',
            'effect_label' => $discount > 0
                ? ('현재 마켓 ' . $discount . '% 할인 적용 중 (금괴·백금괴 할인과 합산)')
                : '아직 할인 구간 전 · 함께 예치해 주세요',
            'rank_top' => function_exists('gv_예치순위_상위')
                ? gv_예치순위_상위('point', 3)
                : [],
        ];
    }
}
