<?php
/**
 * 은총2 저가 구매 회수 · 게임냥 환불
 * URL: /page/eunchong_underprice_migrate.php?code=XXXX  (민호 전용)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/eunchong_shop_buy.inc.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/item_bag_enhance.inc.php';
include_once __DIR__ . '/_item_stock_lib.php';
include_once __DIR__ . '/_eunchong_underprice_migrate_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$미리 = null;
$paste = '';
$manual = '';
$manualUnit = EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT;
$mode = 'paste';
$dateFrom = '';
$dateTo = '';

if ($준호) {
    eunchong_underprice_스키마보장();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $paste = (string)($_POST['paste'] ?? '');
        $manual = (string)($_POST['manual'] ?? '');
        $manualUnit = trim((string)($_POST['manual_unit'] ?? EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT));
        if ($manualUnit === '') {
            $manualUnit = EUNCHONG_UNDERPRICE_BUG_UNIT_TEXT;
        }
        $mode = trim((string)($_POST['mode'] ?? 'paste'));
        if ($mode !== 'log') {
            $mode = 'paste';
        }
        $dateFrom = trim((string)($_POST['date_from'] ?? ''));
        $dateTo = trim((string)($_POST['date_to'] ?? ''));

        if ($action === 'manual') {
            $r = eunchong_underprice_수동일괄($manual, $닉, $manualUnit);
            $결과메시지 = (string)($r['msg'] ?? '완료');
            $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
        }

        $rows = [];
        if ($mode === 'log') {
            $rows = eunchong_underprice_로그스캔($dateFrom, $dateTo);
        } else {
            $rows = eunchong_underprice_채팅파싱($paste);
        }

        if ($action === 'preview') {
            $미리 = eunchong_underprice_미리보기($rows);
            $parsed = (int)($미리['stats']['parsed'] ?? 0);
            $pending = (int)($미리['stats']['pending'] ?? 0);
            if ($parsed < 1) {
                $결과메시지 = '채팅에서 은총2 구매 줄을 찾지 못했습니다. 형식을 확인하세요.';
                $결과종류 = 'err';
            } elseif ($pending < 1) {
                $결과메시지 = "파싱 {$parsed}건 · 미처리 저가 건 없음 (정상가/이미처리/스킵)";
                $결과종류 = 'err';
            } else {
                $결과메시지 = "파싱 {$parsed}건 → 미처리 저가 {$pending}건 · 환불 예정 " . ($미리['stats']['refund_fmt'] ?? '');
                $결과종류 = 'ok';
            }
        } elseif ($action === 'run' || $action === 'run_all') {
            // rows_json은 보조. 기본은 붙여넣기/로그 재파싱(잘림·키 충돌 방지)
            $payload = trim((string)($_POST['rows_json'] ?? ''));
            if ($payload !== '' && $rows === []) {
                $decoded = json_decode($payload, true);
                if (is_array($decoded) && $decoded !== []) {
                    $rows = $decoded;
                }
            }

            $keys = null;
            if ($action === 'run' && isset($_POST['keys']) && is_array($_POST['keys'])) {
                $keys = array_values(array_filter(array_map('strval', $_POST['keys'])));
            }
            // run_all 이거나 체크 없음 → pending 전부
            if ($action === 'run_all' || $keys === []) {
                $keys = null;
            }

            $r = eunchong_underprice_실행($rows, $닉, $keys);
            $결과메시지 = (string)($r['msg'] ?? '완료');
            $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
            $미리 = eunchong_underprice_미리보기($rows);
        }
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$fairFmt = $준호 ? eunchong_underprice_표시(eunchong_underprice_정상단가()) : '—';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>은총2 저가 회수·환불</title>
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
.wrap { max-width: 860px; margin: 0 auto; padding: 18px 14px 80px; }
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
textarea {
  width: 100%; min-height: 160px; border-radius: 10px; border: 1px solid var(--line);
  background: rgba(0,0,0,0.35); color: var(--ink); padding: 10px 12px;
  font-family: ui-monospace, Menlo, monospace; font-size: 0.82rem; line-height: 1.4;
}
textarea.hidden-payload { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
.row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
label.inline { font-size: 0.85rem; color: var(--muted); }
input[type="date"] {
  border: 1px solid var(--line); background: rgba(0,0,0,0.35); color: var(--ink);
  border-radius: 8px; padding: 8px 10px;
}
button, .linkbtn {
  appearance: none; border: 1px solid var(--line); background: rgba(255,255,255,0.06);
  color: var(--ink); border-radius: 10px; padding: 10px 14px; font-size: 0.92rem; cursor: pointer;
}
button.primary { background: rgba(201,169,110,0.22); border-color: rgba(201,169,110,0.45); color: var(--accent); font-weight: 700; }
button.danger { background: rgba(209,107,107,0.18); border-color: rgba(209,107,107,0.45); color: #e8a0a0; font-weight: 700; }
table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
th, td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
th { color: var(--muted); font-weight: 600; }
td.num { text-align: right; font-variant-numeric: tabular-nums; }
.tag { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 0.7rem; }
.tag.ok { background: rgba(108,188,106,0.2); color: var(--ok); }
.tag.warn { background: rgba(201,169,110,0.18); color: #e2c991; }
.tag.done { background: rgba(255,255,255,0.08); color: var(--muted); }
.need { text-align: center; padding: 40px 16px; color: var(--muted); }
code { font-size: 0.85em; color: #d4c4a0; }
.hint { font-size: 0.8rem; color: var(--muted); line-height: 1.45; margin-top: 8px; }
.tabs { display: flex; gap: 6px; margin-bottom: 10px; }
.tabs button.active { border-color: rgba(201,169,110,0.55); color: var(--accent); }
</style>
</head>
<body>
<div class="wrap">
  <h1>은총2 저가 회수 · 환불</h1>
  <p class="sub">
    PHP 정수 오버플로로 싸게 산 <code>은총2</code> 구매를 회수하고 게임냥을 돌려줍니다.<br>
    민호 전용 · <code>?code=</code><br>
    현재 정상 단가(참고): <b><?php echo htmlspecialchars($fairFmt, ENT_QUOTES, 'UTF-8'); ?></b>
  </p>

  <?php if (!$로그인) { ?>
    <div class="need">초대 코드가 필요합니다.<br><code>?code=XXXX</code></div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호 계정으로만 실행할 수 있습니다.<br>(현재: <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?>)</div>
  <?php } else { ?>

    <div class="warn">
      <b>대상</b>: 결제액이 정상 단가의 55% 미만인 은총2 구매<br>
      <b>처리</b>: 보유 은총에서 구매 수량만큼 회수(부족분은 있는 만큼) + 결제 게임냥 전액 환불<br>
      <b>과거 건</b>은 채팅 구매 메시지(<code>►[닉] 은총2 구매 …</code>)를 붙여넣으세요. 신규 구매는 로그 스캔도 가능합니다.
    </div>

    <?php if ($결과메시지 !== '') { ?>
      <div class="msg <?php echo $결과종류 === 'ok' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="card">
      <h2>0) 빠른 수동 · 닉 수량</h2>
      <form method="post" action="/page/eunchong_underprice_migrate.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('은총을 회수하고 수량×단가만큼 환불할까요?');">
        <input type="hidden" name="action" value="manual">
        <textarea name="manual" style="min-height:72px" placeholder="예) 하리 2"><?php echo htmlspecialchars($manual, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <div class="row" style="margin-top:8px">
          <label class="inline">1개당 환불
            <input type="text" name="manual_unit" value="<?php echo htmlspecialchars($manualUnit, ENT_QUOTES, 'UTF-8'); ?>" style="width:110px;margin-left:6px;border:1px solid var(--line);background:rgba(0,0,0,0.35);color:var(--ink);border-radius:8px;padding:8px 10px">
          </label>
          <button type="submit" class="danger">회수 · 환불 실행</button>
        </div>
        <p class="hint"><code>하리 2</code> → 은총 2개 회수 + <b>2 × 931경</b> 환불. 여러 줄 가능. 보유가 부족하면 있는 만큼만 회수하고 환불은 수량분 전액.</p>
      </form>
    </div>

    <div class="card">
      <h2>1) 대상 불러오기</h2>
      <form method="post" action="/page/eunchong_underprice_migrate.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="tabs">
          <button type="button" class="<?php echo $mode === 'paste' ? 'active' : ''; ?>" data-mode="paste">채팅 붙여넣기</button>
          <button type="button" class="<?php echo $mode === 'log' ? 'active' : ''; ?>" data-mode="log">구매 로그 스캔</button>
        </div>
        <input type="hidden" name="mode" id="modeField" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="preview">

        <div id="panelPaste" style="<?php echo $mode === 'log' ? 'display:none' : ''; ?>">
          <textarea name="paste" placeholder="예)&#10;►[준호] 은총2 구매 931경겜냥&#10;(10개 미만 1% 추가금: 9경겜냥)&#10;✨ 은총 +1 (보유 2개)"><?php echo htmlspecialchars($paste, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <p class="hint">여러 건을 한 번에 붙여넣어도 됩니다. 같은 금액·같은 문구라도 줄마다 따로 처리합니다.</p>
        </div>

        <div id="panelLog" style="<?php echo $mode === 'log' ? '' : 'display:none'; ?>">
          <div class="row">
            <label class="inline">시작 <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="inline">끝 <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>"></label>
          </div>
          <p class="hint">오늘부터 쌓인 <code>tb_eunchong_buy_log</code>만 스캔됩니다. (과거 채팅 건은 붙여넣기 사용)</p>
        </div>

        <div class="row">
          <button type="submit" class="primary">미리보기</button>
        </div>
      </form>
    </div>

    <?php if (is_array($미리) && !empty($미리['rows'])) {
        $stats = $미리['stats'];
        $rowsForJson = [];
        // 실행용: 미리보기 목록의 원본 필드만
        foreach ($미리['rows'] as $r) {
            $rowsForJson[] = [
                'nick' => $r['nick'],
                'qty' => $r['qty'],
                'paid' => $r['paid'],
                'raw' => $r['raw'],
                'key' => $r['key'],
            ];
        }
        $rowsJson = json_encode($rowsForJson, JSON_UNESCAPED_UNICODE);
        if (!is_string($rowsJson)) {
            $rowsJson = '[]';
        }
    ?>
    <div class="card">
      <h2>2) 대상 목록 · 정상단가 <?php echo htmlspecialchars((string)$미리['fair_fmt'], ENT_QUOTES, 'UTF-8'); ?></h2>
      <div class="stats">
        <div class="stat"><b><?php echo (int)($stats['parsed'] ?? 0); ?></b><span>파싱</span></div>
        <div class="stat"><b><?php echo (int)$stats['pending']; ?></b><span>미처리 건</span></div>
        <div class="stat"><b><?php echo htmlspecialchars((string)$stats['refund_fmt'], ENT_QUOTES, 'UTF-8'); ?></b><span>환불 예정</span></div>
        <div class="stat"><b><?php echo (int)$stats['skip_done']; ?></b><span>이미 처리</span></div>
      </div>

      <form method="post" action="/page/eunchong_underprice_migrate.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" id="runForm">
        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
        <textarea class="hidden-payload" name="paste" aria-hidden="true"><?php echo htmlspecialchars($paste, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <textarea class="hidden-payload" name="rows_json" aria-hidden="true"><?php echo htmlspecialchars($rowsJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <table>
          <thead>
            <tr>
              <th></th>
              <th>닉</th>
              <th class="num">구매</th>
              <th class="num">결제</th>
              <th class="num">보유</th>
              <th class="num">회수</th>
              <th>상태</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($미리['rows'] as $row) {
                $disabled = !empty($row['done']);
                ?>
            <tr>
              <td>
                <?php if (!$disabled) { ?>
                <input type="checkbox" name="keys[]" value="<?php echo htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8'); ?>" checked>
                <?php } ?>
              </td>
              <td><?php echo htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="num"><?php echo (int)$row['qty']; ?></td>
              <td class="num"><?php echo htmlspecialchars($row['paid_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="num"><?php echo (int)$row['held']; ?></td>
              <td class="num"><?php echo (int)$row['reclaim']; ?></td>
              <td>
                <?php if (!empty($row['done'])) { ?>
                  <span class="tag done">처리됨</span>
                <?php } elseif (!empty($row['short'])) { ?>
                  <span class="tag warn">보유부족</span>
                <?php } else { ?>
                  <span class="tag ok">회수가능</span>
                <?php } ?>
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
        <div class="row">
          <button type="submit" name="action" value="run_all" class="danger" onclick="return confirm('미처리 저가 건을 전부 회수·환불할까요?');">미처리 전부 실행</button>
          <button type="submit" name="action" value="run" onclick="return confirm('체크한 건만 회수·환불할까요?');">선택 건만 실행</button>
        </div>
        <p class="hint">보유부족이어도 결제액은 전액 환불하고, 은총은 보유분만큼만 회수합니다. 실패 건은 결과에 닉별로 표시됩니다.</p>
      </form>
    </div>
    <?php } ?>

  <?php } ?>
</div>
<script>
(function() {
  var modeField = document.getElementById('modeField');
  var panelPaste = document.getElementById('panelPaste');
  var panelLog = document.getElementById('panelLog');
  if (!modeField) return;
  document.querySelectorAll('.tabs button[data-mode]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.tabs button[data-mode]').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var m = btn.getAttribute('data-mode') || 'paste';
      modeField.value = m;
      if (panelPaste) panelPaste.style.display = m === 'log' ? 'none' : '';
      if (panelLog) panelLog.style.display = m === 'log' ? '' : 'none';
    });
  });
})();
</script>
</body>
</html>
