<?php
/**
 * .랭킹1 스냅샷 기준 본방냥(newpoint) 복구 — 민호 전용
 * URL: /page/newpoint_ranking_restore.php?code=XXXX
 *
 * 미리보기 → 실행: 목록 닉의 newpoint 를 목표값으로 SET
 */
require __DIR__ . '/_wallet_preauth.php';
if (!function_exists('db_select')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
}
if (!function_exists('getTwoCharNick')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}

const NPR_ADMIN = '민호';

/** @return list<array{rank:int,nick:string,newpoint:int}> */
function npr_스냅샷(): array {
    return [
        ['rank' => 1,  'nick' => '덕선', 'newpoint' => 11277],
        ['rank' => 2,  'nick' => '오리', 'newpoint' => 7811],
        ['rank' => 3,  'nick' => '하늘', 'newpoint' => 7316],
        ['rank' => 4,  'nick' => '다오', 'newpoint' => 5970],
        ['rank' => 5,  'nick' => '우서', 'newpoint' => 5851],
        ['rank' => 6,  'nick' => '대성', 'newpoint' => 4568],
        ['rank' => 7,  'nick' => '미니', 'newpoint' => 3426],
        ['rank' => 8,  'nick' => '주리', 'newpoint' => 1890],
        ['rank' => 9,  'nick' => '라라', 'newpoint' => 1861],
        ['rank' => 10, 'nick' => '후니', 'newpoint' => 1765],
        ['rank' => 11, 'nick' => '피치', 'newpoint' => 1533],
        ['rank' => 12, 'nick' => '율아', 'newpoint' => 1479],
        ['rank' => 13, 'nick' => '아영', 'newpoint' => 1393],
        ['rank' => 14, 'nick' => '동구', 'newpoint' => 1288],
        ['rank' => 15, 'nick' => '나나', 'newpoint' => 1240],
        ['rank' => 16, 'nick' => '도하', 'newpoint' => 1055],
        ['rank' => 17, 'nick' => '석훈', 'newpoint' => 1046],
        ['rank' => 18, 'nick' => '세은', 'newpoint' => 1034],
        ['rank' => 19, 'nick' => '앙앙', 'newpoint' => 890],
        ['rank' => 20, 'nick' => '이지', 'newpoint' => 719],
        ['rank' => 21, 'nick' => '여름', 'newpoint' => 645],
        ['rank' => 22, 'nick' => '로아', 'newpoint' => 457],
        ['rank' => 23, 'nick' => '제니', 'newpoint' => 457],
        ['rank' => 24, 'nick' => '쥬쥬', 'newpoint' => 435],
        ['rank' => 25, 'nick' => '구구', 'newpoint' => 335],
        ['rank' => 26, 'nick' => '치즈', 'newpoint' => 291],
        ['rank' => 27, 'nick' => '민호', 'newpoint' => 255],
        ['rank' => 28, 'nick' => '하리', 'newpoint' => 245],
        ['rank' => 29, 'nick' => '바라', 'newpoint' => 209],
        ['rank' => 30, 'nick' => '다운', 'newpoint' => 203],
        ['rank' => 31, 'nick' => '다인', 'newpoint' => 200],
        ['rank' => 32, 'nick' => '복자', 'newpoint' => 187],
        ['rank' => 33, 'nick' => '채채', 'newpoint' => 186],
        ['rank' => 34, 'nick' => '최산', 'newpoint' => 160],
        ['rank' => 35, 'nick' => '희수', 'newpoint' => 136],
        ['rank' => 36, 'nick' => '율무', 'newpoint' => 129],
        ['rank' => 37, 'nick' => '채린', 'newpoint' => 120],
        ['rank' => 38, 'nick' => '루루', 'newpoint' => 65],
        ['rank' => 39, 'nick' => '미소', 'newpoint' => 63],
        ['rank' => 40, 'nick' => '크롱', 'newpoint' => 57],
        ['rank' => 41, 'nick' => '디보', 'newpoint' => 49],
        ['rank' => 42, 'nick' => '태리', 'newpoint' => 33],
        ['rank' => 43, 'nick' => '화피', 'newpoint' => 25],
        ['rank' => 44, 'nick' => '모미', 'newpoint' => 24],
        ['rank' => 45, 'nick' => '가이', 'newpoint' => 23],
        ['rank' => 46, 'nick' => '팩트', 'newpoint' => 20],
        ['rank' => 47, 'nick' => '새아', 'newpoint' => 18],
        ['rank' => 48, 'nick' => '민이', 'newpoint' => 12],
        ['rank' => 49, 'nick' => '윤현', 'newpoint' => 5],
        ['rank' => 50, 'nick' => '기철', 'newpoint' => 3],
        ['rank' => 51, 'nick' => '가니', 'newpoint' => 1],
        ['rank' => 52, 'nick' => '로이', 'newpoint' => 0],
        ['rank' => 53, 'nick' => '산지', 'newpoint' => 0],
    ];
}

