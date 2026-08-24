<?php
/**
 * 색표 고정가 매매 공통
 */

if (!defined('COLOR_MARKET_FEE_RATE')) {
    define('COLOR_MARKET_FEE_RATE', 0.05); // 5% 금고(tax)
}
if (!defined('COLOR_MARKET_MIN_PRICE')) {
    define('COLOR_MARKET_MIN_PRICE', '100'); // 본방냥 최소
}
if (!defined('COLOR_MARKET_COOKIE')) {
    define('COLOR_MARKET_COOKIE', 'wallet_code');
    define('COLOR_MARKET_COOKIE_TTL', 7 * 86400);
}
if (!defined('COLOR_MARKET_ADMIN_NICK')) {
    define('COLOR_MARKET_ADMIN_NICK', '민호');
}
if (!defined('COLOR_MARKET_CLAIM_ITEM')) {
    /** 무주인 색 선점 시 소모 아이템 (가방명) */
    define('COLOR_MARKET_CLAIM_ITEM', '색변');
}
if (!defined('COLOR_MARKET_RESERVED_NUM')) {
    /** 장터 예약색(신입색) — 거래·선점 불가 */
    define('COLOR_MARKET_RESERVED_NUM', 1);
}

function cm_준호인가($nick) {
    return trim((string)$nick) === COLOR_MARKET_ADMIN_NICK;
}

function cm_예약색번호(): int {
    return (int)COLOR_MARKET_RESERVED_NUM;
}

function cm_예약색인가($num): bool {
    return (int)$num === cm_예약색번호();
}

/** 1번을 제외한 2~45가 모두 주인/착용자 있음 */
function cm_장터44만석(): bool {
    $filled = [];
    for ($n = 2; $n <= 45; $n++) {
        $filled[$n] = false;
    }
    $rs = @db_query("SELECT num FROM tb_member WHERE status != 1 AND num BETWEEN 2 AND 45");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $n = (int)($row['num'] ?? 0);
            if ($n >= 2 && $n <= 45) {
                $filled[$n] = true;
            }
        }
    }
    $rs2 = @db_query("SELECT color_num, owner_nick FROM tb_color_market WHERE color_num BETWEEN 2 AND 45");
    if ($rs2) {
        while ($row = db_fetch($rs2)) {
            $n = (int)($row['color_num'] ?? 0);
            $owner = trim((string)($row['owner_nick'] ?? ''));
            if ($n >= 2 && $n <= 45 && $owner !== '') {
                $filled[$n] = true;
            }
        }
    }
    for ($n = 2; $n <= 45; $n++) {
        if (empty($filled[$n])) {
            return false;
        }
    }
    return true;
}

function cm_예약색라벨(): string {
    return cm_장터44만석() ? '신입' : '불가';
}

/** 1번은 항상 선점 상태(불가/신입)로 유지 · 판매·무주인 금지 */
function cm_예약색_장터반영(): void {
    $n = cm_예약색번호();
    $label = addslashes(cm_예약색라벨());
    @db_query("UPDATE tb_color_market
      SET owner_nick = '{$label}', price = 0, listed = 0, updated_at = NOW()
      WHERE color_num = {$n} LIMIT 1");
}

function cm_가방로드() {
    if (function_exists('item_bag_qty_nick')) {
        return;
    }
    $f = $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';
    if (is_file($f)) {
        include_once $f;
    }
}

/** 색변(선점 아이템) 보유 개수 */
function cm_색변_보유($nick): int {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return 0;
    }
    cm_가방로드();
    if (function_exists('item_bag_qty_nick')) {
        return (int)item_bag_qty_nick($nick, COLOR_MARKET_CLAIM_ITEM);
    }
    return 0;
}

/**
 * 색변 주식 시세 (게임냥) — .구매 1개와 동일(10개 미만 1% 추가금 포함)
 * @return array{ok:bool,item:string,unit_price:string,total:string,surcharge:string,unit_disp:string,total_disp:string,msg:string}
 */
