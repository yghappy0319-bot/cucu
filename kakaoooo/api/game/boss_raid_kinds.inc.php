<?php
/**
 * 보스 종류 — 매일(또는 .보스변경) 6종 재생성 · 성향별 반격
 *
 * 성향(능력 분리 · 보호 0일 때):
 * - steal         냥탈취형 · 게임냥 탈취만
 * - item          냥약탈형 · 게임냥 탈취만(더 자주 표기용)
 * - item_dur      파괴형 · 무기 내구 10% 차감만
 * - item_dur_swap 환전형 · 본방냥 10%→게임냥 스왑만
 *
 * 작명 닉이 관리자(admin=1)면 HP +3만 · 본방냥 보상 0.5%~0.8%
 */

if (!function_exists('boss_raid_성향정의')) {
    /** @return array<string, array{label:string,desc:string}> */
    function boss_raid_성향정의(): array {
        $durPct = defined('BOSS_RAID_COUNTER_DUR_PCT') ? (int)BOSS_RAID_COUNTER_DUR_PCT : 10;
        $swapPct = defined('BOSS_RAID_COUNTER_SWAP_PCT') ? (int)BOSS_RAID_COUNTER_SWAP_PCT : 10;
        return [
            'steal' => [
                'label' => '냥탈취형',
                'desc' => '게임냥 탈취 전용',
            ],
            'item' => [
                'label' => '냥약탈형',
                'desc' => '게임냥 탈취 전용',
            ],
            'item_dur' => [
                'label' => '파괴형',
                'desc' => "무기 내구 {$durPct}% 차감 전용",
            ],
            'item_dur_swap' => [
                'label' => '환전형',
                'desc' => "본방냥 {$swapPct}% → 게임냥 스왑 전용",
            ],
        ];
    }
}

if (!function_exists('boss_raid_성향_옵션가중치')) {
    /**
     * 성향별 단일 능력 (섞지 않음)
     * @return array<string,int> point|durability|swap
     */
    function boss_raid_성향_옵션가중치($style): array {
        switch (trim((string)$style)) {
            case 'item_dur':
                return ['durability' => 10];
            case 'item_dur_swap':
                return ['swap' => 10];
            case 'item':
            case 'steal':
            default:
                return ['point' => 10];
        }
    }
}

if (!function_exists('boss_raid_성향_가중뽑기순서')) {
    /** 가중 1순위 + 나머지 섞은 시도 순서 */
    function boss_raid_성향_가중뽑기순서($style): array {
        $weights = boss_raid_성향_옵션가중치($style);
        $bag = [];
        foreach ($weights as $k => $w) {
            $n = max(0, (int)$w);
            for ($i = 0; $i < $n; $i++) {
                $bag[] = $k;
            }
        }
        if ($bag === []) {
            return ['point'];
        }
        $primary = $bag[random_int(0, count($bag) - 1)];
        $rest = array_values(array_diff(array_keys($weights), [$primary]));
        shuffle($rest);
        // 실패 시 대체: 성향 능력만 재시도하고, 최후엔 point
        $fallback = array_values(array_unique(array_merge($rest, ['point'])));
        return array_merge([$primary], $fallback);
    }
}

