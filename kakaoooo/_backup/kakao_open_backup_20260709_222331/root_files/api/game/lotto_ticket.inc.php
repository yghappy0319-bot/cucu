<?php
/**
 * 로또 추첨 티켓 — tb_member.lotto_ticket / lotto_ticket_level
 * · 레벨 구간별 티켓 지급 (레벨업·백필)
 * · 본방 `.로또 자동 1~50` 시 티켓 1장씩 차감 (게임냥 미차감)
 */

if (!function_exists('로또티켓_레벨구간표')) {
  /** @return array<int, array{to:int, per:int}> */
  function 로또티켓_레벨구간표(): array {
    return [
      ['to' => 10, 'per' => 15],
      ['to' => 30, 'per' => 30],
      ['to' => 100, 'per' => 50],
      ['to' => 150, 'per' => 100],
      ['to' => 200, 'per' => 200],
      ['to' => 300, 'per' => 300],
    ];
  }
}

if (!function_exists('로또티켓_레벨당_장수')) {
  /** 도달 레벨 1회당 지급 티켓 (Lv0=0) */
  function 로또티켓_레벨당_장수(int $level): int {
    if ($level <= 0) {
      return 0;
    }
    foreach (로또티켓_레벨구간표() as $band) {
      if ($level <= (int)$band['to']) {
        return max(0, (int)$band['per']);
      }
    }
    return 300;
  }
}

if (!function_exists('로또티켓_구간_지급합계')) {
  /** paid_level 초과 ~ new_level 까지 누적 지급 장수 */
  function 로또티켓_구간_지급합계(int $paid_level, int $new_level): int {
    if ($new_level <= $paid_level) {
      return 0;
    }
    $total = 0;
    for ($lv = $paid_level + 1; $lv <= $new_level; $lv++) {
      $total += 로또티켓_레벨당_장수($lv);
    }
    return $total;
  }
}

if (!function_exists('로또티켓_레벨업_안내문구')) {
  function 로또티켓_레벨업_안내문구(): string {
    return "🎟️ 로또 티켓 (레벨업 시 지급 · 본방 `.로또 자동 1~50`에 사용)\n"
      . "Lv 1 ~ 10   : 15장\n"
      . "Lv 11 ~ 30  : 30장\n"
      . "Lv 31 ~ 100 : 50장\n"
      . "Lv 101 ~ 150: 100장\n"
      . "Lv 151 ~ 200: 200장\n"
      . "Lv 201 ~ 300: 300장";
  }
}

if (!function_exists('로또티켓_컬럼_확보')) {
  function 로또티켓_컬럼_확보(): void {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }

    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket'");
    if (empty($col)) {
      @db_query("
        ALTER TABLE tb_member
          ADD COLUMN lotto_ticket INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '로또 추첨 티켓 잔량' AFTER level
      ");
    }
    $colLv = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket_level'");
    if (empty($colLv)) {
      @db_query("
        ALTER TABLE tb_member
          ADD COLUMN lotto_ticket_level INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '티켓 지급 완료 레벨(백필·레벨업 추적)' AFTER lotto_ticket
      ");
    }

    $done = true;
    로또티켓_전원_미지급_동기화();
  }
}

if (!function_exists('로또티켓_전원_미지급_동기화')) {
  /** 기존 레벨 대비 미지급 티켓 일괄 반영 (1회·멱등) */
  function 로또티켓_전원_미지급_동기화(): void {
    static $synced = false;
    if ($synced || !function_exists('db_query') || !function_exists('db_fetch')) {
      return;
    }
    $synced = true;

    $rs = @db_query("
      SELECT idx, level, lotto_ticket_level
      FROM tb_member
      WHERE status = 0
        AND level > lotto_ticket_level
    ");
    if (!$rs) {
      return;
    }
    while ($row = db_fetch($rs)) {
      $idx = (int)($row['idx'] ?? 0);
      $level = (int)($row['level'] ?? 0);
      $paid = (int)($row['lotto_ticket_level'] ?? 0);
      if ($idx > 0 && $level > $paid) {
        로또티켓_레벨구간_지급($idx, $paid, $level);
      }
    }
  }
}

if (!function_exists('로또티켓_레벨구간_지급')) {
  /**
   * 레벨 상승·백필 구간 티켓 지급
   * @return int 지급 장수
   */
  function 로또티켓_레벨구간_지급(int $idx, int $paid_level, int $new_level): int {
    로또티켓_컬럼_확보();
    if ($idx <= 0 || $new_level <= $paid_level) {
      return 0;
    }
    $add = 로또티켓_구간_지급합계($paid_level, $new_level);
    if ($add <= 0) {
      return 0;
    }
    db_query("
      UPDATE tb_member
      SET lotto_ticket = lotto_ticket + {$add},
          lotto_ticket_level = {$new_level}
      WHERE idx = {$idx}
      LIMIT 1
    ");
    return $add;
  }
}

if (!function_exists('로또티켓_조회')) {
  function 로또티켓_조회($idx_or_nick): int {
    로또티켓_컬럼_확보();
    if (is_int($idx_or_nick) || ctype_digit((string)$idx_or_nick)) {
      $idx = (int)$idx_or_nick;
      if ($idx <= 0) {
        return 0;
      }
      $row = @db_select("SELECT lotto_ticket FROM tb_member WHERE idx = {$idx} LIMIT 1");
    } else {
      $nick = addslashes(trim((string)$idx_or_nick));
      if ($nick === '') {
        return 0;
      }
      $row = @db_select("SELECT lotto_ticket FROM tb_member WHERE name = '{$nick}' LIMIT 1");
    }
    return max(0, (int)($row['lotto_ticket'] ?? 0));
  }
}

if (!function_exists('로또티켓_가방_표시줄')) {
  function 로또티켓_가방_표시줄(int $idx): string {
    if (function_exists('로또티켓_잔량_조회_가방')) {
      $cnt = 로또티켓_잔량_조회_가방($idx);
    } else {
      $row = @db_select("SELECT lotto_ticket FROM tb_member WHERE idx = {$idx} LIMIT 1");
      $cnt = max(0, (int)($row['lotto_ticket'] ?? 0));
    }
    return "• 로또 티켓 x{$cnt}";
  }
}

if (!function_exists('로또티켓_차감')) {
  /** @return bool */
  function 로또티켓_차감(int $idx, int $장수): bool {
    로또티켓_컬럼_확보();
    $장수 = max(0, (int)$장수);
    if ($idx <= 0 || $장수 <= 0) {
      return false;
    }
    db_query("
      UPDATE tb_member
      SET lotto_ticket = lotto_ticket - {$장수}
      WHERE idx = {$idx}
        AND lotto_ticket >= {$장수}
      LIMIT 1
    ");
    global $conn;
    if (isset($conn) && $conn) {
      return mysqli_affected_rows($conn) > 0;
    }
    $row = @db_select("SELECT lotto_ticket FROM tb_member WHERE idx = {$idx} LIMIT 1");
    return is_array($row);
  }
}
