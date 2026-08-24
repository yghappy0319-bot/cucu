<?php
/**
 * 생타 200×3일 연속 미지급분 일괄 지급 — 민호 전용
 * URL: /page/raw_tasu_streak_catchup.php?code=XXXX
 *
 * 최근 14일(오늘 포함) 생타를 다시 세서,
 * 연속 3일 200타인데 일방신청권·연장권이 안 나간 창만 지급.
 */
require __DIR__ . '/_wallet_preauth.php';
$root = dirname(__DIR__);
if (!function_exists('db_select')) {
    $lib = $root . '/lib/_function.php';
    if (!is_file($lib) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $lib = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/lib/_function.php';
    }
    if (is_file($lib)) {
        include_once $lib;
    }
}
if (!function_exists('getTwoCharNick')) {
    $fn = $root . '/api/function.php';
    if (!is_file($fn) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $fn = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/api/function.php';
    }
    if (is_file($fn)) {
        include_once $fn;
    }
}
$streak = $root . '/api/_auto_raw_tasu_streak.php';
if (is_file($streak)) {
    include_once $streak;
}

const RTS_ADMIN = '민호';
const RTS_DAYS = 14;

function rts_닉($nick): string {
    $nick = trim((string)$nick);
    if ($nick === '') {
        return '';
    }
    if (function_exists('getTwoCharNick')) {
        $t = getTwoCharNick($nick);
        if ($t !== '') {
            return $t;
        }
    }
    return $nick;
}

$code = '';
$nick = '';
if (!empty($GLOBALS['wallet_preauth']['code'])) {
    $code = trim((string)$GLOBALS['wallet_preauth']['code']);
}
if ($code === '' && isset($_REQUEST['code'])) {
    $code = trim((string)$_REQUEST['code']);
}
if (!empty($GLOBALS['wallet_preauth']['row']['name'])) {
    $nick = trim((string)$GLOBALS['wallet_preauth']['row']['name']);
}
if ($nick === '' && $code !== '') {
    $행 = @db_select("SELECT name FROM tb_member WHERE code = '" . addslashes($code) . "' LIMIT 1");
    $nick = trim((string)($행['name'] ?? ''));
}
$nick = rts_닉($nick);
$로그인 = ($nick !== '');
$허용 = ($로그인 && $nick === RTS_ADMIN);
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

$flash = '';
$flashOk = false;
$runResults = [];
$미리 = [
    'from' => '',
    'to' => date('Y-m-d'),
    'days' => RTS_DAYS,
    'goal' => 200,
    'need' => 3,
    'pay_n' => 0,
    'pay_qty' => 0,
    'rows' => [],
];

