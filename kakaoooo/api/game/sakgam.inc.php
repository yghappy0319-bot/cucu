<?php
/**
 * .삭감 — 대상 닉 게임냥(point) N% 삭감 (관리자, info1·info3)
 * .게임냥삭감 0/00/000 — 전원 point 뒤 N자리 일괄 삭제 (마이너스 포함)
 * .본방냥삭감 0/00/000 — 전원 newpoint 뒤 N자리 일괄 삭제
 *   (+ 채굴기 mining_pending · 마켓 price_newpoint 선매입가 동일 적용)
 * .채굴냥삭감 0/00/000 — 채굴기 적립(mining_pending)만 뒤 N자리 삭제 (본방냥 미변경)
 * .선매입삭감 0/00/000 — 마켓 선매입 본방냥(price_newpoint)만 뒤 N자리 삭제
 */

if (!function_exists('삭감_명령_처리')) {
  function 삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    global $단위;

    $호출닉 = function_exists('getTwoCharNick') ? getTwoCharNick((string)$nick) : '';
    $관리자여부 = in_array($두자리닉넴, $관리자, true)
      || ($호출닉 !== '' && in_array($호출닉, $관리자, true));
    if (!$관리자여부) {
      echo 전송("🔒");
      exit;
    }

    if (!preg_match('/\.삭감\s+([가-힣A-Za-z0-9_]+)\s+(\d+)\s*$/u', trim((string)$status), $m)) {
      echo 전송("❌ 사용법: .삭감 닉네임 퍼센트 (예: .삭감 길동 80)");
      exit;
    }

    $대상닉 = trim($m[1]);
    $퍼센트 = (int)$m[2];
    if ($퍼센트 < 1 || $퍼센트 > 100) {
      echo 전송("❌ 퍼센트는 1~100 사이로 입력해주세요. (예: .삭감 우주 80)");
      exit;
    }

    $대상닉_esc = addslashes($대상닉);
    $대상 = db_select("SELECT idx, name, point FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
    if (empty($대상['idx'])) {
      echo 전송("❌ 존재하지 않는 사용자예요.");
      exit;
    }

    $보유냥 = (int)$대상['point'];
    $삭감액 = (int)floor($보유냥 * $퍼센트 / 100);
    $금액표시 = function_exists('냥축약표시')
      ? static function ($n) { return number_format($n) . "냥"; }
      : static function ($n) use ($단위) { return number_format((int)$n) . $단위; };

    if ($삭감액 <= 0) {
      echo 전송("✅ {$대상닉} 보유 냥이 없거나 삭감할 만큼 없어요. (보유: " . $금액표시($보유냥) . ")");
      exit;
    }

    db_query("UPDATE tb_member SET point = point - {$삭감액} WHERE name = '{$대상닉_esc}'");
    지급로그('삭감', $두자리닉넴, $대상닉, 0, $삭감액);
    echo 전송(
      "✅ {$대상닉} 냥 {$퍼센트}% 삭감 완료.\n"
      . "보유: " . $금액표시($보유냥) . " → " . $금액표시($보유냥 - $삭감액)
      . " (-" . $금액표시($삭감액) . ")"
    );
    exit;
  }
}