function npr_닉($nick): string {
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

function npr_표시($n): string {
    if (function_exists('newpoint표시')) {
        return newpoint표시($n);
    }
    return number_format((int)$n);
}

/** @return array{ok:bool,rows:list<array>,stats:array,msg:string} */
function npr_미리보기(?array $targets = null): array {
    $snap = $targets ?? npr_스냅샷();
    $rows = [];
    $found = 0;
    $missing = 0;
    $match = 0;
    $diff = 0;
    $up = 0;
    $down = 0;
    $deltaSum = 0;

    foreach ($snap as $t) {
        $nick = npr_닉($t['nick'] ?? '');
        $target = max(0, (int)($t['newpoint'] ?? 0));
        if ($nick === '') {
            continue;
        }
        $esc = addslashes($nick);
        $m = db_select("
            SELECT name, CAST(FLOOR(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))) AS CHAR) AS np
            FROM tb_member
            WHERE name = '{$esc}'
            LIMIT 1
        ");
        if (!$m) {
            $missing++;
            $rows[] = [
                'rank' => (int)($t['rank'] ?? 0),
                'nick' => $nick,
                'target' => $target,
                'current' => null,
                'delta' => null,
                'status' => 'missing',
            ];
            continue;
        }
        $found++;
        $cur = (int)(function_exists('냥_정수문자열')
            ? 냥_정수문자열($m['np'] ?? 0)
            : preg_replace('/[^\d]/', '', (string)($m['np'] ?? '0')));
        $d = $target - $cur;
        $deltaSum += $d;
        if ($d === 0) {
            $match++;
            $st = 'match';
        } else {
            $diff++;
            if ($d > 0) {
                $up++;
            } else {
                $down++;
            }
            $st = 'diff';
        }
        $rows[] = [
            'rank' => (int)($t['rank'] ?? 0),
            'nick' => $nick,
            'target' => $target,
            'current' => $cur,
            'delta' => $d,
            'status' => $st,
        ];
    }

    return [
        'ok' => true,
        'rows' => $rows,
        'stats' => [
            'total' => count($rows),
            'found' => $found,
            'missing' => $missing,
            'match' => $match,
            'diff' => $diff,
            'up' => $up,
            'down' => $down,
            'delta_sum' => $deltaSum,
        ],
        'msg' => "목록 {$found}명 · 일치 {$match} · 차이 {$diff} · 없음 {$missing}",
    ];
}

/**
 * @param list<array{nick:string,newpoint:int}> $targets
 * @return array{ok:bool,msg:string,results:list<array>}
 */
