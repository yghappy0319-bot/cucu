<?php
/**
 * tb_member_item_bag — 회원 1행 · tb_item.sname별 보유 수량
 * 구매(가산) / 판매·사용(감산) 공용
 * 부루마블 1등 주화는 tb_burumable_coin (회차마다 가방 컬럼을 늘리지 않음)
 */

$__burumable_coin = __DIR__ . '/game/burumable_coin.inc.php';
if (is_file($__burumable_coin)) {
    require_once $__burumable_coin;
}

if (!function_exists('item_bag_snames')) {
    /** @return list<string> */
    function item_bag_snames(bool $refresh = false): array {
        static $cache = null;
        if ($refresh) {
            $cache = null;
        }
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        if (!function_exists('db_query')) {
            return $cache;
        }
        $rs = @db_query("
          SELECT DISTINCT TRIM(sname) AS sname
          FROM tb_item
          WHERE TRIM(IFNULL(sname, '')) <> ''
          ORDER BY sname ASC
        ");
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $n = trim((string)($row['sname'] ?? ''));
                if ($n === '') {
                    continue;
                }
                if (function_exists('부루마블주화인가') && 부루마블주화인가($n)) {
                    continue;
                }
                if (!in_array($n, $cache, true)) {
                    $cache[] = $n;
                }
            }
        }
        return $cache;
    }
}

if (!function_exists('item_bag_snames_flush')) {
    /** tb_item 신규 sname 반영 시 캐시 갱신 */
    function item_bag_snames_flush(): void {
        item_bag_snames(true);
    }
}

if (!function_exists('item_bag_col_exists')) {
    function item_bag_col_exists(string $col): bool {
        $esc = addslashes($col);
        $row = @db_select("SHOW COLUMNS FROM `tb_member_item_bag` LIKE '{$esc}'");
        return !empty($row);
    }
}

if (!function_exists('item_bag_ensure_schema')) {
    function item_bag_ensure_schema(): void {
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
        foreach (item_bag_snames() as $k) {
            if (!item_bag_col_exists($k)) {
                $k_esc = str_replace('`', '``', $k);
                @db_query("ALTER TABLE `tb_member_item_bag` ADD COLUMN `{$k_esc}` INT UNSIGNED NOT NULL DEFAULT 0");
            }
        }
    }
}

if (!function_exists('item_bag_tracked')) {
    function item_bag_tracked(string $itemname): bool {
        $itemname = trim($itemname);
        if ($itemname === '') {
            return false;
        }
        if (function_exists('부루마블주화인가') && 부루마블주화인가($itemname)) {
            return true;
        }
        item_bag_ensure_schema();
        // bag 컬럼이 있으면 카탈로그(tb_item) 없이도 추적 (은총·강화수호 등)
        if (item_bag_col_exists($itemname)) {
            return true;
        }
        return in_array($itemname, item_bag_snames(), true) && item_bag_col_exists($itemname);
    }
}

if (!function_exists('item_bag_ensure_column')) {
    /** tb_item 없이도 bag 컬럼만 강제 생성 */
    function item_bag_ensure_column(string $itemname): bool {
        $itemname = trim($itemname);
        if ($itemname === '') {
            return false;
        }
        if (function_exists('부루마블주화인가') && 부루마블주화인가($itemname)) {
            if (function_exists('부루마블주화_스키마보장')) {
                부루마블주화_스키마보장();
            }
            return true;
        }
        item_bag_ensure_schema();
        if (item_bag_col_exists($itemname)) {
            return true;
        }
        $k = str_replace('`', '``', $itemname);
        @db_query("ALTER TABLE `tb_member_item_bag` ADD COLUMN `{$k}` INT UNSIGNED NOT NULL DEFAULT 0");
        return item_bag_col_exists($itemname);
    }
}

