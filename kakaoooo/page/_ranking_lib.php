<?php
/**
 * 가방 바로가기 · 랭킹 페이지용 목록
 * - 버프타수: SUM(tasu) · 타수: 생타(일반채팅 · 사진 제외)
 * - 본냥: newpoint(.랭킹1) · 겜냥: point(.랭킹2)
 * - 무기: 강화 높은 순 · 장비: 채굴 장비 레벨 높은 순
 */

if (!function_exists('db_select')) {
    include_once ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . '/lib/_function.php';
}
if (!function_exists('생타_SQL_select_expr') && is_file(__DIR__ . '/../api/function.php')) {
    include_once __DIR__ . '/../api/function.php';
}
if (!function_exists('무기_타입_스키마보장') && is_file(__DIR__ . '/../api/game/weapon_type.inc.php')) {
    require_once __DIR__ . '/../api/game/weapon_type.inc.php';
}
if (!function_exists('mining_tool_def') && is_file(__DIR__ . '/../api/game/mining_tool.inc.php')) {
    require_once __DIR__ . '/../api/game/mining_tool.inc.php';
}

/** @return string Y-m-d */
function rk_오늘날짜() {
    try {
        return (new DateTime('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
    } catch (Throwable $e) {
        return date('Y-m-d');
    }
}

/**
 * 오늘 버프타수 순위 (SUM(tasu) · .생타의 버프타와 동일 필터)
 * @return list<array{rank:int,nick:string,buff:int,raw:int}>
 */
function rk_버프타수목록($날짜 = '') {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
        $날짜 = rk_오늘날짜();
    }
    $raw_expr = function_exists('생타_SQL_select_expr')
        ? 생타_SQL_select_expr('msg')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,''))='' OR TRIM(msg) LIKE '%사진을 보냈습니다%' THEN 0 ELSE 1 END), 0)";
    $buff_expr = function_exists('버프타_SQL_select_expr')
        ? 버프타_SQL_select_expr('msg', 'tasu')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,'')) LIKE '%사진을 보냈습니다%' THEN 0 ELSE IFNULL(tasu, 0) END), 0)";
    $sql = "
      SELECT
        nickname AS nick,
        {$buff_expr} AS buff_cnt,
        {$raw_expr} AS raw_cnt
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$날짜}'
        AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND nickname NOT IN ('오픈', '')
      GROUP BY nickname
      HAVING buff_cnt > 0
      ORDER BY buff_cnt DESC, raw_cnt DESC, nick ASC
    ";
    $out = [];
    $rs = @db_query($sql);
    $rank = 0;
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $rank++;
            $out[] = [
                'rank' => $rank,
                'nick' => $nick,
                'buff' => (int)($row['buff_cnt'] ?? 0),
                'raw'  => (int)($row['raw_cnt'] ?? 0),
            ];
        }
    }
    return $out;
}

/**
 * 오늘 타수(생타) 순위 · .생타와 동일
 * @return list<array{rank:int,nick:string,raw:int,buff:int}>
 */
function rk_타수목록($날짜 = '') {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
        $날짜 = rk_오늘날짜();
    }
    $raw_expr = function_exists('생타_SQL_select_expr')
        ? 생타_SQL_select_expr('msg')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,''))='' OR TRIM(msg) LIKE '%사진을 보냈습니다%' THEN 0 ELSE 1 END), 0)";
    $buff_expr = function_exists('버프타_SQL_select_expr')
        ? 버프타_SQL_select_expr('msg', 'tasu')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,'')) LIKE '%사진을 보냈습니다%' THEN 0 ELSE IFNULL(tasu, 0) END), 0)";
    $sql = "
      SELECT
        nickname AS nick,
        {$raw_expr} AS raw_cnt,
        {$buff_expr} AS buff_cnt
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$날짜}'
        AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND nickname NOT IN ('오픈', '')
      GROUP BY nickname
      HAVING raw_cnt > 0
      ORDER BY raw_cnt DESC, buff_cnt DESC, nick ASC
    ";
    $out = [];
    $rs = @db_query($sql);
    $rank = 0;
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $rank++;
            $out[] = [
                'rank' => $rank,
                'nick' => $nick,
                'raw'  => (int)($row['raw_cnt'] ?? 0),
                'buff' => (int)($row['buff_cnt'] ?? 0),
            ];
        }
    }
    return $out;
}

