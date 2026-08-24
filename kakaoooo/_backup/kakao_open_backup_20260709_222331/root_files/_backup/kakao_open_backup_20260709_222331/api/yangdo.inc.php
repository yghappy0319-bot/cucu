<?php
/**
 * `.양도` — info2 게임냥(point) 양도
 * tb_point_log (status=양도, 오늘 날짜) 기준 하루 1회 + 지호 추가
 */

if (!function_exists('yangdo_db_준비')) {
  function yangdo_db_준비(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    if (function_exists('date_default_timezone_set')) {
      @date_default_timezone_set('Asia/Seoul');
    }
  }
}

if (!function_exists('yangdo_닉_정규화')) {
  function yangdo_닉_정규화(string $닉): string {
    yangdo_db_준비();
    static $cache = [];
    $key = trim($닉);
    if ($key === '') {
      return '';
    }
    if (isset($cache[$key])) {
      return $cache[$key];
    }

    $parsed = $key;
    if (function_exists('getTwoCharNick')) {
      $two = getTwoCharNick($key);
      if ($two !== '') {
        $parsed = $two;
      }
    }

    $parsed_esc = addslashes($parsed);
    $row = db_select("SELECT name FROM tb_member WHERE name = '{$parsed_esc}' LIMIT 1");
    if (empty($row['name'])) {
      $row = db_select("SELECT name FROM tb_member WHERE TRIM(name) = '{$parsed_esc}' LIMIT 1");
    }
    $result = !empty($row['name']) ? trim((string)$row['name']) : $parsed;
    $cache[$key] = $result;
    if ($parsed !== $key) {
      $cache[$parsed] = $result;
    }
    return $result;
  }
}

if (!function_exists('yangdo_받는이_조회')) {
  /** 받는이 정규화 + 회원 존재 확인 (1회 쿼리) */
  function yangdo_받는이_조회(string $receiver): ?array {
    yangdo_db_준비();
    static $cache = [];
    $key = trim($receiver);
    if ($key === '') {
      return null;
    }
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $parsed = $key;
    if (function_exists('getTwoCharNick')) {
      $two = getTwoCharNick($key);
      if ($two !== '') {
        $parsed = $two;
      }
    }
    $parsed_esc = addslashes($parsed);
    $row = db_select("SELECT idx, name FROM tb_member WHERE name = '{$parsed_esc}' LIMIT 1");
    if (empty($row['idx'])) {
      $row = db_select("SELECT idx, name FROM tb_member WHERE TRIM(name) = '{$parsed_esc}' LIMIT 1");
    }
    if (empty($row['idx'])) {
      $cache[$key] = null;
      return null;
    }

    $result = [
      'idx' => (int)$row['idx'],
      'name' => trim((string)$row['name']),
    ];
    $cache[$key] = $result;
    $cache[$parsed] = $result;
    $cache[$result['name']] = $result;
    return $result;
  }
}

