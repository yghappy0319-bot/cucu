<?php
/**
 * 로또 추첨 티켓 — tb_member_item_bag.`로또티켓`
 * · 레벨 구간별 티켓 지급 (레벨업·백필)
 * · `.로또 구매 N`(최대 100) · `.로또 구매 전부` 시 티켓 차감 (게임냥 미차감)
 * · 지급 추적만 tb_member.lotto_ticket_level 유지
 * · 구 tb_member.lotto_ticket → bag 일괄 이관
 */

if (!defined('BAG_LOTTO_TICKET_ITEM')) {
  define('BAG_LOTTO_TICKET_ITEM', '로또티켓');
}

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
    return "🎟️ 로또 티켓 (레벨업 시 지급 · 홍보방 `.로또 구매 1~100` / `.로또 구매 전부`)\n"
      . "Lv 1 ~ 10   : 15장\n"
      . "Lv 11 ~ 30  : 30장\n"
      . "Lv 31 ~ 100 : 50장\n"
      . "Lv 101 ~ 150: 100장\n"
      . "Lv 151 ~ 200: 200장\n"
      . "Lv 201 ~ 300: 300장";
  }
}

if (!function_exists('로또티켓_가방_로드')) {
  function 로또티켓_가방_로드(): void {
    static $loaded = false;
    if ($loaded) {
      return;
    }
    $loaded = true;
    $bag = __DIR__ . '/../item_bag.inc.php';
    if (!function_exists('item_bag_qty') && is_file($bag)) {
      require_once $bag;
    }
  }
}

