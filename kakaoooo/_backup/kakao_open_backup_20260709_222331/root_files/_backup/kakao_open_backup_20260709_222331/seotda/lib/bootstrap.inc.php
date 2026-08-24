<?php
/**
 * 섯다 웹 부트스트랩 — api/function.php 등 공용 DB만 로드 (기존 게임 코드 미포함)
 */

require_once __DIR__ . '/config.inc.php';

if (!function_exists('db_select')) {
    $root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
    if (is_file($root . '/lib/_function.php')) {
        include_once $root . '/lib/_function.php';
    }
    if (is_file($root . '/api/function.php')) {
        include_once $root . '/api/function.php';
    }
}

if (!function_exists('seotda_has_online_at_column')) {
    function seotda_has_online_at_column() {
        if (array_key_exists('seotda_has_online_at', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_online_at'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_online_at'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_online_at'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'online_at'");
        $GLOBALS['seotda_has_online_at'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_online_at'];
    }
}

if (!function_exists('seotda_schema_ensure_online_at')) {
    function seotda_schema_ensure_online_at() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }

        if (seotda_has_online_at_column()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN online_at DATETIME DEFAULT NULL COMMENT '섯다 페이지 status heartbeat' AFTER last_result
        ");
        @db_query("ALTER TABLE tb_seotda_room ADD KEY idx_online_at (online_at)");
        @db_query("UPDATE tb_seotda_room SET online_at = NULL");
        unset($GLOBALS['seotda_has_online_at']);
        seotda_has_online_at_column();
    }
}
seotda_schema_ensure_online_at();

if (!function_exists('seotda_has_guest_action_since_column')) {
    function seotda_has_guest_action_since_column() {
        if (array_key_exists('seotda_has_guest_action_since', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_guest_action_since'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_guest_action_since'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_guest_action_since'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'guest_action_since'");
        $GLOBALS['seotda_has_guest_action_since'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_guest_action_since'];
    }
}

if (!function_exists('seotda_schema_ensure_guest_action_since')) {
    function seotda_schema_ensure_guest_action_since() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }

        if (seotda_has_guest_action_since_column()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN guest_action_since DATETIME DEFAULT NULL COMMENT '게스트 선택 대기 시작' AFTER waiting_since
        ");
        unset($GLOBALS['seotda_has_guest_action_since']);
        seotda_has_guest_action_since_column();
    }
}
seotda_schema_ensure_guest_action_since();

if (!function_exists('seotda_has_ready_wait_since_column')) {
    function seotda_has_ready_wait_since_column() {
        if (array_key_exists('seotda_has_ready_wait_since', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_ready_wait_since'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_ready_wait_since'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_ready_wait_since'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'ready_wait_since'");
        $GLOBALS['seotda_has_ready_wait_since'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_ready_wait_since'];
    }
}

if (!function_exists('seotda_schema_ensure_ready_wait_since')) {
    function seotda_schema_ensure_ready_wait_since() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }

        if (seotda_has_ready_wait_since_column()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN ready_wait_since DATETIME DEFAULT NULL COMMENT '한쪽 준비 후 상대 응답 대기' AFTER guest_action_since
        ");
        unset($GLOBALS['seotda_has_ready_wait_since']);
        seotda_has_ready_wait_since_column();
    }
}
seotda_schema_ensure_ready_wait_since();

if (!function_exists('seotda_has_peek_slot_columns')) {
    function seotda_has_peek_slot_columns() {
        if (array_key_exists('seotda_has_peek_slot_columns', $GLOBALS)) {
            return (bool)$GLOBALS['seotda_has_peek_slot_columns'];
        }
        if (!function_exists('db_select')) {
            $GLOBALS['seotda_has_peek_slot_columns'] = false;
            return false;
        }
        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            $GLOBALS['seotda_has_peek_slot_columns'] = false;
            return false;
        }
        $col = @db_select("SHOW COLUMNS FROM tb_seotda_room LIKE 'host_peek_slot'");
        $GLOBALS['seotda_has_peek_slot_columns'] = !empty($col['Field']);
        return $GLOBALS['seotda_has_peek_slot_columns'];
    }
}

if (!function_exists('seotda_schema_ensure_peek_slot_columns')) {
    function seotda_schema_ensure_peek_slot_columns() {
        static $done = false;
        if ($done || !function_exists('db_query') || !function_exists('db_select')) {
            return;
        }
        $done = true;

        $tbl = @db_select("SHOW TABLES LIKE 'tb_seotda_room'");
        if (empty($tbl)) {
            return;
        }

        if (seotda_has_peek_slot_columns()) {
            return;
        }

        @db_query("
            ALTER TABLE tb_seotda_room
            ADD COLUMN host_peek_slot TINYINT UNSIGNED DEFAULT NULL COMMENT '호스트 랜덤 공개 슬롯 1-3' AFTER guest_pick2,
            ADD COLUMN guest_peek_slot TINYINT UNSIGNED DEFAULT NULL COMMENT '게스트 랜덤 공개 슬롯 1-3' AFTER host_peek_slot
        ");
        unset($GLOBALS['seotda_has_peek_slot_columns']);
        seotda_has_peek_slot_columns();
    }
}
seotda_schema_ensure_peek_slot_columns();

require_once __DIR__ . '/emote.inc.php';
seotda_schema_ensure_emote_columns();

require_once __DIR__ . '/bet_pending.inc.php';
seotda_schema_ensure_bet_pending_columns();

require_once __DIR__ . '/fmt.inc.php';
require_once __DIR__ . '/cards.inc.php';
require_once __DIR__ . '/room.inc.php';
require_once __DIR__ . '/solo.inc.php';
require_once __DIR__ . '/pvp.inc.php';
require_once __DIR__ . '/match.inc.php';
require_once __DIR__ . '/card-ui.inc.php';

if (!function_exists('seotda_json')) {
    function seotda_json($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('seotda_auth')) {
    function seotda_auth($code) {
        $code = is_string($code) ? trim($code) : '';
        if ($code === '' || !function_exists('db_select')) {
            return null;
        }
        $esc = addslashes($code);
        $row = db_select("SELECT idx, name, point, level FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('seotda_nick')) {
    function seotda_nick($name) {
        if (function_exists('getTwoCharNick')) {
            $n = getTwoCharNick($name);
            if ($n !== '') {
                return $n;
            }
        }
        return trim((string)$name);
    }
}

if (!function_exists('seotda_member_point')) {
    function seotda_member_point($nick_esc) {
        $row = @db_select("SELECT point FROM tb_member WHERE name = '{$nick_esc}' LIMIT 1");
        return is_array($row) ? (int)($row['point'] ?? 0) : 0;
    }
}

if (!function_exists('seotda_access_allowed')) {
    function seotda_access_allowed($point) {
        return (int)$point >= (int)SEOTDA_MIN_ACCESS;
    }
}

if (!function_exists('seotda_access_denied_msg')) {
    function seotda_access_denied_msg() {
        $min = function_exists('seotda_fmt_game')
            ? seotda_fmt_game((int)SEOTDA_MIN_ACCESS)
            : number_format((int)SEOTDA_MIN_ACCESS);
        return '❌ 게임냥 ' . $min . ' 이상부터 이용할 수 있어요.';
    }
}

if (!function_exists('seotda_parse_bet')) {
    function seotda_parse_bet($raw) {
        if (function_exists('냥_금액_파싱')) {
            return max(0, (int)냥_금액_파싱((string)$raw));
        }
        return max(0, (int)preg_replace('/[^0-9]/', '', (string)$raw));
    }
}