if (!function_exists('boss_raid_종류_테이블보장')) {
    function boss_raid_종류_테이블보장(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        @db_query("CREATE TABLE IF NOT EXISTS tb_boss_kind (
          idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          play_date DATE NOT NULL,
          slot TINYINT UNSIGNED NOT NULL DEFAULT 0,
          boss_key VARCHAR(48) NOT NULL,
          name VARCHAR(64) NOT NULL,
          emoji VARCHAR(16) NOT NULL DEFAULT '🐉',
          hp INT UNSIGNED NOT NULL DEFAULT 45000,
          reward_pct DECIMAL(8,4) NOT NULL DEFAULT 0.0100,
          style VARCHAR(32) NOT NULL DEFAULT 'steal',
          blurb VARCHAR(200) NOT NULL DEFAULT '',
          is_current TINYINT NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uk_boss_key (boss_key),
          KEY idx_date_current (play_date, is_current),
          KEY idx_date_slot (play_date, slot)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 닉 섞인 보스명 여유
        $nameCol = @db_select("SHOW COLUMNS FROM tb_boss_kind LIKE 'name'");
        $nameType = strtolower((string)($nameCol['Type'] ?? ''));
        if ($nameType !== '' && strpos($nameType, 'varchar(64)') === false) {
            @db_query("ALTER TABLE tb_boss_kind MODIFY name VARCHAR(64) NOT NULL");
        }

        // 생성 범위 상향에 맞춰 오늘 종류·진행 중 보스 HP 1회 보정
        if (function_exists('boss_raid_기존HP_상향_1회')) {
            boss_raid_기존HP_상향_1회();
        }
    }
}

if (!function_exists('boss_raid_기존HP_상향_1회')) {
    /**
     * 이미 나온 오늘 보스 종류 + 진행 중 레이드 + HP 스냅샷을 단계별로 상향
     * config.보스HP상향: 0→1 (×1.15) · 1→2 (×1.15, 추가 상향)
     */
    function boss_raid_기존HP_상향_1회(): void {
        static $ran = false;
        if ($ran || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $ran = true;

        $tbl = @db_select("SHOW TABLES LIKE 'config'");
        if (empty($tbl)) {
            return;
        }
        $col = @db_select("SHOW COLUMNS FROM config LIKE '보스HP상향'");
        if (empty($col['Field'])) {
            @db_query("ALTER TABLE config ADD COLUMN `보스HP상향` TINYINT UNSIGNED NOT NULL DEFAULT 0");
            $col = @db_select("SHOW COLUMNS FROM config LIKE '보스HP상향'");
            if (empty($col['Field'])) {
                return;
            }
        }
        $flag = @db_select("SELECT IFNULL(`보스HP상향`, 0) AS v FROM config LIMIT 1");
        $step = (int)($flag['v'] ?? 0);
        // 2단계까지 적용 (이번 상향 = step 1 → 2)
        if ($step >= 2) {
            return;
        }

        $mult = 1.15;
        $date_esc = addslashes(date('Y-m-d'));
        $hpCap = 250000;

        // 고정 HP 스냅샷 범위도 동일 비율 상향 (다음 종류 생성 반영)
        $snap = @db_select("
          SELECT
            IFNULL(`보스HP_MIN`, 0) AS mn,
            IFNULL(`보스HP_MAX`, 0) AS mx,
            IFNULL(`보스HP_CENTER`, 0) AS cen
          FROM config LIMIT 1
        ");
        $mn = (int)($snap['mn'] ?? 0);
        $mx = (int)($snap['mx'] ?? 0);
        $cen = (int)($snap['cen'] ?? 0);
        if ($mn > 0 && $mx >= $mn) {
            $newMn = (int)max(1000, min($hpCap, (int)round($mn * $mult)));
            $newMx = (int)max($newMn + 1000, min($hpCap, (int)round($mx * $mult)));
            $newCen = (int)max($newMn, min($newMx, (int)round(($cen > 0 ? $cen : (($mn + $mx) / 2)) * $mult)));
            @db_query("
              UPDATE config SET
                `보스HP_MIN` = {$newMn},
                `보스HP_MAX` = {$newMx},
                `보스HP_CENTER` = {$newCen}
              LIMIT 1
            ");
        }

        // 오늘 로테이션 후보 6종
        @db_query("
          UPDATE tb_boss_kind
          SET hp = LEAST({$hpCap}, GREATEST(1000, ROUND(hp * {$mult})))
          WHERE play_date = '{$date_esc}' AND is_current = 1
        ");

        // 진행 중 보스: 최대·잔여 HP 동일 비율로 상향 (남은 % 유지)
        $active = @db_select("
          SELECT idx, hp_max, hp_now
          FROM tb_boss_raid
          WHERE status = 0
          ORDER BY idx DESC
          LIMIT 1
        ");
        if (is_array($active) && !empty($active['idx'])) {
            $max = max(1, (int)($active['hp_max'] ?? 0));
            $now = max(0, (int)($active['hp_now'] ?? 0));
            $newMax = (int)max(1000, min($hpCap, (int)round($max * $mult)));
            $newNow = (int)max(0, min($newMax, (int)round($newMax * ($now / $max))));
            if ($now > 0 && $newNow < 1) {
                $newNow = 1;
            }
            $idx = (int)$active['idx'];
            @db_query("
              UPDATE tb_boss_raid
              SET hp_max = {$newMax}, hp_now = {$newNow}
              WHERE idx = {$idx} AND status = 0
              LIMIT 1
            ");
        }

        $next = $step + 1;
        @db_query("UPDATE config SET `보스HP상향` = {$next} LIMIT 1");
    }
}

if (!function_exists('boss_raid_종류_이름풀')) {
    /** @return array{0:list<string>,1:list<string>,2:list<string>,3:list<string>} */
    function boss_raid_종류_이름풀(): array {
        $prefix = ['암흑', '혈', '천뢰', '심연', '금강', '화염', '설월', '독', '강철', '영혼', '뇌명', '적월', '백야', '황천', '묵흑', '창염'];
        $mid = ['', '검은', '붉은', '푸른', '하얀', '황금', '은빛', '지옥', '천상', '고대'];
        $suffix = ['룡', '랑', '수호', '마왕', '거인', '갑충', '여우', '제왕', '마수', '기사', '악령', '군주', '야차', '호법', '사냥개', '봉황'];
        $emojis = ['🐉', '🐺', '🦊', '🪲', '⚡', '🦑', '🦁', '🐯', '🦅', '🦇', '🦂', '🐍', '💀', '🧿', '🔥', '❄️'];
        return [$prefix, $mid, $suffix, $emojis];
    }
}

if (!function_exists('boss_raid_종류_닉제외목록')) {
    /** @return list<string> */
    function boss_raid_종류_닉제외목록(): array {
        return ['오픈', '신입', '왕벌', '왕남', '요원', '지또', '우지', '봇', '관리'];
    }
}

if (!function_exists('boss_raid_종류_닉풀')) {
    /**
     * 활성 회원 닉 랜덤 추출 (2글자 위주)
     * @return list<string>
     */
    function boss_raid_종류_닉풀(int $need = 3): array {
        $need = max(1, min(12, $need));
        $exclude = boss_raid_종류_닉제외목록();
        $excludeSql = implode("','", array_map('addslashes', $exclude));
        $out = [];
        $seen = [];
        $rs = @db_query("
          SELECT name FROM tb_member
          WHERE status = 0
            AND name IS NOT NULL AND TRIM(name) <> ''
            AND CHAR_LENGTH(TRIM(name)) BETWEEN 2 AND 4
            AND name NOT IN ('{$excludeSql}')
          ORDER BY RAND()
          LIMIT " . (int)($need * 4)
        );
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $n = trim((string)($row['name'] ?? ''));
                if ($n === '' || isset($seen[$n])) {
                    continue;
                }
                $seen[$n] = true;
                $out[] = $n;
                if (count($out) >= $need) {
                    break;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('boss_raid_종류_닉템플릿')) {
    /** {n} = 닉 · 띄어쓰기 있는 문장형 보스명 (다양·웃김 위주) */
    function boss_raid_종류_닉템플릿(): array {
        return [
            // 감정·상태
            '분노한 {n}',
            '폭주하는 {n}',
            '흑화된 {n}',
            '각성한 {n}',
            '수상한 {n}',
            '배고픈 {n}',
            '잠 못 자는 {n}',
            '오늘만 사는 {n}',
            '억울한 {n}',
            '삐진 {n}',
            '멘탈 나간 {n}',
            '과몰입한 {n}',
            '진지한 {n}',
            '웃고 있는 {n}',
            '조용히 화난 {n}',
            '갑자기 진지해진 {n}',
            // 소유감·서사
            '{n}의 저주',
            '{n}의 복수',
            '{n}의 그림자',
            '{n}의 전설',
            '{n}의 눈물',
            '{n}의 야망',
            '{n}의 마지막 한 수',
            '{n}의 반격',
            '{n}의 각성',
            '{n}, 그 이름',
            '{n}… 돌아왔다',
            '돌아온 {n}',
            '다시 나타난 {n}',
            '잊혀진 {n}',
            '전설로 남은 {n}',
            // 장소·분위기
            '심연의 {n}',
            '공창의 {n}',
            '방구석의 {n}',
            '심야의 {n}',
            '새벽의 {n}',
            '지하실의 {n}',
            '카톡방의 {n}',
            '음성방의 {n}',
            '단톡의 끝판왕 {n}',
            // 일상 개그
            '퇴근각 {n}',
            '지각왕 {n}',
            '야식 먹는 {n}',
            '커피 없는 {n}',
            '충전 중인 {n}',
            '읽씹의 제왕 {n}',
            '답장 안 하는 {n}',
            '접속 중인 {n}',
            '오프라인의 {n}',
            '와이파이 찾는 {n}',
            '배터리 1% {n}',
            '공지 안 읽은 {n}',
            '룰 모르는 {n}',
            '룰 설명하는 {n}',
            '한 판만 더 하는 {n}',
            '오늘 마지막이라던 {n}',
            // 냥·레이드 톤
            '냥 탈취왕 {n}',
            '냥을 노리는 {n}',
            '냥을 지키는 {n}',
            '풀피로 등장한 {n}',
            '반격 특화 {n}',
            '크리 갈망 {n}',
            '드롭 갈망 {n}',
            '보상만 보는 {n}',
            'MVP를 꿈꾸는 {n}',
            '딜량 자랑 {n}',
            // 호칭·직함
            '{n} 씨 마왕',
            '대마왕 {n}',
            '최종 보스 {n}',
            '중간 보스 {n}',
            '숨은 보스 {n}',
            '가짜 보스 {n}',
            '진짜 보스 {n}',
            '오늘의 주인공 {n}',
            '특별 출연 {n}',
            '게스트 보스 {n}',
            // 판타지 믹스
            '전설의 {n}',
            '고대의 {n}',
            '타락한 {n}',
            '봉인된 {n}',
            '해방된 {n}',
            '부활한 {n}',
            '저주받은 {n}',
            '선택받은 {n}',
            '버림받은 {n}',
            '미쳐버린 {n} 룡',
            '배고픈 {n} 거인',
            '미친 {n} 랑',
            '불타는 {n}',
            '얼어붙은 {n}',
            '번개 맞은 {n}',
            // 상황극
            '왜 나냐는 {n}',
            '억지로 나온 {n}',
            '뽑혀 나온 {n}',
            '우연히 보스 된 {n}',
            '본인도 놀란 {n}',
            '도망가고 싶은 {n}',
            '아직 준비 안 된 {n}',
            '각오한 {n}',
            '복수하러 온 {n}',
            '냥 뺏으러 온 {n}',
            '친구들 놀래키는 {n}',
            '이름값 하는 {n}',
            '이름값 못 하는 {n}',
            '조용히 센 {n}',
            '겉바속촉 {n}',
            '겉은 귀엽고 속은 {n}',
        ];
    }
}

if (!function_exists('boss_raid_종류_GPT이름묶음')) {
    /**
     * 친구 닉을 포함한 보스명 일괄 생성 (실패 시 빈 배열)
     * @param list<string> $nicks
     * @return array<string,string> nick => bossName
     */
    function boss_raid_종류_GPT이름묶음(array $nicks): array {
        $clean = [];
        $seen = [];
        foreach ($nicks as $n) {
            $n = trim((string)$n);
            if ($n === '' || isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            $clean[] = $n;
        }
        if ($clean === []) {
            return [];
        }
        if (!function_exists('callGPT')) {
            $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            if ($root === '') {
                $root = dirname(__DIR__, 2);
            }
            if (!function_exists('db_query') && is_file($root . '/lib/_function.php')) {
                include_once $root . '/lib/_function.php';
            }
            if (is_file($root . '/api/function.php')) {
                include_once $root . '/api/function.php';
            }
            // callGPT 본체는 config.php
            if (!function_exists('callGPT') && is_file($root . '/api/config.php')) {
                include_once $root . '/api/config.php';
            }
        }
        if (!function_exists('callGPT')) {
            return [];
        }

        $list = implode(', ', $clean);
        $system = '너는 한국 오픈채팅 보스레이드 작명가야. 짧고 웃기거나 간지 나는 한국어 보스 이름만 만든다. 설명 없이 JSON만 출력한다.';
        $user = "친구 닉 목록: {$list}\n"
            . "각 닉마다 보스 이름을 하나씩 만들어라.\n"
            . "규칙:\n"
            . "1) 해당 닉 글자가 이름에 반드시 그대로 들어갈 것\n"
            . "2) 한글 위주, 2~18자, 서로 다른 이름\n"
            . "3) 판타지·개그·상태이상 느낌 OK (예: 분노한 ○○, ○○의 저주, 흑화된 ○○)\n"
            . "4) 비속어·성적 표현·실명 비하 금지\n"
            . "출력은 JSON 객체만. 키=닉, 값=보스이름.\n"
            . '예: {"준호":"분노한 준호","루루":"루루의 그림자"}';

        $raw = trim((string)@callGPT($user, $system, 320));
        if ($raw === '') {
            return [];
        }
        if (preg_match('/\{[\s\S]*\}/u', $raw, $m)) {
            $raw = $m[0];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($clean as $nick) {
            $name = trim((string)($decoded[$nick] ?? ''));
            if ($name === '') {
                continue;
            }
            $name = preg_replace('/\s+/u', ' ', $name);
            if (function_exists('mb_substr')) {
                $name = mb_substr($name, 0, 48, 'UTF-8');
            } else {
                $name = substr($name, 0, 48);
            }
            // 닉 포함 여부 (대소문자 무시·공백 무시)
            $hay = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            $needle = function_exists('mb_strtolower') ? mb_strtolower($nick, 'UTF-8') : strtolower($nick);
            $ok = (function_exists('mb_strpos') ? mb_strpos($hay, $needle, 0, 'UTF-8') : strpos($hay, $needle));
            if ($ok === false) {
                continue;
            }
            $out[$nick] = $name;
        }
        return $out;
    }
}

if (!function_exists('boss_raid_종류_닉섞은이름')) {
    /**
     * @param string|null $gptName GPT가 준 이름(닉 포함) · 없으면 템플릿
     * @return array{name:string,emoji:string}
     */
    function boss_raid_종류_닉섞은이름(string $nick, array &$usedNames, ?string $gptName = null): array {
        $nick = trim($nick);
        if ($nick === '') {
            return boss_raid_종류_랜덤이름($usedNames);
        }
        [, , , $emojis] = boss_raid_종류_이름풀();
        $emoji = $emojis[random_int(0, count($emojis) - 1)];

        $candidates = [];
        if ($gptName !== null && trim($gptName) !== '') {
            $candidates[] = trim($gptName);
        }
        $templates = boss_raid_종류_닉템플릿();
        $tpl = $templates[random_int(0, count($templates) - 1)];
        $candidates[] = trim(str_replace('{n}', $nick, $tpl));
        $candidates[] = '돌아온 ' . $nick;

        foreach ($candidates as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string)$name));
            if (function_exists('mb_substr')) {
                $name = mb_substr($name, 0, 48, 'UTF-8');
            } else {
                $name = substr($name, 0, 48);
            }
            if ($name === '' || isset($usedNames[$name])) {
                continue;
            }
            $usedNames[$name] = true;
            return ['name' => $name, 'emoji' => $emoji];
        }
        $fallback = $nick . ' 보스' . random_int(10, 99);
        $usedNames[$fallback] = true;
        return ['name' => $fallback, 'emoji' => $emoji];
    }
}

if (!function_exists('boss_raid_종류_랜덤이름')) {
    function boss_raid_종류_랜덤이름(array &$usedNames): array {
        [$prefix, $mid, $suffix, $emojis] = boss_raid_종류_이름풀();
        for ($try = 0; $try < 40; $try++) {
            $p = $prefix[random_int(0, count($prefix) - 1)];
            $m = $mid[random_int(0, count($mid) - 1)];
            $s = $suffix[random_int(0, count($suffix) - 1)];
            $name = $m !== '' ? ($p . $m . $s) : ($p . $s);
            if (isset($usedNames[$name])) {
                continue;
            }
            $usedNames[$name] = true;
            $emoji = $emojis[random_int(0, count($emojis) - 1)];
            return ['name' => $name, 'emoji' => $emoji];
        }
        $fallback = '변이보스' . random_int(10, 99);
        $usedNames[$fallback] = true;
        return ['name' => $fallback, 'emoji' => '🐉'];
    }
}

if (!function_exists('boss_raid_종류_성향배치')) {
    /** 6슬롯에 성향 다양하게 배치 */
    function boss_raid_종류_성향배치(): array {
        $must = ['steal', 'item', 'item_dur', 'item_dur_swap'];
        $pool = array_keys(boss_raid_성향정의());
        $styles = $must;
        while (count($styles) < 6) {
            $styles[] = $pool[random_int(0, count($pool) - 1)];
        }
        shuffle($styles);
        return $styles;
    }
}

if (!function_exists('boss_raid_종류_행정규화')) {
    /** @return array{key:string,name:string,emoji:string,hp:int,reward_pct:float,blurb:string,style:string,style_label:string,style_desc:string} */
    function boss_raid_종류_행정규화(array $row): array {
        $style = trim((string)($row['style'] ?? 'steal'));
        $defs = boss_raid_성향정의();
        if (!isset($defs[$style])) {
            $style = 'steal';
        }
        $label = (string)($defs[$style]['label'] ?? '냥탈취형');
        $desc = (string)($defs[$style]['desc'] ?? '');
        $name = trim((string)($row['name'] ?? '방룡'));
        $emoji = trim((string)($row['emoji'] ?? '🐉'));
        $hp = max(1000, (int)($row['hp'] ?? 45000));
        $pct = (float)($row['reward_pct'] ?? 0.01);
        if ($pct <= 0) {
            $pct = 0.01;
        }
        $blurb = trim((string)($row['blurb'] ?? ''));
        if ($blurb === '') {
            $blurb = "{$label} · {$desc}";
        }
        return [
            'key' => trim((string)($row['boss_key'] ?? $row['key'] ?? 'bangryong')),
            'name' => $name,
            'emoji' => $emoji !== '' ? $emoji : '🐉',
            'hp' => $hp,
            'reward_pct' => $pct,
            'blurb' => $blurb,
            'style' => $style,
            'style_label' => $label,
            'style_desc' => $desc,
        ];
    }
}

if (!function_exists('boss_raid_종류_닉관리자인가')) {
    /** 작명에 쓰인 닉이 관리자(tb_member.admin=1 / $관리자)인지 */
    function boss_raid_종류_닉관리자인가($nick): bool {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return false;
        }

        global $관리자;
        if (is_array($관리자) && $관리자 !== []) {
            if (in_array($nick, $관리자, true)) {
                return true;
            }
            if (function_exists('getTwoCharNick')) {
                $two = trim((string)getTwoCharNick($nick));
                if ($two !== '' && in_array($two, $관리자, true)) {
                    return true;
                }
            }
        }

        $esc = addslashes($nick);
        $row = @db_select("
            SELECT IFNULL(admin, 0) AS a
            FROM tb_member
            WHERE name = '{$esc}' AND status = 0
            LIMIT 1
        ");
        if ((int)($row['a'] ?? 0) === 1) {
            return true;
        }
        if (function_exists('getTwoCharNick')) {
            $two = trim((string)getTwoCharNick($nick));
            if ($two !== '' && $two !== $nick) {
                $two_esc = addslashes($two);
                $row2 = @db_select("
                    SELECT IFNULL(admin, 0) AS a
                    FROM tb_member
                    WHERE name = '{$two_esc}' AND status = 0
                    LIMIT 1
                ");
                if ((int)($row2['a'] ?? 0) === 1) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('boss_raid_종류_생성하루')) {
    /**
     * 해당 날짜 6종 생성 (is_current=1). 기존 당일 current 는 0으로.
     * 작명 닉이 관리자면 HP +3만 · 보상 0.5%~0.8%
     * @return list<array>
     */
    function boss_raid_종류_생성하루($date = null): array {
        boss_raid_종류_테이블보장();
        $date = $date !== null ? (string)$date : date('Y-m-d');
        $date_esc = addslashes($date);

        @db_query("UPDATE tb_boss_kind SET is_current = 0 WHERE play_date = '{$date_esc}' AND is_current = 1");

        $styles = boss_raid_종류_성향배치();
        $usedNames = [];
        $created = [];
        $stamp = date('YmdHis');
        $adminHpBonus = 30000;

        // 6마리 전부 친구 닉 기반 작명 (GPT 우선 · 실패 시 템플릿)
        $nickPool = boss_raid_종류_닉풀(6);
        if ($nickPool !== [] && count($nickPool) < 6) {
            $base = $nickPool;
            while (count($nickPool) < 6) {
                $nickPool[] = $base[random_int(0, count($base) - 1)];
            }
        }
        $gptMap = $nickPool !== [] ? boss_raid_종류_GPT이름묶음($nickPool) : [];

        for ($slot = 0; $slot < 6; $slot++) {
            $nick = isset($nickPool[$slot]) ? (string)$nickPool[$slot] : '';
            $isAdminNick = false;
            if ($nick !== '') {
                $gptName = isset($gptMap[$nick]) ? (string)$gptMap[$nick] : null;
                // 같은 닉이 여러 슬롯이면 GPT 이름은 한 번만 쓰고 나머지는 템플릿
                if ($gptName !== null) {
                    unset($gptMap[$nick]);
                }
                $nm = boss_raid_종류_닉섞은이름($nick, $usedNames, $gptName);
                $nickTag = $nick;
                $isAdminNick = boss_raid_종류_닉관리자인가($nick);
            } else {
                $nm = boss_raid_종류_랜덤이름($usedNames);
                $nickTag = '';
            }
            $style = $styles[$slot] ?? 'steal';
            $defs = boss_raid_성향정의();
            $label = (string)($defs[$style]['label'] ?? '냥탈취형');
            $desc = (string)($defs[$style]['desc'] ?? '');
            // 1회 스냅샷 고정 범위(기대딜 총합×참여율 ±15%)에서 추첨
            $hp = function_exists('boss_raid_HP랜덤')
                ? (int)boss_raid_HP랜덤()
                : random_int(38000, 78000);
            if ($isAdminNick) {
                $hp += $adminHpBonus;
                // 관리자 닉 보스: 0.5% ~ 0.8%
                $pct = random_int(50, 80) / 10000;
            } else {
                // 일반: 0.3% ~ 0.5%
                $pct = random_int(30, 50) / 10000;
            }
            $key = sprintf('b%s_%d_%s', $stamp, $slot + 1, bin2hex(random_bytes(2)));
            if ($nickTag !== '') {
                $blurb = $isAdminNick
                    ? "{$label} · {$desc} · 관리자 특별출연 {$nickTag} · HP+3만 · 보상↑"
                    : "{$label} · {$desc} · 오늘의 특별출연 {$nickTag}";
            } else {
                $blurb = "{$label} · {$desc}";
            }
            $key_esc = addslashes($key);
            $name_esc = addslashes($nm['name']);
            $emoji_esc = addslashes($nm['emoji']);
            $style_esc = addslashes($style);
            $blurb_esc = addslashes($blurb);
            $pct_sql = number_format($pct, 4, '.', '');

            @db_query("
              INSERT INTO tb_boss_kind
                (play_date, slot, boss_key, name, emoji, hp, reward_pct, style, blurb, is_current)
              VALUES
                ('{$date_esc}', {$slot}, '{$key_esc}', '{$name_esc}', '{$emoji_esc}',
                 {$hp}, {$pct_sql}, '{$style_esc}', '{$blurb_esc}', 1)
            ");

            $created[] = boss_raid_종류_행정규화([
                'boss_key' => $key,
                'name' => $nm['name'],
                'emoji' => $nm['emoji'],
                'hp' => $hp,
                'reward_pct' => $pct,
                'style' => $style,
                'blurb' => $blurb,
            ]);
        }
        return $created;
    }
}

if (!function_exists('boss_raid_오늘종류목록')) {
    /**
     * 오늘(또는 지정일) is_current=1 목록. 없으면 자동 생성.
     * @return array<string, array>
     */
    function boss_raid_오늘종류목록($date = null, $forceRegen = false): array {
        boss_raid_종류_테이블보장();
        $date = $date !== null ? (string)$date : date('Y-m-d');
        $date_esc = addslashes($date);

        if ($forceRegen) {
            $rows = boss_raid_종류_생성하루($date);
            $out = [];
            foreach ($rows as $r) {
                $out[$r['key']] = $r;
            }
            return $out;
        }

        $list = [];
        $rs = @db_query("
          SELECT * FROM tb_boss_kind
          WHERE play_date = '{$date_esc}' AND is_current = 1
          ORDER BY slot ASC, idx ASC
          LIMIT 6
        ");
        if ($rs) {
            while ($row = db_fetch($rs)) {
                $norm = boss_raid_종류_행정규화(is_array($row) ? $row : []);
                if ($norm['key'] !== '') {
                    $list[$norm['key']] = $norm;
                }
            }
        }
        if (count($list) < 6) {
            $rows = boss_raid_종류_생성하루($date);
            $list = [];
            foreach ($rows as $r) {
                $list[$r['key']] = $r;
            }
        }
        return $list;
    }
}

if (!function_exists('boss_raid_종류_키로조회')) {
    function boss_raid_종류_키로조회($key): ?array {
        $key = trim((string)$key);
        if ($key === '') {
            return null;
        }
        boss_raid_종류_테이블보장();
        $esc = addslashes($key);
        $row = @db_select("SELECT * FROM tb_boss_kind WHERE boss_key = '{$esc}' LIMIT 1");
        if (!is_array($row) || empty($row['boss_key'])) {
            return null;
        }
        return boss_raid_종류_행정규화($row);
    }
}

if (!function_exists('boss_raid_종류_변경')) {
    /** `.보스변경` — 오늘 6종 전체 재작성 */
    function boss_raid_종류_변경(): array {
        $list = boss_raid_오늘종류목록(null, true);
        $msg = "🎲 보스 종류 변경 완료 (" . date('Y-m-d') . ")\n";
        $msg .= "오늘 출현 후보 6종이 새로 생성됐어요.\n\n";
        $n = 1;
        foreach ($list as $b) {
            $pct = function_exists('boss_raid_보상비율문구')
                ? boss_raid_보상비율문구((float)$b['reward_pct'])
                : (round((float)$b['reward_pct'] * 100, 2) . '%');
            $msg .= "{$n}) {$b['emoji']} {$b['name']}\n";
            $msg .= "   HP " . number_format((int)$b['hp']) . " · 보상 {$pct}\n";
            $msg .= "   성향: {$b['style_label']} — {$b['style_desc']}\n\n";
            $n++;
        }
        $msg .= "※ 진행 중 보스는 기존 그대로 · 다음 출현부터 새 종류가 적용됩니다.";
        return [
            'ok' => true,
            'data' => rtrim($msg),
            'list' => array_values($list),
        ];
    }
}

if (!function_exists('boss_raid_반격_본방스왑')) {
    /**
     * 본방냥 고정% → 게임냥 자동 스왑 (기존 .스왑 환율·삭제 규칙)
     * @return array{ok:bool,detail?:string,amount?:string}|null 실패 시 null
     */
    function boss_raid_반격_본방스왑($nick): ?array {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return null;
        }
        $swapPath = __DIR__ . '/swap.inc.php';
        if (is_file($swapPath)) {
            require_once $swapPath;
        }
        if (!function_exists('스왑_견적계산')) {
            return null;
        }

        $esc = addslashes($nick);
        $회원 = db_select("SELECT CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$esc}' AND status = 0 LIMIT 1");
        $보유 = round((float)($회원['newpoint'] ?? 0), 1);
        if ($보유 < 0.1) {
            return null;
        }
        $pct = defined('BOSS_RAID_COUNTER_SWAP_PCT') ? (int)BOSS_RAID_COUNTER_SWAP_PCT : 10;
        $pct = max(1, min(100, $pct));
        $금액 = round($보유 * ($pct / 100), 1);
        if ($금액 < 0.1) {
            return null;
        }

        // 최소금액 검사 생략 (반격용)
        $견적 = 스왑_견적계산('np2pt', $금액, false, null, false);
        if (empty($견적['ok'])) {
            return null;
        }
        $차감 = (float)($견적['차감_np'] ?? 0);
        $지급_pt = function_exists('스왑_정수문자열')
            ? 스왑_정수문자열($견적['지급_pt'] ?? 0)
            : preg_replace('/[^\d]/', '', (string)($견적['지급_pt'] ?? '0'));
        $지급_pt = ltrim((string)$지급_pt, '0') ?: '0';
        if ($차감 < 0.1 || $지급_pt === '0') {
            return null;
        }
        if ($보유 + 1e-9 < $차감) {
            return null;
        }

        if (function_exists('스왑_point_컬럼_보장')) {
            스왑_point_컬럼_보장();
        }
        $sqlPt = function_exists('냥_SQL정수') ? 냥_SQL정수($지급_pt) : $지급_pt;
        db_query("
          UPDATE tb_member
          SET newpoint = newpoint - {$차감},
              point = point + {$sqlPt}
          WHERE name = '{$esc}' AND status = 0 AND newpoint >= {$차감}
          LIMIT 1
        ");

        $npDisp = function_exists('newpoint표시') ? newpoint표시($차감) : number_format($차감, 1);
        $ptDisp = function_exists('냥축약표시') ? 냥축약표시($지급_pt) : number_format((float)$지급_pt);
        $detail = "본방냥 {$npDisp} → 게임냥 {$ptDisp} 강제스왑 (보유 {$pct}%)";
        if (function_exists('지급로그')) {
            지급로그('보스반격-스왑', $nick, "본방{$pct}%", 0, $지급_pt);
        }
        return [
            'ok' => true,
            'detail' => $detail,
            'amount' => (string)$지급_pt,
        ];
    }
}
