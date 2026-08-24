<?php
/**
 * 본방(info1) 대출
 * - `.대출 닉 금액` (관리자): 게임냥 지급 + 원금 기록 · 실행 즉시 이자 10% · 이후 하루 10%(원금 기준, 누적이자 컬럼)
 * - `.대출`: 진행중 대출 목록
 * - `.상환`: 본인 원금+누적이자 게임냥 차감 후 목록에서 숨김
 */

if (!defined('대출_일이자율')) {
  define('대출_일이자율', 0.10);
}

if (!function_exists('대출_테이블_보장')) {
  function 대출_테이블_보장(): void {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    @db_query("CREATE TABLE IF NOT EXISTS `tb_game_loan` (
      `idx` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `nick` VARCHAR(50) NOT NULL,
      `원금` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '대출 원금',
      `누적이자` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '하루 10% 누적 이자',
      `hidden` TINYINT NOT NULL DEFAULT 0 COMMENT '0=진행중 1=상환완료(목록 숨김)',
      `last_interest_date` DATE NOT NULL COMMENT '마지막 이자 반영일',
      `loan_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `repay_date` DATETIME DEFAULT NULL,
      `admin_nick` VARCHAR(50) NOT NULL DEFAULT '',
      KEY `idx_nick_hidden` (`nick`, `hidden`),
      KEY `idx_hidden` (`hidden`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $이자col = @db_select("SHOW COLUMNS FROM `tb_game_loan` LIKE '누적이자'");
    if (empty($이자col['Field'])) {
      @db_query("ALTER TABLE `tb_game_loan` ADD COLUMN `누적이자` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '하루 10% 누적 이자' AFTER `원금`");
    }
  }
}

if (!function_exists('대출_닉정규화')) {
  function 대출_닉정규화($nick): string {
    $nick = trim((string)$nick);
    if ($nick === '') {
      return '';
    }
    if (function_exists('getTwoCharNick')) {
      $p = getTwoCharNick($nick);
      if ($p !== '') {
        return $p;
      }
    }
    return $nick;
  }
}

if (!function_exists('대출_금액표시')) {
  function 대출_금액표시($금액): string {
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($금액, '냥');
    }
    if (function_exists('랭킹_게임냥표시')) {
      return 랭킹_게임냥표시($금액, '냥');
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : preg_replace('/[^\d]/', '', (string)$금액);
    return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s) . '냥';
  }
}

if (!function_exists('대출_일수')) {
  /** last_date(Y-m-d) 다음날부터 today 까지 경과 일수 */
  function 대출_일수($fromDate, $toDate = null): int {
    $from = trim((string)$fromDate);
    $to = $toDate !== null ? trim((string)$toDate) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      return 0;
    }
    try {
      $a = new DateTime($from);
      $b = new DateTime($to);
    } catch (Throwable $e) {
      return 0;
    }
    if ($b <= $a) {
      return 0;
    }
    return (int)$a->diff($b)->days;
  }
}

if (!function_exists('대출_금액이상인가')) {
  function 대출_금액이상인가($보유, $필요): bool {
    $보유s = function_exists('냥_부호포함_정수문자열')
      ? 냥_부호포함_정수문자열($보유)
      : (string)$보유;
    $필요s = function_exists('냥_정수문자열') ? 냥_정수문자열($필요) : preg_replace('/[^\d]/', '', (string)$필요);
    $필요s = ltrim((string)$필요s, '0') ?: '0';
    if ($필요s === '0') {
      return true;
    }
    if ($보유s === '' || $보유s === '0' || (isset($보유s[0]) && $보유s[0] === '-')) {
      return false;
    }
    $보유s = ltrim($보유s, '+');
    if (function_exists('bccomp')) {
      return bccomp($보유s, $필요s, 0) >= 0;
    }
    if (strlen($보유s) !== strlen($필요s)) {
      return strlen($보유s) > strlen($필요s);
    }
    return $보유s >= $필요s;
  }
}

if (!function_exists('대출_합계')) {
  function 대출_합계($원금, $이자): string {
    $원금s = function_exists('냥_정수문자열') ? 냥_정수문자열($원금) : (string)$원금;
    $이자s = function_exists('냥_정수문자열') ? 냥_정수문자열($이자) : (string)$이자;
    if (function_exists('냥_금액_문자열합')) {
      return 냥_금액_문자열합($원금s, $이자s);
    }
    if (function_exists('bcadd')) {
      return bcadd($원금s, $이자s, 0);
    }
    return (string)((int)$원금s + (int)$이자s);
  }
}

if (!function_exists('대출_하루이자')) {
  function 대출_하루이자($원금): string {
    $원금s = function_exists('냥_정수문자열') ? 냥_정수문자열($원금) : (string)$원금;
    if ($원금s === '0') {
      return '0';
    }
    if (function_exists('냥_비율내림')) {
      return 냥_정수문자열(냥_비율내림($원금s, (float)대출_일이자율));
    }
    if (function_exists('bcdiv') && function_exists('bcmul')) {
      return 냥_정수문자열(bcdiv(bcmul($원금s, '10', 0), '100', 0));
    }
    return '0';
  }
}

if (!function_exists('대출_행_이자반영')) {
  /** 진행중 1건 · 달력일 기준 원금의 10%씩 누적이자 가산 */
  function 대출_행_이자반영(array $row): array {
    대출_테이블_보장();
    $idx = (int)($row['idx'] ?? 0);
    if ($idx < 1 || (int)($row['hidden'] ?? 0) === 1) {
      return $row;
    }
    $오늘 = date('Y-m-d');
    $마지막 = trim((string)($row['last_interest_date'] ?? ''));
    if ($마지막 === '') {
      $마지막 = substr((string)($row['loan_date'] ?? $오늘), 0, 10);
    }
    $days = 대출_일수($마지막, $오늘);
    if ($days < 1) {
      return $row;
    }
    $원금 = function_exists('냥_정수문자열') ? 냥_정수문자열($row['원금'] ?? 0) : (string)($row['원금'] ?? '0');
    $이자 = function_exists('냥_정수문자열') ? 냥_정수문자열($row['누적이자'] ?? 0) : (string)($row['누적이자'] ?? '0');
    $하루 = 대출_하루이자($원금);
    $가산 = '0';
    if ($하루 !== '0') {
      $가산 = function_exists('냥_금액_문자열곱')
        ? 냥_금액_문자열곱($하루, (string)$days)
        : (function_exists('bcmul') ? bcmul($하루, (string)$days, 0) : (string)((int)$하루 * $days));
    }
    $새이자 = ($가산 === '0')
      ? $이자
      : (function_exists('냥_금액_문자열합') ? 냥_금액_문자열합($이자, $가산) : (function_exists('bcadd') ? bcadd($이자, $가산, 0) : $이자));
    $새이자_sql = preg_replace('/[^\d]/', '', (string)$새이자) ?: '0';
    $오늘_esc = addslashes($오늘);
    db_query("UPDATE `tb_game_loan`
      SET `누적이자` = {$새이자_sql}, `last_interest_date` = '{$오늘_esc}'
      WHERE idx = {$idx} AND hidden = 0
      LIMIT 1");
    $row['누적이자'] = $새이자_sql;
    $row['last_interest_date'] = $오늘;
    return $row;
  }
}

if (!function_exists('대출_전원이자_반영')) {
  function 대출_전원이자_반영(): void {
    대출_테이블_보장();
    $오늘 = date('Y-m-d');
    $오늘_esc = addslashes($오늘);
    $rs = db_query("SELECT idx, nick,
        CAST(`원금` AS CHAR) AS `원금`,
        CAST(`누적이자` AS CHAR) AS `누적이자`,
        hidden, last_interest_date, loan_date
      FROM `tb_game_loan`
      WHERE hidden = 0
        AND (last_interest_date IS NULL OR last_interest_date < '{$오늘_esc}')");
    if (!$rs) {
      return;
    }
    while ($row = db_fetch($rs)) {
      대출_행_이자반영($row);
    }
  }
}

if (!function_exists('대출_진행중_조회')) {
  function 대출_진행중_조회($nick): ?array {
    대출_테이블_보장();
    $nick = 대출_닉정규화($nick);
    if ($nick === '') {
      return null;
    }
    $esc = addslashes($nick);
    $row = db_select("SELECT idx, nick,
        CAST(`원금` AS CHAR) AS `원금`,
        CAST(`누적이자` AS CHAR) AS `누적이자`,
        hidden, last_interest_date, loan_date, admin_nick
      FROM `tb_game_loan`
      WHERE nick = '{$esc}' AND hidden = 0
      ORDER BY idx DESC
      LIMIT 1");
    if (empty($row['idx'])) {
      return null;
    }
    return 대출_행_이자반영($row);
  }
}

if (!function_exists('대출_목록_문구')) {
  function 대출_목록_문구(): string {
    대출_전원이자_반영();
    $rs = db_query("SELECT idx, nick,
        CAST(`원금` AS CHAR) AS `원금`,
        CAST(`누적이자` AS CHAR) AS `누적이자`,
        last_interest_date, loan_date
      FROM `tb_game_loan`
      WHERE hidden = 0
      ORDER BY loan_date ASC, idx ASC");
    $줄 = [];
    $n = 0;
    while ($row = $rs ? db_fetch($rs) : false) {
      $n++;
      $원금 = 대출_금액표시($row['원금'] ?? 0);
      $이자 = 대출_금액표시($row['누적이자'] ?? 0);
      $합계 = 대출_금액표시(대출_합계($row['원금'] ?? 0, $row['누적이자'] ?? 0));
      $시작 = substr((string)($row['loan_date'] ?? ''), 0, 10);
      $경과일 = 대출_일수($시작, date('Y-m-d'));
      $경과문구 = $경과일 < 1 ? '당일' : ($경과일 . '일');
      $줄[] = "{$n}. {$row['nick']}\n   원금 {$원금} · 누적이자 {$이자}\n   합계 {$합계} · {$경과문구}";
    }
    if ($n < 1) {
      return "📋 현재 대출중인 친구가 없어요.\n\n관리자: `.대출 닉 금액`\n예) `.대출 오리 1조`";
    }
      $msg = "📋 현재 대출 현황 ({$n}명)\n즉시·하루 이자 원금의 10%\n\n";
    $msg .= implode("\n\n", $줄);
    $msg .= "\n\n상환: `.상환` (원금+이자 게임냥 차감)";
    return $msg;
  }
}

if (!function_exists('대출_지급_처리')) {
  function 대출_지급_처리($대상닉, $금액텍스트, $관리자닉): void {
    $대상닉 = 대출_닉정규화($대상닉);
    $금액 = function_exists('냥_금액_파싱_문자열')
      ? 냥_금액_파싱_문자열((string)$금액텍스트)
      : (function_exists('냥_금액_파싱') ? (string)(int)냥_금액_파싱($금액텍스트) : '0');
    $금액 = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : (ltrim(preg_replace('/[^\d]/', '', (string)$금액), '0') ?: '0');
    if ($대상닉 === '' || $금액 === '0') {
      echo 전송("❌ 사용법: `.대출 닉 금액`\n예) `.대출 오리 1조`\n목록: `.대출`");
      exit;
    }
    if (!function_exists('게임냥_이체_적용')) {
      echo 전송('❌ 대출 지급 기능을 불러오지 못했어요.');
      exit;
    }
    $기존 = 대출_진행중_조회($대상닉);
    $결과 = 게임냥_이체_적용($대상닉, $금액);
    if (empty($결과['ok'])) {
      echo 전송('❌ ' . (string)($결과['msg'] ?? '대출 지급 실패'));
      exit;
    }
    $실닉 = (string)($결과['name'] ?? $대상닉);
    $실닉_esc = addslashes($실닉);
    $관리자_esc = addslashes(대출_닉정규화($관리자닉));
    $금액_sql = preg_replace('/[^\d]/', '', $금액) ?: '0';
    $오늘 = date('Y-m-d');
    $오늘_esc = addslashes($오늘);
    $즉시이자 = 대출_하루이자($금액);
    대출_테이블_보장();
    if ($기존 && (int)$기존['idx'] > 0) {
      $idx = (int)$기존['idx'];
      $신원금 = function_exists('냥_금액_문자열합')
        ? 냥_금액_문자열합((string)($기존['원금'] ?? '0'), $금액)
        : (function_exists('bcadd') ? bcadd((string)($기존['원금'] ?? '0'), $금액, 0) : $금액);
      $새이자 = function_exists('냥_금액_문자열합')
        ? 냥_금액_문자열합((string)($기존['누적이자'] ?? '0'), $즉시이자)
        : (function_exists('bcadd') ? bcadd((string)($기존['누적이자'] ?? '0'), $즉시이자, 0) : $즉시이자);
      $신원금_sql = preg_replace('/[^\d]/', '', (string)$신원금) ?: '0';
      $새이자_sql = preg_replace('/[^\d]/', '', (string)$새이자) ?: '0';
      db_query("UPDATE `tb_game_loan`
        SET `원금` = {$신원금_sql}, `누적이자` = {$새이자_sql}, `last_interest_date` = '{$오늘_esc}'
        WHERE idx = {$idx} AND hidden = 0
        LIMIT 1");
      $원금표시 = 대출_금액표시($신원금);
      $추가표시 = 대출_금액표시($금액);
      $즉시표시 = 대출_금액표시($즉시이자);
      $이자표시 = 대출_금액표시($새이자);
      $합계표시 = 대출_금액표시(대출_합계($신원금, $새이자));
      $잔액표시 = 대출_금액표시($결과['after'] ?? '0');
      if (function_exists('지급로그')) {
        지급로그('대출', $관리자닉, $실닉, 0, $금액);
      }
      echo 전송(
        "💰 {$실닉} 추가 대출 {$추가표시}\n"
        . "즉시 이자 {$즉시표시} (추가분 10%)\n"
        . "원금 합계 {$원금표시} · 누적이자 {$이자표시}\n"
        . "상환 예정 {$합계표시}\n"
        . "현재 게임냥 {$잔액표시}\n"
        . "다음날부터 하루 이자 원금의 10% · 상환 `.상환`"
      );
      exit;
    }
    $즉시이자_sql = preg_replace('/[^\d]/', '', (string)$즉시이자) ?: '0';
    db_query("INSERT INTO `tb_game_loan`
      SET nick = '{$실닉_esc}',
          `원금` = {$금액_sql},
          `누적이자` = {$즉시이자_sql},
          hidden = 0,
          last_interest_date = '{$오늘_esc}',
          loan_date = NOW(),
          admin_nick = '{$관리자_esc}'");
    $잔액표시 = 대출_금액표시($결과['after'] ?? '0');
    $원금표시 = 대출_금액표시($금액);
    $즉시표시 = 대출_금액표시($즉시이자);
    $합계표시 = 대출_금액표시(대출_합계($금액, $즉시이자));
    if (function_exists('지급로그')) {
      지급로그('대출', $관리자닉, $실닉, 0, $금액);
    }
    echo 전송(
      "💰 {$실닉} 대출 {$원금표시} 지급!\n"
      . "즉시 이자 {$즉시표시} (원금 10%)\n"
      . "상환 예정 {$합계표시}\n"
      . "현재 게임냥 {$잔액표시}\n"
      . "다음날부터 하루 이자 원금의 10% · 상환 `.상환`"
    );
    exit;
  }
}

if (!function_exists('대출_상환_처리')) {
  function 대출_상환_처리($두자리닉넴): void {
    $닉 = 대출_닉정규화($두자리닉넴);
    if ($닉 === '') {
      echo 전송('❌ 등록된 회원만 `.상환`을 사용할 수 있어요.');
      exit;
    }
    $행 = 대출_진행중_조회($닉);
    if (!$행) {
      echo 전송("❌ {$닉} 진행중인 대출이 없어요.\n목록: `.대출`");
      exit;
    }
    $원금 = function_exists('냥_정수문자열') ? 냥_정수문자열($행['원금'] ?? 0) : (string)($행['원금'] ?? '0');
    $이자 = function_exists('냥_정수문자열') ? 냥_정수문자열($행['누적이자'] ?? 0) : (string)($행['누적이자'] ?? '0');
    $합계 = 대출_합계($원금, $이자);
    $닉_esc = addslashes($닉);
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $회원 = db_select("SELECT idx, name, CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    if (empty($회원['idx'])) {
      echo 전송('❌ 회원 정보를 찾지 못했어요.');
      exit;
    }
    $보유 = function_exists('냥_부호포함_정수문자열')
      ? 냥_부호포함_정수문자열($회원['point_str'] ?? '0')
      : (string)($회원['point_str'] ?? '0');
    if (!대출_금액이상인가($보유, $합계)) {
      $부족 = '0';
      $보유양수 = (isset($보유[0]) && $보유[0] === '-') ? '0' : ltrim($보유, '+');
      if (function_exists('냥_금액_문자열차감')) {
        $부족 = 냥_금액_문자열차감($합계, $보유양수 === '' ? '0' : $보유양수);
      } elseif (function_exists('bcsub') && function_exists('bccomp')) {
        $부족 = bccomp($합계, $보유양수, 0) > 0 ? bcsub($합계, $보유양수, 0) : '0';
      }
      echo 전송(
        "❌ {$닉} 게임냥이 부족해서 상환할 수 없어요.\n"
        . "보유 " . 대출_금액표시($보유) . "\n"
        . "원금 " . 대출_금액표시($원금) . " + 누적이자 " . 대출_금액표시($이자) . "\n"
        . "필요 " . 대출_금액표시($합계)
        . ($부족 !== '0' ? ("\n부족 " . 대출_금액표시($부족)) : '')
      );
      exit;
    }
    if (!function_exists('게임냥_이체_적용')) {
      echo 전송('❌ 상환 차감 기능을 불러오지 못했어요.');
      exit;
    }
    $결과 = 게임냥_이체_적용($닉, '-' . $합계);
    if (empty($결과['ok'])) {
      echo 전송('❌ 상환 차감 실패: ' . (string)($결과['msg'] ?? '처리 실패'));
      exit;
    }
    $idx = (int)$행['idx'];
    db_query("UPDATE `tb_game_loan`
      SET hidden = 1, repay_date = NOW()
      WHERE idx = {$idx} AND hidden = 0
      LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('상환', $닉, $닉, 0, '-' . $합계);
    }
    $잔액표시 = 대출_금액표시($결과['after'] ?? '0');
    echo 전송(
      "✅ {$닉} 대출 상환 완료\n"
      . "원금 " . 대출_금액표시($원금) . " + 이자 " . 대출_금액표시($이자) . "\n"
      . "총 " . 대출_금액표시($합계) . " 차감\n"
      . "남은 게임냥 {$잔액표시}"
    );
    exit;
  }
}

if (!function_exists('대출_관리자인가')) {
  /** $관리자 목록 또는 tb_member.admin=1 */
  function 대출_관리자인가($두자리닉넴, $nick = '', $관리자목록 = []): bool {
    global $관리자;
    $목록 = (array)$관리자목록;
    if ($목록 === []) {
      $목록 = (array)($관리자 ?? []);
    }
    $요청닉 = trim((string)$두자리닉넴);
    $후보 = [];
    if ($요청닉 !== '') {
      $후보[] = $요청닉;
    }
    $파싱 = 대출_닉정규화($nick);
    if ($파싱 !== '' && !in_array($파싱, $후보, true)) {
      $후보[] = $파싱;
    }
    foreach ($후보 as $n) {
      if (in_array($n, $목록, true)) {
        return true;
      }
    }
    if ($후보 === []) {
      return false;
    }
    $in = [];
    foreach ($후보 as $n) {
      $in[] = "'" . addslashes($n) . "'";
    }
    $행 = @db_select("SELECT name FROM tb_member WHERE admin = 1 AND name IN (" . implode(',', $in) . ") LIMIT 1");
    return !empty($행['name']);
  }
}

if (!function_exists('대출_명령_처리')) {
  /**
   * 본방 `.대출` / `.상환` — 매칭 시 전송 후 exit, 아니면 복귀
   * `.대출 닉 금액` 은 관리자만
   */
  function 대출_명령_처리($status, $두자리닉넴, $nick = '', $관리자 = []): void {
    $s = trim((string)$status);
    if (preg_match('/^\.상환/u', $s)) {
      if (!preg_match('/^\.상환\s*$/u', $s)) {
        echo 전송("❌ 사용법: `.상환`\n본인 대출(원금+누적이자)만 상환할 수 있어요.");
        exit;
      }
      대출_상환_처리($두자리닉넴);
      return;
    }
    if (!preg_match('/^\.대출(?:\s|$)/u', $s)) {
      return;
    }
    대출_테이블_보장();
    if (preg_match('/^\.대출\s*$/u', $s)) {
      echo 전송(대출_목록_문구());
      exit;
    }
    // `.대출 닉 금액` — 관리자만 (목록 `.대출` 은 위쪽에서 통과)
    if (!대출_관리자인가($두자리닉넴, $nick, $관리자)) {
      echo 전송('❌ `.대출 닉 금액`은 관리자만 사용할 수 있어요.');
      exit;
    }
    if (!preg_match('/^\.대출\s+(\S+)\s+(.+)$/u', $s, $m)) {
      echo 전송("❌ 사용법: `.대출 닉 금액`\n예) `.대출 오리 1조`\n목록: `.대출` · 상환: `.상환`");
      exit;
    }
    대출_지급_처리($m[1], $m[2], $두자리닉넴);
  }
}