function cm_색변_시세정보(): array {
    $item = COLOR_MARKET_CLAIM_ITEM;
    $empty = [
        'ok' => false,
        'item' => $item,
        'unit_price' => '0',
        'total' => '0',
        'surcharge' => '0',
        'unit_disp' => '-',
        'total_disp' => '-',
        'msg' => '시세를 불러올 수 없어요.',
    ];
    if (!function_exists('아이템_구매단가_계산') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    }
    $esc = addslashes($item);
    $row = @db_select("
        SELECT idx, sname, buy, percent, buystatus
        FROM tb_item
        WHERE sname = '{$esc}' AND buystatus = 0
        LIMIT 1
    ");
    if (empty($row['idx'])) {
        $empty['msg'] = "{$item} 아이템을 상점에서 살 수 없어요.";
        return $empty;
    }
    $unit = '0';
    if (function_exists('아이템_구매시세_단가')) {
        $unit = cm_냥(아이템_구매시세_단가($item, $row));
    } elseif (function_exists('아이템_구매단가_계산')) {
        $unit = cm_냥(아이템_구매단가_계산($row));
    }
    if ($unit === '0' && function_exists('아이템_percent_시세여부') && 아이템_percent_시세여부($row)) {
        $empty['msg'] = "현재 총 게임냥 기준 {$item} 시세가 없어요.";
        return $empty;
    }
    $qty = 1;
    $total = $unit;
    $surcharge = '0';
    if ($qty < 10 && $unit !== '0') {
        if (function_exists('bcdiv') && function_exists('bcmul') && function_exists('bcadd')) {
            $surcharge = bcdiv($total, '100', 0);
            if ($surcharge === '0') {
                $surcharge = '1';
            }
            $total = bcadd($total, $surcharge, 0);
        } else {
            $surcharge = (string)max(1, (int)ceil((float)$total * 0.01));
            $total = (string)((int)$total + (int)$surcharge);
        }
    }
    $unit_disp = function_exists('구매가_축약표시')
        ? 구매가_축약표시($unit, '게임냥')
        : (number_format((float)$unit) . '게임냥');
    $total_disp = function_exists('구매가_축약표시')
        ? 구매가_축약표시($total, '게임냥')
        : (number_format((float)$total) . '게임냥');
    return [
        'ok' => true,
        'item' => $item,
        'unit_price' => cm_냥($unit),
        'total' => cm_냥($total),
        'surcharge' => cm_냥($surcharge),
        'unit_disp' => $unit_disp,
        'total_disp' => $total_disp,
        'msg' => '',
    ];
}

/** 색변 1개 상점 구매 (게임냥). 선점 시 자동구매에도 사용 */
function cm_색변_상점구매(array $회원): array {
    $닉 = trim((string)($회원['nick'] ?? ''));
    $claimItem = COLOR_MARKET_CLAIM_ITEM;
    if ($닉 === '') {
        return ['ok' => false, 'msg' => '닉네임을 확인할 수 없어요.'];
    }
    if (!function_exists('아이템_상점구매_실행') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    }
    if (!function_exists('아이템_상점구매_실행')) {
        return ['ok' => false, 'msg' => '상점 구매 기능을 불러올 수 없어요.'];
    }
    $정보 = [
        'idx' => (int)($회원['idx'] ?? 0),
        'name' => $닉,
        'point' => $회원['point'] ?? 0,
        'newpoint' => $회원['newpoint'] ?? 0,
    ];
    if ($정보['idx'] <= 0) {
        $esc = addslashes($닉);
        $row = db_select("SELECT idx, CAST(IFNULL(point,0) AS CHAR) AS point FROM tb_member WHERE name = '{$esc}' LIMIT 1");
        $정보['idx'] = (int)($row['idx'] ?? 0);
        $정보['point'] = $row['point'] ?? 0;
    }
    return 아이템_상점구매_실행($닉, $정보, $claimItem, 1);
}

function cm_palette() {
    return [
        '#FAE100', '#F14F4A', '#EC7F5A', '#EE9830', '#8DBC30',
        '#4AA366', '#4EA698', '#4EA5B2', '#4D9DD8', '#4469A0',
        '#735FA7', '#9158B6', '#D35497', '#D7456A', '#FBF28C',
        '#EA9A93', '#ED9D8E', '#ECB273', '#B3C270', '#7FCF90',
        '#89CAC2', '#96C7CF', '#7EB9DB', '#88A2D4', '#AC9CDA',
        '#B785CB', '#E480B6', '#EB9EAE', '#F8F5D5', '#F8DAD8',
        '#F7D7C7', '#EFDBB7', '#E7F5BA', '#B9EBC2', '#CCEFF1',
        '#C6EEEE', '#C5E1EF', '#CADCEA', '#D8D9ED', '#E0D0EB',
        '#F7D5E6', '#F9E0E5', '#ffffff', '#C3C3C3', '#000000',
    ];
}

function cm_hex($num) {
    $p = cm_palette();
    $n = (int)$num;
    if ($n < 1 || $n > 45) {
        return $p[0];
    }
    return $p[$n - 1];
}

function cm_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cm_냥($v) {
    if (function_exists('냥_정수문자열')) {
        return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ltrim((string)$s, '0') ?: '0';
}

function cm_냥표시($v) {
    $n = cm_냥($v);
    if (function_exists('newpoint표시')) {
        return newpoint표시($n) . '본방냥';
    }
    if (function_exists('냥축약표시')) {
        return 냥축약표시($n, '본방냥');
    }
    return number_format((float)$n) . '본방냥';
}

/** status=0 회원 본방냥(newpoint) 총합 — 판매가 상한 */
function cm_본방냥총합() {
    $row = @db_select("
        SELECT CONCAT('N', CAST(COALESCE(SUM(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))), 0) AS CHAR)) AS total_np
        FROM tb_member
        WHERE status = 0
    ");
    $raw = (string)($row['total_np'] ?? 'N0');
    if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
        $raw = substr($raw, 1);
    }
    if (strpos($raw, '.') !== false) {
        $raw = explode('.', $raw, 2)[0];
    }
    return cm_냥($raw);
}

