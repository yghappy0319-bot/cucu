<?php
/**
 * 강제일방 오픈채팅 링크 풀 (status 0=대기 · 1=사용중)
 * 로테이션: status=0 중 랜덤 1건 배정
 */

if (!function_exists('강일방_테이블보장')) {
  function 강일방_테이블보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS `tb_gangil_room` (
        `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL DEFAULT '' COMMENT '표시명',
        `url` varchar(255) NOT NULL DEFAULT '',
        `status` tinyint NOT NULL DEFAULT 0 COMMENT '0=비어있음 1=사용중',
        `nick1` varchar(30) NOT NULL DEFAULT '',
        `nick2` varchar(30) NOT NULL DEFAULT '',
        `progress_idx` int(11) unsigned DEFAULT NULL,
        `assigned_at` datetime DEFAULT NULL,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`idx`),
        UNIQUE KEY `uk_gangil_room_url` (`url`),
        KEY `ix_gangil_room_status` (`status`, `idx`),
        KEY `ix_gangil_room_progress` (`progress_idx`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    강일방_시드보장();
  }
}

if (!function_exists('강일방_시드보장')) {
  function 강일방_시드보장(): void {
    $seeds = [
      ['강제일방1', 'https://open.kakao.com/o/glbQodHi'],
      ['강제일방2', 'https://open.kakao.com/o/g2c3odHi'],
      ['강제일방3', 'https://open.kakao.com/o/gaYepdHi'],
      ['강제일방4', 'https://open.kakao.com/o/gQOFsdHi'],
      ['강제일방5', 'https://open.kakao.com/o/g0URsdHi'],
    ];
    foreach ($seeds as $s) {
      $name_esc = addslashes($s[0]);
      $url_esc = addslashes($s[1]);
      $row = @db_select("SELECT idx FROM tb_gangil_room WHERE url = '{$url_esc}' LIMIT 1");
      if (!empty($row['idx'])) {
        continue;
      }
      @db_query("
        INSERT INTO tb_gangil_room
        SET name = '{$name_esc}', url = '{$url_esc}', status = 0,
            nick1 = '', nick2 = '', progress_idx = NULL, assigned_at = NULL
      ");
    }
  }
}

if (!function_exists('강일방_입장안내문구')) {
  function 강일방_입장안내문구(string $nick1, string $nick2, string $url): string {
    $nick1 = trim($nick1);
    $nick2 = trim($nick2);
    $url = trim($url);
    return "{$nick1} {$nick2} 강제일방 입장하세요.\n{$url}";
  }
}

if (!function_exists('강일방_사용중목록_문구')) {
  /**
   * 관리방 `.강일` — 강제일방 전체 목록 (사용중/비어있음)
   */
  function 강일방_사용중목록_문구(): string {
    강일방_테이블보장();
    $rs = @db_query("
      SELECT idx, name, url, status, nick1, nick2, progress_idx, assigned_at
      FROM tb_gangil_room
      ORDER BY idx ASC
    ");
    $rows = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $rows[] = $row;
      }
    }
    $cnt = count($rows);
    if ($cnt < 1) {
      return "🏕 강제일방 목록\n\n등록된 방이 없어요.";
    }
    $사용중 = 0;
    foreach ($rows as $row) {
      if ((int)($row['status'] ?? 0) === 1) {
        $사용중++;
      }
    }
    $비어있음 = $cnt - $사용중;
    $msg = "🏕 강제일방 전체 ({$cnt}개 · 사용중 {$사용중} · 비어있음 {$비어있음})\n";
    $n = 0;
    foreach ($rows as $row) {
      $n++;
      $name = trim((string)($row['name'] ?? ''));
      if ($name === '') {
        $name = '강제일방' . (int)($row['idx'] ?? $n);
      }
      $status = (int)($row['status'] ?? 0);
      $사용중인가 = ($status === 1);
      $상태라벨 = $사용중인가 ? '🔴 사용중' : '🟢 비어있음';

      $msg .= "\n{$n}. {$name} · {$상태라벨}\n";

      if ($사용중인가) {
        $nick1 = trim((string)($row['nick1'] ?? ''));
        $nick2 = trim((string)($row['nick2'] ?? ''));
        $who = trim($nick1 . ' · ' . $nick2, ' ·');
        if ($who === '') {
          $who = '(참가자 없음)';
        }
        $msg .= "· {$who}\n";
        $배정 = '';
        if (!empty($row['assigned_at']) && $row['assigned_at'] !== '0000-00-00 00:00:00') {
          $배정 = date('m-d H:i', strtotime((string)$row['assigned_at']));
        }
        $종료 = '';
        $pidx = (int)($row['progress_idx'] ?? 0);
        if ($pidx > 0) {
          $prog = @db_select("SELECT enddate FROM tb_progress WHERE idx = {$pidx} AND status = '강일' LIMIT 1");
          if (!empty($prog['enddate'])) {
            $종료 = date('m-d H:i', strtotime((string)$prog['enddate']));
          }
        }
        if ($배정 !== '') {
          $msg .= "· 배정 {$배정}";
          if ($종료 !== '') {
            $msg .= " · 종료 {$종료}";
          }
          $msg .= "\n";
        } elseif ($종료 !== '') {
          $msg .= "· 종료 {$종료}\n";
        }
      } else {
        $msg .= "· 대기중 (배정 가능)\n";
      }

      $url = trim((string)($row['url'] ?? ''));
      if ($url !== '') {
        $msg .= "· {$url}\n";
      }
    }
    return rtrim($msg);
  }
}

if (!function_exists('강일방_배정')) {
  /**
   * status=0 방 1건을 랜덤 배정(→ status=1)
   * @return array{ok:bool,idx?:int,name?:string,url?:string,msg?:string}
   */
  function 강일방_배정(string $nick1, string $nick2, $progress_idx = null): array {
    global $conn;
    강일방_테이블보장();
    $nick1 = trim($nick1);
    $nick2 = trim($nick2);
    if ($nick1 === '' || $nick2 === '') {
      return ['ok' => false, 'msg' => '닉네임이 없어요.'];
    }
    $n1 = addslashes($nick1);
    $n2 = addslashes($nick2);
    $pidx = ($progress_idx !== null && (int)$progress_idx > 0) ? (int)$progress_idx : 'NULL';

    $began = ($conn instanceof mysqli) && @mysqli_begin_transaction($conn);
    $row = @db_select("
      SELECT idx, name, url
      FROM tb_gangil_room
      WHERE status = 0
      ORDER BY RAND()
      LIMIT 1
      " . ($began ? 'FOR UPDATE' : '')
    );
    if (empty($row['idx'])) {
      if ($began) {
        @mysqli_rollback($conn);
      }
      return ['ok' => false, 'msg' => '비어 있는 강제일방이 없어요.'];
    }
    $idx = (int)$row['idx'];
    $ok = @db_query("
      UPDATE tb_gangil_room
      SET status = 1,
          nick1 = '{$n1}',
          nick2 = '{$n2}',
          progress_idx = {$pidx},
          assigned_at = NOW()
      WHERE idx = {$idx} AND status = 0
      LIMIT 1
    ");
    $chk = @db_select("SELECT status, url, name FROM tb_gangil_room WHERE idx = {$idx} LIMIT 1");
    if (!$ok || (int)($chk['status'] ?? 0) !== 1) {
      if ($began) {
        @mysqli_rollback($conn);
      }
      return ['ok' => false, 'msg' => '강일방 배정에 실패했어요.'];
    }
    if ($began) {
      @mysqli_commit($conn);
    }
    return [
      'ok' => true,
      'idx' => $idx,
      'name' => trim((string)($chk['name'] ?? $row['name'] ?? '')),
      'url' => trim((string)($chk['url'] ?? $row['url'] ?? '')),
    ];
  }
}

if (!function_exists('강일방_해제_progress')) {
  /** progress_idx 기준 방 반환 (status=0) */
  function 강일방_해제_progress($progress_idx): bool {
    $progress_idx = (int)$progress_idx;
    if ($progress_idx <= 0) {
      return false;
    }
    강일방_테이블보장();
    return (bool)@db_query("
      UPDATE tb_gangil_room
      SET status = 0, nick1 = '', nick2 = '', progress_idx = NULL, assigned_at = NULL
      WHERE progress_idx = {$progress_idx}
      LIMIT 1
    ");
  }
}

if (!function_exists('강일방_해제_닉')) {
  /** 진행 참가자 닉으로 사용중 방 반환 */
  function 강일방_해제_닉(array $닉목록): bool {
    강일방_테이블보장();
    $닉목록 = array_values(array_filter(array_map('trim', $닉목록)));
    if ($닉목록 === []) {
      return false;
    }
    $conds = [];
    foreach ($닉목록 as $nn) {
      $esc = addslashes($nn);
      $conds[] = "(nick1 = '{$esc}' OR nick2 = '{$esc}')";
    }
    $where = implode(' OR ', $conds);
    return (bool)@db_query("
      UPDATE tb_gangil_room
      SET status = 0, nick1 = '', nick2 = '', progress_idx = NULL, assigned_at = NULL
      WHERE status = 1 AND ({$where})
    ");
  }
}

if (!function_exists('강일방_해제_진행행')) {
  function 강일방_해제_진행행(array $대상): void {
    $idx = (int)($대상['idx'] ?? 0);
    if ($idx > 0 && 강일방_해제_progress($idx)) {
      return;
    }
    $이름들 = function_exists('강일_진행_참가자목록')
      ? 강일_진행_참가자목록($대상['nick'] ?? '')
      : [];
    if ($이름들 !== []) {
      강일방_해제_닉($이름들);
    }
  }
}

if (!function_exists('강일방_배정및알림')) {
  /**
   * 배정 + 본방·관리방 알림 문구 반환
   * @return array{ok:bool,안내:string,url:string}
   */
  function 강일방_배정및알림(string $nick1, string $nick2, $progress_idx = null): array {
    $배정 = 강일방_배정($nick1, $nick2, $progress_idx);
    if (empty($배정['ok']) || trim((string)($배정['url'] ?? '')) === '') {
      return [
        'ok' => false,
        '안내' => '⚠️ 비어 있는 강제일방이 없어 링크를 배정하지 못했어요.',
        'url' => '',
      ];
    }
    $안내 = 강일방_입장안내문구($nick1, $nick2, (string)$배정['url']);
    if (function_exists('본방알림_등록')) {
      본방알림_등록($안내, '강일방');
    }
    // 관리방에서 이미 echo 하는 경우에는 큐 중복 방지
    $스크립트 = (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    $관리방요청 = (stripos($스크립트, 'info3') !== false);
    if (!$관리방요청 && function_exists('관리방알림_등록')) {
      관리방알림_등록($안내, '강일방');
    }
    return ['ok' => true, '안내' => $안내, 'url' => (string)$배정['url']];
  }
}

/** 관리방(info3) 알림 큐 */
if (!function_exists('관리방알림_테이블_보장')) {
  function 관리방알림_테이블_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("
      CREATE TABLE IF NOT EXISTS tb_info3_alarm (
        idx INT UNSIGNED NOT NULL AUTO_INCREMENT,
        status TINYINT NOT NULL DEFAULT 0 COMMENT '0=대기 1=전송',
        msg TEXT NOT NULL,
        item VARCHAR(64) NOT NULL DEFAULT 'system',
        regdate DATETIME NOT NULL,
        PRIMARY KEY (idx),
        KEY ix_status_reg (status, regdate, idx)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
  }
}

if (!function_exists('관리방알림_등록')) {
  function 관리방알림_등록($msg, $item = 'system') {
    $msg = trim((string)$msg);
    if ($msg === '') {
      return false;
    }
    관리방알림_테이블_보장();
    $msg_esc = addslashes($msg);
    $item_esc = addslashes((string)$item);
    return (bool)@db_query("INSERT INTO tb_info3_alarm SET status = 0, msg = '{$msg_esc}', item = '{$item_esc}', regdate = NOW()");
  }
}

if (!function_exists('관리방알림_큐_꺼내기')) {
  function 관리방알림_큐_꺼내기() {
    global $conn;
    if (!($conn instanceof mysqli)) {
      return null;
    }
    관리방알림_테이블_보장();
    if (!@mysqli_begin_transaction($conn)) {
      return null;
    }
    $row = db_select("SELECT idx, msg FROM tb_info3_alarm WHERE status = 0 ORDER BY regdate ASC, idx ASC LIMIT 1 FOR UPDATE");
    if (empty($row['idx'])) {
      @mysqli_rollback($conn);
      return null;
    }
    $idx = (int)$row['idx'];
    @db_query("UPDATE tb_info3_alarm SET status = 1 WHERE idx = {$idx} LIMIT 1");
    @mysqli_commit($conn);
    $msg = trim((string)($row['msg'] ?? ''));
    return $msg !== '' ? $msg : null;
  }
}

if (!function_exists('관리방알림_큐_응답_시도')) {
  function 관리방알림_큐_응답_시도() {
    $msg = 관리방알림_큐_꺼내기();
    if ($msg === null) {
      return false;
    }
    echo 전송($msg);
    exit;
  }
}
