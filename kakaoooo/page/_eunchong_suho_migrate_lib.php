<?php
/**
 * tb_member.은총개수 · enhance_suho → tb_member_item_bag (은총 · 강화수호)
 *
 * 핵심: 수량은 bag 테이블로 이동.
 * tb_item 등록은 bag/상점 카탈로그 호환용 스텁이며, 실패해도 bag 컬럼·이관은 진행.
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';

if (!defined('EUNCHONG_SUHO_ITEM_은총')) {
    define('EUNCHONG_SUHO_ITEM_은총', '은총');
}
if (!defined('EUNCHONG_SUHO_ITEM_강화수호')) {
    define('EUNCHONG_SUHO_ITEM_강화수호', '강화수호');
}

/** @return list<array{sname:string,col:string,label:string}> */
function eunchong_suho_migrate_대상목록(): array {
    return [
        [
            'sname' => EUNCHONG_SUHO_ITEM_은총,
            'col' => '은총개수',
            'label' => '은총',
        ],
        [
            'sname' => EUNCHONG_SUHO_ITEM_강화수호,
            'col' => 'enhance_suho',
            'label' => '강화수호',
        ],
    ];
}

function eunchong_suho_migrate_member컬럼존재(string $col): bool {
    $esc = addslashes($col);
    $row = @db_select("SHOW COLUMNS FROM tb_member LIKE '{$esc}'");
    return !empty($row['Field']);
}

function eunchong_suho_migrate_db_error(): string {
    global $conn;
    if ($conn instanceof mysqli) {
        $e = trim((string)mysqli_error($conn));
        if ($e !== '') {
            return $e;
        }
    }
    return '';
}

/**
 * tb_item 스텁 등록 (선택). bag 이관과 무관하게 실패해도 됨.
 * @return array{ok:bool,msg:string,exists?:bool}
 */
