<?php
/**
 * 꼬벙기념주화 — 가방 표시: 제1회🌶️꼬벙기념주화
 * 지급 대상: 민호 · 우서 · 다오 · 여름 (각 1개, 미보유 시만)
 */

require_once __DIR__ . '/item_bag.inc.php';

if (!defined('BAG_KKOBUNG_COIN_ITEM')) {
    define('BAG_KKOBUNG_COIN_ITEM', '꼬벙기념주화');
}
if (!defined('BAG_KKOBUNG_COIN_LABEL')) {
    define('BAG_KKOBUNG_COIN_LABEL', '제1회🌶️꼬벙기념주화');
}

if (!function_exists('꼬벙기념주화_지급대상')) {
    /** @return list<string> */
    function 꼬벙기념주화_지급대상(): array {
        return ['민호', '우서', '다오', '여름'];
    }
}

if (!function_exists('아이템_가방_표시명')) {
    /** 가방·채팅 표시용 이름 (이모지 등) */
    function 아이템_가방_표시명(string $sname): string {
        $sname = trim($sname);
        if ($sname === '') {
            return '';
        }
        if (
            $sname === BAG_KKOBUNG_COIN_ITEM
            || $sname === '🌶️꼬벙기념주화'
            || $sname === '제1회🌶️꼬벙기념주화'
            || $sname === BAG_KKOBUNG_COIN_LABEL
        ) {
            return BAG_KKOBUNG_COIN_LABEL;
        }
        return $sname;
    }
}

if (!function_exists('꼬벙기념주화_tb_item_보장')) {
    function 꼬벙기념주화_tb_item_보장(): void {
        $esc = addslashes(BAG_KKOBUNG_COIN_ITEM);
        $row = @db_select("SELECT idx FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
        if (!empty($row['idx'])) {
            return;
        }
        $ok = @db_query("
          INSERT INTO tb_item
          SET itemname = '{$esc}',
              sname = '{$esc}',
              buy = 0,
              sell = 0,
              percent = 0,
              buystatus = '1',
              status = '1',
              randum = '0',
              sort = 920
        ");
        if (!$ok) {
            @db_query("
              INSERT INTO tb_item (itemname, sname, buy, sell, percent, buystatus, status, randum, sort)
              VALUES ('{$esc}', '{$esc}', 0, 0, 0, '1', '1', '0', 920)
            ");
        }
        if (!$ok) {
            @db_query("INSERT INTO tb_item SET sname = '{$esc}', buy = 0, sell = 0, percent = 0, buystatus = 1, sort = 920");
        }
        if (function_exists('item_bag_snames_flush')) {
            item_bag_snames_flush();
        }
    }
}

if (!function_exists('꼬벙기념주화_스키마보장')) {
    function 꼬벙기념주화_스키마보장(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!function_exists('db_query')) {
            return;
        }
        꼬벙기념주화_tb_item_보장();
        if (function_exists('item_bag_ensure_column')) {
            item_bag_ensure_column(BAG_KKOBUNG_COIN_ITEM);
        }
    }
}

if (!function_exists('꼬벙기념주화_지정지급')) {
    /**
     * 대상 닉에게 1개씩 지급 (이미 1개 이상이면 스킵)
     * @return array{ok:bool,granted:list<string>,skipped:list<string>,missing:list<string>}
     */
    function 꼬벙기념주화_지정지급(): array {
        꼬벙기념주화_스키마보장();
        $granted = [];
        $skipped = [];
        $missing = [];
        foreach (꼬벙기념주화_지급대상() as $nick) {
            $nick = trim((string)$nick);
            if ($nick === '') {
                continue;
            }
            $esc = addslashes($nick);
            $mem = @db_select("SELECT idx, name FROM tb_member WHERE name = '{$esc}' AND status = 0 LIMIT 1");
            if (empty($mem['idx'])) {
                $missing[] = $nick;
                continue;
            }
            $midx = (int)$mem['idx'];
            $name = trim((string)($mem['name'] ?? $nick));
            $have = function_exists('item_bag_qty')
                ? item_bag_qty($midx, BAG_KKOBUNG_COIN_ITEM)
                : 0;
            if ($have >= 1) {
                $skipped[] = $name;
                continue;
            }
            if (!function_exists('item_bag_add')) {
                $missing[] = $name;
                continue;
            }
            $r = item_bag_add($midx, $name, BAG_KKOBUNG_COIN_ITEM, 1);
            if (!empty($r['ok'])) {
                $granted[] = $name;
            } else {
                $missing[] = $name;
            }
        }
        return [
            'ok' => true,
            'granted' => $granted,
            'skipped' => $skipped,
            'missing' => $missing,
        ];
    }
}

if (!function_exists('꼬벙기념주화_부트')) {
    /** 프로세스당 1회: 스키마 보장 · 지정 지급(완료 플래그 후 스킵) */
    function 꼬벙기념주화_부트(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!function_exists('db_query')) {
            return;
        }
        꼬벙기념주화_스키마보장();

        $marker = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'kakao_kkobung_coin_granted.flag';
        if (is_file($marker)) {
            return;
        }
        $결과 = 꼬벙기념주화_지정지급();
        $대상수 = count(꼬벙기념주화_지급대상());
        $완료수 = count($결과['granted'] ?? []) + count($결과['skipped'] ?? []);
        if ($완료수 >= $대상수 && empty($결과['missing'])) {
            @touch($marker);
        }
    }
}

꼬벙기념주화_부트();
