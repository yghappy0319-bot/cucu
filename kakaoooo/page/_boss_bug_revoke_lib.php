<?php
/**
 * 보스 연속 출현 버그 — 처치 보상(본방냥·은총조각) 회수
 * 근거: tb_point_log (보스처치| / 보스MVP| / 보스은총조각| / 보스드랍-은총조각 / 보스처치풀|)
 */

if (!defined('BOSS_BUG_REVOKE_GAP_SEC')) {
    /** 처치 웨이브 구분 간격(초) — 이보다 멀면 다른 처치로 간주 */
    define('BOSS_BUG_REVOKE_GAP_SEC', 120);
}

function boss_bug_revoke_스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_boss_bug_revoke (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        log_idx INT UNSIGNED NOT NULL,
        nick VARCHAR(32) NOT NULL DEFAULT '',
        kind VARCHAR(24) NOT NULL DEFAULT '',
        amount DECIMAL(40,0) NOT NULL DEFAULT 0,
        amount_done DECIMAL(40,0) NOT NULL DEFAULT 0,
        status_text VARCHAR(255) NOT NULL DEFAULT '',
        admin_nick VARCHAR(32) NOT NULL DEFAULT '',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        UNIQUE KEY uq_log (log_idx),
        KEY ix_nick (nick),
        KEY ix_reg (regdate)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function boss_bug_revoke_금액문자($n): string {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($n);
    }
    $s = preg_replace('/[^\d\-]/', '', (string)$n);
    if ($s === '' || $s === '-') {
        return '0';
    }
    if (isset($s[0]) && $s[0] === '-') {
        $s = '-' . (ltrim(substr($s, 1), '0') ?: '0');
        return $s;
    }
    return ltrim($s, '0') ?: '0';
}

function boss_bug_revoke_표시($n, $unit = '본방냥'): string {
    $s = boss_bug_revoke_금액문자($n);
    if (function_exists('newpoint표시') && $unit === '본방냥') {
        return newpoint표시($s) . $unit;
    }
    if (function_exists('냥축약표시')) {
        return 냥축약표시($s, $unit);
    }
    return number_format((float)$s) . $unit;
}

/** @return 'nyang'|'shard'|'' */
function boss_bug_revoke_로그종류($status): string {
    $status = trim((string)$status);
    if ($status === '보스드랍-은총조각' || strpos($status, '보스은총조각|') === 0) {
        return 'shard';
    }
    if (strpos($status, '보스처치|') === 0
        || strpos($status, '보스MVP|') === 0
        || strpos($status, '보스처치풀|') === 0
    ) {
        return 'nyang';
    }
    return '';
}

/**
 * @return list<array{idx:int,nick:string,status:string,point:string,regdate:string,kind:string,done:bool}>
 */
function boss_bug_revoke_로그스캔($from, $to): array {
    boss_bug_revoke_스키마보장();
    $from = trim((string)$from);
    $to = trim((string)$to);
    if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $from)) {
        $from = date('Y-m-d H:i:s', time() - 6 * 3600);
    }
    if ($to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $to)) {
        $to = date('Y-m-d H:i:s');
    }
    // date-only → 하루 범위
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from .= ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to .= ' 23:59:59';
    }
    $from_esc = addslashes($from);
    $to_esc = addslashes($to);

    $rs = @db_query("
      SELECT l.idx, l.nick, l.status, CAST(l.point AS CHAR) AS point, l.regdate,
             IF(r.log_idx IS NULL, 0, 1) AS done
      FROM tb_point_log l
      LEFT JOIN tb_boss_bug_revoke r ON r.log_idx = l.idx
      WHERE l.regdate >= '{$from_esc}'
        AND l.regdate <= '{$to_esc}'
        AND (
          l.status LIKE '보스처치|%'
          OR l.status LIKE '보스MVP|%'
          OR l.status LIKE '보스은총조각|%'
          OR l.status LIKE '보스처치풀|%'
          OR l.status = '보스드랍-은총조각'
        )
      ORDER BY l.regdate ASC, l.idx ASC
      LIMIT 5000
    ");
    $out = [];
    if (!$rs) {
        return $out;
    }
    while ($row = db_fetch($rs)) {
        $kind = boss_bug_revoke_로그종류($row['status'] ?? '');
        if ($kind === '') {
            continue;
        }
        $out[] = [
            'idx' => (int)($row['idx'] ?? 0),
            'nick' => trim((string)($row['nick'] ?? '')),
            'status' => (string)($row['status'] ?? ''),
            'point' => boss_bug_revoke_금액문자($row['point'] ?? 0),
            'regdate' => (string)($row['regdate'] ?? ''),
            'kind' => $kind,
            'done' => !empty($row['done']),
        ];
    }
    return $out;
}