function eunchong_suho_migrate_tb_item_보장(string $sname): array {
    $sname = trim($sname);
    if ($sname === '') {
        return ['ok' => false, 'msg' => '빈 sname'];
    }
    $esc = addslashes($sname);
    $row = @db_select("SELECT idx FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
    if (!empty($row['idx'])) {
        return ['ok' => true, 'msg' => "tb_item [{$sname}] 이미 있음", 'exists' => true];
    }

    $시도 = [
        "INSERT INTO tb_item SET sname = '{$esc}', buy = 0, sell = 0, percent = 0, buystatus = 1, sort = 900",
        "INSERT INTO tb_item SET sname = '{$esc}', buy = 0, sell = 0, percent = 0, buystatus = 1",
        "INSERT INTO tb_item SET sname = '{$esc}', buy = 0, sell = 0, percent = 0, buystatus = 0, sort = 900",
        "INSERT INTO tb_item (sname, buy, sell, percent, buystatus) VALUES ('{$esc}', 0, 0, 0, 1)",
        "INSERT INTO tb_item (sname) VALUES ('{$esc}')",
    ];

    // DESCRIBE 기반: NOT NULL 이고 DEFAULT 없는 컬럼에 안전한 값
    $cols = [];
    $rs = @db_query('SHOW COLUMNS FROM tb_item');
    if ($rs) {
        while ($c = mysqli_fetch_assoc($rs)) {
            $cols[] = $c;
        }
    }
    if ($cols !== []) {
        $fields = [];
        $values = [];
        foreach ($cols as $c) {
            $field = (string)($c['Field'] ?? '');
            if ($field === '' || strcasecmp($field, 'idx') === 0) {
                continue;
            }
            $extra = strtolower((string)($c['Extra'] ?? ''));
            if (strpos($extra, 'auto_increment') !== false) {
                continue;
            }
            $nullOk = strtoupper((string)($c['Null'] ?? '')) === 'YES';
            $default = $c['Default'] ?? null;
            $hasDefault = array_key_exists('Default', $c) && $default !== null;

            if ($field === 'sname') {
                $fields[] = '`' . str_replace('`', '``', $field) . '`';
                $values[] = "'{$esc}'";
                continue;
            }
            // 값이 필수인 컬럼만 채움
            if ($nullOk || $hasDefault) {
                continue;
            }
            $type = strtolower((string)($c['Type'] ?? ''));
            $fields[] = '`' . str_replace('`', '``', $field) . '`';
            if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false
                || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
                $values[] = '0';
            } elseif (strpos($type, 'date') !== false || strpos($type, 'time') !== false) {
                $values[] = 'NOW()';
            } else {
                $values[] = "''";
            }
        }
        if ($fields !== [] && in_array("'{$esc}'", $values, true)) {
            array_unshift($시도, 'INSERT INTO tb_item (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')');
        }
    }

    $lastErr = '';
    foreach ($시도 as $sql) {
        $ok = @db_query($sql);
        if ($ok) {
            $check = @db_select("SELECT idx FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
            if (!empty($check['idx'])) {
                return ['ok' => true, 'msg' => "tb_item [{$sname}] 등록됨", 'exists' => false];
            }
        }
        $err = eunchong_suho_migrate_db_error();
        if ($err !== '') {
            $lastErr = $err;
        }
    }

    return [
        'ok' => false,
        'msg' => "tb_item [{$sname}] 스텁 등록 실패" . ($lastErr !== '' ? " ({$lastErr})" : ''),
    ];
}

/**
 * bag 테이블·컬럼 보장 (+ 가능하면 tb_item 스텁)
 * @return array{ok:bool,msg:string}
 */
function eunchong_suho_migrate_스키마보장(): array {
    $notes = [];

    $bagTbl = @db_select("SHOW TABLES LIKE 'tb_member_item_bag'");
    if (empty($bagTbl)) {
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
    if (function_exists('item_bag_ensure_schema')) {
        item_bag_ensure_schema();
    }

    foreach (eunchong_suho_migrate_대상목록() as $t) {
        $sname = $t['sname'];
        $col = $t['col'];
        if (!eunchong_suho_migrate_member컬럼존재($col)) {
            $notes[] = "⚠ tb_member.{$col} 컬럼 없음";
        }

        // 1) bag 컬럼 — 이게 마이그레이션 본체
        $bagOk = function_exists('item_bag_ensure_column')
            ? item_bag_ensure_column($sname)
            : false;
        if (!$bagOk) {
            $k_esc = str_replace('`', '``', $sname);
            @db_query("ALTER TABLE `tb_member_item_bag` ADD COLUMN `{$k_esc}` INT UNSIGNED NOT NULL DEFAULT 0");
            $bagOk = function_exists('item_bag_col_exists')
                ? item_bag_col_exists($sname)
                : !empty(@db_select("SHOW COLUMNS FROM `tb_member_item_bag` LIKE '" . addslashes($sname) . "'"));
        }
        if (!$bagOk) {
            $err = eunchong_suho_migrate_db_error();
            return [
                'ok' => false,
                'msg' => "bag 컬럼 [{$sname}] 생성 실패" . ($err !== '' ? " ({$err})" : ''),
            ];
        }
        $notes[] = "bag.{$sname} 컬럼 확인";

        // 2) tb_item 스텁 — 실패해도 이관 계속 (카탈로그 호환용)
        $itemR = eunchong_suho_migrate_tb_item_보장($sname);
        if (!empty($itemR['ok'])) {
            $notes[] = (string)$itemR['msg'];
        } else {
            $notes[] = '⚠ ' . (string)($itemR['msg'] ?? 'tb_item 스텁 실패') . ' — bag 이관은 계속 가능';
        }
    }

    if (function_exists('item_bag_snames_flush')) {
        item_bag_snames_flush();
    }

    return ['ok' => true, 'msg' => implode("\n", $notes)];
}

/**
 * @return array{
 *   ok:bool,
 *   items:list<array{label:string,sname:string,col:string,src_sum:int,src_holders:int,bag_sum:int,bag_holders:int,top:list}>,
 *   members:int
 * }
 */
function eunchong_suho_migrate_미리보기(): array {
    $members = (int)(db_select("SELECT COUNT(*) AS c FROM tb_member WHERE IFNULL(status, 0) <> 1")['c'] ?? 0);
    $items = [];

    foreach (eunchong_suho_migrate_대상목록() as $t) {
        $sname = $t['sname'];
        $col = $t['col'];
        $label = $t['label'];
        $src_sum = 0;
        $src_holders = 0;
        $bag_sum = 0;
        $bag_holders = 0;
        $top = [];

        if (eunchong_suho_migrate_member컬럼존재($col)) {
            $sumRow = db_select("
              SELECT
                IFNULL(SUM(GREATEST(IFNULL(`{$col}`, 0), 0)), 0) AS s,
                IFNULL(SUM(CASE WHEN IFNULL(`{$col}`, 0) > 0 THEN 1 ELSE 0 END), 0) AS h
              FROM tb_member
              WHERE IFNULL(status, 0) <> 1
            ");
            $src_sum = (int)($sumRow['s'] ?? 0);
            $src_holders = (int)($sumRow['h'] ?? 0);

            $rs = @db_query("
              SELECT name, IFNULL(`{$col}`, 0) AS qty
              FROM tb_member
              WHERE IFNULL(status, 0) <> 1 AND IFNULL(`{$col}`, 0) > 0
              ORDER BY qty DESC, name ASC
              LIMIT 20
            ");
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $top[] = [
                        'nick' => (string)($row['name'] ?? ''),
                        'src' => (int)($row['qty'] ?? 0),
                    ];
                }
            }
        }

        $bagExists = @db_select("SHOW TABLES LIKE 'tb_member_item_bag'");
        $colOk = !empty($bagExists) && (
            (function_exists('item_bag_col_exists') && item_bag_col_exists($sname))
            || !empty(@db_select("SHOW COLUMNS FROM `tb_member_item_bag` LIKE '" . addslashes($sname) . "'"))
        );
        if ($colOk) {
            $colEsc = str_replace('`', '``', $sname);
            $bagRow = db_select("
              SELECT
                IFNULL(SUM(GREATEST(IFNULL(`{$colEsc}`, 0), 0)), 0) AS s,
                IFNULL(SUM(CASE WHEN IFNULL(`{$colEsc}`, 0) > 0 THEN 1 ELSE 0 END), 0) AS h
              FROM tb_member_item_bag
            ");
            $bag_sum = (int)($bagRow['s'] ?? 0);
            $bag_holders = (int)($bagRow['h'] ?? 0);
        }

        $items[] = [
            'label' => $label,
            'sname' => $sname,
            'col' => $col,
            'src_sum' => $src_sum,
            'src_holders' => $src_holders,
            'bag_sum' => $bag_sum,
            'bag_holders' => $bag_holders,
            'top' => $top,
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'members' => $members,
    ];
}

/** bag 직접 가산 (item_bag_add 폴백) */
function eunchong_suho_migrate_bag_가산(int $midx, string $nick, string $sname, int $qty): array {
    if (function_exists('item_bag_ensure_column')) {
        item_bag_ensure_column($sname);
    }
    if (function_exists('item_bag_add')) {
        $add = item_bag_add($midx, $nick, $sname, $qty);
        if (!empty($add['ok'])) {
            return $add;
        }
        // tracked 실패 시 직접 UPDATE
        if (strpos((string)($add['msg'] ?? ''), '등록되지 않은') === false) {
            return $add;
        }
    }

    if (function_exists('item_bag_ensure_member')) {
        item_bag_ensure_member($midx, $nick);
    } else {
        $nick_esc = addslashes($nick);
        @db_query("
          INSERT IGNORE INTO tb_member_item_bag (midx, nick, migrated_at)
          VALUES ({$midx}, '{$nick_esc}', NOW())
        ");
    }
    $col = str_replace('`', '``', $sname);
    $ok = @db_query("
      UPDATE tb_member_item_bag
      SET `{$col}` = IFNULL(`{$col}`, 0) + {$qty}
      WHERE midx = {$midx}
      LIMIT 1
    ");
    if (!$ok) {
        return ['ok' => false, 'msg' => 'bag UPDATE 실패: ' . eunchong_suho_migrate_db_error()];
    }
    return ['ok' => true];
}

/**
 * 컬럼 수량 → bag 가산 후 컬럼 0
 * @return array{ok:bool,msg:string,moved:array}
 */
function eunchong_suho_migrate_실행(bool $clearSource = true): array {
    $스키마 = eunchong_suho_migrate_스키마보장();
    if (empty($스키마['ok'])) {
        return ['ok' => false, 'msg' => $스키마['msg'] ?? '스키마 실패', 'moved' => []];
    }

    $moved = [];
    $lines = [(string)($스키마['msg'] ?? '')];

    foreach (eunchong_suho_migrate_대상목록() as $t) {
        $sname = $t['sname'];
        $col = $t['col'];
        $label = $t['label'];
        if (!eunchong_suho_migrate_member컬럼존재($col)) {
            $moved[$sname] = ['holders' => 0, 'qty' => 0, 'skipped' => true];
            $lines[] = "{$label}: tb_member.{$col} 없음 → 스킵";
            continue;
        }

        $holders = 0;
        $qtyTotal = 0;
        $rs = @db_query("
          SELECT idx, name, IFNULL(`{$col}`, 0) AS qty
          FROM tb_member
          WHERE IFNULL(status, 0) <> 1 AND IFNULL(`{$col}`, 0) > 0
        ");
        if (!$rs) {
            return ['ok' => false, 'msg' => "{$label} 조회 실패", 'moved' => $moved];
        }

        while ($row = mysqli_fetch_assoc($rs)) {
            $midx = (int)($row['idx'] ?? 0);
            $nick = trim((string)($row['name'] ?? ''));
            $qty = (int)($row['qty'] ?? 0);
            if ($midx < 1 || $nick === '' || $qty < 1) {
                continue;
            }

            $add = eunchong_suho_migrate_bag_가산($midx, $nick, $sname, $qty);
            if (empty($add['ok'])) {
                return [
                    'ok' => false,
                    'msg' => "{$label} bag 가산 실패 ({$nick}): " . ($add['msg'] ?? ''),
                    'moved' => $moved,
                ];
            }

            if ($clearSource) {
                $nick_esc = addslashes($nick);
                @db_query("UPDATE tb_member SET `{$col}` = 0 WHERE idx = {$midx} AND name = '{$nick_esc}' LIMIT 1");
            }

            $holders++;
            $qtyTotal += $qty;
        }

        $moved[$sname] = [
            'holders' => $holders,
            'qty' => $qtyTotal,
            'cleared' => $clearSource,
        ];
        $clearTxt = $clearSource ? '컬럼 0 처리' : '컬럼 유지(복사)';
        $lines[] = "{$label} → bag.{$sname}: {$holders}명 · {$qtyTotal}개 ({$clearTxt})";
    }

    return [
        'ok' => true,
        'msg' => implode("\n", array_filter($lines)),
        'moved' => $moved,
    ];
}

/**
 * 이전 후: 원본 합=0 · bag 합 표시
 * @return array{ok:bool,checks:list}
 */
function eunchong_suho_migrate_검증(): array {
    $미리 = eunchong_suho_migrate_미리보기();
    $checks = [];
    $allOk = true;
    foreach ($미리['items'] as $it) {
        $srcOk = ((int)$it['src_sum'] === 0);
        $match = $srcOk; // 이동 완료 기준: 원본 소진
        if (!$match) {
            $allOk = false;
        }
        $checks[] = [
            'label' => $it['label'],
            'sname' => $it['sname'],
            'src' => (int)$it['src_sum'],
            'bag' => (int)$it['bag_sum'],
            'src_holders' => (int)$it['src_holders'],
            'bag_holders' => (int)$it['bag_holders'],
            'match' => $match,
        ];
    }
    return ['ok' => $allOk, 'checks' => $checks];
}