if ($허용) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'run') {
        if (!function_exists('생타연속_기간소급')) {
            $flash = '생타연속 소급 기능을 불러올 수 없어요.';
        } else {
            $결과 = 생타연속_기간소급(RTS_DAYS, true);
            $flash = (string)($결과['msg'] ?? '');
            $flashOk = !empty($결과['ok']);
            $runResults = $결과['results'] ?? [];
        }
    }
    if (function_exists('생타연속_기간미지급목록')) {
        $미리 = 생타연속_기간미지급목록(RTS_DAYS);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>생타 3일권 일괄 지급</title>
<style>
:root {
  --ink: #e8eef5;
  --muted: #8a9bb0;
  --accent: #7eb8ff;
  --ok: #7dba7a;
  --warn: #e0a060;
  --bad: #e08080;
  --line: rgba(180, 200, 230, 0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #10151c 0%, #1a2230 50%, #121820 100%);
  color: var(--ink);
  font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
  font-size: 15px;
}
.wrap { max-width: 920px; margin: 0 auto; padding: 16px 14px 80px; }
.top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
h1 { margin: 0; font-size: 22px; color: var(--accent); letter-spacing: -0.03em; }
.sub { margin: 6px 0 0; color: var(--muted); font-size: 13px; line-height: 1.45; }
.back {
  display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 10px;
  border: 1px solid var(--line); background: rgba(255,255,255,0.04);
  color: var(--ink); text-decoration: none; font-size: 13px; white-space: nowrap;
}
.card {
  padding: 14px; border-radius: 14px; background: rgba(0,0,0,0.28);
  border: 1px solid var(--line); margin-bottom: 14px;
}
.flash { padding: 12px 14px; border-radius: 12px; margin-bottom: 14px; border: 1px solid var(--line); }
.flash.ok { background: rgba(125,186,122,0.15); color: var(--ok); }
.flash.bad { background: rgba(224,128,128,0.15); color: var(--bad); }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; margin-bottom: 12px; }
.stat { padding: 10px; border-radius: 10px; background: rgba(255,255,255,0.04); }
.stat strong { display: block; font-size: 1.05rem; }
.stat span { color: var(--muted); font-size: 11px; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: 44px; padding: 0 18px; border: 0; border-radius: 10px;
  font-size: 15px; font-weight: 700; cursor: pointer;
  color: #1a1408; background: linear-gradient(180deg, #f0d78c, #d4a017);
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.note { font-size: 12px; color: var(--muted); line-height: 1.55; margin: 10px 0 0; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
th { color: var(--muted); font-weight: 500; font-size: 11px; }
td.num { text-align: right; white-space: nowrap; }
.pay { color: var(--ok); font-weight: 700; }
.skip { color: var(--muted); }
.login { text-align: center; padding: 40px 16px; color: var(--muted); line-height: 1.5; }
.scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.win { display: block; margin: 0 0 4px; white-space: nowrap; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>생타 3일권 일괄 지급</h1>
      <p class="sub">최근 14일 생타 200 × 3일 연속 · 미지급분만 일방신청권·연장권 · 민호 전용</p>
    </div>
    <a class="back" href="/page/wallet_admin.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 관리</a>
  </div>

<?php if (!$로그인): ?>
  <div class="login">연구실 코드 링크로 접속해주세요.<br><code>?code=XXXX</code></div>
<?php elseif (!$허용): ?>
  <div class="login">이 페이지는 [ 민호 ] 님만 사용할 수 있어요.<br>현재: <?= htmlspecialchars($nick, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>

  <?php if ($flash !== ''): ?>
    <div class="flash <?= $flashOk ? 'ok' : 'bad' ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="stats">
      <div class="stat"><strong><?= htmlspecialchars((string)$미리['from'] . ' ~ ' . (string)$미리['to'], ENT_QUOTES, 'UTF-8') ?></strong><span>조사 구간</span></div>
      <div class="stat"><strong><?= (int)$미리['pay_n'] ?>명</strong><span>미지급 인원</span></div>
      <div class="stat"><strong><?= (int)$미리['pay_qty'] ?>회</strong><span>지급할 횟수</span></div>
    </div>

    <form method="post" action="" onsubmit="return confirm('미지급 <?= (int)$미리['pay_n'] ?>명에게 일방신청권·일방연장권을 각 <?= (int)$미리['pay_qty'] ?>회 지급할까요?\n이미 받은 창은 건너뜁니다.');">
      <input type="hidden" name="action" value="run">
      <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn" <?= ((int)$미리['pay_n'] < 1) ? 'disabled' : '' ?>>미지급분 일괄 지급</button>
    </form>
    <p class="note">
      · 생타는 일반채팅만 (사진 제외 · .생타와 동일)<br>
      · 연속 3일 200타마다 신청권·연장권 각 1개 · 지급 후 연속은 처음부터<br>
      · 이미 <b>생타3일연속</b> 로그가 있는 날은 건너뜀<br>
      · 6일 연속이면 2회(3일+3일)까지 나갑니다
    </p>
  </div>

  <div class="card">
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>닉</th>
            <th class="num">횟수</th>
            <th>미지급 3일 구간 (생타)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($미리['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="num pay"><?= (int)$r['qty'] ?></td>
            <td>
              <?php foreach ($r['windows'] as $w):
                $days = $w['days'] ?? [];
                $cnt = $w['cnt'] ?? [];
                $bits = [];
                foreach ($days as $d) {
                    $bits[] = $d . ' ' . (int)($cnt[$d] ?? 0);
                }
              ?>
                <span class="win"><?= htmlspecialchars(implode(' · ', $bits), ENT_QUOTES, 'UTF-8') ?></span>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($미리['rows'] === []): ?>
          <tr><td colspan="3" class="skip">최근 14일 기준 미지급자가 없어요.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($runResults !== []): ?>
  <div class="card">
    <strong>실행 결과</strong>
    <div class="scroll" style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th>닉</th>
            <th class="num">지급</th>
            <th>달성일</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($runResults as $rr): ?>
          <tr>
            <td><?= htmlspecialchars((string)$rr['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="num <?= !empty($rr['ok']) ? 'pay' : 'skip' ?>"><?= (int)($rr['qty'] ?? 0) ?></td>
            <td class="skip"><?= htmlspecialchars(implode(', ', $rr['ends'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