/**
 * 본냥(보유냥/newpoint) 순위 — .랭킹1 과 동일
 * @return list<array{rank:int,nick:string,level:int,title:string,amount:string,score:string}>
 */
function rk_본냥목록() {
    $sql = "
      SELECT name, level, title, point,
             CONCAT('N', CAST(FLOOR(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))) AS CHAR)) AS np
      FROM tb_member
      WHERE name NOT IN ('오픈', '신입', '')
    ";
    $rows = [];
    $rs = @db_query($sql);
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['name'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $raw = (string)($row['np'] ?? 'N0');
            if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
                $raw = substr($raw, 1);
            }
            $np = function_exists('냥_정수문자열')
                ? 냥_정수문자열($raw)
                : (ltrim(preg_replace('/[^\d\-]/', '', $raw), '0') ?: '0');
            if ($np === '' || $np === '-') {
                $np = '0';
            }
            $호칭 = trim((string)($row['title'] ?? ''));
            if ($호칭 === '' && function_exists('계급')) {
                $계급 = 계급($row['point'] ?? 0);
                $호칭 = (string)($계급['name'] ?? '');
            }
            $rows[] = [
                'nick'   => $nick,
                'level'  => (int)($row['level'] ?? 0),
                'title'  => $호칭,
                'amount' => $np,
                'score'  => $np,
            ];
        }
    }
    usort($rows, static function ($a, $b) {
        $na = (string)($a['score'] ?? '0');
        $nb = (string)($b['score'] ?? '0');
        if (function_exists('bccomp')) {
            return bccomp($nb, $na, 0);
        }
        $la = strlen(ltrim($na, '-'));
        $lb = strlen(ltrim($nb, '-'));
        $naNeg = isset($na[0]) && $na[0] === '-';
        $nbNeg = isset($nb[0]) && $nb[0] === '-';
        if ($naNeg !== $nbNeg) {
            return $naNeg ? 1 : -1;
        }
        if ($la !== $lb) {
            return $nbNeg ? ($la <=> $lb) : ($lb <=> $la);
        }
        return $nbNeg ? strcmp($na, $nb) : strcmp($nb, $na);
    });
    $out = [];
    $rank = 0;
    foreach ($rows as $row) {
        $rank++;
        $row['rank'] = $rank;
        $out[] = $row;
    }
    return $out;
}

/**
 * 겜냥(게임냥/point) 순위 — .랭킹2 과 동일
 * @return list<array{rank:int,nick:string,level:int,title:string,amount:string,score:string,signed:bool}>
 */
function rk_겜냥목록() {
    $sql = "
      SELECT name, level, title,
             CONCAT('N', CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR)) AS point_raw,
             CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS point_num
      FROM tb_member
      WHERE name NOT IN ('오픈', '신입', '')
      ORDER BY CAST(IFNULL(point, 0) AS DECIMAL(65,0)) DESC, name ASC
    ";
    $out = [];
    $rs = @db_query($sql);
    $rank = 0;
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['name'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $raw = (string)($row['point_raw'] ?? 'N0');
            if (isset($raw[0]) && ($raw[0] === 'N' || $raw[0] === 'n')) {
                $raw = substr($raw, 1);
            }
            $pt = function_exists('냥_정수문자열')
                ? 냥_정수문자열($raw)
                : (ltrim(preg_replace('/[^\d\-]/', '', $raw), '0') ?: '0');
            if ($pt === '' || $pt === '-') {
                $pt = '0';
            }
            $호칭 = trim((string)($row['title'] ?? ''));
            if ($호칭 === '' && function_exists('계급')) {
                $계급 = 계급($row['point_raw'] ?? $pt);
                $호칭 = (string)($계급['name'] ?? '');
            }
            $신불 = ($호칭 === '🆘신불자') || (bool)preg_match('/신불자/u', $호칭);
            $음수 = (isset($pt[0]) && $pt[0] === '-') || $신불;
            $rank++;
            $out[] = [
                'rank'   => $rank,
                'nick'   => $nick,
                'level'  => (int)($row['level'] ?? 0),
                'title'  => $호칭,
                'amount' => $pt,
                'score'  => $pt,
                'signed' => $음수,
            ];
        }
    }
    return $out;
}

