<?php
/**
 * 아이템 보유량 집계 테이블 (tb_member 1명 = 1행)
 * - tb_member_item status=0 → tb_member_item_bag (컬럼 = tb_item.sname)
 * - tb_item.sname에 없는 itemname은 마이그레이션 페이지에서 삭제 가능
 */

if (!defined('ITEM_STOCK_ADMIN_NICK')) {
    define('ITEM_STOCK_ADMIN_NICK', '민호');
}

/** 거래소 시세 설정(분모·유통곡선) 전용 권한 */
if (!defined('ITEM_STOCK_PRICE_ADMIN_NICK')) {
    define('ITEM_STOCK_PRICE_ADMIN_NICK', '민호');
}

/** 아이템 관리 페이지 권한 (민호) */
function item_stock_준호인가($nick): bool {
    return trim((string)$nick) === ITEM_STOCK_ADMIN_NICK;
}

/** 거래소 시세 설정 권한 (민호) */
function item_stock_시세관리자인가($nick): bool {
    return trim((string)$nick) === ITEM_STOCK_PRICE_ADMIN_NICK;
}

function item_stock_auth($code = '') {
    $code = trim((string)$code);
    if ($code === '' && !empty($_COOKIE['wallet_code'])) {
        $code = trim((string)$_COOKIE['wallet_code']);
    }
    if ($code === '' && !empty($_GET['code'])) {
        $code = trim((string)$_GET['code']);
    }
    if ($code === '') {
        return null;
    }
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, status FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name']) || (int)($row['status'] ?? 0) === 1) {
        return null;
    }
    return [
        'code' => $code,
        'nick' => trim((string)$row['name']),
        'midx' => (int)($row['idx'] ?? 0),
    ];
}

/** tb_item.sname 목록 (가방 컬럼) */
function item_stock_keys(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $rs = @db_query("
      SELECT DISTINCT TRIM(sname) AS sname
      FROM tb_item
      WHERE TRIM(IFNULL(sname, '')) <> ''
      ORDER BY sname ASC
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $n = trim((string)($row['sname'] ?? ''));
            if ($n !== '' && !in_array($n, $cache, true)) {
                $cache[] = $n;
            }
        }
    }
    // 폴백 (tb_item 비정상 시)
    if ($cache === []) {
        $cache = ['수호', '제한', '프변', '지호', '선물', '강일', '지목', '교환', '색변', '닉변', '일방신청권', '일방연장권'];
    }
    return $cache;
}

function item_stock_컬럼존재($col): bool {
    $esc = addslashes($col);
    $row = @db_select("SHOW COLUMNS FROM `tb_member_item_bag` LIKE '{$esc}'");
    return !empty($row);
}

