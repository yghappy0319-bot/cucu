<?php
/**
 * 마피아 — 단일 글로벌 게임 (방 없음)
 * 낮/밤 시계 10분 정각 전환(…:00/:10/:20/:30/:40/:50) · 낮 투표 1·2등 확정처형 · 3등 50%
 * 주말(토·일) 1회 · 종료 후 다음 라운드는 주말 오전 10시(서울) 시작
 */
if (!defined('MAFIA_ADMIN_NICK')) {
    define('MAFIA_ADMIN_NICK', '민호');
}
if (!defined('MAFIA_PHASE_ALIGN_SEC')) {
    /** 낮/밤 전환 격자(초). 600=10분 정각(12:00부터 :00/:10/:20/:30/:40/:50) */
    define('MAFIA_PHASE_ALIGN_SEC', 600);
}
if (!defined('MAFIA_PHASE_ALIGN_HOUR')) {
    /** 호환용 · ALIGN_SEC 사용 (true여도 ALIGN_SEC 우선) */
    define('MAFIA_PHASE_ALIGN_HOUR', false);
}
if (!defined('MAFIA_PHASE_SEC')) {
    /** 폴백·비정렬 시 길이 */
    define('MAFIA_PHASE_SEC', 600);
}
if (!defined('MAFIA_PHASE_MIN_REMAIN_SEC')) {
    /** 다음 격자까지 이보다 짧으면 그다음 칸으로 */
    define('MAFIA_PHASE_MIN_REMAIN_SEC', 60);
}
if (!defined('MAFIA_DAILY_START_HOUR')) {
    /** 주말(토·일) 라운드 시작 시각 (0–23, Asia/Seoul 기준) */
    define('MAFIA_DAILY_START_HOUR', 10);
}
if (!defined('MAFIA_REDIS_SEC')) {
    /** 레거시 호환 · 실제 재시작은 MAFIA_DAILY_START_HOUR */
    define('MAFIA_REDIS_SEC', 600);
}
if (!defined('MAFIA_DAY_EXECUTE')) {
    /** 낮 투표 처형 후보 인원 (득표 상위 N명) */
    define('MAFIA_DAY_EXECUTE', 3);
}
if (!defined('MAFIA_DAY_EXECUTE_GUARANTEED')) {
    /** 득표 상위 이 순위까지는 무조건 처형 (1·2등) */
    define('MAFIA_DAY_EXECUTE_GUARANTEED', 2);
}
if (!defined('MAFIA_DAY_EXECUTE_CHANCE')) {
    /** 확정 순위 초과 후보(3등) 처형 확률 */
    define('MAFIA_DAY_EXECUTE_CHANCE', 0.5);
}
if (!defined('MAFIA_DAY_FORCE_ELIM_FROM')) {
    /** 이 낮 회차부터 투표 0건이면 생존자 1명 강제탈락 (2=두 번째 낮) */
    define('MAFIA_DAY_FORCE_ELIM_FROM', 2);
}
if (!defined('MAFIA_DAY_VOTES_DEFAULT')) {
    /** 일반 역할 낮 투표 최대 수 */
    define('MAFIA_DAY_VOTES_DEFAULT', 2);
}
if (!defined('MAFIA_DAY_VOTES_POLITICIAN')) {
    /** 정치인(시민측) 낮 투표 최대 수 */
    define('MAFIA_DAY_VOTES_POLITICIAN', 3);
}
if (!defined('MAFIA_DAY_VOTES_MAFIA')) {
    /** 마피아 낮 투표 최대 수 */
    define('MAFIA_DAY_VOTES_MAFIA', 3);
}
if (!defined('MAFIA_NIGHT_TARGETS')) {
    /** 마피아 밤 살해 지목 최대 수 */
    define('MAFIA_NIGHT_TARGETS', 2);
}
if (!defined('MAFIA_MAFIA_RATIO')) {
    define('MAFIA_MAFIA_RATIO', 0.15);
}
if (!defined('MAFIA_CATCH_REWARD_RATE')) {
    /** 낮 투표로 마피아 적발 시 본방냥 총합 대비 보상 비율 (투표 시민에게 균등 분배) */
    define('MAFIA_CATCH_REWARD_RATE', 0.005);
}
if (!defined('MAFIA_WIN_REWARD_RATE')) {
    /** 마피아 승리 시 본방냥 총합 대비 보상 비율 (생존 마피아에게 균등 분배) */
    define('MAFIA_WIN_REWARD_RATE', 0.05);
}
if (!defined('MAFIA_TOWN_WIN_REWARD_RATE')) {
    /** 시민 승리 시 생존자 1인당 본방냥 총합 대비 성공보수 비율 */
    define('MAFIA_TOWN_WIN_REWARD_RATE', 0.01);
}
if (!defined('MAFIA_DOCTOR_SAVE_FEE_RATE')) {
    /** 의사 보호 성공 시 피보호자 → 의사 이전액 = 본방냥 총합 × 이 비율 */
    define('MAFIA_DOCTOR_SAVE_FEE_RATE', 0.01);
}
if (!defined('MAFIA_ACTIVITY_DAYS')) {
    /** 마피아 배정용 공창(tb_msg) 참여도 집계 일수 */
    define('MAFIA_ACTIVITY_DAYS', 7);
}
if (!defined('MAFIA_ACTIVITY_WEIGHT_BASE')) {
    /** 전원 기본 가중 — 높을수록 무발화도 잘 섞임 */
    define('MAFIA_ACTIVITY_WEIGHT_BASE', 10.0);
}
if (!defined('MAFIA_ACTIVITY_WEIGHT_BOOST')) {
    /** √타수 가산 배율 (약하게) */
    define('MAFIA_ACTIVITY_WEIGHT_BOOST', 0.35);
}
if (!defined('MAFIA_ACTIVITY_WEIGHT_CAP')) {
    /** √타수 가산 상한 — 상위권 독식 방지 */
    define('MAFIA_ACTIVITY_WEIGHT_CAP', 8.0);
}
if (!defined('MAFIA_ACTIVITY_WEIGHTED_RATIO')) {
    /** 마피아 자리 중 공창가중 추첨 비율 (나머지는 완전 랜덤) */
    define('MAFIA_ACTIVITY_WEIGHTED_RATIO', 0.4);
}
if (!defined('MAFIA_MISSION_REWARD_RATE')) {
    /** 마피아 공통미션 성공 시 누적 = 본방냥 총합 × 이 비율 (0.3%) */
    define('MAFIA_MISSION_REWARD_RATE', 0.003);
}
if (!defined('MAFIA_MISSION_REWARD_NP')) {
    /** @deprecated 고정액 대신 MAFIA_MISSION_REWARD_RATE 사용 */
    define('MAFIA_MISSION_REWARD_NP', 0);
}
if (!defined('MAFIA_MISSION_MAX_FAILS')) {
    /** 마피아 개인 미션 실패 허용 횟수 · 도달 시 비마피아와 역할 랜덤 교체 */
    define('MAFIA_MISSION_MAX_FAILS', 2);
}
if (!defined('MAFIA_MISSION_RECENT_MAX')) {
    /** 최근 사용 미션 단어 보관 개수 (중복 출제 방지) */
    define('MAFIA_MISSION_RECENT_MAX', 120);
}

function mafia_준호인가($nick): bool {
    $nick = trim((string)$nick);
    if ($nick !== '' && function_exists('getTwoCharNick')) {
        $nick = getTwoCharNick($nick) ?: $nick;
    }
    return $nick === MAFIA_ADMIN_NICK;
}

/** api/function.php 지연 로드 — 본방(공창) 알림용 */
function mafia_함수로드(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('본방알림_등록')) {
        return;
    }
    $cands = [
        dirname(__DIR__) . '/function.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/function.php',
        '/home/kakao/public_html/api/function.php',
    ];
    foreach ($cands as $path) {
        if ($path !== '' && is_file($path)) {
            include_once $path;
            break;
        }
    }
}

/** 공창(본방) 알림 */
function mafia_공창알림($msg, $item = 'mafia'): bool {
    $msg = trim((string)$msg);
    if ($msg === '') {
        return false;
    }
    mafia_함수로드();
    if (function_exists('본방알림_등록')) {
        return (bool)본방알림_등록($msg, $item);
    }
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    return (bool)@db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$msg_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
}

function mafia_json($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 낮/밤 페이즈 종료 시각 — 시계 격자 정렬
 * 예) 10분 격자: 12:00, 12:10, 12:20, 12:30, 12:40, 12:50, 13:00 …
 */
function mafia_다음페이즈종료(?int $now = null): string {
    $now = $now ?? time();
    $align = (int)MAFIA_PHASE_ALIGN_SEC;
    if ($align < 60) {
        $align = (int)MAFIA_PHASE_SEC;
    }
    if ($align < 60) {
        $align = 600;
    }
    // 레거시: 시간 단위만 쓰던 설정
    if (MAFIA_PHASE_ALIGN_HOUR && $align >= 3600) {
        $align = 3600;
    }

    $next = (int)((floor($now / $align) + 1) * $align);
    $minRemain = max(0, (int)MAFIA_PHASE_MIN_REMAIN_SEC);
    if ($next - $now < $minRemain) {
        $next += $align;
    }
    return date('Y-m-d H:i:s', $next);
}

/** 종료 시각이 현재 격자에 맞는지 */
function mafia_페이즈종료_정렬됨(int $endsTs): bool {
    $align = (int)MAFIA_PHASE_ALIGN_SEC;
    if ($align < 60) {
        $align = 600;
    }
    return ($endsTs % $align) === 0;
}

/** 종료 시각까지 남은 분(올림, 최소 1) */
function mafia_페이즈남은분(?string $endsAt = null): int {
    if ($endsAt === null || $endsAt === '') {
        $endsAt = mafia_다음페이즈종료();
    }
    $ts = strtotime($endsAt);
    if ($ts === false) {
        return max(1, (int)ceil(MAFIA_PHASE_ALIGN_SEC / 60));
    }
    return max(1, (int)ceil(($ts - time()) / 60));
}

/** 주말(토·일) 여부 · Asia/Seoul date('w'): 0=일, 6=토 */
function mafia_주말인가(?int $ts = null): bool {
    $ts = $ts ?? time();
    $w = (int)date('w', $ts);
    return $w === 0 || $w === 6;
}

/**
 * 다음 라운드 시작 시각 — 주말(토·일) 오전 MAFIA_DAILY_START_HOUR
 * 지금이 해당 주말 시작 시각 이전이면 그날, 아니면 다음 주말.
 */
function mafia_다음일일시작(?int $now = null): string {
    $now = $now ?? time();
    $hour = (int)MAFIA_DAILY_START_HOUR;
    if ($hour < 0) {
        $hour = 0;
    }
    if ($hour > 23) {
        $hour = 23;
    }
    $baseDay = strtotime(date('Y-m-d', $now) . ' 00:00:00');
    if ($baseDay === false) {
        return date('Y-m-d H:i:s', $now + 86400 * 7);
    }
    for ($i = 0; $i < 16; $i++) {
        $dayTs = strtotime('+' . $i . ' day', $baseDay);
        if ($dayTs === false || !mafia_주말인가($dayTs)) {
            continue;
        }
        $start = strtotime(date('Y-m-d', $dayTs) . sprintf(' %02d:00:00', $hour));
        if ($start !== false && $now < $start) {
            return date('Y-m-d H:i:s', $start);
        }
    }
    return date('Y-m-d H:i:s', $now + 86400 * 7);
}

/** phase_ends_at 이 주말 일일 시작(정각)인지 */
function mafia_일일시작시각인가(int $endsTs): bool {
    return mafia_주말인가($endsTs)
        && ((int)date('G', $endsTs) === (int)MAFIA_DAILY_START_HOUR)
        && ((int)date('i', $endsTs) === 0)
        && ((int)date('s', $endsTs) === 0);
}

function mafia_일일시작라벨(?string $endsAt = null): string {
    $hour = (int)MAFIA_DAILY_START_HOUR;
    if ($endsAt === null || $endsAt === '') {
        return sprintf('주말 오전 %d시', $hour);
    }
    $ts = strtotime($endsAt);
    if ($ts === false) {
        return sprintf('주말 오전 %d시', $hour);
    }
    $요 = ['일', '월', '화', '수', '목', '금', '토'][(int)date('w', $ts)] ?? '';
    return date('n/j', $ts) . '(' . $요 . ')' . sprintf(' 오전 %d시', $hour);
}

/** 남은 초 → "N시간 M분" 안내 */
function mafia_남은시간문구(int $sec): string {
    $sec = max(0, $sec);
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    if ($h > 0) {
        return $h . '시간 ' . $m . '분';
    }
    if ($m > 0) {
        return $m . '분' . ($s > 0 ? ' ' . $s . '초' : '');
    }
    return $s . '초';
}

/**
 * `.마피아` — 진행 상태·다음 시작 시각 안내
 */
function mafia_시작안내문구(): string {
    mafia_테이블보장();
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? 'idle'));
    $endsAt = trim((string)($game['phase_ends_at'] ?? ''));
    $hour = (int)MAFIA_DAILY_START_HOUR;

    $lines = [];
    $lines[] = '🕵️ 마피아 안내';
    $lines[] = '· 주말(토·일) 1회 · 오전 ' . $hour . '시 자동 시작';

    if ($phase === 'night' || $phase === 'day') {
        $lines[] = '· 진행 중: 라운드 #' . $round . ' · ' . mafia_페이즈라벨($phase);
        if ($endsAt !== '') {
            $ts = strtotime($endsAt);
            if ($ts !== false) {
                $left = max(0, $ts - time());
                $lines[] = '· 이번 페이즈 종료: ' . date('H:i', $ts) . ' (약 ' . mafia_남은시간문구($left) . ')';
            }
        }
        $next = mafia_다음일일시작();
        $lines[] = '· 종료 후 다음 시작: ' . mafia_일일시작라벨($next);
    } elseif ($phase === 'waiting') {
        if ($endsAt === '' || !mafia_일일시작시각인가((int)strtotime($endsAt))) {
            $endsAt = mafia_다음일일시작();
        }
        $ts = strtotime($endsAt);
        $left = ($ts !== false) ? max(0, $ts - time()) : 0;
        $lines[] = '· 대기 중 (라운드 #' . $round . ' 종료)';
        $lines[] = '· 다음 시작: ' . mafia_일일시작라벨($endsAt);
        $lines[] = '· 남은 시간: 약 ' . mafia_남은시간문구($left);
        $last = trim((string)($game['last_result'] ?? ''));
        if ($last !== '') {
            $lines[] = '· 최근 결과: ' . $last;
        }
    } else {
        $next = mafia_다음일일시작();
        $ts = strtotime($next);
        $left = ($ts !== false) ? max(0, $ts - time()) : 0;
        $lines[] = '· 현재 미진행';
        $lines[] = '· 다음 시작: ' . mafia_일일시작라벨($next);
        $lines[] = '· 남은 시간: 약 ' . mafia_남은시간문구($left);
    }

    return implode("\n", $lines);
}

/**
 * `.마피아종료` — 진행 중 라운드 강제 종료 → 다음 주말 오전 10시 대기
 * (민호 전용 · 보상 없음)
 */
function mafia_강제종료(string $adminNick): array {
    if (!mafia_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '마피아 강제 종료는 민호만 할 수 있어요.'];
    }
    mafia_테이블보장();
    mafia_틱();
    $game = mafia_게임();
    $phase = trim((string)($game['phase'] ?? 'idle'));
    $round = (int)($game['round_no'] ?? 0);

    if ($phase === 'idle') {
        return ['ok' => false, 'msg' => '진행 중인 마피아 게임이 없어요.'];
    }
    if ($phase === 'waiting') {
        $endsAt = trim((string)($game['phase_ends_at'] ?? ''));
        if ($endsAt === '' || !mafia_일일시작시각인가((int)strtotime($endsAt))) {
            $endsAt = mafia_다음일일시작();
            $ends_esc = addslashes($endsAt);
            @db_query("UPDATE tb_mafia_game SET phase_ends_at = '{$ends_esc}', updated_at = NOW() WHERE idx = 1");
        }
        return [
            'ok' => true,
            'msg' => '이미 종료 대기 중이에요.\n다음 시작: ' . mafia_일일시작라벨($endsAt),
            'already' => true,
        ];
    }
    if ($round < 1) {
        return ['ok' => false, 'msg' => '진행 중인 마피아 게임이 없어요.'];
    }

    mafia_승리대기($round, 'ended', '🛑 마피아 강제 종료됐어요');
    $ends = mafia_다음일일시작();
    return [
        'ok' => true,
        'msg' => "라운드 #{$round} 강제 종료했어요.\n다음 시작: " . mafia_일일시작라벨($ends),
    ];
}

