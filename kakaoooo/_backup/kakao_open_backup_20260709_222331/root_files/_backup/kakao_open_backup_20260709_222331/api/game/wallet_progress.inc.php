<?php
/**
 * 지갑 — 지목·강일·일방 진행 표시 · 연장
 */

if (!function_exists('wallet_progress_boot')) {
    function wallet_progress_boot() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!function_exists('진행문자열_닉목록')) {
            if (function_exists('wallet_odd_even_includes')) {
                wallet_odd_even_includes();
            } else {
                include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
            }
        }
    }
}

if (!function_exists('wallet_progress_resolve_nick')) {
    function wallet_progress_resolve_nick($nick) {
        $nick = trim((string)$nick);
        if ($nick === '') {
            return '';
        }
        if (function_exists('getTwoCharNick')) {
            $parsed = getTwoCharNick($nick);
            if ($parsed !== '') {
                return $parsed;
            }
        }
        return $nick;
    }
}

if (!function_exists('wallet_progress_participants')) {
    /** @return string[] */
    function wallet_progress_participants($진행) {
        $진행 = trim((string)$진행);
        if ($진행 === '') {
            return [];
        }
        if (function_exists('강일_진행_참가자목록')) {
            $list = 강일_진행_참가자목록($진행);
            if (!empty($list)) {
                return $list;
            }
        }
        if (function_exists('진행문자열_닉목록')) {
            return 진행문자열_닉목록($진행);
        }
        return [];
    }
}