function item_stock_테이블보장(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $exists = @db_select("SHOW TABLES LIKE 'tb_member_item_bag'");
    if (empty($exists)) {
        @db_query("
          CREATE TABLE `tb_member_item_bag` (
            `midx` INT NOT NULL,
            `nick` VARCHAR(50) NOT NULL,
            `migrated_at` DATETIME NULL DEFAULT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`midx`),
            UNIQUE KEY `uk_nick` (`nick`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    foreach (item_stock_keys() as $k) {
        if (!item_stock_컬럼존재($k)) {
            $k_esc = str_replace('`', '``', $k);
            @db_query("ALTER TABLE `tb_member_item_bag` ADD COLUMN `{$k_esc}` INT UNSIGNED NOT NULL DEFAULT 0");
        }
    }
}

/** tb_item.sname에 없는 tb_member_item 미리보기 */
function item_stock_비정상아이템_미리보기(): array {
    $rows = [];
    $total = 0;
    $rs = @db_query("
      SELECT mi.itemname, COUNT(*) AS c
      FROM tb_member_item mi
      LEFT JOIN tb_item ti ON TRIM(ti.sname) = TRIM(mi.itemname)
      WHERE ti.idx IS NULL
         OR TRIM(IFNULL(ti.sname, '')) = ''
      GROUP BY mi.itemname
      ORDER BY c DESC
      LIMIT 80
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $c = (int)($row['c'] ?? 0);
            $total += $c;
            $rows[] = [
                'itemname' => trim((string)($row['itemname'] ?? '')),
                'cnt' => $c,
            ];
        }
    }
    $전체 = (int)(db_select("
      SELECT COUNT(*) AS c
      FROM tb_member_item mi
      LEFT JOIN tb_item ti ON TRIM(ti.sname) = TRIM(mi.itemname)
      WHERE ti.idx IS NULL OR TRIM(IFNULL(ti.sname, '')) = ''
    ")['c'] ?? 0);
    return ['rows' => $rows, 'total' => $전체 > 0 ? $전체 : $total];
}

/**
 * tb_item.sname에 없는 itemname 로우 전부 삭제
 * @return array{ok:bool,msg:string,deleted:int}
 */
function item_stock_비정상아이템_삭제(): array {
    $미리 = item_stock_비정상아이템_미리보기();
    $예상 = (int)$미리['total'];
    $rs = @db_query("
      DELETE mi FROM tb_member_item mi
      LEFT JOIN tb_item ti ON TRIM(ti.sname) = TRIM(mi.itemname)
      WHERE ti.idx IS NULL OR TRIM(IFNULL(ti.sname, '')) = ''
    ");
    if (!$rs) {
        return ['ok' => false, 'msg' => '삭제 쿼리 실패', 'deleted' => 0];
    }
    global $conn;
    $deleted = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : $예상;
    return [
        'ok' => true,
        'msg' => "tb_item.sname에 없는 아이템 {$deleted}개 로우를 삭제했습니다.",
        'deleted' => $deleted,
    ];
}

/**
 * tb_member.idx+name 과 맞지 않는 bag 행 (고아 midx / 닉 불일치)
 * @return array{rows:list<array{midx:int,bag_nick:string,mem_name:string}>,total:int}
 */
function item_stock_닉불일치_미리보기(): array {
    $rows = [];
    $exists = @db_select("SHOW TABLES LIKE 'tb_member_item_bag'");
    if (empty($exists)) {
        return ['rows' => [], 'total' => 0];
    }
    $total = (int)(db_select("
      SELECT COUNT(*) AS c
      FROM tb_member_item_bag b
      LEFT JOIN tb_member m
        ON m.idx = b.midx
       AND TRIM(IFNULL(m.name, '')) = TRIM(IFNULL(b.nick, ''))
      WHERE m.idx IS NULL
    ")['c'] ?? 0);
    $rs = @db_query("
      SELECT
        b.midx,
        TRIM(IFNULL(b.nick, '')) AS bag_nick,
        TRIM(IFNULL(m.name, '')) AS mem_name
      FROM tb_member_item_bag b
      LEFT JOIN tb_member m ON m.idx = b.midx
      WHERE m.idx IS NULL
         OR TRIM(IFNULL(b.nick, '')) <> TRIM(IFNULL(m.name, ''))
      ORDER BY b.midx ASC
      LIMIT 80
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = [
                'midx' => (int)($row['midx'] ?? 0),
                'bag_nick' => (string)($row['bag_nick'] ?? ''),
                'mem_name' => (string)($row['mem_name'] ?? ''),
            ];
        }
    }
    return ['rows' => $rows, 'total' => $total];
}

/**
 * tb_member 와 midx·닉이 일치하지 않는 bag 행 삭제
 * @return array{ok:bool,msg:string,deleted:int}
 */
function item_stock_닉불일치_삭제(): array {
    $미리 = item_stock_닉불일치_미리보기();
    $예상 = (int)$미리['total'];
    if ($예상 < 1) {
        return ['ok' => true, 'msg' => '삭제할 닉 불일치 bag 행이 없습니다.', 'deleted' => 0];
    }
    $rs = @db_query("
      DELETE b FROM tb_member_item_bag b
      LEFT JOIN tb_member m
        ON m.idx = b.midx
       AND TRIM(IFNULL(m.name, '')) = TRIM(IFNULL(b.nick, ''))
      WHERE m.idx IS NULL
    ");
    if (!$rs) {
        return ['ok' => false, 'msg' => '닉 불일치 bag 삭제 실패', 'deleted' => 0];
    }
    global $conn;
    $deleted = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : $예상;
    return [
        'ok' => true,
        'msg' => "tb_member와 닉이 맞지 않는 bag {$deleted}행을 삭제했습니다.",
        'deleted' => $deleted,
    ];
}

function item_stock_미리보기(): array {
    $keys = item_stock_keys();
    $회원수 = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member WHERE IFNULL(status, 0) <> 1")['c'] ?? 0);
    $총로우 = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member_item")['c'] ?? 0);
    $미사용 = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member_item WHERE status = 0")['c'] ?? 0);
    $사용됨 = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member_item WHERE status <> 0")['c'] ?? 0);

    $항목 = [];
    $rs = @db_query("
      SELECT itemname, COUNT(*) AS c
      FROM tb_member_item
      WHERE status = 0
      GROUP BY itemname
      ORDER BY c DESC
      LIMIT 40
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $name = trim((string)($row['itemname'] ?? ''));
            $항목[] = [
                'itemname' => $name,
                'cnt' => (int)($row['c'] ?? 0),
                'tracked' => in_array($name, $keys, true),
            ];
        }
    }

    $백존재 = false;
    $백행수 = 0;
    $exists = @db_select("SHOW TABLES LIKE 'tb_member_item_bag'");
    if (!empty($exists)) {
        $백존재 = true;
        $백행수 = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member_item_bag")['c'] ?? 0);
    }

    $비정상 = item_stock_비정상아이템_미리보기();
    $닉불일치 = item_stock_닉불일치_미리보기();

    return [
        'member_n' => $회원수,
        'total_rows' => $총로우,
        'unused_rows' => $미사용,
        'used_rows' => $사용됨,
        'items' => $항목,
        'bag_exists' => $백존재,
        'bag_rows' => $백행수,
        'keys' => $keys,
        'orphan' => $비정상,
        'nick_mismatch' => $닉불일치,
    ];
}

/**
 * tb_member 전원(탈퇴 status=1 제외) 1행씩 + status=0·sname 아이템만 수량 반영
 * @return array{ok:bool,msg:string,members:int,unused:int}
 */
function item_stock_마이그레이션_실행(): array {
    item_stock_테이블보장();
    $keys = item_stock_keys();
    if ($keys === []) {
        return ['ok' => false, 'msg' => 'tb_item.sname이 비어 있습니다.', 'members' => 0, 'unused' => 0];
    }

    // nick => [item => qty] (공식 sname만)
    $qtyMap = [];
    $unused = 0;
    $inList = [];
    foreach ($keys as $k) {
        $inList[] = "'" . addslashes($k) . "'";
    }
    $inSql = implode(',', $inList);

    $rs = @db_query("
      SELECT
        TRIM(IFNULL(nick, '')) AS nick,
        TRIM(itemname) AS itemname,
        COUNT(*) AS c
      FROM tb_member_item
      WHERE status = 0
        AND TRIM(IFNULL(nick, '')) <> ''
        AND TRIM(itemname) IN ({$inSql})
      GROUP BY nick, itemname
    ");
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $nick = trim((string)$row['nick']);
            $name = trim((string)$row['itemname']);
            $c = (int)($row['c'] ?? 0);
            if ($nick === '' || $c < 1 || !in_array($name, $keys, true)) {
                continue;
            }
            if (!isset($qtyMap[$nick])) {
                $qtyMap[$nick] = [];
            }
            $qtyMap[$nick][$name] = ($qtyMap[$nick][$name] ?? 0) + $c;
            $unused += $c;
        }
    }

    @db_query('TRUNCATE TABLE tb_member_item_bag');

    $colList = ['midx', 'nick'];
    foreach ($keys as $k) {
        $colList[] = '`' . str_replace('`', '``', $k) . '`';
    }
    $colList[] = 'migrated_at';
    $cols = implode(', ', $colList);

    $n = 0;
    $mrs = @db_query("
      SELECT idx, TRIM(name) AS nick
      FROM tb_member
      WHERE IFNULL(status, 0) <> 1
        AND TRIM(IFNULL(name, '')) <> ''
      ORDER BY idx ASC
    ");
    if (!$mrs) {
        return ['ok' => false, 'msg' => 'tb_member 조회 실패', 'members' => 0, 'unused' => 0];
    }

    while ($m = mysqli_fetch_assoc($mrs)) {
        $midx = (int)($m['idx'] ?? 0);
        $nick = trim((string)($m['nick'] ?? ''));
        if ($midx <= 0 || $nick === '') {
            continue;
        }
        $nick_esc = addslashes($nick);
        $vals = [(string)$midx, "'{$nick_esc}'"];
        $q = $qtyMap[$nick] ?? [];
        foreach ($keys as $k) {
            $vals[] = (string)max(0, (int)($q[$k] ?? 0));
        }
        $vals[] = 'NOW()';
        $sql = 'INSERT INTO tb_member_item_bag (' . $cols . ') VALUES (' . implode(', ', $vals) . ')';
        if (@db_query($sql)) {
            $n++;
        }
    }

    return [
        'ok' => true,
        'msg' => "마이그레이션 완료 · tb_member {$n}명 전원 반영 · 공식 아이템 미사용 {$unused}개 수량 합산",
        'members' => $n,
        'unused' => $unused,
    ];
}

/**
 * bag 합계 vs tb_member_item status=0 (sname만) 검증
 * @return array{ok:bool,checks:list<array{item:string,bag:int,src:int,match:bool}>}
 */
function item_stock_검증(): array {
    item_stock_테이블보장();
    $checks = [];
    $allOk = true;
    foreach (item_stock_keys() as $k) {
        if (!item_stock_컬럼존재($k)) {
            $checks[] = ['item' => $k, 'bag' => 0, 'src' => 0, 'match' => false];
            $allOk = false;
            continue;
        }
        $k_esc = str_replace('`', '``', $k);
        $bag = (int)(db_select("SELECT IFNULL(SUM(`{$k_esc}`),0) AS c FROM tb_member_item_bag")['c'] ?? 0);
        $name_esc = addslashes($k);
        $src = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member_item WHERE status = 0 AND itemname = '{$name_esc}'")['c'] ?? 0);
        $match = ($bag === $src);
        if (!$match) {
            $allOk = false;
        }
        $checks[] = ['item' => $k, 'bag' => $bag, 'src' => $src, 'match' => $match];
    }
    return ['ok' => $allOk, 'checks' => $checks];
}
