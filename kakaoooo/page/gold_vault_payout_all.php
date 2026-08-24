<?php
/**
 * 개인금고 예치중 금괴 전원 일괄 지급
 * 원금 + 미수령 이자 · 위약금(중도수수료) 없음 · 민호 전용
 * URL: /page/gold_vault_payout_all.php?code=XXXX
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (!function_exists('냥_정수문자열')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_gold_vault_lib.php';

gv_테이블보장();

$회원 = gv_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && gv_준호인가($닉);

$결과메시지 = '';
$결과종류 = '';
$실행오류 = [];
$미리 = null;
$확인문구 = '일괄지급';

if ($준호) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        if ($action === 'run') {
            $typed = trim((string)($_POST['confirm'] ?? ''));
            $checked = !empty($_POST['agree']);
            if (!$checked || $typed !== $확인문구) {
                $결과메시지 = '확인란을 체크하고 「' . $확인문구 . '」를 정확히 입력해야 실행돼요.';
                $결과종류 = 'err';
                $미리 = gv_전체금괴일괄회수_미리보기();
            } else {
                $r = gv_전체금괴일괄회수($닉);
                $결과메시지 = (string)($r['msg'] ?? '완료');
                $결과종류 = !empty($r['ok']) ? 'ok' : 'err';
                $실행오류 = $r['errors'] ?? [];
                $미리 = gv_전체금괴일괄회수_미리보기();
            }
        } else {
            $미리 = gv_전체금괴일괄회수_미리보기();
        }
    } else {
        $미리 = gv_전체금괴일괄회수_미리보기();
    }
}

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$rows = $미리['rows'] ?? [];
$byTerm = $미리['by_term'] ?? [];
$barCount = (int)($미리['bar_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>개인금고 일괄 지급</title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --ink: #f3e6c8;
  --muted: #a89878;
  --gold: #f0d78c;
  --ok: #7dba7a;
  --err: #d16b6b;
  --warn: #e2c991;
  --line: rgba(240, 215, 140, 0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #12151a 0%, #1c2028 45%, #151820 100%);
  color: var(--ink);
  font-family: 'MyFont', 'Apple SD Gothic Neo', sans-serif;
  font-size: 15px;
}
.wrap { max-width: 920px; margin: 0 auto; padding: 16px 14px 80px; }
.top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 16px; }
h1 { margin: 0; font-size: 1.45rem; color: var(--gold); letter-spacing: -0.03em; }
.sub { margin: 6px 0 0; color: var(--muted); font-size: 0.88rem; line-height: 1.45; }
.links { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.back {
  display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 10px;
  border: 1px solid var(--line); background: rgba(255,255,255,0.04);
  color: var(--ink); text-decoration: none; font-size: 13px;
}
.card {
  background: rgba(0,0,0,0.28);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
  margin-bottom: 12px;
}
.card h2 { margin: 0 0 10px; font-size: 1.05rem; color: var(--gold); }
.msg {
  padding: 10px 12px; border-radius: 10px; margin-bottom: 12px;
  border: 1px solid var(--line); white-space: pre-wrap; line-height: 1.45;
}
.msg.ok { border-color: rgba(125,186,122,0.4); color: var(--ok); }
.msg.err { border-color: rgba(209,107,107,0.45); color: var(--err); }
.warn {
  margin: 0 0 12px; padding: 10px 12px; border-radius: 10px;
  background: rgba(201,169,110,0.1); border: 1px solid rgba(201,169,110,0.35);
  color: var(--warn); font-size: 0.86rem; line-height: 1.5;
}
.stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 10px; }
@media (min-width: 560px) { .stats { grid-template-columns: repeat(4, 1fr); } }
.stat {
  text-align: center; padding: 10px 8px; border-radius: 10px;
  background: rgba(255,255,255,0.03); border: 1px solid var(--line);
}
.stat b { display: block; font-size: 1.05rem; color: var(--gold); margin-bottom: 3px; word-break: break-all; }
.stat span { font-size: 0.72rem; color: var(--muted); }
.term-line { margin: 0 0 8px; color: var(--muted); font-size: 0.84rem; line-height: 1.45; }
.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--line); }
table { width: 100%; border-collapse: collapse; min-width: 560px; }
th, td { padding: 9px 8px; text-align: left; border-bottom: 1px solid var(--line); font-size: 13px; }
th { color: var(--muted); font-weight: 500; background: rgba(0,0,0,0.2); white-space: nowrap; }
td.nick { color: var(--gold); font-weight: 600; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
td.num.em { color: var(--gold); font-weight: 700; }
.deny {
  text-align: center; padding: 40px 16px; color: var(--muted);
  border: 1px dashed var(--line); border-radius: 14px;
}
.deny.warn-c { color: #c47a4a; }
.form-row { margin: 12px 0; }
.form-row label { display: flex; align-items: flex-start; gap: 8px; line-height: 1.45; font-size: 0.9rem; }
.form-row input[type="text"] {
  width: 100%; max-width: 280px; margin-top: 6px; padding: 10px 12px;
  border-radius: 10px; border: 1px solid var(--line); background: rgba(0,0,0,0.35);
  color: var(--ink); font-size: 15px;
}
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 12px 18px; border-radius: 12px; border: none; cursor: pointer;
  font-size: 15px; font-weight: 600; font-family: inherit;
}
.btn.danger {
  background: linear-gradient(180deg, #c47a4a, #a85a32);
  color: #1a1008;
}
.btn.danger:disabled { opacity: 0.45; cursor: not-allowed; }
.err-list { margin: 8px 0 0; padding-left: 18px; color: var(--err); font-size: 0.84rem; line-height: 1.5; }
.hint { margin: 8px 0 0; color: var(--muted); font-size: 0.8rem; line-height: 1.45; }
.empty { text-align: center; padding: 28px 12px; color: var(--muted); }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>개인금고 일괄 지급</h1>
      <p class="sub">예치중 금괴 전원 · 원금 + 쌓인 이자 · 위약금 없음 · 민호 전용</p>
    </div>
    <div class="links">
      <a class="back" href="/page/gold_vault_admin.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">친구별 통계</a>
      <a class="back" href="/page/gold_vault.php<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">개인금고</a>
    </div>
  </div>

<?php if (!$로그인): ?>
  <div class="deny">연구실 코드 링크로 접속해주세요.</div>
<?php elseif (!$준호): ?>
  <div class="deny warn-c">민호만 실행할 수 있는 페이지예요.</div>
<?php else: ?>

  <?php if ($결과메시지 !== ''): ?>
    <div class="msg <?php echo htmlspecialchars($결과종류, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($결과메시지, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($실행오류 !== []): ?>
      <ul class="err-list">
        <?php foreach (array_slice($실행오류, 0, 40) as $e): ?>
          <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
        <?php if (count($실행오류) > 40): ?>
          <li>… 외 <?php echo count($실행오류) - 40; ?>건</li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>

  <div class="warn">
    예치중인 모든 금괴를 즉시 해지하고, 원금과 미수령 이자를 각 친구 게임냥으로 지급합니다.<br>
    중도해지 위약금·수수료는 받지 않습니다. 이미 수령한 이자는 포함되지 않습니다. 되돌릴 수 없습니다.
  </div>

  <div class="card">
    <h2>지급 예정 요약</h2>
    <div class="stats">
      <div class="stat"><b><?php echo (int)($미리['nick_count'] ?? 0); ?></b><span>대상 인원</span></div>
      <div class="stat"><b><?php echo $barCount; ?></b><span>금괴 수</span></div>
      <div class="stat"><b><?php echo htmlspecialchars((string)($미리['principal_disp'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></b><span>원금 합</span></div>
      <div class="stat"><b><?php echo htmlspecialchars((string)($미리['interest_disp'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></b><span>미수령 이자</span></div>
    </div>
    <div class="stats" style="grid-template-columns:1fr;">
      <div class="stat"><b><?php echo htmlspecialchars((string)($미리['payout_disp'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></b><span>총 지급 예정 (원금+이자)</span></div>
    </div>
    <?php if ($byTerm !== []): ?>
      <p class="term-line">기간별:
        <?php
        $bits = [];
        foreach ($byTerm as $t => $n) {
            $bits[] = (int)$t . '일 ' . (int)$n . '개';
        }
        echo htmlspecialchars(implode(' · ', $bits), ENT_QUOTES, 'UTF-8');
        ?>
      </p>
    <?php endif; ?>
  </div>

  <?php if ($barCount > 0): ?>
    <div class="card">
      <h2>닉네임별 내역</h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>닉네임</th>
              <th class="num">금괴</th>
              <th class="num">원금</th>
              <th class="num">이자</th>
              <th class="num">지급</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="nick"><?php echo htmlspecialchars((string)$r['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo (int)$r['bars']; ?></td>
                <td class="num"><?php echo htmlspecialchars((string)$r['principal_disp'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars((string)$r['interest_disp'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num em"><?php echo htmlspecialchars((string)$r['payout_disp'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>실행</h2>
      <form method="post" id="runForm" onsubmit="return confirm('정말로 전원에게 일괄 지급할까요?\\n되돌릴 수 없습니다.');">
        <input type="hidden" name="action" value="run">
        <div class="form-row">
          <label>
            <input type="checkbox" name="agree" value="1" required>
            <span>위약금 없이 원금+이자 전액을 각 계정 게임냥으로 지급하는 것에 동의합니다.</span>
          </label>
        </div>
        <div class="form-row">
          <div>확인 문구 <strong><?php echo htmlspecialchars($확인문구, ENT_QUOTES, 'UTF-8'); ?></strong> 입력</div>
          <input type="text" name="confirm" autocomplete="off" placeholder="<?php echo htmlspecialchars($확인문구, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <button type="submit" class="btn danger">전원 일괄 지급 실행</button>
        <p class="hint">실행 중 시간이 걸릴 수 있습니다. 페이지를 닫지 마세요.</p>
      </form>
    </div>
  <?php else: ?>
    <div class="card"><div class="empty">예치중인 금괴가 없습니다.</div></div>
  <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
