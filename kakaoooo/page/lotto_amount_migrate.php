<?php
/**
 * 로또 금액 DECIMAL 확장 · total_amount 백필 (관리용)
 * URL: /page/lotto_amount_migrate.php
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/_chk.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/function.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_amount.inc.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/lotto_amount.inc.php';
}

header('Content-Type: text/html; charset=utf-8');

$from = isset($_GET['from']) ? max(1, (int)$_GET['from']) : 101;
$do = isset($_GET['run']) && (string)$_GET['run'] === '1';

$cols = [];
foreach (
  [
    ['tb_game_lotto', 'amount'],
    ['tb_game_lotto', 'winnings'],
    ['tb_game_lotto_result', 'total_amount'],
    ['tb_game_lotto_result', 'last_amount'],
  ] as $pair
) {
  $info = @db_select("SHOW COLUMNS FROM `{$pair[0]}` LIKE '{$pair[1]}'");
  $cols[] = [
    'table' => $pair[0],
    'col' => $pair[1],
    'type' => (string)($info['Type'] ?? '(없음)'),
  ];
}

$result = null;
if ($do && function_exists('로또_금액컬럼_보장') && function_exists('로또_total_amount_백필')) {
  로또_금액컬럼_보장();
  $result = 로또_total_amount_백필($from);
}

$sample = [];
$rs = @db_query("
  SELECT drow,
         CAST(IFNULL(total_amount, 0) AS CHAR) AS total_amount,
         CAST(IFNULL(last_amount, 0) AS CHAR) AS last_amount
  FROM tb_game_lotto_result
  WHERE drow >= {$from}
  ORDER BY drow ASC
  LIMIT 20
");
while ($rs && ($row = db_fetch($rs))) {
  $d = (int)$row['drow'];
  $sum = function_exists('로또_회차금액_안전합') ? 로또_회차금액_안전합($d) : '-';
  $sample[] = [
    'drow' => $d,
    'stored' => (string)($row['total_amount'] ?? '0'),
    'sum' => $sum,
    'last' => (string)($row['last_amount'] ?? '0'),
  ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>로또 금액 마이그레이션</title>
  <style>
    body { font-family: sans-serif; max-width: 900px; margin: 24px auto; padding: 0 16px; color: #222; }
    table { border-collapse: collapse; width: 100%; margin: 12px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; word-break: break-all; }
    th { background: #f5f5f5; }
    .ok { color: #0a7; }
    .btn { display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; }
    code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>로또 금액 DECIMAL · 백필</h1>
  <p>BIGINT(~922경) 오버플로로 <code>total_amount=0</code> 인 회차를 티켓 합으로 복구합니다.</p>

  <h2>컬럼 타입</h2>
  <table>
    <tr><th>테이블</th><th>컬럼</th><th>Type</th></tr>
    <?php foreach ($cols as $c) { ?>
      <tr>
        <td><?= htmlspecialchars($c['table']) ?></td>
        <td><?= htmlspecialchars($c['col']) ?></td>
        <td><?= htmlspecialchars($c['type']) ?></td>
      </tr>
    <?php } ?>
  </table>

  <?php if ($result !== null) { ?>
    <h2 class="ok">실행 결과</h2>
    <p>수정 <?= (int)$result['fixed'] ?>건 (from <?= (int)$from ?>)</p>
    <?php if (!empty($result['details'])) { ?>
      <ul>
        <?php foreach ($result['details'] as $line) { ?>
          <li><?= htmlspecialchars($line) ?></li>
        <?php } ?>
      </ul>
    <?php } ?>
  <?php } ?>

  <p>
    <a class="btn" href="?from=<?= (int)$from ?>&run=1">스키마 확장 + <?= (int)$from ?>회차~ 백필 실행</a>
  </p>

  <h2><?= (int)$from ?>회차~ 샘플 (최대 20)</h2>
  <table>
    <tr><th>회차</th><th>저장 total_amount</th><th>티켓 합(안전)</th><th>last_amount</th></tr>
    <?php foreach ($sample as $s) { ?>
      <tr>
        <td><?= (int)$s['drow'] ?></td>
        <td><?= htmlspecialchars($s['stored']) ?></td>
        <td><?= htmlspecialchars($s['sum']) ?></td>
        <td><?= htmlspecialchars($s['last']) ?></td>
      </tr>
    <?php } ?>
  </table>

  <p><a href="/lotto_chk.php?result=<?= (int)$from ?>">lotto_chk <?= (int)$from ?>회차 보기</a></p>
</body>
</html>
