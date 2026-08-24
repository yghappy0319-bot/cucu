<?php
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/_chk.php";

// 코드에서 회차를 고정하고 싶으면 숫자를 넣으세요. (예: 1)
$fixed_result_drow = 0;

$req_result = isset($_GET['result']) ? (int)$_GET['result'] : 0;
$req_nick = isset($_GET['nick']) ? trim((string)$_GET['nick']) : '';
$target_drow = $fixed_result_drow > 0 ? (int)$fixed_result_drow : $req_result;
$target_nick = $req_nick;

if ($target_drow <= 0) {
  $last_row = db_select("SELECT IFNULL(MAX(drow), 0) AS max_drow FROM tb_game_lotto_result");
  $target_drow = (int)($last_row['max_drow'] ?? 0);
}

$result_row = [];
$buy_rows = [];
$total_buy_count = 0;
$total_winnings = 0;
$rank_prizes = [1 => 0, 2 => 0, 3 => 0];

if ($target_drow > 0) {
  $result_row = db_select("SELECT * FROM tb_game_lotto_result WHERE drow = {$target_drow} LIMIT 1");
  if (!empty($result_row)) {
    $등수집계 = [1 => 0, 2 => 0, 3 => 0];
    $cnt_rs = db_query("
      SELECT rank, COUNT(*) AS cnt
      FROM tb_game_lotto
      WHERE drow = {$target_drow}
        AND rank BETWEEN 1 AND 3
        AND nick != '이월금'
      GROUP BY rank
    ");
    while ($cnt_rs && $cnt_row = db_fetch($cnt_rs)) {
      $rk = (int)($cnt_row['rank'] ?? 0);
      if ($rk >= 1 && $rk <= 3) {
        $등수집계[$rk] = (int)($cnt_row['cnt'] ?? 0);
      }
    }
    $rank_prizes = lotto_rank_prizes(
      (int)($result_row['total_amount'] ?? 0),
      $등수집계[1],
      $등수집계[2],
      $등수집계[3]
    );
  }
  $where_nick = " AND nick != '이월금' ";
  if ($target_nick !== '') {
    $nick_esc = addslashes($target_nick);
    $where_nick .= " AND nick = '{$nick_esc}' ";
  }
  $buy_rs = db_query("SELECT * FROM tb_game_lotto WHERE drow = {$target_drow} {$where_nick} AND rank IN (1, 2, 3) ORDER BY rank ASC, idx ASC");
  while ($buy_rs && $buy = db_fetch($buy_rs)) {
    $buy['display_winnings'] = lotto_row_winnings($buy, $rank_prizes);
    $buy_rows[] = $buy;
    $total_buy_count++;
    $total_winnings += (int)$buy['display_winnings'];
  }
}

function rank_label($rank) {
  $rank = (int)$rank;
  if ($rank === 1) return "1등";
  if ($rank === 2) return "2등";
  if ($rank === 3) return "3등";
  return "-";
}

/** 등수별 1인당 당첨금 (로또_지급금액_계산과 동일 규칙) */
function lotto_rank_prizes($total_amount, $c1, $c2, $c3) {
  $total_amount = max(0, (int)$total_amount);
  $c1 = max(0, (int)$c1);
  $c2 = max(0, (int)$c2);
  $c3 = max(0, (int)$c3);
  $이등풀 = (int)floor($total_amount * 0.07);
  $삼등풀 = (int)floor($total_amount * 0.03);
  $일등풀 = ($c1 > 0) ? (int)floor($total_amount * 0.90) : 0;
  $일등지급풀 = ($c1 > 0 && $일등풀 > 0) ? (int)floor($일등풀 * 0.70) : 0;
  return [
    1 => ($c1 > 0 && $일등지급풀 > 0) ? (int)floor($일등지급풀 / $c1) : 0,
    2 => ($c2 > 0 && $이등풀 > 0) ? (int)floor($이등풀 / $c2) : 0,
    3 => ($c3 > 0 && $삼등풀 > 0) ? (int)floor($삼등풀 / $c3) : 0,
  ];
}

function lotto_row_winnings(array $row, array $rank_prizes) {
  $winnings = (int)($row['winnings'] ?? 0);
  if ($winnings > 0) {
    return $winnings;
  }
  $rank = (int)($row['rank'] ?? 0);
  return (int)($rank_prizes[$rank] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>로또 당첨 내역 조회</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 20px;
      background: #f7f8fa;
      font-family: Arial, sans-serif;
      color: #222;
    }
    .container {
      max-width: 980px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      padding: 20px;
    }
    .title {
      margin: 0 0 14px 0;
      font-size: 22px;
      font-weight: 700;
    }
    .search-box {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .search-box input {
      width: 140px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px;
    }
    .search-box button {
      padding: 10px 14px;
      border: 0;
      border-radius: 8px;
      background: #2f80ed;
      color: #fff;
      cursor: pointer;
      font-size: 15px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }
    .card {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 12px;
      background: #fafafa;
    }
    .card .label {
      font-size: 13px;
      color: #666;
      margin-bottom: 4px;
    }
    .card .value {
      font-size: 16px;
      font-weight: 700;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      border-bottom: 1px solid #eee;
      text-align: left;
      padding: 10px 8px;
      font-size: 14px;
      vertical-align: middle;
    }
    th {
      background: #f0f3f8;
    }
    .text-center { text-align: center; }
    .rank-1 { color: #d97706; font-weight: 700; }
    .rank-2 { color: #4b5563; font-weight: 700; }
    .rank-3 { color: #059669; font-weight: 700; }
    .empty {
      margin-top: 8px;
      color: #666;
      font-size: 14px;
    }
    .mobile-list {
      display: none;
      margin-top: 10px;
    }
    .mobile-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 12px;
      margin-bottom: 10px;
      background: #fff;
    }
    .mobile-item .row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      font-size: 14px;
      padding: 4px 0;
      border-bottom: 1px dashed #f0f0f0;
    }
    .mobile-item .row:last-child {
      border-bottom: 0;
    }
    .mobile-item .label {
      color: #666;
      min-width: 74px;
    }
    .mobile-item .value {
      text-align: right;
      font-weight: 600;
      word-break: break-all;
    }
    @media (max-width: 768px) {
      body {
        padding: 10px;
      }
      .container {
        padding: 14px;
        border-radius: 10px;
      }
      .title {
        font-size: 18px;
        margin-bottom: 10px;
      }
      .search-box {
        gap: 6px;
        margin-bottom: 12px;
      }
      .search-box input {
        width: calc(100% - 84px);
        min-width: 140px;
        font-size: 14px;
        padding: 9px;
      }
      .search-box button {
        width: 72px;
        font-size: 14px;
        padding: 9px 8px;
      }
      .info-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
      }
      .card {
        padding: 10px;
      }
      .card .label {
        font-size: 12px;
      }
      .card .value {
        font-size: 14px;
      }
      .desktop-table {
        display: none;
      }
      .mobile-list {
        display: block;
      }
    }
    @media (max-width: 430px) {
      .info-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="title">로또 당첨 내역 조회</h1>

    <form method="get" class="search-box">
      <input type="number" name="result" min="1" placeholder="회차 입력" value="<?= htmlspecialchars((string)$target_drow) ?>">
      <input type="text" name="nick" maxlength="20" placeholder="닉네임 입력" value="<?= htmlspecialchars($target_nick) ?>">
      <button type="submit">조회</button>
    </form>

    <?php if ($target_drow <= 0) { ?>
      <div class="empty">조회 가능한 로또 회차가 없습니다.</div>
    <?php } else if (empty($result_row)) { ?>
      <div class="empty"><?= $target_drow ?>회차 추첨 결과가 없습니다. (tb_game_lotto_result 기준)</div>
    <?php } else { ?>
      <?php
        $n1 = str_pad((string)((int)($result_row['num1'] ?? 0)), 2, '0', STR_PAD_LEFT);
        $n2 = str_pad((string)((int)($result_row['num2'] ?? 0)), 2, '0', STR_PAD_LEFT);
        $n3 = str_pad((string)((int)($result_row['num3'] ?? 0)), 2, '0', STR_PAD_LEFT);
        $result_total_amount = (int)($result_row['total_amount'] ?? 0);
        $result_status = (int)($result_row['status'] ?? 0) === 1 ? "정산완료" : "정산대기";
      ?>

      <div class="info-grid">
        <div class="card">
          <div class="label">회차</div>
          <div class="value"><?= (int)$target_drow ?>회차</div>
        </div>
        <div class="card">
          <div class="label">추첨번호</div>
          <div class="value"><?= $n1 ?>, <?= $n2 ?>, <?= $n3 ?></div>
        </div>
        <div class="card">
          <div class="label">회차 총합금</div>
          <div class="value"><?= number_format($result_total_amount) ?></div>
        </div>
        <div class="card">
          <div class="label">정산 상태</div>
          <div class="value"><?= $result_status ?></div>
        </div>
        <div class="card">
          <div class="label">당첨 건수</div>
          <div class="value"><?= number_format($total_buy_count) ?>건</div>
        </div>
        <div class="card">
          <div class="label">닉네임 필터</div>
          <div class="value"><?= $target_nick !== '' ? htmlspecialchars($target_nick) : '전체' ?></div>
        </div>
        <div class="card">
          <div class="label">총 당첨금</div>
          <div class="value"><?= number_format($total_winnings) ?>냥</div>
        </div>
      </div>

      <table class="desktop-table">
        <thead>
          <tr>
            <th class="text-center">번호</th>
            <th>닉네임</th>
            <th>구매번호</th>
            <th class="text-center">구매금액</th>
            <th class="text-center">당첨금액</th>
            <th class="text-center">결과</th>
            <th class="text-center">처리상태</th>
            <th>구매시각</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($buy_rows)) { ?>
            <tr>
              <td colspan="8" class="text-center">해당 회차 당첨 내역이 없습니다.</td>
            </tr>
          <?php } else { ?>
            <?php foreach ($buy_rows as $i => $row) { ?>
              <?php
                $row_num1 = str_pad((string)((int)($row['num1'] ?? 0)), 2, '0', STR_PAD_LEFT);
                $row_num2 = str_pad((string)((int)($row['num2'] ?? 0)), 2, '0', STR_PAD_LEFT);
                $row_num3 = str_pad((string)((int)($row['num3'] ?? 0)), 2, '0', STR_PAD_LEFT);
                $rank = (int)($row['rank'] ?? 0);
                $rank_text = rank_label($rank);
                $rank_class = $rank > 0 ? "rank-".$rank : "";
                $buy_status = (int)($row['status'] ?? 0) === 1 ? "채점완료" : "진행중";
                $prize = (int)($row['display_winnings'] ?? 0);
                $buy_amount = (int)($row['amount'] ?? 0);
                $buy_amount_text = $buy_amount > 0 ? number_format($buy_amount) : '티켓';
              ?>
              <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars((string)($row['nick'] ?? '')) ?></td>
                <td><?= $row_num1 ?>, <?= $row_num2 ?>, <?= $row_num3 ?></td>
                <td class="text-center"><?= $buy_amount_text ?></td>
                <td class="text-center <?= $rank_class ?>"><?= number_format($prize) ?>냥</td>
                <td class="text-center <?= $rank_class ?>"><?= $rank_text ?></td>
                <td class="text-center"><?= $buy_status ?></td>
                <td><?= htmlspecialchars((string)($row['regdate'] ?? '')) ?></td>
              </tr>
            <?php } ?>
          <?php } ?>
        </tbody>
      </table>

      <div class="mobile-list">
        <?php if (empty($buy_rows)) { ?>
          <div class="empty">해당 회차 당첨 내역이 없습니다.</div>
        <?php } else { ?>
          <?php foreach ($buy_rows as $i => $row) { ?>
            <?php
              $row_num1 = str_pad((string)((int)($row['num1'] ?? 0)), 2, '0', STR_PAD_LEFT);
              $row_num2 = str_pad((string)((int)($row['num2'] ?? 0)), 2, '0', STR_PAD_LEFT);
              $row_num3 = str_pad((string)((int)($row['num3'] ?? 0)), 2, '0', STR_PAD_LEFT);
              $rank = (int)($row['rank'] ?? 0);
              $rank_text = rank_label($rank);
              $rank_class = $rank > 0 ? "rank-".$rank : "";
              $buy_status = (int)($row['status'] ?? 0) === 1 ? "채점완료" : "진행중";
              $prize = (int)($row['display_winnings'] ?? 0);
              $buy_amount = (int)($row['amount'] ?? 0);
              $buy_amount_text = $buy_amount > 0 ? number_format($buy_amount) : '티켓';
            ?>
            <div class="mobile-item">
              <div class="row"><span class="label">번호</span><span class="value"><?= $i + 1 ?></span></div>
              <div class="row"><span class="label">닉네임</span><span class="value"><?= htmlspecialchars((string)($row['nick'] ?? '')) ?></span></div>
              <div class="row"><span class="label">구매번호</span><span class="value"><?= $row_num1 ?>, <?= $row_num2 ?>, <?= $row_num3 ?></span></div>
              <div class="row"><span class="label">구매금액</span><span class="value"><?= $buy_amount_text ?></span></div>
              <div class="row"><span class="label">당첨금액</span><span class="value <?= $rank_class ?>"><?= number_format($prize) ?>냥</span></div>
              <div class="row"><span class="label">결과</span><span class="value <?= $rank_class ?>"><?= $rank_text ?></span></div>
              <div class="row"><span class="label">처리상태</span><span class="value"><?= $buy_status ?></span></div>
              <div class="row"><span class="label">구매시각</span><span class="value"><?= htmlspecialchars((string)($row['regdate'] ?? '')) ?></span></div>
            </div>
          <?php } ?>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
</body>
</html>
