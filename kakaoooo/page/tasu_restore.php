<?php
/**
 * 오늘 생타/버프타 복구 — 민호 전용
 * URL: /page/tasu_restore.php?code=XXXX
 *
 * 생타 = 비어있지 않은 msg 수 + 사진(+2)
 * 버프타 = SUM(tasu)
 */
require __DIR__ . '/_wallet_preauth.php';
if (!function_exists('db_select')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
}
if (!function_exists('getTwoCharNick')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
if (!function_exists('생타_SQL_select_expr') && is_file(__DIR__ . '/../api/function.php')) {
    include_once __DIR__ . '/../api/function.php';
}
include_once __DIR__ . '/_today_tasu_lib.php';

const TASU_RESTORE_ADMIN = '민호';
const TASU_RESTORE_MSG = '[생타복구]';
const TASU_RESTORE_PHOTO = '사진을 보냈습니다.';

/** 복구 목표 (등수, 닉, 생타, 버프타) — 요청 스냅샷 */
function tr_기본목표(): array {
    return [
        ['rank' => 1,  'nick' => '우서', 'raw' => 1984, 'buff' => 3929],
        ['rank' => 2,  'nick' => '대성', 'raw' => 1173, 'buff' => 1884],
        ['rank' => 3,  'nick' => '아영', 'raw' => 1089, 'buff' => 2101],
        ['rank' => 4,  'nick' => '치즈', 'raw' => 920,  'buff' => 1822],
        ['rank' => 5,  'nick' => '미니', 'raw' => 857,  'buff' => 1709],
        ['rank' => 6,  'nick' => '앙앙', 'raw' => 790,  'buff' => 965],
        ['rank' => 7,  'nick' => '민호', 'raw' => 771,  'buff' => 817],
        ['rank' => 8,  'nick' => '제니', 'raw' => 639,  'buff' => 1269],
        ['rank' => 9,  'nick' => '하리', 'raw' => 428,  'buff' => 373],
        ['rank' => 10, 'nick' => '다운', 'raw' => 394,  'buff' => 616],
        ['rank' => 11, 'nick' => '동구', 'raw' => 393,  'buff' => 765],
        ['rank' => 12, 'nick' => '덕선', 'raw' => 382,  'buff' => 743],
        ['rank' => 13, 'nick' => '쥬쥬', 'raw' => 382,  'buff' => 380],
        ['rank' => 14, 'nick' => '하늘', 'raw' => 370,  'buff' => 735],
        ['rank' => 15, 'nick' => '오리', 'raw' => 309,  'buff' => 618],
        ['rank' => 16, 'nick' => '여름', 'raw' => 281,  'buff' => 289],
        ['rank' => 17, 'nick' => '주리', 'raw' => 272,  'buff' => 542],
        ['rank' => 18, 'nick' => '권혁', 'raw' => 271,  'buff' => 270],
        ['rank' => 19, 'nick' => '이지', 'raw' => 247,  'buff' => 247],
        ['rank' => 20, 'nick' => '바라', 'raw' => 223,  'buff' => 223],
        ['rank' => 21, 'nick' => '피치', 'raw' => 220,  'buff' => 460],
        ['rank' => 22, 'nick' => '복자', 'raw' => 217,  'buff' => 203],
        ['rank' => 23, 'nick' => '가이', 'raw' => 177,  'buff' => 177],
        ['rank' => 24, 'nick' => '라인', 'raw' => 173,  'buff' => 173],
        ['rank' => 25, 'nick' => '희수', 'raw' => 128,  'buff' => 128],
        ['rank' => 26, 'nick' => '다오', 'raw' => 114,  'buff' => 268],
        ['rank' => 27, 'nick' => '효진', 'raw' => 113,  'buff' => 117],
        ['rank' => 28, 'nick' => '석훈', 'raw' => 95,   'buff' => 94],
        ['rank' => 29, 'nick' => '가니', 'raw' => 52,   'buff' => 102],
        ['rank' => 30, 'nick' => '해이', 'raw' => 50,   'buff' => 80],
        ['rank' => 31, 'nick' => '로이', 'raw' => 49,   'buff' => 79],
        ['rank' => 32, 'nick' => '루루', 'raw' => 43,   'buff' => 43],
        ['rank' => 33, 'nick' => '크롱', 'raw' => 38,   'buff' => 38],
        ['rank' => 34, 'nick' => '모미', 'raw' => 32,   'buff' => 32],
        ['rank' => 35, 'nick' => '윤현', 'raw' => 26,   'buff' => 26],
        ['rank' => 36, 'nick' => '다다', 'raw' => 23,   'buff' => 23],
        ['rank' => 37, 'nick' => '다인', 'raw' => 23,   'buff' => 23],
        ['rank' => 38, 'nick' => '채린', 'raw' => 21,   'buff' => 42],
        ['rank' => 39, 'nick' => '나나', 'raw' => 11,   'buff' => 22],
        ['rank' => 40, 'nick' => 'ㅇㅈ', 'raw' => 1,    'buff' => 1],
        ['rank' => 41, 'nick' => '다해', 'raw' => 1,    'buff' => 1],
    ];
}

function tr_닉정규화($nick): string {
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

/** @return array{raw:int,buff:int} */
function tr_현재타수($nick, $날짜): array {
    $nick_esc = addslashes($nick);
    $날짜 = preg_replace('/[^0-9\-]/', '', (string)$날짜);
    $raw_expr = function_exists('생타_SQL_select_expr')
        ? 생타_SQL_select_expr('msg')
        : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,''))='' OR TRIM(msg)='사진을 보냈습니다.' THEN 0 ELSE 1 END), 0)";
    $row = db_select("
        SELECT {$raw_expr} AS raw_cnt,
               " . (function_exists('버프타_SQL_select_expr') ? 버프타_SQL_select_expr('msg', 'tasu') : "COALESCE(SUM(CASE WHEN TRIM(IFNULL(msg,'')) LIKE '%사진을 보냈습니다%' THEN 0 ELSE IFNULL(tasu, 0) END), 0)") . " AS buff_cnt
        FROM tb_msg
        WHERE nickname = '{$nick_esc}'
          AND tasu != 0
          AND regdate >= '{$날짜}'
          AND regdate < '{$날짜}' + INTERVAL 1 DAY
    ");
    return [
        'raw' => (int)($row['raw_cnt'] ?? 0),
        'buff' => (int)($row['buff_cnt'] ?? 0),
    ];
}