/** 본방냥 총합 초과 판매가 자동 내림 */
function cm_초과판매_정리() {
    $총합 = cm_본방냥총합();
    if ($총합 === '0') {
        return;
    }
    @db_query("UPDATE tb_color_market SET listed = 0, price = 0
        WHERE listed = 1 AND price > {$총합}");
}

function cm_auth($code = '') {
    foreach (cm_코드후보($code) as $c) {
        $esc = addslashes($c);
        $row = db_select("SELECT idx, name,
            CAST(IFNULL(point,0) AS CHAR) AS point,
            CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint,
            num, status
            FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            continue;
        }
        if ((int)($row['status'] ?? 0) === 1) {
            continue;
        }
        cm_쿠키저장($c);
        $np = $row['newpoint'] ?? 0;
        if (function_exists('newpoint양도가능')) {
            $np = (string)newpoint양도가능($np);
        } else {
            $np = cm_냥((string)(int)floor((float)$np));
        }
        return [
            'code'     => $c,
            'nick'     => trim((string)$row['name']),
            'point'    => cm_냥($row['point'] ?? 0),
            'newpoint' => cm_냥($np),
            'num'      => (int)($row['num'] ?? 0),
            'idx'      => (int)($row['idx'] ?? 0),
        ];
    }
    return null;
}

function cm_비교($a, $b) {
    $a = cm_냥($a);
    $b = cm_냥($b);
    if (function_exists('bccomp')) {
        return bccomp($a, $b, 0);
    }
    if (strlen($a) !== strlen($b)) {
        return strlen($a) <=> strlen($b);
    }
    return $a <=> $b;
}

function cm_수수료($price) {
    $price = cm_냥($price);
    if (function_exists('bcmul') && function_exists('bcdiv')) {
        // floor(price * rate%)  — 5% = *5/100
        $pct = (string)(int)round(COLOR_MARKET_FEE_RATE * 100);
        $fee = bcdiv(bcmul($price, $pct, 0), '100', 0);
        if (cm_비교($fee, $price) >= 0 && cm_비교($price, '0') > 0) {
            $fee = function_exists('bcsub') ? bcsub($price, '1', 0) : '0';
        }
        return cm_냥($fee);
    }
    $fee = (string)(int)floor((float)$price * COLOR_MARKET_FEE_RATE);
    return cm_냥($fee);
}

function cm_테이블보장() {
    static $done = false;
    if ($done) {
        return;
    }
    db_query("CREATE TABLE IF NOT EXISTS `tb_color_market` (
      `color_num` TINYINT UNSIGNED NOT NULL,
      `owner_nick` VARCHAR(50) NOT NULL DEFAULT '',
      `price` DECIMAL(40,0) NOT NULL DEFAULT 0,
      `listed` TINYINT(1) NOT NULL DEFAULT 0,
      `trade_lock` TINYINT(1) NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`color_num`),
      KEY `idx_owner` (`owner_nick`),
      KEY `idx_listed` (`listed`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db_query("CREATE TABLE IF NOT EXISTS `tb_color_trade_log` (
      `idx` INT NOT NULL AUTO_INCREMENT,
      `color_num` TINYINT UNSIGNED NOT NULL,
      `seller_nick` VARCHAR(50) NOT NULL DEFAULT '',
      `buyer_nick` VARCHAR(50) NOT NULL DEFAULT '',
      `price` DECIMAL(40,0) NOT NULL DEFAULT 0,
      `fee` DECIMAL(40,0) NOT NULL DEFAULT 0,
      `action` VARCHAR(20) NOT NULL DEFAULT 'buy',
      `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`idx`),
      KEY `idx_color` (`color_num`),
      KEY `idx_regdate` (`regdate`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 기존 테이블에 trade_lock 컬럼 추가
    $col = db_select("SHOW COLUMNS FROM tb_color_market LIKE 'trade_lock'");
    if (empty($col['Field'])) {
        @db_query("ALTER TABLE tb_color_market ADD COLUMN `trade_lock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `listed`");
    }

    for ($i = 1; $i <= 45; $i++) {
        $exists = db_select("SELECT color_num FROM tb_color_market WHERE color_num = {$i} LIMIT 1");
        if (!empty($exists['color_num'])) {
            continue;
        }
        $owner = '';
        $row = db_select("SELECT name FROM tb_member WHERE num = {$i} AND status != 1 ORDER BY idx ASC LIMIT 1");
        if (!empty($row['name'])) {
            $owner = trim((string)$row['name']);
        }
        $owner_esc = addslashes($owner);
        db_query("INSERT INTO tb_color_market SET color_num = {$i}, owner_nick = '{$owner_esc}', price = 0, listed = 0, trade_lock = 0, updated_at = NOW()");
    }

    // 2026-08-17: 세은 #38 장터 잔존 1회 정리 → 선점 가능
    $seeun38 = @db_select("SELECT idx FROM tb_color_trade_log WHERE action = 'resign_fix' AND color_num = 38 AND seller_nick = '세은' LIMIT 1");
    if (empty($seeun38['idx'])) {
        @db_query("UPDATE tb_member SET num = 0 WHERE name = '세은' AND num = 38 AND status != 1 LIMIT 1");
        @db_query("UPDATE tb_color_market SET owner_nick = '', price = 0, listed = 0, updated_at = NOW()
            WHERE color_num = 38 AND owner_nick = '세은' LIMIT 1");
        db_query("INSERT INTO tb_color_trade_log
            SET color_num = 38, seller_nick = '세은', buyer_nick = '', price = 0, fee = 0, action = 'resign_fix', regdate = NOW()");
    }

    cm_소유자_동기화();
    cm_예약색_장터반영();
    $done = true;
}

/**
 * tb_color_market.owner_nick 을 현재 착용자(tb_member.num)에 즉각 맞춤.
 * - 착용자 있음: owner가 그중 하나면 유지, 아니면 첫 착용자로 교체
 * - 착용자 없음: 고아 owner 제거 + 판매 해제
 */
function cm_소유자_동기화() {
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;

    $wearers = [];
    for ($i = 1; $i <= 45; $i++) {
        $wearers[$i] = [];
    }
    $rs = db_query("SELECT name, num FROM tb_member WHERE status != 1 AND num BETWEEN 1 AND 45 ORDER BY idx ASC");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $n = (int)($row['num'] ?? 0);
            if ($n < 1 || $n > 45) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '' || in_array($name, $wearers[$n], true)) {
                continue;
            }
            $wearers[$n][] = $name;
        }
    }

    $rs2 = db_query("SELECT color_num, owner_nick, listed FROM tb_color_market WHERE color_num BETWEEN 1 AND 45");
    if (!$rs2) {
        return;
    }
    while ($row = db_fetch($rs2)) {
        $n = (int)($row['color_num'] ?? 0);
        if ($n < 1 || $n > 45) {
            continue;
        }
        if (cm_예약색인가($n)) {
            continue;
        }
        $owner = trim((string)($row['owner_nick'] ?? ''));
        $list = $wearers[$n];

        if (!empty($list)) {
            $newOwner = in_array($owner, $list, true) ? $owner : $list[0];
            if ($newOwner === $owner) {
                continue;
            }
            $esc = addslashes($newOwner);
            db_query("UPDATE tb_color_market SET owner_nick = '{$esc}', updated_at = NOW()
                WHERE color_num = {$n} LIMIT 1");
            continue;
        }

        // 착용자 없음
        if ($owner === '') {
            // 무주인(+ 민호 판매가 등록)은 유지
            continue;
        }
        // 고아 닉/판매중 정리
        db_query("UPDATE tb_color_market SET owner_nick = '', price = 0, listed = 0, updated_at = NOW()
            WHERE color_num = {$n} LIMIT 1");
    }
}

function cm_코드후보($preferred = '') {
    $list = [];
    $push = function ($v) use (&$list) {
        $v = trim((string)$v);
        if ($v !== '' && !in_array($v, $list, true)) {
            $list[] = $v;
        }
    };
    $push($preferred);
    $push($_GET['code'] ?? '');
    $push($_POST['code'] ?? '');
    $push($_POST['wallet_code'] ?? '');
    $push($_COOKIE[COLOR_MARKET_COOKIE] ?? '');
    $push($_COOKIE['wallet_code'] ?? '');
    return $list;
}

function cm_쿠키저장($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return false;
    }
    return setcookie(COLOR_MARKET_COOKIE, $code, time() + COLOR_MARKET_COOKIE_TTL, '/', '', false, true);
}

function cm_회원필수($code = '') {
    $회원 = cm_auth($code);
    if (!$회원) {
        cm_json(['ok' => false, 'msg' => '연구실에서 발급받은 코드 링크로 접속해주세요.']);
    }
    return $회원;
}

/** 해당 색을 프로필로 쓰는 닉들 (공커 2명 포함) */
function cm_착용자들($num) {
    $num = (int)$num;
    $list = [];
    if ($num < 1 || $num > 45) {
        return $list;
    }
    $rs = db_query("SELECT name FROM tb_member WHERE status != 1 AND num = {$num} ORDER BY idx ASC");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '' && !in_array($name, $list, true)) {
                $list[] = $name;
            }
        }
    }
    return $list;
}