/**
 * 무기 강화 랭킹 (.무기랭킹 과 동일 · 전체)
 * @return list<array{rank:int,nick:string,title:string,style:string,weapon:string,enhance:int}>
 */
function rk_무기목록() {
    if (function_exists('무기_타입_스키마보장')) {
        무기_타입_스키마보장();
    }
    $sql = "
      SELECT title, style, name, item, enhance, IFNULL(무기타입,0) AS 무기타입
      FROM tb_member
      WHERE TRIM(COALESCE(item,'')) != ''
        AND IFNULL(status, 0) = 0
      ORDER BY CAST(COALESCE(enhance,0) AS UNSIGNED) DESC, 강화성공시간 ASC
    ";
    $out = [];
    $rs = @db_query($sql);
    $rank = 0;
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['name'] ?? ''));
            if ($nick === '' || $nick === '오픈' || $nick === '신입') {
                continue;
            }
            $무기 = trim((string)($row['item'] ?? ''));
            $강화 = (int)($row['enhance'] ?? 0);
            if (function_exists('무기_타입값') && function_exists('무기_표시아이템')) {
                $t = 무기_타입값($row);
                if ($t >= 1 && $t <= 3) {
                    $무기 = 무기_표시아이템($t, $강화);
                }
            }
            $rank++;
            $out[] = [
                'rank'    => $rank,
                'nick'    => $nick,
                'title'   => trim((string)($row['title'] ?? '')),
                'style'   => trim((string)($row['style'] ?? '')),
                'weapon'  => $무기,
                'enhance' => $강화,
            ];
        }
    }
    return $out;
}

/**
 * 채굴 장비 랭킹 (.랭킹3 과 동일)
 * @return list<array{rank:int,nick:string,level:int,title:string,tool_level:int,tool_icon:string,tool_label:string}>
 */
function rk_장비목록() {
    $sql = "
      SELECT m.name, m.level, m.title, m.point, IFNULL(mm.mining_tool, 0) AS mining_tool
      FROM tb_member m
      LEFT JOIN tb_member_mining mm ON mm.nick = m.name
      WHERE IFNULL(m.status, 0) = 0
        AND m.name NOT IN ('오픈', '신입', '')
      ORDER BY IFNULL(mm.mining_tool, 0) DESC, m.name ASC
    ";
    $out = [];
    $rs = @db_query($sql);
    $rank = 0;
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['name'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $toolLv = (int)($row['mining_tool'] ?? 0);
            $def = function_exists('mining_tool_def')
                ? mining_tool_def($toolLv)
                : ['icon' => '', 'label' => 'Lv.' . $toolLv];
            $호칭 = trim((string)($row['title'] ?? ''));
            if ($호칭 === '' && function_exists('계급')) {
                $계급 = 계급($row['point'] ?? 0);
                $호칭 = (string)($계급['name'] ?? '');
            }
            $rank++;
            $out[] = [
                'rank'       => $rank,
                'nick'       => $nick,
                'level'      => (int)($row['level'] ?? 0),
                'title'      => $호칭,
                'tool_level' => $toolLv,
                'tool_icon'  => (string)($def['icon'] ?? ''),
                'tool_label' => (string)($def['label'] ?? ''),
            ];
        }
    }
    return $out;
}
