<?php
/**
 * 생타수: info1 당일 타수와 동일 — tb_msg 에서 SUM(tasu) 는 버프 반영이므로 쓰지 않음.
 *       생타_SQL_select_expr (로우 수 + 사진 +2) 만 집계 (tasu != 0, 달력일 범위).
 * 매일 생타 400 미만이면 연속일 0으로 초기화.
 * 생타 400+ 연속 5일이면 일방신청권·일방연장권 각 1개 지급 후 연속 0으로 리셋.
 * 해당일 생타 400 달성자 전원은 tb_lotto_info 에 item=생타400목록 으로 한 번에 기록.
 *
 * DB 마이그레이션: schema/tb_member_alter_raw_tasu_streak.sql, config_alter_raw_streak_settled.sql
 * 호출: _auto_attendance.php — 매일 00:05 (크론이 매분 호출된다고 가정)
 */
if (!function_exists('생타연속_하루처리')) {
  function 생타연속_하루처리($D) {
    $생타연속_목표일 = 5;
    $생타연속_목표타수 = 400;
    $D_esc = preg_replace('/[^0-9\-]/', '', $D);
    if ($D_esc !== $D || strlen($D) !== 10) {
      return;
    }

    $D_prev = date('Y-m-d', strtotime($D . ' -1 day'));

    // 생타 = 로우 수 + 사진 +2. sum(tasu) 미사용 (tasu!=0 + 당일 범위)
    $sums = array();
    $agg = @db_query("
      SELECT
        nickname,
        " . 생타_SQL_select_expr('msg') . " AS day_cnt
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$D_esc} 00:00:00'
        AND regdate < DATE_ADD('{$D_esc}', INTERVAL 1 DAY)
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
        $아1 = '일방신청권';
        $아2 = '일방연장권';
        $아1_esc = addslashes($아1);
        $아2_esc = addslashes($아2);
        db_query("INSERT INTO tb_member_item SET midx = {$idx}, nick = '{$name_esc}', itemname = '{$아1_esc}', status = 0, regdate = NOW()");
        db_query("INSERT INTO tb_member_item SET midx = {$idx}, nick = '{$name_esc}', itemname = '{$아2_esc}', status = 0, regdate = NOW()");
        if (function_exists('일방신청권_지급기록')) {
          일방신청권_지급기록($name, '생타5일연속', [
            'midx' => $idx,
            'reason_text' => "생타 {$생타연속_목표타수}타 × {$생타연속_목표일}일 연속 달성 ({$D})",
            'qty' => 1,
          ]);
        }
        if (function_exists('미션완료_기록_if_new')) {
          미션완료_기록_if_new($name, '일방', '400타');
        }
        지급로그('생타5일연속', $name, $name, 0, 0);
        db_query("UPDATE tb_member SET raw_tasu_streak = 0, raw_tasu_streak_last = NULL WHERE idx = {$idx} LIMIT 1");

        $멘트 = "⌨️ 생타 {$생타연속_목표타수}×{$생타연속_목표일}일 연속 달성!\n{$name}님 일방신청권·일방연장권 각 1개 지급\n({$D} 기준)";
        $멘트_esc = addslashes($멘트);
        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '{$name_esc}', regdate = NOW()");
      } else {
        db_query("UPDATE tb_member SET raw_tasu_streak = {$new_streak}, raw_tasu_streak_last = '{$D_esc}' WHERE idx = {$idx} LIMIT 1");
      }
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
        $본문 = "⌨️ 생타 {$생타연속_목표타수} 달성 {$태그}\n총 " . count($달성자) . "명\n\n" . implode("\n", $줄);
        if (mb_strlen($본문, 'UTF-8') > 6000) {
          $본문 = mb_substr($본문, 0, 5990, 'UTF-8') . "\n…(생략)";
        }
        $본문_esc = addslashes($본문);
        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$본문_esc}', leverage = 0, item = '생타400목록', regdate = NOW()");
      }
    }
  }
}

if (!function_exists('생타연속_자정마감')) {
  function 생타연속_자정마감() {
    $cfg = @db_select('SELECT raw_streak_settled_date FROM config LIMIT 1');
    if (!is_array($cfg)) {
      return;
    }

    $endD = date('Y-m-d', strtotime('-1 day'));
    $last = trim((string)($cfg['raw_streak_settled_date'] ?? ''));

    if ($last === $endD) {
      return;
    }

    if ($last === '') {
      $cursor = $endD;
    } else {
      $cursor = date('Y-m-d', strtotime($last . ' +1 day'));
    }

    if ($cursor > $endD) {
      return;
    }

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
    db_query("UPDATE config SET raw_streak_settled_date = '{$end_esc}' LIMIT 1");
  }
}