if (!function_exists('item_bag_ensure_member')) {
    function item_bag_ensure_member(int $midx, string $nick): bool {
        if ($midx < 1) {
            return false;
        }
        item_bag_ensure_schema();
        $nick = trim($nick);
        $nick_esc = addslashes($nick !== '' ? $nick : (string)$midx);

        // 1) 이미 midx 행이 있으면 nick만 맞추고 성공
        $row = @db_select("SELECT midx, nick FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        if (!empty($row['midx'])) {
            if ($nick !== '' && trim((string)($row['nick'] ?? '')) !== $nick) {
                // 다른 midx가 같은 nick을 쓰고 있으면 비워 충돌 방지
                @db_query("
                  UPDATE tb_member_item_bag
                  SET nick = CONCAT('__old_', midx, '_', UNIX_TIMESTAMP())
                  WHERE nick = '{$nick_esc}' AND midx <> {$midx}
                  LIMIT 1
                ");
                @db_query("UPDATE tb_member_item_bag SET nick = '{$nick_esc}' WHERE midx = {$midx} LIMIT 1");
            }
            return true;
        }

        // 2) nick만 남은 고아/이전 계정 행 → 현재 midx로 재연결 (보유 수량 유지)
        if ($nick !== '') {
            $byNick = @db_select("SELECT midx FROM tb_member_item_bag WHERE nick = '{$nick_esc}' LIMIT 1");
            if (!empty($byNick['midx'])) {
                $old = (int)$byNick['midx'];
                if ($old > 0 && $old !== $midx) {
                    $okRebind = @db_query("
                      UPDATE tb_member_item_bag
                      SET midx = {$midx}, nick = '{$nick_esc}', updated_at = NOW()
                      WHERE midx = {$old}
                      LIMIT 1
                    ");
                    if ($okRebind) {
                        $check = @db_select("SELECT midx FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
                        if (!empty($check['midx'])) {
                            return true;
                        }
                    }
                    // 재연결 실패 시 고아 행 제거 후 새로 생성
                    @db_query("DELETE FROM tb_member_item_bag WHERE midx = {$old} LIMIT 1");
                } elseif ($old === $midx) {
                    return true;
                }
            }
        }

        // 3) 신규 생성
        $ok = @db_query("
          INSERT INTO tb_member_item_bag (midx, nick, migrated_at)
          VALUES ({$midx}, '{$nick_esc}', NOW())
        ");
        if ($ok) {
            return true;
        }

        // 4) 동시성/잔여 충돌 — 한 번 더 확인
        $again = @db_select("SELECT midx FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        if (!empty($again['midx'])) {
            return true;
        }
        if ($nick !== '') {
            $againNick = @db_select("SELECT midx FROM tb_member_item_bag WHERE nick = '{$nick_esc}' LIMIT 1");
            if (!empty($againNick['midx'])) {
                $old = (int)$againNick['midx'];
                if ($old === $midx) {
                    return true;
                }
                // nick 점유 해제 후 재시도
                @db_query("
                  UPDATE tb_member_item_bag
                  SET nick = CONCAT('__old_', midx, '_', UNIX_TIMESTAMP())
                  WHERE midx = {$old}
                  LIMIT 1
                ");
                $ok2 = @db_query("
                  INSERT INTO tb_member_item_bag (midx, nick, migrated_at)
                  VALUES ({$midx}, '{$nick_esc}', NOW())
                ");
                return (bool)$ok2;
            }
        }
        return false;
    }
}

if (!function_exists('item_bag_qty')) {
    function item_bag_qty(int $midx, string $itemname): int {
        $itemname = trim($itemname);
        if ($midx < 1) {
            return 0;
        }
        if (function_exists('부루마블주화인가') && 부루마블주화인가($itemname) && function_exists('부루마블주화_수량')) {
            return 부루마블주화_수량($midx, $itemname);
        }
        if (!item_bag_tracked($itemname)) {
            return 0;
        }
        $col = str_replace('`', '``', $itemname);
        $row = @db_select("SELECT IFNULL(`{$col}`, 0) AS c FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('item_bag_add')) {
    /**
     * 보유 수량 증가 (구매·지급)
     * @return array{ok:bool,msg?:string,qty?:int}
     */
    function item_bag_add(int $midx, string $nick, string $itemname, int $qty = 1): array {
        $itemname = trim($itemname);
        $qty = (int)$qty;
        if ($midx < 1 || $qty < 1) {
            return ['ok' => false, 'msg' => '지급 정보가 올바르지 않아요.'];
        }
        if (function_exists('부루마블주화인가') && 부루마블주화인가($itemname) && function_exists('부루마블주화_지급')) {
            return 부루마블주화_지급($midx, $nick, $itemname, $qty);
        }
        if (!item_bag_tracked($itemname)) {
            return ['ok' => false, 'msg' => "[{$itemname}] bag에 등록되지 않은 아이템이에요."];
        }
        if (!item_bag_ensure_member($midx, $nick)) {
            return ['ok' => false, 'msg' => '가방 행을 만들 수 없어요.'];
        }
        $col = str_replace('`', '``', $itemname);
        $ok = @db_query("
          UPDATE tb_member_item_bag
          SET `{$col}` = IFNULL(`{$col}`, 0) + {$qty}
          WHERE midx = {$midx}
          LIMIT 1
        ");
        if (!$ok) {
            return ['ok' => false, 'msg' => '가방 수량 증가 실패'];
        }
        return ['ok' => true, 'qty' => item_bag_qty($midx, $itemname)];
    }
}

if (!function_exists('item_bag_sub')) {
    /**
     * 보유 수량 감소 (판매·사용) — 부족하면 실패
     * @return array{ok:bool,msg?:string,qty?:int}
     */
    function item_bag_sub(int $midx, string $nick, string $itemname, int $qty = 1): array {
        $itemname = trim($itemname);
        $qty = (int)$qty;
        if ($midx < 1 || $qty < 1) {
            return ['ok' => false, 'msg' => '차감 정보가 올바르지 않아요.'];
        }
        if (function_exists('부루마블주화인가') && 부루마블주화인가($itemname) && function_exists('부루마블주화_차감')) {
            return 부루마블주화_차감($midx, $nick, $itemname, $qty);
        }
        if (!item_bag_tracked($itemname)) {
            return ['ok' => false, 'msg' => "[{$itemname}] bag에 등록되지 않은 아이템이에요."];
        }
        global $conn;
        item_bag_ensure_member($midx, $nick);
        $col = str_replace('`', '``', $itemname);
        $ok = @db_query("
          UPDATE tb_member_item_bag
          SET `{$col}` = `{$col}` - {$qty}
          WHERE midx = {$midx}
            AND IFNULL(`{$col}`, 0) >= {$qty}
          LIMIT 1
        ");
        $affected = (isset($conn) && ($conn instanceof mysqli)) ? (int)mysqli_affected_rows($conn) : 0;
        if (!$ok || $affected < 1) {
            $have = item_bag_qty($midx, $itemname);
            return ['ok' => false, 'msg' => "{$itemname} 보유수량 {$have}개", 'qty' => $have];
        }
        return ['ok' => true, 'qty' => item_bag_qty($midx, $itemname)];
    }
}

if (!function_exists('item_bag_member_by_nick')) {
    /**
     * @return array{idx:int,name:string}|null
     */
    function item_bag_member_by_nick(string $nick): ?array {
        $nick = trim($nick);
        if ($nick === '') {
            return null;
        }
        $esc = addslashes($nick);
        $row = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        if (empty($row['idx'])) {
            return null;
        }
        return [
            'idx' => (int)$row['idx'],
            'name' => trim((string)($row['name'] ?? $nick)),
        ];
    }
}

if (!function_exists('item_bag_qty_nick')) {
    function item_bag_qty_nick(string $nick, string $itemname): int {
        $mem = item_bag_member_by_nick($nick);
        if ($mem === null) {
            return 0;
        }
        return item_bag_qty($mem['idx'], $itemname);
    }
}

if (!function_exists('item_bag_add_nick')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function item_bag_add_nick(string $nick, string $itemname, int $qty = 1): array {
        $mem = item_bag_member_by_nick($nick);
        if ($mem === null) {
            return ['ok' => false, 'msg' => '회원 정보를 찾을 수 없어요.'];
        }
        return item_bag_add($mem['idx'], $mem['name'], $itemname, $qty);
    }
}

if (!function_exists('item_bag_sub_nick')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function item_bag_sub_nick(string $nick, string $itemname, int $qty = 1): array {
        $mem = item_bag_member_by_nick($nick);
        if ($mem === null) {
            return ['ok' => false, 'msg' => '회원 정보를 찾을 수 없어요.'];
        }
        return item_bag_sub($mem['idx'], $mem['name'], $itemname, $qty);
    }
}

if (!function_exists('item_bag_list')) {
    /**
     * 보유 수량 > 0 인 공식 아이템 목록
     * @return list<array{name:string,count:int}>
     */
    function item_bag_list(int $midx): array {
        if ($midx < 1) {
            return [];
        }
        item_bag_ensure_schema();
        $cols = [];
        foreach (item_bag_snames() as $k) {
            if (item_bag_col_exists($k)) {
                $cols[] = '`' . str_replace('`', '``', $k) . '`';
            }
        }
        $목록 = [];
        if ($cols !== []) {
            $row = @db_select('SELECT ' . implode(', ', $cols) . " FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
            if (is_array($row)) {
                foreach (item_bag_snames() as $k) {
                    $c = (int)($row[$k] ?? 0);
                    if ($c > 0) {
                        $표시 = function_exists('아이템_가방_표시명') ? 아이템_가방_표시명($k) : $k;
                        $목록[] = ['name' => $k, 'label' => $표시, 'count' => $c];
                    }
                }
            }
        }
        if (function_exists('부루마블주화_보유목록')) {
            foreach (부루마블주화_보유목록($midx) as $c) {
                $목록[] = $c;
            }
        }
        usort($목록, static function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        return $목록;
    }
}

/**
 * tb_member_item 의 일방신청권·일방연장권(status 0·1) → tb_member_item_bag
 * bag = max(기존 bag, 행 수) 후 행 삭제 (.선물 동기화로 bag에 이미 반영된 경우 이중가산 방지)
 * @return array{ok:bool,msg:string,items:array<string,array{members:int,qty:int,added:int}>}
 */
if (!function_exists('일방권_item_to_bag_마이그레이션')) {
    function 일방권_item_to_bag_마이그레이션(): array {
        if (!function_exists('item_bag_qty') || !function_exists('item_bag_add') || !function_exists('item_bag_ensure_column')) {
            return ['ok' => false, 'msg' => 'item_bag 미로드', 'items' => []];
        }
        foreach (['일방신청권', '일방연장권'] as $col) {
            item_bag_ensure_column($col);
        }

        $items = [];
        foreach (['일방신청권', '일방연장권'] as $itemname) {
            $item_esc = addslashes($itemname);
            $members = 0;
            $qty = 0;
            $added = 0;
            $rs = @db_query("
              SELECT
                CASE WHEN IFNULL(midx, 0) > 0 THEN midx ELSE 0 END AS midx,
                TRIM(IFNULL(nick, '')) AS nick,
                COUNT(*) AS c
              FROM tb_member_item
              WHERE itemname = '{$item_esc}'
                AND status IN (0, 1)
              GROUP BY
                CASE WHEN IFNULL(midx, 0) > 0 THEN midx ELSE 0 END,
                TRIM(IFNULL(nick, ''))
            ");
            if ($rs) {
                while ($row = db_fetch($rs)) {
                    $c = (int)($row['c'] ?? 0);
                    if ($c < 1) {
                        continue;
                    }
                    $midx = (int)($row['midx'] ?? 0);
                    $nick = trim((string)($row['nick'] ?? ''));
                    if ($midx < 1 && $nick !== '') {
                        $nick_esc = addslashes($nick);
                        $mem = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
                        $midx = (int)($mem['idx'] ?? 0);
                        if ($nick === '' && !empty($mem['name'])) {
                            $nick = trim((string)$mem['name']);
                        }
                    }
                    if ($midx < 1) {
                        continue;
                    }
                    if ($nick === '') {
                        $mem2 = @db_select("SELECT name FROM tb_member WHERE idx = {$midx} LIMIT 1");
                        $nick = trim((string)($mem2['name'] ?? ''));
                    }
                    if ($nick === '') {
                        $nick = (string)$midx;
                    }

                    if (!item_bag_ensure_member($midx, $nick)) {
                        continue;
                    }
                    $bagNow = (int)item_bag_qty($midx, $itemname);
                    $need = $c - $bagNow;
                    if ($need > 0) {
                        $add = item_bag_add($midx, $nick, $itemname, $need);
                        if (empty($add['ok'])) {
                            continue;
                        }
                        $added += $need;
                    }
                    $nick_esc = addslashes($nick);
                    @db_query("
                      DELETE FROM tb_member_item
                      WHERE itemname = '{$item_esc}'
                        AND status IN (0, 1)
                        AND (
                          midx = {$midx}
                          OR (IFNULL(midx, 0) = 0 AND TRIM(IFNULL(nick, '')) = '{$nick_esc}')
                        )
                    ");
                    $members++;
                    $qty += $c;
                }
            }
            $items[$itemname] = ['members' => $members, 'qty' => $qty, 'added' => $added];
        }

        $msgParts = [];
        foreach ($items as $name => $st) {
            $msgParts[] = "{$name} 행{$st['qty']}→가산{$st['added']}/{$st['members']}명";
        }
        return [
            'ok' => true,
            'msg' => '일방권 bag 이관: ' . implode(', ', $msgParts),
            'items' => $items,
        ];
    }
}
