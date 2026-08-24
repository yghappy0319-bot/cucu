<?php
/**
 * 무기 타입 (tb_member.무기타입)
 * 1=활 · 2=단소 · 3=마법 · 0=없음
 *
 * item 컬럼: 표시명 (단소만 강화 구간별 명칭, 활/마법은 고정)
 */

if (!defined('무기_타입_없음')) {
    define('무기_타입_없음', 0);
}
if (!defined('무기_타입_활')) {
    define('무기_타입_활', 1);
}
if (!defined('무기_타입_단소')) {
    define('무기_타입_단소', 2);
}
if (!defined('무기_타입_마법')) {
    define('무기_타입_마법', 3);
}

if (!function_exists('단소_구간표시명')) {
    /**
     * 단소 강화 구간 표시명
     * 0~9 젓가락 · 10~19 리코더 · 20~29 단소 · 30~39 피리 · 40~49 대금 · 50~59 플루트 · … · 100 엑스칼리버
     */
    function 단소_구간표시명(int $강화): string {
        $e = max(0, (int)$강화);
        if ($e >= 100) {
            return '⚔️엑스칼리버';
        }
        if ($e >= 90) {
            return '🐉용피리';
        }
        if ($e >= 80) {
            return '✨천상의 피리';
        }
        if ($e >= 70) {
            return '🎺태평소';
        }
        if ($e >= 60) {
            return '📯나발';
        }
        if ($e >= 50) {
            return '🪈플루트';
        }
        if ($e >= 40) {
            return '🪈대금';
        }
        if ($e >= 30) {
            return '🪈피리';
        }
        if ($e >= 20) {
            return '🪈단소';
        }
        if ($e >= 10) {
            return '🎵리코더';
        }
        return '🥢젓가락';
    }
}

if (!function_exists('단소_구간표시명목록')) {
    /** @return list<string> */
    function 단소_구간표시명목록(): array {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $set = [
            '🪈단소',
            '플루트단소',
            '🪈 단소',
            '플루트 단소',
            '🥢젓가락',
            '🎵리코더',
            '🪈피리',
            '피리',
            '🪈대금',
            '대금',
            '🪈플루트',
            '플루트',
            '🪈생황',
            '생황',
            '📯나발',
            '🎺태평소',
            '✨천상의 피리',
            '🐉용피리',
            '⚔️엑스칼리버',
        ];
        for ($e = 0; $e <= 100; $e++) {
            $set[] = 단소_구간표시명($e);
        }
        $list = array_values(array_unique($set));
        return $list;
    }
}

if (!function_exists('무기_표시아이템')) {
    /** 타입+강화 → DB item 표시명 */
    function 무기_표시아이템(int $타입, int $강화 = 0): string {
        $타입 = (int)$타입;
        if ($타입 === 무기_타입_활) {
            return '🏹활';
        }
        if ($타입 === 무기_타입_마법) {
            return '🪄마법';
        }
        if ($타입 === 무기_타입_단소) {
            return 단소_구간표시명($강화);
        }
        return '';
    }
}

if (!function_exists('무기_타입키')) {
    /** 1|2|3 → 활|단소|마법 */
    function 무기_타입키(int $타입): string {
        if ($타입 === 무기_타입_활) {
            return '활';
        }
        if ($타입 === 무기_타입_단소) {
            return '단소';
        }
        if ($타입 === 무기_타입_마법) {
            return '마법';
        }
        return '';
    }
}

if (!function_exists('무기_타입_키에서')) {
    /** 활|단소|마법 → 1|2|3 */
    function 무기_타입_키에서(string $키): int {
        $키 = trim($키);
        if ($키 === '활') {
            return 무기_타입_활;
        }
        if ($키 === '단소') {
            return 무기_타입_단소;
        }
        if ($키 === '마법') {
            return 무기_타입_마법;
        }
        return 무기_타입_없음;
    }
}