/** 화면에 보여줄 닉 목록: 착용자 우선, 없으면 소유자 */
function cm_표시닉들($owner_nick, $wearers) {
    $wearers = is_array($wearers) ? $wearers : [];
    $clean = [];
    foreach ($wearers as $w) {
        $w = trim((string)$w);
        if ($w !== '' && !in_array($w, $clean, true)) {
            $clean[] = $w;
        }
    }
    if (!empty($clean)) {
        return $clean;
    }
    $owner = trim((string)$owner_nick);
    return $owner !== '' ? [$owner] : [];
}

/** 공커 포함 — 소유자이거나 같은 색 착용자면 내 색 */
function cm_내색인가($nick, $owner_nick, $wearers = []) {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return false;
    }
    if ($nick === trim((string)$owner_nick)) {
        return true;
    }
    foreach ((array)$wearers as $w) {
        if ($nick === trim((string)$w)) {
            return true;
        }
    }
    return false;
}

function cm_색행($num) {
    $num = (int)$num;
    if ($num < 1 || $num > 45) {
        return null;
    }
    $row = db_select("SELECT color_num, owner_nick, CAST(price AS CHAR) AS price, listed, trade_lock, updated_at
        FROM tb_color_market WHERE color_num = {$num} LIMIT 1");
    if (empty($row['color_num'])) {
        return null;
    }
    $row['color_num'] = (int)$row['color_num'];
    $row['owner_nick'] = trim((string)($row['owner_nick'] ?? ''));
    $row['price'] = cm_냥($row['price'] ?? 0);
    $row['listed'] = (int)($row['listed'] ?? 0);
    $row['trade_lock'] = (int)($row['trade_lock'] ?? 0);
    $row['hex'] = cm_hex($row['color_num']);
    $row['wearers'] = cm_착용자들($num);
    $row['display_nicks'] = cm_표시닉들($row['owner_nick'], $row['wearers']);
    $row['reserved'] = cm_예약색인가($num);
    $row['reserved_label'] = $row['reserved'] ? cm_예약색라벨() : '';
    if ($row['reserved']) {
        $row['owner_nick'] = $row['reserved_label'];
        $row['display_nicks'] = [$row['reserved_label']];
        $row['listed'] = 0;
        $row['price'] = '0';
    }
    return $row;
}

function cm_목록() {
    cm_테이블보장();
    $map = [];
    for ($i = 1; $i <= 45; $i++) {
        $map[$i] = [
            'color_num'     => $i,
            'owner_nick'    => '',
            'price'         => '0',
            'listed'        => 0,
            'trade_lock'    => 0,
            'hex'           => cm_hex($i),
            'wearers'       => [],
            'display_nicks' => [],
        ];
    }
    $rs = db_query("SELECT color_num, owner_nick, CAST(price AS CHAR) AS price, listed, trade_lock FROM tb_color_market");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $n = (int)$row['color_num'];
            if ($n < 1 || $n > 45) {
                continue;
            }
            $map[$n]['owner_nick'] = trim((string)($row['owner_nick'] ?? ''));
            $map[$n]['price'] = cm_냥($row['price'] ?? 0);
            $map[$n]['listed'] = (int)($row['listed'] ?? 0);
            $map[$n]['trade_lock'] = (int)($row['trade_lock'] ?? 0);
        }
    }
    $rs2 = db_query("SELECT name, num FROM tb_member WHERE status != 1 AND num BETWEEN 1 AND 45 ORDER BY idx ASC");
    if ($rs2) {
        while ($row = db_fetch($rs2)) {
            $n = (int)$row['num'];
            if ($n < 1 || $n > 45) {
                continue;
            }
            $name = trim((string)$row['name']);
            if ($name === '' || in_array($name, $map[$n]['wearers'], true)) {
                continue;
            }
            $map[$n]['wearers'][] = $name;
        }
    }
    foreach ($map as $n => $it) {
        $map[$n]['display_nicks'] = cm_표시닉들($it['owner_nick'], $it['wearers']);
        $map[$n]['reserved'] = false;
        $map[$n]['reserved_label'] = '';
    }
    $예약 = cm_예약색번호();
    if (isset($map[$예약])) {
        $라벨 = cm_예약색라벨();
        $map[$예약]['reserved'] = true;
        $map[$예약]['reserved_label'] = $라벨;
        $map[$예약]['owner_nick'] = $라벨;
        $map[$예약]['display_nicks'] = [$라벨];
        $map[$예약]['listed'] = 0;
        $map[$예약]['price'] = '0';
    }
    return array_values($map);
}

