<?php
/**
 * 홀짝 웹 페이백 미수령 내역 · 0단위(뒤 N자리) 일괄 삭제
 * URL: /page/odd_even_payback_trim.php?code=XXXX  (민호 전용)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/api/game/odd_even_payback.inc.php')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/game/odd_even_payback.inc.php';
}
include_once __DIR__ . '/_item_stock_lib.php';
include_once __DIR__ . '/_odd_even_payback_trim_lib.php';

$회원 = item_stock_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && item_stock_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$zeros = '0000';
$미리 = null;

if ($준호) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $zeros = trim((string)($_POST['zeros'] ?? '0000'));
        if (!preg_match('/^0+$/', $zeros)) {
            $zeros = '0000';
        }
        if ($action === 'preview') {
            $미리 = 홀짝페이백_미리보기($zeros);
            $결과메시지 = (string)($미리['msg'] ?? '');
            $결과종류 = !empty($미리['ok']) ? 'ok' : 'err';
        } elseif ($action === 'run') {
            $confirm = trim((string)($_POST['confirm'] ?? ''));
            if ($confirm !== '삭제') {
                $결과메시지 = '실행하려면 확인란에 「삭제」를 입력하세요.';
                $결과종류 = 'err';
                $미리 = 홀짝페이백_미리보기($zeros);
            } else {
                $r = 홀짝페이백_일괄삭감($zeros, $닉);
                $결과메시지 = (string)($r['msg'] ?? '완료');
                $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
                $미리 = 홀짝페이백_미리보기($zeros);
            }
        }
    } else {
        $zeros = isset($_GET['zeros']) && preg_match('/^0+$/', (string)$_GET['zeros'])
            ? (string)$_GET['zeros']
            : '0000';
        $미리 = 홀짝페이백_미리보기($zeros);
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$rows = $미리['rows'] ?? [];
$cnt = (int)($미리['cnt'] ?? 0);
$sumBeforeFmt = (string)($미리['sum_before_fmt'] ?? '—');
$sumAfterFmt = (string)($미리['sum_after_fmt'] ?? '—');
$div = (string)($미리['div'] ?? '1');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>홀짝 페이백 0단위 삭감</title>
<style>
:root {
  --ink: #e8e4dc;
  --muted: #9a9388;
  --ok: #6cbc6a;
  --err: #d16b6b;
  --line: rgba(255,255,255,0.12);
  --accent: #c9a96e;
  --bg: #141210;
  --card: #1c1916;
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  font-family: "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
  background: radial-gradient(1200px 600px at 20% -10%, #2a241c 0%, var(--bg) 55%);
  color: var(--ink);
}
.wrap { max-width: 920px; margin: 0 auto; padding: 20px 14px 48px; }
h1 { font-size: 1.25rem; margin: 0 0 6px; font-weight: 700; }
.sub { color: var(--muted); font-size: 0.88rem; margin: 0 0 18px; line-height: 1.45; }
.need {
  background: var(--card); border: 1px solid var(--line); border-radius: 12px;
  padding: 28px 18px; text-align: center; color: var(--muted);
}
.card {
  background: var(--card); border: 1px solid var(--line); border-radius: 12px;
  padding: 14px 14px 16px; margin-bottom: 14px;
}
.stats {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px;
}
.stat {
  background: rgba(0,0,0,0.25); border-radius: 10px; padding: 10px 8px; text-align: center;
}
.stat b { display: block; font-size: 0.95rem; margin-top: 4px; word-break: break-all; }
.stat span { color: var(--muted); font-size: 0.75rem; }
.msg {
  padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 0.9rem;
  white-space: pre-wrap; line-height: 1.4;
}
.msg.ok { background: rgba(108,188,106,0.12); color: var(--ok); }
.msg.err { background: rgba(209,107,107,0.12); color: var(--err); }
label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 6px; }
.row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
select, input[type="text"] {
  background: #0e0c0a; color: var(--ink); border: 1px solid var(--line);
  border-radius: 8px; padding: 10px 12px; font-size: 0.95rem;
}
select { min-width: 140px; }
input[type="text"] { min-width: 120px; }
.btn {
  appearance: none; border: 0; border-radius: 8px; padding: 10px 14px;
  font-size: 0.9rem; font-weight: 600; cursor: pointer;
}
.btn-preview { background: #3d3428; color: var(--ink); }
.btn-run { background: var(--accent); color: #1a140c; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.hint { font-size: 0.8rem; color: var(--muted); margin: 8px 0 0; line-height: 1.4; }
table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
th, td { padding: 8px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
th { color: var(--muted); font-weight: 600; font-size: 0.75rem; }
td.num { text-align: right; font-variant-numeric: tabular-nums; word-break: break-all; }
.changed { color: var(--accent); }
.scroll { max-height: 60vh; overflow: auto; -webkit-overflow-scrolling: touch; }
@media (max-width: 640px) {
  .stats { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="wrap">
  <h1>홀짝 페이백 0단위 삭감</h1>
  <p class="sub">미수령 <code>payback_pool</code> 내역을 보고, 뒤 N자리(0/00/000/0000)를 일괄 삭제합니다.<br>
  수령된 잔액(point)은 건드리지 않습니다. · 민호 전용</p>

  <?php if (!$로그인) { ?>
    <div class="need">로그인 코드가 필요합니다.<br><code>?code=</code> 를 붙여 열어주세요.</div>
  <?php } elseif (!$준호) { ?>
    <div class="need">민호 계정으로만 실행할 수 있습니다.<br>(현재: <?php echo htmlspecialchars($닉, ENT_QUOTES, 'UTF-8'); ?>)</div>
  <?php } else { ?>

    <?php if ($결과메시지 !== '') { ?>
      <div class="msg <?php echo $결과종류 === 'ok' ? 'ok' : 'err'; ?>"><?php
        echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8');
      ?></div>
    <?php } ?>

    <div class="card">
      <div class="stats">
        <div class="stat"><span>미수령 인원</span><b><?php echo number_format($cnt); ?>명</b></div>
        <div class="stat"><span>현재 총합</span><b><?php echo htmlspecialchars($sumBeforeFmt, ENT_QUOTES, 'UTF-8'); ?></b></div>
        <div class="stat"><span>삭감 후 총합 (÷<?php echo htmlspecialchars($div, ENT_QUOTES, 'UTF-8'); ?>)</span><b><?php echo htmlspecialchars($sumAfterFmt, ENT_QUOTES, 'UTF-8'); ?></b></div>
      </div>

      <form method="post" action="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="zeros">삭제할 뒤자리 (0 개수)</label>
        <div class="row">
          <select name="zeros" id="zeros">
            <?php
            $opts = ['0' => '0 (÷10)', '00' => '00 (÷100)', '000' => '000 (÷1000)', '0000' => '0000 (÷10000)', '00000' => '00000 (÷10만)'];
            foreach ($opts as $val => $label) {
              $sel = ($zeros === $val) ? ' selected' : '';
              echo '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            ?>
          </select>
          <button type="submit" name="action" value="preview" class="btn btn-preview">미리보기</button>
        </div>
        <p class="hint">예) 52,360 → <code>0000</code> 적용 시 5냥 (뒤 4자리 삭제)</p>
      </form>

      <form method="post" action="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
            onsubmit="return confirm('정말로 미수령 페이백 전체를 ÷<?php echo htmlspecialchars($div, ENT_QUOTES, 'UTF-8'); ?> 할까요?');">
        <input type="hidden" name="zeros" value="<?php echo htmlspecialchars($zeros, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="confirm">실행 확인 (「삭제」입력)</label>
        <div class="row">
          <input type="text" name="confirm" id="confirm" placeholder="삭제" autocomplete="off">
          <button type="submit" name="action" value="run" class="btn btn-run"<?php echo $cnt < 1 ? ' disabled' : ''; ?>>일괄 삭감 실행</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>닉</th>
              <th style="text-align:right">현재</th>
              <th style="text-align:right">삭감 후</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []) { ?>
              <tr><td colspan="4" style="color:var(--muted);text-align:center;padding:20px;">미수령 페이백 없음</td></tr>
            <?php } else {
              $i = 0;
              foreach ($rows as $r) {
                $i++;
                $cls = !empty($r['changed']) ? ' changed' : '';
                ?>
                <tr>
                  <td><?php echo $i; ?></td>
                  <td><?php echo htmlspecialchars((string)$r['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="num"><?php echo htmlspecialchars((string)$r['before_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="num<?php echo $cls; ?>"><?php echo htmlspecialchars((string)$r['after_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php }
            } ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php } ?>
</div>
</body>
</html>
