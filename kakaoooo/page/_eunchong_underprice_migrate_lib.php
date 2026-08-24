<?php
/**
 * 은총2 저가 구매(버그) 회수 · 게임냥 환불
 * - 채팅 구매 메시지 붙여넣기 파싱
 * - 또는 tb_eunchong_buy_log 저가 건 스캔
 */

if (!defined('EUNCHONG_UNDERPRICE_FAIR_RATIO')) {
    /** 정상 단가 대비 이 비율 미만이면 저가로 간주 (기본 55%) */
    define('EUNCHONG_UNDERPRICE_FAIR_RATIO', 0.55);
}

if (!defined('EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT')) {
    /** 오버플로 버그 당시 은총2 1개당 결제액(표시용) */
    define('EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT', '931경');
}

function eunchong_underprice_스키마보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_eunchong_buy_log (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nick VARCHAR(32) NOT NULL,
        item VARCHAR(16) NOT NULL DEFAULT '은총2',
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        unit_price DECIMAL(65,0) NOT NULL DEFAULT 0,
        paid_total DECIMAL(65,0) NOT NULL DEFAULT 0,
        surcharge DECIMAL(65,0) NOT NULL DEFAULT 0,
        channel VARCHAR(16) NOT NULL DEFAULT 'chat',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        KEY ix_nick_reg (nick, regdate),
        KEY ix_paid (paid_total)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_eunchong_underprice_fix (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nick VARCHAR(32) NOT NULL,
        qty INT UNSIGNED NOT NULL DEFAULT 1,
        paid_total DECIMAL(65,0) NOT NULL DEFAULT 0,
        reclaimed INT UNSIGNED NOT NULL DEFAULT 0,
        refunded DECIMAL(65,0) NOT NULL DEFAULT 0,
        source_key VARCHAR(80) NOT NULL DEFAULT '',
        source_text VARCHAR(255) NOT NULL DEFAULT '',
        admin_nick VARCHAR(32) NOT NULL DEFAULT '',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        UNIQUE KEY uq_source (source_key),
        KEY ix_nick (nick)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function eunchong_underprice_db_error(): string {
    global $conn;
    if ($conn instanceof mysqli) {
        return trim((string)mysqli_error($conn));
    }
    return '';
}

function eunchong_underprice_금액문자($n): string {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($n);
    }
    return ltrim(preg_replace('/\D/', '', (string)$n), '0') ?: '0';
}

function eunchong_underprice_표시($n): string {
    if (function_exists('구매가_축약표시')) {
        return 구매가_축약표시($n, '겜냥');
    }
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '겜냥');
    }
    return eunchong_underprice_금액문자($n) . '겜냥';
}

function eunchong_underprice_정상단가(): string {
    if (!function_exists('info3_은총2_구매단가')) {
        $path = dirname(__DIR__) . '/api/eunchong_shop_buy.inc.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
    if (function_exists('info3_은총2_구매단가')) {
        return eunchong_underprice_금액문자(info3_은총2_구매단가());
    }
    return '0';
}

/** 저가 여부: 결제총액 < 정상단가 × 수량 × ratio */
function eunchong_underprice_저가인가(string $paidTotal, int $qty, string $fairUnit = ''): bool {
    $paidTotal = eunchong_underprice_금액문자($paidTotal);
    $qty = max(1, $qty);
    if ($paidTotal === '0') {
        return false;
    }
    if ($fairUnit === '' || $fairUnit === '0') {
        $fairUnit = eunchong_underprice_정상단가();
    }
    $fairUnit = eunchong_underprice_금액문자($fairUnit);
    if ($fairUnit === '0') {
        // 정상단가 실패 시 INT_MAX 밴드(약 400~1500경/개)
        $min = '4000000000000000000';
        $max = '15000000000000000000';
        $per = function_exists('bcdiv') ? bcdiv($paidTotal, (string)$qty, 0) : $paidTotal;
        if (function_exists('bccomp')) {
            return bccomp($per, $min, 0) >= 0 && bccomp($per, $max, 0) <= 0;
        }
        return false;
    }
    $ratio = (float)EUNCHONG_UNDERPRICE_FAIR_RATIO;
    if ($ratio <= 0 || $ratio >= 1) {
        $ratio = 0.55;
    }
    if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bccomp')) {
        $fairTotal = bcmul($fairUnit, (string)$qty, 0);
        $threshold = bcdiv(bcmul($fairTotal, (string)(int)round($ratio * 1000), 0), '1000', 0);
        return bccomp($paidTotal, $threshold, 0) < 0;
    }
    return ((float)$paidTotal) < ((float)$fairUnit * $qty * $ratio);
}

