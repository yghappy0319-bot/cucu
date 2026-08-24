<?php
/**
 * .우리방초기화 / 매월 1일 자동 시즌 초기화
 * - 자산(냥·아이템·금고·커플 등) 유지
 * - 로그·시즌 기록은 "직전 달"만 삭제 (예: 9월 1일 → 8월분)
 */

if (!function_exists('우리방초기화_스키마보장')) {
  function 우리방초기화_스키마보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = @db_select("SHOW COLUMNS FROM config LIKE '우리방초기화월'");
    if (empty($col['Field'])) {
      @db_query("ALTER TABLE config ADD COLUMN `우리방초기화월` VARCHAR(7) NOT NULL DEFAULT '' COMMENT 'YYYY-MM 매월 자동 초기화 완료 마커'");
    }
  }
}

if (!function_exists('우리방초기화_전달구간')) {
  /**
   * @return array{start:string,end:string,label:string,ym:string}
   * start 포함 ~ end 미만 (DATETIME/DATE 공통)
   */
  function 우리방초기화_전달구간(?string $기준일 = null): array {
    $기준 = $기준일 ? strtotime($기준일 . ' 00:00:00') : time();
    if ($기준 === false) {
      $기준 = time();
    }
    $이번달시작_ts = strtotime(date('Y-m-01 00:00:00', $기준));
    $전달시작_ts = strtotime('-1 month', $이번달시작_ts);
    $start = date('Y-m-d', $전달시작_ts);
    $end = date('Y-m-d', $이번달시작_ts);
    return [
      'start' => $start . ' 00:00:00',
      'end' => $end . ' 00:00:00',
      'start_date' => $start,
      'end_date' => $end,
      'label' => date('Y년 n월', $전달시작_ts),
      'ym' => date('Y-m', $전달시작_ts),
    ];
  }
}

if (!function_exists('우리방초기화_완료문구')) {
  function 우리방초기화_완료문구(string $출처 = '수동', string $대상월라벨 = ''): string {
    $대상 = $대상월라벨 !== '' ? $대상월라벨 : '직전 달';
    $머리 = ($출처 === '자동')
      ? "✅ 우리방 자동 초기화 완료 (매월 1일 · {$대상}만 삭제)\n\n"
      : "✅ 우리방 초기화 완료 (매월 시즌 · {$대상}만 삭제)\n\n";
    return $머리
      . "· 회원: title·level·tasu·lotto_ticket_level 초기화 (로또티켓 가방 잔량 유지)\n"
      . "· 로그/시즌 기록: {$대상} 구간만 삭제 (이번 달·그 이전 오래된 달은 유지)\n"
      . "· 대상: 전투/퀴즈/지급로그/홀짝/로또/강화·채굴·거래/강일·투표·초시계·룰렛·보스·tb_msg 등\n\n"
      . "※ 냥·아이템·금고·커플·자숙/모금 진행건은 유지";
  }
}

if (!function_exists('우리방초기화_테이블컬럼존재')) {
  function 우리방초기화_테이블컬럼존재(string $table, string $col): bool {
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $col_esc = addslashes($col);
    $row = @db_select("SHOW COLUMNS FROM `{$table}` LIKE '{$col_esc}'");
    $cache[$key] = !empty($row['Field']);
    return $cache[$key];
  }
}

if (!function_exists('우리방초기화_월삭제')) {
  /**
   * 날짜 컬럼 기준 직전 달 행만 청크 삭제
   */
  function 우리방초기화_월삭제(string $table, string $dateCol, array $구간, int $limit = 50000): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $dateCol = preg_replace('/[^a-zA-Z0-9_]/', '', $dateCol);
    if ($table === '' || $dateCol === '') {
      return false;
    }
    if (!우리방초기화_테이블컬럼존재($table, $dateCol)) {
      return false;
    }

    // DATE 전용 컬럼은 날짜만, DATETIME은 시각 포함
    $colInfo = @db_select("SHOW COLUMNS FROM `{$table}` LIKE '" . addslashes($dateCol) . "'");
    $type = strtolower(trim((string)($colInfo['Type'] ?? '')));
    $isDateOnly = (bool)preg_match('/^date(\s|\(|$)/', $type);
    $start = $isDateOnly ? $구간['start_date'] : $구간['start'];
    $end = $isDateOnly ? $구간['end_date'] : $구간['end'];
    $start_esc = addslashes($start);
    $end_esc = addslashes($end);

    $any = false;
    for ($i = 0; $i < 500; $i++) {
      $ok = @db_query("
        DELETE FROM `{$table}`
        WHERE `{$dateCol}` >= '{$start_esc}'
          AND `{$dateCol}` < '{$end_esc}'
        LIMIT {$limit}
      ");
      if (!$ok) {
        break;
      }
      global $conn;
      $affected = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
      if ($affected > 0) {
        $any = true;
      }
      if ($affected < $limit) {
        break;
      }
    }
    return $any;
  }
}

