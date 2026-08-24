<?php
/**
 * tb_member.은총개수 · enhance_suho → tb_member_item_bag (은총 · 강화수호)
 * URL: /page/eunchong_suho_migrate.php?code=XXXX  (민호 전용)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_item_stock_lib.php';
include_once __DIR__ . '/_eunchong_suho_migrate_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$검증 = null;

if ($준호 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'ensure') {
        $r = eunchong_suho_migrate_스키마보장();
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
    } elseif ($action === 'migrate') {
        $r = eunchong_suho_migrate_실행(true);
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
        if (!empty($r['ok'])) {
            $검증 = eunchong_suho_migrate_검증();
        }
    } elseif ($action === 'copy') {
        $r = eunchong_suho_migrate_실행(false);
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
    } elseif ($action === 'verify') {
        $검증 = eunchong_suho_migrate_검증();
        $결과메시지 = !empty($검증['ok'])
            ? '검증 OK: 원본 컬럼 합계가 0입니다. (이동 완료)'
            : '검증: 원본 컬럼에 아직 수량이 남아 있습니다.';
        $결과종류 = !empty($검증['ok']) ? 'ok' : 'err';
    }
}

$미리 = $준호 ? eunchong_suho_migrate_미리보기() : null;
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>은총·강화수호 마이그레이션</title>
<style>
:root {
  --ink: #e8e4dc;
  --muted: #9a9388;
  --ok: #6cbc6a;
  --err: #d16b6b;
  --line: rgba(255,255,255,0.12);
  --accent: #c9a96e;
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #141820 0%, #1a1f28 50%, #12151a 100%);
  color: var(--ink);
  font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
  font-size: 15px;
}
.wrap { max-width: 720px; margin: 0 auto; padding: 18px 14px 80px; }
h1 { margin: 0 0 6px; font-size: 1.35rem; color: var(--accent); letter-spacing: -0.02em; }
.sub { margin: 0 0 18px; color: var(--muted); font-size: 0.9rem; line-height: 1.45; }
.card {
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px 14px 12px;
  margin-bottom: 12px;
}
.card h2 { margin: 0 0 10px; font-size: 1.05rem; }
.stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
@media (min-width: 520px) { .stats { grid-template-columns: repeat(4, 1fr); } }
.stat {
  text-align: center; padding: 10px 8px; border-radius: 10px;
  background: rgba(255,255,255,0.03); border: 1px solid var(--line);
}
.stat b { display: block; font-size: 1.2rem; color: var(--accent); margin-bottom: 3px; }
.stat span { font-size: 0.72rem; color: var(--muted); }
.msg {
  padding: 10px 12px; border-radius: 10px; margin-bottom: 12px;
  border: 1px solid var(--line); white-space: pre-wrap;
}
.msg.ok { border-color: rgba(108,188,106,0.4); color: var(--ok); }
.msg.err { border-color: rgba(209,107,107,0.45); color: var(--err); }
table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
th, td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: left; }
th { color: var(--muted); font-weight: 600; }
td.num { text-align: right; font-variant-numeric: tabular-nums; }
.tag { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 0.72rem; }
.tag.on { background: rgba(108,188,106,0.2); color: var(--ok); }
.tag.off { background: rgba(255,255,255,0.08); color: var(--muted); }
.btn-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
button, .linkbtn {
  appearance: none; border: 1px solid var(--line); background: rgba(255,255,255,0.06);
  color: var(--ink); border-radius: 10px; padding: 10px 14px; font-size: 0.92rem; cursor: pointer;
  text-decoration: none; display: inline-flex; align-items: center;
}
button.primary { background: rgba(201,169,110,0.22); border-color: rgba(201,169,110,0.45); color: var(--accent); font-weight: 700; }
button.soft { background: rgba(108,188,106,0.12); border-color: rgba(108,188,106,0.35); }
.hint { font-size: 0.82rem; color: var(--muted); line-height: 1.45; margin: 8px 0 0; }
.need { text-align: center; padding: 40px 16px; color: var(--muted); }
code { font-size: 0.85em; color: #d4c4a0; }
.warn {
  margin: 0 0 12px; padding: 10px 12px; border-radius: 10px;
  background: rgba(201,169,110,0.1); border: 1px solid rgba(201,169,110,0.35);
  color: #e2c991; font-size: 0.86rem; line-height: 1.45;
}
</style>
</head>
<body>
<div class="wrap">
  <h1>은총 · 강화수호 마이그레이션</h1>
  <p class="sub">
    <code>tb_member.은총개수</code> → <code>tb_member_item_bag.은총</code><br>
    <code>tb_member.enhance_suho</code> → <code>tb_member_item_bag.강화수호</code><br>
    (민호 전용 · <code>?code=</code>)<br>
    <span style="opacity:.85">tb_item 스텁은 가방 카탈로그 호환용이며, 실패해도 bag 이관은 됩니다.</span>
  </p>

  <?php if (!$로그인) { ?>
    <div class="need">초대 코드가 필요합니다.<br><code>?code=XXXX</code></div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호 계정으로만 실행할 수 있습니다.<br>(현재: <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?>)</div>
  <?php } else { ?>

    <div class="warn">
      <b>이동(권장)</b>은 bag에 더하고 원본 컬럼을 0으로 만듭니다.<br>
      게임 코드가 아직 컬럼을 읽는 동안이면 먼저 <b>복사</b>만 하고, 로직 전환 후 이동하세요.
    </div>

    <?php if ($결과메시지 !== '') { ?>
      <div class="msg <?php echo $결과종류 === 'ok' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="card">
      <h2>현황 · 활성 회원 <?php echo number_format((int)$미리['members']); ?>명</h2>
      <div class="stats">
        <?php foreach (($미리['items'] ?? []) as $it) { ?>
          <div class="stat">
            <b><?php echo number_format((int)$it['src_sum']); ?></b>
            <span><?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?> 컬럼합</span>
          </div>
          <div class="stat">
            <b><?php echo number_format((int)$it['bag_sum']); ?></b>
            <span>bag.<?php echo htmlspecialchars($it['sname'], ENT_QUOTES, 'UTF-8'); ?> 합</span>
          </div>
        <?php } ?>
      </div>
      <p class="hint">
        <?php foreach (($미리['items'] ?? []) as $it) { ?>
          <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>:
          컬럼 보유 <?php echo number_format((int)$it['src_holders']); ?>명 ·
          bag 보유 <?php echo number_format((int)$it['bag_holders']); ?>명<br>
        <?php } ?>
      </p>
    </div>

    <?php foreach (($미리['items'] ?? []) as $it) { ?>
    <div class="card">
      <h2><?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?> TOP (컬럼 기준)</h2>
      <?php if (empty($it['top'])) { ?>
        <p class="hint">컬럼에 수량이 있는 회원이 없습니다.</p>
      <?php } else { ?>
        <table>
          <thead><tr><th>닉</th><th class="num">수량</th></tr></thead>
          <tbody>
          <?php foreach ($it['top'] as $row) { ?>
            <tr>
              <td><?php echo htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="num"><?php echo number_format((int)$row['src']); ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      <?php } ?>
    </div>
    <?php } ?>

    <div class="card">
      <h2>실행</h2>
      <form method="post" class="btn-row" onsubmit="return confirm('bag 컬럼을 준비하고(필수), tb_item 스텁은 가능하면 등록합니다. 진행할까요?');">
        <input type="hidden" name="action" value="ensure">
        <button type="submit">1) bag 컬럼 준비</button>
      </form>
      <form method="post" class="btn-row" onsubmit="return confirm('컬럼 수량을 bag에 복사합니다. 원본 컬럼은 유지됩니다. 진행할까요?');">
        <input type="hidden" name="action" value="copy">
        <button type="submit" class="soft">2a) 복사 (컬럼 유지)</button>
      </form>
      <form method="post" class="btn-row" onsubmit="return confirm('컬럼 수량을 bag로 이동하고 원본을 0으로 만듭니다. 되돌리기 어렵습니다. 진행할까요?');">
        <input type="hidden" name="action" value="migrate">
        <button type="submit" class="primary">2b) 이동 (컬럼 0)</button>
      </form>
      <form method="post" class="btn-row">
        <input type="hidden" name="action" value="verify">
        <button type="submit">3) 검증 (원본 0 여부)</button>
      </form>
      <p class="hint">권장: 준비 → 복사 → (로직 bag 전환) → 이동 → 검증<br>
      복사를 두 번 실행하면 bag가 중복 가산됩니다.</p>
    </div>

    <?php if (is_array($검증)) { ?>
    <div class="card">
      <h2>검증</h2>
      <table>
        <thead><tr><th>항목</th><th class="num">컬럼합</th><th class="num">bag합</th><th>상태</th></tr></thead>
        <tbody>
        <?php foreach ($검증['checks'] as $c) { ?>
          <tr>
            <td><?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo number_format((int)$c['src']); ?></td>
            <td class="num"><?php echo number_format((int)$c['bag']); ?></td>
            <td><?php echo !empty($c['match']) ? '<span class="tag on">원본0</span>' : '<span class="tag off">잔여</span>'; ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>

    <p class="hint" style="margin-top:16px;">
      <a class="linkbtn" href="/page/item_stock_migrate.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">아이템 수량 마이그레이션</a>
      <a class="linkbtn" href="/page/wallet.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">가방으로</a>
    </p>
  <?php } ?>
</div>
</body>
</html>