/**
 * 채팅 구매 문구 파싱 (여러 건)
 * @return list<array{nick:string,qty:int,paid:string,raw:string,key:string}>
 */
function eunchong_underprice_채팅파싱(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // 카카오/복사 시 화살표·특수공백 정규화
    $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
    $out = [];
    // ► ▶ ▸ > ［닉］ / [닉] 은총2 (N개) 구매 AMOUNT
    if (!preg_match_all(
        '/(?:►|▶|▸|➢|➤|>)?\s*[\[\x{FF3B}]\s*([^\]\x{FF3D}]+?)\s*[\]\x{FF3D}]\s*은총\s*2(?:\s*(\d+)\s*개)?\s*구매\s*([^\n<]+)/u',
        $text,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }
    $seq = 0;
    foreach ($matches as $m) {
        $seq++;
        $nick = trim((string)$m[1]);
        $qty = isset($m[2]) && $m[2] !== '' ? max(1, (int)$m[2]) : 1;
        $amtRaw = trim((string)$m[3]);
        // 추가금 줄이 붙었으면 잘라냄
        if (preg_match('/^(.+?)(?:\(|（|10개|추가금)/u', $amtRaw, $am)) {
            $amtRaw = trim($am[1]);
        }
        $amtRaw = preg_replace('/\s+/u', '', $amtRaw);
        $amtRaw = preg_replace('/(겜냥|게임냥|냥)$/u', '', $amtRaw);
        $amtRaw = str_replace([',', '，'], '', $amtRaw);
        $paid = function_exists('냥_금액_파싱_문자열')
            ? 냥_금액_파싱_문자열($amtRaw)
            : (ltrim(preg_replace('/\D/', '', $amtRaw), '0') ?: '0');
        if ($nick === '' || $paid === '0') {
            continue;
        }
        $raw = trim((string)$m[0]);
        // 동일 문구 여러 건이 겹치지 않게 순번 포함
        $key = substr(hash('sha256', $seq . '|' . $nick . '|' . $qty . '|' . $paid . '|' . $raw), 0, 48);
        $out[] = [
            'nick' => $nick,
            'qty' => $qty,
            'paid' => $paid,
            'raw' => $raw,
            'key' => $key,
        ];
    }
    return $out;
}

/**
 * @return list<array{nick:string,qty:int,paid:string,raw:string,key:string,log_idx:int}>
 */
function eunchong_underprice_로그스캔(?string $from = null, ?string $to = null): array {
    eunchong_underprice_스키마보장();
    $fair = eunchong_underprice_정상단가();
    $where = "item IN ('은총2', '은총')";
    if ($from !== null && $from !== '') {
        $from_esc = addslashes($from . ' 00:00:00');
        $where .= " AND regdate >= '{$from_esc}'";
    }
    if ($to !== null && $to !== '') {
        $to_esc = addslashes($to . ' 23:59:59');
        $where .= " AND regdate <= '{$to_esc}'";
    }
    $rs = @db_query("
      SELECT idx, nick, qty,
             CAST(paid_total AS CHAR) AS paid_total,
             regdate
      FROM tb_eunchong_buy_log
      WHERE {$where}
      ORDER BY idx ASC
    ");
    $out = [];
    if (!$rs) {
        return [];
    }
    while ($row = db_fetch($rs)) {
        $nick = trim((string)($row['nick'] ?? ''));
        $qty = max(1, (int)($row['qty'] ?? 1));
        $paid = eunchong_underprice_금액문자((string)($row['paid_total'] ?? '0'));
        if ($nick === '' || $paid === '0') {
            continue;
        }
        if (!eunchong_underprice_저가인가($paid, $qty, $fair)) {
            continue;
        }
        $idx = (int)($row['idx'] ?? 0);
        $raw = 'log#' . $idx . ' ' . (string)($row['regdate'] ?? '');
        $key = 'log:' . $idx;
        $out[] = [
            'nick' => $nick,
            'qty' => $qty,
            'paid' => $paid,
            'raw' => $raw,
            'key' => $key,
            'log_idx' => $idx,
        ];
    }
    return $out;
}

function eunchong_underprice_이미처리(string $sourceKey): bool {
    eunchong_underprice_스키마보장();
    $esc = addslashes($sourceKey);
    $row = @db_select("SELECT idx FROM tb_eunchong_underprice_fix WHERE source_key = '{$esc}' LIMIT 1");
    return !empty($row['idx']);
}

/** source_key / 구키 / 동일 문구 기록 여부 */
function eunchong_underprice_이미처리_건(string $nick, int $qty, string $paid, string $raw, string $key): bool {
    if ($key !== '' && eunchong_underprice_이미처리($key)) {
        return true;
    }
    // 예전 키(순번 없음)로 이미 처리된 경우
    $legacy = substr(hash('sha256', $nick . '|' . $qty . '|' . $paid . '|' . $raw), 0, 40);
    if ($legacy !== '' && eunchong_underprice_이미처리($legacy)) {
        return true;
    }
    $nick_esc = addslashes($nick);
    $paid_sql = preg_replace('/\D/', '', eunchong_underprice_금액문자($paid)) ?: '0';
    $raw_esc = addslashes(mb_substr($raw, 0, 240, 'UTF-8'));
    $qty = max(1, $qty);
    $row = @db_select("
      SELECT idx FROM tb_eunchong_underprice_fix
      WHERE nick = '{$nick_esc}'
        AND qty = {$qty}
        AND CAST(paid_total AS CHAR) = '{$paid_sql}'
        AND source_text = '{$raw_esc}'
      LIMIT 1
    ");
    return !empty($row['idx']);
}

/**
 * @param list<array{nick:string,qty:int,paid:string,raw?:string,key:string}> $rows
 */
function eunchong_underprice_미리보기(array $rows): array {
    if (!function_exists('bag_은총_수량') && is_file(dirname(__DIR__) . '/api/item_bag_enhance.inc.php')) {
        require_once dirname(__DIR__) . '/api/item_bag_enhance.inc.php';
    }
    $fair = eunchong_underprice_정상단가();
    $list = [];
    $totalPaid = '0';
    $totalQty = 0;
    $totalReclaim = 0;
    $skipDone = 0;
    $skipFair = 0;
    $parsed = count($rows);

    foreach ($rows as $r) {
        $nick = trim((string)($r['nick'] ?? ''));
        $qty = max(1, (int)($r['qty'] ?? 1));
        $paid = eunchong_underprice_금액문자((string)($r['paid'] ?? '0'));
        $key = (string)($r['key'] ?? '');
        $raw = (string)($r['raw'] ?? '');
        if ($nick === '' || $paid === '0' || $key === '') {
            continue;
        }
        if (!eunchong_underprice_저가인가($paid, $qty, $fair)) {
            $skipFair++;
            continue;
        }
        $done = eunchong_underprice_이미처리_건($nick, $qty, $paid, $raw, $key);
        if ($done) {
            $skipDone++;
        }
        $held = function_exists('bag_은총_수량') ? bag_은총_수량($nick) : 0;
        $reclaim = $done ? 0 : min($qty, max(0, $held));
        $list[] = [
            'nick' => $nick,
            'qty' => $qty,
            'paid' => $paid,
            'paid_fmt' => eunchong_underprice_표시($paid),
            'held' => $held,
            'reclaim' => $reclaim,
            'key' => $key,
            'raw' => $raw,
            'done' => $done,
            'short' => !$done && $reclaim < $qty,
        ];
        if (!$done) {
            $totalQty += $qty;
            $totalReclaim += $reclaim;
            if (function_exists('bcadd')) {
                $totalPaid = bcadd($totalPaid, $paid, 0);
            } else {
                $totalPaid = (string)((int)$totalPaid + (int)$paid);
            }
        }
    }

    $pending = 0;
    foreach ($list as $x) {
        if (empty($x['done'])) {
            $pending++;
        }
    }

    return [
        'ok' => true,
        'rows' => $list,
        'fair' => $fair,
        'fair_fmt' => eunchong_underprice_표시($fair),
        'stats' => [
            'parsed' => $parsed,
            'count' => count($list),
            'pending' => $pending,
            'qty' => $totalQty,
            'reclaim' => $totalReclaim,
            'refund' => $totalPaid,
            'refund_fmt' => eunchong_underprice_표시($totalPaid),
            'skip_fair' => $skipFair,
            'skip_done' => $skipDone,
        ],
    ];
}

/**
 * @param list<string>|null $keys null/빈배열이면 pending 전부
 */
function eunchong_underprice_실행(array $rows, string $adminNick, ?array $keys = null): array {
    eunchong_underprice_스키마보장();
    if (!function_exists('bag_은총_차감') && is_file(dirname(__DIR__) . '/api/item_bag_enhance.inc.php')) {
        require_once dirname(__DIR__) . '/api/item_bag_enhance.inc.php';
    }
    if (!function_exists('bag_은총_차감')) {
        return ['ok' => false, 'msg' => '은총 차감 기능을 불러올 수 없어요.', 'fixed' => 0, 'reclaimed' => 0, 'refunded' => '0'];
    }

    // 체크 없음 = 전체 처리
    if ($keys !== null && $keys === []) {
        $keys = null;
    }

    $preview = eunchong_underprice_미리보기($rows);
    $fixed = 0;
    $reclaimed = 0;
    $refunded = '0';
    $errors = [];
    $targets = 0;

    foreach ($preview['rows'] as $row) {
        if (!empty($row['done'])) {
            continue;
        }
        if ($keys !== null && !in_array($row['key'], $keys, true)) {
            continue;
        }
        $targets++;
        $nick = $row['nick'];
        $qty = (int)$row['qty'];
        $paid = eunchong_underprice_금액문자((string)$row['paid']);
        $key = (string)$row['key'];
        $reclaim = (int)$row['reclaim'];
        $nick_esc = addslashes($nick);
        $key_esc = addslashes($key);
        $raw_esc = addslashes(mb_substr((string)$row['raw'], 0, 240, 'UTF-8'));
        $admin_esc = addslashes($adminNick);
        $paid_sql = preg_replace('/\D/', '', $paid) ?: '0';

        $mem = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        if (empty($mem['idx'])) {
            $errors[] = "{$nick}: 회원 없음";
            continue;
        }

        $ins = @db_query("
          INSERT INTO tb_eunchong_underprice_fix
            (nick, qty, paid_total, reclaimed, refunded, source_key, source_text, admin_nick, regdate)
          VALUES
            ('{$nick_esc}', {$qty}, '{$paid_sql}', 0, 0, '{$key_esc}', '{$raw_esc}', '{$admin_esc}', NOW())
        ");
        if (!$ins) {
            $err = eunchong_underprice_db_error();
            $errors[] = "{$nick}: 기록 실패" . ($err !== '' ? " ({$err})" : ' (이미 처리?)');
            continue;
        }
        $fixIdx = 0;
        global $conn;
        if ($conn instanceof mysqli) {
            $fixIdx = (int)mysqli_insert_id($conn);
        }

        $got = 0;
        if ($reclaim > 0) {
            $sub = bag_은총_차감($nick, $reclaim);
            if (!empty($sub['ok'])) {
                $got = $reclaim;
            } else {
                $heldNow = function_exists('bag_은총_수량') ? bag_은총_수량($nick) : 0;
                $try = min($reclaim, max(0, $heldNow));
                if ($try > 0) {
                    $sub2 = bag_은총_차감($nick, $try);
                    if (!empty($sub2['ok'])) {
                        $got = $try;
                    }
                }
            }
        }

        // 대금액 안전 가산 (info2 입금과 동일하게 DECIMAL(65,0))
        $okPay = db_query("
          UPDATE tb_member
          SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) + CAST('{$paid_sql}' AS DECIMAL(65,0))
          WHERE name = '{$nick_esc}'
          LIMIT 1
        ");
        $err = eunchong_underprice_db_error();
        $affected = 0;
        if ($conn instanceof mysqli) {
            $affected = (int)mysqli_affected_rows($conn);
        }
        if ($okPay === false || $err !== '' || $affected < 1) {
            if ($got > 0 && function_exists('bag_은총_가산')) {
                bag_은총_가산($nick, $got);
            }
            if ($fixIdx > 0) {
                @db_query("DELETE FROM tb_eunchong_underprice_fix WHERE idx = {$fixIdx} LIMIT 1");
            }
            $errors[] = "{$nick}: 냥 환불 실패" . ($err !== '' ? " ({$err})" : ($affected < 1 ? ' (affected=0)' : ''));
            continue;
        }

        @db_query("
          UPDATE tb_eunchong_underprice_fix
          SET reclaimed = {$got}, refunded = '{$paid_sql}'
          WHERE idx = {$fixIdx}
          LIMIT 1
        ");

        if (function_exists('지급로그')) {
            지급로그('은총2저가회수환불', $nick, $adminNick, 0, $paid_sql);
        }

        $fixed++;
        $reclaimed += $got;
        if (function_exists('bcadd')) {
            $refunded = bcadd($refunded, $paid, 0);
        } else {
            $refunded = (string)((int)$refunded + (int)$paid);
        }
    }

    if ($targets < 1) {
        return [
            'ok' => false,
            'msg' => '처리할 미처리 건이 없습니다. (이미 처리됐거나 미리보기 대상이 비어 있어요)',
            'fixed' => 0,
            'reclaimed' => 0,
            'refunded' => '0',
        ];
    }

    $msg = "대상 {$targets}건 중 처리 {$fixed}건 · 은총 회수 {$reclaimed}개 · 환불 " . eunchong_underprice_표시($refunded);
    if ($errors !== []) {
        $msg .= "\n⚠ " . implode("\n⚠ ", array_slice($errors, 0, 15));
        if (count($errors) > 15) {
            $msg .= "\n… 외 " . (count($errors) - 15) . '건';
        }
    }
    return [
        'ok' => $fixed > 0,
        'msg' => $msg,
        'fixed' => $fixed,
        'reclaimed' => $reclaimed,
        'refunded' => $refunded,
    ];
}

/** 버그 단가 1개분 (기본 931경) */
function eunchong_underprice_버그단가(string $unitText = ''): string {
    if ($unitText === '') {
        $unitText = EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT;
    }
    $unitText = trim(preg_replace('/(겜냥|게임냥|냥)$/u', '', $unitText));
    if (function_exists('냥_금액_파싱_문자열')) {
        $v = 냥_금액_파싱_문자열($unitText);
        if ($v !== '0') {
            return $v;
        }
    }
    return '9310000000000000000'; // 931경 fallback
}

/**
 * 수동 입력 파싱: "하리 2" / "하리 2개" (여러 줄 OK)
 * @return list<array{nick:string,qty:int}>
 */
function eunchong_underprice_수동파싱(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $out = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }
        // "하리 2" / "하리 2개"
        if (!preg_match('/^(.+?)\s+(\d+)\s*개?\s*$/u', $line, $m)) {
            continue;
        }
        $nick = trim((string)$m[1]);
        $qty = max(1, (int)$m[2]);
        if ($nick === '' || $qty < 1) {
            continue;
        }
        $out[] = ['nick' => $nick, 'qty' => $qty];
    }
    return $out;
}

/**
 * 수동: 은총 qty개 회수 + (qty × 버그단가) 환불
 * @return array{ok:bool,msg:string,reclaimed:int,refunded:string}
 */
function eunchong_underprice_수동실행(string $nick, int $qty, string $adminNick, string $unitText = ''): array {
    eunchong_underprice_스키마보장();
    if (!function_exists('bag_은총_차감') && is_file(dirname(__DIR__) . '/api/item_bag_enhance.inc.php')) {
        require_once dirname(__DIR__) . '/api/item_bag_enhance.inc.php';
    }
    if (!function_exists('bag_은총_차감')) {
        return ['ok' => false, 'msg' => '은총 차감 기능을 불러올 수 없어요.', 'reclaimed' => 0, 'refunded' => '0'];
    }

    $nick = trim($nick);
    $qty = max(1, (int)$qty);
    if ($nick === '') {
        return ['ok' => false, 'msg' => '닉네임을 입력하세요. (예: 하리 2)', 'reclaimed' => 0, 'refunded' => '0'];
    }

    $unit = eunchong_underprice_버그단가($unitText);
    $paid = function_exists('bcmul') ? bcmul($unit, (string)$qty, 0) : eunchong_underprice_금액문자((string)((float)$unit * $qty));
    $paid = eunchong_underprice_금액문자($paid);
    $paid_sql = preg_replace('/\D/', '', $paid) ?: '0';

    $nick_esc = addslashes($nick);
    $mem = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
    if (empty($mem['idx'])) {
        $mem = @db_select("SELECT idx, name FROM tb_member WHERE TRIM(name) = '{$nick_esc}' LIMIT 1");
    }
    if (empty($mem['idx'])) {
        return ['ok' => false, 'msg' => "{$nick}: 회원 없음", 'reclaimed' => 0, 'refunded' => '0'];
    }
    // DB에 저장된 정식 닉 사용
    $nick = (string)$mem['name'];
    $nick_esc = addslashes($nick);

    $held = function_exists('bag_은총_수량') ? bag_은총_수량($nick) : 0;
    $reclaim = min($qty, max(0, $held));

    $key = 'manual:' . substr(hash('sha256', $nick . '|' . $qty . '|' . $paid . '|' . microtime(true) . '|' . mt_rand()), 0, 40);
    $raw = "수동 {$nick} {$qty} × " . ($unitText !== '' ? $unitText : EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT);
    $key_esc = addslashes($key);
    $raw_esc = addslashes(mb_substr($raw, 0, 240, 'UTF-8'));
    $admin_esc = addslashes($adminNick);

    $ins = @db_query("
      INSERT INTO tb_eunchong_underprice_fix
        (nick, qty, paid_total, reclaimed, refunded, source_key, source_text, admin_nick, regdate)
      VALUES
        ('{$nick_esc}', {$qty}, '{$paid_sql}', 0, 0, '{$key_esc}', '{$raw_esc}', '{$admin_esc}', NOW())
    ");
    if (!$ins) {
        $err = eunchong_underprice_db_error();
        return ['ok' => false, 'msg' => "{$nick}: 기록 실패" . ($err !== '' ? " ({$err})" : ''), 'reclaimed' => 0, 'refunded' => '0'];
    }
    $fixIdx = 0;
    global $conn;
    if ($conn instanceof mysqli) {
        $fixIdx = (int)mysqli_insert_id($conn);
    }

    $got = 0;
    if ($reclaim > 0) {
        $sub = bag_은총_차감($nick, $reclaim);
        if (!empty($sub['ok'])) {
            $got = $reclaim;
        }
    }

    $okPay = db_query("
      UPDATE tb_member
      SET point = CAST(IFNULL(point, 0) AS DECIMAL(65,0)) + CAST('{$paid_sql}' AS DECIMAL(65,0))
      WHERE name = '{$nick_esc}'
      LIMIT 1
    ");
    $err = eunchong_underprice_db_error();
    $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
    if ($okPay === false || $err !== '' || $affected < 1) {
        if ($got > 0 && function_exists('bag_은총_가산')) {
            bag_은총_가산($nick, $got);
        }
        if ($fixIdx > 0) {
            @db_query("DELETE FROM tb_eunchong_underprice_fix WHERE idx = {$fixIdx} LIMIT 1");
        }
        return [
            'ok' => false,
            'msg' => "{$nick}: 냥 환불 실패" . ($err !== '' ? " ({$err})" : ''),
            'reclaimed' => 0,
            'refunded' => '0',
        ];
    }

    @db_query("
      UPDATE tb_eunchong_underprice_fix
      SET reclaimed = {$got}, refunded = '{$paid_sql}'
      WHERE idx = {$fixIdx}
      LIMIT 1
    ");
    if (function_exists('지급로그')) {
        지급로그('은총2저가수동환불', $nick, $adminNick, 0, $paid_sql);
    }

    $heldAfter = function_exists('bag_은총_수량') ? bag_은총_수량($nick) : 0;
    $msg = "{$nick}: 은총 {$got}개 회수(요청 {$qty}·보유였던 {$held}) · 환불 "
        . eunchong_underprice_표시($paid)
        . " ({$qty}×" . ($unitText !== '' ? $unitText : EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT) . ")"
        . " · 남은 은총 {$heldAfter}";
    if ($got < $qty) {
        $msg .= "\n⚠ 보유 은총이 부족해 {$got}개만 회수했습니다. (환불은 {$qty}개분 전액)";
    }
    return ['ok' => true, 'msg' => $msg, 'reclaimed' => $got, 'refunded' => $paid];
}

/**
 * 수동 여러 줄 일괄 실행
 * @return array{ok:bool,msg:string,fixed:int,reclaimed:int,refunded:string}
 */
function eunchong_underprice_수동일괄(string $text, string $adminNick, string $unitText = ''): array {
    $list = eunchong_underprice_수동파싱($text);
    if ($list === []) {
        return [
            'ok' => false,
            'msg' => '형식이 맞지 않습니다. 예: 하리 2',
            'fixed' => 0,
            'reclaimed' => 0,
            'refunded' => '0',
        ];
    }
    $fixed = 0;
    $reclaimed = 0;
    $refunded = '0';
    $lines = [];
    foreach ($list as $row) {
        $r = eunchong_underprice_수동실행($row['nick'], (int)$row['qty'], $adminNick, $unitText);
        $lines[] = (string)($r['msg'] ?? '');
        if (!empty($r['ok'])) {
            $fixed++;
            $reclaimed += (int)($r['reclaimed'] ?? 0);
            $paid = eunchong_underprice_금액문자((string)($r['refunded'] ?? '0'));
            $refunded = function_exists('bcadd') ? bcadd($refunded, $paid, 0) : (string)((int)$refunded + (int)$paid);
        }
    }
    $head = "수동 " . count($list) . "건 중 {$fixed}건 · 은총 회수 {$reclaimed}개 · 환불 " . eunchong_underprice_표시($refunded);
    return [
        'ok' => $fixed > 0,
        'msg' => $head . "\n" . implode("\n", $lines),
        'fixed' => $fixed,
        'reclaimed' => $reclaimed,
        'refunded' => $refunded,
    ];
}