if (!function_exists('무기_타입_추정')) {
    /** item 문자열만으로 타입 추정 (마이그레이션·하위호환) */
    function 무기_타입_추정($아이템): int {
        $아이템 = trim((string)$아이템);
        if ($아이템 === '') {
            return 무기_타입_없음;
        }
        $압축 = str_replace(' ', '', $아이템);
        if ($압축 === '🪄마법' || mb_strpos($아이템, '마법') !== false) {
            return 무기_타입_마법;
        }
        if ($압축 === '🏹활' || ($압축 === '활') || $아이템 === '🏹 활') {
            return 무기_타입_활;
        }
        if (in_array($아이템, 단소_구간표시명목록(), true)
            || mb_strpos($아이템, '단소') !== false
            || mb_strpos($아이템, '젓가락') !== false
            || mb_strpos($아이템, '리코더') !== false
            || mb_strpos($아이템, '피리') !== false
            || mb_strpos($아이템, '대금') !== false
            || mb_strpos($아이템, '플루트') !== false
            || mb_strpos($아이템, '생황') !== false
            || mb_strpos($아이템, '나발') !== false
            || mb_strpos($아이템, '태평소') !== false
            || mb_strpos($아이템, '엑스칼리버') !== false
        ) {
            return 무기_타입_단소;
        }
        if (mb_strpos($아이템, '활') !== false) {
            return 무기_타입_활;
        }
        return 무기_타입_없음;
    }
}

if (!function_exists('무기_타입값')) {
    /**
     * 회원 row / 타입 int / item 문자열 → 무기타입
     * @param array|int|string|null $소스
     */
    function 무기_타입값($소스): int {
        if ($소스 === null || $소스 === '') {
            return 무기_타입_없음;
        }
        if (is_array($소스)) {
            if (array_key_exists('무기타입', $소스) && $소스['무기타입'] !== null && $소스['무기타입'] !== '') {
                $t = (int)$소스['무기타입'];
                if ($t >= 1 && $t <= 3) {
                    return $t;
                }
            }
            return 무기_타입_추정($소스['item'] ?? '');
        }
        if (is_int($소스) || (is_string($소스) && preg_match('/^[0-3]$/', $소스))) {
            $t = (int)$소스;
            return ($t >= 0 && $t <= 3) ? $t : 무기_타입_없음;
        }
        return 무기_타입_추정((string)$소스);
    }
}

if (!function_exists('무기_단소인가')) {
    function 무기_단소인가($소스): bool {
        return 무기_타입값($소스) === 무기_타입_단소;
    }
}
if (!function_exists('무기_활인가')) {
    function 무기_활인가($소스): bool {
        return 무기_타입값($소스) === 무기_타입_활;
    }
}
if (!function_exists('무기_마법인가')) {
    function 무기_마법인가($소스): bool {
        return 무기_타입값($소스) === 무기_타입_마법;
    }
}
if (!function_exists('무기_시전가능인가')) {
    function 무기_시전가능인가($소스): bool {
        $t = 무기_타입값($소스);
        return $t === 무기_타입_활 || $t === 무기_타입_단소 || $t === 무기_타입_마법;
    }
}
if (!function_exists('무기_단소활인가')) {
    function 무기_단소활인가($소스): bool {
        $t = 무기_타입값($소스);
        return $t === 무기_타입_활 || $t === 무기_타입_단소;
    }
}