function mafia_테이블보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS tb_mafia_game (
        idx TINYINT UNSIGNED NOT NULL DEFAULT 1,
        round_no INT UNSIGNED NOT NULL DEFAULT 0,
        phase VARCHAR(16) NOT NULL DEFAULT 'idle',
        phase_ends_at DATETIME NULL DEFAULT NULL,
        winner VARCHAR(16) NOT NULL DEFAULT '',
        last_result TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (idx)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @db_query("CREATE TABLE IF NOT EXISTS tb_mafia_player (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        round_no INT UNSIGNED NOT NULL DEFAULT 0,
        nick VARCHAR(50) NOT NULL,
        role VARCHAR(16) NOT NULL DEFAULT 'citizen',
        alive TINYINT NOT NULL DEFAULT 1,
        night_action_nick VARCHAR(160) NOT NULL DEFAULT '',
        day_vote_nick VARCHAR(160) NOT NULL DEFAULT '',
        investigated TINYINT NOT NULL DEFAULT 0,
        investigate_result VARCHAR(16) NOT NULL DEFAULT '',
        PRIMARY KEY (idx),
        UNIQUE KEY uq_round_nick (round_no, nick),
        KEY idx_round_alive (round_no, alive),
        KEY idx_round_role (round_no, role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // 마피아 2지목 · 낮 2~3표용 (구 VARCHAR(50) 확장)
    $colN = @db_select("SHOW COLUMNS FROM tb_mafia_player LIKE 'night_action_nick'");
    if (!empty($colN['Type']) && stripos((string)$colN['Type'], 'varchar(50)') !== false) {
        @db_query("ALTER TABLE tb_mafia_player MODIFY night_action_nick VARCHAR(160) NOT NULL DEFAULT ''");
    }
    $colD = @db_select("SHOW COLUMNS FROM tb_mafia_player LIKE 'day_vote_nick'");
    if (!empty($colD['Type']) && stripos((string)$colD['Type'], 'varchar(50)') !== false) {
        @db_query("ALTER TABLE tb_mafia_player MODIFY day_vote_nick VARCHAR(160) NOT NULL DEFAULT ''");
    }
    @db_query("CREATE TABLE IF NOT EXISTS tb_mafia_log (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        round_no INT UNSIGNED NOT NULL DEFAULT 0,
        msg VARCHAR(500) NOT NULL,
        regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (idx),
        KEY idx_round (round_no, idx)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = @db_select("SELECT idx FROM tb_mafia_game WHERE idx = 1 LIMIT 1");
    if (empty($row['idx'])) {
        @db_query("INSERT INTO tb_mafia_game (idx, round_no, phase, phase_ends_at, winner, last_result)
            VALUES (1, 0, 'idle', NULL, '', '')");
    }
    // 공통미션 · 누적보상 컬럼
    $missionCols = [
        'mission_word' => "ALTER TABLE tb_mafia_game ADD COLUMN mission_word VARCHAR(40) NOT NULL DEFAULT ''",
        'mission_started_at' => "ALTER TABLE tb_mafia_game ADD COLUMN mission_started_at DATETIME NULL DEFAULT NULL",
        'mission_pot' => "ALTER TABLE tb_mafia_game ADD COLUMN mission_pot BIGINT NOT NULL DEFAULT 0",
        'mission_recent' => "ALTER TABLE tb_mafia_game ADD COLUMN mission_recent TEXT NOT NULL",
        'mission_reroll_used' => "ALTER TABLE tb_mafia_game ADD COLUMN mission_reroll_used TINYINT UNSIGNED NOT NULL DEFAULT 0",
    ];
    foreach ($missionCols as $col => $alterSql) {
        $c = @db_select("SHOW COLUMNS FROM tb_mafia_game LIKE '{$col}'");
        if (empty($c['Field'])) {
            @db_query($alterSql);
        }
    }
    // 최근 미션 이력 확장 (중복 방지용)
    $cRecent = @db_select("SHOW COLUMNS FROM tb_mafia_game LIKE 'mission_recent'");
    $recentType = strtolower((string)($cRecent['Type'] ?? ''));
    if ($recentType !== '' && strpos($recentType, 'text') === false) {
        @db_query("ALTER TABLE tb_mafia_game MODIFY COLUMN mission_recent TEXT NOT NULL");
    }
    // 마피아 개인 미션 실패 횟수 (2회 실패 시 역할 교체)
    $cFail = @db_select("SHOW COLUMNS FROM tb_mafia_player LIKE 'mission_fails'");
    if (empty($cFail['Field'])) {
        @db_query("ALTER TABLE tb_mafia_player ADD COLUMN mission_fails TINYINT UNSIGNED NOT NULL DEFAULT 0");
    }
    // 라운드 내 낮 회차 (1부터 · 2회차부터 무투표 강제탈락)
    $cDay = @db_select("SHOW COLUMNS FROM tb_mafia_game LIKE 'day_no'");
    if (empty($cDay['Field'])) {
        @db_query("ALTER TABLE tb_mafia_game ADD COLUMN day_no TINYINT UNSIGNED NOT NULL DEFAULT 0");
    }
}

function mafia_게임(): array {
    mafia_테이블보장();
    $row = db_select("SELECT * FROM tb_mafia_game WHERE idx = 1 LIMIT 1");
    return is_array($row) ? $row : [
        'idx' => 1,
        'round_no' => 0,
        'phase' => 'idle',
        'phase_ends_at' => null,
        'winner' => '',
        'last_result' => '',
        'mission_word' => '',
        'mission_started_at' => null,
        'mission_pot' => 0,
        'mission_recent' => '',
        'mission_reroll_used' => 0,
        'day_no' => 0,
    ];
}

function mafia_로그($round, $msg): void {
    $round = (int)$round;
    $msg = addslashes(mb_substr(trim((string)$msg), 0, 480));
    if ($msg === '') {
        return;
    }
    @db_query("INSERT INTO tb_mafia_log SET round_no = {$round}, msg = '{$msg}', regdate = NOW()");
}

function mafia_최근로그($round, $limit = 12): array {
    $round = (int)$round;
    $limit = max(1, min(50, (int)$limit));
    // 여유분 조회 후 교체 로그 필터
    $fetch = max($limit * 3, 30);
    $rs = @db_query("SELECT msg, regdate FROM tb_mafia_log WHERE round_no = {$round} ORDER BY idx DESC LIMIT {$fetch}");
    $out = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $msg = (string)($row['msg'] ?? '');
            if (mafia_로그_역할교체숨김인가($msg)) {
                continue;
            }
            $out[] = [
                'msg' => $msg,
                'at' => substr((string)($row['regdate'] ?? ''), 0, 16),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    return array_reverse($out);
}

/** 역할 교체 관련 로그는 기록 UI에 노출하지 않음 */
function mafia_로그_역할교체숨김인가(string $msg): bool {
    $msg = trim($msg);
    if ($msg === '') {
        return false;
    }
    $needles = ['역할 교체', '역할교체', '랜덤교체', '미션 실패 교체'];
    foreach ($needles as $n) {
        if (mb_strpos($msg, $n) !== false) {
            return true;
        }
    }
    return false;
}

/** 종료된 라운드 번호 상한 (진행 중이면 현재-1, 재분배 대기는 현재 포함) */
function mafia_공개기록_최대라운드(?array $game = null): int {
    if ($game === null) {
        $game = mafia_게임();
    }
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? 'idle'));
    if ($round < 1) {
        return 0;
    }
    if ($phase === 'waiting') {
        return $round;
    }
    return max(0, $round - 1);
}

/** @return array{winner:string,winner_label:string,result:string} */
function mafia_라운드결과조회(int $round, ?array $game = null): array {
    $round = (int)$round;
    $out = ['winner' => '', 'winner_label' => '', 'result' => ''];
    if ($round < 1) {
        return $out;
    }
    if ($game === null) {
        $game = mafia_게임();
    }
    if ((int)($game['round_no'] ?? 0) === $round) {
        $w = trim((string)($game['winner'] ?? ''));
        $res = trim((string)($game['last_result'] ?? ''));
        if ($w !== '' || $res !== '') {
            $out['winner'] = $w;
            $out['result'] = $res;
            if ($w === 'mafia') {
                $out['winner_label'] = '마피아 승리';
            } elseif ($w === 'town') {
                $out['winner_label'] = '시민 승리';
            }
            return $out;
        }
    }
    $row = @db_select("SELECT msg FROM tb_mafia_log
        WHERE round_no = {$round}
          AND (msg LIKE '%시민 승리%' OR msg LIKE '%마피아 승리%')
        ORDER BY idx ASC LIMIT 1");
    $msg = trim((string)($row['msg'] ?? ''));
    if ($msg === '') {
        return $out;
    }
    $out['result'] = $msg;
    if (mb_strpos($msg, '마피아 승리') !== false) {
        $out['winner'] = 'mafia';
        $out['winner_label'] = '마피아 승리';
    } elseif (mb_strpos($msg, '시민 승리') !== false) {
        $out['winner'] = 'town';
        $out['winner_label'] = '시민 승리';
    }
    return $out;
}

/**
 * 종료된 라운드 역할 공개 기록
 * @return list<array{round:int,winner:string,winner_label:string,result:string,roles:array<string,list<string>>,alive:array<string,bool>}>
 */
function mafia_이전라운드기록(int $limit = 5, ?array $game = null): array {
    $limit = max(1, min(20, (int)$limit));
    if ($game === null) {
        $game = mafia_게임();
    }
    $maxRound = mafia_공개기록_최대라운드($game);
    if ($maxRound < 1) {
        return [];
    }

    $rs = @db_query("SELECT DISTINCT round_no FROM tb_mafia_player
        WHERE round_no >= 1 AND round_no <= {$maxRound}
        ORDER BY round_no DESC LIMIT {$limit}");
    $rounds = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $r = (int)($row['round_no'] ?? 0);
            if ($r > 0) {
                $rounds[] = $r;
            }
        }
    }
    if ($rounds === []) {
        return [];
    }

    $out = [];
    foreach ($rounds as $r) {
        $players = mafia_플레이어목록($r);
        if ($players === []) {
            continue;
        }
        $roles = [
            'mafia' => [],
            'police' => [],
            'doctor' => [],
            'politician' => [],
            'citizen' => [],
        ];
        $alive = [];
        foreach ($players as $p) {
            $nick = trim((string)($p['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $role = trim((string)($p['role'] ?? 'citizen'));
            if (!isset($roles[$role])) {
                $role = 'citizen';
            }
            $roles[$role][] = $nick;
            $alive[$nick] = !empty($p['alive']);
        }
        foreach ($roles as $k => $list) {
            sort($roles[$k], SORT_STRING);
        }
        $결과 = mafia_라운드결과조회($r, $game);
        $out[] = [
            'round' => $r,
            'winner' => $결과['winner'],
            'winner_label' => $결과['winner_label'],
            'result' => $결과['result'],
            'roles' => $roles,
            'alive' => $alive,
        ];
    }
    return $out;
}

function mafia_활성닉목록(): array {
    $rs = @db_query("SELECT name FROM tb_member WHERE status = 0 ORDER BY name ASC");
    $nicks = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $n = trim((string)($row['name'] ?? ''));
            if ($n === '') {
                continue;
            }
            if (function_exists('getTwoCharNick')) {
                $n2 = getTwoCharNick($n);
                if ($n2 !== '') {
                    $n = $n2;
                }
            }
            $nicks[$n] = $n;
        }
    }
    return array_values($nicks);
}

/** @return array{mafia:int,police:int,doctor:int,politician:int,citizen:int} */
function mafia_역할배분표(int $n): array {
    $n = max(0, $n);
    if ($n < 2) {
        return ['mafia' => 0, 'police' => 0, 'doctor' => 0, 'politician' => 0, 'citizen' => $n];
    }
    $mafia = (int)max(1, round($n * MAFIA_MAFIA_RATIO));
    if ($mafia >= (int)floor($n / 2)) {
        $mafia = max(1, (int)floor(($n - 1) / 2));
    }
    // 경찰·의사: 기존 대비 +1~2명
    // 경찰: 8~34 → 2 · 35+ → 4  (기존 1 / 2)
    // 의사: 10~34 → 2 · 35+ → 3  (기존 1 / 1)
    $police = ($n >= 8) ? (($n >= 35) ? 4 : 2) : 0;
    $doctor = ($n >= 10) ? (($n >= 35) ? 3 : 2) : 0;
    // 정치인(시민측·투표3): 12~34 → 1 · 35+ → 2
    $politician = ($n >= 12) ? (($n >= 35) ? 2 : 1) : 0;
    while (($mafia + $police + $doctor + $politician) >= $n) {
        if ($politician > 0) {
            $politician--;
        } elseif ($doctor > 0) {
            $doctor--;
        } elseif ($police > 0) {
            $police--;
        } else {
            $mafia = max(1, $mafia - 1);
            break;
        }
    }
    $citizen = max(0, $n - $mafia - $police - $doctor - $politician);
    return [
        'mafia' => $mafia,
        'police' => $police,
        'doctor' => $doctor,
        'politician' => $politician,
        'citizen' => $citizen,
    ];
}

function mafia_닉정규화(string $nick): string {
    $nick = trim($nick);
    if ($nick !== '' && function_exists('getTwoCharNick')) {
        $n2 = trim((string)getTwoCharNick($nick));
        if ($n2 !== '') {
            return $n2;
        }
    }
    return $nick;
}

/**
 * 최근 N일 공창(tb_msg) 참여 점수 — SUM(tasu), 닉은 두자리 정규화
 * @param string[] $nicks
 * @return array<string,float> nick => score
 */
function mafia_공창참여점수(array $nicks): array {
    $scores = [];
    foreach ($nicks as $n) {
        $n = mafia_닉정규화((string)$n);
        if ($n !== '') {
            $scores[$n] = 0.0;
        }
    }
    if ($scores === []) {
        return [];
    }

    $days = max(1, (int)MAFIA_ACTIVITY_DAYS);
    $rs = @db_query("SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS score
        FROM tb_msg
        WHERE regdate >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
          AND nickname NOT IN ('오픈', '')
          AND IFNULL(tasu, 0) <> 0
        GROUP BY nickname");
    if (!$rs) {
        return $scores;
    }
    while ($row = db_fetch($rs)) {
        $raw = trim((string)($row['nickname'] ?? ''));
        if ($raw === '') {
            continue;
        }
        $nick = mafia_닉정규화($raw);
        if ($nick === '' || !array_key_exists($nick, $scores)) {
            continue;
        }
        $scores[$nick] += max(0.0, (float)($row['score'] ?? 0));
    }
    return $scores;
}

/**
 * 가중 랜덤 비복원 추첨
 * @param array<string,float|int> $weights nick => weight (>0)
 * @return string[]
 */
function mafia_가중추첨(array $weights, int $count): array {
    $pool = [];
    foreach ($weights as $nick => $w) {
        $nick = trim((string)$nick);
        if ($nick === '') {
            continue;
        }
        $pool[$nick] = max(0.0001, (float)$w);
    }
    $count = max(0, min((int)$count, count($pool)));
    $picked = [];
    for ($i = 0; $i < $count; $i++) {
        $total = 0.0;
        foreach ($pool as $w) {
            $total += $w;
        }
        if ($total <= 0 || $pool === []) {
            break;
        }
        $r = (mt_rand() / (mt_getrandmax() ?: 1)) * $total;
        $acc = 0.0;
        $chosen = '';
        foreach ($pool as $nick => $w) {
            $acc += $w;
            if ($r <= $acc) {
                $chosen = $nick;
                break;
            }
        }
        if ($chosen === '') {
            $keys = array_keys($pool);
            $chosen = (string)$keys[array_rand($keys)];
        }
        $picked[] = $chosen;
        unset($pool[$chosen]);
    }
    return $picked;
}

/**
 * 남은 후보에서 공창가중+랜덤 혼합 추첨
 * @param array<string,float> $activityWeights nick => weight
 * @param array<string,true> $pool 남은 닉 집합
 * @return string[]
 */
function mafia_혼합추첨(array $activityWeights, array &$pool, int $need): array {
    $need = max(0, (int)$need);
    if ($need < 1 || $pool === []) {
        return [];
    }
    $need = min($need, count($pool));
    $ratio = max(0.0, min(1.0, (float)MAFIA_ACTIVITY_WEIGHTED_RATIO));

    if ($need <= 1) {
        $weightedNeed = (mt_rand(1, 100) <= (int)round($ratio * 100)) ? 1 : 0;
    } else {
        $weightedNeed = (int)round($need * $ratio);
        $weightedNeed = max(1, min($need - 1, $weightedNeed));
    }
    $randomNeed = $need - $weightedNeed;

    $picked = [];
    if ($weightedNeed > 0) {
        $wPool = [];
        foreach ($pool as $nick => $_t) {
            $wPool[$nick] = (float)($activityWeights[$nick] ?? MAFIA_ACTIVITY_WEIGHT_BASE);
        }
        foreach (mafia_가중추첨($wPool, $weightedNeed) as $nick) {
            $picked[] = $nick;
            unset($pool[$nick]);
        }
    }
    if ($randomNeed > 0 && $pool !== []) {
        $uPool = [];
        foreach ($pool as $nick => $_t) {
            $uPool[$nick] = 1.0;
        }
        foreach (mafia_가중추첨($uPool, $randomNeed) as $nick) {
            $picked[] = $nick;
            unset($pool[$nick]);
        }
    }
    return $picked;
}

/**
 * 공창 참여를 살짝 반영하되 잘 섞어 역할 배정
 * 마피아·경찰·의사·정치인: 일부 자리만 약한 공창가중 · 나머지는 완전 랜덤
 * @param string[] $nicks
 * @return array<string,string> nick => role
 */
function mafia_역할배정(array $nicks): array {
    $nicks = array_values(array_unique(array_filter(array_map(static function ($n) {
        return mafia_닉정규화((string)$n);
    }, $nicks))));
    $n = count($nicks);
    $배분 = mafia_역할배분표($n);
    $assign = [];
    if ($n < 1) {
        return $assign;
    }

    $scores = mafia_공창참여점수($nicks);
    $base = (float)MAFIA_ACTIVITY_WEIGHT_BASE;
    $boost = (float)MAFIA_ACTIVITY_WEIGHT_BOOST;
    $cap = (float)MAFIA_ACTIVITY_WEIGHT_CAP;
    $activityWeights = [];
    $pool = [];
    foreach ($nicks as $nick) {
        $score = max(0.0, (float)($scores[$nick] ?? 0));
        $bonus = min(sqrt($score) * $boost, $cap);
        $activityWeights[$nick] = $base + $bonus;
        $pool[$nick] = true;
    }

    foreach (mafia_혼합추첨($activityWeights, $pool, (int)$배분['mafia']) as $nick) {
        $assign[$nick] = 'mafia';
    }
    foreach (mafia_혼합추첨($activityWeights, $pool, (int)$배분['police']) as $nick) {
        $assign[$nick] = 'police';
    }
    foreach (mafia_혼합추첨($activityWeights, $pool, (int)$배분['doctor']) as $nick) {
        $assign[$nick] = 'doctor';
    }
    foreach (mafia_혼합추첨($activityWeights, $pool, (int)($배분['politician'] ?? 0)) as $nick) {
        $assign[$nick] = 'politician';
    }
    foreach ($pool as $nick => $_t) {
        $assign[$nick] = 'citizen';
    }
    return $assign;
}

function mafia_역할라벨($role): string {
    $map = [
        'mafia' => '마피아',
        'citizen' => '시민',
        'police' => '경찰',
        'doctor' => '의사',
        'politician' => '정치인',
    ];
    $role = trim((string)$role);
    return $map[$role] ?? $role;
}

/** 역할별 낮 투표 최대 수 */
function mafia_낮투표한도($role): int {
    $role = trim((string)$role);
    if ($role === 'mafia') {
        return max(1, (int)MAFIA_DAY_VOTES_MAFIA);
    }
    if ($role === 'politician') {
        return max(1, (int)MAFIA_DAY_VOTES_POLITICIAN);
    }
    return max(1, (int)MAFIA_DAY_VOTES_DEFAULT);
}

/**
 * 진행 중(밤/낮) 라운드에 정치인이 없으면 생존 시민 1명을 랜덤 정치인으로 승격 (1회)
 */
function mafia_진행중_정치인1회부여(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $game = mafia_게임();
    $phase = trim((string)($game['phase'] ?? ''));
    $round = (int)($game['round_no'] ?? 0);
    if ($round < 1 || !in_array($phase, ['night', 'day'], true)) {
        return;
    }

    $players = mafia_플레이어목록($round);
    $citizens = [];
    foreach ($players as $p) {
        if (($p['role'] ?? '') === 'politician') {
            return; // 이미 있음
        }
        if (!empty($p['alive']) && ($p['role'] ?? '') === 'citizen') {
            $citizens[] = (string)$p['nick'];
        }
    }
    if ($citizens === []) {
        return;
    }
    shuffle($citizens);
    $pick = $citizens[0];
    $esc = addslashes($pick);
    @db_query("UPDATE tb_mafia_player SET role = 'politician'
        WHERE round_no = {$round} AND nick = '{$esc}' AND role = 'citizen' AND alive = 1 LIMIT 1");
    mafia_로그($round, "🗳️ 정치인 역할 부여 · {$pick} (투표 3표)");
    mafia_공창알림(
        "🗳️ 정치인이 등장했어요! (라운드 #{$round})\n"
        . "시민 중 1명이 정치인으로 변경 · 낮 투표 3표\n"
        . "· 가방→마피아에서 내 역할을 확인하세요",
        'mafia_politician'
    );
}

/** 공창에서 자연스럽게 쓸 수 있는 공통미션 단어 풀 (GPT 실패 시 폴백) */
function mafia_미션단어풀(): array {
    return [
        '바보', '진짜', '대박', '배고파', '귀여워', '힘들어', '갑자기',
        '잠깐', '인정', '헐', '오케이', '오늘도', '솔직히', '아니근데',
        '레알', '뭐래', '개꿀', '미치겠', '존맛', '개웃김', '실화냐',
        '일단', '그래도', '완전', '졸려', '배불러', '심심해', '행복해',
        '짜증나', '괜찮음', '당연하지', '왜이래', '그건좀', '미쳤다', '쩐다',
        '노잼', '꿀잼', '레전드', '가즈아', '일단먹어', '커피각', '치킨각',
        '산책갈까', '날씨좋다', '비온다', '더워죽겠', '추워죽겠', '월급날', '주말이다',
        '월요병', '퇴근각', '야식각', '다이어트', '운동할까', '사진찍자', '노래좋다',
        '영화볼까', '게임하자', '잠온다', '카페인', '아이스크림', '떡볶이', '라면각',
        '피자먹고싶', '수박각', '딸기우유', '초코우유', '민트초코', '고양이', '강아지',
        '햄스터', '구름같다', '별보러가', '달빛좋다', '힐링이다', '멘붕', '현타온다',
        '갓생각', '운치있다', '분위기좋', '감성터짐', '소름돋아', '깜짝이야', '설렌다',
        '심쿵', '눈물남', '웃프다', '짠하다', '훈훈하다', '쿨쿨', '뒹굴뒹굴',
        '멍때림', '집중모드', '프로야식러', '집순이', '밖순이', '급식각', '야근각',
        '지각각', '버스놓침', '택시탈까', '비와서짜증', '에어컨켜', '히터켜', '마스크끼자',
        '손소독', '비타민각', '스트레칭', '걷기각', '헬스장', '홈트각', '물마셔',
        '잠부족', '낮잠각', '알람끄고싶', '출근하기싫', '칼퇴각', '회식각', '회식싫',
        '야근싫', '주말언제', '연휴각', '여행가고싶', '바다각', '산각', '캠핑각',
        '드라이브', '카페투어', '빵순이', '커피중독', '디저트각', '붕어빵', '호빵각',
        '군고구마', '핫초코', '아아각', '라떼는말야', '단짠단짠', '맵찔이', '매운맛각',
        '순한맛', '곱창각', '삼겹살각', '회먹고싶', '초밥각', '분식각', '김밥각',
        '우동각', '짜장면', '짬뽕각', '탕수육', '족발각', '보쌈각', '치킨무',
        '피클각', '콜라각', '사이다각', '맥주각', '소주각', '안주각', '군것질',
        '야식배달', '배민켜', '쿠팡와서', '택배왔다', '언박싱', '득템했다', '세일각',
        '장바구니', '포인트적립', '가성비갑', '충동구매', '환불각', '재택각', '회의길다',
        '줌미팅', '슬랙확인', '메일확인', '할일폭탄', '마감임박', '프로크래', '미루기왕',
        '일단쉬자', '산책각', '공원각', '벤치각', '하늘예쁘다', '노을좋다', '비온후맑음',
        '미세먼지', '황사온다', '우산챙겼', '양산각', '선크림', '선글라스', '모자각',
        '패딩각', '목도리각', '장갑끼자', '핫팩각', '발시려', '손시려', '땀나',
        '더워죽', '추워죽', '선풍기켜', '가습기켜', '빨래널자', '청소각', '설거지혐',
        '분리수거', '쓰레기배출', '먼지털자', '환기하자', '창문열어', '커튼쳐', '조명켜',
        '무드등', '향초각', '디퓨저', '플랜테리어', '화분물줘', '강아지산책', '냥이간식',
        '사료사야', '브러싱각', '발톱깎기', '동물병원', '넷플릭스', '유튜브각', '쇼츠중독',
        '릴스보다', '게임접속', '랭크돌자', '한판각', '팟캐스트', '음악틀어', '플레이리스트',
        '노래방각', '춤추고싶', '공연보고싶', '전시회각', '박물관각', '서점각', '책읽을까',
        '웹툰각', '웹소설', '드라마정주행', '예능보다', '뉴스스킵', '날씨앱', '지하철앱',
        '내비켜', '길잃음', '주차지옥', '주유각', '전기충전', '따릉이각', '킥보드각',
        '걷기만보', '층수많음', '엘리베이터', '에스컬', '계단각', '숨차다', '다리풀려',
        '허리아프다', '어깨결려', '목아파', '두통온다', '배아프다', '속쓰려', '감기기운',
        '목아픔', '기침나', '재채기', '알레르기', '비염각', '렌즈빼자', '안경끼자',
        '충혈됐어', '건조해', '립밤각', '핸드크림', '바디로션', '샤워각', '목욕각',
        '스파가고싶', '마사지각', '안마의자', '스트레칭중', '요가각', '필라테스', '러닝각',
        '조깅각', '자전거각', '수영각', '클라이밍', '볼링각', '당구각', '탁구각',
        '배드민턴', '농구할까', '축구할까', '야구할까', '골프각', '스크린골프', '보드게임',
        '카드게임', '마피아게임', '진다면져', '개쩐다', '핵노잼', '핵꿀잼', '존버각',
        '존중함', '예의바름', '매너각', '센스갑', '눈치없', '티내자', '비밀이야',
        '스포금지', '나중에말해', '기억안남', '깜빡했어', '미안해용', '고마워용', '부탁해용',
        '나중에봐', '내일봐', '잘자요', '굿나잇', '굿모닝', '하이요', '방가방가',
        '오랜만이야', '보고싶었어', '그리웠어', '반가워', '심쿵각', '설렘각', '두근두근',
        '떨린다', '긴장돼', '떨떠름', '애매하다', '애매모호', '글쎄요', '모르겠당',
        '생각해볼게', '고민중', '결정장애', '선택장애', '다좋아', '둘다좋아', '다싫어',
        '취향존중', '취향저격', '내스타일', '아님말고', '팩트폭행', '현실직시', '정신차려',
        '정신없', '정신차려요', '정신줄', '멘탈케어', '힐링타임', '셀프케어', '워라밸',
        '번아웃', '번아웃각', '재충전', '에너지충전', '비타민씨', '단백질각', '샐러드각',
        '도시락각', '편의점각', '삼각김밥', '컵라면', '핫바각', '어묵각', '떡꼬치',
        '핫도그각', '와플각', '크로플각', '마카롱', '케이크각', '빙수각', '팥빙수',
        '소프트콘', '츄러스', '도넛각', '베이글각', '토스트각', '샌드위치', '햄버거각',
        '핫케이크', '팬케이크', '오믈렛', '계란후라이', '스크램블', '김치찌개', '된장찌개',
        '순두부각', '부대찌개', '칼국수각', '비빔면', '냉면각', '막국수', '콩국수',
        '수제비각', '만두각', '교자각', '군만두', '찐만두', '왕만두', '물만두',
        '김치전', '파전각', '감자전', '계란말이', '잡채각', '불고기각', '제육각',
        '닭갈비', '찜닭각', '닭볶음탕', '삼계탕', '곰탕각', '설렁탕', '갈비탕',
        '육개장', '해장국', '콩나물국', '미역국각', '북어국', '오뎅탕', '어묵탕',
        '매운탕', '알탕각', '순대국', '뼈해장국', '감자탕', '돼지국밥', '소고기무국',
        '된장국', '시래기국', '아욱국', '시금치나물', '콩나물무침', '오이무침', '무생채',
        '깍두기', '열무김치', '배추김치', '파김치', '겉절이', '쌈장각', '고추장각',
        '간장각', '참기름', '들기름', '깨소금', '후추뿌려', '소금뿌려', '설탕살짝',
        '식초각', '레몬즙', '와사비각', '겨자각', '마요네즈', '케첩각', '머스타드',
        '소스듬뿍', '소스찍어', '찍어먹어', '비벼먹어', '말아먹어', '후루룩', '우걱우걱',
        '와구와구', '꿀꺽', '쪽쪽', '아삭아삭', '바삭바삭', '쫄깃쫄깃', '꾸덕꾸덕',
        '촉촉하다', '보들보들', '말랑말랑', '쫀득쫀득', '바삭함', '고소해', '달달해',
        '짭짤해', '새콤해', '매콤해', '얼큰해', '시원해', '뜨끈해', '미지근',
        '차갑다', '따뜻하다', '따끈따끈', '후끈후끈', '쿨쿨쿨', '쌩쌩하다', '개운하다',
        '상쾌하다', '산뜻하다', '뽀송하다', '촉촉각', '보습각', '수분충전', '오일바르자',
        '선케어', '톤업각', '쿠션바르자', '립틴트', '마스카라', '아이라인', '블러셔',
        '하이라이터', '쉐딩각', '파운데이션', '비비크림', '씨씨크림', '스킨케어', '클렌징',
        '폼클렌징', '오일클렌징', '토너각', '세럼각', '앰플각', '크림바르자', '팩하자',
        '마스크팩', '아이패치', '립스크럽', '각질케어', '모공케어', '트러블났', '여드름각',
        '뾰루지', '홍조각', '다크서클', '붓기뺐다', '숙면각', '수면부족', '커피컷',
        '카페인도즈', '디카페인', '티타임', '허브티', '녹차각', '홍차각', '밀크티',
        '버블티', '스무디각', '주스각', '에이드각', '탄산각', '제로콜라', '제로사이다',
        '이온음료', '스포츠드링크', '프로틴쉐이크', '두유각', '우유각', '요거트각', '그릭요거트',
        '시리얼각', '그래놀라', '견과류', '아몬드각', '호두각', '피스타치오', '캐슈넛',
        '땅콩각', '해바라기씨', '호박씨', '김부각', '쥐포각', '오징어채', '한치각',
        '버터구이', '버터바른', '잼바른', '땅콩버터', '누텔라각', '초코시럽', '카라멜각',
        '허니버터', '치즈듬뿍', '모짜렐라', '체다치즈', '크림치즈', '파마산', '고르곤졸라',
        '블루베리', '라즈베리', '스트로베리', '블랙베리', '크랜베리', '망고각', '파인애플',
        '바나나각', '사과각', '배각', '포도각', '귤각', '오렌지각', '자몽각',
        '레몬각', '라임각', '키위각', '아보카도', '토마토각', '오이각', '당근각',
        '브로콜리', '시금치각', '양상추', '케일각', '파프리카', '버섯각', '표고각',
        '새송이', '팽이버섯', '느타리', '양송이', '트러플각', '올리브각', '케이퍼',
        '안초비', '훈제연어', '연어각', '참치각', '고등어각', '삼치각', '갈치각',
        '조기각', '광어각', '우럭각', '도미각', '새우각', '게각', '랍스터',
        '문어각', '낙지각', '주꾸미', '오징어각', '해산물각', '조개구이', '홍합각',
        '바지락', '굴각', '전복각', '소라각',
    ];
}

function mafia_설정로드(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('callGPT')) {
        return;
    }
    $cands = [
        dirname(__DIR__) . '/config.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/config.php',
        '/home/kakao/public_html/api/config.php',
    ];
    foreach ($cands as $path) {
        if ($path !== '' && is_file($path)) {
            include_once $path;
            break;
        }
    }
}

/** @return string[] */
function mafia_미션최근목록(?array $game = null): array {
    if ($game === null) {
        $game = mafia_게임();
    }
    $raw = trim((string)($game['mission_recent'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/u', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $w = trim((string)$p);
        if ($w === '' || isset($out[$w])) {
            continue;
        }
        $out[$w] = $w;
    }
    return array_values($out);
}

function mafia_미션단어정제($raw): string {
    $w = trim((string)$raw);
    $w = preg_replace('/^[\"\'「『【\s]+|[\"\'」』】\s.。!！?？]+$/u', '', $w) ?? '';
    $w = preg_replace('/\s+/u', '', $w) ?? '';
    // 첫 토큰만
    if (preg_match('/^([가-힣A-Za-z0-9]{2,12})/u', $w, $m)) {
        $w = $m[1];
    }
    $w = trim((string)$w);
    if ($w === '' || mb_strlen($w) < 2 || mb_strlen($w) > 12) {
        return '';
    }
    if (!preg_match('/^[가-힣A-Za-z0-9]+$/u', $w)) {
        return '';
    }
    // 너무 밋밋한 단음절/금칙성
    $block = ['씨발', '시발', '병신', '좆', '지랄', '개새끼', '니애미', '느금마'];
    foreach ($block as $b) {
        if (mb_stripos($w, $b) !== false) {
            return '';
        }
    }
    return $w;
}

/**
 * GPT로 공창용 미션 단어 1개 생성 (실패·중복 시 '')
 * @param string[] $제외
 */
function mafia_미션단어_GPT생성(array $제외 = []): string {
    mafia_설정로드();
    if (!function_exists('callGPT')) {
        return '';
    }
    $제외 = array_values(array_unique(array_filter(array_map('trim', $제외))));
    $제외문구 = $제외 !== []
        ? ("아래 단어는 절대 쓰지 마(최근 출제·중복 금지):\n" . implode(', ', array_slice($제외, 0, 80)))
        : '최근 사용 단어 없음 — 그래도 흔하지 않은 말로';
    $seed = (string)mt_rand(10000, 99999) . '-' . date('His');
    $themes = [
        '음식·야식·카페', '날씨·계절·외출', '출근·퇴근·월요병', '운동·건강·스트레칭',
        '쇼핑·택배·득템', '게임·넷플릭스·유튜브', '반려동물·힐링', '감성·밈·신조어',
        '집안일·청소·정리', '여행·드라이브·나들이',
    ];
    $theme = $themes[array_rand($themes)];
    $system = '너는 카카오톡 오픈채팅 미션 출제자다. 답은 미션 단어/짧은 말투 하나만. 설명·번호·따옴표·이모지·문장 금지.';
    $user = "카톡 공창에서 대화 중에 자연스럽게 말할 수 있는 한국어 단어/짧은 말투 1개만 새로 만들어줘.\n"
        . "조건:\n"
        . "- 2~10글자 (한글 위주, 숫자·영문 약간 OK)\n"
        . "- 테마 힌트: {$theme}\n"
        . "- 욕설·비하·선정적·정치·실명·혐오 금지\n"
        . "- 반드시 새로운 표현 (비슷한 말도 금지)\n"
        . "- {$제외문구}\n"
        . "- 시드: {$seed}\n"
        . "단어만 한 줄 출력:";
    $res = callGPT($user, $system, 48);
    if ($res === false || $res === null) {
        return '';
    }
    $word = mafia_미션단어정제($res);
    if ($word === '') {
        return '';
    }
    $bannedMap = array_fill_keys($제외, true);
    if (isset($bannedMap[$word])) {
        return '';
    }
    return $word;
}

/** 금지 목록에 없는 조합어 생성 (최후 폴백) */
function mafia_미션단어_유니크폴백(array $banned): string {
    $bannedMap = [];
    foreach ($banned as $b) {
        $b = trim((string)$b);
        if ($b !== '') {
            $bannedMap[$b] = true;
        }
    }
    $a = ['오늘', '갑자기', '솔직히', '완전', '약간', '일단', '요즘', '나름', '진짜', '은근', '꽤', '또', '방금', '지금'];
    $b = ['웃김', '대박', '피곤', '배고픔', '신기함', '설렘', '개꿀', '꿀잼', '노잼', '힐링', '현타', '멘붕', '소름', '감동', '짜증'];
    $c = ['각', '중', '옴', '임', '함', '됨', '요', ''];
    for ($i = 0; $i < 80; $i++) {
        $w = $a[array_rand($a)] . $b[array_rand($b)] . $c[array_rand($c)];
        $w = mafia_미션단어정제($w);
        if ($w !== '' && !isset($bannedMap[$w])) {
            return $w;
        }
    }
    $tail = ['각', '중', '임', '요'];
    $w = '오늘' . date('Hi') . $tail[array_rand($tail)];
    $w = mafia_미션단어정제($w);
    return $w !== '' ? $w : ('미션' . mt_rand(10, 99));
}

/**
 * 매번 다른 미션 단어 (GPT 우선 · 대형 풀 폴백 · 최근 사용 제외)
 * @param string[]|null $제외추가
 */
function mafia_미션단어추첨(?string $제외 = null, ?array $제외추가 = null): string {
    $banned = [];
    $제외 = trim((string)$제외);
    if ($제외 !== '') {
        $banned[$제외] = $제외;
    }
    if (is_array($제외추가)) {
        foreach ($제외추가 as $x) {
            $x = trim((string)$x);
            if ($x !== '') {
                $banned[$x] = $x;
            }
        }
    }
    foreach (mafia_미션최근목록() as $x) {
        $banned[$x] = $x;
    }
    $bannedList = array_values($banned);

    // 1) GPT 최대 5회 — 중복이면 재시도
    for ($i = 0; $i < 5; $i++) {
        $gpt = mafia_미션단어_GPT생성($bannedList);
        if ($gpt !== '') {
            return $gpt;
        }
    }

    // 2) 풀에서 최근·직전 제외 후 랜덤
    $pool = array_values(array_filter(mafia_미션단어풀(), static function ($w) use ($banned) {
        return !isset($banned[$w]);
    }));
    if ($pool !== []) {
        shuffle($pool);
        return (string)$pool[0];
    }

    // 3) GPT 한 번 더 후 조합 폴백 (중복 허용 풀 재사용 금지)
    $gpt = mafia_미션단어_GPT생성($bannedList);
    if ($gpt !== '') {
        return $gpt;
    }
    return mafia_미션단어_유니크폴백($bannedList);
}

/**
 * 라운드 내 마피아 익명 라벨 (닉 정렬 고정) nick => 마피아N
 * @return array<string,string>
 */
function mafia_마피아익명라벨(int $round): array {
    $players = mafia_플레이어목록($round);
    $mafia = [];
    foreach ($players as $p) {
        if (($p['role'] ?? '') === 'mafia') {
            $mafia[] = (string)$p['nick'];
        }
    }
    sort($mafia, SORT_STRING);
    $map = [];
    $i = 1;
    foreach ($mafia as $nick) {
        $map[$nick] = '마피아' . $i;
        $i++;
    }
    return $map;
}

/**
 * 밤 공통미션 배정 (단어만 교체 · pot은 라운드 중 유지 · 최근 단어 기록)
 * · 밤 시작 배정 시 미션 1회 변경권 초기화
 */
function mafia_미션배정(bool $resetPot = false): string {
    $game = mafia_게임();
    $prev = trim((string)($game['mission_word'] ?? ''));
    $recent = mafia_미션최근목록($game);
    $word = mafia_미션단어추첨($prev !== '' ? $prev : null, $recent);
    if ($word === '' || $word === $prev) {
        $word = mafia_미션단어_유니크폴백(array_merge($recent, $prev !== '' ? [$prev] : []));
    }

    // 최근 사용 앞에 추가 (중복 방지용 장기 보관)
    array_unshift($recent, $word);
    $seen = [];
    $recentKeep = [];
    $maxRecent = max(40, (int)MAFIA_MISSION_RECENT_MAX);
    foreach ($recent as $w) {
        $w = trim((string)$w);
        if ($w === '' || isset($seen[$w])) {
            continue;
        }
        $seen[$w] = true;
        $recentKeep[] = $w;
        if (count($recentKeep) >= $maxRecent) {
            break;
        }
    }
    $recent_esc = addslashes(implode(',', $recentKeep));
    $word_esc = addslashes($word);
    $potSql = $resetPot ? ', mission_pot = 0' : '';
    @db_query("UPDATE tb_mafia_game SET
        mission_word = '{$word_esc}',
        mission_started_at = NOW(),
        mission_recent = '{$recent_esc}',
        mission_reroll_used = 0
        {$potSql}
        , updated_at = NOW()
        WHERE idx = 1");
    return $word;
}

/**
 * 생존 마피아 · 밤마다 공통미션 단어 1회 변경 (팀 공용)
 * · 변경 시 발화 검사 시작시각도 갱신 (새 단어부터 다시 말해야 함)
 * @return array{ok:bool,msg:string,word?:string}
 */
function mafia_미션변경(string $nick): array {
    mafia_틱();
    $nick = mafia_닉정규화($nick);
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? ''));
    if ($round < 1 || $phase !== 'night') {
        return ['ok' => false, 'msg' => '밤에만 미션을 바꿀 수 있어요.'];
    }
    $me = mafia_플레이어찾기($round, $nick);
    if (!$me || ($me['role'] ?? '') !== 'mafia' || empty($me['alive'])) {
        return ['ok' => false, 'msg' => '생존 마피아만 미션을 바꿀 수 있어요.'];
    }
    $cur = trim((string)($game['mission_word'] ?? ''));
    if ($cur === '') {
        return ['ok' => false, 'msg' => '아직 미션이 배정되지 않았어요.'];
    }
    if ((int)($game['mission_reroll_used'] ?? 0) === 1) {
        return ['ok' => false, 'msg' => '이번 밤 미션 변경은 이미 사용했어요.'];
    }

    // 먼저 변경권 선점 (동시 요청 방지)
    global $conn;
    @db_query("UPDATE tb_mafia_game SET mission_reroll_used = 1, updated_at = NOW()
        WHERE idx = 1 AND phase = 'night' AND IFNULL(mission_reroll_used, 0) = 0");
    $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
    if ($affected < 1) {
        return ['ok' => false, 'msg' => '이번 밤 미션 변경은 이미 사용했어요.'];
    }

    $game = mafia_게임();
    $prev = trim((string)($game['mission_word'] ?? ''));
    $recent = mafia_미션최근목록($game);
    $word = mafia_미션단어추첨($prev !== '' ? $prev : null, $recent);
    if ($word === '' || $word === $prev) {
        $word = mafia_미션단어_유니크폴백(array_merge($recent, $prev !== '' ? [$prev] : []));
    }

    array_unshift($recent, $word);
    $seen = [];
    $recentKeep = [];
    $maxRecent = max(40, (int)MAFIA_MISSION_RECENT_MAX);
    foreach ($recent as $w) {
        $w = trim((string)$w);
        if ($w === '' || isset($seen[$w])) {
            continue;
        }
        $seen[$w] = true;
        $recentKeep[] = $w;
        if (count($recentKeep) >= $maxRecent) {
            break;
        }
    }
    $recent_esc = addslashes(implode(',', $recentKeep));
    $word_esc = addslashes($word);
    @db_query("UPDATE tb_mafia_game SET
        mission_word = '{$word_esc}',
        mission_started_at = NOW(),
        mission_recent = '{$recent_esc}',
        mission_reroll_used = 1,
        updated_at = NOW()
        WHERE idx = 1");

    return [
        'ok' => true,
        'msg' => "미션이 「{$word}」로 바뀌었어요. (이번 밤 변경권 소진)",
        'word' => $word,
    ];
}

/**
 * 닉이 구간 내 공창(tb_msg)에서 미션 단어를 말했는지
 */
function mafia_미션발화여부(string $nick, string $word, string $since): bool {
    $nick = mafia_닉정규화($nick);
    $word = trim($word);
    $since = trim($since);
    if ($nick === '' || $word === '' || $since === '') {
        return false;
    }
    $nick_esc = addslashes($nick);
    $since_esc = addslashes($since);
    // 두자리/원본 닉 모두 매칭 · 메시지만 (타수 조작 로우 제외)
    $rs = @db_query("SELECT nickname, msg FROM tb_msg
        WHERE regdate >= '{$since_esc}'
          AND TRIM(IFNULL(msg, '')) <> ''
          AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
          AND (nickname = '{$nick_esc}' OR nickname LIKE '{$nick_esc}%')
        ORDER BY regdate ASC
        LIMIT 200");
    if (!$rs) {
        return false;
    }
    while ($row = db_fetch($rs)) {
        $rawNick = trim((string)($row['nickname'] ?? ''));
        if (mafia_닉정규화($rawNick) !== $nick) {
            continue;
        }
        $msg = (string)($row['msg'] ?? '');
        if ($msg !== '' && mb_stripos($msg, $word) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 밤→낮: 공통미션 검사 · 익명 알림 · 성공 시 본방냥 0.3% 누적
 * · 개인 실패 2회(MAFIA_MISSION_MAX_FAILS) 도달 시 비마피아와 역할 랜덤 교체
 * @return array{ok:bool,success:string[],added:int,pot:int,word:string,swapped:int}
 */
function mafia_미션결산(int $round): array {
    $game = mafia_게임();
    $word = trim((string)($game['mission_word'] ?? ''));
    $since = trim((string)($game['mission_started_at'] ?? ''));
    $pot = (int)($game['mission_pot'] ?? 0);
    $out = ['ok' => false, 'success' => [], 'added' => 0, 'pot' => $pot, 'word' => $word, 'swapped' => 0];
    if ($round < 1 || $word === '' || $since === '') {
        return $out;
    }

    $maxFails = max(1, (int)MAFIA_MISSION_MAX_FAILS);
    $players = mafia_플레이어목록($round);
    $successCount = 0;
    $swapCandidates = [];
    foreach ($players as $p) {
        if (($p['role'] ?? '') !== 'mafia') {
            continue;
        }
        // 밤 시작 시점 생존자만 (이미 죽은 마피아는 제외)
        if (empty($p['alive'])) {
            continue;
        }
        $nick = (string)$p['nick'];
        $esc = addslashes($nick);
        $fails = (int)($p['mission_fails'] ?? 0);
        if (mafia_미션발화여부($nick, $word, $since)) {
            $successCount++;
            continue;
        }
        $fails++;
        @db_query("UPDATE tb_mafia_player SET mission_fails = {$fails}
            WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");
        if ($fails >= $maxFails) {
            $swapCandidates[] = $nick;
        }
    }
    $out['success'] = $successCount > 0 ? array_fill(0, $successCount, '마피아') : [];
    $out['ok'] = true;

    if ($successCount < 1) {
        mafia_로그($round, '🕵️ 마피아 공통미션 실패 · 누적 없음');
        mafia_공창알림(
            "🕵️ 마피아 공통미션 결과 (라운드 #{$round})\n"
            . "이번 밤 미션 성공자가 없어요.\n"
            . "누적 성공보수: " . (function_exists('newpoint표시') ? newpoint표시($pot) : number_format($pot)) . '냥',
            'mafia_mission'
        );
    } else {
        mafia_함수로드();
        $added = 0;
        if (function_exists('newpoint비율계산')) {
            $added = (int)newpoint비율계산((float)MAFIA_MISSION_REWARD_RATE);
        }
        if ($added < 1) {
            mafia_로그($round, "🕵️ 공통미션 성공 마피아 {$successCount}명 · 보상 산정 0 · 누적 없음");
            mafia_공창알림(
                "🕵️ 마피아 공통미션 결과 (라운드 #{$round})\n"
                . "마피아 {$successCount}명이 미션을 성공했지만\n"
                . "성공보수 산정이 0이라 누적되지 않았어요.\n"
                . "누적 성공보수: " . (function_exists('newpoint표시') ? newpoint표시($pot) : number_format($pot)) . '냥',
                'mafia_mission'
            );
        } else {
            $pot += $added;
            @db_query("UPDATE tb_mafia_game SET mission_pot = {$pot}, updated_at = NOW() WHERE idx = 1");
            $out['added'] = $added;
            $out['pot'] = $pot;

            $pctLabel = rtrim(rtrim(sprintf('%.2F', (float)MAFIA_MISSION_REWARD_RATE * 100), '0'), '.');
            $추가표시 = function_exists('newpoint표시') ? newpoint표시($added) : number_format($added);
            $누적표시 = function_exists('newpoint표시') ? newpoint표시($pot) : number_format($pot);
            mafia_로그($round, "🕵️ 공통미션 성공 마피아 {$successCount}명 · +{$추가표시}냥({$pctLabel}%) 누적 (총 {$누적표시})");
            mafia_공창알림(
                "🕵️ 마피아 공통미션 결과 (라운드 #{$round})\n"
                . "마피아 {$successCount}명이 미션을 성공하여\n"
                . "성공보수 {$추가표시}냥(본방냥 {$pctLabel}%)이 누적되었습니다\n"
                . "· 이번 +{$추가표시}냥 · 누적 {$누적표시}냥",
                'mafia_mission'
            );
        }
    }

    if ($swapCandidates !== []) {
        $swapped = mafia_미션실패역할교체($round, $swapCandidates);
        $out['swapped'] = count($swapped);
    }
    return $out;
}

/**
 * 미션 실패 한도 도달 마피아 ↔ 생존 비마피아 역할 랜덤 교체
 * @param list<string> $mafiaNicks
 * @return list<array{mafia:string,partner:string,partner_role:string}>
 */
function mafia_미션실패역할교체(int $round, array $mafiaNicks): array {
    $round = (int)$round;
    $maxFails = max(1, (int)MAFIA_MISSION_MAX_FAILS);
    $mafiaNicks = array_values(array_unique(array_filter(array_map('trim', $mafiaNicks))));
    if ($round < 1 || $mafiaNicks === []) {
        return [];
    }

    $players = mafia_플레이어목록($round);
    $byNick = [];
    $pool = [];
    foreach ($players as $p) {
        $n = trim((string)($p['nick'] ?? ''));
        if ($n === '') {
            continue;
        }
        $byNick[$n] = $p;
        if (!empty($p['alive']) && ($p['role'] ?? '') !== 'mafia') {
            $pool[] = $n;
        }
    }
    if ($pool === []) {
        return [];
    }
    shuffle($pool);

    $done = [];
    $usedPartner = [];
    foreach ($mafiaNicks as $mafiaNick) {
        if (empty($byNick[$mafiaNick]['alive']) || ($byNick[$mafiaNick]['role'] ?? '') !== 'mafia') {
            continue;
        }
        $partner = '';
        foreach ($pool as $cand) {
            if (isset($usedPartner[$cand])) {
                continue;
            }
            if (empty($byNick[$cand]['alive']) || ($byNick[$cand]['role'] ?? '') === 'mafia') {
                continue;
            }
            $partner = $cand;
            break;
        }
        if ($partner === '') {
            break;
        }
        $usedPartner[$partner] = true;
        $partnerRole = (string)($byNick[$partner]['role'] ?? 'citizen');
        $mafia_esc = addslashes($mafiaNick);
        $partner_esc = addslashes($partner);
        $role_esc = addslashes($partnerRole);

        // 마피아 → 상대 역할 / 상대 → 마피아 · 실패횟수 초기화
        @db_query("UPDATE tb_mafia_player
            SET role = '{$role_esc}', mission_fails = 0,
                night_action_nick = '', day_vote_nick = '',
                investigated = 0, investigate_result = ''
            WHERE round_no = {$round} AND nick = '{$mafia_esc}' LIMIT 1");
        @db_query("UPDATE tb_mafia_player
            SET role = 'mafia', mission_fails = 0,
                night_action_nick = '', day_vote_nick = '',
                investigated = 0, investigate_result = ''
            WHERE round_no = {$round} AND nick = '{$partner_esc}' LIMIT 1");

        $byNick[$mafiaNick]['role'] = $partnerRole;
        $byNick[$mafiaNick]['mission_fails'] = 0;
        $byNick[$partner]['role'] = 'mafia';
        $byNick[$partner]['mission_fails'] = 0;

        $done[] = [
            'mafia' => $mafiaNick,
            'partner' => $partner,
            'partner_role' => $partnerRole,
        ];
        // 역할 교체 상세는 기록(로그)에 남기지 않음
    }

    $n = count($done);
    if ($n > 0) {
        mafia_공창알림(
            "🔄 마피아 {$n}명이 미션에 실패하여 역할이 랜덤교체 되었습니다 (라운드 #{$round})\n"
            . "· 가방→마피아에서 내 역할을 다시 확인하세요",
            'mafia_mission_swap'
        );
    }
    return $done;
}

/**
 * 마피아 승리 시 공통미션 누적분 지급
 */
function mafia_미션누적지급(int $round): void {
    $game = mafia_게임();
    $pot = (int)($game['mission_pot'] ?? 0);
    if ($pot < 1) {
        return;
    }
    $players = mafia_플레이어목록($round);
    $survivors = [];
    foreach ($players as $p) {
        if (!empty($p['alive']) && ($p['role'] ?? '') === 'mafia') {
            $n = trim((string)($p['nick'] ?? ''));
            if ($n !== '') {
                $survivors[$n] = $n;
            }
        }
    }
    $survivors = array_values($survivors);
    if ($survivors === []) {
        @db_query("UPDATE tb_mafia_game SET mission_pot = 0, updated_at = NOW() WHERE idx = 1");
        mafia_로그($round, '🕵️ 미션 누적보상 · 생존 마피아 없음 · 소멸');
        return;
    }

    $n = count($survivors);
    $each = (int)floor($pot / $n);
    $remain = $pot - ($each * $n);
    $지급목록 = [];
    foreach ($survivors as $i => $nick) {
        $amt = $each + ($i < $remain ? 1 : 0);
        if ($amt < 1) {
            continue;
        }
        $esc = addslashes($nick);
        @db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$amt} WHERE name = '{$esc}' AND status = 0 LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('마피아미션', $nick, 'mission', 0, $amt);
        }
        $지급목록[] = $nick;
    }
    @db_query("UPDATE tb_mafia_game SET mission_pot = 0, updated_at = NOW() WHERE idx = 1");
    $표시 = function_exists('newpoint표시') ? newpoint표시($pot) : number_format($pot);
    $명수 = count($지급목록);
    mafia_로그($round, "🕵️ 미션 누적보상 {$표시}냥 → 생존 마피아 {$명수}명 분배");
    if ($명수 > 0) {
        mafia_공창알림(
            "🕵️ 미션 누적보상 지급! #{$round}\n"
            . "🎁 생존 마피아 {$명수}명에게 누적 {$표시}냥 분배",
            'mafia_mission_pay'
        );
    }
}

/** 현재 라운드 내 역할 한글명 (미참여·대기면 '') */
function mafia_내역할라벨(string $nick): string {
    $nick = trim($nick);
    if ($nick === '') {
        return '';
    }
    try {
        $game = mafia_게임();
        $round = (int)($game['round_no'] ?? 0);
        $phase = trim((string)($game['phase'] ?? 'idle'));
        if ($round < 1 || !in_array($phase, ['night', 'day', 'waiting'], true)) {
            return '';
        }
        $me = mafia_플레이어찾기($round, $nick);
        if ($me === null) {
            return '';
        }
        return mafia_역할라벨($me['role'] ?? '');
    } catch (Throwable $e) {
        return '';
    }
}

function mafia_페이즈라벨($phase): string {
    $map = [
        'idle' => '대기',
        'night' => '밤',
        'day' => '낮',
        'waiting' => '다음 라운드 대기',
    ];
    $phase = trim((string)$phase);
    return $map[$phase] ?? $phase;
}

/**
 * 득표 상위 N명 선정 (동률이면 해당 순위 안에서 랜덤)
 * @param array<string,int> $votes nick => 표수
 * @return string[]
 */
function mafia_투표상위선정(array $votes, int $limit = 2): array {
    $limit = max(1, $limit);
    if ($votes === []) {
        return [];
    }
    arsort($votes);
    $byScore = [];
    foreach ($votes as $nick => $cnt) {
        $cnt = (int)$cnt;
        if ($cnt < 1) {
            continue;
        }
        $byScore[$cnt][] = (string)$nick;
    }
    krsort($byScore, SORT_NUMERIC);
    $picked = [];
    foreach ($byScore as $cands) {
        shuffle($cands);
        foreach ($cands as $nick) {
            $picked[] = $nick;
            if (count($picked) >= $limit) {
                return $picked;
            }
        }
    }
    return $picked;
}

/** 콤마 구분 닉 목록 파싱 (최대 $max개) */
function mafia_닉목록파싱($raw, int $max = 2): array {
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/\s*,\s*/u', trim((string)$raw)) ?: [];
    }
    $out = [];
    foreach ($parts as $p) {
        $n = trim((string)$p);
        if ($n === '' || isset($out[$n])) {
            continue;
        }
        $out[$n] = $n;
        if (count($out) >= max(1, $max)) {
            break;
        }
    }
    return array_values($out);
}

function mafia_닉목록직렬화(array $nicks): string {
    $nicks = mafia_닉목록파싱($nicks, 8);
    return implode(',', $nicks);
}

function mafia_플레이어목록(int $round): array {
    $round = (int)$round;
    if ($round < 1) {
        return [];
    }
    $rs = @db_query("SELECT nick, role, alive, night_action_nick, day_vote_nick, investigated, investigate_result,
            IFNULL(mission_fails, 0) AS mission_fails
        FROM tb_mafia_player WHERE round_no = {$round} ORDER BY nick ASC");
    $out = [];
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nightRaw = trim((string)($row['night_action_nick'] ?? ''));
            $dayRaw = trim((string)($row['day_vote_nick'] ?? ''));
            $out[] = [
                'nick' => trim((string)($row['nick'] ?? '')),
                'role' => trim((string)($row['role'] ?? 'citizen')),
                'alive' => (int)($row['alive'] ?? 0) === 1,
                'night_action_nick' => $nightRaw,
                'night_action_nicks' => mafia_닉목록파싱($nightRaw, (int)MAFIA_NIGHT_TARGETS),
                'day_vote_nick' => $dayRaw,
                'day_vote_nicks' => mafia_닉목록파싱($dayRaw, 4),
                'investigated' => (int)($row['investigated'] ?? 0) === 1,
                'investigate_result' => trim((string)($row['investigate_result'] ?? '')),
                'mission_fails' => (int)($row['mission_fails'] ?? 0),
            ];
        }
    }
    return $out;
}

function mafia_플레이어찾기(int $round, string $nick): ?array {
    $nick = trim($nick);
    if ($nick === '' || $round < 1) {
        return null;
    }
    $esc = addslashes($nick);
    $row = @db_select("SELECT nick, role, alive, night_action_nick, day_vote_nick, investigated, investigate_result,
            IFNULL(mission_fails, 0) AS mission_fails
        FROM tb_mafia_player WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");
    if (empty($row['nick'])) {
        return null;
    }
    $nightRaw = trim((string)($row['night_action_nick'] ?? ''));
    $dayRaw = trim((string)($row['day_vote_nick'] ?? ''));
    return [
        'nick' => trim((string)$row['nick']),
        'role' => trim((string)($row['role'] ?? 'citizen')),
        'alive' => (int)($row['alive'] ?? 0) === 1,
        'night_action_nick' => $nightRaw,
        'night_action_nicks' => mafia_닉목록파싱($nightRaw, (int)MAFIA_NIGHT_TARGETS),
        'day_vote_nick' => $dayRaw,
        'day_vote_nicks' => mafia_닉목록파싱($dayRaw, 4),
        'investigated' => (int)($row['investigated'] ?? 0) === 1,
        'investigate_result' => trim((string)($row['investigate_result'] ?? '')),
        'mission_fails' => (int)($row['mission_fails'] ?? 0),
    ];
}

function mafia_라운드시작(?string $사유 = null): array {
    mafia_테이블보장();
    $nicks = mafia_활성닉목록();
    $n = count($nicks);
    if ($n < 4) {
        return ['ok' => false, 'msg' => '활성 인원이 4명 이상이어야 시작할 수 있어요. (현재 ' . $n . '명)'];
    }
    $배분 = mafia_역할배분표($n);
    $역할맵 = mafia_역할배정($nicks);

    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0) + 1;
    $ends = mafia_다음페이즈종료();
    $분 = mafia_페이즈남은분($ends);

    @db_query("UPDATE tb_mafia_game SET
        round_no = {$round},
        phase = 'night',
        phase_ends_at = '{$ends}',
        winner = '',
        last_result = '',
        day_no = 0,
        updated_at = NOW()
        WHERE idx = 1");

    foreach ($nicks as $nick) {
        $role = $역할맵[$nick] ?? 'citizen';
        $nick_esc = addslashes($nick);
        $role_esc = addslashes($role);
        @db_query("INSERT INTO tb_mafia_player
            SET round_no = {$round}, nick = '{$nick_esc}', role = '{$role_esc}', alive = 1,
                night_action_nick = '', day_vote_nick = '', investigated = 0, investigate_result = '',
                mission_fails = 0
            ON DUPLICATE KEY UPDATE
                role = VALUES(role), alive = 1,
                night_action_nick = '', day_vote_nick = '', investigated = 0, investigate_result = '',
                mission_fails = 0");
    }

    $미션단어 = mafia_미션배정(true);

    $사유 = trim((string)$사유);
    $msg = "라운드 #{$round} 시작 · 밤 페이즈 (다음 10분 정각까지 약 {$분}분)";
    $msg .= " · 마피아 {$배분['mafia']} · 경찰 {$배분['police']} · 의사 {$배분['doctor']} · 정치인 " . (int)($배분['politician'] ?? 0) . " · 시민 {$배분['citizen']}";
    $msg .= ' · 공창참여 가중배정';
    $msg .= ' · 공통미션 배정';
    if ($사유 !== '') {
        $msg .= " · {$사유}";
    }
    mafia_로그($round, $msg);

    $maxFails = max(1, (int)MAFIA_MISSION_MAX_FAILS);
    $pol = (int)($배분['politician'] ?? 0);
    mafia_공창알림(
        "🕵️ 마피아 라운드 #{$round} 시작!\n"
        . "인원 {$n}명 · 마피아 {$배분['mafia']} · 경찰 {$배분['police']} · 의사 {$배분['doctor']}"
        . ($pol > 0 ? " · 정치인 {$pol}" : '') . "\n"
        . "🌙 밤이 됐어요 · 다음 10분 정각까지 약 {$분}분 (12:00·10·20·30·40·50 기준)\n"
        . "마피아 공통미션: 기회 {$maxFails}회 · 연속 실패 시 다른 역할과 교체\n"
        . "가방 → 마피아에서 확인하세요",
        'mafia_start'
    );

    return [
        'ok' => true,
        'msg' => $msg,
        'round' => $round,
        'counts' => $배분,
        'players' => $n,
    ];
}

function mafia_초기화(string $adminNick): array {
    if (!mafia_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '초기화는 민호만 할 수 있어요.'];
    }
    return mafia_라운드시작('수동 초기화');
}

/**
 * 민호 전용 · 진행 중 플레이어 역할 변경 (기록 로그에 남기지 않음)
 */
function mafia_관리자역할변경(string $adminNick, string $targetNick, string $newRole): array {
    if (!mafia_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '역할 변경은 민호만 할 수 있어요.'];
    }
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? ''));
    if ($round < 1 || !in_array($phase, ['night', 'day'], true)) {
        return ['ok' => false, 'msg' => '진행 중인 밤/낮 라운드에서만 역할을 바꿀 수 있어요.'];
    }

    $targetNick = mafia_닉정규화($targetNick);
    $newRole = trim((string)$newRole);
    $allowed = ['mafia', 'citizen', 'police', 'doctor', 'politician'];
    if ($targetNick === '' || !in_array($newRole, $allowed, true)) {
        return ['ok' => false, 'msg' => '대상 닉·역할을 확인해주세요. (마피아/시민/경찰/의사/정치인)'];
    }

    $me = mafia_플레이어찾기($round, $targetNick);
    if (!$me) {
        return ['ok' => false, 'msg' => "{$targetNick} 님은 이번 라운드 참가자가 아니에요."];
    }

    $oldRole = (string)($me['role'] ?? 'citizen');
    if ($oldRole === $newRole) {
        return ['ok' => true, 'msg' => mafia_역할라벨($newRole) . ' 역할이 이미 적용돼 있어요.'];
    }

    $esc = addslashes($targetNick);
    $role_esc = addslashes($newRole);
    @db_query("UPDATE tb_mafia_player
        SET role = '{$role_esc}',
            night_action_nick = '',
            day_vote_nick = '',
            investigated = 0,
            investigate_result = '',
            mission_fails = 0
        WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");

    $from = mafia_역할라벨($oldRole);
    $to = mafia_역할라벨($newRole);
    // 의도적으로 mafia_로그 미기록 (기록 탭에 교체/변경 노출 방지)
    return [
        'ok' => true,
        'msg' => "{$targetNick}: {$from} → {$to} 변경했어요.",
        'nick' => $targetNick,
        'role' => $newRole,
        'role_label' => $to,
    ];
}

/**
 * 민호 전용 · 현재 생존 마피아 전원 재편입 (미션실패·행동 초기화, 기록 미남김)
 */
function mafia_생존마피아재편입(string $adminNick): array {
    if (!mafia_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '재편입은 민호만 할 수 있어요.'];
    }
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? ''));
    if ($round < 1 || !in_array($phase, ['night', 'day'], true)) {
        return ['ok' => false, 'msg' => '진행 중인 밤/낮 라운드에서만 재편입할 수 있어요.'];
    }

    $players = mafia_플레이어목록($round);
    $nicks = [];
    foreach ($players as $p) {
        if (empty($p['alive']) || ($p['role'] ?? '') !== 'mafia') {
            continue;
        }
        $n = trim((string)($p['nick'] ?? ''));
        if ($n !== '') {
            $nicks[] = $n;
        }
    }
    if ($nicks === []) {
        return ['ok' => false, 'msg' => '생존 마피아가 없어요.'];
    }

    $escList = [];
    foreach ($nicks as $n) {
        $escList[] = "'" . addslashes($n) . "'";
    }
    $in = implode(',', $escList);
    @db_query("UPDATE tb_mafia_player
        SET role = 'mafia',
            mission_fails = 0,
            night_action_nick = '',
            day_vote_nick = '',
            investigated = 0,
            investigate_result = ''
        WHERE round_no = {$round}
          AND alive = 1
          AND nick IN ({$in})");

    // 전원 현재 낮 투표·밤 지목 리셋
    @db_query("UPDATE tb_mafia_player
        SET night_action_nick = '',
            day_vote_nick = ''
        WHERE round_no = {$round}");

    $cnt = count($nicks);
    $명단 = implode(', ', $nicks);
    // 기록(로그)에는 남기지 않음
    return [
        'ok' => true,
        'msg' => "생존 마피아 {$cnt}명 재편입 · 전원 투표/지목 리셋 ({$명단})",
        'count' => $cnt,
        'nicks' => $nicks,
    ];
}

/**
 * 민호 전용 · 낮/밤 강제 전환 (현재 페이즈 결산 후 이동)
 * @param string $target 'day'|'night'
 */
function mafia_페이즈강제(string $adminNick, string $target): array {
    if (!mafia_준호인가($adminNick)) {
        return ['ok' => false, 'msg' => '페이즈 강제 전환은 민호만 할 수 있어요.'];
    }
    $target = trim($target);
    if ($target !== 'day' && $target !== 'night') {
        return ['ok' => false, 'msg' => 'day 또는 night 만 가능해요.'];
    }

    mafia_틱();
    $game = mafia_게임();
    $phase = trim((string)($game['phase'] ?? 'idle'));
    $round = (int)($game['round_no'] ?? 0);

    if ($round < 1 || $phase === 'idle') {
        return ['ok' => false, 'msg' => '진행 중인 라운드가 없어요. 먼저 초기화하세요.'];
    }
    if ($phase === 'waiting') {
        return ['ok' => false, 'msg' => '다음 라운드 대기 중이에요. 주말 오전 ' . (int)MAFIA_DAILY_START_HOUR . '시에 자동 시작되거나 초기화하세요.'];
    }

    if ($target === 'day') {
        if ($phase === 'day') {
            return ['ok' => false, 'msg' => '이미 낮 페이즈예요.'];
        }
        if ($phase !== 'night') {
            return ['ok' => false, 'msg' => '밤일 때만 낮으로 넘길 수 있어요.'];
        }
        mafia_로그($round, '⚡ 민호가 낮으로 강제 전환');
        mafia_밤결산($round);
        $g2 = mafia_게임();
        return [
            'ok' => true,
            'msg' => '낮으로 강제 전환했어요. (현재: ' . mafia_페이즈라벨($g2['phase'] ?? '') . ')',
        ];
    }

    // night
    if ($phase === 'night') {
        return ['ok' => false, 'msg' => '이미 밤 페이즈예요.'];
    }
    if ($phase !== 'day') {
        return ['ok' => false, 'msg' => '낮일 때만 밤으로 넘길 수 있어요.'];
    }
    mafia_로그($round, '⚡ 민호가 밤으로 강제 전환');
    mafia_낮결산($round);
    $g2 = mafia_게임();
    return [
        'ok' => true,
        'msg' => '밤으로 강제 전환했어요. (현재: ' . mafia_페이즈라벨($g2['phase'] ?? '') . ')',
    ];
}

function mafia_생존집계(array $players): array {
    $aliveMafia = 0;
    $aliveTown = 0;
    $alive = 0;
    $citizen = 0;
    $police = 0;
    $doctor = 0;
    $politician = 0;
    foreach ($players as $p) {
        if (empty($p['alive'])) {
            continue;
        }
        $alive++;
        $role = (string)($p['role'] ?? '');
        if ($role === 'mafia') {
            $aliveMafia++;
        } else {
            $aliveTown++;
            if ($role === 'police') {
                $police++;
            } elseif ($role === 'doctor') {
                $doctor++;
            } elseif ($role === 'politician') {
                $politician++;
            } else {
                $citizen++;
            }
        }
    }
    return [
        'alive' => $alive,
        'mafia' => $aliveMafia,
        'town' => $aliveTown,
        'citizen' => $citizen,
        'police' => $police,
        'doctor' => $doctor,
        'politician' => $politician,
    ];
}

/** 생존자 역할별 현황 한 줄 (살아있는 인원만) */
function mafia_생존현황문구(array $생존): string {
    $alive = (int)($생존['alive'] ?? 0);
    $mafia = (int)($생존['mafia'] ?? 0);
    $citizen = (int)($생존['citizen'] ?? 0);
    $police = (int)($생존['police'] ?? 0);
    $doctor = (int)($생존['doctor'] ?? 0);
    $politician = (int)($생존['politician'] ?? 0);
    return "생존 {$alive}명 · 마피아 {$mafia} · 시민 {$citizen} · 의사 {$doctor} · 경찰 {$police} · 정치인 {$politician}";
}

function mafia_승패판정(array $players): string {
    $c = mafia_생존집계($players);
    if ($c['mafia'] <= 0 && $c['alive'] > 0) {
        return 'town';
    }
    if ($c['mafia'] > 0 && $c['mafia'] >= $c['town']) {
        return 'mafia';
    }
    return '';
}

function mafia_제거(int $round, string $nick, string $사유): bool {
    $nick = trim($nick);
    if ($nick === '') {
        return false;
    }
    $esc = addslashes($nick);
    @db_query("UPDATE tb_mafia_player SET alive = 0 WHERE round_no = {$round} AND nick = '{$esc}' AND alive = 1 LIMIT 1");
    mafia_로그($round, "💀 {$nick} 탈락 · {$사유}");
    return true;
}

/**
 * 의사 보호 성공 치료비: 본방냥 총합 × 1%를 피보호자→의사
 * 1) 본방냥 충분 → 본방냥 이전
 * 2) 본방냥 부족·게임냥 충분 → 동일 금액을 게임냥으로 이전
 * 3) 둘 다 부족 → 실패(사망)
 *
 * @return array{ok:bool,currency?:string,amount?:int|string,amount_label?:string,msg?:string}
 */
function mafia_의사보호수수료정산(int $round, string $doctorNick, string $patientNick): array {
    $doctorNick = trim($doctorNick);
    $patientNick = trim($patientNick);
    if ($doctorNick === '' || $patientNick === '') {
        return ['ok' => false, 'msg' => '대상 없음'];
    }
    // 자기 보호: 이전 없이 생존만
    if ($doctorNick === $patientNick) {
        return ['ok' => true, 'currency' => 'none', 'amount' => 0, 'amount_label' => '0', 'msg' => '자기보호'];
    }

    mafia_함수로드();
    $fee = 0;
    if (function_exists('newpoint비율계산')) {
        $fee = (int)newpoint비율계산((float)MAFIA_DOCTOR_SAVE_FEE_RATE);
    }
    if ($fee < 1) {
        return ['ok' => true, 'currency' => 'none', 'amount' => 0, 'amount_label' => '0', 'msg' => '치료비0'];
    }

    $pEsc = addslashes($patientNick);
    $dEsc = addslashes($doctorNick);
    $row = @db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point, IFNULL(newpoint, 0) AS newpoint
        FROM tb_member WHERE name = '{$pEsc}' AND status = 0 LIMIT 1");
    if (empty($row)) {
        return ['ok' => false, 'msg' => '회원 없음'];
    }

    $np = (float)($row['newpoint'] ?? 0);
    $gp = preg_replace('/[^\d]/', '', (string)($row['point'] ?? '0')) ?: '0';
    $feeStr = (string)$fee;
    $feeLabel = function_exists('newpoint표시') ? newpoint표시($fee) : number_format($fee);

    // 1) 본방냥
    if ($np + 1e-9 >= $fee) {
        global $conn;
        @db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) - {$fee}
            WHERE name = '{$pEsc}' AND status = 0 AND IFNULL(newpoint, 0) >= {$fee} LIMIT 1");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if ($ok) {
            @db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$fee}
                WHERE name = '{$dEsc}' AND status = 0 LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('마피아의사치료', $patientNick, $doctorNick, 0, -$fee);
                지급로그('마피아의사치료', $doctorNick, $patientNick, 0, $fee);
            }
            return [
                'ok' => true,
                'currency' => 'newpoint',
                'amount' => $fee,
                'amount_label' => $feeLabel,
            ];
        }
    }

    // 2) 게임냥 (본방 1%와 동일 수량)
    $gpEnough = function_exists('bccomp')
        ? (bccomp($gp, $feeStr, 0) >= 0)
        : ((float)$gp >= $fee);
    if ($gpEnough) {
        global $conn;
        @db_query("UPDATE tb_member SET point = point - {$feeStr}
            WHERE name = '{$pEsc}' AND status = 0 AND point >= {$feeStr} LIMIT 1");
        $ok = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
        if ($ok) {
            @db_query("UPDATE tb_member SET point = point + {$feeStr}
                WHERE name = '{$dEsc}' AND status = 0 LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('마피아의사치료겜', $patientNick, $doctorNick, -$fee, 0);
                지급로그('마피아의사치료겜', $doctorNick, $patientNick, $fee, 0);
            }
            $gpLabel = function_exists('랭킹_게임냥표시')
                ? 랭킹_게임냥표시($feeStr, '')
                : (function_exists('게임냥_안전표시') ? 게임냥_안전표시($feeStr, '') : number_format($fee));
            return [
                'ok' => true,
                'currency' => 'point',
                'amount' => $feeStr,
                'amount_label' => trim((string)$gpLabel) !== '' ? trim((string)$gpLabel) : number_format($fee),
            ];
        }
    }

    return ['ok' => false, 'msg' => '냥 부족'];
}

/**
 * 낮 투표로 마피아를 찾은 시민에게 본방냥 0.5% 균등 지급
 * · 냥보내기「주고받은 내역」에 라운드·마피아·보유전/후 기록
 * @param string[] $voterNicks 해당 마피아에게 투표한 비마피아(시민측)
 */
function mafia_마피아적발보상(int $round, string $mafiaNick, array $voterNicks): void {
    global $conn;
    $voterNicks = array_values(array_unique(array_filter(array_map('trim', $voterNicks))));
    if ($voterNicks === []) {
        return;
    }
    mafia_함수로드();
    $총보상 = 0;
    if (function_exists('newpoint비율계산')) {
        $총보상 = (int)newpoint비율계산((float)MAFIA_CATCH_REWARD_RATE);
    }
    if ($총보상 < 1) {
        mafia_로그($round, "🎉 {$mafiaNick} 마피아 적발! (보상 산정 0 · 지급 없음)");
        return;
    }
    $n = count($voterNicks);
    $each = (int)floor($총보상 / $n);
    $remain = $총보상 - ($each * $n);
    $지급성공 = 0;
    $지급합 = 0;
    $실패닉 = [];
    foreach ($voterNicks as $i => $nick) {
        $amt = $each + ($i < $remain ? 1 : 0);
        if ($amt < 1) {
            continue;
        }
        $esc = addslashes($nick);
        $beforeRow = @db_select(
            "SELECT CAST(CAST(IFNULL(newpoint, 0) AS DECIMAL(40,0)) AS CHAR) AS np
             FROM tb_member WHERE name = '{$esc}' AND status = 0 LIMIT 1"
        );
        if (empty($beforeRow)) {
            $실패닉[] = $nick;
            continue;
        }
        $before = preg_replace('/[^\d]/', '', (string)($beforeRow['np'] ?? '0')) ?: '0';
        $rs = @db_query(
            "UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$amt}
             WHERE name = '{$esc}' AND status = 0 LIMIT 1"
        );
        $ok = false;
        if ($rs && ($conn instanceof mysqli)) {
            $ok = ((int)mysqli_affected_rows($conn) > 0);
        } elseif ($rs) {
            $ok = true;
        }
        if (!$ok) {
            $실패닉[] = $nick;
            continue;
        }
        if (function_exists('bcadd')) {
            $after = bcadd($before, (string)$amt, 0);
        } else {
            $after = (string)((int)$before + $amt);
        }
        mafia_적발보상내역로그($round, $nick, $mafiaNick, $amt, $before, $after);
        $지급성공++;
        $지급합 += $amt;
    }
    $표시 = function_exists('newpoint표시') ? newpoint표시($총보상) : number_format($총보상);
    $실지급표시 = function_exists('newpoint표시') ? newpoint표시($지급합) : number_format($지급합);
    mafia_로그(
        $round,
        "🎉 {$mafiaNick} 마피아 적발! 투표 시민 {$지급성공}/{$n}명 · 본방냥 {$실지급표시}냥(산정 {$표시}·0.5%)"
        . ($실패닉 !== [] ? (' · 지급실패 ' . implode(',', $실패닉)) : '')
    );
    if ($지급성공 > 0) {
        mafia_공창알림(
            "🕵️ 마피아 적발! #{$round}\n"
            . "💀 {$mafiaNick} = 마피아\n"
            . "🎁 투표 시민 {$지급성공}명에게 본방냥 {$실지급표시}냥(전체 0.5%) 분배",
            'mafia_catch'
        );
    }
}

/**
 * 마피아 적발 보상 → tb_point_log (냥보내기 주고받은 내역에 표시)
 * receiver: 라운드#N 마피아 닉 보상
 * mypoint: 지급 후 본방냥 · tax: 지급 전 본방냥
 */
function mafia_적발보상내역로그(
    int $round,
    string $nick,
    string $mafiaNick,
    int $amt,
    string $before,
    string $after
): void {
    if ($nick === '' || $amt < 1) {
        return;
    }
    if (function_exists('지급로그_컬럼_보장')) {
        지급로그_컬럼_보장();
    }
    $label = '라운드#' . (int)$round . ' 마피아 ' . trim($mafiaNick) . ' 보상';
    $nick_esc = addslashes($nick);
    $recv_esc = addslashes(mb_substr($label, 0, 80));
    $amt_sql = function_exists('지급로그_금액_SQL') ? 지급로그_금액_SQL($amt) : (string)max(0, (int)$amt);
    $before_sql = function_exists('지급로그_금액_SQL') ? 지급로그_금액_SQL($before) : (preg_replace('/[^\d]/', '', $before) ?: '0');
    $after_sql = function_exists('지급로그_금액_SQL') ? 지급로그_금액_SQL($after) : (preg_replace('/[^\d]/', '', $after) ?: '0');
    @db_query(
        "INSERT INTO tb_point_log SET
            status = '마피아적발',
            nick = '{$nick_esc}',
            receiver = '{$recv_esc}',
            tax = {$before_sql},
            point = {$amt_sql},
            mypoint = {$after_sql},
            regdate = NOW()"
    );
}

function mafia_액션리셋(int $round): void {
    @db_query("UPDATE tb_mafia_player SET night_action_nick = '', day_vote_nick = '' WHERE round_no = {$round}");
}

/**
 * 마피아 승리 시 생존 마피아에게 본방냥 5% 균등 지급
 */
function mafia_마피아승리보상(int $round): void {
    $players = mafia_플레이어목록($round);
    $survivors = [];
    foreach ($players as $p) {
        if (!empty($p['alive']) && ($p['role'] ?? '') === 'mafia') {
            $n = trim((string)($p['nick'] ?? ''));
            if ($n !== '') {
                $survivors[$n] = $n;
            }
        }
    }
    $survivors = array_values($survivors);
    if ($survivors === []) {
        mafia_로그($round, '🩸 마피아 승리 보상 · 생존 마피아 없음 · 지급 없음');
        return;
    }

    mafia_함수로드();
    $총보상 = 0;
    if (function_exists('newpoint비율계산')) {
        $총보상 = (int)newpoint비율계산((float)MAFIA_WIN_REWARD_RATE);
    }
    if ($총보상 < 1) {
        mafia_로그($round, '🩸 마피아 승리 보상 · 산정 0 · 지급 없음');
        return;
    }

    $n = count($survivors);
    $each = (int)floor($총보상 / $n);
    $remain = $총보상 - ($each * $n);
    $지급목록 = [];
    foreach ($survivors as $i => $nick) {
        $amt = $each + ($i < $remain ? 1 : 0);
        if ($amt < 1) {
            continue;
        }
        $esc = addslashes($nick);
        @db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$amt} WHERE name = '{$esc}' AND status = 0 LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('마피아승리', $nick, 'mafia', 0, $amt);
        }
        $지급목록[] = $nick;
    }

    $표시 = function_exists('newpoint표시') ? newpoint표시($총보상) : number_format($총보상);
    $명수 = count($지급목록);
    $명단 = $명수 <= 8 ? implode(', ', $지급목록) : (implode(', ', array_slice($지급목록, 0, 8)) . ' 외');
    $pctLabel = rtrim(rtrim(sprintf('%.2F', (float)MAFIA_WIN_REWARD_RATE * 100), '0'), '.');
    mafia_로그($round, "🩸 마피아 승리! 생존 마피아 {$명수}명에게 본방냥 {$표시}냥({$pctLabel}%) 분배");
    if ($명수 > 0) {
        mafia_공창알림(
            "🩸 마피아 승리 보상! #{$round}\n"
            . "🎁 생존 마피아 {$명수}명에게 본방냥 {$표시}냥(전체 {$pctLabel}%) 분배\n"
            . ($명단 !== '' ? "· {$명단}" : ''),
            'mafia_win'
        );
    }
}

/**
 * 시민 승리(마피아 전멸) 시 생존자 전원에게 본방냥 1%씩 성공보수 지급
 */
function mafia_시민승리보상(int $round): void {
    $players = mafia_플레이어목록($round);
    $survivors = [];
    foreach ($players as $p) {
        if (empty($p['alive'])) {
            continue;
        }
        // 마피아 전멸 후 생존자(시민·경찰·의사·정치인 등)
        if (($p['role'] ?? '') === 'mafia') {
            continue;
        }
        $n = trim((string)($p['nick'] ?? ''));
        if ($n !== '') {
            $survivors[$n] = $n;
        }
    }
    $survivors = array_values($survivors);
    if ($survivors === []) {
        mafia_로그($round, '🎉 시민 승리 보상 · 생존자 없음 · 지급 없음');
        return;
    }

    mafia_함수로드();
    $인당 = 0;
    if (function_exists('newpoint비율계산')) {
        $인당 = (int)newpoint비율계산((float)MAFIA_TOWN_WIN_REWARD_RATE);
    }
    if ($인당 < 1) {
        mafia_로그($round, '🎉 시민 승리 보상 · 산정 0 · 지급 없음');
        return;
    }

    $지급목록 = [];
    $지급합 = 0;
    foreach ($survivors as $nick) {
        $esc = addslashes($nick);
        @db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$인당} WHERE name = '{$esc}' AND status = 0 LIMIT 1");
        if (function_exists('지급로그')) {
            지급로그('마피아시민승리', $nick, 'town', 0, $인당);
        }
        $지급목록[] = $nick;
        $지급합 += $인당;
    }

    $명수 = count($지급목록);
    $인당표시 = function_exists('newpoint표시') ? newpoint표시($인당) : number_format($인당);
    $합표시 = function_exists('newpoint표시') ? newpoint표시($지급합) : number_format($지급합);
    $pctLabel = rtrim(rtrim(sprintf('%.2F', (float)MAFIA_TOWN_WIN_REWARD_RATE * 100), '0'), '.');
    mafia_로그($round, "🎉 시민 승리! 생존 {$명수}명에게 각 {$인당표시}냥({$pctLabel}%) · 합 {$합표시}");
    if ($명수 > 0) {
        mafia_공창알림(
            "🎉 시민 승리 성공보수! #{$round}\n"
            . "🎁 생존 {$명수}명에게 각 본방냥 {$인당표시}냥({$pctLabel}%씩) 지급\n"
            . "· 합계 {$합표시}냥",
            'mafia_town_win'
        );
    }
}

function mafia_승리대기(int $round, string $winner, string $resultMsg): void {
    $winner_esc = addslashes($winner);
    $result_esc = addslashes(mb_substr($resultMsg, 0, 480));
    $ends = mafia_다음일일시작();
    $ends_esc = addslashes($ends);
    @db_query("UPDATE tb_mafia_game SET
        phase = 'waiting',
        phase_ends_at = '{$ends_esc}',
        winner = '{$winner_esc}',
        last_result = '{$result_esc}',
        day_no = 0,
        updated_at = NOW()
        WHERE idx = 1");
    $시작라벨 = mafia_일일시작라벨($ends);
    mafia_로그($round, $resultMsg . ' · 다음 라운드 ' . $시작라벨 . ' 시작 (주말 1회)');
    if ($winner === 'mafia') {
        mafia_마피아승리보상($round);
        mafia_미션누적지급($round);
    } elseif ($winner === 'town') {
        mafia_시민승리보상($round);
    }
    mafia_공창알림(
        "{$resultMsg}\n"
        . "⏳ 다음 라운드는 {$시작라벨}에 시작해요 (주말 1회)",
        'mafia_end'
    );
}

function mafia_밤결산(int $round): void {
    $players = mafia_플레이어목록($round);
    $byNick = [];
    foreach ($players as $p) {
        $byNick[$p['nick']] = $p;
    }

    // 마피아 살해 투표 (1인당 최대 3지목 · 다수 · 동률이면 랜덤)
    $killVotes = [];
    foreach ($players as $p) {
        if (!$p['alive'] || $p['role'] !== 'mafia') {
            continue;
        }
        $targets = $p['night_action_nicks'] ?? mafia_닉목록파싱($p['night_action_nick'] ?? '', (int)MAFIA_NIGHT_TARGETS);
        foreach ($targets as $t) {
            if ($t === '' || empty($byNick[$t]['alive']) || $t === $p['nick']) {
                continue;
            }
            if (!isset($killVotes[$t])) {
                $killVotes[$t] = 0;
            }
            $killVotes[$t]++;
        }
    }
    $killTarget = '';
    if ($killVotes !== []) {
        arsort($killVotes);
        $top = (int)reset($killVotes);
        $cands = array_keys(array_filter($killVotes, static function ($v) use ($top) {
            return (int)$v === $top;
        }));
        $killTarget = $cands[array_rand($cands)];
    }
    // 마피아 지목 없으면 살해 없음 (자동 랜덤 살해 없음)

    // 의사 보호
    $protect = '';
    $protector = '';
    foreach ($players as $p) {
        if ($p['alive'] && $p['role'] === 'doctor') {
            $docTargets = $p['night_action_nicks'] ?? mafia_닉목록파싱($p['night_action_nick'] ?? '', 1);
            if ($docTargets !== []) {
                $protect = $docTargets[0];
                $protector = (string)$p['nick'];
            }
            break;
        }
    }

    // 경찰 조사
    foreach ($players as $p) {
        if (!$p['alive'] || $p['role'] !== 'police') {
            continue;
        }
        $polTargets = $p['night_action_nicks'] ?? mafia_닉목록파싱($p['night_action_nick'] ?? '', 1);
        if ($polTargets === []) {
            continue;
        }
        $t = $polTargets[0];
        $isMafia = (!empty($byNick[$t]) && ($byNick[$t]['role'] ?? '') === 'mafia') ? 'mafia' : 'town';
        $esc = addslashes($p['nick']);
        $res_esc = addslashes($isMafia);
        @db_query("UPDATE tb_mafia_player SET investigated = 1, investigate_result = '{$res_esc}'
            WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");
        mafia_로그($round, '🔦 경찰이 누군가를 조사했어요');
    }

    if ($killTarget !== '') {
        if ($protect !== '' && $protect === $killTarget && $protector !== '') {
            $fee = mafia_의사보호수수료정산($round, $protector, $killTarget);
            if (!empty($fee['ok'])) {
                $통화 = (($fee['currency'] ?? '') === 'point') ? '게임냥' : '본방냥';
                $금액표시 = (string)($fee['amount_label'] ?? ($fee['amount'] ?? '0'));
                mafia_로그($round, "🛡️ {$killTarget} 보호 성공 · {$통화} {$금액표시} → 의사 {$protector}");
                mafia_공창알림(
                    "🛡️ 의사 보호 성공! (라운드 #{$round})\n"
                    . "{$killTarget} 살아남음 · 치료비 {$금액표시}{$통화} → 의사 {$protector}",
                    'mafia_doctor_save'
                );
            } else {
                mafia_로그($round, "🛡️ {$killTarget} 보호 실패(냥 부족) · 사망");
                mafia_공창알림(
                    "🛡️ 의사 보호 실패! (라운드 #{$round})\n"
                    . "{$killTarget} 치료비를 낼 냥이 없어 밤 살해로 사망했어요",
                    'mafia_doctor_fail'
                );
                mafia_제거($round, $killTarget, '밤 살해(보호 실패·냥 부족)');
            }
        } else {
            mafia_제거($round, $killTarget, '밤 살해');
        }
    } else {
        mafia_로그($round, '🌙 평화로운 밤이었어요');
    }

    // 공통미션 결산 (낮 전환·즉시 승패 모두) · 익명 알림 · 성공 시 누적
    mafia_미션결산($round);

    $players = mafia_플레이어목록($round);
    $winner = mafia_승패판정($players);
    if ($winner === 'town') {
        mafia_승리대기($round, 'town', '🎉 시민 승리! 마피아가 모두 잡혔어요');
        return;
    }
    if ($winner === 'mafia') {
        mafia_승리대기($round, 'mafia', '🩸 마피아 승리! 마을이 점령됐어요');
        return;
    }

    $ends = mafia_다음페이즈종료();
    $분 = mafia_페이즈남은분($ends);
    mafia_액션리셋($round);
    $gameNow = mafia_게임();
    $dayNo = (int)($gameNow['day_no'] ?? 0) + 1;
    @db_query("UPDATE tb_mafia_game SET phase = 'day', phase_ends_at = '{$ends}', day_no = {$dayNo}, updated_at = NOW() WHERE idx = 1");
    mafia_로그($round, "☀️ 낮 #{$dayNo} · 토론 후 투표하세요");
    $생존 = mafia_생존집계(mafia_플레이어목록($round));
    $강제안내 = '';
    $forceFrom = max(2, (int)MAFIA_DAY_FORCE_ELIM_FROM);
    if ($dayNo >= $forceFrom) {
        $강제안내 = "\n⚠️ 낮 {$forceFrom}회차부터 · 투표 0건이면 생존자 1명 강제탈락";
    } elseif ($dayNo === $forceFrom - 1) {
        $강제안내 = "\n⚠️ 다음 낮부터 투표 0건 시 강제탈락";
    }
    mafia_공창알림(
        "☀️ 마피아 낮 #{$dayNo} 이 됐어요! (라운드 #{$round})\n"
        . mafia_생존현황문구($생존) . "\n"
        . "다음 10분 정각까지 약 {$분}분 토론·투표(일반 2표 · 정치인·마피아 3표 · 1·2등 확정처형 · 3등 50%)"
        . $강제안내 . "\n"
        . "가방 → 마피아에서 투표하세요",
        'mafia_day'
    );
}

/**
 * 낮 투표 실시간 집계 (닉 => 표수, 득표순 · 동률은 닉 고정정렬)
 * @return array{votes: array<string,int>, cast: int, alive: int, tally: list<array{rank:int,nick:string,votes:int}>}
 */
function mafia_낮투표집계(array $players): array {
    $byNick = [];
    foreach ($players as $p) {
        $byNick[$p['nick']] = $p;
    }

    $votes = [];
    $cast = 0;
    $alive = 0;
    foreach ($players as $p) {
        if (!$p['alive']) {
            continue;
        }
        $alive++;
        $targets = $p['day_vote_nicks'] ?? mafia_닉목록파싱($p['day_vote_nick'] ?? '', 4);
        $valid = false;
        foreach ($targets as $t) {
            if ($t === '' || empty($byNick[$t]['alive'])) {
                continue;
            }
            if (!isset($votes[$t])) {
                $votes[$t] = 0;
            }
            $votes[$t]++;
            $valid = true;
        }
        if ($valid) {
            $cast++;
        }
    }

    uksort($votes, static function ($a, $b) use ($votes) {
        $cmp = ((int)($votes[$b] ?? 0)) <=> ((int)($votes[$a] ?? 0));
        return $cmp !== 0 ? $cmp : strcmp((string)$a, (string)$b);
    });

    $tally = [];
    $rank = 0;
    $prev = null;
    $idx = 0;
    foreach ($votes as $nick => $cnt) {
        $cnt = (int)$cnt;
        if ($cnt < 1) {
            continue;
        }
        $idx++;
        if ($prev === null || $cnt < $prev) {
            $rank = $idx;
            $prev = $cnt;
        }
        $tally[] = [
            'rank' => $rank,
            'nick' => (string)$nick,
            'votes' => $cnt,
        ];
    }

    return [
        'votes' => $votes,
        'cast' => $cast,
        'alive' => $alive,
        'tally' => $tally,
    ];
}

function mafia_낮결산(int $round): void {
    $players = mafia_플레이어목록($round);
    $byNick = [];
    foreach ($players as $p) {
        $byNick[$p['nick']] = $p;
    }

    $집계 = mafia_낮투표집계($players);
    $votes = $집계['votes'];
    $cast = (int)($집계['cast'] ?? 0);
    $game = mafia_게임();
    $dayNo = max(1, (int)($game['day_no'] ?? 1));
    $forceFrom = max(2, (int)MAFIA_DAY_FORCE_ELIM_FROM);

    $처형수 = (int)MAFIA_DAY_EXECUTE;
    $outs = mafia_투표상위선정($votes, $처형수);
    if ($outs === []) {
        // 낮 2회차부터 투표 0건 → 생존자 1명 랜덤 강제탈락
        if ($dayNo >= $forceFrom && $cast < 1) {
            $후보 = [];
            foreach ($players as $p) {
                if (!empty($p['alive'])) {
                    $후보[] = (string)$p['nick'];
                }
            }
            if ($후보 !== []) {
                shuffle($후보);
                $out = $후보[0];
                mafia_로그($round, "☀️ 낮 #{$dayNo} 투표 0건 · {$out} 강제탈락");
                mafia_제거($round, $out, "낮 #{$dayNo} 투표 0건 · 강제탈락");
                mafia_공창알림(
                    "⚠️ 낮 #{$dayNo} 투표가 1건도 없어 {$out} 님이 강제탈락했어요! (라운드 #{$round})",
                    'mafia_force_elim'
                );
                if (isset($byNick[$out])) {
                    $byNick[$out]['alive'] = false;
                }
            } else {
                mafia_로그($round, "☀️ 낮 #{$dayNo} 투표 없음 · 강제탈락 대상 없음");
            }
        } else {
            mafia_로그($round, "☀️ 낮 #{$dayNo} 투표 없음 · 아무도 처형되지 않았어요");
        }
    } else {
        $순위 = 0;
        $확정까지 = max(0, (int)MAFIA_DAY_EXECUTE_GUARANTEED);
        $확률 = (float)MAFIA_DAY_EXECUTE_CHANCE;
        if ($확률 < 0) {
            $확률 = 0.0;
        } elseif ($확률 > 1) {
            $확률 = 1.0;
        }
        foreach ($outs as $out) {
            $순위++;
            if (empty($byNick[$out]['alive'])) {
                continue;
            }
            $cnt = (int)($votes[$out] ?? 0);
            $확정 = ($순위 <= $확정까지);
            if (!$확정) {
                // 3등 이후: 반반(기본 50%) 확률
                $roll = mt_rand(1, 10000);
                $threshold = (int)round($확률 * 10000);
                if ($roll > $threshold) {
                    mafia_로그($round, "☀️ 낮 투표 {$순위}등 {$out} ({$cnt}표) · 확률 탈락 실패 · 생존");
                    mafia_공창알림(
                        "🎲 낮 투표 {$순위}등 {$out} 님은 확률 처형에 살아남았어요! ({$cnt}표 · 라운드 #{$round})",
                        'mafia_day_survive'
                    );
                    continue;
                }
            }
            $outRole = (string)($byNick[$out]['role'] ?? '');
            $적발투표자 = [];
            if ($outRole === 'mafia') {
                foreach ($players as $p) {
                    if (!$p['alive']) {
                        continue;
                    }
                    $dayVotes = $p['day_vote_nicks'] ?? mafia_닉목록파싱($p['day_vote_nick'] ?? '', 4);
                    if (!in_array($out, $dayVotes, true)) {
                        continue;
                    }
                    if (($p['role'] ?? '') === 'mafia') {
                        continue;
                    }
                    $적발투표자[] = $p['nick'];
                }
            }
            $사유 = $확정
                ? "낮 투표 {$순위}등 처형 ({$cnt}표)"
                : "낮 투표 {$순위}등 확률처형 ({$cnt}표)";
            mafia_제거($round, $out, $사유);
            if ($outRole === 'mafia') {
                mafia_마피아적발보상($round, $out, $적발투표자);
            }
            // 로컬 생존 반영 (같은 결산에서 중복 처형 방지)
            if (isset($byNick[$out])) {
                $byNick[$out]['alive'] = false;
            }
        }
    }

    $players = mafia_플레이어목록($round);
    $winner = mafia_승패판정($players);
    if ($winner === 'town') {
        mafia_승리대기($round, 'town', '🎉 시민 승리! 마피아가 모두 잡혔어요');
        return;
    }
    if ($winner === 'mafia') {
        mafia_승리대기($round, 'mafia', '🩸 마피아 승리! 마을이 점령됐어요');
        return;
    }

    $ends = mafia_다음페이즈종료();
    $분 = mafia_페이즈남은분($ends);
    mafia_액션리셋($round);
    @db_query("UPDATE tb_mafia_game SET phase = 'night', phase_ends_at = '{$ends}', updated_at = NOW() WHERE idx = 1");
    mafia_미션배정(false);
    mafia_로그($round, '🌙 밤이 됐어요 · 능력자 행동 시간 · 공통미션 갱신');
    $생존 = mafia_생존집계(mafia_플레이어목록($round));
    mafia_공창알림(
        "🌙 마피아 밤이 됐어요! (라운드 #{$round})\n"
        . mafia_생존현황문구($생존) . "\n"
        . "다음 10분 정각까지 약 {$분}분 능력 사용(마피아 " . (int)MAFIA_NIGHT_TARGETS . "지목)\n"
        . "마피아 공통미션이 새로 배정됐어요 (가방→마피아)\n"
        . "가방 → 마피아에서 행동하세요",
        'mafia_night'
    );
}

function mafia_틱(): array {
    mafia_테이블보장();
    // 진행 중 라운드에 정치인이 없으면 생존 시민 1명을 1회 부여 (배포 직후 호환)
    mafia_진행중_정치인1회부여();
    // 진행 중 종료시각을 10분 시계 격자에 맞춤
    $g0 = mafia_게임();
    $ph0 = trim((string)($g0['phase'] ?? ''));
    if (in_array($ph0, ['night', 'day'], true)) {
        $ends0 = trim((string)($g0['phase_ends_at'] ?? ''));
        $endsTs0 = $ends0 !== '' ? strtotime($ends0) : false;
        if ($endsTs0 !== false && $endsTs0 > time() && !mafia_페이즈종료_정렬됨($endsTs0)) {
            $snap = mafia_다음페이즈종료();
            $snap_esc = addslashes($snap);
            @db_query("UPDATE tb_mafia_game SET phase_ends_at = '{$snap_esc}', updated_at = NOW() WHERE idx = 1");
        }
    }

    $game = mafia_게임();
    $phase = trim((string)($game['phase'] ?? 'idle'));
    $endsAt = trim((string)($game['phase_ends_at'] ?? ''));
    $round = (int)($game['round_no'] ?? 0);

    if ($phase === 'idle' || $endsAt === '') {
        return ['ok' => true, 'advanced' => false, 'phase' => $phase];
    }
    $endsTs = strtotime($endsAt);
    if ($endsTs === false) {
        return ['ok' => true, 'advanced' => false, 'phase' => $phase];
    }

    // waiting 예약시각이 주말 10시가 아니면 다음 주말로 보정
    if ($phase === 'waiting' && $endsTs > time() && !mafia_일일시작시각인가($endsTs)) {
        $snap = mafia_다음일일시작();
        $snap_esc = addslashes($snap);
        @db_query("UPDATE tb_mafia_game SET phase_ends_at = '{$snap_esc}', updated_at = NOW() WHERE idx = 1");
        mafia_로그($round, '⏳ 재시작 시각 보정 · ' . mafia_일일시작라벨($snap) . ' (주말 1회)');
        return ['ok' => true, 'advanced' => false, 'phase' => 'waiting', 'msg' => 'daily_start_snap'];
    }

    if ($endsTs > time()) {
        return ['ok' => true, 'advanced' => false, 'phase' => $phase];
    }

    if ($phase === 'waiting') {
        // 평일·비주말 슬롯이면 시작하지 않고 다음 주말 10시로 미룸 (크론 지연 포함)
        if (!mafia_일일시작시각인가($endsTs) || !mafia_주말인가(time())) {
            $snap = mafia_다음일일시작();
            $snap_esc = addslashes($snap);
            @db_query("UPDATE tb_mafia_game SET phase_ends_at = '{$snap_esc}', updated_at = NOW() WHERE idx = 1");
            mafia_로그($round, '⏳ 주말 외 자동시작 보류 · ' . mafia_일일시작라벨($snap));
            return ['ok' => true, 'advanced' => false, 'phase' => 'waiting', 'msg' => 'weekend_only_snap'];
        }
        $r = mafia_라운드시작('주말 자동 시작');
        return ['ok' => !empty($r['ok']), 'advanced' => true, 'phase' => 'night', 'msg' => $r['msg'] ?? ''];
    }
    if ($phase === 'night' && $round > 0) {
        mafia_밤결산($round);
        $game2 = mafia_게임();
        return ['ok' => true, 'advanced' => true, 'phase' => $game2['phase'] ?? ''];
    }
    if ($phase === 'day' && $round > 0) {
        mafia_낮결산($round);
        $game2 = mafia_게임();
        return ['ok' => true, 'advanced' => true, 'phase' => $game2['phase'] ?? ''];
    }
    return ['ok' => true, 'advanced' => false, 'phase' => $phase];
}

function mafia_밤행동(string $nick, $target): array {
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? ''));
    if ($phase !== 'night') {
        return ['ok' => false, 'msg' => '밤 페이즈에만 행동할 수 있어요.'];
    }
    $me = mafia_플레이어찾기($round, $nick);
    if (!$me || !$me['alive']) {
        return ['ok' => false, 'msg' => '생존 플레이어만 행동할 수 있어요.'];
    }
    $role = $me['role'];
    if (!in_array($role, ['mafia', 'police', 'doctor'], true)) {
        return ['ok' => false, 'msg' => '특수 능력이 없는 역할이에요.'];
    }

    $max = ($role === 'mafia') ? (int)MAFIA_NIGHT_TARGETS : 1;
    $targets = mafia_닉목록파싱($target, $max);
    if ($targets === []) {
        return ['ok' => false, 'msg' => '대상을 선택해주세요.'];
    }
    if (count($targets) > $max) {
        $targets = array_slice($targets, 0, $max);
    }

    foreach ($targets as $tNick) {
        $t = mafia_플레이어찾기($round, $tNick);
        if (!$t || !$t['alive']) {
            return ['ok' => false, 'msg' => "생존 중인 대상을 선택해주세요. ({$tNick})"];
        }
        if ($role === 'police' && $tNick === $nick) {
            return ['ok' => false, 'msg' => '자기 자신은 조사할 수 없어요.'];
        }
    }

    $esc = addslashes($nick);
    $저장 = addslashes(mafia_닉목록직렬화($targets));
    @db_query("UPDATE tb_mafia_player SET night_action_nick = '{$저장}' WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");
    $label = mafia_역할라벨($role);
    $표시 = implode(', ', $targets);
    return ['ok' => true, 'msg' => "{$label} 행동 등록: {$표시}"];
}

function mafia_낮투표(string $nick, $target): array {
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? ''));
    if ($phase !== 'day') {
        return ['ok' => false, 'msg' => '낮 페이즈에만 투표할 수 있어요.'];
    }
    $me = mafia_플레이어찾기($round, $nick);
    if (!$me || !$me['alive']) {
        return ['ok' => false, 'msg' => '생존 플레이어만 투표할 수 있어요.'];
    }

    $maxVotes = mafia_낮투표한도($me['role'] ?? 'citizen');
    $targets = mafia_닉목록파싱($target, $maxVotes);
    if ($targets === []) {
        return ['ok' => false, 'msg' => '투표 대상을 선택해주세요.'];
    }
    if (count($targets) > $maxVotes) {
        $targets = array_slice($targets, 0, $maxVotes);
    }

    foreach ($targets as $tNick) {
        $t = mafia_플레이어찾기($round, $tNick);
        if (!$t || !$t['alive']) {
            return ['ok' => false, 'msg' => "생존 중인 대상을 선택해주세요. ({$tNick})"];
        }
        if ($tNick === $nick) {
            return ['ok' => false, 'msg' => '자기 자신은 투표할 수 없어요.'];
        }
    }

    $esc = addslashes($nick);
    $저장 = addslashes(mafia_닉목록직렬화($targets));
    @db_query("UPDATE tb_mafia_player SET day_vote_nick = '{$저장}' WHERE round_no = {$round} AND nick = '{$esc}' LIMIT 1");
    $표시 = implode(', ', $targets);
    return ['ok' => true, 'msg' => "투표 등록: {$표시}"];
}

function mafia_상태(string $viewerNick): array {
    mafia_틱();
    $game = mafia_게임();
    $round = (int)($game['round_no'] ?? 0);
    $phase = trim((string)($game['phase'] ?? 'idle'));
    // 진행 중 밤에 미션 단어가 없으면 즉시 배정 (배포 직후 호환)
    if ($phase === 'night' && $round > 0 && trim((string)($game['mission_word'] ?? '')) === '') {
        mafia_미션배정(false);
        $game = mafia_게임();
    }
    $endsAt = trim((string)($game['phase_ends_at'] ?? ''));
    // 대기/미진행: 다음 오전 6시까지 타이머 표시용
    if ($phase === 'idle' || ($phase === 'waiting' && $endsAt === '')) {
        $endsAt = mafia_다음일일시작();
    } elseif ($phase === 'waiting') {
        $ets = strtotime($endsAt);
        if ($ets === false || !mafia_일일시작시각인가($ets)) {
            $endsAt = mafia_다음일일시작();
        }
    }
    $endsTs = $endsAt !== '' ? strtotime($endsAt) : 0;
    $remain = ($endsTs > 0) ? max(0, $endsTs - time()) : 0;

    $phaseLabel = mafia_페이즈라벨($phase);
    if ($phase === 'waiting' || $phase === 'idle') {
        $phaseLabel .= ' · ' . mafia_일일시작라벨($endsAt);
    }

    $players = $round > 0 ? mafia_플레이어목록($round) : [];
    $counts = mafia_생존집계($players);
    $me = $round > 0 ? mafia_플레이어찾기($round, $viewerNick) : null;
    $admin = mafia_준호인가($viewerNick);

    $aliveList = [];
    foreach ($players as $p) {
        if (!$p['alive']) {
            continue;
        }
        $aliveList[] = $p['nick'];
    }

    $publicPlayers = [];
    $adminRoster = [];
    foreach ($players as $p) {
        $row = [
            'nick' => $p['nick'],
            'alive' => $p['alive'],
        ];
        // 죽은 사람만 역할 공개 (민호도 참여자 — 살아있는 타인 역할은 비공개)
        if (!$p['alive']) {
            $row['role'] = $p['role'];
            $row['role_label'] = mafia_역할라벨($p['role']);
        }
        $publicPlayers[] = $row;
        if ($admin) {
            $adminRoster[] = [
                'nick' => $p['nick'],
                'alive' => $p['alive'],
                'role' => $p['role'],
                'role_label' => mafia_역할라벨($p['role']),
            ];
        }
    }

    $my = null;
    if ($me) {
        $my = [
            'nick' => $me['nick'],
            'role' => $me['role'],
            'role_label' => mafia_역할라벨($me['role']),
            'alive' => $me['alive'],
            'night_action_nick' => $me['night_action_nick'],
            'night_action_nicks' => $me['night_action_nicks'] ?? mafia_닉목록파싱($me['night_action_nick'] ?? '', (int)MAFIA_NIGHT_TARGETS),
            'day_vote_nick' => $me['day_vote_nick'],
            'day_vote_nicks' => $me['day_vote_nicks'] ?? mafia_닉목록파싱($me['day_vote_nick'] ?? '', 4),
            'investigate_result' => $me['investigate_result'],
            'investigated' => $me['investigated'],
        ];
    }

    $missionWord = trim((string)($game['mission_word'] ?? ''));
    $missionPot = (int)($game['mission_pot'] ?? 0);
    $missionRerollUsed = (int)($game['mission_reroll_used'] ?? 0) === 1;
    $isMafia = ($my && ($my['role'] ?? '') === 'mafia');
    $maxFails = max(1, (int)MAFIA_MISSION_MAX_FAILS);
    $myFails = $isMafia ? (int)($me['mission_fails'] ?? 0) : 0;
    $remainChances = $isMafia ? max(0, $maxFails - $myFails) : 0;
    $canReroll = ($isMafia && !empty($my['alive']) && $phase === 'night' && $missionWord !== '' && !$missionRerollUsed);
    $mission = [
        'active' => ($phase === 'night' && $missionWord !== ''),
        'word' => $isMafia ? $missionWord : '',
        'hint' => $isMafia
            ? ($phase === 'night'
                ? ('공창에서 「' . $missionWord . '」 를 자연스럽게 말해 주세요 · 남은 기회 ' . $remainChances . '/' . $maxFails)
                : ('다음 밤에 새 미션 · 남은 기회 ' . $remainChances . '/' . $maxFails . ' · 실패 ' . $maxFails . '회 시 역할 교체'))
            : (($phase === 'night') ? '마피아에게 공창 공통미션이 진행 중이에요' : ''),
        'pot' => $missionPot,
        'pot_label' => function_exists('newpoint표시') ? newpoint표시($missionPot) : number_format($missionPot),
        'my_label' => '',
        'max_fails' => $maxFails,
        'fails' => $myFails,
        'remain_chances' => $remainChances,
        'reroll_used' => $missionRerollUsed,
        'can_reroll' => $canReroll,
        'reroll_hint' => $isMafia
            ? ($phase === 'night'
                ? ($missionRerollUsed ? '이번 밤 미션 변경권 사용됨' : '밤마다 미션 1회 변경 가능 (팀 공용)')
                : '밤이 되면 미션 1회 변경권 갱신')
            : '',
    ];
    if ($isMafia && $round > 0) {
        $labels = mafia_마피아익명라벨($round);
        $mission['my_label'] = $labels[$my['nick']] ?? '';
    }

    if ($my) {
        $my['mission_fails'] = (int)($me['mission_fails'] ?? 0);
    }

    $dayVoteTally = [];
    $dayVoteCast = 0;
    $dayVoteAlive = 0;
    if ($phase === 'day' && $players !== []) {
        $집계 = mafia_낮투표집계($players);
        $dayVoteTally = $집계['tally'];
        $dayVoteCast = (int)$집계['cast'];
        $dayVoteAlive = (int)$집계['alive'];
    }

    return [
        'ok' => true,
        'round' => $round,
        'phase' => $phase,
        'phase_label' => $phaseLabel,
        'phase_ends_at' => $endsAt,
        'remain_sec' => $remain,
        'day_no' => (int)($game['day_no'] ?? 0),
        'day_force_elim_from' => max(2, (int)MAFIA_DAY_FORCE_ELIM_FROM),
        'winner' => trim((string)($game['winner'] ?? '')),
        'last_result' => trim((string)($game['last_result'] ?? '')),
        'counts' => $counts,
        'alive_list' => $aliveList,
        'mafia_allies' => [], // 마피아끼리도 동료 비공개
        'players' => $publicPlayers,
        'me' => $my,
        'mission' => $mission,
        'is_admin' => $admin,
        'admin_roster' => $admin ? $adminRoster : [],
        'admin_only' => false,
        'phase_minutes' => mafia_페이즈남은분($endsAt !== '' ? $endsAt : null),
        'phase_align_min' => (int)round(MAFIA_PHASE_ALIGN_SEC / 60),
        'phase_align_hour' => false,
        'redis_minutes' => (int)(MAFIA_REDIS_SEC / 60),
        'daily_start_hour' => (int)MAFIA_DAILY_START_HOUR,
        'daily_once' => 1,
        'next_daily_start' => in_array($phase, ['waiting', 'idle'], true)
            ? $endsAt
            : mafia_다음일일시작(),
        'next_daily_label' => mafia_일일시작라벨(
            in_array($phase, ['waiting', 'idle'], true) ? $endsAt : mafia_다음일일시작()
        ),
        'max_mafia_targets' => (int)MAFIA_NIGHT_TARGETS,
        'max_day_votes' => ($my ? mafia_낮투표한도($my['role'] ?? 'citizen') : (int)MAFIA_DAY_VOTES_DEFAULT),
        'day_execute' => (int)MAFIA_DAY_EXECUTE,
        'day_execute_guaranteed' => (int)MAFIA_DAY_EXECUTE_GUARANTEED,
        'day_execute_chance' => (float)MAFIA_DAY_EXECUTE_CHANCE,
        'day_vote_tally' => $dayVoteTally,
        'day_vote_cast' => $dayVoteCast,
        'day_vote_alive' => $dayVoteAlive,
        'logs' => mafia_최근로그($round, 15),
        'history' => mafia_이전라운드기록(5, $game),
        'can_act_night' => ($phase === 'night' && $my && !empty($my['alive']) && in_array($my['role'], ['mafia', 'police', 'doctor'], true)),
        'can_vote_day' => ($phase === 'day' && $my && !empty($my['alive'])),
    ];
}