if (!function_exists('냥삭감_일괄_명령_처리')) {
  /**
   * .게임냥삭감 / .본방냥삭감 공통
   * @param string $명령어 게임냥삭감|본방냥삭감
   * @param string $컬럼 point|newpoint
   * @param string $라벨 게임냥|본방냥
   */
  function 냥삭감_일괄_명령_처리($status, $두자리닉넴, $nick, array $관리자, string $명령어, string $컬럼, string $라벨) {
    global $단위;

    $호출닉 = function_exists('getTwoCharNick') ? getTwoCharNick((string)$nick) : '';
    $관리자여부 = in_array($두자리닉넴, $관리자, true)
      || ($호출닉 !== '' && in_array($호출닉, $관리자, true));
    if (!$관리자여부) {
      echo 전송("🔒");
      exit;
    }

    if ($컬럼 !== 'point' && $컬럼 !== 'newpoint') {
      echo 전송("❌ 내부 오류: 허용되지 않은 컬럼입니다.");
      exit;
    }

    $입력 = trim((string)$status);
    $cmdEsc = preg_quote($명령어, '/');
    if (!preg_match('/^\.' . $cmdEsc . '\s+(0+)\s*$/u', $입력, $m)) {
      echo 전송(
        "❌ 사용법: .{$명령어} 0 (또는 00 / 000 …)\n"
        . "예) .{$명령어} 0 → 전원 {$라벨} 뒤 1자리 삭제 (÷10)\n"
        . "예) .{$명령어} 000 → 뒤 3자리 삭제 (÷1000)\n"
        . "※ 마이너스 계정 포함 · 전원 일괄"
      );
      exit;
    }

    $자리 = strlen($m[1]);
    if ($자리 < 1 || $자리 > 18) {
      echo 전송("❌ 0은 1~18개까지 가능해요. (예: .{$명령어} 0 ~ .{$명령어} " . str_repeat('0', 18) . ")");
      exit;
    }

    if ($컬럼 === 'point' && function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }

    $div = '1' . str_repeat('0', $자리); // 10^N
    $합정규 = static function ($raw): string {
      $s = trim((string)$raw);
      $neg = isset($s[0]) && $s[0] === '-';
      $digits = function_exists('냥_정수문자열')
        ? 냥_정수문자열($s)
        : (ltrim(preg_replace('/\D/', '', $s) ?: '0', '0') ?: '0');
      if ($digits === '0') {
        return '0';
      }
      return $neg ? ('-' . $digits) : $digits;
    };
    $표시 = static function ($v) use ($단위, $라벨): string {
      $s = (string)$v;
      $neg = isset($s[0]) && $s[0] === '-';
      $abs = $neg ? substr($s, 1) : $s;
      if ($라벨 === '본방냥' && function_exists('newpoint표시')) {
        $t = newpoint표시($abs);
        // newpoint표시가 이미 단위를 붙이는 경우도 있어 숫자만이면 단위 추가
        if ($t !== '' && !preg_match('/냥$/u', $t)) {
          $t .= ($단위 ?: '냥');
        }
        return ($neg ? '-' : '') . $t;
      }
      if (function_exists('강화비용_표시')) {
        return ($neg ? '-' : '') . 강화비용_표시($abs, $단위 ?: '냥');
      }
      if (function_exists('게임냥_안전표시')) {
        return ($neg ? '-' : '') . 게임냥_안전표시($abs, $단위 ?: '냥');
      }
      return ($neg ? '-' : '') . (function_exists('냥_숫자콤마') ? 냥_숫자콤마($abs) : $abs) . ($단위 ?: '냥');
    };

    // newpoint는 소수 가능 → DECIMAL(65,4) / point는 DECIMAL(65,0)
    $cast = ($컬럼 === 'newpoint')
      ? "CAST(IFNULL(`{$컬럼}`, 0) AS DECIMAL(65,4))"
      : "CAST(IFNULL(`{$컬럼}`, 0) AS DECIMAL(65,0))";

    $전 = db_select("
      SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(CASE WHEN {$cast} < 0 THEN 1 ELSE 0 END), 0) AS neg_cnt
      FROM tb_member
    ");
    $전합행 = db_select("
      SELECT CAST(COALESCE(SUM({$cast}), 0) AS CHAR) AS sum_v
      FROM tb_member
    ");
    $전합 = $합정규($전합행['sum_v'] ?? '0');
    $전체인원 = (int)($전['cnt'] ?? 0);
    $마이너스수 = (int)($전['neg_cnt'] ?? 0);

    // 부호 유지 · 0 방향 절삭 (뒤 N자리 삭제 = ÷10^N)
    $ok = db_query("
      UPDATE tb_member
      SET `{$컬럼}` = TRUNCATE({$cast} / {$div}, 0)
    ");
    if (!$ok) {
      echo 전송("❌ {$라벨} 일괄 삭감에 실패했어요. DB를 확인해 주세요.");
      exit;
    }

    global $conn;
    $변경행 = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;

    $후합행 = db_select("
      SELECT CAST(COALESCE(SUM({$cast}), 0) AS CHAR) AS sum_v
      FROM tb_member
    ");
    $후합 = $합정규($후합행['sum_v'] ?? '0');

    $부가문구 = [];

    // 본방냥삭감 시: 채굴기 적립(mining_pending) · 마켓 선매입가(price_newpoint) 동일 ÷10^N
    if ($컬럼 === 'newpoint') {
      $채굴결과 = 냥삭감_채굴적립_일괄($div, $자리, $표시, $합정규);
      if ($채굴결과 !== '') {
        $부가문구[] = $채굴결과;
      }
      $마켓결과 = 냥삭감_마켓선매입_일괄($div, $자리, $표시, $합정규);
      if ($마켓결과 !== '') {
        $부가문구[] = $마켓결과;
      }
    }

    if (function_exists('지급로그')) {
      지급로그($라벨 . '삭감×' . $자리, (string)$두자리닉넴, '전체', 0, $자리);
    }

    if (function_exists('시세기준_스냅샷_갱신')) {
      @시세기준_스냅샷_갱신(true);
    }
    if (function_exists('시세기준_스냅샷_캐시_초기화')) {
      시세기준_스냅샷_캐시_초기화();
    }

    $msg = "✅ {$라벨} 뒤 {$자리}자리 일괄 삭제 완료 (÷{$div})\n";
    $msg .= "대상: 전원 {$전체인원}명 (마이너스 {$마이너스수}명 포함)\n";
    $msg .= "DB 변경행: {$변경행}\n";
    $msg .= "총합: " . $표시($전합) . " → " . $표시($후합);
    if ($부가문구 !== []) {
      $msg .= "\n" . implode("\n", $부가문구);
    }
    echo 전송($msg);
    exit;
  }
}

if (!function_exists('냥삭감_채굴적립_일괄')) {
  /** tb_member_mining.mining_pending ÷ div */
  function 냥삭감_채굴적립_일괄(string $div, int $자리, callable $표시, callable $합정규): string {
    $storage = __DIR__ . '/mining_storage.inc.php';
    if (is_file($storage)) {
      require_once $storage;
    }
    if (function_exists('mining_data_ensure_table')) {
      mining_data_ensure_table();
    }
    $tbl = defined('MINING_TABLE') ? MINING_TABLE : 'tb_member_mining';
    $exists = @db_select("SHOW TABLES LIKE '" . addslashes($tbl) . "'");
    if (empty($exists)) {
      return '';
    }
    $scale = defined('MINING_PENDING_SCALE') ? max(0, (int)MINING_PENDING_SCALE) : 10;
    $전 = db_select("
      SELECT
        COUNT(*) AS cnt,
        CAST(COALESCE(SUM(CAST(IFNULL(mining_pending, 0) AS DECIMAL(65,{$scale}))), 0) AS CHAR) AS sum_v
      FROM `{$tbl}`
      WHERE CAST(IFNULL(mining_pending, 0) AS DECIMAL(65,{$scale})) <> 0
    ");
    $전합 = $합정규($전['sum_v'] ?? '0');
    $건수 = (int)($전['cnt'] ?? 0);
    if ($건수 <= 0 && $전합 === '0') {
      return "⛏ 채굴기 적립: 변경 없음";
    }
    $ok = @db_query("
      UPDATE `{$tbl}`
      SET mining_pending = TRUNCATE(CAST(IFNULL(mining_pending, 0) AS DECIMAL(65,{$scale})) / {$div}, {$scale}),
          mining_sync_at = NOW()
      WHERE CAST(IFNULL(mining_pending, 0) AS DECIMAL(65,{$scale})) <> 0
    ");
    if (!$ok) {
      return "⛏ 채굴기 적립: 실패";
    }
    global $conn;
    $변경 = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : $건수;
    $후 = db_select("
      SELECT CAST(COALESCE(SUM(CAST(IFNULL(mining_pending, 0) AS DECIMAL(65,{$scale}))), 0) AS CHAR) AS sum_v
      FROM `{$tbl}`
    ");
    $후합 = $합정규($후['sum_v'] ?? '0');
    return "⛏ 채굴기 적립(÷10^{$자리}): {$건수}명 · " . $표시($전합) . " → " . $표시($후합) . " (변경 {$변경})";
  }
}

if (!function_exists('냥삭감_마켓선매입_일괄')) {
  /** tb_gifticon.price_newpoint ÷ div (선매입 본방냥 고정가) */
  function 냥삭감_마켓선매입_일괄(string $div, int $자리, callable $표시, callable $합정규): string {
    $exists = @db_select("SHOW TABLES LIKE 'tb_gifticon'");
    if (empty($exists)) {
      return '';
    }
    $col = @db_select("SHOW COLUMNS FROM tb_gifticon LIKE 'price_newpoint'");
    if (empty($col['Field'])) {
      return '';
    }
    $전 = db_select("
      SELECT
        COUNT(*) AS cnt,
        CAST(COALESCE(SUM(CAST(IFNULL(price_newpoint, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS sum_v
      FROM tb_gifticon
      WHERE CAST(IFNULL(price_newpoint, 0) AS DECIMAL(65,0)) > 0
    ");
    $전합 = $합정규($전['sum_v'] ?? '0');
    $건수 = (int)($전['cnt'] ?? 0);
    if ($건수 <= 0) {
      return "🛒 마켓 선매입가: 변경 없음";
    }
    $ok = @db_query("
      UPDATE tb_gifticon
      SET price_newpoint = TRUNCATE(CAST(IFNULL(price_newpoint, 0) AS DECIMAL(65,0)) / {$div}, 0)
      WHERE CAST(IFNULL(price_newpoint, 0) AS DECIMAL(65,0)) > 0
    ");
    if (!$ok) {
      return "🛒 마켓 선매입가: 실패";
    }
    global $conn;
    $변경 = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : $건수;
    $후 = db_select("
      SELECT CAST(COALESCE(SUM(CAST(IFNULL(price_newpoint, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS sum_v
      FROM tb_gifticon
    ");
    $후합 = $합정규($후['sum_v'] ?? '0');
    return "🛒 마켓 선매입 본방냥(÷10^{$자리}): {$건수}건 · " . $표시($전합) . " → " . $표시($후합) . " (변경 {$변경})";
  }
}

if (!function_exists('게임냥삭감_명령_처리')) {
  function 게임냥삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    냥삭감_일괄_명령_처리($status, $두자리닉넴, $nick, $관리자, '게임냥삭감', 'point', '게임냥');
  }
}

if (!function_exists('본방냥삭감_명령_처리')) {
  function 본방냥삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    냥삭감_일괄_명령_처리($status, $두자리닉넴, $nick, $관리자, '본방냥삭감', 'newpoint', '본방냥');
  }
}

if (!function_exists('냥삭감_부가_자리파싱')) {
  /** @return array{0:string,1:int}|null [div, 자리] */
  function 냥삭감_부가_자리파싱(string $status, string $명령어): ?array {
    $입력 = trim($status);
    $cmdEsc = preg_quote($명령어, '/');
    if (!preg_match('/^\.' . $cmdEsc . '\s+(0+)\s*$/u', $입력, $m)) {
      return null;
    }
    $자리 = strlen($m[1]);
    if ($자리 < 1 || $자리 > 18) {
      return null;
    }
    return ['1' . str_repeat('0', $자리), $자리];
  }
}

if (!function_exists('냥삭감_부가_표시헬퍼')) {
  /** @return array{0:callable,1:callable} [$표시, $합정규] */
  function 냥삭감_부가_표시헬퍼(): array {
    global $단위;
    $합정규 = static function ($raw): string {
      $s = trim((string)$raw);
      $neg = isset($s[0]) && $s[0] === '-';
      $digits = function_exists('냥_정수문자열')
        ? 냥_정수문자열($s)
        : (ltrim(preg_replace('/\D/', '', $s) ?: '0', '0') ?: '0');
      if ($digits === '0') {
        return '0';
      }
      return $neg ? ('-' . $digits) : $digits;
    };
    $표시 = static function ($v) use ($단위): string {
      $s = (string)$v;
      $neg = isset($s[0]) && $s[0] === '-';
      $abs = $neg ? substr($s, 1) : $s;
      if (function_exists('newpoint표시')) {
        $t = newpoint표시($abs);
        if ($t !== '' && !preg_match('/냥$/u', $t)) {
          $t .= ($단위 ?: '냥');
        }
        return ($neg ? '-' : '') . $t;
      }
      if (function_exists('강화비용_표시')) {
        return ($neg ? '-' : '') . 강화비용_표시($abs, $단위 ?: '냥');
      }
      return ($neg ? '-' : '') . (function_exists('냥_숫자콤마') ? 냥_숫자콤마($abs) : $abs) . ($단위 ?: '냥');
    };
    return [$표시, $합정규];
  }
}

if (!function_exists('냥삭감_부가_관리자확인')) {
  function 냥삭감_부가_관리자확인($두자리닉넴, $nick, array $관리자): bool {
    $호출닉 = function_exists('getTwoCharNick') ? getTwoCharNick((string)$nick) : '';
    return in_array($두자리닉넴, $관리자, true)
      || ($호출닉 !== '' && in_array($호출닉, $관리자, true));
  }
}

if (!function_exists('채굴냥삭감_명령_처리')) {
  /** 본방 newpoint는 건드리지 않고 채굴기 적립만 ÷10^N */
  function 채굴냥삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    if (!냥삭감_부가_관리자확인($두자리닉넴, $nick, $관리자)) {
      echo 전송("🔒");
      exit;
    }
    $parsed = 냥삭감_부가_자리파싱((string)$status, '채굴냥삭감');
    if ($parsed === null) {
      echo 전송(
        "❌ 사용법: .채굴냥삭감 0 (또는 00 / 000 …)\n"
        . "예) .채굴냥삭감 0000 → 채굴기 적립 뒤 4자리 삭제 (÷10000)\n"
        . "※ 회원 본방냥(newpoint)은 변경하지 않음"
      );
      exit;
    }
    [$div, $자리] = $parsed;
    [$표시, $합정규] = 냥삭감_부가_표시헬퍼();
    $결과 = 냥삭감_채굴적립_일괄($div, $자리, $표시, $합정규);
    if ($결과 === '') {
      echo 전송("❌ 채굴 테이블을 찾을 수 없어요.");
      exit;
    }
    if (function_exists('지급로그')) {
      지급로그('채굴냥삭감×' . $자리, (string)$두자리닉넴, '채굴적립', 0, $자리);
    }
    echo 전송("✅ 채굴기 적립 뒤 {$자리}자리 일괄 삭제 완료 (÷{$div})\n" . $결과);
    exit;
  }
}

if (!function_exists('선매입삭감_명령_처리')) {
  /** 본방 newpoint는 건드리지 않고 마켓 선매입가만 ÷10^N */
  function 선매입삭감_명령_처리($status, $두자리닉넴, $nick, array $관리자) {
    if (!냥삭감_부가_관리자확인($두자리닉넴, $nick, $관리자)) {
      echo 전송("🔒");
      exit;
    }
    $parsed = 냥삭감_부가_자리파싱((string)$status, '선매입삭감');
    if ($parsed === null) {
      echo 전송(
        "❌ 사용법: .선매입삭감 0 (또는 00 / 000 …)\n"
        . "예) .선매입삭감 0000 → 마켓 선매입 본방냥 뒤 4자리 삭제 (÷10000)\n"
        . "※ 회원 본방냥(newpoint)은 변경하지 않음"
      );
      exit;
    }
    [$div, $자리] = $parsed;
    [$표시, $합정규] = 냥삭감_부가_표시헬퍼();
    $결과 = 냥삭감_마켓선매입_일괄($div, $자리, $표시, $합정규);
    if ($결과 === '') {
      echo 전송("❌ 마켓(선매입가) 테이블/컬럼을 찾을 수 없어요.");
      exit;
    }
    if (function_exists('지급로그')) {
      지급로그('선매입삭감×' . $자리, (string)$두자리닉넴, '마켓선매입', 0, $자리);
    }
    echo 전송("✅ 마켓 선매입 본방냥 뒤 {$자리}자리 일괄 삭제 완료 (÷{$div})\n" . $결과);
    exit;
  }
}