function cm_로그($action, $color_num, $seller, $buyer, $price, $fee) {
    $action_esc = addslashes((string)$action);
    $seller_esc = addslashes((string)$seller);
    $buyer_esc = addslashes((string)$buyer);
    $price_sql = cm_냥($price);
    $fee_sql = cm_냥($fee);
    $num = (int)$color_num;
    db_query("INSERT INTO tb_color_trade_log
        SET color_num = {$num}, seller_nick = '{$seller_esc}', buyer_nick = '{$buyer_esc}',
            price = {$price_sql}, fee = {$fee_sql}, action = '{$action_esc}', regdate = NOW()");
}

function cm_최근거래($limit = 12) {
    $limit = max(1, min(50, (int)$limit));
    $rows = [];
    $rs = db_query("SELECT color_num, seller_nick, buyer_nick, CAST(price AS CHAR) AS price, CAST(fee AS CHAR) AS fee, action, regdate
        FROM tb_color_trade_log ORDER BY idx DESC LIMIT {$limit}");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $rows[] = [
                'color_num'   => (int)$row['color_num'],
                'seller_nick' => trim((string)$row['seller_nick']),
                'buyer_nick'  => trim((string)$row['buyer_nick']),
                'price'       => cm_냥($row['price'] ?? 0),
                'price_disp'  => cm_냥표시($row['price'] ?? 0),
                'fee'         => cm_냥($row['fee'] ?? 0),
                'action'      => (string)$row['action'],
                'regdate'     => (string)$row['regdate'],
                'hex'         => cm_hex((int)$row['color_num']),
            ];
        }
    }
    return $rows;
}

function cm_affected() {
    global $conn;
    if ($conn instanceof mysqli) {
        return (int)mysqli_affected_rows($conn);
    }
    return 0;
}