if (!function_exists('yangdo_오늘완료수')) {
  /** tb_point_log — status=양도, MySQL 오늘(CURDATE) 기준 (인덱스 활용) */
  function yangdo_오늘완료수(string $닉): int {
    yangdo_db_준비();
    static $cache = [];
    $닉 = trim($닉);
    if ($닉 === '') {
      return 0;
    }
    if (isset($cache[$닉])) {
      return $cache[$닉];
    }

    $닉_esc = addslashes($닉);
    $row = db_select("
      SELECT COUNT(*) AS cnt
      FROM tb_point_log
      WHERE status = '양도'
        AND nick = '{$닉_esc}'
        AND regdate >= CURDATE()
        AND regdate < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
    $cnt = (int)($row['cnt'] ?? 0);
    $cache[$닉] = $cnt;
    return $cnt;
  }
}

if (!function_exists('yangdo_지호_정보')) {
  /** 지호 1회 조회 — 적용 여부·남은 시간·차감용 idx */
  function yangdo_지호_정보(string $닉): array {
    yangdo_db_준비();
    static $cache = [];
    $닉 = trim($닉);
    if ($닉 === '') {
      return ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
    }
    if (isset($cache[$닉])) {
      return $cache[$닉];
    }

    $닉_esc = addslashes($닉);
    $row = db_select("
      SELECT idx, enddate
      FROM tb_item_use
      WHERE nickname = '{$닉_esc}' AND item = '지호' AND enddate > NOW()
      ORDER BY enddate DESC
      LIMIT 1
    ");
    if (empty($row['idx'])) {
      $info = ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $ts = strtotime((string)$row['enddate']);
    if ($ts === false || $ts <= time()) {
      $info = ['적용중' => false, '남은시간' => 0, 'idx' => 0, 'enddate_ts' => null];
      $cache[$닉] = $info;
      return $info;
    }

    $info = [
      '적용중' => true,
      '남은시간' => (int)floor(($ts - time()) / 3600),
      'idx' => (int)$row['idx'],
      'enddate_ts' => $ts,
    ];
    $cache[$닉] = $info;
    return $info;
  }
}

if (!function_exists('yangdo_amount_sql')) {
  /** tb_point_log / tb_member UPDATE용 — INT 캐스트 없이 숫자 문자열만 */
  function yangdo_amount_sql($n): string {
    $s = preg_replace('/[^\d]/', '', (string)$n);
    return $s === '' ? '0' : $s;
  }
}

if (!function_exists('yangdo_amount_gte')) {
  function yangdo_amount_gte($a, $b): bool {
    $as = yangdo_amount_sql($a);
    $bs = yangdo_amount_sql($b);
    if ($as === '0' && $bs === '0') {
      return true;
    }
    if (function_exists('bccomp')) {
      return bccomp($as, $bs, 0) >= 0;
    }
    return (float)$as >= (float)$bs;
  }
}

if (!function_exists('yangdo_db_last_error')) {
  function yangdo_db_last_error(): string {
    global $conn;
    return ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
  }
}

if (!function_exists('yangdo_양도_로그_기록')) {
  /** 경량 로그 — mypoint SELECT 생략 (홀짝·채굴과 동일 패턴) */
  function yangdo_양도_로그_기록(string $닉, string $receiver, int $수수료, int $보낸양): bool {
    $닉_esc = addslashes(trim($닉));
    $받는_esc = addslashes(trim($receiver));
    if ($닉_esc === '') {
      return false;
    }
    $수수료_sql = yangdo_amount_sql($수수료);
    $보낸양_sql = yangdo_amount_sql($보낸양);
    if ($보낸양_sql === '0') {
      return false;
    }

    $ok = (bool)db_query("
      INSERT INTO tb_point_log
      SET status = '양도',
          nick = '{$닉_esc}',
          receiver = '{$받는_esc}',
          tax = {$수수료_sql},
          point = {$보낸양_sql},
          regdate = NOW()
    ");
    if (!$ok) {
      return false;
    }
    db_query("
      INSERT INTO tb_point_log
      SET status = '수수료',
          nick = '{$닉_esc}',
          receiver = '',
          tax = {$수수료_sql},
          point = {$수수료_sql},
          regdate = NOW()
    ");
    return true;
  }
}

if (!function_exists('yangdo_지호_enddate_ts')) {
  function yangdo_지호_enddate_ts(string $닉): ?int {
    $info = yangdo_지호_정보($닉);
    return $info['enddate_ts'];
  }
}

if (!function_exists('yangdo_지호적용중')) {
  function yangdo_지호적용중(string $닉): bool {
    return yangdo_지호_정보($닉)['적용중'];
  }
}

if (!function_exists('yangdo_지호_남은시간_시간')) {
  function yangdo_지호_남은시간_시간(string $닉): int {
    return yangdo_지호_정보($닉)['남은시간'];
  }
}

if (!function_exists('yangdo_일일제한_검사')) {
  function yangdo_일일제한_검사(string $닉, ?int $완료수 = null, ?array $지호 = null): ?string {
    $닉 = trim($닉);
    $완료수 = $완료수 ?? yangdo_오늘완료수($닉);
    if ($완료수 < 1) {
      return null;
    }

    $지호 = $지호 ?? yangdo_지호_정보($닉);
    if ($지호['남은시간'] >= 1 && $지호['적용중']) {
      return null;
    }

    return "❌ [ {$닉} ] 오늘 `.양도` {$완료수}회 사용했어요.\n본방·게임방 합산 하루 1회 무료 — 추가 양도는 지호 1시간 이상 필요 (현재 남은 지호: {$지호['남은시간']}시간)";
  }
}

if (!function_exists('yangdo_지호_소비')) {
  function yangdo_지호_소비(string $닉, ?array $지호 = null): string {
    $닉 = trim($닉);
    if ($닉 === '') {
      return '';
    }
    $지호 = $지호 ?? yangdo_지호_정보($닉);
    if (empty($지호['idx']) || empty($지호['enddate_ts'])) {
      return '';
    }

    $idx = (int)$지호['idx'];
    $종료_ts = (int)$지호['enddate_ts'];
    if ($종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료_ts = $종료_ts - 3600;
    if ($새종료_ts <= time()) {
      db_query("DELETE FROM tb_item_use WHERE idx = {$idx}");
      return '(지호 1시간 차감 · 버프 종료)';
    }

    $새종료 = date('Y-m-d H:i', $새종료_ts);
    $새종료_esc = addslashes($새종료);
    db_query("UPDATE tb_item_use SET enddate = '{$새종료_esc}' WHERE idx = {$idx}");
    return '(지호 1시간 차감 · ' . date('m-d H:i', $새종료_ts) . ' 까지)';
  }
}

if (!function_exists('yangdo_게임냥_표시')) {
  function yangdo_게임냥_표시($금액): string {
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시((int)$금액, '');
    }
    return number_format((int)$금액);
  }
}

if (!function_exists('yangdo_게임냥_실행')) {
  function yangdo_게임냥_실행(string $닉, string $receiver, $amount_raw, ?array $회원정보 = null): array {
    yangdo_db_준비();
    $닉 = trim($닉);
    $amount = function_exists('냥_금액_파싱') ? (int)냥_금액_파싱((string)$amount_raw) : (int)$amount_raw;

    $받는 = yangdo_받는이_조회($receiver);
    if ($받는 === null) {
      return ['ok' => false, 'data' => "[ {$receiver} ] 회원을 찾을 수 없습니다."];
    }
    $receiver = $받는['name'];

    if ($닉 === '' || $receiver === '' || $amount < 1) {
      return ['ok' => false, 'data' => '받는 사람과 금액을 확인해주세요.'];
    }
    if ($amount < 30000) {
      return ['ok' => false, 'data' => '게임냥 양도는 30,000냥 이상만 가능합니다.'];
    }
    if ($receiver === $닉) {
      return ['ok' => false, 'data' => '본인에게는 양도할 수 없습니다.'];
    }

    $오늘완료 = yangdo_오늘완료수($닉);
    $지호소비 = ($오늘완료 >= 1);
    $지호 = $지호소비 ? yangdo_지호_정보($닉) : null;

    $양도제한 = yangdo_일일제한_검사($닉, $오늘완료, $지호);
    if ($양도제한 !== null) {
      return ['ok' => false, 'data' => $양도제한];
    }

    if ($지호소비 && (empty($지호['적용중']) || $지호['남은시간'] < 1)) {
      return ['ok' => false, 'data' => yangdo_일일제한_검사($닉, $오늘완료, $지호) ?? '추가 양도는 지호 1시간 이상 필요합니다.'];
    }

    if (is_array($회원정보) && isset($회원정보['point'], $회원정보['level'])) {
      $정보 = $회원정보;
    } else {
      $닉_esc = addslashes($닉);
      $정보 = db_select("SELECT point, level FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    }
    if (empty($정보)) {
      return ['ok' => false, 'data' => '회원 정보를 찾을 수 없습니다.'];
    }

    if (!function_exists('양도수수료계산')) {
      require_once __DIR__ . '/config.php';
    }
    $수수료 = 양도수수료계산((int)($정보['level'] ?? 0), $amount);
    $게임냥 = $정보['point'] ?? 0;
    if (!yangdo_amount_gte($게임냥, $amount)) {
      return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . yangdo_게임냥_표시($게임냥) . '냥)'];
    }

    $수수료제외 = $amount - $수수료;
    if ($수수료제외 < 1) {
      return ['ok' => false, 'data' => '양도 금액이 너무 적습니다.'];
    }

    $닉_esc = addslashes($닉);
    $받는_esc = addslashes($receiver);

    global $conn;
    if (!($conn instanceof mysqli)) {
      return ['ok' => false, 'data' => 'DB 연결을 확인할 수 없습니다.'];
    }

    $amount_sql = yangdo_amount_sql($amount);
    $수수료제외_sql = yangdo_amount_sql($수수료제외);
    $수수료_sql = yangdo_amount_sql($수수료);

    mysqli_begin_transaction($conn);
    try {
      db_query("
        UPDATE tb_member
        SET point = point - {$amount_sql}
        WHERE name = '{$닉_esc}'
          AND point >= {$amount_sql}
        LIMIT 1
      ");
      if ((int)mysqli_affected_rows($conn) < 1) {
        throw new RuntimeException('insufficient');
      }
      db_query("
        UPDATE tb_member
        SET point = point + {$수수료제외_sql}
        WHERE name = '{$받는_esc}'
        LIMIT 1
      ");
      db_query("UPDATE config SET tax = tax + {$수수료_sql}");

      if (!yangdo_양도_로그_기록($닉, $receiver, $수수료, $amount)) {
        throw new RuntimeException('log');
      }

      $지호차감문구 = '';
      if ($지호소비) {
        $지호차감문구 = yangdo_지호_소비($닉, $지호);
      }

      mysqli_commit($conn);
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      if ($e instanceof RuntimeException && $e->getMessage() === 'insufficient') {
        return ['ok' => false, 'data' => '게임냥이 부족합니다. (보유 ' . yangdo_게임냥_표시($게임냥) . '냥)'];
      }
      $db_err = yangdo_db_last_error();
      if ($db_err !== '' && (stripos($db_err, 'Out of range') !== false || stripos($db_err, '1264') !== false)) {
        return ['ok' => false, 'data' => '양도 금액이 로그 저장 한도를 초과했습니다. 관리자에게 tb_point_log BIGINT 마이그레이션(api/schema/tb_point_log_alter_bigint.sql) 실행을 요청해주세요.'];
      }
      return ['ok' => false, 'data' => '양도 기록 저장에 실패했습니다. 잠시 후 다시 시도해주세요.'];
    }

    $회차 = $오늘완료 + 1;
    if ($회차 <= 1) {
      $회차문구 = '오늘 1회째(무료)';
    } else {
      $회차문구 = "오늘 {$회차}회째(지호 추가)";
    }

    $결과문구 = "{$닉} → {$receiver} 게임냥 " . yangdo_게임냥_표시($수수료제외) . "냥 (수수료 " . yangdo_게임냥_표시($수수료) . "냥)\n[{$회차문구}]";
    if ($지호차감문구 !== '') {
      $결과문구 .= "\n" . $지호차감문구;
    }

    return ['ok' => true, 'data' => $결과문구];
  }
}

if (!function_exists('양도_일일현황_문구')) {
  function 양도_일일현황_문구(string $닉): string {
    $닉 = trim($닉);
    $오늘양도 = yangdo_오늘완료수($닉);
    if ($오늘양도 < 1) {
      return '';
    }
    $지호 = yangdo_지호_정보($닉);
    $msg = "\n\n[오늘 양도 {$오늘양도}회 (본방·게임방 합산)";
    if ($오늘양도 >= 1 && $지호['남은시간'] < 1) {
      $msg .= ' · 무료 1회 사용 완료';
    } elseif ($지호['적용중']) {
      $msg .= " · 지호 남은 {$지호['남은시간']}시간";
    }
    $msg .= ']';
    return $msg;
  }
}

if (!function_exists('양도_게임냥_안내')) {
  function 양도_게임냥_안내(string $단위, int $level): string {
    if (!function_exists('양도수수료계산')) {
      require_once __DIR__ . '/config.php';
    }
    $보낼양 = 30000;
    $수수료 = 양도수수료계산($level, $보낼양);
    $수령액 = function_exists('양도_수령액') ? 양도_수령액($보낼양) : ($보낼양 - $수수료);
    $fail = "✅ {$단위} 양도\n\n.양도 받을닉 양도할 {$단위}수량";
    $fail .= "\n양도금액: 게임냥(point)";
    $fail .= "\n양도금액 기준 수수료 (금고 적립)";
    $fail .= 양도수수료_안내문();
    $fail .= "\n\n-유의사항";
    $fail .= "\n본방냥·게임냥 합쳐 하루 1회 무료";
    $fail .= "\n본방에서 오늘 양도했으면 게임방 양도 불가 (지호로 추가 가능)";
    $fail .= "\n지호 적용 시 남은 시간(1시간)마다 추가 양도 가능 (추가 1회당 지호 -1시간)";
    $fail .= "\n최소 30000{$단위} 이상만 양도 가능";
    $fail .= "\n금액: 숫자 또는 만·억·조 (예: 50000000, 5000만, 100억, 5조)";
    $fail .= "\n현재 예상 수수료 {$수수료}냥 (30000{$단위} 전송 시 수령 {$수령액}{$단위})";
    return $fail;
  }
}

if (!function_exists('양도_게임냥_명령_처리')) {
  function 양도_게임냥_명령_처리(string $status, string $두자리닉넴, string $단위, int $level = 0, ?array $회원정보 = null): bool {
    if (strpos($status, '.양도') === false) {
      return false;
    }

    if (!function_exists('냥_금액_파싱')) {
      require_once __DIR__ . '/function.php';
    }
    if (!function_exists('양도수수료_안내문')) {
      require_once __DIR__ . '/config.php';
    }

    $닉 = (is_array($회원정보) && !empty($회원정보['name']))
      ? trim((string)$회원정보['name'])
      : yangdo_닉_정규화($두자리닉넴);

    if (!preg_match('/\.양도\s*(.+)/u', $status, $match)) {
      $fail = 양도_게임냥_안내($단위, $level) . 양도_일일현황_문구($닉);
      echo 전송($fail);
      exit;
    }

    $after = trim($match[1]);
    if (!preg_match('/^([가-힣A-Za-z]+)\s*(.+)$/u', $after, $parts)) {
      $fail = 양도_게임냥_안내($단위, $level) . 양도_일일현황_문구($닉);
      echo 전송($fail);
      exit;
    }

    $받는이 = $parts[1];
    $금액raw = trim($parts[2]);
    $보내는양 = function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($금액raw) : (int)$금액raw;
    if ($보내는양 <= 0) {
      echo 전송("❌ 금액 형식을 확인해주세요.\n예) .양도 도하 5조 / .양도 도하 100억 / .양도 도하 50000000");
      exit;
    }

    $result = yangdo_게임냥_실행($닉, $받는이, $금액raw, $회원정보);
    if (empty($result['ok'])) {
      echo 전송($result['data']);
      exit;
    }
    echo 전송('💰 ' . $result['data']);
    exit;
  }
}