function npr_실행(array $targets, string $adminNick): array {
    $results = [];
    $okN = 0;
    $failN = 0;
    $skipN = 0;

    foreach ($targets as $t) {
        $nick = npr_닉($t['nick'] ?? '');
        $target = max(0, (int)($t['newpoint'] ?? 0));
        if ($nick === '') {
            continue;
        }
        $esc = addslashes($nick);
        $beforeRow = db_select("
            SELECT CAST(FLOOR(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))) AS CHAR) AS np
            FROM tb_member WHERE name = '{$esc}' LIMIT 1
        ");
        if (!$beforeRow) {
            $failN++;
            $results[] = ['nick' => $nick, 'ok' => false, 'msg' => '회원 없음'];
            continue;
        }
        $before = (int)(function_exists('냥_정수문자열')
            ? 냥_정수문자열($beforeRow['np'] ?? 0)
            : preg_replace('/[^\d]/', '', (string)($beforeRow['np'] ?? '0')));

        if ($before === $target) {
            $skipN++;
            $results[] = [
                'nick' => $nick,
                'ok' => true,
                'skipped' => true,
                'before' => $before,
                'after' => $before,
                'target' => $target,
                'msg' => '이미 동일',
            ];
            continue;
        }

        $targetSql = (string)$target;
        db_query("
            UPDATE tb_member
            SET newpoint = {$targetSql}
            WHERE name = '{$esc}'
            LIMIT 1
        ");
        $afterRow = db_select("
            SELECT CAST(FLOOR(CAST(IFNULL(newpoint, 0) AS DECIMAL(65,4))) AS CHAR) AS np
            FROM tb_member WHERE name = '{$esc}' LIMIT 1
        ");
        $after = (int)(function_exists('냥_정수문자열')
            ? 냥_정수문자열($afterRow['np'] ?? 0)
            : preg_replace('/[^\d]/', '', (string)($afterRow['np'] ?? '0')));

        if ($after !== $target) {
            $failN++;
            $results[] = [
                'nick' => $nick,
                'ok' => false,
                'before' => $before,
                'after' => $after,
                'target' => $target,
                'msg' => '반영 실패',
            ];
            continue;
        }

        $delta = $after - $before;
        if (function_exists('지급로그')) {
            지급로그('본방냥랭킹복구', $nick, $adminNick, 0, $delta);
        }
        $okN++;
        $results[] = [
            'nick' => $nick,
            'ok' => true,
            'skipped' => false,
            'before' => $before,
            'after' => $after,
            'target' => $target,
            'delta' => $delta,
            'msg' => ($delta >= 0 ? '+' : '') . npr_표시($delta),
        ];
    }

    $msg = "복구 완료 · 변경 {$okN}명";
    if ($skipN > 0) {
        $msg .= " · 동일 skip {$skipN}";
    }
    if ($failN > 0) {
        $msg .= " · 실패 {$failN}";
    }
    return ['ok' => $failN === 0, 'msg' => $msg, 'results' => $results];
}

// ——— auth ———
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
    $행 = db_select("SELECT name FROM tb_member WHERE code = '" . addslashes($code) . "' LIMIT 1");
    $nick = trim((string)($행['name'] ?? ''));
}
if ($nick !== '' && function_exists('getTwoCharNick')) {
    $nick = getTwoCharNick($nick) ?: $nick;
}
$로그인 = ($nick !== '');
$허용 = ($로그인 && $nick === NPR_ADMIN);
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

$flash = '';
$flashOk = false;
$runResults = [];
$미리 = null;

if ($허용) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $targets = [];
        $nicksIn = $_POST['nick'] ?? [];
        $npIn = $_POST['newpoint'] ?? [];
        if (is_array($nicksIn)) {
            foreach ($nicksIn as $i => $n) {
                $n = npr_닉($n);
                if ($n === '') {
                    continue;
                }
                $targets[] = [
                    'rank' => $i + 1,
                    'nick' => $n,
                    'newpoint' => max(0, (int)($npIn[$i] ?? 0)),
                ];
            }
        }
        if ($targets === []) {
            $targets = npr_스냅샷();
        }

        if ($action === 'preview') {
            $미리 = npr_미리보기($targets);
            $flash = (string)($미리['msg'] ?? '');
            $flashOk = true;
        } elseif ($action === 'run') {
            $실행 = npr_실행($targets, $nick);
            $flash = (string)($실행['msg'] ?? '');
            $flashOk = !empty($실행['ok']);
            $runResults = $실행['results'] ?? [];
            $미리 = npr_미리보기($targets);
        } else {
            $미리 = npr_미리보기($targets);
        }
    } else {
        $미리 = npr_미리보기();
    }
}