if (!function_exists('wallet_progress_nick_in_list')) {
    function wallet_progress_nick_in_list($nick, array $participants) {
        $nick = wallet_progress_resolve_nick($nick);
        if ($nick === '') {
            return false;
        }
        foreach ($participants as $p) {
            $p = wallet_progress_resolve_nick($p);
            if ($p !== '' && $p === $nick) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('wallet_progress_row_active')) {
    function wallet_progress_row_active(array $row) {
        $end = strtotime((string)($row['enddate'] ?? ''));
        return $end > time();
    }
}

if (!function_exists('wallet_progress_find_for_nick')) {
    /** @return array<string,mixed>|null */
    function wallet_progress_find_for_nick($nick, $status) {
        wallet_progress_boot();
        $nick = wallet_progress_resolve_nick($nick);
        if ($nick === '') {
            return null;
        }

        if ($status === '강일' && function_exists('강일_진행중_닉조회')) {
            $row = 강일_진행중_닉조회($nick);
            if (is_array($row) && !empty($row['idx']) && wallet_progress_row_active($row)) {
                return $row;
            }
            return null;
        }

        $status_esc = addslashes((string)$status);
        $nick_esc = addslashes($nick);
        $rs = db_query("
            SELECT idx, nick, enddate, regdate
            FROM tb_progress
            WHERE status = '{$status_esc}'
              AND nick LIKE '%{$nick_esc}%'
            ORDER BY enddate ASC
        ");
        if (!$rs) {
            return null;
        }
        while ($row = db_fetch($rs)) {
            if (!wallet_progress_row_active($row)) {
                continue;
            }
            $participants = wallet_progress_participants($row['nick'] ?? '');
            if (wallet_progress_nick_in_list($nick, $participants)) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('wallet_progress_item_count')) {
    function wallet_progress_item_count($nick, $itemname) {
        $nick_esc = addslashes(wallet_progress_resolve_nick($nick));
        $item_esc = addslashes(trim((string)$itemname));
        if ($nick_esc === '' || $item_esc === '') {
            return 0;
        }
        $row = db_select("
            SELECT COUNT(*) AS cnt
            FROM tb_member_item
            WHERE nick = '{$nick_esc}'
              AND itemname = '{$item_esc}'
              AND status = 0
        ");
        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('wallet_progress_type_defs')) {
    /** @return array<string,array{label:string,extend_hours:int,item:string}> */
    function wallet_progress_type_defs() {
        return [
            '지목' => ['label' => '지목', 'extend_hours' => 6, 'item' => '지목'],
            '강일' => ['label' => '강일', 'extend_hours' => 12, 'item' => '강일'],
            '일방' => ['label' => '일방', 'extend_hours' => 0, 'item' => ''],
        ];
    }
}

if (!function_exists('wallet_progress_payload')) {
    /** @return array<string,mixed> */
    function wallet_progress_payload($nick) {
        wallet_progress_boot();
        $nick = wallet_progress_resolve_nick($nick);
        $active_type = null;
        $types = [];
        $extend_target = null;

        foreach (wallet_progress_type_defs() as $status => $meta) {
            $row = wallet_progress_find_for_nick($nick, $status);
            $is_active = is_array($row) && !empty($row['idx']);
            if ($is_active && $active_type === null) {
                $active_type = $status;
            }
            $end_ts = $is_active ? strtotime((string)$row['enddate']) : 0;
            $left_sec = ($is_active && $end_ts) ? max(0, $end_ts - time()) : 0;
            $item_cnt = $meta['item'] !== '' ? wallet_progress_item_count($nick, $meta['item']) : 0;
            $can_extend = $is_active && (int)$meta['extend_hours'] > 0 && $item_cnt > 0;

            if ($can_extend && $extend_target === null) {
                $extend_target = [
                    'type' => $status,
                    'hours' => (int)$meta['extend_hours'],
                    'label' => (int)$meta['extend_hours'] . '시간 연장하기',
                    'item' => $meta['item'],
                    'item_count' => $item_cnt,
                ];
            }

            $types[] = [
                'type' => $status,
                'label' => $meta['label'],
                'active' => $is_active,
                'enddate' => $is_active ? (string)$row['enddate'] : '',
                'enddate_fmt' => ($is_active && $end_ts) ? date('m-d H:i', $end_ts) : '',
                'left_sec' => $left_sec,
                'extend_hours' => (int)$meta['extend_hours'],
                'extend_label' => ($is_active && (int)$meta['extend_hours'] > 0)
                    ? (int)$meta['extend_hours'] . '시간 연장하기'
                    : '',
                'item_count' => $item_cnt,
                'can_extend' => $can_extend,
                'can_cancel' => $is_active && $status === '강일',
                'nick_display' => $is_active ? trim((string)$row['nick']) : '',
            ];
        }

        return [
            'active_type' => $active_type,
            'types' => $types,
            'can_extend' => $extend_target !== null,
            'extend_label' => $extend_target ? (string)$extend_target['label'] : '',
            'extend_type' => $extend_target ? (string)$extend_target['type'] : '',
            'extend_hours' => $extend_target ? (int)$extend_target['hours'] : 0,
            'extend_item_count' => $extend_target ? (int)$extend_target['item_count'] : 0,
        ];
    }
}

if (!function_exists('wallet_progress_extend_execute')) {
    /** @return array<string,mixed> */
    function wallet_progress_extend_execute($nick, $type = '') {
        wallet_progress_boot();
        $nick = wallet_progress_resolve_nick($nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $extend_type = trim((string)$type);
        if ($extend_type === '') {
            $payload = wallet_progress_payload($nick);
            $extend_type = (string)($payload['extend_type'] ?? '');
            if ($extend_type === '') {
                foreach (['지목', '강일'] as $try) {
                    $row = wallet_progress_find_for_nick($nick, $try);
                    if ($row) {
                        $extend_type = $try;
                        break;
                    }
                }
            }
        }
        if (!in_array($extend_type, ['지목', '강일'], true)) {
            return ['ok' => false, 'data' => '연장할 수 있는 지목·강일을 선택해주세요.'];
        }

        $defs = wallet_progress_type_defs();
        if (!isset($defs[$extend_type]) || (int)$defs[$extend_type]['extend_hours'] < 1) {
            return ['ok' => false, 'data' => '이 진행은 지갑에서 연장할 수 없어요.'];
        }

        $row = wallet_progress_find_for_nick($nick, $extend_type);
        if (!$row || empty($row['idx'])) {
            return ['ok' => false, 'data' => '진행 중인 ' . $extend_type . '이 없어요.'];
        }

        $item = (string)$defs[$extend_type]['item'];
        $hours = (int)$defs[$extend_type]['extend_hours'];
        $item_cnt = wallet_progress_item_count($nick, $item);
        if ($item_cnt < 1) {
            return ['ok' => false, 'data' => "{$item} 아이템이 없어요. (보유 0개)"];
        }

        $nick_esc = addslashes($nick);
        db_query("
            UPDATE tb_member_item
            SET status = 1, usedate = NOW()
            WHERE nick = '{$nick_esc}'
              AND itemname = '{$item}'
              AND status = 0
            ORDER BY idx ASC
            LIMIT 1
        ");
        if (function_exists('아이템사용_시세하락')) {
            아이템사용_시세하락($item, 1);
        }

        $끝나는날 = date('Y-m-d H:i', strtotime((string)$row['enddate'] . " +{$hours} hours"));
        $idx = (int)$row['idx'];
        db_query("UPDATE tb_progress SET enddate = '{$끝나는날}' WHERE idx = {$idx} LIMIT 1");

        $진행 = trim((string)($row['nick'] ?? ''));
        return [
            'ok' => true,
            'data' => "{$extend_type}\n{$진행} {$hours}시간 연장!\n{$끝나는날} 까지",
            'progress' => wallet_progress_payload($nick),
        ];
    }
}

if (!function_exists('wallet_progress_cancel_execute')) {
    /** @return array<string,mixed> */
    function wallet_progress_cancel_execute($nick) {
        wallet_progress_boot();
        $nick = wallet_progress_resolve_nick($nick);
        if ($nick === '') {
            return ['ok' => false, 'data' => '회원 정보를 확인할 수 없습니다.'];
        }

        $대상 = function_exists('강일_진행중_닉조회')
            ? 강일_진행중_닉조회($nick)
            : wallet_progress_find_for_nick($nick, '강일');
        if (!is_array($대상) || empty($대상['idx']) || !wallet_progress_row_active($대상)) {
            return ['ok' => false, 'data' => '진행 중인 강일이 없어요.'];
        }

        $진행 = trim((string)($대상['nick'] ?? ''));
        $이름들 = function_exists('강일_진행_참가자목록')
            ? 강일_진행_참가자목록($진행)
            : wallet_progress_participants($진행);

        if (function_exists('강일취소_타일_등록')) {
            강일취소_타일_등록($이름들);
        }
        if (function_exists('강일기록_등록')) {
            강일기록_등록($대상, 'cancel');
        }

        $idx = (int)$대상['idx'];
        db_query("DELETE FROM tb_progress WHERE idx = {$idx} LIMIT 1");

        $msg = "강일취소\n{$진행} 취소완료!";
        if (function_exists('wallet_본방알림_등록')) {
            wallet_본방알림_등록($msg, $nick);
        } elseif (function_exists('본방알림_등록')) {
            본방알림_등록($msg, 'wallet_gangil_cancel_' . $nick);
        }

        return [
            'ok' => true,
            'data' => $msg,
            'progress' => wallet_progress_payload($nick),
        ];
    }
}
