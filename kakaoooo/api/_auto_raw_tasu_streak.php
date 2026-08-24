<?php
/**
 * 생타수: info1 당일 타수와 동일 — tb_msg 에서 SUM(tasu) 는 버프 반영이므로 쓰지 않음.
 *       생타_SQL_select_expr (일반채팅 · 사진 제외) 만 집계 (tasu != 0, 달력일 범위).
 * 매일 생타 200 미만이면 연속일 0으로 초기화.
 * 생타 200+ 연속 3일이면 일방신청권·일방연장권 각 1개 지급(tb_member_item_bag) 후 연속 0으로 리셋.
 * 해당일 생타 200 달성자 전원은 tb_lotto_info 에 item=생타400목록 으로 한 번에 기록.
 *
 * DB 마이그레이션: schema/tb_member_alter_raw_tasu_streak.sql, config_alter_raw_streak_settled.sql
 * 호출: _auto_attendance.php — 매 크론(어제까지 정산) + 당일 200타 달성 시 즉시 지급
 */
if (!function_exists('생타연속_스키마_보장')) {
  function 생타연속_스키마_보장(): bool {
    static $ok = null;
    if ($ok !== null) {
      return $ok;
    }
    $c1 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'raw_tasu_streak'");
    if (empty($c1)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN raw_tasu_streak INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '생타 200+ 연속 일수'");
      $c1 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'raw_tasu_streak'");
    }
    $c2 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'raw_tasu_streak_last'");
    if (empty($c2)) {
      @db_query("ALTER TABLE tb_member ADD COLUMN raw_tasu_streak_last DATE NULL DEFAULT NULL COMMENT '마지막으로 200+ 충족한 날짜'");
      $c2 = @db_select("SHOW COLUMNS FROM tb_member LIKE 'raw_tasu_streak_last'");
    }
    $c3 = @db_select("SHOW COLUMNS FROM config LIKE 'raw_streak_settled_date'");
    if (empty($c3)) {
      @db_query("ALTER TABLE config ADD COLUMN raw_streak_settled_date VARCHAR(12) NULL DEFAULT '' COMMENT '생타연속 마지막 처리일'");
      $c3 = @db_select("SHOW COLUMNS FROM config LIKE 'raw_streak_settled_date'");
    }
    $c4 = @db_select("SHOW COLUMNS FROM config LIKE 'raw_streak_catchup_date'");
    if (empty($c4)) {
      @db_query("ALTER TABLE config ADD COLUMN raw_streak_catchup_date VARCHAR(12) NULL DEFAULT '' COMMENT '생타연속 3일창 소급 처리일'");
      $c4 = @db_select("SHOW COLUMNS FROM config LIKE 'raw_streak_catchup_date'");
    }
    if (is_file(__DIR__ . '/item_bag.inc.php')) {
      require_once __DIR__ . '/item_bag.inc.php';
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column('일방신청권');
      item_bag_ensure_column('일방연장권');
    }
    $ok = !empty($c1) && !empty($c2) && !empty($c3);
    return $ok;
  }
}

