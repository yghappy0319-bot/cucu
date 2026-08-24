<?php
/**
 * 오늘 평타·생타 랭킹 (가방 오늘타수 페이지용)
 */

if (!function_exists('db_select')) {
    include_once ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . '/lib/_function.php';
}
if (!function_exists('생타_SQL_select_expr') && is_file(__DIR__ . '/../api/function.php')) {
    include_once __DIR__ . '/../api/function.php';
}

/** @return string Y-m-d */
function tt_오늘날짜() {
    try {
        return (new DateTime('now', new DateTimeZone('Asia/Seoul')))->format('Y-m-d');
    } catch (Throwable $e) {
        return date('Y-m-d');
    }
}

/**
 * 오늘 활로 뺏긴 타수 (피해자 기준)
 * ※ 단소 피해는 냥(포인트)이라 제외 — 활/활지목만 타수
 * @return array<string,int> nick => stolen
 */
function tt_뺏긴맵($날짜 = '') {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
        $날짜 = tt_오늘날짜();
    }
    $map = [];
    $sql = "
      SELECT
        피해자 AS nick,
        COALESCE(SUM(CAST(피해 AS SIGNED)), 0) AS stolen
      FROM tb_damege
      WHERE regdate >= '{$날짜}'
        AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND 유형 IN ('활', '활지목')
        AND 피해 REGEXP '^[0-9]+$'
      GROUP BY 피해자
    ";
    $rs = @db_query($sql);
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $nick = trim((string)($row['nick'] ?? ''));
            if ($nick === '') {
                continue;
            }
            $map[$nick] = (int)($row['stolen'] ?? 0);
        }
    }
    return $map;
}

/**
 * 오늘 평타 순위
 * - total: 채팅으로 쌓인 전체(msg 비어있지 않음 · 이모티콘 제외)
 * - cnt: 현재 평타(SUM · 이모티콘 제외 · .평타와 동일)
 * - stolen: 오늘 활로 뺏긴 타수
 * @return list<array{rank:int,nick:string,total:int,cnt:int,stolen:int}>
 */
function tt_평타목록($날짜 = '') {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
        $날짜 = tt_오늘날짜();
    }
    $stolenMap = tt_뺏긴맵($날짜);
    $sql = "
      SELECT
          tm.name AS nick,
          COALESCE(SUM(CASE
            WHEN TRIM(IFNULL(m.msg, '')) <> '' THEN m.tasu
            ELSE 0
          END), 0) AS total_cnt,
          COALESCE(SUM(m.tasu), 0) AS net_cnt
      FROM tb_member tm
      INNER JOIN tb_msg m ON tm.name = m.nickname
      WHERE m.regdate >= '{$날짜}'
        AND m.regdate < '{$날짜}' + INTERVAL 1 DAY
        AND IFNULL(tm.status, 0) = 0
        AND tm.name NOT IN ('오픈', '신입', '')
        AND m.msg NOT LIKE '%이모티콘을 보냈습니다.%'
      GROUP BY tm.name
      HAVING total_cnt > 0 OR net_cnt > 0
      ORDER BY total_cnt DESC, net_cnt DESC, nick ASC
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
                'rank'   => $rank,
                'nick'   => $nick,
                'total'  => (int)($row['total_cnt'] ?? 0),
                'cnt'    => (int)($row['net_cnt'] ?? 0),
                'stolen' => (int)($stolenMap[$nick] ?? 0),
            ];
        }
    }
    return $out;
}

/**
 * 오늘 생타 순위 (일반채팅 · 사진 제외 · .생타와 동일)
 * @return list<array{rank:int,nick:string,raw:int,buff:int}>
 */
function tt_생타목록($날짜 = '') {
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    if (strlen($날짜) !== 10) {
        $날짜 = tt_오늘날짜();
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
