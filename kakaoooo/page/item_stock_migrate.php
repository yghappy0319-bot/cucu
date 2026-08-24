<?php
/**
 * tb_member_item(로우) → tb_member_item_bag(tb_member 1행·tb_item.sname 수량)
 * URL: /page/item_stock_migrate.php?code=XXXX  (민호 전용)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_item_stock_lib.php';

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
    if ($action === 'create') {
        item_stock_테이블보장();
        $결과메시지 = 'tb_member_item_bag 테이블 확인·생성 · 컬럼=tb_item.sname 동기화';
        $결과종류 = 'ok';
    } elseif ($action === 'purge_orphan') {
        $r = item_stock_비정상아이템_삭제();
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
    } elseif ($action === 'purge_nick_mismatch') {
        $r = item_stock_닉불일치_삭제();
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
    } elseif ($action === 'migrate') {
        $r = item_stock_마이그레이션_실행();
        $결과메시지 = (string)($r['msg'] ?? '완료');
        $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
        if (!empty($r['ok'])) {
            $검증 = item_stock_검증();
        }
    } elseif ($action === 'verify') {
        item_stock_테이블보장();
        $검증 = item_stock_검증();
        $결과메시지 = !empty($검증['ok']) ? '검증 일치: 모든 sname 아이템 합계가 같습니다.' : '검증 불일치: 아래 표를 확인하세요.';
        $결과종류 = !empty($검증['ok']) ? 'ok' : 'err';
    }
}

$미리 = $준호 ? item_stock_미리보기() : null;
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>아이템 수량 마이그레이션</title>
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
h1 { margin: 0 0 6px; font-size: 1.45rem; color: var(--accent); letter-spacing: -0.02em; }
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
.stat b { display: block; font-size: 1.25rem; color: var(--accent); margin-bottom: 3px; }
.stat span { font-size: 0.75rem; color: var(--muted); }
.msg {
  padding: 10px 12px; border-radius: 10px; margin-bottom: 12px;
  border: 1px solid var(--line); white-space: pre-wrap;
}
.msg.ok { border-color: rgba(108,188,106,0.4); color: var(--ok); }
.msg.err { border-color: rgba(209,107,107,0.4); color: var(--err); }
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
button.danger { background: rgba(209,107,107,0.18); border-color: rgba(209,107,107,0.45); color: #e8a0a0; font-weight: 700; }
.hint { font-size: 0.82rem; color: var(--muted); line-height: 1.45; margin: 8px 0 0; }
.need { text-align: center; padding: 40px 16px; color: var(--muted); }
code { font-size: 0.85em; color: #d4c4a0; }
</style>
</head>
<body>
<div class="wrap">
  <h1>아이템 수량 마이그레이션</h1>
  <p class="sub">
    <code>tb_member</code> 친구 전원 1행 → <code>tb_member_item_bag</code><br>
    수량은 <code>tb_item.sname</code> 아이템의 미사용(status=0) 개수만 합산합니다.
  </p>

  <?php if (!$로그인) { ?>
    <div class="need">초대 코드가 필요합니다.<br><code>?code=XXXX</code></div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호 계정으로만 실행할 수 있습니다.<br>(현재: <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?>)</div>
  <?php } else { ?>

    <?php if ($결과메시지 !== '') { ?>
      <div class="msg <?php echo $결과종류 === 'ok' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="card">
      <h2>현황</h2>
      <div class="stats">
        <div class="stat"><b><?php echo number_format((int)$미리['member_n']); ?></b><span>tb_member (활성)</span></div>
        <div class="stat"><b><?php echo number_format((int)$미리['unused_rows']); ?></b><span>미사용 로우</span></div>
        <div class="stat"><b><?php echo number_format((int)$미리['orphan']['total']); ?></b><span>비정상 아이템 로우</span></div>
        <div class="stat"><b><?php echo number_format((int)($미리['nick_mismatch']['total'] ?? 0)); ?></b><span>닉 불일치 bag</span></div>
        <div class="stat"><b><?php echo $미리['bag_exists'] ? number_format((int)$미리['bag_rows']) : '—'; ?></b><span>bag 행 수</span></div>
      </div>
      <p class="hint">
        bag 컬럼(tb_item.sname): <?php echo htmlspecialchars(implode(', ', $미리['keys']), ENT_QUOTES, 'UTF-8'); ?>
      </p>
    </div>

    <div class="card">
      <h2>비정상 아이템 (tb_item.sname 없음)</h2>
      <?php if ((int)$미리['orphan']['total'] < 1) { ?>
        <p class="hint">삭제할 비정상 아이템이 없습니다.</p>
      <?php } else { ?>
        <table>
          <thead><tr><th>itemname</th><th class="num">로우 수</th></tr></thead>
          <tbody>
          <?php foreach (($미리['orphan']['rows'] ?? []) as $it) { ?>
            <tr>
              <td><?php echo htmlspecialchars($it['itemname'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="num"><?php echo number_format((int)$it['cnt']); ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
        <form method="post" class="btn-row" onsubmit="return confirm('tb_item.sname에 없는 아이템 로우를 전부 삭제합니다. 되돌릴 수 없습니다. 진행할까요?');">
          <input type="hidden" name="action" value="purge_orphan">
          <button type="submit" class="danger">비정상 아이템 전부 삭제 (<?php echo number_format((int)$미리['orphan']['total']); ?>개)</button>
        </form>
      <?php } ?>
      <p class="hint">마이그레이션 전에 먼저 정리하는 것을 권장합니다.</p>
    </div>

    <div class="card">
      <h2>닉 불일치 bag (tb_member 와 불일치)</h2>
      <?php if ((int)($미리['nick_mismatch']['total'] ?? 0) < 1) { ?>
        <p class="hint">삭제할 닉 불일치 bag 행이 없습니다.</p>
      <?php } else { ?>
        <table>
          <thead><tr><th>midx</th><th>bag.nick</th><th>tb_member.name</th></tr></thead>
          <tbody>
          <?php foreach (($미리['nick_mismatch']['rows'] ?? []) as $it) { ?>
            <tr>
              <td class="num"><?php echo (int)$it['midx']; ?></td>
              <td><?php echo htmlspecialchars($it['bag_nick'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $it['mem_name'] !== '' ? htmlspecialchars($it['mem_name'], ENT_QUOTES, 'UTF-8') : '<span class="tag off">회원없음</span>'; ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
        <form method="post" class="btn-row" onsubmit="return confirm('tb_member와 midx·닉이 맞지 않는 bag 행을 전부 삭제합니다. 되돌릴 수 없습니다. 진행할까요?');">
          <input type="hidden" name="action" value="purge_nick_mismatch">
          <button type="submit" class="danger">닉 불일치 bag 전부 삭제 (<?php echo number_format((int)$미리['nick_mismatch']['total']); ?>행)</button>
        </form>
      <?php } ?>
      <p class="hint">midx 없음 또는 bag.nick ≠ member.name 인 행을 삭제합니다.</p>
    </div>

    <div class="card">
      <h2>미사용 아이템 TOP</h2>
      <table>
        <thead><tr><th>아이템</th><th class="num">개수</th><th>sname</th></tr></thead>
        <tbody>
        <?php foreach (($미리['items'] ?? []) as $it) { ?>
          <tr>
            <td><?php echo htmlspecialchars($it['itemname'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo number_format((int)$it['cnt']); ?></td>
            <td><?php echo !empty($it['tracked']) ? '<span class="tag on">공식</span>' : '<span class="tag off">비정상</span>'; ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>실행</h2>
      <form method="post" class="btn-row" onsubmit="return confirm('테이블/컬럼을 생성·동기화할까요?');">
        <input type="hidden" name="action" value="create">
        <button type="submit">1) 테이블·sname 컬럼 동기화</button>
      </form>
      <form method="post" class="btn-row" onsubmit="return confirm('tb_member 전원으로 bag를 다시 만듭니다. 기존 bag는 TRUNCATE 됩니다. 진행할까요?');">
        <input type="hidden" name="action" value="migrate">
        <button type="submit" class="primary">2) 마이그레이션 (회원 전원)</button>
      </form>
      <form method="post" class="btn-row">
        <input type="hidden" name="action" value="verify">
        <button type="submit">3) 합계 검증</button>
      </form>
      <p class="hint">권장 순서: 비정상 삭제 → 테이블 동기화 → 마이그레이션 → 검증</p>
    </div>

    <?php if (is_array($검증)) { ?>
    <div class="card">
      <h2>검증 결과</h2>
      <table>
        <thead><tr><th>아이템</th><th class="num">bag 합</th><th class="num">원본 개수</th><th>결과</th></tr></thead>
        <tbody>
        <?php foreach ($검증['checks'] as $c) { ?>
          <tr>
            <td><?php echo htmlspecialchars($c['item'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo number_format((int)$c['bag']); ?></td>
            <td class="num"><?php echo number_format((int)$c['src']); ?></td>
            <td><?php echo !empty($c['match']) ? '<span class="tag on">OK</span>' : '<span class="tag off">DIFF</span>'; ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>

    <p class="hint" style="margin-top:16px;">
      <a class="linkbtn" href="/page/wallet.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">가방으로</a>
    </p>
  <?php } ?>
</div>
</body>
</html>