if (!function_exists('생타연속_권지급')) {
  /**
   * 일방신청권·일방연장권 각 1개 + 미션/로그/알림
   * last 는 달성일(D)로 남겨 같은 날 중복 지급을 막음
   */
  function 생타연속_권지급(int $idx, string $name, string $D, int $목표타수 = 200, int $목표일 = 3): bool {
    if ($idx < 1 || $name === '' || strlen($D) !== 10) {
      return false;
    }
    생타연속_스키마_보장();
    $name_esc = addslashes($name);
    $D_esc = addslashes($D);
    $아1 = '일방신청권';
    $아2 = '일방연장권';
    $지급ok = false;
    if (function_exists('item_bag_add')) {
      $r1 = item_bag_add($idx, $name, $아1, 1);
      $r2 = item_bag_add($idx, $name, $아2, 1);
      $지급ok = !empty($r1['ok']) && !empty($r2['ok']);
    }
    if (!$지급ok) {
      $아1_esc = addslashes($아1);
      $아2_esc = addslashes($아2);
      @db_query("INSERT INTO tb_member_item SET midx = {$idx}, nick = '{$name_esc}', itemname = '{$아1_esc}', status = 0, regdate = NOW()");
      @db_query("INSERT INTO tb_member_item SET midx = {$idx}, nick = '{$name_esc}', itemname = '{$아2_esc}', status = 0, regdate = NOW()");
    }
    if (function_exists('일방신청권_지급기록')) {
      일방신청권_지급기록($name, '생타3일연속', [
        'midx' => $idx,
        'reason_text' => "생타 {$목표타수}타 × {$목표일}일 연속 달성 ({$D})",
        'qty' => 1,
      ]);
    }
    if (function_exists('미션완료_기록_if_new')) {
      미션완료_기록_if_new($name, '일방', '200타');
    }
    if (function_exists('지급로그')) {
      지급로그('생타3일연속', $name, $name, 0, 0);
    }
    $D_esc = addslashes($D);
    db_query("
      UPDATE tb_member
      SET raw_tasu_streak = IF(raw_tasu_streak_last IS NULL OR raw_tasu_streak_last <= '{$D_esc}', 0, raw_tasu_streak),
          raw_tasu_streak_last = IF(raw_tasu_streak_last IS NULL OR raw_tasu_streak_last < '{$D_esc}', '{$D_esc}', raw_tasu_streak_last)
      WHERE idx = {$idx}
      LIMIT 1
    ");
    return true;
  }
}

if (!function_exists('생타연속_하루처리')) {
  function 생타연속_하루처리($D) {
    $생타연속_목표일 = 3;
    $생타연속_목표타수 = 200;
    $D_esc = preg_replace('/[^0-9\-]/', '', $D);
    if ($D_esc !== $D || strlen($D) !== 10) {
      return;
    }

    $D_prev = date('Y-m-d', strtotime($D . ' -1 day'));

    // 생타 = 일반채팅 · 사진 제외. sum(tasu) 미사용 (.생타·.궁금 과 동일 필터)
    $sums = array();
    $agg = @db_query("
      SELECT
        nickname,
        " . 생타_SQL_select_expr('msg') . " AS day_cnt
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$D_esc} 00:00:00'
        AND regdate < DATE_ADD('{$D_esc}', INTERVAL 1 DAY)
        AND nickname NOT IN ('오픈', '')
      GROUP BY nickname
    ");
    if ($agg) {
      while ($r = db_fetch($agg)) {
        $nk = trim((string)($r['nickname'] ?? ''));
        if ($nk !== '') {
          $sums[$nk] = (int)($r['day_cnt'] ?? 0);
        }
      }
    }

    $mem_rs = @db_query("
      SELECT idx, name,
        COALESCE(raw_tasu_streak, 0) AS raw_tasu_streak,
        raw_tasu_streak_last
      FROM tb_member
    ");
    if (!$mem_rs) {
      return;
    }

    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column('일방신청권');
      item_bag_ensure_column('일방연장권');
    }

    $연속달성자 = array();
    while ($m = db_fetch($mem_rs)) {
      $idx = (int)($m['idx'] ?? 0);
      $name = trim((string)($m['name'] ?? ''));
      if ($idx <= 0 || $name === '') {
        continue;
      }
      $name_esc = addslashes($name);

      $day_cnt = (int)($sums[$name] ?? 0);

      if ($day_cnt < $생타연속_목표타수) {
        db_query("UPDATE tb_member SET raw_tasu_streak = 0, raw_tasu_streak_last = NULL WHERE idx = {$idx} LIMIT 1");
        continue;
      }

      $streak = (int)($m['raw_tasu_streak'] ?? 0);
      $last = $m['raw_tasu_streak_last'] ?? null;
      $last_s = ($last !== null && $last !== '') ? trim((string)$last) : '';

      if ($last_s === $D) {
        continue;
      }

      if ($last_s === $D_prev) {
        $new_streak = $streak + 1;
      } else {
        $new_streak = 1;
      }

      if ($new_streak >= $생타연속_목표일) {
        생타연속_권지급($idx, $name, $D_esc, $생타연속_목표타수, $생타연속_목표일);
        $연속달성자[] = $name;
      } else {
        db_query("UPDATE tb_member SET raw_tasu_streak = {$new_streak}, raw_tasu_streak_last = '{$D_esc}' WHERE idx = {$idx} LIMIT 1");
      }
    }

    // 같은 날짜의 3일 연속 달성자는 개인별 알림 대신 한 건으로 묶어 전송
    if (count($연속달성자) > 0) {
      sort($연속달성자, SORT_STRING);
      $달성줄 = array();
      foreach ($연속달성자 as $달성닉) {
        $달성줄[] = $달성닉 . '님';
      }
      $멘트 = "⌨️ 생타 {$생타연속_목표타수}×{$생타연속_목표일}일 연속 달성!";
      // .랭킹2와 동일 — 첫 줄 뒤 공백 패딩으로 카톡 전체보기 유도
      $멘트 .= function_exists('채팅_첫줄_뒤_공백')
        ? 채팅_첫줄_뒤_공백()
        : ("\n" . str_repeat(' ', 800));
      $멘트 .= "총 " . count($연속달성자) . "명\n\n"
        . implode("\n", $달성줄)
        . "\n\n일방신청권·일방연장권 각 1개 지급\n({$D} 기준)";
      if (mb_strlen($멘트, 'UTF-8') > 6000) {
        $멘트 = mb_substr($멘트, 0, 5990, 'UTF-8') . "\n…(생략)";
      }
      $멘트_esc = addslashes($멘트);
      db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '생타3일연속목록', regdate = NOW()");
    }

    // 당일 생타 400 이상 달성자 목록 — tb_lotto_info 한 건 (중복 방지: 동일 날짜 태그)
    $달성자 = array();
    foreach ($sums as $닉 => $cnt) {
      if ((int)$cnt >= $생타연속_목표타수) {
        $달성자[] = array('nick' => $닉, 'cnt' => (int)$cnt);
      }
    }
    if (count($달성자) > 0) {
      usort($달성자, function ($a, $b) {
        if ($a['cnt'] !== $b['cnt']) {
          return $b['cnt'] - $a['cnt'];
        }
        return strcmp($a['nick'], $b['nick']);
      });
      $태그 = '【' . $D_esc . '】';
      $중복명단 = @db_select("SELECT idx FROM tb_lotto_info WHERE item = '생타400목록' AND msg LIKE '%" . addslashes($태그) . "%' LIMIT 1");
      if (empty($중복명단['idx'])) {
        $줄 = array();
        foreach ($달성자 as $행) {
          $줄[] = $행['nick'] . ' ' . $행['cnt'] . '생타';
        }
        $본문 = "⌨️ 생타 {$생타연속_목표타수} 달성 {$태그}";
        // .랭킹2와 동일 — 첫 줄 뒤 공백 패딩으로 카톡 전체보기 유도
        $본문 .= function_exists('채팅_첫줄_뒤_공백')
          ? 채팅_첫줄_뒤_공백()
          : ("\n" . str_repeat(' ', 800));
        $본문 .= "총 " . count($달성자) . "명\n\n" . implode("\n", $줄);
        if (mb_strlen($본문, 'UTF-8') > 6000) {
          $본문 = mb_substr($본문, 0, 5990, 'UTF-8') . "\n…(생략)";
        }
        $본문_esc = addslashes($본문);
        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$본문_esc}', leverage = 0, item = '생타400목록', regdate = NOW()");
      }
    }
  }
}

