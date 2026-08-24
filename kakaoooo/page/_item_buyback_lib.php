<?php
/**
 * .구매 시세 기준 — 지정 아이템 일괄 회수 후 게임냥(point) 지급
 * 대상: 강일·수호·지목·프변·제한·색변·닉변·선물
 */

if (!defined('ITEM_BUYBACK_ITEMS')) {
    define('ITEM_BUYBACK_ITEMS', ['강일', '수호', '지목', '프변', '제한', '색변', '닉변', '선물']);
}

function item_buyback_대상목록(): array {
    return ITEM_BUYBACK_ITEMS;
}

/** 일괄회수 단가 산정용 총게임냥 — 실시간 합계(문자열) */
function item_buyback_기준총게임냥(): string {
    if (function_exists('시세기준_실시간합계')) {
        $live = 시세기준_실시간합계();
        return function_exists('냥_정수문자열')
            ? 냥_정수문자열($live['게임냥'] ?? 0)
            : (preg_replace('/[^\d]/', '', (string)($live['게임냥'] ?? '0')) ?: '0');
    }
    if (function_exists('아이템_총게임냥')) {
        return function_exists('냥_정수문자열')
            ? 냥_정수문자열(아이템_총게임냥())
            : (string)아이템_총게임냥();
    }
    return '0';
}

/** BIGINT 상한 — buy 컬럼이 여기 붙어 있으면 오버플로 포화(실제 시세 아님) */
if (!defined('ITEM_BUYBACK_BIGINT_MAX')) {
    define('ITEM_BUYBACK_BIGINT_MAX', '9223372036854775807');
}

/**
 * 아이템별 단가 산출 근거
 * @return array<string, array{unit:string,source:string,percent:string,buy_raw:string,saturated:bool}>
 */
function item_buyback_단가정보(): array {
    $정보 = [];
    // 스냅샷이 BIGINT/922경으로 굳었을 수 있어 — 실시간 합계 우선
    $총게임냥 = item_buyback_기준총게임냥();
    foreach (item_buyback_대상목록() as $name) {
        $esc = addslashes($name);
        // DECIMAL/BIGINT를 float로 받지 않도록 CHAR 캐스팅
        $row = @db_select("
          SELECT sname,
                 CAST(buy AS CHAR) AS buy_raw,
                 CAST(percent AS CHAR) AS percent_raw,
                 buy, percent, buystatus
          FROM tb_item
          WHERE sname = '{$esc}'
          LIMIT 1
        ");
        if (empty($row)) {
            $정보[$name] = ['unit' => '0', 'source' => 'none', 'percent' => '0', 'buy_raw' => '0', 'saturated' => false];
            continue;
        }

        $percentRaw = trim((string)($row['percent_raw'] ?? '0'));
        $buyRaw = function_exists('냥_정수문자열')
            ? 냥_정수문자열($row['buy_raw'] ?? 0)
            : (ltrim(preg_replace('/[^\d]/', '', (string)($row['buy_raw'] ?? '0')), '0') ?: '0');
        $saturated = function_exists('bccomp')
            ? (bccomp($buyRaw, ITEM_BUYBACK_BIGINT_MAX, 0) >= 0)
            : ($buyRaw === ITEM_BUYBACK_BIGINT_MAX);

        if ((float)$percentRaw > 0) {
            $unit = 아이템_구매단가_계산(['percent' => (float)$percentRaw, 'buy' => $buyRaw], $총게임냥);
            $정보[$name] = [
                'unit' => 냥_정수문자열($unit),
                'source' => 'percent',
                'percent' => $percentRaw,
                'buy_raw' => $buyRaw,
                'saturated' => $saturated,
            ];
            continue;
        }

        // percent 미설정 → buy 컬럼. BIGINT 포화값(922경)은 실제 시세가 아니라 오버플로 흔적
        $정보[$name] = [
            'unit' => $saturated ? '0' : $buyRaw,
            'source' => $saturated ? 'buy_saturated' : 'buy',
            'percent' => $percentRaw,
            'buy_raw' => $buyRaw,
            'saturated' => $saturated,
        ];
    }
    return $정보;
}

/** @return array<string, string> sname => 단가(정수문자열) */
function item_buyback_단가맵(): array {
    $맵 = [];
    foreach (item_buyback_단가정보() as $name => $i) {
        $맵[$name] = (string)($i['unit'] ?? '0');
    }
    return $맵;
}

function item_buyback_금액표시($금액, string $단위 = '냥'): string {
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($금액, $단위);
    }
    if (function_exists('구매가_축약표시')) {
        return 구매가_축약표시($금액, $단위);
    }
    return number_format((float)$금액) . $단위;
}

