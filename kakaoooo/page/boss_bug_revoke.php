<?php
/**
 * 보스 연속출현 버그 — 본방냥·은총조각 회수
 * URL: /page/boss_bug_revoke.php?code=XXXX  (민호 전용)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/mining_config.inc.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/mining_config.inc.php';
}
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/mining_ore.inc.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/mining_ore.inc.php';
}
include_once __DIR__ . '/_item_stock_lib.php';
include_once __DIR__ . '/_boss_bug_revoke_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$미리 = null;

$dateFrom = date('Y-m-d H:i:s', time() - 6 * 3600);
$dateTo = date('Y-m-d H:i:s');
$keepFirst = 1;

if ($준호) {
    boss_bug_revoke_스키마보장();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $dateFrom = trim((string)($_POST['date_from'] ?? $dateFrom));
        $dateTo = trim((string)($_POST['date_to'] ?? $dateTo));
        $keepFirst = (int)($_POST['keep_first'] ?? 1);
        if ($keepFirst < 0) {
            $keepFirst = 0;
        }
        if ($keepFirst > 50) {
            $keepFirst = 50;
        }

        if ($action === 'preview' || $action === 'run') {
            $미리 = boss_bug_revoke_미리보기($dateFrom, $dateTo, $keepFirst);
            $결과메시지 = (string)($미리['msg'] ?? '');
            $결과종류 = !empty($미리['ok']) ? 'ok' : 'err';
            if ((int)($미리['stats']['pending'] ?? 0) < 1 && $action === 'preview') {
                $결과종류 = ((int)($미리['stats']['log_total'] ?? 0) > 0) ? 'ok' : 'err';
                if ((int)($미리['stats']['log_total'] ?? 0) < 1) {
                    $결과메시지 = '해당 구간에 보스 보상 로그가 없습니다.';
                    $결과종류 = 'err';
                } elseif ((int)($미리['stats']['pending'] ?? 0) < 1) {
                    $결과메시지 = '회수할 미처리 로그가 없습니다. (유지 웨이브만 있거나 이미 회수됨)';
                }
            }
        }

        if ($action === 'run') {
            $pending = (int)(($미리['stats']['pending'] ?? 0));
            if ($pending < 1) {
                $결과메시지 = '회수할 미처리 건이 없습니다.';
                $결과종류 = 'err';
            } else {
                $r = boss_bug_revoke_실행($dateFrom, $dateTo, $keepFirst, $닉);
                $결과메시지 = (string)($r['msg'] ?? '완료');
                $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
                $미리 = boss_bug_revoke_미리보기($dateFrom, $dateTo, $keepFirst);
                if (!empty($r['details'])) {
                    $미리['last_run'] = $r;
                }
            }
        }
    } else {
        $미리 = boss_bug_revoke_미리보기($dateFrom, $dateTo, $keepFirst);
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>보스 연속처치 보상 회수</title>
  <style>
    :root {
      --bg: #0f1419;
      --card: #1a222d;
      --line: #2a3544;
      --text: #e8eef6;
      --muted: #8b9bb0;
      --accent: #3d8bfd;
      --ok: #3dd68c;
      --err: #ff6b6b;
      --warn: #f0b429;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Pretendard", "Apple SD Gothic Neo", sans-serif;
      background: radial-gradient(1200px 600px at 10% -10%, #1c2a3d 0%, var(--bg) 55%);
      color: var(--text);
      min-height: 100vh;
      padding: 20px 14px 48px;
    }
    .wrap { max-width: 920px; margin: 0 auto; }
    h1 { font-size: 1.35rem; margin: 0 0 6px; letter-spacing: -0.02em; }
    .sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 18px; line-height: 1.45; }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px;
      margin-bottom: 14px;
    }
    label { display: block; font-size: 0.82rem; color: var(--muted); margin-bottom: 6px; }
    input, select {
      width: 100%;
      background: #121820;
      border: 1px solid var(--line);
      color: var(--text);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 0.95rem;
    }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    @media (max-width: 640px) { .row { grid-template-columns: 1fr; } }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    button, .btn {
      border: 0;
      border-radius: 10px;
      padding: 11px 16px;
      font-weight: 700;
      cursor: pointer;
      font-size: 0.92rem;
    }
    .btn-preview { background: var(--accent); color: #fff; }
    .btn-run { background: var(--err); color: #fff; }
    .btn-link { background: transparent; color: var(--muted); border: 1px solid var(--line); text-decoration: none; display: inline-block; }
    .msg {
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 14px;
      font-size: 0.92rem;
      line-height: 1.4;
    }
    .msg.ok { background: rgba(61,214,140,.12); color: var(--ok); border: 1px solid rgba(61,214,140,.25); }
    .msg.err { background: rgba(255,107,107,.12); color: var(--err); border: 1px solid rgba(255,107,107,.25); }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 8px;
      margin-bottom: 12px;
    }
    .stat {
      background: #121820;
      border-radius: 10px;
      padding: 10px 12px;
      border: 1px solid var(--line);
    }
    .stat strong { display: block; font-size: 1.05rem; margin-bottom: 2px; }
    .stat span { color: var(--muted); font-size: 0.78rem; }
    table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
    th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
    th { color: var(--muted); font-weight: 600; font-size: 0.78rem; }
    .badge {
      display: inline-block;
      font-size: 0.72rem;
      padding: 2px 8px;
      border-radius: 999px;
      background: #243041;
      color: var(--muted);
    }
    .badge.warn { background: rgba(240,180,41,.15); color: var(--warn); }
    .badge.ok { background: rgba(61,214,140,.15); color: var(--ok); }
    .deny {
      text-align: center;
      padding: 48px 16px;
      color: var(--muted);
    }
    .hint { color: var(--muted); font-size: 0.82rem; line-height: 1.45; margin-top: 8px; }
    .wave-list { font-size: 0.84rem; color: var(--muted); }
    .wave-list li { margin-bottom: 4px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>보스 연속처치 보상 회수</h1>
  <p class="sub">
    연속 출현 버그로 연달아 처치된 구간의 <b>본방냥 · 은총조각</b>을
    <code>tb_point_log</code> 기준으로 회수합니다. (민호 전용)
  </p>

  <?php if (!$로그인) { ?>
    <div class="deny">코드로 접속해주세요.<br><code>?code=XXXX</code></div>
  <?php } elseif (!$준호) { ?>
    <div class="deny">민호만 사용할 수 있습니다.<br>(현재: <?= htmlspecialchars($닉, ENT_QUOTES, 'UTF-8') ?>)</div>
  <?php } else { ?>

  <?php if ($결과메시지 !== '') { ?>
    <div class="msg <?= $결과종류 === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8') ?></div>
  <?php } ?>

  <form method="post" class="card" id="revoke-form">
    <div class="row">
      <div>
        <label for="date_from">시작 (로그 시각)</label>
        <input type="text" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" placeholder="YYYY-MM-DD HH:MM:SS">
      </div>
      <div>
        <label for="date_to">끝</label>
        <input type="text" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" placeholder="YYYY-MM-DD HH:MM:SS">
      </div>
    </div>
    <div style="margin-top:10px;max-width:220px">
      <label for="keep_first">앞에서부터 유지할 처치 웨이브 수</label>
      <input type="number" id="keep_first" name="keep_first" min="0" max="50" value="<?= (int)$keepFirst ?>">
    </div>
    <p class="hint">
      · 처치 로그(<code>보스처치|</code>) 시각이 <?= (int)BOSS_BUG_REVOKE_GAP_SEC ?>초 이내면 같은 웨이브로 묶습니다.<br>
      · 유지 웨이브(기본 1)의 보상은 남기고, 이후 웨이브의 본방냥·은총조각·드랍조각을 회수합니다.<br>
      · 조각이 부족하면 은총 1개 = <?= (int)(defined('MINING_ORE_EUNCHONG_SHARDS') ? MINING_ORE_EUNCHONG_SHARDS : 10) ?>조각으로 환산해 차감합니다.<br>
      · 이미 회수한 로그는 다시 깎지 않습니다.
    </p>
    <div class="actions">
      <button type="submit" name="action" value="preview" class="btn-preview">미리보기</button>
      <button type="submit" name="action" value="run" class="btn-run" onclick="return confirm('선택한 구간의 미처리 보상을 정말 회수할까요?');">회수 실행</button>
      <a class="btn btn-link" href="/page/boss.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">보스 페이지</a>
    </div>
  </form>

  <?php if (is_array($미리)) {
      $st = $미리['stats'] ?? [];
      $waves = $미리['waves'] ?? [];
      $byNick = $미리['by_nick'] ?? [];
  ?>
  <div class="card">
    <div class="stats">
      <div class="stat"><strong><?= (int)($st['wave_total'] ?? 0) ?></strong><span>처치 웨이브</span></div>
      <div class="stat"><strong><?= (int)($st['wave_revoke'] ?? 0) ?></strong><span>회수 대상 웨이브</span></div>
      <div class="stat"><strong><?= (int)($st['pending'] ?? 0) ?></strong><span>미처리 로그</span></div>
      <div class="stat"><strong><?= htmlspecialchars((string)($st['nyang_total_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></strong><span>회수 예정 본방냥</span></div>
      <div class="stat"><strong><?= number_format((int)($st['shard_total'] ?? 0)) ?>개</strong><span>회수 예정 은총조각</span></div>
      <div class="stat"><strong><?= (int)($st['nick_cnt'] ?? 0) ?>명</strong><span>대상 닉</span></div>
    </div>

    <?php if ($waves !== []) { ?>
    <ol class="wave-list">
      <?php foreach ($waves as $w) {
          $keep = ((int)$w['wave'] <= (int)$keepFirst);
      ?>
      <li>
        <span class="badge <?= $keep ? 'ok' : 'warn' ?>"><?= $keep ? '유지' : '회수' ?></span>
        #<?= (int)$w['wave'] ?>
        <?= htmlspecialchars($w['start'], ENT_QUOTES, 'UTF-8') ?>
        ~ <?= htmlspecialchars($w['end'], ENT_QUOTES, 'UTF-8') ?>
        · 처치로그 <?= (int)$w['kill_logs'] ?> · 관련로그 <?= count($w['idxs']) ?>
      </li>
      <?php } ?>
    </ol>
    <?php } ?>
  </div>

  <div class="card">
    <h2 style="margin:0 0 10px;font-size:1rem">닉별 회수 예정</h2>
    <?php if ($byNick === []) { ?>
      <div class="hint">회수 대상이 없습니다.</div>
    <?php } else { ?>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>닉</th>
            <th>본방냥</th>
            <th>보유 본방냥</th>
            <th>은총조각</th>
            <th>보유 조각</th>
            <th>로그</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byNick as $row) { ?>
          <tr>
            <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['nyang_fmt'] ?? boss_bug_revoke_표시($row['nyang']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['have_nyang_fmt'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((int)$row['shard']) ?></td>
            <td><?= number_format((int)$row['have_shard']) ?></td>
            <td><?= (int)$row['logs'] ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>

  <?php if (!empty($미리['last_run']['details'])) { ?>
  <div class="card">
    <h2 style="margin:0 0 10px;font-size:1rem">방금 회수 결과</h2>
    <table>
      <thead>
        <tr><th>닉</th><th>본방냥 차감</th><th>조각 차감</th><th>은총 차감</th></tr>
      </thead>
      <tbody>
        <?php foreach ($미리['last_run']['details'] as $d) { ?>
        <tr>
          <td><?= htmlspecialchars($d['nick'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars(boss_bug_revoke_표시($d['nyang_done']), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((int)$d['shard_done']) ?></td>
          <td><?= number_format((int)$d['eunchong_done']) ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php } ?>

  <?php } ?>
  <?php } ?>
</div>
</body>
</html>