/**
 * 생타 R · 버프타 B 를 만드는 INSERT 행 목록
 * @return list<array{msg:string,tasu:int}>
 */
function tr_복구행목록(int $raw, int $buff): array {
    $raw = max(0, $raw);
    $buff = (int)$buff;
    $rows = [];
    if ($raw <= 0) {
        if ($buff !== 0) {
            $rows[] = ['msg' => '', 'tasu' => $buff];
        }
        return $rows;
    }
    // 사진 1건 = 생타 +3 → 로우 수 절약
    $photos = intdiv($raw, 3);
    $chats = $raw % 3;
    for ($i = 0; $i < $photos; $i++) {
        $rows[] = ['msg' => TASU_RESTORE_PHOTO, 'tasu' => 1];
    }
    for ($i = 0; $i < $chats; $i++) {
        $rows[] = ['msg' => TASU_RESTORE_MSG, 'tasu' => 1];
    }
    $baseBuff = count($rows);
    $diff = $buff - $baseBuff;
    if ($diff !== 0) {
        $rows[] = ['msg' => '', 'tasu' => $diff];
    }
    return $rows;
}

/**
 * @param list<array{nick:string,raw:int,buff:int}> $targets
 * @return array{ok:bool,msg:string,results?:list}
 */
function tr_복구실행(array $targets, string $날짜, bool $clearOthers = false): array {
    $날짜 = preg_replace('/[^0-9\-]/', '', $날짜);
    if (strlen($날짜) !== 10) {
        return ['ok' => false, 'msg' => '날짜 형식이 올바르지 않아요.'];
    }
    if ($targets === []) {
        return ['ok' => false, 'msg' => '복구할 대상이 없어요.'];
    }

    $reg = $날짜 . ' 12:00:00';
    $results = [];
    $nicks = [];

    global $conn;
    $useTx = ($conn instanceof mysqli);
    if ($useTx) {
        mysqli_begin_transaction($conn);
    }

    try {
        foreach ($targets as $t) {
            $nick = tr_닉정규화($t['nick'] ?? '');
            $raw = max(0, (int)($t['raw'] ?? 0));
            $buff = (int)($t['buff'] ?? 0);
            if ($nick === '') {
                continue;
            }
            $nicks[$nick] = true;
            $nick_esc = addslashes($nick);

            db_query("DELETE FROM tb_msg
                WHERE nickname = '{$nick_esc}'
                  AND regdate >= '{$날짜}'
                  AND regdate < '{$날짜}' + INTERVAL 1 DAY");

            $rows = tr_복구행목록($raw, $buff);
            $batch = [];
            $flush = static function () use (&$batch) {
                if ($batch === []) {
                    return;
                }
                $sql = 'INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ' . implode(',', $batch);
                db_query($sql);
                $batch = [];
            };

            foreach ($rows as $r) {
                $msg_esc = addslashes($r['msg']);
                $tasu = (int)$r['tasu'];
                $batch[] = "('{$nick_esc}', '{$msg_esc}', {$tasu}, '{$reg}')";
                if (count($batch) >= 200) {
                    $flush();
                }
            }
            $flush();

            $now = tr_현재타수($nick, $날짜);
            $results[] = [
                'nick' => $nick,
                'target_raw' => $raw,
                'target_buff' => $buff,
                'now_raw' => $now['raw'],
                'now_buff' => $now['buff'],
                'ok' => ($now['raw'] === $raw && $now['buff'] === $buff),
                'rows' => count($rows),
            ];
        }

        if ($clearOthers && $nicks !== []) {
            $in = [];
            foreach (array_keys($nicks) as $n) {
                $in[] = "'" . addslashes($n) . "'";
            }
            $inSql = implode(',', $in);
            db_query("DELETE FROM tb_msg
                WHERE regdate >= '{$날짜}'
                  AND regdate < '{$날짜}' + INTERVAL 1 DAY
                  AND nickname NOT IN ({$inSql})
                  AND nickname NOT IN ('오픈', '')");
        }

        if ($useTx) {
            mysqli_commit($conn);
        }
    } catch (Throwable $e) {
        if ($useTx) {
            mysqli_rollback($conn);
        }
        return ['ok' => false, 'msg' => '복구 중 오류: ' . $e->getMessage()];
    }

    $fail = 0;
    foreach ($results as $r) {
        if (empty($r['ok'])) {
            $fail++;
        }
    }
    $msg = '복구 완료 · ' . count($results) . '명';
    if ($fail > 0) {
        $msg .= " · 검증 불일치 {$fail}명 (아래 표 확인)";
    }
    if ($clearOthers) {
        $msg .= ' · 목록 외 당일 타수 삭제함';
    }
    return ['ok' => true, 'msg' => $msg, 'results' => $results];
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
$허용 = ($로그인 && $nick === TASU_RESTORE_ADMIN);
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';

$날짜 = tt_오늘날짜();
if (isset($_REQUEST['date'])) {
    $d = preg_replace('/[^0-9\-]/', '', (string)$_REQUEST['date']);
    if (strlen($d) === 10) {
        $날짜 = $d;
    }
}

$flash = '';
$flashOk = false;
$restoreResults = [];

if ($허용 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    $targets = [];
    $nicksIn = $_POST['nick'] ?? [];
    $rawsIn = $_POST['raw'] ?? [];
    $buffsIn = $_POST['buff'] ?? [];
    if (is_array($nicksIn)) {
        foreach ($nicksIn as $i => $n) {
            $n = tr_닉정규화($n);
            if ($n === '') {
                continue;
            }
            $targets[] = [
                'nick' => $n,
                'raw' => (int)($rawsIn[$i] ?? 0),
                'buff' => (int)($buffsIn[$i] ?? 0),
            ];
        }
    }
    $clearOthers = !empty($_POST['clear_others']);
    $결과 = tr_복구실행($targets, $날짜, $clearOthers);
    $flash = $결과['msg'] ?? '';
    $flashOk = !empty($결과['ok']);
    $restoreResults = $결과['results'] ?? [];
}

$목표 = tr_기본목표();
$현재맵 = [];
foreach (tt_생타목록($날짜) as $row) {
    $현재맵[$row['nick']] = $row;
}
$rows = [];
foreach ($목표 as $t) {
    $n = tr_닉정규화($t['nick']);
    $cur = $현재맵[$n] ?? ['raw' => 0, 'buff' => 0];
    $rows[] = [
        'rank' => (int)$t['rank'],
        'nick' => $n,
        'raw' => (int)$t['raw'],
        'buff' => (int)$t['buff'],
        'cur_raw' => (int)$cur['raw'],
        'cur_buff' => (int)$cur['buff'],
        'match' => ((int)$cur['raw'] === (int)$t['raw'] && (int)$cur['buff'] === (int)$t['buff']),
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>생타/버프타 복구</title>
<style>
:root {
  --ink: #e8eef5;
  --muted: #8a9bb0;
  --accent: #7eb8ff;
  --ok: #7dba7a;
  --warn: #e0a060;
  --bad: #e08080;
  --line: rgba(180, 200, 230, 0.18);
  --bg: #121820;
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #10151c 0%, #1a2230 50%, #121820 100%);
  color: var(--ink);
  font-family: 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
  font-size: 15px;
}
.wrap { max-width: 720px; margin: 0 auto; padding: 16px 14px 80px; }
.top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
h1 { margin: 0; font-size: 24px; color: var(--accent); letter-spacing: -0.03em; }
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
label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
input[type="date"], input[type="number"], input[type="text"] {
  width: 100%; padding: 8px 10px; border-radius: 8px;
  border: 1px solid var(--line); background: rgba(0,0,0,0.35); color: var(--ink);
  font-size: 14px;
}
.row-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 12px; }
.row-tools > div { flex: 1; min-width: 140px; }
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: 44px; padding: 0 16px; border: 0; border-radius: 10px;
  font-size: 15px; font-weight: 600; cursor: pointer;
  color: #0c1218; background: linear-gradient(180deg, #a8d0ff, #6aa8f0);
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-warn { background: linear-gradient(180deg, #f0c090, #d09050); }
.check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin: 10px 0; }
.check input { width: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { padding: 8px 6px; border-bottom: 1px solid var(--line); text-align: right; }
th:nth-child(2), td:nth-child(2) { text-align: left; }
th { color: var(--muted); font-weight: 500; font-size: 11px; }
td input { padding: 6px 8px; font-size: 13px; }
.match { color: var(--ok); }
.diff { color: var(--warn); }
.login { text-align: center; padding: 40px 16px; color: var(--muted); }
.note { font-size: 12px; color: var(--muted); line-height: 1.5; margin-top: 10px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1>생타/버프타 복구</h1>
      <p class="sub">민호 전용 · 해당일 tb_msg를 목표 생타/버프타에 맞게 재작성</p>
    </div>
    <a class="back" href="/page/today_tasu.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">오늘타수</a>
  </div>

<?php if (!$로그인): ?>
  <div class="login">연구실 코드 링크로 접속해주세요.</div>
<?php elseif (!$허용): ?>
  <div class="login">이 페이지는 [ 민호 ] 님만 사용할 수 있어요.<br>현재: <?= htmlspecialchars($nick, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>

  <?php if ($flash !== ''): ?>
    <div class="flash <?= $flashOk ? 'ok' : 'bad' ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="" id="trForm" class="card"
    onsubmit="return confirm('선택한 날짜의 타수를 목표값으로 덮어쓸까요?\n대상 닉의 당일 tb_msg가 삭제 후 재삽입됩니다.');">
    <input type="hidden" name="action" value="restore">
    <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">

    <div class="row-tools">
      <div>
        <label for="date">복구 날짜</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($날짜, ENT_QUOTES, 'UTF-8') ?>"
          onchange="location.href='?code=<?= rawurlencode($code) ?>&date='+this.value">
      </div>
      <div style="flex:0 0 auto;">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-warn">목표값으로 복구</button>
      </div>
    </div>

    <label class="check">
      <input type="checkbox" name="clear_others" value="1">
      목록에 없는 닉의 당일 타수도 전부 삭제
    </label>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>닉</th>
            <th>목표 생타</th>
            <th>목표 버프</th>
            <th>현재 생타</th>
            <th>현재 버프</th>
            <th>상태</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr class="<?= $r['match'] ? 'match' : 'diff' ?>">
            <td><?= (int)$r['rank'] ?></td>
            <td>
              <input type="text" name="nick[]" value="<?= htmlspecialchars($r['nick'], ENT_QUOTES, 'UTF-8') ?>" required>
            </td>
            <td><input type="number" name="raw[]" value="<?= (int)$r['raw'] ?>" min="0" step="1" required></td>
            <td><input type="number" name="buff[]" value="<?= (int)$r['buff'] ?>" step="1" required></td>
            <td><?= number_format($r['cur_raw']) ?></td>
            <td><?= number_format($r['cur_buff']) ?></td>
            <td><?= $r['match'] ? 'OK' : '차이' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="note">
      · 복구 방식: 당일 해당 닉 tb_msg 삭제 → 사진/채팅 합성 행으로 생타 맞춤 → 빈 msg로 버프타 보정<br>
      · 사진 메시지로 생타 3배 압축 삽입 (대량 보유 시 부하↓)<br>
      · 복구 후 .생타 / 오늘타수 페이지에서 확인하세요
    </p>
  </form>

  <?php if ($restoreResults !== []): ?>
  <div class="card">
    <strong>검증 결과</strong>
    <table style="margin-top:10px;">
      <thead>
        <tr><th>닉</th><th>목표</th><th>결과</th><th>행수</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($restoreResults as $r): ?>
        <tr class="<?= !empty($r['ok']) ? 'match' : 'diff' ?>">
          <td style="text-align:left;"><?= htmlspecialchars($r['nick'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)$r['target_raw'] ?> / <?= (int)$r['target_buff'] ?></td>
          <td><?= (int)$r['now_raw'] ?> / <?= (int)$r['now_buff'] ?></td>
          <td><?= (int)$r['rows'] ?></td>
          <td><?= !empty($r['ok']) ? 'OK' : '불일치' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