/** bcmath 없이도 자릿수 제한 없는 덧셈 (부호 없는 정수 문자열) */
function item_buyback_문자열합(string $a, string $b): string {
    $a = ltrim($a, '0') ?: '0';
    $b = ltrim($b, '0') ?: '0';
    $i = strlen($a) - 1;
    $j = strlen($b) - 1;
    $carry = 0;
    $out = '';
    while ($i >= 0 || $j >= 0 || $carry > 0) {
        $sum = $carry;
        if ($i >= 0) {
            $sum += (int)$a[$i--];
        }
        if ($j >= 0) {
            $sum += (int)$b[$j--];
        }
        $out = (string)($sum % 10) . $out;
        $carry = intdiv($sum, 10);
    }
    return ltrim($out, '0') ?: '0';
}

/** bcmath 없이도 자릿수 제한 없는 곱셈 (문자열 × 작은 정수) */
function item_buyback_문자열곱(string $a, int $m): string {
    $a = ltrim($a, '0') ?: '0';
    if ($a === '0' || $m < 1) {
        return '0';
    }
    $carry = 0;
    $out = '';
    for ($i = strlen($a) - 1; $i >= 0; $i--) {
        $prod = (int)$a[$i] * $m + $carry;
        $out = (string)($prod % 10) . $out;
        $carry = intdiv($prod, 10);
    }
    while ($carry > 0) {
        $out = (string)($carry % 10) . $out;
        $carry = intdiv($carry, 10);
    }
    return ltrim($out, '0') ?: '0';
}

function item_buyback_곱(string $단가, int $수량): string {
    $수량 = max(0, (int)$수량);
    $단가 = function_exists('냥_정수문자열') ? 냥_정수문자열($단가) : (ltrim(preg_replace('/[^\d]/', '', $단가), '0') ?: '0');
    if ($수량 < 1 || $단가 === '0') {
        return '0';
    }
    if (function_exists('bcmul')) {
        return bcmul($단가, (string)$수량, 0);
    }
    return item_buyback_문자열곱($단가, $수량);
}

function item_buyback_합(string $a, string $b): string {
    $a = function_exists('냥_정수문자열') ? 냥_정수문자열($a) : (ltrim(preg_replace('/[^\d]/', '', $a), '0') ?: '0');
    $b = function_exists('냥_정수문자열') ? 냥_정수문자열($b) : (ltrim(preg_replace('/[^\d]/', '', $b), '0') ?: '0');
    if (function_exists('bcadd')) {
        return bcadd($a, $b, 0);
    }
    return item_buyback_문자열합($a, $b);
}

function item_buyback_키(int $midx, string $item): string {
    return $midx . '|' . $item;
}

/**
 * 가방 보유 스캔 → 라인 목록
 * @return array{ok:bool,msg:string,prices:array,rows:array,stats:array}
 */
