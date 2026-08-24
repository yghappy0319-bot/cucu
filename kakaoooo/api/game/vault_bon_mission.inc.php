<?php
/**
 * 개인금고 본냥 전체미션
 * — 친구들(전원)이 개인금고에 맡긴 본방냥 합 / 전체 본방냥 비율
 * — 10% 예치 → 무기·채굴 강화비 5% 할인
 * — 30% 예치 → 10% 할인
 * — 60% 예치 → 15% 할인
 */

if (!function_exists('gv_본냥미션_티어표')) {
    /** @return list<array{need_pct:int,discount_pct:int,label:string}> */
    function gv_본냥미션_티어표(): array {
        return [
            ['need_pct' => 10, 'discount_pct' => 5, 'label' => '전체 본방냥 10% 예치 → 강화비 5% 할인'],
            ['need_pct' => 30, 'discount_pct' => 10, 'label' => '전체 본방냥 30% 예치 → 강화비 10% 할인'],
            ['need_pct' => 60, 'discount_pct' => 15, 'label' => '전체 본방냥 60% 예치 → 강화비 15% 할인'],
        ];
    }
}

if (!function_exists('gv_본냥미션_정수')) {
    function gv_본냥미션_정수($v): string {
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

if (!function_exists('gv_본냥미션_예치합')) {
    /**
     * 개인금고 자유예치·7일잠금 본방냥 합 (status=0 · currency=newpoint)
     * ※ 은/금/백금괴 액면은 제외
     */
    function gv_본냥미션_예치합(): string {
        $bust = (int)($GLOBALS['_gv_bon_mission_bust'] ?? 0);
        static $cache = null;
        static $cacheAt = 0;
        static $cacheBust = -1;
        $now = time();
        if ($cache !== null && $cacheBust === $bust && ($now - $cacheAt) < 5) {
            return $cache;
        }
        $row = @db_select("
            SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(amount,0) AS DECIMAL(65,0))), 0) AS CHAR)) AS s
            FROM tb_gold_bar
            WHERE status = 0
              AND IFNULL(currency, 'point') = 'newpoint'
              AND IFNULL(bar_kind, '') IN (
                'cash', 'vault', '전액', '전액예치', '자유',
                'lock7', 'lock', '잠금', '7일잠금'
              )
        ");
        $raw = $row['s'] ?? 'N0';
        if (function_exists('냥_금액원문_정규화')) {
            $cache = gv_본냥미션_정수(냥_금액원문_정규화($raw));
        } else {
            $cache = gv_본냥미션_정수($raw);
        }
        $cacheAt = $now;
        $cacheBust = $bust;
        return $cache;
    }
}