if (!function_exists('무기_타입_스키마보장')) {
    /** 컬럼 추가 + 기존 item 기준 타입 채움 + 단소 표시명 동기화 (프로세스당 1회) */
    function 무기_타입_스키마보장(): void {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $col = @db_select("SHOW COLUMNS FROM tb_member LIKE '무기타입'");
        if (empty($col)) {
            @db_query("ALTER TABLE tb_member ADD COLUMN `무기타입` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=활 2=단소 3=마법' AFTER `item`");
        }

        // 레거시 item → 타입 (아직 0인 행만)
        @db_query("
          UPDATE tb_member
          SET 무기타입 = 3
          WHERE IFNULL(무기타입,0) = 0
            AND TRIM(COALESCE(item,'')) != ''
            AND REPLACE(TRIM(item), ' ', '') LIKE '%마법%'
        ");
        @db_query("
          UPDATE tb_member
          SET 무기타입 = 2
          WHERE IFNULL(무기타입,0) = 0
            AND TRIM(COALESCE(item,'')) != ''
            AND (
              REPLACE(TRIM(item), ' ', '') LIKE '%단소%'
              OR item LIKE '%젓가락%'
              OR item LIKE '%리코더%'
              OR item LIKE '%피리%'
              OR item LIKE '%대금%'
              OR item LIKE '%플루트%'
              OR item LIKE '%생황%'
              OR item LIKE '%나발%'
              OR item LIKE '%태평소%'
              OR item LIKE '%엑스칼리버%'
            )
        ");
        @db_query("
          UPDATE tb_member
          SET 무기타입 = 1
          WHERE IFNULL(무기타입,0) = 0
            AND TRIM(COALESCE(item,'')) != ''
            AND (
              REPLACE(TRIM(item), ' ', '') LIKE '%활%'
              OR TRIM(item) IN ('🏹활', '🏹 활')
            )
        ");

        // 단소 표시명 동기화 (강화 구간별)
        $rs = @db_query("
          SELECT name, enhance, item
          FROM tb_member
          WHERE 무기타입 = 2 AND TRIM(COALESCE(item,'')) != ''
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $닉 = trim((string)($row['name'] ?? ''));
                if ($닉 === '') {
                    continue;
                }
                $강화 = (int)($row['enhance'] ?? 0);
                $기대 = 단소_구간표시명($강화);
                $현재 = trim((string)($row['item'] ?? ''));
                if ($현재 === $기대) {
                    continue;
                }
                $닉_esc = addslashes($닉);
                $아이템_esc = addslashes($기대);
                @db_query("UPDATE tb_member SET `item` = '{$아이템_esc}' WHERE name = '{$닉_esc}' AND 무기타입 = 2 LIMIT 1");
            }
        }
    }
}

if (!function_exists('무기_장착_갱신')) {
    /**
     * 타입·강화에 맞게 item/무기타입/enhance 동기화 UPDATE
     * @param array $extra 추가 SET 절 (예: ['point = point - 1', '강화성공시간 = NOW()'])
     */
    function 무기_장착_갱신(string $닉, int $타입, int $강화, array $extra = []): bool {
        $닉 = trim($닉);
        if ($닉 === '' || !function_exists('db_query')) {
            return false;
        }
        무기_타입_스키마보장();
        $타입 = (int)$타입;
        $강화 = max(0, (int)$강화);
        $아이템 = ($타입 >= 1 && $타입 <= 3) ? 무기_표시아이템($타입, $강화) : '';
        $닉_esc = addslashes($닉);
        $아이템_esc = addslashes($아이템);
        $sets = [
            "`item` = " . ($아이템 === '' ? 'NULL' : "'{$아이템_esc}'"),
            "`무기타입` = " . (($타입 >= 1 && $타입 <= 3) ? $타입 : 0),
            "`enhance` = {$강화}",
        ];
        foreach ($extra as $frag) {
            $frag = trim((string)$frag);
            if ($frag !== '') {
                $sets[] = $frag;
            }
        }
        $sql = 'UPDATE tb_member SET ' . implode(', ', $sets) . " WHERE name = '{$닉_esc}' LIMIT 1";
        return (bool)db_query($sql);
    }
}

if (!function_exists('무기_강화후_표시동기화')) {
    /** 강화 성공 후 단소 등 표시명·타입 맞춤 (enhance는 이미 올렸거나 함께 갱신) */
    function 무기_강화후_표시동기화(string $닉, $타입또는아이템, int $다음강화, bool $enhance이미갱신 = true): void {
        $닉 = trim($닉);
        if ($닉 === '') {
            return;
        }
        무기_타입_스키마보장();
        $타입 = 무기_타입값($타입또는아이템);
        if ($타입 < 1 || $타입 > 3) {
            return;
        }
        $아이템 = 무기_표시아이템($타입, $다음강화);
        $닉_esc = addslashes($닉);
        $아이템_esc = addslashes($아이템);
        if ($enhance이미갱신) {
            db_query("UPDATE tb_member SET `item` = '{$아이템_esc}', `무기타입` = {$타입} WHERE name = '{$닉_esc}' LIMIT 1");
        } else {
            db_query("UPDATE tb_member SET `item` = '{$아이템_esc}', `무기타입` = {$타입}, `enhance` = {$다음강화} WHERE name = '{$닉_esc}' LIMIT 1");
        }
    }
}

if (!function_exists('무기_해제')) {
    function 무기_해제(string $닉, array $extra = []): bool {
        $닉 = trim($닉);
        if ($닉 === '' || !function_exists('db_query')) {
            return false;
        }
        무기_타입_스키마보장();
        $닉_esc = addslashes($닉);
        $sets = [
            "`item` = NULL",
            "`무기타입` = 0",
            "`enhance` = 0",
        ];
        foreach ($extra as $frag) {
            $frag = trim((string)$frag);
            if ($frag !== '') {
                $sets[] = $frag;
            }
        }
        return (bool)db_query('UPDATE tb_member SET ' . implode(', ', $sets) . " WHERE name = '{$닉_esc}' LIMIT 1");
    }
}