function item_buyback_미리보기(): array {
    if (!function_exists('item_bag_qty') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';
    }
    if (function_exists('item_bag_ensure_schema')) {
        item_bag_ensure_schema();
    }

    $items = item_buyback_대상목록();
    $priceInfo = item_buyback_단가정보();
    $prices = [];
    foreach ($priceInfo as $pn => $pi) {
        $prices[$pn] = (string)($pi['unit'] ?? '0');
    }
    $cols = [];
    foreach ($items as $name) {
        if (function_exists('item_bag_ensure_column')) {
            item_bag_ensure_column($name);
        }
        if (function_exists('item_bag_col_exists') && !item_bag_col_exists($name)) {
            continue;
        }
        $cols[] = $name;
    }
    if ($cols === []) {
        return [
            'ok' => false,
            'msg' => '가방 컬럼을 찾을 수 없습니다.',
            'prices' => $prices,
            'price_info' => $priceInfo,
            'bcmath' => function_exists('bcmul'),
            'rows' => [],
            'friends' => [],
            'stats' => ['holders' => 0, 'lines' => 0, 'qty' => 0, 'payout' => '0', 'payout_fmt' => '0냥'],
        ];
    }

    $selectParts = ['b.midx', 'm.name', 'CAST(CAST(IFNULL(m.point,0) AS DECIMAL(65,0)) AS CHAR) AS point'];
    $whereOr = [];
    foreach ($cols as $c) {
        $k = str_replace('`', '``', $c);
        $selectParts[] = "IFNULL(b.`{$k}`, 0) AS `{$k}`";
        $whereOr[] = "IFNULL(b.`{$k}`, 0) > 0";
    }
    $sql = '
      SELECT ' . implode(', ', $selectParts) . '
      FROM tb_member_item_bag b
      INNER JOIN tb_member m ON m.idx = b.midx
      WHERE IFNULL(m.status, 0) = 0
        AND (' . implode(' OR ', $whereOr) . ')
      ORDER BY m.name ASC
    ';
    $rs = @db_query($sql);
    $rows = [];
    $byNick = []; // nick => {midx, nick, qty, pay, lines:[]}
    $총지급 = '0';
    $총수량 = 0;
    $닉셋 = [];
    if ($rs) {
        while ($bag = mysqli_fetch_assoc($rs)) {
            $midx = (int)($bag['midx'] ?? 0);
            $nick = trim((string)($bag['name'] ?? ''));
            if ($midx < 1 || $nick === '') {
                continue;
            }
            foreach ($cols as $item) {
                $qty = (int)($bag[$item] ?? 0);
                if ($qty < 1) {
                    continue;
                }
                $unit = $prices[$item] ?? '0';
                $pay = item_buyback_곱($unit, $qty);
                $총지급 = item_buyback_합($총지급, $pay);
                $총수량 += $qty;
                $닉셋[$nick] = true;
                $line = [
                    'key' => item_buyback_키($midx, $item),
                    'midx' => $midx,
                    'nick' => $nick,
                    'item' => $item,
                    'qty' => $qty,
                    'unit' => $unit,
                    'unit_fmt' => item_buyback_금액표시($unit),
                    'pay' => $pay,
                    'pay_fmt' => item_buyback_금액표시($pay),
                    'zero_price' => ($unit === '0'),
                ];
                $rows[] = $line;
                if (!isset($byNick[$nick])) {
                    $byNick[$nick] = [
                        'midx' => $midx,
                        'nick' => $nick,
                        'qty' => 0,
                        'pay' => '0',
                        'items' => [],
                    ];
                }
                $byNick[$nick]['qty'] += $qty;
                $byNick[$nick]['pay'] = item_buyback_합($byNick[$nick]['pay'], $pay);
                $byNick[$nick]['items'][] = $item . '×' . $qty;
            }
        }
    }

    $friends = [];
    foreach ($byNick as $f) {
        $friends[] = [
            'midx' => (int)$f['midx'],
            'nick' => (string)$f['nick'],
            'qty' => (int)$f['qty'],
            'pay' => (string)$f['pay'],
            'pay_fmt' => item_buyback_금액표시($f['pay']),
            'items_fmt' => implode(', ', $f['items']),
        ];
    }
    // 지급액 큰 순
    usort($friends, static function ($a, $b) {
        $pa = (string)($a['pay'] ?? '0');
        $pb = (string)($b['pay'] ?? '0');
        if (function_exists('bccomp')) {
            return -bccomp($pa, $pb, 0);
        }
        return strlen($pb) <=> strlen($pa) ?: ($pb <=> $pa);
    });

    $lines = count($rows);
    $holders = count($닉셋);
    $기준총 = item_buyback_기준총게임냥();
    $msg = $lines < 1
        ? '대상 아이템 보유자가 없습니다.'
        : "보유자 {$holders}명 · {$lines}건 · 총 {$총수량}개 · 지급 예정 " . item_buyback_금액표시($총지급);

    return [
        'ok' => $lines > 0,
        'msg' => $msg,
        'prices' => $prices,
        'price_info' => $priceInfo,
        'bcmath' => function_exists('bcmul'),
        'base_total' => $기준총,
        'base_total_fmt' => item_buyback_금액표시($기준총),
        'rows' => $rows,
        'friends' => $friends,
        'stats' => [
            'holders' => $holders,
            'lines' => $lines,
            'qty' => $총수량,
            'payout' => $총지급,
            'payout_fmt' => item_buyback_금액표시($총지급),
        ],
    ];
}

