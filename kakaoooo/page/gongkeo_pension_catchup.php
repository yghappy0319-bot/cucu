<?php
/**
 * 공커연금 미지급분 일괄 지급 — 민호 전용
 * URL: /page/gongkeo_pension_catchup.php?code=XXXX
 *
 * 각 닉의 마지막 수령일(.공커연금 / 공커연금일괄) 다음날 ~ 오늘
 * 일수 × 오늘 본방냥 시세(×0.001) 를 공커연금 대기(gongkeo_pension)에 누적.
 * `.공커연금`으로 수령. 커플 마지막 누적일을 오늘로 맞춤 → 내일부터 하루씩 쌓임.
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
if (!function_exists('공커연금_단가')) {
    $fn = $root . '/api/function.php';
    if (!is_file($fn) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $fn = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/api/function.php';
    }
    if (is_file($fn)) {
        include_once $fn;
    }
}

const GPC_ADMIN = '민호';
const GPC_로그 = '공커연금일괄';
const GPC_상한일 = 7;

function gpc_닉($nick): string {
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

function gpc_날짜만($v): string {
    $s = substr(trim((string)$v), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
}

/** @return array{today:string,rate:int,already:bool,rows:list<array>,pay_n:int,pay_sum:int,skip_n:int} */
function gpc_미리보기(): array {
    if (function_exists('공커연금_컬럼_보장')) {
        공커연금_컬럼_보장();
    }
    if (function_exists('공커연금_커플컬럼_보장')) {
        공커연금_커플컬럼_보장();
    }
    $today = date('Y-m-d');
    $rate = function_exists('공커연금_단가') ? (int)공커연금_단가() : 0;
    $already = false;
    $chk = @db_select("SELECT idx FROM tb_point_log WHERE status = '" . addslashes(GPC_로그) . "' AND DATE(regdate) = '{$today}' LIMIT 1");
    if (!empty($chk['idx'])) {
        $already = true;
    }

    $couples = [];
    $rs = @db_query("SELECT idx, couple, sdate, pension_last_accrual FROM tb_couple WHERE status = 0 ORDER BY idx ASC");
    if ($rs) {
        while ($row = db_fetch($rs)) {
            $couples[] = $row;
        }
    }

    $nicks = [];
    foreach ($couples as $c) {
        $목록 = function_exists('공커문자열_닉목록') ? 공커문자열_닉목록($c['couple'] ?? '') : [];
        foreach ($목록 as $nn) {
            $nn = gpc_닉($nn);
            if ($nn !== '') {
                $nicks[$nn] = true;
            }
        }
    }
    $nickList = array_keys($nicks);

    $lastPay = [];
    if ($nickList !== []) {
        $in = [];
        foreach ($nickList as $nn) {
            $in[] = "'" . addslashes($nn) . "'";
        }
        $inSql = implode(',', $in);
        $lrs = @db_query("
            SELECT nick, MAX(regdate) AS last_at
            FROM tb_point_log
            WHERE nick IN ({$inSql})
              AND status IN ('공커연금', '" . addslashes(GPC_로그) . "')
            GROUP BY nick
        ");
        if ($lrs) {
            while ($r = db_fetch($lrs)) {
                $nn = gpc_닉($r['nick'] ?? '');
                $d = gpc_날짜만($r['last_at'] ?? '');
                if ($nn !== '' && $d !== '') {
                    $lastPay[$nn] = $d;
                }
            }
        }
    }

    $wait = [];
    if ($nickList !== []) {
        $in = [];
        foreach ($nickList as $nn) {
            $in[] = "'" . addslashes($nn) . "'";
        }
        $wrs = @db_query("SELECT name, IFNULL(gongkeo_pension, 0) AS wait FROM tb_member WHERE name IN (" . implode(',', $in) . ")");
        if ($wrs) {
            while ($r = db_fetch($wrs)) {
                $wait[gpc_닉($r['name'] ?? '')] = (int)($r['wait'] ?? 0);
            }
        }
    }

    $rows = [];
    $payN = 0;
    $paySum = 0;
    $skipN = 0;
    foreach ($couples as $c) {
        $couple = trim((string)($c['couple'] ?? ''));
        $sdate = gpc_날짜만($c['sdate'] ?? '');
        $accrual = gpc_날짜만($c['pension_last_accrual'] ?? '');
        $목록 = function_exists('공커문자열_닉목록') ? 공커문자열_닉목록($couple) : [];
        foreach ($목록 as $nn) {
            $nn = gpc_닉($nn);
            if ($nn === '') {
                continue;
            }
            $last = $lastPay[$nn] ?? '';
            $from = '';
            $days = 0;
            $note = '';
            if ($sdate === '') {
                $note = '공커 시작일 없음';
            } elseif ($last !== '' && $last >= $sdate) {
                // 마지막 수령일 다음날 ~ 오늘
                $days = (int)floor((strtotime($today) - strtotime($last)) / 86400);
                $from = date('Y-m-d', strtotime($last . ' +1 day'));
                if ($days <= 0) {
                    $days = 0;
                    $note = '오늘까지 이미 수령';
                }
            } else {
                // 이번 공커에서 수령 기록 없음 → 시작일~오늘 포함
                $days = (int)floor((strtotime($today) - strtotime($sdate)) / 86400) + 1;
                $from = $sdate;
                $note = $last === '' ? '수령 기록 없음' : '이전 공커 수령 · 이번 시작일부터';
            }
            if ($days < 0) {
                $days = 0;
            }
            $coupleDays = $sdate !== ''
                ? ((int)floor((strtotime($today) - strtotime($sdate)) / 86400) + 1)
                : 0;
            if ($days > 0 && $coupleDays >= GPC_상한일 && $days > GPC_상한일) {
                $days = GPC_상한일;
                $from = date('Y-m-d', strtotime($today . ' -' . (GPC_상한일 - 1) . ' day'));
                $note = trim($note . ($note !== '' ? ' · ' : '') . '7일 상한');
            }
            $pay = $days * $rate;
            if ($pay > 0) {
                $payN++;
                $paySum += $pay;
            } else {
                $skipN++;
            }
            $rows[] = [
                'couple_idx' => (int)($c['idx'] ?? 0),
                'couple' => $couple,
                'nick' => $nn,
                'sdate' => $sdate,
                'accrual' => $accrual,
                'last_pay' => $last,
                'from' => $from,
                'to' => $today,
                'days' => $days,
                'wait' => (int)($wait[$nn] ?? 0),
                'pay' => $pay,
                'note' => $note,
            ];
        }
    }

    return [
        'today' => $today,
        'rate' => $rate,
        'already' => $already,
        'rows' => $rows,
        'pay_n' => $payN,
        'pay_sum' => $paySum,
        'skip_n' => $skipN,
    ];
}

/** @return array{ok:bool,msg:string,results?:list} */
function gpc_실행(string $adminNick): array {
    $미리 = gpc_미리보기();
    if ((int)$미리['rate'] < 1) {
        return ['ok' => false, 'msg' => '오늘 단가가 0이라 지급할 수 없어요. 본방냥 시세를 확인해주세요.'];
    }
    if ((int)$미리['pay_n'] < 1) {
        return ['ok' => false, 'msg' => '지급할 일수가 있는 사람이 없어요. (이미 오늘까지 맞춰진 상태)'];
    }

    global $conn;
    $useTx = ($conn instanceof mysqli);
    if ($useTx) {
        mysqli_begin_transaction($conn);
    }

    $results = [];
    $okN = 0;
    $sum = 0;
    $coupleDone = [];
    $today = $미리['today'];
    $rate = (int)$미리['rate'];

    try {
        foreach ($미리['rows'] as $r) {
            $nn = (string)$r['nick'];
            $pay = (int)$r['pay'];
            $days = (int)$r['days'];
            $esc = addslashes($nn);
            $cidx = (int)$r['couple_idx'];

            if ($pay > 0) {
                $before = @db_select("SELECT idx, IFNULL(gongkeo_pension, 0) AS wait FROM tb_member WHERE name = '{$esc}' LIMIT 1");
                if (empty($before['idx'])) {
                    $results[] = ['nick' => $nn, 'ok' => false, 'msg' => '회원 없음', 'pay' => 0, 'days' => $days];
                    continue;
                }
                db_query("UPDATE tb_member SET gongkeo_pension = IFNULL(gongkeo_pension, 0) + {$pay} WHERE name = '{$esc}' LIMIT 1");
                if (function_exists('지급로그')) {
                    지급로그(GPC_로그, $nn, $days . '일×' . $rate, 0, $pay);
                }
                $okN++;
                $sum += $pay;
                $afterWait = (int)($before['wait'] ?? 0) + $pay;
                $results[] = [
                    'nick' => $nn,
                    'ok' => true,
                    'days' => $days,
                    'pay' => $pay,
                    'msg' => '대기 +' . number_format($pay) . ' → ' . number_format($afterWait),
                ];
            } else {
                $results[] = [
                    'nick' => $nn,
                    'ok' => true,
                    'days' => 0,
                    'pay' => 0,
                    'msg' => 'skip',
                ];
            }

            if ($cidx > 0 && empty($coupleDone[$cidx])) {
                db_query("UPDATE tb_couple SET pension_last_accrual = '{$today}' WHERE idx = {$cidx} LIMIT 1");
                $coupleDone[$cidx] = true;
            }
        }

        if ($useTx) {
            mysqli_commit($conn);
        }
    } catch (Throwable $e) {
        if ($useTx) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '실행 중 오류: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'msg' => "공커연금 누적 완료 · {$okN}명 · +" . number_format($sum) . "냥 대기 · 누적일 오늘 · 내일부터 하루씩 쌓입니다",
        'results' => $results,
    ];
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
$nick = gpc_닉($nick);
$로그인 = ($nick !== '');
$허용 = ($로그인 && $nick === GPC_ADMIN);
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

$flash = '';
$flashOk = false;
$runResults = [];
$미리 = ['today' => date('Y-m-d'), 'rate' => 0, 'already' => false, 'rows' => [], 'pay_n' => 0, 'pay_sum' => 0, 'skip_n' => 0];

if ($허용) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'run') {
        $결과 = gpc_실행($nick);
        $flash = (string)($결과['msg'] ?? '');
        $flashOk = !empty($결과['ok']);
        $runResults = $결과['results'] ?? [];
    }
    $미리 = gpc_미리보기();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>공커연금 일괄 지급</title>
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
.warn { color: var(--warn); }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th, td { padding: 7px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
th { color: var(--muted); font-weight: 500; font-size: 11px; }
td.num { text-align: right; white-space: nowrap; }
.pay { color: var(--ok); font-weight: 700; }
.skip { color: var(--muted); }
.login { text-align: center; padding: 40px 16px; color: var(--muted); line-height: 1.5; }
.scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>공커연금 일괄 지급</h1>
      <p class="sub">마지막 수령일 ~ 오늘 · 공커 7일 이상은 7일치 상한 · 공커연금 대기에 누적 · 민호 전용</p>
    </div>
    <a class="back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 가방</a>
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
      <div class="stat"><strong><?= htmlspecialchars((string)$미리['today'], ENT_QUOTES, 'UTF-8') ?></strong><span>기준일</span></div>
      <div class="stat"><strong><?= number_format((int)$미리['rate']) ?>냥</strong><span>오늘 1일 단가</span></div>
      <div class="stat"><strong><?= (int)$미리['pay_n'] ?>명</strong><span>지급 대상</span></div>
      <div class="stat"><strong><?= number_format((int)$미리['pay_sum']) ?>냥</strong><span>대기 누적 합</span></div>
      <div class="stat"><strong><?= (int)$미리['skip_n'] ?>명</strong><span>이미 맞춤</span></div>
    </div>

    <?php if (!empty($미리['already'])): ?>
      <p class="note warn">오늘 이미 일괄 지급 기록이 있습니다. 다시 실행하면 수령일이 오늘인 사람은 건너뜁니다.</p>
    <?php endif; ?>

    <form method="post" action="" onsubmit="return confirm('활성 공커 <?= (int)$미리['pay_n'] ?>명 공커연금 대기에 총 <?= number_format((int)$미리['pay_sum']) ?>냥을 더할까요?\n본방냥은 바로 안 들어가고, .공커연금으로 수령합니다.\n내일부터 하루씩 쌓입니다.');">
      <input type="hidden" name="action" value="run">
      <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn" <?= ((int)$미리['pay_n'] < 1 || (int)$미리['rate'] < 1) ? 'disabled' : '' ?>>공커연금에 누적 실행</button>
    </form>
    <p class="note">
      · 마지막 <b>.공커연금 수령일</b> 다음날부터 오늘까지 일수 × 오늘 단가<br>
      · 수령 기록이 없으면 이번 공커 시작일부터 오늘까지(시작일 포함)<br>
      · <b>공커 7일 이상</b>은 최대 7일치만 누적 (그보다 짧은 커플은 실제 일수)<br>
      · 금액은 <b>공커연금 대기</b>에 더합니다. 본방냥은 `.공커연금`으로 받을 때 들어갑니다<br>
      · 커플 마지막 누적일을 오늘로 맞춰, 내일 크론부터 하루씩 쌓입니다
    </p>
  </div>

  <div class="card">
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>커플</th>
            <th>닉</th>
            <th>시작</th>
            <th>마지막 수령</th>
            <th>누적일</th>
            <th>구간</th>
            <th class="num">일수</th>
            <th class="num">지금 대기</th>
            <th class="num">더할 금액</th>
            <th>메모</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($미리['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['couple'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$r['nick'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$r['sdate'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['last_pay'] !== '' ? (string)$r['last_pay'] : '없음', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['accrual'] !== '' ? (string)$r['accrual'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?php
              if ((int)$r['days'] > 0) {
                  echo htmlspecialchars((string)$r['from'] . ' ~ ' . (string)$r['to'], ENT_QUOTES, 'UTF-8');
              } else {
                  echo '—';
              }
            ?></td>
            <td class="num"><?= (int)$r['days'] ?></td>
            <td class="num"><?= number_format((int)$r['wait']) ?></td>
            <td class="num <?= (int)$r['pay'] > 0 ? 'pay' : 'skip' ?>"><?= (int)$r['pay'] > 0 ? ('+' . number_format((int)$r['pay'])) : '0' ?></td>
            <td class="skip"><?= htmlspecialchars((string)$r['note'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($미리['rows'] === []): ?>
          <tr><td colspan="10" class="skip">활성 공커가 없어요.</td></tr>
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
            <th class="num">일수</th>
            <th class="num">지급</th>
            <th>결과</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($runResults as $rr): ?>
          <tr>
            <td><?= htmlspecialchars((string)$rr['nick'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="num"><?= (int)($rr['days'] ?? 0) ?></td>
            <td class="num"><?= number_format((int)($rr['pay'] ?? 0)) ?></td>
            <td><?= htmlspecialchars((string)($rr['msg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
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
