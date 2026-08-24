<?php
/**
 * .구매 시세 기준 아이템 일괄 회수 → 게임냥 지급
 * URL: /page/item_buyback.php?code=XXXX  (민호 전용)
 * 대상: 강일·수호·지목·프변·제한·색변·닉변·선물
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag.inc.php';
}
include_once __DIR__ . '/_item_stock_lib.php';
include_once __DIR__ . '/_item_buyback_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$미리 = null;

if ($준호) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        if ($action === 'preview') {
            $미리 = item_buyback_미리보기();
            $결과메시지 = (string)($미리['msg'] ?? '');
            $결과종류 = !empty($미리['ok']) ? 'ok' : 'err';
        } elseif ($action === 'run' || $action === 'run_all') {
            $keys = null;
            if ($action === 'run' && isset($_POST['keys']) && is_array($_POST['keys'])) {
                $keys = array_values(array_filter(array_map('strval', $_POST['keys'])));
            }
            if ($action === 'run_all' || $keys === []) {
                $keys = null;
            }
            $r = item_buyback_실행($닉, $keys);
            $결과메시지 = (string)($r['msg'] ?? '완료');
            $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
            $미리 = item_buyback_미리보기();
        }
    } else {
        $미리 = item_buyback_미리보기();
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$prices = $미리['prices'] ?? ($준호 ? item_buyback_단가맵() : []);
$priceInfo = $미리['price_info'] ?? ($준호 ? item_buyback_단가정보() : []);
$rows = $미리['rows'] ?? [];
$friends = $미리['friends'] ?? [];
$stats = $미리['stats'] ?? null;
$bcmathOn = $미리['bcmath'] ?? function_exists('bcmul');
$baseTotal = $준호 ? item_buyback_기준총게임냥() : '0';
$baseTotalFmt = $미리['base_total_fmt'] ?? ($준호 ? item_buyback_금액표시($baseTotal) : '—');
$saturatedItems = [];
foreach ($priceInfo as $pn => $pi) {
    if (!empty($pi['saturated'])) {
        $saturatedItems[] = $pn;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>구매시세 일괄 회수</title>
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
.wrap { max-width: 920px; margin: 0 auto; padding: 18px 14px 80px; }
h1 { margin: 0 0 6px; font-size: 1.35rem; color: var(--accent); letter-spacing: -0.02em; }
.sub { margin: 0 0 18px; color: var(--muted); font-size: 0.9rem; line-height: 1.45; }
.card {
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
  margin-bottom: 12px;
}
.card h2 { margin: 0 0 10px; font-size: 1.05rem; }
.msg {
  padding: 10px 12px; border-radius: 10px; margin-bottom: 12px;
  border: 1px solid var(--line); white-space: pre-wrap;
}
.msg.ok { border-color: rgba(108,188,106,0.4); color: var(--ok); }
.msg.err { border-color: rgba(209,107,107,0.45); color: var(--err); }
.warn {
  margin: 0 0 12px; padding: 10px 12px; border-radius: 10px;
  background: rgba(201,169,110,0.1); border: 1px solid rgba(201,169,110,0.35);
  color: #e2c991; font-size: 0.86rem; line-height: 1.45;
}
.stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 10px; }
@media (min-width: 560px) { .stats { grid-template-columns: repeat(4, 1fr); } }
.stat {
  text-align: center; padding: 10px 8px; border-radius: 10px;
  background: rgba(255,255,255,0.03); border: 1px solid var(--line);
}
.stat b { display: block; font-size: 1.05rem; color: var(--accent); margin-bottom: 3px; word-break: break-all; }
.stat span { font-size: 0.72rem; color: var(--muted); }
.price-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;
}
@media (min-width: 560px) { .price-grid { grid-template-columns: repeat(4, 1fr); } }
.price-item {
  padding: 8px 10px; border-radius: 10px;
  background: rgba(255,255,255,0.03); border: 1px solid var(--line);
  font-size: 0.82rem;
}
.price-item .n { color: var(--accent); font-weight: 700; }
.price-item .p { color: var(--muted); margin-top: 2px; word-break: break-all; }
.row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
button {
  appearance: none; border: 1px solid var(--line); background: rgba(255,255,255,0.06);
  color: var(--ink); border-radius: 10px; padding: 10px 14px; font-size: 0.92rem; cursor: pointer;
}
button.primary { background: rgba(201,169,110,0.22); border-color: rgba(201,169,110,0.45); color: var(--accent); font-weight: 700; }
button.danger { background: rgba(209,107,107,0.18); border-color: rgba(209,107,107,0.45); color: #e8a0a0; font-weight: 700; }
table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
th, td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
th { color: var(--muted); font-weight: 600; }
td.num { text-align: right; font-variant-numeric: tabular-nums; }
.zero { color: var(--err); }
.need { text-align: center; padding: 40px 16px; color: var(--muted); }
code { font-size: 0.85em; color: #d4c4a0; }
.hint { font-size: 0.8rem; color: var(--muted); line-height: 1.45; margin-top: 8px; }
.scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>
</head>
<body>
<div class="wrap">
  <h1>구매시세 일괄 회수</h1>
  <p class="sub">
    친구 보유 아이템을 <code>.구매</code> 시세(단가×개수)로 회수하고 게임냥을 지급합니다.<br>
    민호 전용 · <code>?code=</code>
  </p>

  <?php if (!$로그인) { ?>
    <div class="need">초대 코드가 필요합니다.<br><code>?code=XXXX</code></div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호 계정으로만 실행할 수 있습니다.<br>(현재: <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?>)</div>
  <?php } else { ?>

    <div class="warn">
      <b>대상</b>: 강일 · 수호 · 지목 · 프변 · 제한 · 색변 · 닉변 · 선물<br>
      <b>단가</b>: 현재 <code>.구매</code>와 동일 (실시간 총게임냥 × percent → 억 절사 · 문자열 연산)<br>
      <b>처리</b>: 가방에서 전량 차감 + 게임냥(<code>point</code>) 전액 지급 · 수수료/시세하락 없음<br>
      <b>참고</b>: 예전 922경(=PHP_INT_MAX) 상한 잘림은 수정됨 — 새로고침 후 단가·합계를 다시 확인하세요.
    </div>

    <?php if ($결과메시지 !== '') { ?>
      <div class="msg <?php echo $결과종류 === 'ok' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <?php if ($saturatedItems !== []) { ?>
      <div class="msg err">⚠️ <?php echo htmlspecialchars(implode(', ', $saturatedItems), ENT_QUOTES, 'UTF-8'); ?> — <code>tb_item.buy</code>가 BIGINT 상한(9,223,372,036,854,775,807 = 922경)에 붙어 있습니다.
실제 시세가 아니라 오버플로 포화값이라 단가 0으로 처리(스킵)했습니다. <code>percent</code>를 설정하거나 <code>buy</code>를 정상값으로 되돌려 주세요.</div>
    <?php } ?>

    <div class="card">
      <h2>현재 .구매 단가</h2>
      <p class="hint" style="margin-top:0">
        기준 총게임냥(실시간): <b><?php echo htmlspecialchars($baseTotalFmt, ENT_QUOTES, 'UTF-8'); ?></b>
        (<?php echo strlen($baseTotal); ?>자리) ·
        bcmath <?php echo $bcmathOn ? '사용 가능' : '<b style="color:#d16b6b">없음 — 큰 수 계산이 922경에서 잘립니다</b>'; ?>
      </p>
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>아이템</th>
              <th class="num">percent</th>
              <th class="num">buy 원본</th>
              <th>산출</th>
              <th class="num">단가</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (item_buyback_대상목록() as $iname) {
              $pi = $priceInfo[$iname] ?? [];
              $u = $prices[$iname] ?? '0';
              $src = (string)($pi['source'] ?? '-');
              $srcLabel = [
                'percent' => 'percent 기준',
                'buy' => 'buy 컬럼',
                'buy_saturated' => 'buy 포화(무시)',
                'none' => '아이템 없음',
              ][$src] ?? $src;
              ?>
              <tr class="<?php echo !empty($pi['saturated']) ? 'zero' : ''; ?>">
                <td><b><?php echo htmlspecialchars($iname, ENT_QUOTES, 'UTF-8'); ?></b></td>
                <td class="num"><?php echo htmlspecialchars((string)($pi['percent'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars((string)($pi['buy_raw'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($srcLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars(item_buyback_금액표시($u), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <form method="post" action="/page/item_buyback.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" class="row">
        <input type="hidden" name="action" value="preview">
        <button type="submit" class="primary">보유 현황 새로고침</button>
      </form>
    </div>

    <?php if (is_array($stats)) { ?>
      <div class="card">
        <h2>미리보기</h2>
        <div class="stats">
          <div class="stat"><b><?php echo (int)$stats['holders']; ?></b><span>보유자</span></div>
          <div class="stat"><b><?php echo (int)$stats['lines']; ?></b><span>건수</span></div>
          <div class="stat"><b><?php echo (int)$stats['qty']; ?></b><span>총 개수</span></div>
          <div class="stat"><b><?php echo htmlspecialchars((string)$stats['payout_fmt'], ENT_QUOTES, 'UTF-8'); ?></b><span>지급 예정</span></div>
        </div>

        <?php if ($friends !== []) { ?>
          <h2 style="margin-top:14px">친구별 지급 총액</h2>
          <div class="scroll">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>닉</th>
                  <th>내역</th>
                  <th class="num">개수</th>
                  <th class="num">지급 총액</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $fi = 1;
                foreach ($friends as $f) {
                  ?>
                  <tr>
                    <td><?php echo $fi++; ?></td>
                    <td><?php echo htmlspecialchars((string)$f['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$f['items_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="num"><?php echo (int)$f['qty']; ?></td>
                    <td class="num"><b><?php echo htmlspecialchars((string)$f['pay_fmt'], ENT_QUOTES, 'UTF-8'); ?></b></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        <?php } ?>

        <?php if ($rows !== []) { ?>
          <h2 style="margin-top:16px">아이템별 상세</h2>
          <form method="post" action="/page/item_buyback.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" id="runForm"
                onsubmit="return confirm('선택한 아이템을 회수하고 게임냥을 지급할까요? 되돌릴 수 없습니다.');">
            <input type="hidden" name="action" value="run" id="runAction">
            <div class="scroll">
              <table>
                <thead>
                  <tr>
                    <th><input type="checkbox" id="chkAll" checked title="전체"></th>
                    <th>닉</th>
                    <th>아이템</th>
                    <th class="num">수량</th>
                    <th class="num">단가</th>
                    <th class="num">지급</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r) {
                    $zero = !empty($r['zero_price']);
                    ?>
                    <tr class="<?php echo $zero ? 'zero' : ''; ?>">
                      <td>
                        <input type="checkbox" name="keys[]" value="<?php echo htmlspecialchars((string)$r['key'], ENT_QUOTES, 'UTF-8'); ?>"
                          <?php echo $zero ? '' : 'checked'; ?> <?php echo $zero ? 'disabled' : ''; ?>>
                      </td>
                      <td><?php echo htmlspecialchars((string)$r['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$r['item'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="num"><?php echo (int)$r['qty']; ?></td>
                      <td class="num"><?php echo htmlspecialchars((string)$r['unit_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="num"><?php echo htmlspecialchars((string)$r['pay_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <div class="row">
              <button type="submit" class="danger" onclick="document.getElementById('runAction').value='run';">선택 회수·지급</button>
              <button type="submit" class="danger" onclick="document.getElementById('runAction').value='run_all'; document.querySelectorAll('#runForm input[name=\'keys[]\']:not(:disabled)').forEach(function(c){c.checked=true;});">전체 회수·지급</button>
            </div>
            <p class="hint">시세 0원인 행은 스킵됩니다. 실행 후 가방에서 빠지고 게임냥이 바로 올라갑니다.</p>
          </form>
        <?php } else { ?>
          <p class="hint">보유분이 없습니다.</p>
        <?php } ?>
      </div>
    <?php } ?>

  <?php } ?>
</div>
<script>
(function () {
  var all = document.getElementById('chkAll');
  if (!all) return;
  all.addEventListener('change', function () {
    document.querySelectorAll('#runForm input[name="keys[]"]:not(:disabled)').forEach(function (c) {
      c.checked = all.checked;
    });
  });
})();
</script>
</body>
</html>