if (!function_exists('gv_본냥미션_지갑합')) {
    /** 회원 보유 본방냥 합 (status=0 · floor) */
    function gv_본냥미션_지갑합(): string {
        $bust = (int)($GLOBALS['_gv_bon_mission_bust'] ?? 0);
        static $cache = null;
        static $cacheAt = 0;
        static $cacheBust = -1;
        $now = time();
        if ($cache !== null && $cacheBust === $bust && ($now - $cacheAt) < 5) {
            return $cache;
        }
        $row = @db_select("
            SELECT CAST(COALESCE(SUM(FLOOR(CAST(IFNULL(newpoint,0) AS DECIMAL(65,4)))), 0) AS CHAR) AS s
            FROM tb_member
            WHERE status = 0
        ");
        $cache = gv_본냥미션_정수($row['s'] ?? 0);
        $cacheAt = $now;
        $cacheBust = $bust;
        return $cache;
    }
}

if (!function_exists('gv_본냥미션_전체본방')) {
    /**
     * 전체 본방냥 = 지갑 합 + 금고 본냥 예치 합
     * (예치분은 지갑에서 빠지므로 합쳐야 전체 풀이 유지됨)
     */
    function gv_본냥미션_전체본방(): string {
        $wallet = gv_본냥미션_지갑합();
        $vault = gv_본냥미션_예치합();
        if (function_exists('bcadd')) {
            return bcadd($wallet, $vault, 0);
        }
        if (function_exists('gv_더하기')) {
            return gv_더하기($wallet, $vault);
        }
        return (string)(((int)$wallet) + ((int)$vault));
    }
}

if (!function_exists('gv_본냥미션_비율bp')) {
    /** 예치 비율 · basis points (10000 = 100%) */
    function gv_본냥미션_비율bp(): int {
        $dep = gv_본냥미션_예치합();
        $tot = gv_본냥미션_전체본방();
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

if (!function_exists('gv_본냥미션_강화할인율')) {
    /** @return int 0|5|10|15 */
    function gv_본냥미션_강화할인율(): int {
        $bp = gv_본냥미션_비율bp(); // 1000=10%, 3000=30%, 6000=60%
        $rate = 0;
        foreach (gv_본냥미션_티어표() as $tier) {
            $needBp = ((int)$tier['need_pct']) * 100;
            if ($bp >= $needBp) {
                $rate = (int)$tier['discount_pct'];
            }
        }
        return $rate;
    }
}

if (!function_exists('gv_본냥미션_비용할인')) {
    /**
     * 강화비에 본냥 전체미션 할인 적용 (내림 · 최소 1)
     * @param int|string $cost
     * @return int|string
     */
    function gv_본냥미션_비용할인($cost) {
        $rate = gv_본냥미션_강화할인율();
        if ($rate <= 0) {
            return $cost;
        }
        $keep = 100 - max(0, min(99, (int)$rate));
        $s = gv_본냥미션_정수($cost);
        if ($s === '0') {
            return $cost;
        }
        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
            $out = bcdiv(bcmul($s, (string)$keep, 0), '100', 0);
            if (bccomp($out, '1', 0) < 0) {
                $out = '1';
            }
            return $out;
        }
        return (string)max(1, (int)floor(((float)$s) * $keep / 100));
    }
}

if (!function_exists('gv_본냥미션_표시')) {
    function gv_본냥미션_표시($v): string {
        if (function_exists('gv_본방표시')) {
            return gv_본방표시($v);
        }
        if (function_exists('newpoint표시')) {
            $s = newpoint표시($v);
            return (strpos($s, '냥') !== false) ? $s : ($s . '냥');
        }
        $n = gv_본냥미션_정수($v);
        if ($n === '0') {
            return '0냥';
        }
        $rev = strrev($n);
        $parts = str_split($rev, 3);
        return strrev(implode(',', $parts)) . '냥';
    }
}

if (!function_exists('gv_본냥미션_캐시초기화')) {
    function gv_본냥미션_캐시초기화(): void {
        // static 캐시는 요청 단위 — 예치 직후 같은 요청에서 쓰려면 별도 플래그
        $GLOBALS['_gv_bon_mission_bust'] = time();
    }
}

if (!function_exists('gv_본냥미션_현황')) {
    /**
     * UI/API용 전체미션 현황
     * @return array<string,mixed>
     */
    function gv_본냥미션_현황(): array {
        $deposited = gv_본냥미션_예치합();
        $total = gv_본냥미션_전체본방();
        $bp = gv_본냥미션_비율bp();
        $ratioPct = round($bp / 100, 2); // 12.34 (%)
        $discount = gv_본냥미션_강화할인율();

        $tiers = [];
        $nextNeed = null;
        $nextDisc = null;
        foreach (gv_본냥미션_티어표() as $tier) {
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
            // need amount = total * need% / 100
            $needAmt = bcdiv(bcmul($total, (string)$nextNeed, 0), '100', 0);
            if (bccomp($needAmt, $deposited, 0) > 0) {
                $remainToNext = bcsub($needAmt, $deposited, 0);
            }
            $remainToNextDisp = gv_본냥미션_표시($remainToNext);
        }

        $barPct = max(0, min(100, (int)round($ratioPct)));

        return [
            'deposited' => $deposited,
            'deposited_disp' => gv_본냥미션_표시($deposited),
            'total' => $total,
            'total_disp' => gv_본냥미션_표시($total),
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
            'title' => '본냥 전체미션',
            'subtitle' => '친구들이 개인금고에 맡긴 본방냥 비율로 전원 강화비 할인',
            'reward_label' => '강화비',
            'effect_label' => $discount > 0
                ? ('현재 무기·채굴 강화비 ' . $discount . '% 할인 적용 중')
                : '아직 할인 구간 전 · 함께 예치해 주세요',
            'rank_top' => function_exists('gv_예치순위_상위')
                ? gv_예치순위_상위('newpoint', 3)
                : [],
        ];
    }
}