if (!function_exists('우리방초기화_조인월삭제')) {
  /**
   * 자식 테이블을 부모 날짜 기준으로 삭제
   * 예: tb_boss_raid_player ← tb_boss_raid.created_at
   */
  function 우리방초기화_조인월삭제(
    string $childTable,
    string $childFk,
    string $parentTable,
    string $parentPk,
    string $parentDateCol,
    array $구간,
    int $limit = 50000
  ): bool {
    $childTable = preg_replace('/[^a-zA-Z0-9_]/', '', $childTable);
    $childFk = preg_replace('/[^a-zA-Z0-9_]/', '', $childFk);
    $parentTable = preg_replace('/[^a-zA-Z0-9_]/', '', $parentTable);
    $parentPk = preg_replace('/[^a-zA-Z0-9_]/', '', $parentPk);
    $parentDateCol = preg_replace('/[^a-zA-Z0-9_]/', '', $parentDateCol);
    if ($childTable === '' || $parentTable === '') {
      return false;
    }
    if (!우리방초기화_테이블컬럼존재($parentTable, $parentDateCol)) {
      return false;
    }

    $colInfo = @db_select("SHOW COLUMNS FROM `{$parentTable}` LIKE '" . addslashes($parentDateCol) . "'");
    $type = strtolower(trim((string)($colInfo['Type'] ?? '')));
    $isDateOnly = (bool)preg_match('/^date(\s|\(|$)/', $type);
    $start = $isDateOnly ? $구간['start_date'] : $구간['start'];
    $end = $isDateOnly ? $구간['end_date'] : $구간['end'];
    $start_esc = addslashes($start);
    $end_esc = addslashes($end);

    return (bool)@db_query("
      DELETE c FROM `{$childTable}` c
      INNER JOIN `{$parentTable}` p ON p.`{$parentPk}` = c.`{$childFk}`
      WHERE p.`{$parentDateCol}` >= '{$start_esc}'
        AND p.`{$parentDateCol}` < '{$end_esc}'
    ");
  }
}

if (!function_exists('우리방초기화_실행')) {
  /**
   * @param array{출처?:string,월마커갱신?:bool,기준일?:string} $opts
   * @return array{ok:bool,msg:string,대상월?:string}
   */
  function 우리방초기화_실행(array $opts = []): array {
    $출처 = (string)($opts['출처'] ?? '수동');
    $월마커갱신 = array_key_exists('월마커갱신', $opts)
      ? (bool)$opts['월마커갱신']
      : true;
    $기준일 = isset($opts['기준일']) ? (string)$opts['기준일'] : null;
    $구간 = 우리방초기화_전달구간($기준일);

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @db_query('SET SESSION foreign_key_checks = 0');
    @db_query('SET SESSION unique_checks = 0');

    // 1) 회원 시즌 필드 (새 달 시즌)
    // lotto_ticket_level도 같이 리셋해야 이후 레벨업 로또티켓이 다시 지급됨 (가방 잔량은 유지)
    $회원리셋 = "title = '', level = 0, tasu = 0";
    if (우리방초기화_테이블컬럼존재('tb_member', 'lotto_ticket_level')) {
      $회원리셋 .= ', lotto_ticket_level = 0';
    }
    @db_query("UPDATE tb_member SET {$회원리셋}");

    // 2) 전투·퀴즈·칭호보너스
    우리방초기화_월삭제('tb_question', 'regdate', $구간);
    우리방초기화_월삭제('tb_battle', 'regdate', $구간);
    우리방초기화_월삭제('tb_damege', 'regdate', $구간);
    우리방초기화_월삭제('tb_title_bonus_track', 'last_bonus_date', $구간);

    // 3) 지급·도박 로그
    우리방초기화_월삭제('tb_point_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_odd_even_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_odd_even_profit', 'regdate', $구간);
    우리방초기화_월삭제('tb_odd_even_donate_log', 'regdate', $구간);

    // 4) 로또
    우리방초기화_월삭제('tb_lotto_info', 'regdate', $구간);
    우리방초기화_월삭제('tb_lotto', 'regdate', $구간);
    우리방초기화_월삭제('tb_winner', 'regdate', $구간);

    // 5) 강화·채굴·거래·은총 로그
    우리방초기화_월삭제('tb_enhance_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_mining_ore_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_mining_ore_find', 'found_at', $구간);
    우리방초기화_월삭제('tb_item_trade_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_eunchong_buy_log', 'regdate', $구간);

    // 6) 기타 시즌 로그
    우리방초기화_월삭제('tb_gangil_log', 'closed_at', $구간);
    우리방초기화_월삭제('tb_curious', 'regdate', $구간);
    우리방초기화_월삭제('tb_sadari', 'regdate', $구간);
    우리방초기화_조인월삭제('tb_vote_ballot', 'vote_idx', 'tb_vote', 'idx', 'started_at', $구간);
    우리방초기화_월삭제('tb_vote', 'started_at', $구간);
    우리방초기화_조인월삭제('tb_stopwatch_entry', 'round_id', 'tb_stopwatch_round', 'idx', 'opened_at', $구간);
    우리방초기화_월삭제('tb_stopwatch_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_stopwatch_round', 'opened_at', $구간);
    우리방초기화_월삭제('tb_rand_gangil_used', 'regdate', $구간);
    우리방초기화_월삭제('tb_rand_gangil_result', 'regdate', $구간);
    우리방초기화_월삭제('tb_ilbang_ticket_log', 'regdate', $구간);
    우리방초기화_월삭제('tb_kkomaen_rank', 'play_date', $구간);
    우리방초기화_월삭제('tb_attendance_roulette_grant', 'spin_date', $구간);
    우리방초기화_월삭제('tb_attendance_roulette', 'spin_date', $구간);
    우리방초기화_월삭제('tb_info2_alarm', 'regdate', $구간);

    // 7) 보스 레이드 (자식 → 부모)
    우리방초기화_조인월삭제('tb_boss_raid_hit', 'boss_idx', 'tb_boss_raid', 'idx', 'created_at', $구간);
    우리방초기화_조인월삭제('tb_boss_raid_player', 'boss_idx', 'tb_boss_raid', 'idx', 'created_at', $구간);
    우리방초기화_조인월삭제('tb_boss_raid_counter', 'boss_idx', 'tb_boss_raid', 'idx', 'created_at', $구간);
    우리방초기화_조인월삭제('tb_boss_raid_drop', 'boss_idx', 'tb_boss_raid', 'idx', 'created_at', $구간);
    // hit/counter/drop 자체 날짜도 있으면 보조 삭제
    우리방초기화_월삭제('tb_boss_raid_hit', 'regdate', $구간);
    우리방초기화_월삭제('tb_boss_raid_counter', 'regdate', $구간);
    우리방초기화_월삭제('tb_boss_raid_drop', 'created_at', $구간);
    우리방초기화_월삭제('tb_boss_raid', 'created_at', $구간);

    // 8) 타수(msg) 맨 마지막
    우리방초기화_월삭제('tb_msg', 'regdate', $구간);

    @db_query('SET SESSION foreign_key_checks = 1');
    @db_query('SET SESSION unique_checks = 1');

    if ($월마커갱신) {
      우리방초기화_스키마보장();
      $월 = addslashes(date('Y-m'));
      @db_query("UPDATE config SET `우리방초기화월` = '{$월}' LIMIT 1");
    }

    return [
      'ok' => true,
      'msg' => 우리방초기화_완료문구($출처, $구간['label']),
      '대상월' => $구간['ym'],
    ];
  }
}

if (!function_exists('우리방초기화_월간자동_시도')) {
  /**
   * 매월 1일 1회 자동 실행. 이미 해당 월 마커가 있으면 no-op.
   * @return array{ran:bool,msg?:string}
   */
  function 우리방초기화_월간자동_시도(): array {
    static $checked = false;
    if ($checked) {
      return ['ran' => false];
    }
    $checked = true;

    if ((int)date('j') !== 1) {
      return ['ran' => false];
    }
    if (!function_exists('db_query') || !function_exists('db_select')) {
      return ['ran' => false];
    }

    우리방초기화_스키마보장();
    $이번달 = date('Y-m');
    $행 = @db_select("SELECT `우리방초기화월` FROM config LIMIT 1");
    if (trim((string)($행['우리방초기화월'] ?? '')) === $이번달) {
      return ['ran' => false];
    }

    $lock = @db_select("SELECT GET_LOCK('room_reset_monthly', 0) AS ok");
    if (empty($lock['ok'])) {
      return ['ran' => false];
    }

    try {
      $행2 = @db_select("SELECT `우리방초기화월` FROM config LIMIT 1");
      if (trim((string)($행2['우리방초기화월'] ?? '')) === $이번달) {
        return ['ran' => false];
      }

      $결과 = 우리방초기화_실행(['출처' => '자동', '월마커갱신' => true]);
      $msg = (string)($결과['msg'] ?? '');

      @db_query("INSERT INTO cron_log SET content = 'auto_room_reset', regdate = NOW()");
      return ['ran' => true, 'msg' => $msg];
    } finally {
      @db_query("SELECT RELEASE_LOCK('room_reset_monthly')");
    }
  }
}
