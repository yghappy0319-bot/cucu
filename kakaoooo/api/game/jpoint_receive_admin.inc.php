<?php
/**
 * 민생지원냥(jpoint) 일괄 수령 — 임시 관리 웹
 */

require_once __DIR__ . '/mining_config.inc.php';

if (!function_exists('jpoint_receive_admin_bootstrap')) {
    function jpoint_receive_admin_bootstrap(): void {
        if (!function_exists('db_query')) {
            $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            if ($root !== '' && is_file($root . '/lib/_function.php')) {
                include_once $root . '/lib/_function.php';
            }
            if (!function_exists('db_query') && is_file(dirname(__DIR__, 2) . '/lib/_function.php')) {
                include_once dirname(__DIR__, 2) . '/lib/_function.php';
            }
        }
        if (function_exists('db_query')) {
            jpoint_receive_admin_ensure_jpoint_column();
            if (function_exists('mining_data_ensure_table')) {
                require_once __DIR__ . '/mining_storage.inc.php';
                mining_data_ensure_table();
            }
        }
    }
}

if (!function_exists('jpoint_receive_admin_db_link')) {
    function jpoint_receive_admin_db_link() {
        global $conn;
        return (isset($conn) && $conn instanceof mysqli) ? $conn : null;
    }
}

if (!function_exists('jpoint_receive_admin_db_error')) {
    function jpoint_receive_admin_db_error(string $fallback = 'DB 오류'): string {
        $conn = jpoint_receive_admin_db_link();
        if ($conn) {
            $err = mysqli_error($conn);
            if ($err !== '') {
                return $err;
            }
        }
        if (function_exists('mysqli_connect_error')) {
            $boot = mysqli_connect_error();
            if ($boot !== '') {
                return $boot;
            }
        }
        return $fallback;
    }
}

if (!function_exists('jpoint_receive_admin_has_table')) {
    function jpoint_receive_admin_has_table(string $table): bool {
        $table_esc = addslashes($table);
        $rs = db_query("SHOW TABLES LIKE '{$table_esc}'");
        if (!$rs) {
            return false;
        }
        return (bool)db_fetch($rs);
    }
}