/**
 * 처치 웨이브: 보스처치 로그 시각 기준으로 클러스터
 * @param list<array> $logs
 * @return list<array{wave:int,start:string,end:string,kill_logs:int,idxs:list<int>}>
 */
function boss_bug_revoke_웨이브($logs): array {
    $killTimes = [];
    foreach ($logs as $log) {
        if (strpos((string)($log['status'] ?? ''), '보스처치|') !== 0) {
            continue;
        }
        $ts = strtotime((string)($log['regdate'] ?? ''));
        if ($ts !== false) {
            $killTimes[] = $ts;
        }
    }
    sort($killTimes);
    $wavesMeta = [];
    $gap = max(30, (int)BOSS_BUG_REVOKE_GAP_SEC);
    foreach ($killTimes as $ts) {
        if ($wavesMeta === []) {
            $wavesMeta[] = ['start' => $ts, 'end' => $ts];
            continue;
        }
        $last = count($wavesMeta) - 1;
        if ($ts - $wavesMeta[$last]['end'] <= $gap) {
            $wavesMeta[$last]['end'] = $ts;
        } else {
            $wavesMeta[] = ['start' => $ts, 'end' => $ts];
        }
    }

    // 웨이브가 없으면(처치 로그 없이 드랍만) 전체를 1웨이브로
    if ($wavesMeta === [] && $logs !== []) {
        $first = strtotime((string)$logs[0]['regdate']);
        $lastTs = strtotime((string)$logs[count($logs) - 1]['regdate']);
        if ($first === false) {
            $first = time();
        }
        if ($lastTs === false) {
            $lastTs = $first;
        }
        $wavesMeta[] = ['start' => $first, 'end' => $lastTs];
    }

    $waves = [];
    foreach ($wavesMeta as $i => $meta) {
        // 웨이브 범위: 시작-30초 ~ 다음웨이브 시작-1초 (또는 end+90초)
        $wStart = $meta['start'] - 30;
        if (isset($wavesMeta[$i + 1])) {
            $wEnd = $wavesMeta[$i + 1]['start'] - 1;
        } else {
            $wEnd = $meta['end'] + 90;
        }
        $idxs = [];
        $killLogs = 0;
        foreach ($logs as $log) {
            $ts = strtotime((string)($log['regdate'] ?? ''));
            if ($ts === false || $ts < $wStart || $ts > $wEnd) {
                continue;
            }
            $idxs[] = (int)$log['idx'];
            if (strpos((string)$log['status'], '보스처치|') === 0) {
                $killLogs++;
            }
        }
        $waves[] = [
            'wave' => $i + 1,
            'start' => date('Y-m-d H:i:s', $meta['start']),
            'end' => date('Y-m-d H:i:s', $meta['end']),
            'kill_logs' => $killLogs,
            'idxs' => $idxs,
        ];
    }
    return $waves;
}

/**
 * @return array{
 *   ok:bool,msg:string,from:string,to:string,keep_first:int,
 *   waves:list,stats:array,by_nick:list,revoke_logs:list,keep_logs:list
 * }
 */
