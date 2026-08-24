<?php
/**
 * 무기 강화용 은총 · 강화수호 — tb_member_item_bag 연동
 * sname: 은총 / 강화수호
 *
 * 규칙:
 * - bag 행이 있고 (또는 bag 수량>0)이면 bag가 소스
 * - bag=0 이고 컬럼>0 이면 첫 연산 시 컬럼→bag 이관(hydrate)
 * - 쓰기 후 컬럼은 bag 수량으로 미러(구 코드/표시 호환)
 */

require_once __DIR__ . '/item_bag.inc.php';

if (!defined('BAG_ENHANCE_ITEM_은총')) {
    define('BAG_ENHANCE_ITEM_은총', '은총');
}
if (!defined('BAG_ENHANCE_ITEM_강화수호')) {
    define('BAG_ENHANCE_ITEM_강화수호', '강화수호');
}

if (!function_exists('bag_enhance_tb_item_보장')) {
    function bag_enhance_tb_item_보장(string $sname): void {
        $sname = trim($sname);
        if ($sname === '') {
            return;
        }
        $esc = addslashes($sname);
        $row = @db_select("SELECT idx FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
        if (!empty($row['idx'])) {
            return;
        }
        if (!@db_query("
          INSERT INTO tb_item
          SET sname = '{$esc}', buy = 0, sell = 0, percent = 0, buystatus = 1, sort = 900
        ")) {
            @db_query("INSERT INTO tb_item (sname) VALUES ('{$esc}')");
        }
        if (function_exists('item_bag_snames_flush')) {
            item_bag_snames_flush();
        }
    }
}

if (!function_exists('bag_enhance_스키마보장')) {
    function bag_enhance_스키마보장(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([BAG_ENHANCE_ITEM_은총, BAG_ENHANCE_ITEM_강화수호] as $sname) {
            bag_enhance_tb_item_보장($sname);
            if (function_exists('item_bag_ensure_column')) {
                item_bag_ensure_column($sname);
            } elseif (!item_bag_col_exists($sname)) {
                $k = str_replace('`', '``', $sname);
                item_bag_ensure_schema();
                @db_query("ALTER TABLE `tb_member_item_bag` ADD COLUMN `{$k}` INT UNSIGNED NOT NULL DEFAULT 0");
            }
        }
        if (function_exists('item_bag_snames_flush')) {
            item_bag_snames_flush();
        }
    }
}

if (!function_exists('bag_enhance_has_bag_row')) {
    function bag_enhance_has_bag_row(int $midx): bool {
        if ($midx < 1) {
            return false;
        }
        $row = @db_select("SELECT midx FROM tb_member_item_bag WHERE midx = {$midx} LIMIT 1");
        return !empty($row['midx']);
    }
}

if (!function_exists('bag_enhance_member')) {
    /** @return array{idx:int,name:string}|null */
    function bag_enhance_member(string $nick): ?array {
        return item_bag_member_by_nick($nick);
    }
}

if (!function_exists('bag_enhance_column_qty')) {
    function bag_enhance_column_qty(string $nick, string $col): int {
        $nick = trim($nick);
        $col = trim($col);
        if ($nick === '' || $col === '') {
            return 0;
        }
        $esc = addslashes($nick);
        $colEsc = str_replace('`', '``', $col);
        $exists = @db_select("SHOW COLUMNS FROM tb_member LIKE '" . addslashes($col) . "'");
        if (empty($exists['Field'])) {
            return 0;
        }
        $row = @db_select("SELECT IFNULL(`{$colEsc}`, 0) AS c FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        return max(0, (int)($row['c'] ?? 0));
    }
}

if (!function_exists('bag_enhance_column_set')) {
    function bag_enhance_column_set(string $nick, string $col, int $qty): void {
        $nick = trim($nick);
        $col = trim($col);
        $qty = max(0, $qty);
        if ($nick === '' || $col === '') {
            return;
        }
        $esc = addslashes($nick);
        $colEsc = str_replace('`', '``', $col);
        $exists = @db_select("SHOW COLUMNS FROM tb_member LIKE '" . addslashes($col) . "'");
        if (empty($exists['Field'])) {
            return;
        }
        @db_query("UPDATE tb_member SET `{$colEsc}` = {$qty} WHERE name = '{$esc}' LIMIT 1");
    }
}

/**
 * bag ↔ 컬럼 동기화
 * - bag 행이 있으면 bag가 소스 (컬럼만 미러) — 매도 후 컬럼 잔존 시 .가방 복구 방지
 * - bag 행이 없고 컬럼>0 이면 컬럼→bag 이관 후 컬럼 0
 */
if (!function_exists('bag_enhance_hydrate')) {
    function bag_enhance_hydrate(string $nick, string $sname, string $col): void {
        bag_enhance_스키마보장();
        $mem = bag_enhance_member($nick);
        if ($mem === null) {
            return;
        }
        $bagQty = item_bag_qty($mem['idx'], $sname);
        $colQty = bag_enhance_column_qty($nick, $col);

        // bag 행 존재 = bag가 진실 (0이어도 컬럼에서 되살리지 않음)
        if (bag_enhance_has_bag_row($mem['idx'])) {
            if ($colQty !== $bagQty) {
                bag_enhance_column_set($nick, $col, $bagQty);
            }
            return;
        }

        if ($colQty < 1) {
            return;
        }
        item_bag_ensure_member($mem['idx'], $mem['name']);
        $add = item_bag_add($mem['idx'], $mem['name'], $sname, $colQty);
        if (!empty($add['ok'])) {
            bag_enhance_column_set($nick, $col, 0);
        }
    }
}

if (!function_exists('bag_enhance_mirror_column')) {
    function bag_enhance_mirror_column(string $nick, string $sname, string $col): void {
        $mem = bag_enhance_member($nick);
        if ($mem === null) {
            return;
        }
        $qty = item_bag_qty($mem['idx'], $sname);
        bag_enhance_column_set($nick, $col, $qty);
    }
}

// ——— 은총 ———

if (!function_exists('bag_은총_수량')) {
    function bag_은총_수량(string $nick): int {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        if ($nick === '') {
            return 0;
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
        $mem = bag_enhance_member($nick);
        if ($mem === null) {
            return bag_enhance_column_qty($nick, '은총개수');
        }
        if (bag_enhance_has_bag_row($mem['idx']) || item_bag_tracked(BAG_ENHANCE_ITEM_은총)) {
            item_bag_ensure_member($mem['idx'], $mem['name']);
            return item_bag_qty($mem['idx'], BAG_ENHANCE_ITEM_은총);
        }
        return bag_enhance_column_qty($nick, '은총개수');
    }
}

if (!function_exists('bag_은총_가산')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function bag_은총_가산(string $nick, int $qty = 1): array {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        $qty = max(0, (int)$qty);
        if ($nick === '' || $qty < 1) {
            return ['ok' => false, 'msg' => '지급 정보가 올바르지 않아요.'];
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
        $r = item_bag_add_nick($nick, BAG_ENHANCE_ITEM_은총, $qty);
        if (!empty($r['ok'])) {
            bag_enhance_mirror_column($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
        }
        return $r;
    }
}

if (!function_exists('bag_은총_차감')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function bag_은총_차감(string $nick, int $qty = 1): array {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        $qty = max(0, (int)$qty);
        if ($nick === '' || $qty < 1) {
            return ['ok' => false, 'msg' => '차감 정보가 올바르지 않아요.'];
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
        $r = item_bag_sub_nick($nick, BAG_ENHANCE_ITEM_은총, $qty);
        if (!empty($r['ok'])) {
            bag_enhance_mirror_column($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
        }
        return $r;
    }
}

/**
 * 거래소(chat_stock) 은총 매도 후 bag만 깎이고 은총개수 hydrate로 복구된 건 소급 정리
 * - 미리보기: bag_은총_거래소매도_정산(false)
 * - 실행: bag_은총_거래소매도_정산(true)
 *
 * 판별:
 *  · col > bag → 부분매도(미러 안 됨). bag이 진실 → 컬럼만 동기화, 차감 없음
 *  · bag > 0 && sells > buys && col <= bag → 전량매도 후 컬럼 복구(유령) 가능성 → min(bag, sells-buys) 차감
 *
 * @return array{ok:bool,msg:string,applied:bool,days:int,rows:list<array<string,mixed>>,deduct_total:int,sync_total:int}
 */
if (!function_exists('bag_은총_거래소매도_정산')) {
    function bag_은총_거래소매도_정산(bool $apply = false, int $recentDays = 30): array {
        bag_enhance_스키마보장();
        if (!function_exists('item_trade_log_스키마보장') && is_file(__DIR__ . '/item_trade_log.inc.php')) {
            require_once __DIR__ . '/item_trade_log.inc.php';
        }
        if (function_exists('item_trade_log_스키마보장')) {
            item_trade_log_스키마보장();
        }

        $recentDays = max(1, min(365, (int)$recentDays));
        $rows = [];
        $deductTotal = 0;
        $syncTotal = 0;

        // 최근 N일 안에 거래소 은총 매도가 있는 닉
        $rsNick = @db_query("
          SELECT DISTINCT nick, midx
          FROM tb_item_trade_log
          WHERE side = 'sell'
            AND channel = 'chat_stock'
            AND TRIM(item) IN ('은총', '은총2')
            AND regdate >= (NOW() - INTERVAL {$recentDays} DAY)
          ORDER BY nick ASC
        ");
        if (!$rsNick) {
            return [
                'ok' => false,
                'msg' => '판매 로그를 읽지 못했어요.',
                'applied' => false,
                'days' => $recentDays,
                'rows' => [],
                'deduct_total' => 0,
                'sync_total' => 0,
            ];
        }

        while ($nr = db_fetch($rsNick)) {
            $nick = trim((string)($nr['nick'] ?? ''));
            $midx = (int)($nr['midx'] ?? 0);
            if ($nick === '') {
                continue;
            }
            $nick_esc = addslashes($nick);

            if ($midx < 1) {
                $mem = bag_enhance_member($nick);
                $midx = $mem ? (int)$mem['idx'] : 0;
            }
            if ($midx < 1) {
                continue;
            }

            // hydrate 없이 raw 조회 (부분매도 col>bag 신호 보존)
            $bag = item_bag_qty($midx, BAG_ENHANCE_ITEM_은총);
            $col = bag_enhance_column_qty($nick, '은총개수');

            $agg = @db_select("
              SELECT
                CAST(COALESCE(SUM(CASE WHEN side = 'buy' THEN qty ELSE 0 END), 0) AS UNSIGNED) AS buys,
                CAST(COALESCE(SUM(CASE WHEN side = 'sell' THEN qty ELSE 0 END), 0) AS UNSIGNED) AS sells,
                CAST(COALESCE(SUM(CASE WHEN side = 'sell' AND regdate >= (NOW() - INTERVAL {$recentDays} DAY) THEN qty ELSE 0 END), 0) AS UNSIGNED) AS sells_recent
              FROM tb_item_trade_log
              WHERE channel = 'chat_stock'
                AND TRIM(item) IN ('은총', '은총2')
                AND (midx = {$midx} OR nick = '{$nick_esc}')
            ");
            $buys = (int)($agg['buys'] ?? 0);
            $sells = (int)($agg['sells'] ?? 0);
            $sellsRecent = (int)($agg['sells_recent'] ?? 0);
            if ($sellsRecent < 1) {
                continue;
            }

            $action = 'skip';
            $deduct = 0;
            $note = '';

            if ($col > $bag) {
                // 부분매도 등 — bag이 진실, 컬럼만 맞춤
                $action = 'sync';
                $note = '컬럼>' . $bag . '개(부분매도/미러누락) → bag 기준으로 동기화';
            } elseif ($bag > 0 && $sells > $buys && $col <= $bag) {
                // 전량매도 후 옛 hydrate 복구(유령) 가능성
                $deduct = min($bag, $sells - $buys);
                if ($deduct > 0) {
                    $action = 'deduct';
                    $note = "매도{$sells}−매수{$buys} 초과분 유령복구 의심 → {$deduct}개 차감";
                } else {
                    $action = 'skip';
                    $note = '차감 수량 0';
                }
            } elseif ($bag !== $col) {
                $action = 'sync';
                $note = "bag{$bag}/col{$col} 불일치 → 동기화";
            } else {
                $action = 'skip';
                $note = '이상 없음';
            }

            $afterBag = $bag;
            $afterCol = $col;
            $okRow = true;
            $err = '';

            if ($apply) {
                if ($action === 'deduct' && $deduct > 0) {
                    // 차감 전 컬럼을 bag에 맞춰 복구 hydrate를 막음
                    bag_enhance_column_set($nick, '은총개수', $bag);
                    $r = bag_은총_차감($nick, $deduct);
                    if (empty($r['ok'])) {
                        $okRow = false;
                        $err = (string)($r['msg'] ?? '차감 실패');
                    } else {
                        $afterBag = (int)($r['qty'] ?? bag_은총_수량($nick));
                        $afterCol = bag_enhance_column_qty($nick, '은총개수');
                        $deductTotal += $deduct;
                    }
                } elseif ($action === 'sync') {
                    bag_enhance_mirror_column($nick, BAG_ENHANCE_ITEM_은총, '은총개수');
                    $afterBag = item_bag_qty($midx, BAG_ENHANCE_ITEM_은총);
                    $afterCol = bag_enhance_column_qty($nick, '은총개수');
                    $syncTotal++;
                }
            } else {
                if ($action === 'deduct') {
                    $deductTotal += $deduct;
                } elseif ($action === 'sync') {
                    $syncTotal++;
                }
            }

            if ($action === 'skip' && !$apply) {
                // 미리보기에서는 이상 없는 닉은 생략
                continue;
            }
            if ($action === 'skip' && $apply) {
                continue;
            }

            $rows[] = [
                'nick' => $nick,
                'midx' => $midx,
                'bag' => $bag,
                'col' => $col,
                'buys' => $buys,
                'sells' => $sells,
                'sells_recent' => $sellsRecent,
                'action' => $action,
                'deduct' => $deduct,
                'note' => $note,
                'after_bag' => $afterBag,
                'after_col' => $afterCol,
                'ok' => $okRow,
                'err' => $err,
            ];
        }

        $mode = $apply ? '실행' : '미리보기';
        $msg = "은총 거래소 매도 정산 ({$mode} · 최근 {$recentDays}일)\n";
        $msg .= "차감 대상 {$deductTotal}개 · 동기화 " . count(array_filter($rows, static function ($r) {
            return ($r['action'] ?? '') === 'sync';
        })) . "명\n";
        if ($rows === []) {
            $msg .= '대상 없음';
        } else {
            $lines = [];
            foreach ($rows as $r) {
                $line = "· {$r['nick']}: bag{$r['bag']}/col{$r['col']} · 매수{$r['buys']}/매도{$r['sells']}";
                if (($r['action'] ?? '') === 'deduct') {
                    $line .= " → 차감 {$r['deduct']}개";
                    if ($apply && !empty($r['ok'])) {
                        $line .= " (후 {$r['after_bag']})";
                    }
                } elseif (($r['action'] ?? '') === 'sync') {
                    $line .= ' → 컬럼동기화';
                }
                if (!empty($r['err'])) {
                    $line .= " ⚠{$r['err']}";
                }
                $lines[] = $line;
                if (count($lines) >= 25) {
                    $lines[] = '· …';
                    break;
                }
            }
            $msg .= implode("\n", $lines);
            if (!$apply) {
                $msg .= "\n\n적용: .은총매도정산 실행";
            }
        }

        return [
            'ok' => true,
            'msg' => $msg,
            'applied' => $apply,
            'days' => $recentDays,
            'rows' => $rows,
            'deduct_total' => $deductTotal,
            'sync_total' => $syncTotal,
        ];
    }
}

// ——— 강화수호 ———

if (!function_exists('bag_강화수호_수량')) {
    function bag_강화수호_수량(string $nick): int {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        if ($nick === '') {
            return 0;
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        $mem = bag_enhance_member($nick);
        if ($mem === null) {
            return bag_enhance_column_qty($nick, 'enhance_suho');
        }
        item_bag_ensure_member($mem['idx'], $mem['name']);
        return item_bag_qty($mem['idx'], BAG_ENHANCE_ITEM_강화수호);
    }
}

if (!function_exists('bag_강화수호_가산')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function bag_강화수호_가산(string $nick, int $qty = 1): array {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        $qty = max(0, (int)$qty);
        if ($nick === '' || $qty < 1) {
            return ['ok' => false, 'msg' => '지급 정보가 올바르지 않아요.'];
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        $r = item_bag_add_nick($nick, BAG_ENHANCE_ITEM_강화수호, $qty);
        if (!empty($r['ok'])) {
            bag_enhance_mirror_column($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        }
        return $r;
    }
}

if (!function_exists('bag_강화수호_차감')) {
    /** @return array{ok:bool,msg?:string,qty?:int} */
    function bag_강화수호_차감(string $nick, int $qty = 1): array {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        $qty = max(0, (int)$qty);
        if ($nick === '' || $qty < 1) {
            return ['ok' => false, 'msg' => '차감 정보가 올바르지 않아요.'];
        }
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        $r = item_bag_sub_nick($nick, BAG_ENHANCE_ITEM_강화수호, $qty);
        if (!empty($r['ok'])) {
            bag_enhance_mirror_column($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        }
        return $r;
    }
}

/** 배치 커밋용 — bag 절대값 설정 + 컬럼 미러 */
if (!function_exists('bag_강화수호_설정')) {
    function bag_강화수호_설정(string $nick, int $qty): bool {
        bag_enhance_스키마보장();
        $nick = trim($nick);
        $qty = max(0, (int)$qty);
        bag_enhance_hydrate($nick, BAG_ENHANCE_ITEM_강화수호, 'enhance_suho');
        $mem = bag_enhance_member($nick);
        if ($mem === null) {
            bag_enhance_column_set($nick, 'enhance_suho', $qty);
            return true;
        }
        item_bag_ensure_member($mem['idx'], $mem['name']);
        $col = str_replace('`', '``', BAG_ENHANCE_ITEM_강화수호);
        $ok = @db_query("
          UPDATE tb_member_item_bag
          SET `{$col}` = {$qty}
          WHERE midx = {$mem['idx']}
          LIMIT 1
        ");
        if ($ok) {
            bag_enhance_column_set($nick, 'enhance_suho', $qty);
        }
        return (bool)$ok;
    }
}
