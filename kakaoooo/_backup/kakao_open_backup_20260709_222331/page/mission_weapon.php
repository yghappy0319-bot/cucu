<?php
/**
 * 무기 보유 회원 → 일방 미션「무기구매 1회」일괄 완료
 * /page/mission_weapon.php
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
require_once __DIR__ . '/../api/function.php';

$오늘 = date('Y-m-d');
$실행됨 = false;
$추가건수 = 0;
$실행결과 = [];

$대상_sql = "
  SELECT m.name, m.item, m.enhance
  FROM tb_member m
  LEFT JOIN tb_mission mi
    ON mi.nick = m.name AND mi.types = '일방' AND mi.status = '무기구매'
  WHERE m.status = 0
    AND m.item IS NOT NULL
    AND TRIM(m.item) != ''
    AND mi.idx IS NULL
  ORDER BY m.name ASC
";

$이미완료_sql = "
  SELECT m.name, m.item, m.enhance
  FROM tb_member m
  INNER JOIN tb_mission mi
    ON mi.nick = m.name AND mi.types = '일방' AND mi.status = '무기구매'
  WHERE m.status = 0
    AND m.item IS NOT NULL
    AND TRIM(m.item) != ''
  ORDER BY m.name ASC
";

$insert_sql = "
  INSERT INTO tb_mission (nick, types, status, chk, regdate)
  SELECT m.name, '일방', '무기구매', '0', '{$오늘}'
  FROM tb_member m
  LEFT JOIN tb_mission mi
    ON mi.nick = m.name AND mi.types = '일방' AND mi.status = '무기구매'
  WHERE m.status = 0
    AND m.item IS NOT NULL
    AND TRIM(m.item) != ''
    AND mi.idx IS NULL
";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run']) && $_POST['run'] === '1') {
  $대상_rs = db_query($대상_sql);
  while ($row = db_fetch($대상_rs)) {
    $실행결과[] = $row;
  }
  if (count($실행결과) > 0) {
    db_query($insert_sql);
    $추가건수 = count($실행결과);
  }
  $실행됨 = true;
}

$대상목록 = [];
$대상_rs = db_query($대상_sql);
while ($row = db_fetch($대상_rs)) {
  $대상목록[] = $row;
}

$이미완료목록 = [];
$완료_rs = db_query($이미완료_sql);
while ($row = db_fetch($완료_rs)) {
  $이미완료목록[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>무기구매 미션 일괄 완료</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Noto Sans KR', Arial, sans-serif;
      background: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .wrap {
      max-width: 720px;
      margin: 0 auto;
      background: #fff;
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }
    h1 { margin: 0 0 8px; font-size: 1.25rem; }
    .sub { color: #666; font-size: 0.9rem; margin-bottom: 20px; }
    .box {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 14px;
      margin-bottom: 16px;
      font-size: 0.85rem;
      overflow-x: auto;
    }
    pre {
      margin: 0;
      white-space: pre-wrap;
      word-break: break-all;
      font-size: 0.8rem;
      line-height: 1.5;
    }
    .stat { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .stat span {
      padding: 8px 12px;
      border-radius: 8px;
      background: #eef2ff;
      font-size: 0.9rem;
    }
    .stat .warn { background: #fff3cd; }
    .stat .ok { background: #d1e7dd; }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
      margin-top: 8px;
    }
    th, td {
      border: 1px solid #dee2e6;
      padding: 8px 10px;
      text-align: left;
    }
    th { background: #f1f3f5; }
    .btn {
      display: inline-block;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      background: #339af0;
      color: #fff;
      font-size: 1rem;
      cursor: pointer;
      margin-top: 8px;
    }
    .btn:hover { background: #228be6; }
    .btn:disabled { background: #adb5bd; cursor: not-allowed; }
    .result {
      margin-top: 16px;
      padding: 12px 14px;
      border-radius: 8px;
      background: #d1e7dd;
      color: #0f5132;
    }
    .empty { color: #868e96; font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>무기구매 1회 미션 일괄 완료</h1>
    <p class="sub">
      현재 무기를 보유한 회원(<code>tb_member.item</code> 있음) 중
      아직 미션이 없는 사람에게 <code>tb_mission</code> (일방 / 무기구매) 를 추가합니다.
    </p>

    <div class="stat">
      <span class="warn">추가 대상: <b><?php echo count($대상목록); ?></b>명</span>
      <span class="ok">이미 완료(무기 보유): <b><?php echo count($이미완료목록); ?></b>명</span>
    </div>

    <?php if ($실행됨) { ?>
      <div class="result">
        ✅ 실행 완료 — <b><?php echo (int)$추가건수; ?></b>명 미션 추가 (regdate: <?php echo htmlspecialchars($오늘); ?>)
      </div>
    <?php } ?>

    <form method="post" onsubmit="return confirm('무기 보유 <?php echo count($대상목록); ?>명에게 미션을 추가할까요?');">
      <input type="hidden" name="run" value="1">
      <button type="submit" class="btn" <?php echo count($대상목록) < 1 ? 'disabled' : ''; ?>>
        일괄 미션 완료 처리 (<?php echo count($대상목록); ?>명)
      </button>
    </form>

    <h2 style="margin-top:24px;font-size:1rem;">실행 쿼리</h2>
    <div class="box"><pre><?php echo htmlspecialchars(trim($insert_sql)); ?></pre></div>

    <h2 style="margin-top:20px;font-size:1rem;">추가 대상 목록</h2>
    <?php if (count($대상목록) < 1) { ?>
      <p class="empty">추가할 대상이 없습니다. (무기 보유자는 모두 이미 미션 완료 상태)</p>
    <?php } else { ?>
      <table>
        <thead><tr><th>닉</th><th>무기</th><th>강화</th></tr></thead>
        <tbody>
          <?php foreach ($대상목록 as $r) { ?>
            <tr>
              <td><?php echo htmlspecialchars($r['name']); ?></td>
              <td><?php echo htmlspecialchars($r['item']); ?></td>
              <td>+<?php echo (int)$r['enhance']; ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    <?php } ?>

    <h2 style="margin-top:20px;font-size:1rem;">이미 미션 완료 (무기 보유)</h2>
    <?php if (count($이미완료목록) < 1) { ?>
      <p class="empty">해당 없음</p>
    <?php } else { ?>
      <table>
        <thead><tr><th>닉</th><th>무기</th><th>강화</th></tr></thead>
        <tbody>
          <?php foreach ($이미완료목록 as $r) { ?>
            <tr>
              <td><?php echo htmlspecialchars($r['name']); ?></td>
              <td><?php echo htmlspecialchars($r['item']); ?></td>
              <td>+<?php echo (int)$r['enhance']; ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    <?php } ?>
  </div>
</body>
</html>