/**
 * @param string[]|null $keys null이면 전부
 * @return array{ok:bool,msg:string,done:int,fail:int,payout:string,payout_fmt:string}
 */
function item_buyback_실행(string $adminNick, ?array $keys = null): array {
    if (!function_exists('item_bag_sub') && is_file($_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';
    }

    $미리 = item_buyback_미리보기();
    $rows = $미리['rows'] ?? [];
    if ($rows === []) {
        return ['ok' => false, 'msg' => '처리할 보유분이 없습니다.', 'done' => 0, 'fail' => 0, 'payout' => '0', 'payout_fmt' => '0냥'];
    }

    $keySet = null;
    if (is_array($keys) && $keys !== []) {
        $keySet = [];
        foreach ($keys as $k) {
            $keySet[(string)$k] = true;
        }
    }

    $done = 0;
    $fail = 0;
    $총지급 = '0';
    $실패메시지 = [];

    foreach ($rows as $row) {
        $key = (string)($row['key'] ?? '');
        if ($keySet !== null && !isset($keySet[$key])) {
            continue;
        }
        $midx = (int)($row['midx'] ?? 0);
        $nick = trim((string)($row['nick'] ?? ''));
        $item = trim((string)($row['item'] ?? ''));
        $qty = (int)($row['qty'] ?? 0);
        $pay = (string)($row['pay'] ?? '0');
        $pay = function_exists('냥_정수문자열') ? 냥_정수문자열($pay) : (ltrim(preg_replace('/[^\d]/', '', $pay), '0') ?: '0');

        if ($midx < 1 || $nick === '' || $item === '' || $qty < 1) {
            $fail++;
            continue;
        }
        if ($pay === '0') {
            $실패메시지[] = "{$nick} {$item}×{$qty}: 구매시세 0 — 스킵";
            $fail++;
            continue;
        }

        $sub = item_bag_sub($midx, $nick, $item, $qty);
        if (empty($sub['ok'])) {
            $실패메시지[] = "{$nick} {$item}: " . ($sub['msg'] ?? '가방 차감 실패');
            $fail++;
            continue;
        }

        $닉_esc = addslashes($nick);
        $pay_sql = preg_replace('/[^\d]/', '', $pay) ?: '0';
        $ok = @db_query("UPDATE tb_member SET point = point + {$pay_sql} WHERE name = '{$닉_esc}' LIMIT 1");
        if (!$ok) {
            // 롤백 시도: 가방 복구
            if (function_exists('item_bag_add')) {
                item_bag_add($midx, $nick, $item, $qty);
            }
            $실패메시지[] = "{$nick} {$item}: 게임냥 지급 실패";
            $fail++;
            continue;
        }

        if (function_exists('지급로그')) {
            지급로그('구매시세일괄회수', $nick, $item . '×' . $qty, 0, $pay_sql);
        }

        $총지급 = item_buyback_합($총지급, $pay_sql);
        $done++;
    }

    $fmt = item_buyback_금액표시($총지급);
    if ($done < 1) {
        $msg = '처리된 건이 없습니다.';
        if ($실패메시지 !== []) {
            $msg .= "\n" . implode("\n", array_slice($실패메시지, 0, 8));
        }
        return ['ok' => false, 'msg' => $msg, 'done' => 0, 'fail' => $fail, 'payout' => '0', 'payout_fmt' => '0냥'];
    }

    $msg = "완료 {$done}건 · 지급 합계 {$fmt}";
    if ($fail > 0) {
        $msg .= " · 실패/스킵 {$fail}건";
        if ($실패메시지 !== []) {
            $msg .= "\n" . implode("\n", array_slice($실패메시지, 0, 5));
        }
    }
    if ($adminNick !== '') {
        $msg .= "\n실행: {$adminNick}";
    }

    return [
        'ok' => true,
        'msg' => $msg,
        'done' => $done,
        'fail' => $fail,
        'payout' => $총지급,
        'payout_fmt' => $fmt,
    ];
}