if (!function_exists('jpoint_receive_admin_has_column')) {
    function jpoint_receive_admin_has_column(string $table, string $column): bool {
        $table = addslashes($table);
        $column = addslashes($column);
        $row = db_select("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return !empty($row['Field']);
    }
}

if (!function_exists('jpoint_receive_admin_ensure_jpoint_column')) {
    function jpoint_receive_admin_ensure_jpoint_column(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;
        if (jpoint_receive_admin_has_column('tb_member', 'jpoint')) {
            return;
        }
        db_query("ALTER TABLE tb_member ADD COLUMN `jpoint` INT NOT NULL DEFAULT 0 COMMENT '민생지원냥'");
    }
}

if (!function_exists('jpoint_receive_admin_fmt_mining')) {
    function jpoint_receive_admin_fmt_mining($n): string {
        $s = sprintf('%.10f', mining_pending_round($n));
        $s = rtrim(rtrim($s, '0'), '.');
        return ($s === '' || $s === '-0') ? '0' : $s;
    }
}

if (!function_exists('jpoint_receive_admin_mining_amount')) {
    function jpoint_receive_admin_mining_amount(array $row): float {
        $stored = (float)($row['mining_pending'] ?? 0);
        $sync_at = $row['mining_sync_at'] ?? null;
        if (($sync_at === null || $sync_at === '' || $sync_at === '0000-00-00 00:00:00') && $stored <= 0) {
            return 0.0;
        }
        if (!function_exists('mining_sync_display_amount')) {
            require_once __DIR__ . '/mining_sync.inc.php';
        }
        return mining_sync_display_amount([
            'mining_tool' => (int)($row['mining_tool'] ?? 0),
            'mining_pending' => $stored,
            'mining_sync_at' => $sync_at,
        ]);
    }
}

if (!function_exists('jpoint_receive_admin_build_scan_sql')) {
    function jpoint_receive_admin_build_scan_sql(): ?string {
        $has_jpoint = jpoint_receive_admin_has_column('tb_member', 'jpoint');
        $has_mining_tbl = jpoint_receive_admin_has_table('tb_member_mining');
        $has_legacy_tool = jpoint_receive_admin_has_column('tb_member', 'mining_tool');
        $has_legacy_pending = jpoint_receive_admin_has_column('tb_member', 'mining_pending');

        $select = ['m.name', 'IFNULL(m.point, 0) AS point'];
        $where = [];

        if ($has_jpoint) {
            $select[] = 'IFNULL(m.jpoint, 0) AS jpoint';
            $where[] = 'IFNULL(m.jpoint, 0) > 0';
        } else {
            $select[] = '0 AS jpoint';
        }

        if ($has_mining_tbl) {
            $select[] = 'IFNULL(mm.mining_tool, 0) AS mining_tool';
            $select[] = 'IFNULL(mm.mining_pending, 0) AS mining_pending';
            $select[] = 'mm.mining_sync_at';
            $from = 'FROM tb_member m LEFT JOIN tb_member_mining mm ON mm.nick = m.name';
            $where[] = 'IFNULL(mm.mining_pending, 0) > 0';
            $where[] = "(mm.mining_sync_at IS NOT NULL AND mm.mining_sync_at != '0000-00-00 00:00:00')";
        } elseif ($has_legacy_pending || $has_legacy_tool) {
            $select[] = 'IFNULL(m.mining_tool, 0) AS mining_tool';
            $select[] = 'IFNULL(m.mining_pending, 0) AS mining_pending';
            $select[] = 'm.mining_sync_at';
            $from = 'FROM tb_member m';
            if ($has_legacy_pending) {
                $where[] = 'IFNULL(m.mining_pending, 0) > 0';
            }
            $where[] = "(m.mining_sync_at IS NOT NULL AND m.mining_sync_at != '0000-00-00 00:00:00')";
        } else {
            $select[] = '0 AS mining_tool';
            $select[] = '0 AS mining_pending';
            $select[] = 'NULL AS mining_sync_at';
            $from = 'FROM tb_member m';
        }

        if (!$has_jpoint && !$has_mining_tbl && !$has_legacy_pending && !$has_legacy_tool) {
            return null;
        }
        if (empty($where)) {
            return null;
        }

        return 'SELECT ' . implode(', ', $select) . ' '
            . $from . ' WHERE (' . implode(' OR ', $where) . ') '
            . 'ORDER BY jpoint DESC, m.name ASC';
    }
}

if (!function_exists('jpoint_receive_admin_scan')) {
    /** @return array{rows:list<array>,stats:array,error:string} */
    function jpoint_receive_admin_scan(): array {
        jpoint_receive_admin_bootstrap();

        if (!function_exists('db_query')) {
            return [
                'rows' => [],
                'stats' => jpoint_receive_admin_empty_stats(),
                'error' => 'DB 함수를 불러오지 못했습니다. lib/_function.php 경로를 확인해주세요.',
            ];
        }
        if (!jpoint_receive_admin_db_link()) {
            return [
                'rows' => [],
                'stats' => jpoint_receive_admin_empty_stats(),
                'error' => 'DB 연결 실패: ' . jpoint_receive_admin_db_error('mysqli 연결 없음'),
            ];
        }

        $sql = jpoint_receive_admin_build_scan_sql();
        if ($sql === null) {
            return [
                'rows' => [],
                'stats' => jpoint_receive_admin_empty_stats(),
                'error' => 'jpoint·채굴 관련 컬럼/테이블을 찾을 수 없습니다.',
            ];
        }

        $rs = db_query($sql);
        if (!$rs) {
            return [
                'rows' => [],
                'stats' => jpoint_receive_admin_empty_stats(),
                'error' => '조회 실패: ' . jpoint_receive_admin_db_error('SQL 실행 실패'),
            ];
        }

        $rows = [];
        $total_jpoint = 0;
        $total_mining = 0.0;
        $receive_count = 0;

        while ($row = db_fetch($rs)) {
            $jpoint = (int)($row['jpoint'] ?? 0);
            $mining_amt = jpoint_receive_admin_mining_amount($row);
            if ($jpoint <= 0 && $mining_amt + 1e-12 < 0.0000001) {
                continue;
            }
            $can_receive = $jpoint > 0;
            if ($can_receive) {
                $receive_count++;
                $total_jpoint += $jpoint;
            }
            $total_mining += $mining_amt;
            $rows[] = [
                'nick' => (string)$row['name'],
                'jpoint' => $jpoint,
                'jpoint_fmt' => number_format($jpoint),
                'can_receive' => $can_receive,
                'point' => (int)($row['point'] ?? 0),
                'point_fmt' => number_format((int)($row['point'] ?? 0)),
                'mining_pending' => $mining_amt,
                'mining_pending_fmt' => jpoint_receive_admin_fmt_mining($mining_amt),
            ];
        }

        usort($rows, static function ($a, $b) {
            if ($a['can_receive'] !== $b['can_receive']) {
                return $a['can_receive'] ? -1 : 1;
            }
            if ($a['jpoint'] !== $b['jpoint']) {
                return $b['jpoint'] <=> $a['jpoint'];
            }
            if ($a['mining_pending'] !== $b['mining_pending']) {
                return $b['mining_pending'] <=> $a['mining_pending'];
            }
            return strcmp($a['nick'], $b['nick']);
        });

        return [
            'rows' => $rows,
            'stats' => [
                'count' => $receive_count,
                'list_count' => count($rows),
                'total_jpoint' => $total_jpoint,
                'total_jpoint_fmt' => number_format($total_jpoint),
                'total_mining' => $total_mining,
                'total_mining_fmt' => jpoint_receive_admin_fmt_mining($total_mining),
            ],
            'error' => '',
        ];
    }
}

if (!function_exists('jpoint_receive_admin_empty_stats')) {
    function jpoint_receive_admin_empty_stats(): array {
        return [
            'count' => 0,
            'list_count' => 0,
            'total_jpoint' => 0,
            'total_jpoint_fmt' => '0',
            'total_mining' => 0.0,
            'total_mining_fmt' => '0',
        ];
    }
}

if (!function_exists('jpoint_receive_admin_one')) {
    function jpoint_receive_admin_one(string $nick): array {
        jpoint_receive_admin_bootstrap();
        if (!jpoint_receive_admin_has_column('tb_member', 'jpoint')) {
            return ['ok' => false, 'msg' => 'jpoint 컬럼이 없습니다.'];
        }
        $nick = trim($nick);
        if ($nick === '') {
            return ['ok' => false, 'msg' => '닉네임이 비어 있어요.'];
        }
        $nick_esc = addslashes($nick);
        $row = db_select("
            SELECT name, IFNULL(jpoint, 0) AS jpoint
            FROM tb_member
            WHERE name = '{$nick_esc}'
            LIMIT 1
        ");
        if (empty($row['name'])) {
            return ['ok' => false, 'msg' => '회원을 찾을 수 없어요.'];
        }
        $민생 = (int)($row['jpoint'] ?? 0);
        if ($민생 <= 0) {
            return ['ok' => false, 'msg' => '수령할 민생지원냥이 없어요.'];
        }
        db_query("
            UPDATE tb_member
            SET point = IFNULL(point, 0) + {$민생},
                jpoint = 0
            WHERE name = '{$nick_esc}'
            LIMIT 1
        ");
        return [
            'ok' => true,
            'msg' => $nick . ' — 민생지원냥 ' . number_format($민생) . '냥을 게임냥으로 옮겼어요.',
            'nick' => $nick,
            'jpoint' => $민생,
        ];
    }
}

if (!function_exists('jpoint_receive_admin_bulk')) {
    function jpoint_receive_admin_bulk(): array {
        if (!jpoint_receive_admin_has_column('tb_member', 'jpoint')) {
            return ['ok' => false, 'msg' => 'jpoint 컬럼이 없습니다.'];
        }
        $scan = jpoint_receive_admin_scan();
        if (($scan['error'] ?? '') !== '') {
            return ['ok' => false, 'msg' => $scan['error']];
        }
        $count = (int)($scan['stats']['count'] ?? 0);
        $total = (int)($scan['stats']['total_jpoint'] ?? 0);
        if ($count <= 0 || $total <= 0) {
            return ['ok' => false, 'msg' => '수령할 민생지원냥이 있는 회원이 없어요.', 'count' => 0, 'total_jpoint' => 0];
        }

        db_query("
            UPDATE tb_member
            SET point = IFNULL(point, 0) + IFNULL(jpoint, 0),
                jpoint = 0
            WHERE IFNULL(jpoint, 0) > 0
        ");

        return [
            'ok' => true,
            'msg' => '민생지원냥 ' . number_format($total) . '냥을 ' . $count . '명 게임냥으로 일괄 수령했어요.',
            'count' => $count,
            'total_jpoint' => $total,
        ];
    }
}
