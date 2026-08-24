<?php
/**
 * 로또 오래된 회차 정리 + 인덱스 보장 (관리용)
 *
 * 미리보기: /page/lotto_maintain.php
 * 116회 이전 삭제: /page/lotto_maintain.php?run=1&before=116
 * 보관 N회차:     /page/lotto_maintain.php?run=1&keep=10
 * 수수료행→config: /page/lotto_maintain.php?migrate_fees=1
 *
 * 700만 건은 배치 DELETE — 한 번에 안 끝나면 같은 URL 재실행
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/_chk.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_purchase.inc.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_purchase.inc.php';
}
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_amount.inc.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_amount.inc.php';
}

header('Content-Type: text/html; charset=utf-8');
@set_time_limit(0);
@ignore_user_abort(true);

$보관 = isset($_GET['keep']) ? max(1, (int)$_GET['keep']) : 10;
$before = isset($_GET['before']) ? max(0, (int)$_GET['before']) : 0;
$do = isset($_GET['run']) && (string)$_GET['run'] === '1';
$migrateFees = isset($_GET['migrate_fees']) && (string)$_GET['migrate_fees'] === '1';
$batch = isset($_GET['batch']) ? max(1000, min(200000, (int)$_GET['batch'])) : 50000;

$진행 = function_exists('로또_진행회차') ? (int)로또_진행회차() : 0;
$보호하한 = function_exists('로또_삭제보호하한') ? (int)로또_삭제보호하한() : max(1, $진행);

// before 우선 · 없으면 keep 방식
if ($before > 0) {
  $삭제기준 = min($before, $보호하한);
} else {
  $삭제기준 = max(0, $진행 - $보관);
  if ($삭제기준 > $보호하한) {
    $삭제기준 = $보호하한;
  }
}

$min행 = @db_select('SELECT IFNULL(MIN(drow), 0) AS m FROM tb_game_lotto');
$max행 = @db_select('SELECT IFNULL(MAX(drow), 0) AS m FROM tb_game_lotto');
// 전체 COUNT는 700만에서 느릴 수 있어 추정값 병행
$전체추정 = @db_select("
  SELECT TABLE_ROWS AS c
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_game_lotto'
  LIMIT 1
");
$전체 = @db_select('SELECT COUNT(*) AS c FROM tb_game_lotto');
$오래 = ($삭제기준 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow < {$삭제기준}")
  : ['c' => 0];
$고아 = ($진행 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow > {$진행}")
  : ['c' => 0];
$진행표 = ($진행 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow = {$진행}")
  : ['c' => 0];
$진행금액행 = ($진행 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow = {$진행} AND amount > 0")
  : ['c' => 0];
$보호회차행 = ($보호하한 > 0)
  ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow = {$보호하한}")
  : ['c' => 0];

$로그 = [];
$삭제결과 = null;
$로또누적현재 = function_exists('로또누적_조회') ? 로또누적_조회() : '0';
$수수료행합 = ['total_amount' => '0', 'cnt' => 0];
if ($진행 > 0) {
  $수수료행합 = @db_select("
    SELECT
      CAST(IFNULL(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS total_amount,
      COUNT(*) AS cnt
    FROM tb_game_lotto
    WHERE drow = {$진행}
      AND status = 0
      AND amount > 0
      AND IFNULL(num1, 0) = 0
      AND IFNULL(num2, 0) = 0
      AND IFNULL(num3, 0) = 0
      AND nick <> '이월금'
  ") ?: ['total_amount' => '0', 'cnt' => 0];
}

if ($migrateFees && function_exists('로또누적_수수료행_이관')) {
  $이관 = 로또누적_수수료행_이관($진행);
  $로그[] = (string)($이관['msg'] ?? '수수료 이관 완료');
  $로또누적현재 = function_exists('로또누적_조회') ? 로또누적_조회() : '0';
  $수수료행합 = @db_select("
    SELECT
      CAST(IFNULL(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS total_amount,
      COUNT(*) AS cnt
    FROM tb_game_lotto
    WHERE drow = {$진행}
      AND status = 0
      AND amount > 0
      AND IFNULL(num1, 0) = 0
      AND IFNULL(num2, 0) = 0
      AND IFNULL(num3, 0) = 0
      AND nick <> '이월금'
  ") ?: ['total_amount' => '0', 'cnt' => 0];
}

$ensureIndex = function (string $table, string $name, string $cols) use (&$로그): string {
  $name_esc = addslashes($name);
  $exists = @db_select("
    SELECT INDEX_NAME AS n
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '" . addslashes($table) . "'
      AND INDEX_NAME = '{$name_esc}'
    LIMIT 1
  ");
  if (!empty($exists['n'])) {
    $로그[] = "인덱스 유지: {$table}.{$name}";
    return 'exists';
  }
  $ok = @db_query("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})");
  if ($ok) {
    $로그[] = "인덱스 추가: {$table}.{$name} ({$cols})";
    return 'added';
  }
  $로그[] = "인덱스 실패: {$table}.{$name}";
  return 'fail';
};

if ($do) {
  if (function_exists('로또_오래된회차_정리')) {
    $삭제결과 = 로또_오래된회차_정리(0, $보관, $before);
    $로그[] = (string)($삭제결과['msg'] ?? (
      '오래된 회차 삭제: ' . (int)($삭제결과['삭제건수'] ?? 0) . '건 (drow < ' . (int)($삭제결과['삭제기준'] ?? 0) . ')'
    ));
    $로그[] = '보호하한: ' . (int)($삭제결과['보호하한'] ?? $보호하한) . '회차 이상 유지 (삭제 안 함)';
    if ((int)($삭제결과['남은건수'] ?? 0) > 0) {
      $다시 = $before > 0
        ? ('?run=1&before=' . (int)$before . '&batch=' . (int)$batch)
        : ('?run=1&keep=' . (int)$보관 . '&batch=' . (int)$batch);
      $로그[] = '아직 남음 → 같은 주소 다시 실행: ' . $다시;
    }
  }

  if ($진행 > 0) {
    $고아행 = @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow > {$진행}");
    $고아삭제예정 = (int)($고아행['c'] ?? 0);
    if ($고아삭제예정 > 0 && function_exists('로또_회차이전_배치삭제') === false) {
      // noop
    }
    if ($고아삭제예정 > 0) {
      // 고아도 배치
      $고아삭제합 = 0;
      for ($gi = 0; $gi < 200; $gi++) {
        $ok = @db_query("DELETE FROM tb_game_lotto WHERE drow > {$진행} LIMIT {$batch}");
        if (!$ok) {
          break;
        }
        global $conn;
        $aff = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
        $고아삭제합 += $aff;
        if ($aff < $batch) {
          break;
        }
      }
      $로그[] = "고아 회차 삭제: {$고아삭제합}건 (drow > {$진행})";
    } else {
      $로그[] = "고아 회차: 없음 (진행 {$진행})";
    }
  }

  $ensureIndex('tb_game_lotto', 'idx_drow_rank', 'drow, rank');
  $ensureIndex('tb_game_lotto', 'idx_drow_nick', 'drow, nick');
  $ensureIndex('tb_game_lotto', 'idx_drow_status', 'drow, status');
  $ensureIndex('tb_game_lotto', 'idx_status_drow', 'status, drow');
  $ensureIndex('tb_game_lotto_result', 'idx_status_drow', 'status, drow');
  $ensureIndex('tb_game_lotto_result', 'idx_drow', 'drow');

  $풀대상 = $보호하한 > 0 ? $보호하한 : $진행;
  if ($풀대상 > 0 && function_exists('로또_회차금액_금액행합') && function_exists('로또_회차풀_쓰기')) {
    $풀시드 = 로또_회차금액_금액행합($풀대상);
    로또_회차풀_쓰기($풀대상, $풀시드);
    if (function_exists('로또_회차금액_캐시쓰기')) {
      로또_회차금액_캐시쓰기($풀대상, $풀시드);
    }
    $로그[] = "풀 시드: {$풀대상}회차 = {$풀시드} (amount>0 SUM)";
  }

  $min행 = @db_select('SELECT IFNULL(MIN(drow), 0) AS m FROM tb_game_lotto');
  $max행 = @db_select('SELECT IFNULL(MAX(drow), 0) AS m FROM tb_game_lotto');
  $전체추정 = @db_select("
    SELECT TABLE_ROWS AS c
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_game_lotto'
    LIMIT 1
  ");
  $전체 = @db_select('SELECT COUNT(*) AS c FROM tb_game_lotto');
  $오래 = ($삭제기준 > 0)
    ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow < {$삭제기준}")
    : ['c' => 0];
  $고아 = ($진행 > 0)
    ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow > {$진행}")
    : ['c' => 0];
  $진행표 = ($진행 > 0)
    ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow = {$진행}")
    : ['c' => 0];
  $보호회차행 = ($보호하한 > 0)
    ? @db_select("SELECT COUNT(*) AS c FROM tb_game_lotto WHERE drow = {$보호하한}")
    : ['c' => 0];
}

$indexList = [];
$rsIdx = @db_query("
  SELECT TABLE_NAME AS t, INDEX_NAME AS n, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('tb_game_lotto', 'tb_game_lotto_result')
  GROUP BY TABLE_NAME, INDEX_NAME
  ORDER BY TABLE_NAME, INDEX_NAME
");
while ($rsIdx && ($row = db_fetch($rsIdx))) {
  $indexList[] = $row;
}

$남은삭제 = (int)($오래['c'] ?? 0);
$다시Url = $before > 0
  ? ('?run=1&before=' . (int)$before . '&batch=' . (int)$batch)
  : ('?run=1&keep=' . (int)$보관 . '&batch=' . (int)$batch);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>로또 정리·인덱스</title>
  <style>
    body { font-family: sans-serif; max-width: 760px; margin: 2rem auto; line-height: 1.5; padding: 0 16px; }
    code { background: #f3f3f3; padding: 0.1em 0.35em; }
    .ok { color: #0a7; }
    .warn { color: #b45309; }
    .btn { display: inline-block; margin: 8px 8px 0 0; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; }
    .btn.danger { background: #dc2626; }
    .btn.secondary { background: #64748b; }
    ul.log { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 12px 12px 32px; }
  </style>
</head>
<body>
  <h1>로또 오래된 회차 정리 · 인덱스</h1>
  <p>
    진행 회차: <strong><?= (int)$진행 ?></strong>
    · 보호하한: <strong><?= (int)$보호하한 ?></strong>회차 이상 유지
    · 삭제 기준: <code>drow &lt; <?= (int)$삭제기준 ?></code>
  </p>
  <ul>
    <li>tb_game_lotto min/max: <?= (int)($min행['m'] ?? 0) ?> ~ <?= (int)($max행['m'] ?? 0) ?></li>
    <li>전체 행(정확): <?= number_format((int)($전체['c'] ?? 0)) ?>
      <?php if (!empty($전체추정['c'])): ?>
        · 추정 <?= number_format((int)$전체추정['c']) ?>
      <?php endif; ?>
    </li>
    <li class="warn">삭제 대상 (drow &lt; <?= (int)$삭제기준 ?>): <strong><?= number_format($남은삭제) ?></strong>건</li>
    <li>보호 회차(<?= (int)$보호하한 ?>) 행: <?= number_format((int)($보호회차행['c'] ?? 0)) ?> ← 삭제 안 함</li>
    <li>진행회차(<?= (int)$진행 ?>) 행: <?= number_format((int)($진행표['c'] ?? 0)) ?></li>
    <li>진행회차 금액 행(amount&gt;0): <?= number_format((int)($진행금액행['c'] ?? 0)) ?></li>
    <li>고아(drow &gt; <?= (int)$진행 ?>): <?= number_format((int)($고아['c'] ?? 0)) ?></li>
    <li><strong>config.로또누적</strong>: <?= htmlspecialchars((string)$로또누적현재, ENT_QUOTES, 'UTF-8') ?></li>
    <li>진행회차 옛 수수료 INSERT 행: <?= number_format((int)($수수료행합['cnt'] ?? 0)) ?>건
      · 합 <?= htmlspecialchars((string)($수수료행합['total_amount'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>
      ← 이관 대상</li>
  </ul>

  <p style="color:#64748b;font-size:14px;">
    <strong>116회 이전만 지우려면</strong> <code>before=116</code> 을 쓰세요.<br>
    → <code>drow &lt; 116</code> 만 배치 삭제 · <strong>116 이상은 절대 안 지움</strong><br>
    700만 건은 한 번에 안 끝날 수 있습니다. 남으면 같은 URL을 다시 열어주세요.
  </p>

  <?php if ($do || $migrateFees): ?>
    <h2 class="ok">실행 결과</h2>
    <ul class="log">
      <?php foreach ($로그 as $line): ?>
        <li><?= htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($do && $남은삭제 > 0): ?>
      <p class="warn">아직 <?= number_format($남은삭제) ?>건 남음 → 이어서 삭제</p>
      <a class="btn danger" href="<?= htmlspecialchars($다시Url, ENT_QUOTES, 'UTF-8') ?>">이어서 삭제 실행</a>
    <?php elseif ($do): ?>
      <p class="ok">삭제 대상 없음 · 정리 완료</p>
    <?php endif; ?>
  <?php endif; ?>

  <p>아래 버튼으로 실행하세요.</p>
  <a class="btn danger" href="?migrate_fees=1">
    진행회차 수수료 INSERT → config.로또누적 이관
  </a>
  <a class="btn danger" href="?run=1&amp;before=116&amp;batch=<?= (int)$batch ?>">
    116회 이전 삭제 (drow &lt; 116)
  </a>
  <a class="btn secondary" href="?run=1&amp;keep=<?= (int)$보관 ?>&amp;batch=<?= (int)$batch ?>">
    keep=<?= (int)$보관 ?> 방식 정리
  </a>
  <?php if ($before > 0): ?>
    <a class="btn danger" href="?run=1&amp;before=<?= (int)$before ?>&amp;batch=<?= (int)$batch ?>">
      before=<?= (int)$before ?> 삭제 실행
    </a>
  <?php endif; ?>

  <h2>현재 인덱스</h2>
  <ul>
    <?php foreach ($indexList as $idx): ?>
      <li><code><?= htmlspecialchars(($idx['t'] ?? '') . '.' . ($idx['n'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
        (<?= htmlspecialchars((string)($idx['cols'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