if (!function_exists('생타연속_닉날짜생타')) {
  function 생타연속_닉날짜생타(string $name, string $D): int {
    $name = trim($name);
    $D = preg_replace('/[^0-9\-]/', '', $D);
    if ($name === '' || strlen($D) !== 10) {
      return 0;
    }
    static $cache = [];
    $key = $name . "\x1e" . $D;
    if (isset($cache[$key])) {
      return $cache[$key];
    }
    $name_esc = addslashes($name);
    $D_esc = addslashes($D);
    $row = @db_select("
      SELECT " . 생타_SQL_select_expr('msg') . " AS c
      FROM tb_msg
      WHERE nickname = '{$name_esc}'
        AND tasu != 0
        AND regdate >= '{$D_esc} 00:00:00'
        AND regdate < DATE_ADD('{$D_esc}', INTERVAL 1 DAY)
    ");
    $cache[$key] = (int)($row['c'] ?? 0);
    return $cache[$key];
  }
}

if (!function_exists('생타연속_이미지급됨')) {
  /** $fromDate 이후(포함) 생타3일연속 지급 기록이 있으면 true */
  function 생타연속_이미지급됨(string $name, string $fromDate): bool {
    $name = trim($name);
    $fromDate = preg_replace('/[^0-9\-]/', '', $fromDate);
    if ($name === '' || strlen($fromDate) !== 10) {
      return false;
    }
    $name_esc = addslashes($name);
    $from_esc = addslashes($fromDate);
    $row = @db_select("
      SELECT idx FROM tb_ilbang_ticket_log
      WHERE nick = '{$name_esc}'
        AND reason_code = '생타3일연속'
        AND DATE(regdate) >= '{$from_esc}'
      LIMIT 1
    ");
    return !empty($row['idx']);
  }
}

if (!function_exists('생타연속_3일창_지급시도')) {
  /**
   * $endDate 포함 최근 3일(달력) 생타가 모두 200 이상이면 지급.
   * 같은 3일 창에서 이미 지급했으면 건너뜀.
   */
  function 생타연속_3일창_지급시도(int $idx, string $name, string $endDate, int $end_raw = -1): bool {
    $목표타수 = 200;
    $목표일 = 3;
    $endDate = preg_replace('/[^0-9\-]/', '', $endDate);
    if ($idx < 1 || $name === '' || strlen($endDate) !== 10) {
      return false;
    }
    $d1 = date('Y-m-d', strtotime($endDate . ' -1 day'));
    $d2 = date('Y-m-d', strtotime($endDate . ' -2 day'));
    $c0 = ($end_raw >= 0) ? (int)$end_raw : 생타연속_닉날짜생타($name, $endDate);
    if ($c0 < $목표타수) {
      return false;
    }
    if (생타연속_닉날짜생타($name, $d1) < $목표타수 || 생타연속_닉날짜생타($name, $d2) < $목표타수) {
      return false;
    }
    if (생타연속_이미지급됨($name, $d2)) {
      return false;
    }
    생타연속_스키마_보장();
    $row = @db_select("
      SELECT COALESCE(raw_tasu_streak, 0) AS raw_tasu_streak, raw_tasu_streak_last
      FROM tb_member WHERE idx = {$idx} LIMIT 1
    ");
    $last = is_array($row) ? trim((string)($row['raw_tasu_streak_last'] ?? '')) : '';
    $streak = is_array($row) ? (int)($row['raw_tasu_streak'] ?? 0) : 0;
    // 오늘(또는 마감일) 이미 지급·집계된 경우
    if ($last === $endDate && $streak === 0) {
      return false;
    }
    생타연속_권지급($idx, $name, $endDate, $목표타수, $목표일);
    return true;
  }
}

if (!function_exists('생타연속_오늘200_지급시도')) {
  /**
   * 당일 생타 200 달성 시점 — 어제·그저께도 200이면 즉시 지급 (연속일 컬럼 누락과 무관)
   */
  function 생타연속_오늘200_지급시도(int $idx, string $name, int $today_raw): bool {
    $today = date('Y-m-d');
    if (!생타연속_3일창_지급시도($idx, $name, $today, $today_raw)) {
      return false;
    }
    $멘트 = "⌨️ {$name}님 생타 200×3일 연속 달성!\n일방신청권·일방연장권 각 1개 지급";
    $멘트_esc = addslashes($멘트);
    $item_esc = addslashes($name);
    @db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()");
    return true;
  }
}

if (!function_exists('생타연속_3일창_소급')) {
  /**
   * $endD 기준 3일 200타인데 권 못 받은 사람 일괄 지급
   * @return list<string> 이번에 지급된 닉
   */
  function 생타연속_3일창_소급(string $endD, bool $공지 = true): array {
    $목표타수 = 200;
    $endD = preg_replace('/[^0-9\-]/', '', $endD);
    if (strlen($endD) !== 10) {
      return [];
    }
    $d1 = date('Y-m-d', strtotime($endD . ' -1 day'));
    $d2 = date('Y-m-d', strtotime($endD . ' -2 day'));
    $end_esc = addslashes($endD);
    $d1_esc = addslashes($d1);
    $d2_esc = addslashes($d2);
    $사진 = function_exists('생타_사진메시지_SQL')
      ? 생타_사진메시지_SQL('t.msg')
      : "TRIM(IFNULL(t.msg, '')) LIKE '%사진을 보냈습니다%'";
    $생타일 = "CASE WHEN TRIM(IFNULL(t.msg, '')) = '' THEN 0 WHEN {$사진} THEN 0 ELSE 1 END";
    $rs = @db_query("
      SELECT
        m.idx,
        m.name,
        SUM(CASE WHEN t.regdate >= '{$d2_esc} 00:00:00' AND t.regdate < '{$d1_esc} 00:00:00' THEN {$생타일} ELSE 0 END) AS d2_cnt,
        SUM(CASE WHEN t.regdate >= '{$d1_esc} 00:00:00' AND t.regdate < '{$end_esc} 00:00:00' THEN {$생타일} ELSE 0 END) AS d1_cnt,
        SUM(CASE WHEN t.regdate >= '{$end_esc} 00:00:00' AND t.regdate < DATE_ADD('{$end_esc}', INTERVAL 1 DAY) THEN {$생타일} ELSE 0 END) AS d0_cnt
      FROM tb_member m
      INNER JOIN tb_msg t
        ON t.nickname = m.name
       AND t.tasu != 0
       AND t.regdate >= '{$d2_esc} 00:00:00'
       AND t.regdate < DATE_ADD('{$end_esc}', INTERVAL 1 DAY)
      WHERE m.status = 0
        AND m.name NOT IN ('오픈', '')
      GROUP BY m.idx, m.name
      HAVING d2_cnt >= {$목표타수} AND d1_cnt >= {$목표타수} AND d0_cnt >= {$목표타수}
    ");
    if (!$rs) {
      return [];
    }
    $연속달성자 = [];
    while ($r = db_fetch($rs)) {
      $idx = (int)($r['idx'] ?? 0);
      $name = trim((string)($r['name'] ?? ''));
      $d0 = (int)($r['d0_cnt'] ?? 0);
      if ($idx < 1 || $name === '') {
        continue;
      }
      if (!생타연속_3일창_지급시도($idx, $name, $endD, $d0)) {
        continue;
      }
      $연속달성자[] = $name;
    }
    if ($공지 && count($연속달성자) > 0) {
      sort($연속달성자, SORT_STRING);
      $달성줄 = [];
      foreach ($연속달성자 as $달성닉) {
        $달성줄[] = $달성닉 . '님';
      }
      $멘트 = "⌨️ 생타 {$목표타수}×3일 연속 달성!";
      $멘트 .= function_exists('채팅_첫줄_뒤_공백')
        ? 채팅_첫줄_뒤_공백()
        : ("\n" . str_repeat(' ', 800));
      $멘트 .= "총 " . count($연속달성자) . "명\n\n"
        . implode("\n", $달성줄)
        . "\n\n일방신청권·일방연장권 각 1개 지급\n({$endD} 기준 · 소급)";
      if (mb_strlen($멘트, 'UTF-8') > 6000) {
        $멘트 = mb_substr($멘트, 0, 5990, 'UTF-8') . "\n…(생략)";
      }
      $멘트_esc = addslashes($멘트);
      @db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '생타3일연속목록', regdate = NOW()");
    }
    return $연속달성자;
  }
}

if (!function_exists('생타연속_체크지급_명령문구')) {
  /**
   * .생타체크 — 현시점 기준 3일 200타인데 권 미지급자 일괄 지급 후 본문 반환
   * 오늘 200 미만이면 오늘은 아직 진행 중으로 보고, 어제까지의 3일을 지급 대상으로 봄
   */
  function 생타연속_체크지급_명령문구(): string {
    생타연속_스키마_보장();
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $uniq = [];
    foreach ([$yesterday, $today] as $endD) {
      foreach (생타연속_3일창_소급($endD, false) as $nick) {
        $n = trim((string)$nick);
        if ($n !== '') {
          $uniq[$n] = true;
        }
      }
    }
    $list = array_keys($uniq);
    sort($list, SORT_STRING);
    $pad = function_exists('채팅_첫줄_뒤_공백')
      ? 채팅_첫줄_뒤_공백()
      : ("\n" . str_repeat(' ', 800));
    if (count($list) === 0) {
      return "⌨️ 생타체크{$pad}현시점 기준 미지급자가 없어요.\n(연속 3일 생타 200 · 일방신청권·일방연장권)";
    }
    $줄 = [];
    foreach ($list as $닉) {
      $줄[] = $닉 . '님';
    }
    $msg = "⌨️ 생타체크 — 미지급 일괄지급{$pad}";
    $msg .= "총 " . count($list) . "명\n\n"
      . implode("\n", $줄)
      . "\n\n일방신청권·일방연장권 각 1개 지급";
    if (mb_strlen($msg, 'UTF-8') > 6000) {
      $msg = mb_substr($msg, 0, 5990, 'UTF-8') . "\n…(생략)";
    }
    return $msg;
  }
}

if (!function_exists('생타연속_창지급맵')) {
  /**
   * nick => [달성일(Y-m-d) => true]
   * reason_text 의 (YYYY-MM-DD) 를 우선하고, 없으면 지급 당일을 창 종료일로 봄
   */
  function 생타연속_창지급맵(string $fromDate): array {
    $fromDate = preg_replace('/[^0-9\-]/', '', $fromDate);
    if (strlen($fromDate) !== 10) {
      return [];
    }
    $loadFrom = date('Y-m-d', strtotime($fromDate . ' -40 day'));
    $load_esc = addslashes($loadFrom);
    $rs = @db_query("
      SELECT nick, reason_text, DATE(regdate) AS d
      FROM tb_ilbang_ticket_log
      WHERE reason_code = '생타3일연속'
        AND DATE(regdate) >= '{$load_esc}'
    ");
    $map = [];
    if (!$rs) {
      return $map;
    }
    while ($r = db_fetch($rs)) {
      $nick = trim((string)($r['nick'] ?? ''));
      if ($nick === '') {
        continue;
      }
      $end = '';
      $text = (string)($r['reason_text'] ?? '');
      if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $text, $m)) {
        $end = $m[1];
      } else {
        $end = substr(trim((string)($r['d'] ?? '')), 0, 10);
      }
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $map[$nick][$end] = true;
      }
    }
    return $map;
  }
}

if (!function_exists('생타연속_기간미지급목록')) {
  /**
   * 최근 $days일(오늘 포함) 동안, 생타 200 연속 3일인데 권이 안 나간 창
   * 실제 지급 규칙과 같이 3일 달성 시 연속을 리셋(겹치는 4일째는 새 1일)
   *
   * @return array{from:string,to:string,days:int,goal:int,need:int,pay_n:int,pay_qty:int,rows:list<array>}
   */
  function 생타연속_기간미지급목록(int $days = 14): array {
    $목표타수 = 200;
    $목표일 = 3;
    $days = max(3, min(60, $days));
    $today = date('Y-m-d');
    $firstEnd = date('Y-m-d', strtotime($today . ' -' . ($days - 1) . ' day'));
    $dataStart = date('Y-m-d', strtotime($firstEnd . ' -' . ($목표일 - 1) . ' day'));
    $empty = [
      'from' => $firstEnd,
      'to' => $today,
      'days' => $days,
      'goal' => $목표타수,
      'need' => $목표일,
      'pay_n' => 0,
      'pay_qty' => 0,
      'rows' => [],
    ];
    if (!function_exists('생타_SQL_select_expr')) {
      return $empty;
    }
    생타연속_스키마_보장();
    $start_esc = addslashes($dataStart);
    $today_esc = addslashes($today);
    $expr = 생타_SQL_select_expr('msg');
    $byNick = [];
    $rs = @db_query("
      SELECT nickname, DATE(regdate) AS d, {$expr} AS c
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$start_esc} 00:00:00'
        AND regdate < DATE_ADD('{$today_esc}', INTERVAL 1 DAY)
        AND nickname NOT IN ('오픈', '')
      GROUP BY nickname, DATE(regdate)
    ");
    if ($rs) {
      while ($r = db_fetch($rs)) {
        $nk = trim((string)($r['nickname'] ?? ''));
        $d = substr(trim((string)($r['d'] ?? '')), 0, 10);
        if ($nk === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
          continue;
        }
        $byNick[$nk][$d] = (int)($r['c'] ?? 0);
      }
    }

    $granted = 생타연속_창지급맵($dataStart);
    $memRs = @db_query("SELECT idx, name FROM tb_member WHERE status = 0 AND name NOT IN ('오픈', '') ORDER BY name ASC");
    if (!$memRs) {
      return $empty;
    }

    $dates = [];
    $cursor = $dataStart;
    while ($cursor <= $today) {
      $dates[] = $cursor;
      $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
    }

    $rows = [];
    $payN = 0;
    $payQty = 0;
    while ($m = db_fetch($memRs)) {
      $idx = (int)($m['idx'] ?? 0);
      $name = trim((string)($m['name'] ?? ''));
      if ($idx < 1 || $name === '') {
        continue;
      }
      $consec = 0;
      $windows = [];
      foreach ($dates as $d) {
        $c = (int)($byNick[$name][$d] ?? 0);
        if ($c < $목표타수) {
          $consec = 0;
          continue;
        }
        $consec++;
        if ($consec < $목표일) {
          continue;
        }
        $inRange = ($d >= $firstEnd);
        $already = !empty($granted[$name][$d]);
        if ($inRange && !$already) {
          $d1 = date('Y-m-d', strtotime($d . ' -1 day'));
          $d2 = date('Y-m-d', strtotime($d . ' -2 day'));
          $windows[] = [
            'end' => $d,
            'days' => [$d2, $d1, $d],
            'cnt' => [
              $d2 => (int)($byNick[$name][$d2] ?? 0),
              $d1 => (int)($byNick[$name][$d1] ?? 0),
              $d => $c,
            ],
          ];
        }
        $consec = 0;
      }
      if ($windows === []) {
        continue;
      }
      $qty = count($windows);
      $payN++;
      $payQty += $qty;
      $rows[] = [
        'idx' => $idx,
        'name' => $name,
        'qty' => $qty,
        'windows' => $windows,
      ];
    }

    $empty['pay_n'] = $payN;
    $empty['pay_qty'] = $payQty;
    $empty['rows'] = $rows;
    return $empty;
  }
}

if (!function_exists('생타연속_기간소급')) {
  /**
   * 최근 $days일 미지급 3일창을 일괄 지급
   * @return array{ok:bool,msg:string,n:int,qty:int,results:list<array>}
   */
  function 생타연속_기간소급(int $days = 14, bool $공지 = true): array {
    $list = 생타연속_기간미지급목록($days);
    $results = [];
    $okN = 0;
    $okQty = 0;
    $nicks = [];
    foreach ($list['rows'] as $row) {
      $idx = (int)($row['idx'] ?? 0);
      $name = trim((string)($row['name'] ?? ''));
      $got = 0;
      $ends = [];
      foreach ($row['windows'] as $w) {
        $end = (string)($w['end'] ?? '');
        if ($idx < 1 || $name === '' || strlen($end) !== 10) {
          continue;
        }
        if (!생타연속_권지급($idx, $name, $end, (int)$list['goal'], (int)$list['need'])) {
          continue;
        }
        $got++;
        $ends[] = $end;
      }
      if ($got > 0) {
        $okN++;
        $okQty += $got;
        $nicks[$name] = true;
      }
      $results[] = [
        'name' => $name,
        'ok' => $got > 0,
        'qty' => $got,
        'ends' => $ends,
      ];
    }
    if ($공지 && $okN > 0) {
      $names = array_keys($nicks);
      sort($names, SORT_STRING);
      $줄 = [];
      foreach ($names as $닉) {
        $줄[] = $닉 . '님';
      }
      $from = (string)$list['from'];
      $to = (string)$list['to'];
      $멘트 = "⌨️ 생타 200×3일 연속 소급 지급!";
      $멘트 .= function_exists('채팅_첫줄_뒤_공백')
        ? 채팅_첫줄_뒤_공백()
        : ("\n" . str_repeat(' ', 800));
      $멘트 .= "총 {$okN}명 · 권 {$okQty}회\n({$from} ~ {$to})\n\n"
        . implode("\n", $줄)
        . "\n\n일방신청권·일방연장권 각 1개";
      if (mb_strlen($멘트, 'UTF-8') > 6000) {
        $멘트 = mb_substr($멘트, 0, 5990, 'UTF-8') . "\n…(생략)";
      }
      $멘트_esc = addslashes($멘트);
      @db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '생타3일연속목록', regdate = NOW()");
    }
    $msg = $okN < 1
      ? '미지급자가 없어요.'
      : ("소급 지급 완료 · {$okN}명 · 일방신청권·연장권 각 {$okQty}회");
    return [
      'ok' => true,
      'msg' => $msg,
      'n' => $okN,
      'qty' => $okQty,
      'results' => $results,
    ];
  }
}

if (!function_exists('생타연속_자정마감')) {
  function 생타연속_자정마감() {
    if (!생타연속_스키마_보장()) {
      return;
    }
    $cfg = @db_select('SELECT * FROM config LIMIT 1');
    if (!is_array($cfg)) {
      $cfg = [];
    }

    $endD = date('Y-m-d', strtotime('-1 day'));
    $today = date('Y-m-d');
    $last = trim((string)($cfg['raw_streak_settled_date'] ?? ''));
    $catchup = trim((string)($cfg['raw_streak_catchup_date'] ?? ''));

    if ($last !== $endD) {
      if ($last === '') {
        // 첫 가동: 어제 포함 최근 3일만 소급 (이미 3일 200타 한 사람 지급)
        $cursor = date('Y-m-d', strtotime($endD . ' -2 day'));
      } else {
        $cursor = date('Y-m-d', strtotime($last . ' +1 day'));
      }

      if ($cursor <= $endD) {
        $guard = 0;
        while ($cursor <= $endD) {
          생타연속_하루처리($cursor);
          if ($cursor === $endD) {
            break;
          }
          $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
          $guard++;
          if ($guard > 400) {
            break;
          }
        }
        $end_esc = addslashes($endD);
        @db_query("UPDATE config SET raw_streak_settled_date = '{$end_esc}' LIMIT 1");
      }
    }

    // 연속일 컬럼이 어긋난 계정 — 어제 기준 3일 200타인데 권 미지급이면 하루 1회 소급
    if ($catchup !== $today) {
      생타연속_3일창_소급($endD);
      $today_esc = addslashes($today);
      @db_query("UPDATE config SET raw_streak_catchup_date = '{$today_esc}' LIMIT 1");
    }
  }
}