function boss_bug_revoke_미리보기($from, $to, $keep_first = 1): array {
    $keep_first = max(0, (int)$keep_first);
    $logs = boss_bug_revoke_로그스캔($from, $to);
    $waves = boss_bug_revoke_웨이브($logs);

    $keepIdx = [];
    for ($i = 0; $i < $keep_first && $i < count($waves); $i++) {
        foreach ($waves[$i]['idxs'] as $id) {
            $keepIdx[(int)$id] = true;
        }
    }

    $revokeLogs = [];
    $keepLogs = [];
    $byNick = [];
    $nyangTotal = '0';
    $shardTotal = 0;
    $pending = 0;
    $done = 0;

    foreach ($logs as $log) {
        $id = (int)$log['idx'];
        if (!empty($keepIdx[$id])) {
            $keepLogs[] = $log;
            continue;
        }
        $revokeLogs[] = $log;
        if (!empty($log['done'])) {
            $done++;
            continue;
        }
        $pending++;
        $nick = $log['nick'];
        if ($nick === '') {
            continue;
        }
        if (!isset($byNick[$nick])) {
            $byNick[$nick] = [
                'nick' => $nick,
                'nyang' => '0',
                'shard' => 0,
                'logs' => 0,
                'have_nyang' => '0',
                'have_shard' => 0,
            ];
        }
        $byNick[$nick]['logs']++;
        $amt = boss_bug_revoke_금액문자($log['point']);
        if ($log['kind'] === 'shard') {
            $q = max(0, (int)$amt);
            $byNick[$nick]['shard'] += $q;
            $shardTotal += $q;
        } else {
            if (function_exists('bcadd')) {
                $byNick[$nick]['nyang'] = bcadd($byNick[$nick]['nyang'], $amt, 0);
                $nyangTotal = bcadd($nyangTotal, $amt, 0);
            } else {
                $byNick[$nick]['nyang'] = (string)((int)$byNick[$nick]['nyang'] + (int)$amt);
                $nyangTotal = (string)((int)$nyangTotal + (int)$amt);
            }
        }
    }

    // 보유량 조회
    if (!function_exists('mining_ore_shard_count')) {
        $ore = dirname(__DIR__) . '/api/game/mining_ore.inc.php';
        if (is_file($ore)) {
            require_once $ore;
        }
    }
    foreach ($byNick as $nick => &$row) {
        $esc = addslashes($nick);
        $m = @db_select("SELECT CAST(CAST(IFNULL(newpoint,0) AS DECIMAL(40,0)) AS CHAR) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        $row['have_nyang'] = boss_bug_revoke_금액문자($m['np'] ?? 0);
        $row['have_shard'] = function_exists('mining_ore_shard_count')
            ? (int)mining_ore_shard_count($nick)
            : 0;
        $row['nyang_fmt'] = boss_bug_revoke_표시($row['nyang']);
        $row['have_nyang_fmt'] = boss_bug_revoke_표시($row['have_nyang']);
    }
    unset($row);

    usort($byNick, static function ($a, $b) {
        $cmp = strcmp(
            str_pad(boss_bug_revoke_금액문자($b['nyang']), 40, '0', STR_PAD_LEFT),
            str_pad(boss_bug_revoke_금액문자($a['nyang']), 40, '0', STR_PAD_LEFT)
        );
        if ($cmp !== 0) {
            return $cmp;
        }
        return ($b['shard'] ?? 0) <=> ($a['shard'] ?? 0);
    });

    $waveCount = count($waves);
    $revokeWaves = max(0, $waveCount - $keep_first);

    return [
        'ok' => true,
        'msg' => "처치웨이브 {$waveCount}회 · 유지 {$keep_first} · 회수대상 웨이브 {$revokeWaves} · 미처리 로그 {$pending}건",
        'from' => $from,
        'to' => $to,
        'keep_first' => $keep_first,
        'waves' => $waves,
        'stats' => [
            'log_total' => count($logs),
            'keep_logs' => count($keepLogs),
            'revoke_logs' => count($revokeLogs),
            'pending' => $pending,
            'done' => $done,
            'wave_total' => $waveCount,
            'wave_revoke' => $revokeWaves,
            'nyang_total' => $nyangTotal,
            'nyang_total_fmt' => boss_bug_revoke_표시($nyangTotal),
            'shard_total' => $shardTotal,
            'nick_cnt' => count($byNick),
        ],
        'by_nick' => array_values($byNick),
        'revoke_logs' => $revokeLogs,
        'keep_logs' => $keepLogs,
    ];
}

/**
 * 본방냥 회수 (부족하면 전액)
 * @return string 실제 차감액
 */
function boss_bug_revoke_본방냥차감($nick, $amount): string {
    $nick = trim((string)$nick);
    $amount = boss_bug_revoke_금액문자($amount);
    if ($nick === '' || $amount === '0' || (isset($amount[0]) && $amount[0] === '-')) {
        return '0';
    }
    $esc = addslashes($nick);
    $row = @db_select("SELECT CAST(CAST(IFNULL(newpoint,0) AS DECIMAL(40,0)) AS CHAR) AS np FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    $have = boss_bug_revoke_금액문자($row['np'] ?? 0);
    if (function_exists('bccomp') && function_exists('bcsub')) {
        $cut = (bccomp($have, $amount, 0) >= 0) ? $amount : $have;
        if (bccomp($cut, '0', 0) <= 0) {
            return '0';
        }
        $cut_sql = $cut;
        @db_query("
          UPDATE tb_member
          SET newpoint = GREATEST(0, CAST(IFNULL(newpoint,0) AS DECIMAL(40,0)) - {$cut_sql})
          WHERE name = '{$esc}'
          LIMIT 1
        ");
        return $cut;
    }
    $cut = min((int)$have, (int)$amount);
    if ($cut < 1) {
        return '0';
    }
    @db_query("UPDATE tb_member SET newpoint = GREATEST(0, newpoint - {$cut}) WHERE name = '{$esc}' LIMIT 1");
    return (string)$cut;
}

/**
 * 은총조각 회수 — 조각 부족 시 은총 1개=10조각으로 환산 차감
 * @return array{shard:int,eunchong:int}
 */
function boss_bug_revoke_조각차감($nick, int $qty): array {
    $nick = trim((string)$nick);
    $qty = max(0, $qty);
    $out = ['shard' => 0, 'eunchong' => 0];
    if ($nick === '' || $qty < 1) {
        return $out;
    }
    if (!function_exists('mining_ore_shard_count')) {
        $ore = dirname(__DIR__) . '/api/game/mining_ore.inc.php';
        if (is_file($ore)) {
            require_once $ore;
        }
    }
    if (!function_exists('mining_ore_ensure_schema')) {
        return $out;
    }
    mining_ore_ensure_schema();
    if (function_exists('mining_data_ensure_row')) {
        mining_data_ensure_row($nick);
    }

    $need = max(1, (int)(defined('MINING_ORE_EUNCHONG_SHARDS') ? MINING_ORE_EUNCHONG_SHARDS : 10));
    $remain = $qty;
    $have = function_exists('mining_ore_shard_count') ? (int)mining_ore_shard_count($nick) : 0;
    $takeShard = min($have, $remain);
    if ($takeShard > 0 && function_exists('mining_ore_spend_shards')) {
        $r = mining_ore_spend_shards($nick, $takeShard);
        if (!empty($r['ok'])) {
            $out['shard'] += $takeShard;
            $remain -= $takeShard;
        }
    }

    if ($remain > 0) {
        // 은총으로 부족분 충당 (조각 need개 = 은총 1)
        $needEunchong = (int)ceil($remain / $need);
        if ($needEunchong > 0) {
            if (!function_exists('bag_은총_차감')) {
                $bag = dirname(__DIR__) . '/api/item_bag_enhance.inc.php';
                if (is_file($bag)) {
                    require_once $bag;
                }
            }
            $esc = addslashes($nick);
            $took = 0;

            // 보유 은총 확인 후 가능한 만큼만
            $haveE = 0;
            if (function_exists('bag_은총_수량')) {
                $haveE = max(0, (int)bag_은총_수량($nick));
            } else {
                $m = @db_select("SELECT IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE name = '{$esc}' LIMIT 1");
                $haveE = max(0, (int)($m['cnt'] ?? 0));
            }
            $want = min($haveE, $needEunchong);
            if ($want > 0 && function_exists('bag_은총_차감')) {
                $차감 = bag_은총_차감($nick, $want);
                if (!empty($차감['ok'])) {
                    $took = $want;
                }
            }
            if ($took < 1 && $want > 0) {
                @db_query("
                  UPDATE tb_member
                  SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - {$want}, 0)
                  WHERE name = '{$esc}' AND IFNULL(은총개수, 0) >= {$want}
                  LIMIT 1
                ");
                global $conn;
                if ($conn instanceof mysqli && (int)mysqli_affected_rows($conn) > 0) {
                    $took = $want;
                }
            }
            if ($took > 0) {
                $out['eunchong'] += $took;
            }
        }
    }

    return $out;
}

/**
 * @return array{ok:bool,msg:string,stats?:array,details?:list}
 */
function boss_bug_revoke_실행($from, $to, $keep_first, $admin_nick): array {
    boss_bug_revoke_스키마보장();
    $admin_nick = trim((string)$admin_nick);
    if ($admin_nick === '') {
        return ['ok' => false, 'msg' => '관리자 닉이 없습니다.'];
    }
    if (!function_exists('지급로그')) {
        $fn = dirname(__DIR__) . '/api/function.php';
        if (is_file($fn)) {
            require_once $fn;
        }
    }

    $preview = boss_bug_revoke_미리보기($from, $to, $keep_first);
    $logs = $preview['revoke_logs'] ?? [];
    $nyangDone = '0';
    $shardDone = 0;
    $eunchongDone = 0;
    $logDone = 0;
    $logSkip = 0;
    $details = [];

    // 닉별 합산 후 1회 차감 (로그는 건별 마킹)
    $agg = [];
    foreach ($logs as $log) {
        if (!empty($log['done'])) {
            $logSkip++;
            continue;
        }
        $nick = $log['nick'];
        if ($nick === '' || (int)$log['idx'] < 1) {
            continue;
        }
        if (!isset($agg[$nick])) {
            $agg[$nick] = ['nyang' => '0', 'shard' => 0, 'log_idxs' => [], 'statuses' => []];
        }
        $amt = boss_bug_revoke_금액문자($log['point']);
        if ($log['kind'] === 'shard') {
            $agg[$nick]['shard'] += max(0, (int)$amt);
        } else {
            if (function_exists('bcadd')) {
                $agg[$nick]['nyang'] = bcadd($agg[$nick]['nyang'], $amt, 0);
            } else {
                $agg[$nick]['nyang'] = (string)((int)$agg[$nick]['nyang'] + (int)$amt);
            }
        }
        $agg[$nick]['log_idxs'][] = (int)$log['idx'];
        $agg[$nick]['statuses'][] = (string)$log['status'];
    }

    foreach ($agg as $nick => $row) {
        $cutNyang = boss_bug_revoke_본방냥차감($nick, $row['nyang']);
        $cutShard = boss_bug_revoke_조각차감($nick, (int)$row['shard']);
        if (function_exists('bcadd')) {
            $nyangDone = bcadd($nyangDone, $cutNyang, 0);
        } else {
            $nyangDone = (string)((int)$nyangDone + (int)$cutNyang);
        }
        $shardDone += (int)($cutShard['shard'] ?? 0);
        $eunchongDone += (int)($cutShard['eunchong'] ?? 0);

        if (function_exists('지급로그')) {
            if ($cutNyang !== '0' && $cutNyang !== '') {
                지급로그('보스버그회수|본방냥', $nick, $admin_nick, 0, '-' . $cutNyang);
            }
            $shardPart = (int)($cutShard['shard'] ?? 0);
            $euPart = (int)($cutShard['eunchong'] ?? 0);
            if ($shardPart > 0) {
                지급로그('보스버그회수|은총조각', $nick, $admin_nick, 0, '-' . $shardPart);
            }
            if ($euPart > 0) {
                지급로그('보스버그회수|은총', $nick, $admin_nick, 0, '-' . $euPart);
            }
        }

        $admin_esc = addslashes($admin_nick);
        $nick_esc = addslashes($nick);
        foreach ($row['log_idxs'] as $i => $logIdx) {
            $st = addslashes((string)($row['statuses'][$i] ?? ''));
            $kind = (strpos($st, '보스은총조각') === 0 || $st === '보스드랍-은총조각') ? 'shard' : 'nyang';
            @db_query("
              INSERT INTO tb_boss_bug_revoke
                (log_idx, nick, kind, amount, amount_done, status_text, admin_nick, regdate)
              VALUES
                ({$logIdx}, '{$nick_esc}', '{$kind}', 0, 0, '{$st}', '{$admin_esc}', NOW())
              ON DUPLICATE KEY UPDATE admin_nick = VALUES(admin_nick)
            ");
            $logDone++;
        }

        $details[] = [
            'nick' => $nick,
            'nyang_target' => $row['nyang'],
            'nyang_done' => $cutNyang,
            'shard_target' => (int)$row['shard'],
            'shard_done' => (int)($cutShard['shard'] ?? 0),
            'eunchong_done' => (int)($cutShard['eunchong'] ?? 0),
        ];
    }

    $msg = '회수 완료 · 본방냥 ' . boss_bug_revoke_표시($nyangDone)
        . ' · 은총조각 ' . number_format($shardDone) . '개';
    if ($eunchongDone > 0) {
        $msg .= ' · 은총 ' . number_format($eunchongDone) . '개(조각 부족분 환산)';
    }
    $msg .= ' · 로그마킹 ' . number_format($logDone) . '건';
    if ($logSkip > 0) {
        $msg .= ' · 이미처리 ' . number_format($logSkip) . '건 스킵';
    }

    return [
        'ok' => true,
        'msg' => $msg,
        'stats' => [
            'nyang_done' => $nyangDone,
            'nyang_done_fmt' => boss_bug_revoke_표시($nyangDone),
            'shard_done' => $shardDone,
            'eunchong_done' => $eunchongDone,
            'log_done' => $logDone,
            'log_skip' => $logSkip,
            'nick_cnt' => count($details),
        ],
        'details' => $details,
    ];
}