if (!function_exists('로또티켓_tb_item_보장')) {
  function 로또티켓_tb_item_보장(): void {
    $esc = addslashes(BAG_LOTTO_TICKET_ITEM);
    $row = @db_select("SELECT idx FROM tb_item WHERE TRIM(sname) = '{$esc}' LIMIT 1");
    if (!empty($row['idx'])) {
      return;
    }
    // tb_item.itemname / sort 가 NOT NULL 인 환경 대응
    $ok = @db_query("
      INSERT INTO tb_item
      SET itemname = '{$esc}',
          sname = '{$esc}',
          buy = 0,
          sell = 0,
          percent = 0,
          buystatus = '1',
          status = '0',
          randum = '0',
          sort = 910
    ");
    if (!$ok) {
      @db_query("
        INSERT INTO tb_item (itemname, sname, buy, sell, percent, buystatus, status, randum, sort)
        VALUES ('{$esc}', '{$esc}', 0, 0, 0, '1', '0', '0', 910)
      ");
    }
    if (function_exists('item_bag_snames_flush')) {
      item_bag_snames_flush();
    }
  }
}

if (!function_exists('로또티켓_회원닉')) {
  function 로또티켓_회원닉(int $idx): string {
    if ($idx <= 0) {
      return '';
    }
    $row = @db_select("SELECT name FROM tb_member WHERE idx = {$idx} LIMIT 1");
    return trim((string)($row['name'] ?? ''));
  }
}

if (!function_exists('로또티켓_회원idx')) {
  function 로또티켓_회원idx(string $nick): int {
    $nick = trim($nick);
    if ($nick === '') {
      return 0;
    }
    $esc = addslashes($nick);
    $row = @db_select("SELECT idx FROM tb_member WHERE name = '{$esc}' LIMIT 1");
    return (int)($row['idx'] ?? 0);
  }
}

if (!function_exists('로또티켓_스키마_확보')) {
  /** 컬럼·가방 스키마만 (전원 스캔 없음) */
  function 로또티켓_스키마_확보(): void {
    static $done = false;
    if ($done || !function_exists('db_select') || !function_exists('db_query')) {
      return;
    }
    $done = true;

    로또티켓_가방_로드();

    $colLv = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket_level'");
    if (empty($colLv)) {
      $after = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket'");
      $afterSql = !empty($after['Field']) ? ' AFTER lotto_ticket' : ' AFTER level';
      @db_query("
        ALTER TABLE tb_member
          ADD COLUMN lotto_ticket_level INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT '티켓 지급 완료 레벨(백필·레벨업 추적)'{$afterSql}
      ");
    }

    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket'");
    if (empty($col)) {
      @db_query("
        ALTER TABLE tb_member
          ADD COLUMN lotto_ticket INT UNSIGNED NOT NULL DEFAULT 0
            COMMENT 'deprecated: bag.로또티켓으로 이관됨' AFTER level
      ");
    }

    로또티켓_tb_item_보장();
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column(BAG_LOTTO_TICKET_ITEM);
    }
  }
}

if (!function_exists('로또티켓_마이그레이션_1회')) {
  /**
   * 구 컬럼 이관·전원 미지급 동기화 — 완료 마커로 재실행 차단
   * (.로또 조회마다 전체 회원 스캔하지 않음)
   */
  function 로또티켓_마이그레이션_1회(): void {
    static $ran = false;
    if ($ran) {
      return;
    }
    $ran = true;

    $flagDir = dirname(__DIR__, 2) . '/data';
    $flagFile = $flagDir . '/lotto_ticket_migrate.done';
    if (is_file($flagFile)) {
      return;
    }

    로또티켓_스키마_확보();
    로또티켓_가방_일괄이관();
    로또티켓_전원_미지급_동기화();

    if (!is_dir($flagDir)) {
      @mkdir($flagDir, 0755, true);
    }
    @file_put_contents($flagFile, date('c') . "\n");
  }
}

if (!function_exists('로또티켓_컬럼_확보')) {
  function 로또티켓_컬럼_확보(): void {
    로또티켓_스키마_확보();
    로또티켓_마이그레이션_1회();
  }
}

if (!function_exists('로또티켓_회원_미지급_반영')) {
  /** 조회/구매 대상 1명만 레벨 구간 티켓 보정 */
  function 로또티켓_회원_미지급_반영(int $idx): void {
    if ($idx <= 0) {
      return;
    }
    로또티켓_스키마_확보();
    $row = @db_select("
      SELECT level, IFNULL(lotto_ticket_level, 0) AS lotto_ticket_level
      FROM tb_member
      WHERE idx = {$idx}
      LIMIT 1
    ");
    if (empty($row)) {
      return;
    }
    $level = (int)($row['level'] ?? 0);
    $paid = (int)($row['lotto_ticket_level'] ?? 0);
    // level만 리셋되고 지급추적(lotto_ticket_level)이 남은 경우 → 0부터 재지급
    if ($paid > $level) {
      @db_query("UPDATE tb_member SET lotto_ticket_level = 0 WHERE idx = {$idx} LIMIT 1");
      $paid = 0;
    }
    if ($level > $paid) {
      로또티켓_레벨구간_지급($idx, $paid, $level);
    }
  }
}

if (!function_exists('로또티켓_가방_일괄이관')) {
  /**
   * tb_member.lotto_ticket → tb_member_item_bag.로또티켓 (멱등)
   * @return int 이관된 회원 수
   */
  function 로또티켓_가방_일괄이관(): int {
    static $ran = false;
    if ($ran || !function_exists('db_query') || !function_exists('db_fetch')) {
      return 0;
    }
    $ran = true;

    로또티켓_가방_로드();
    if (!function_exists('item_bag_add') || !function_exists('item_bag_ensure_column')) {
      return 0;
    }
    item_bag_ensure_column(BAG_LOTTO_TICKET_ITEM);

    $col = @db_select("SHOW COLUMNS FROM tb_member LIKE 'lotto_ticket'");
    if (empty($col['Field'])) {
      return 0;
    }

    $rs = @db_query("
      SELECT idx, name, IFNULL(lotto_ticket, 0) AS lotto_ticket
      FROM tb_member
      WHERE IFNULL(lotto_ticket, 0) > 0
    ");
    if (!$rs) {
      return 0;
    }

    $moved = 0;
    while ($row = db_fetch($rs)) {
      $idx = (int)($row['idx'] ?? 0);
      $nick = trim((string)($row['name'] ?? ''));
      $qty = max(0, (int)($row['lotto_ticket'] ?? 0));
      if ($idx <= 0 || $qty < 1) {
        continue;
      }
      if ($nick === '') {
        $nick = (string)$idx;
      }
      if (!item_bag_ensure_member($idx, $nick)) {
        continue;
      }
      $add = item_bag_add($idx, $nick, BAG_LOTTO_TICKET_ITEM, $qty);
      if (empty($add['ok'])) {
        continue;
      }
      @db_query("UPDATE tb_member SET lotto_ticket = 0 WHERE idx = {$idx} AND IFNULL(lotto_ticket, 0) > 0 LIMIT 1");
      $moved++;
    }
    return $moved;
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
   * 레벨 상승·백필 구간 티켓 지급 (bag)
   * @return int 지급 장수
   */
  function 로또티켓_레벨구간_지급(int $idx, int $paid_level, int $new_level): int {
    로또티켓_스키마_확보();
    if ($idx <= 0 || $new_level <= $paid_level) {
      return 0;
    }
    $add = 로또티켓_구간_지급합계($paid_level, $new_level);
    if ($add <= 0) {
      @db_query("UPDATE tb_member SET lotto_ticket_level = {$new_level} WHERE idx = {$idx} LIMIT 1");
      return 0;
    }

    $nick = 로또티켓_회원닉($idx);
    if ($nick === '') {
      return 0;
    }
    로또티켓_가방_로드();
    if (!function_exists('item_bag_add')) {
      return 0;
    }
    if (function_exists('item_bag_ensure_column')) {
      item_bag_ensure_column(BAG_LOTTO_TICKET_ITEM);
    }
    $r = item_bag_add($idx, $nick, BAG_LOTTO_TICKET_ITEM, $add);
    if (empty($r['ok'])) {
      return 0;
    }
    db_query("
      UPDATE tb_member
      SET lotto_ticket_level = {$new_level}
      WHERE idx = {$idx}
      LIMIT 1
    ");
    return $add;
  }
}

if (!function_exists('로또티켓_조회')) {
  function 로또티켓_조회($idx_or_nick): int {
    // 스키마만 + 해당 회원만 보정 (전원 스캔 금지 — .로또 응답 지연 원인)
    로또티켓_스키마_확보();
    로또티켓_가방_로드();

    $idx = 0;
    if (is_int($idx_or_nick) || ctype_digit((string)$idx_or_nick)) {
      $idx = (int)$idx_or_nick;
    } else {
      $idx = 로또티켓_회원idx((string)$idx_or_nick);
    }
    if ($idx <= 0 || !function_exists('item_bag_qty')) {
      return 0;
    }

    로또티켓_회원_미지급_반영($idx);

    // 개별 hydrate (일괄이관 누락분)
    $legacy = @db_select("SELECT IFNULL(lotto_ticket, 0) AS c, name FROM tb_member WHERE idx = {$idx} LIMIT 1");
    $legacyQty = max(0, (int)($legacy['c'] ?? 0));
    if ($legacyQty > 0 && function_exists('item_bag_add')) {
      $nick = trim((string)($legacy['name'] ?? ''));
      if ($nick === '') {
        $nick = (string)$idx;
      }
      if (item_bag_ensure_member($idx, $nick)) {
        $add = item_bag_add($idx, $nick, BAG_LOTTO_TICKET_ITEM, $legacyQty);
        if (!empty($add['ok'])) {
          @db_query("UPDATE tb_member SET lotto_ticket = 0 WHERE idx = {$idx} LIMIT 1");
        }
      }
    }

    return max(0, (int)item_bag_qty($idx, BAG_LOTTO_TICKET_ITEM));
  }
}

if (!function_exists('로또티켓_가방_표시줄')) {
  function 로또티켓_가방_표시줄(int $idx): string {
    $cnt = function_exists('로또티켓_잔량_조회_가방')
      ? 로또티켓_잔량_조회_가방($idx)
      : 로또티켓_조회($idx);
    return "• 로또 티켓 x{$cnt}";
  }
}

if (!function_exists('로또티켓_차감')) {
  /** @return bool */
  function 로또티켓_차감(int $idx, int $장수): bool {
    로또티켓_스키마_확보();
    $장수 = max(0, (int)$장수);
    if ($idx <= 0 || $장수 <= 0) {
      return false;
    }
    // hydrate 후 차감
    로또티켓_조회($idx);

    $nick = 로또티켓_회원닉($idx);
    if ($nick === '') {
      return false;
    }
    로또티켓_가방_로드();
    if (!function_exists('item_bag_sub')) {
      return false;
    }
    $r = item_bag_sub($idx, $nick, BAG_LOTTO_TICKET_ITEM, $장수);
    return !empty($r['ok']);
  }
}