$st = $미리['stats'] ?? [];
$rows = $미리['rows'] ?? [];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>본방냥 랭킹 복구</title>
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
.wrap { max-width: 820px; margin: 0 auto; padding: 16px 14px 80px; }
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
.flash {
  padding: 12px 14px; border-radius: 12px; margin-bottom: 14px;
  border: 1px solid var(--line);
}
.flash.ok { background: rgba(125,186,122,0.15); color: var(--ok); }
.flash.bad { background: rgba(224,128,128,0.15); color: var(--bad); }
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 8px;
  margin-bottom: 12px;
}
.stat {
  background: rgba(0,0,0,0.35);
  border-radius: 10px;
  padding: 10px 12px;
  border: 1px solid var(--line);
}
.stat strong { display: block; font-size: 1.05rem; margin-bottom: 2px; }
.stat span { color: var(--muted); font-size: 0.75rem; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: 44px; padding: 0 16px; border: 0; border-radius: 10px;
  font-size: 15px; font-weight: 700; cursor: pointer;
}
.btn-preview { color: #0c1218; background: linear-gradient(180deg, #a8d0ff, #6aa8f0); }
.btn-run { color: #fff; background: linear-gradient(180deg, #e08080, #c05050); }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.warn-note {
  margin: 10px 0 0; font-size: 12px; color: var(--warn); line-height: 1.4;
}
table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); vertical-align: middle; }
th { color: var(--muted); font-weight: 600; font-size: 0.75rem; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
input[type="text"], input[type="number"] {
  width: 100%; max-width: 110px; padding: 6px 8px; border-radius: 8px;
  border: 1px solid var(--line); background: rgba(0,0,0,0.35); color: var(--ink);
  font-size: 13px; text-align: right;
}
input.nick { max-width: 72px; text-align: left; }
.badge {
  display: inline-block; font-size: 0.72rem; padding: 2px 8px; border-radius: 999px;
  background: #243041; color: var(--muted);
}
.badge.ok { background: rgba(125,186,122,0.18); color: var(--ok); }
.badge.diff { background: rgba(224,160,96,0.18); color: var(--warn); }
.badge.miss { background: rgba(224,128,128,0.18); color: var(--bad); }
.delta-up { color: var(--ok); }
.delta-down { color: var(--bad); }
.deny {
  padding: 28px 16px; text-align: center; color: var(--muted); line-height: 1.5;
}
.scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>본방냥 랭킹 복구</h1>
      <p class="sub">.랭킹1 스냅샷 값으로 <b>newpoint</b>를 맞춥니다. (민호 전용)</p>
    </div>
    <a class="back" href="/page/wallet.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 지갑</a>
  </div>

<?php if (!$로그인) { ?>
  <div class="card deny">접속 코드가 필요합니다.<br><code>?code=XXXX</code></div>
<?php } elseif (!$허용) { ?>
  <div class="card deny">민호만 사용할 수 있어요.<br>(현재: <?= htmlspecialchars($nick, ENT_QUOTES, 'UTF-8') ?>)</div>
<?php } else { ?>

  <?php if ($flash !== '') { ?>
  <div class="flash <?= $flashOk ? 'ok' : 'bad' ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php } ?>

  <div class="card">
    <div class="stats">
      <div class="stat"><strong><?= (int)($st['total'] ?? 0) ?></strong><span>목록</span></div>
      <div class="stat"><strong><?= (int)($st['match'] ?? 0) ?></strong><span>이미 일치</span></div>
      <div class="stat"><strong><?= (int)($st['diff'] ?? 0) ?></strong><span>차이</span></div>
      <div class="stat"><strong><?= (int)($st['up'] ?? 0) ?> / <?= (int)($st['down'] ?? 0) ?></strong><span>증가 / 감소</span></div>
      <div class="stat"><strong><?= htmlspecialchars(npr_표시($st['delta_sum'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong><span>목표−현재 합</span></div>
      <div class="stat"><strong><?= (int)($st['missing'] ?? 0) ?></strong><span>닉 없음</span></div>
    </div>

    <form method="post" action="<?= htmlspecialchars($q !== '' ? $q : '?') ?>" id="restoreForm">
      <div class="actions">
        <button type="submit" class="btn btn-preview" name="action" value="preview">미리보기 갱신</button>
        <button type="submit" class="btn btn-run" name="action" value="run"
          onclick="return confirm('목록 전원의 본방냥을 목표값으로 덮어씁니다.\\n되돌리기 어렵습니다. 실행할까요?');">복구 실행</button>
      </div>
      <p class="warn-note">실행 시 각 닉의 본방냥을 목표값으로 <b>SET</b>합니다. 채굴 적립(mining_pending)·게임냥은 건드리지 않습니다.</p>

      <div class="scroll" style="margin-top:14px;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>닉</th>
              <th class="num">현재</th>
              <th class="num">목표</th>
              <th class="num">차이</th>
              <th>상태</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $r) {
              $stt = (string)($r['status'] ?? '');
              $badge = $stt === 'match' ? 'ok' : ($stt === 'missing' ? 'miss' : 'diff');
              $badgeTxt = $stt === 'match' ? '일치' : ($stt === 'missing' ? '없음' : '차이');
              $delta = $r['delta'];
              $deltaCls = '';
              $deltaTxt = '—';
              if ($delta !== null) {
                  $deltaCls = $delta > 0 ? 'delta-up' : ($delta < 0 ? 'delta-down' : '');
                  $deltaTxt = ($delta > 0 ? '+' : '') . npr_표시($delta);
              }
          ?>
            <tr>
              <td><?= (int)$r['rank'] ?></td>
              <td>
                <input class="nick" type="text" name="nick[]" value="<?= htmlspecialchars($r['nick'], ENT_QUOTES, 'UTF-8') ?>">
              </td>
              <td class="num"><?= $r['current'] === null ? '—' : htmlspecialchars(npr_표시($r['current']), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="num">
                <input type="number" name="newpoint[]" min="0" step="1" value="<?= (int)$r['target'] ?>">
              </td>
              <td class="num <?= $deltaCls ?>"><?= htmlspecialchars($deltaTxt, ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="badge <?= $badge ?>"><?= $badgeTxt ?></span></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>

  <?php if ($runResults) { ?>
  <div class="card">
    <h2 style="margin:0 0 10px;font-size:1rem;color:var(--muted);font-weight:600;">실행 결과</h2>
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>닉</th>
            <th class="num">이전</th>
            <th class="num">이후</th>
            <th class="num">변동</th>
            <th>메모</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($runResults as $rr) { ?>
          <tr>
            <td><?= htmlspecialchars((string)$rr['nick'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="num"><?= isset($rr['before']) ? htmlspecialchars(npr_표시($rr['before']), ENT_QUOTES, 'UTF-8') : '—' ?></td>
            <td class="num"><?= isset($rr['after']) ? htmlspecialchars(npr_표시($rr['after']), ENT_QUOTES, 'UTF-8') : '—' ?></td>
            <td class="num"><?= htmlspecialchars((string)($rr['msg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= !empty($rr['ok']) ? (!empty($rr['skipped']) ? 'skip' : 'ok') : 'fail' ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php } ?>

<?php } ?>
</div>
</body>
</html>
