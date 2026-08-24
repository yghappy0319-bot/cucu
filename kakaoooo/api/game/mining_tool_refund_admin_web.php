<?php
/**
 * 채굴 장비 강화비 환불 (관리자)
 *
 * URL: /page/mining_tool_refund.php?code=XXXX
 */
require_once __DIR__ . '/mining_config.inc.php';
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
require_once __DIR__ . '/mining_tool_refund_admin.inc.php';

$auth_state = mining_ore_admin_auth_state();
$code = (string)$auth_state['code'];
$admin = $auth_state['admin'];
$is_admin = (bool)$auth_state['is_admin'];
$deny_reason = (string)$auth_state['deny_reason'];

$tz = new DateTimeZone('Asia/Seoul');
$yesterday = (new DateTime('yesterday', $tz))->format('Y-m-d');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$week_ago = (new DateTime('-6 days', $tz))->format('Y-m-d');

$date_from = trim((string)($_REQUEST['date_from'] ?? ''));
$date_to = trim((string)($_REQUEST['date_to'] ?? ''));
$preset = trim((string)($_REQUEST['preset'] ?? ''));
if ($preset === 'yesterday') {
    $date_from = $yesterday;
    $date_to = $yesterday;
} elseif ($preset === 'week') {
    $date_from = $week_ago;
    $date_to = $today;
} elseif ($preset === 'all') {
    $date_from = '';
    $date_to = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    if (!$is_admin || empty($admin['nick'])) {
        $msg = '관리자 인증이 필요합니다.';
        if ($deny_reason === 'invalid_code') {
            $msg = '유효하지 않은 코드입니다.';
        } elseif ($deny_reason === 'not_admin') {
            $msg = '관리자 계정이 아닙니다.';
        } elseif ($deny_reason === 'code_required') {
            $msg = '접속 코드가 필요합니다.';
        }
        echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $admin_nick = (string)$admin['nick'];
    $post_from = trim((string)($_POST['date_from'] ?? ''));
    $post_to = trim((string)($_POST['date_to'] ?? ''));
    $reset_mining = !empty($_POST['reset_mining']);

    if ($action === 'refund_all') {
        echo json_encode(
            mining_tool_refund_execute($admin_nick, null, $post_from, $post_to, $reset_mining),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
    if ($action === 'refund_nick') {
        $nick = trim((string)($_POST['nick'] ?? ''));
        echo json_encode(
            mining_tool_refund_execute($admin_nick, $nick !== '' ? [$nick] : null, $post_from, $post_to, $reset_mining),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => '알 수 없는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scan = $is_admin
    ? mining_tool_refund_scan($date_from !== '' ? $date_from : null, $date_to !== '' ? $date_to : null)
    : ['nick_summary' => [], 'logs' => [], 'stats' => []];

$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$filter_q = $q;
if ($date_from !== '') {
    $filter_q .= ($filter_q === '' ? '?' : '&') . 'date_from=' . rawurlencode($date_from);
}
if ($date_to !== '') {
    $filter_q .= ($filter_q === '' ? '?' : '&') . 'date_to=' . rawurlencode($date_to);
}

$deny_msg = '관리자 코드로 접속해주세요.';
if ($deny_reason === 'invalid_code') {
    $deny_msg = '코드가 올바르지 않습니다. 채굴·가방 페이지와 동일한 접속 코드를 사용해주세요.';
} elseif ($deny_reason === 'not_admin') {
    $deny_msg = '코드는 인식됐지만 관리자 권한이 없습니다. (tb_member.admin=1 또는 .관리자 지정 필요)';
}

$filter_label = '전체 기간';
if ($date_from !== '' && $date_to !== '') {
    $filter_label = $date_from === $date_to ? $date_from : ($date_from . ' ~ ' . $date_to);
} elseif ($date_from !== '') {
    $filter_label = $date_from . ' 이후';
} elseif ($date_to !== '') {
    $filter_label = $date_to . ' 이전';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <title>채굴 강화비 환불</title>
    <style>
        :root {
            --bg: #0b1a14;
            --card: rgba(12, 32, 24, 0.92);
            --mint: #6ee7b7;
            --gold: #fbbf24;
            --danger: #f87171;
            --text: #ecfdf5;
            --muted: rgba(236,253,245,0.62);
            --line: rgba(110,231,183,0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 1.1rem; margin: 0 0 8px; }
        h2 { font-size: 0.82rem; margin: 0 0 10px; color: var(--muted); font-weight: 600; }
        .sub { font-size: 0.76rem; color: var(--muted); line-height: 1.55; margin-bottom: 14px; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 10px;
            margin-bottom: 12px;
        }
        .back { color: var(--mint); text-decoration: none; font-size: 0.78rem; display: inline-block; margin-bottom: 12px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px; }
        @media (min-width: 640px) { .stats { grid-template-columns: repeat(4, 1fr); } }
        .stat {
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
        }
        .stat strong { display: block; font-size: 1rem; color: var(--gold); }
        .stat span { font-size: 0.68rem; color: var(--muted); }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 0.74rem; min-width: 720px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-weight: 600; white-space: nowrap; }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-danger { background: rgba(248,113,113,0.25); color: #fecaca; border: 1px solid rgba(248,113,113,0.4); }
        .btn-warn { background: rgba(251,191,36,0.2); color: var(--gold); border: 1px solid rgba(251,191,36,0.35); }
        .btn-mint { background: rgba(110,231,183,0.18); color: var(--mint); border: 1px solid var(--line); }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; align-items: center; }
        .toast {
            position: fixed;
            left: 50%;
            bottom: 20px;
            transform: translateX(-50%);
            background: rgba(12,32,24,0.95);
            border: 1px solid var(--line);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.78rem;
            display: none;
            z-index: 20;
            max-width: 90vw;
        }
        .empty { text-align: center; padding: 24px 12px; color: var(--muted); font-size: 0.8rem; }
        .denied { color: var(--danger); line-height: 1.6; }
        .code-form { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        .code-form input {
            flex: 1 1 180px;
            min-width: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 0.85rem;
        }
        .code-form button {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: rgba(110,231,183,0.18);
            color: var(--mint);
            font-weight: 700;
            cursor: pointer;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-form input[type="date"] {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 0.78rem;
        }
        .filter-label {
            font-size: 0.72rem;
            color: var(--muted);
        }
        .amt { color: var(--gold); font-weight: 700; }
        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
            margin-bottom: 10px;
        }
        .check-row input { margin-top: 2px; }
        .check-row strong { color: var(--text); }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="/page/mining.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 채굴로</a>
    <h1>⛏️ 채굴 장비 강화비 환불</h1>
    <p class="sub">
        <code>tb_point_log</code>의 <strong>채굴강화성공</strong> · <strong>채굴강화실패</strong> 기록을 합산해 닉별 환불액을 보여줍니다.<br>
        환불 시 <strong>게임냥</strong>(<code>tb_member.point</code>) + <strong>본방냥</strong>(닉당 <?= number_format(mining_tool_refund_newpoint_per_nick()) ?>냥 · <code>tb_member.newpoint</code>)을 지급합니다.<br>
        원 로그는 <code>|환불</code> 표시로 중복 환불을 막습니다.
    </p>

    <?php if (!$is_admin) { ?>
    <div class="card denied">
        <?= htmlspecialchars($deny_msg, ENT_QUOTES, 'UTF-8') ?>
        <form class="code-form" method="get" action="/page/mining_tool_refund.php">
            <input type="text" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" placeholder="접속 코드 입력" autocomplete="off" required>
            <button type="submit">접속</button>
        </form>
    </div>
    <?php } else { ?>
    <p class="sub" style="margin-top:-6px">관리자: <?= htmlspecialchars((string)$admin['nick'], ENT_QUOTES, 'UTF-8') ?> · 조회: <?= htmlspecialchars($filter_label, ENT_QUOTES, 'UTF-8') ?></p>

    <div class="card">
        <form class="filter-form" method="get" action="/page/mining_tool_refund.php">
            <?php if ($code !== '') { ?>
            <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
            <?php } ?>
            <span class="filter-label">기간</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') ?>">
            <span class="filter-label">~</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-mint">조회</button>
            <a class="btn btn-mint" href="/page/mining_tool_refund.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&preset=all">전체</a>
            <a class="btn btn-mint" href="/page/mining_tool_refund.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&preset=yesterday">어제</a>
            <a class="btn btn-mint" href="/page/mining_tool_refund.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>&preset=week">최근7일</a>
        </form>
    </div>

    <div class="stats">
        <div class="stat"><strong><?= (int)($scan['stats']['nick_count'] ?? 0) ?></strong><span>환불 대상 닉</span></div>
        <div class="stat"><strong><?= htmlspecialchars((string)($scan['stats']['pending_total_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></strong><span>게임냥 환불</span></div>
        <div class="stat"><strong><?= htmlspecialchars((string)($scan['stats']['pending_newpoint_total_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></strong><span>본방냥 환불</span></div>
        <div class="stat"><strong><?= (int)($scan['stats']['attempt_total'] ?? 0) ?></strong><span>강화 시도</span></div>
    </div>

    <div class="card">
        <div class="toolbar">
            <label class="check-row">
                <input type="checkbox" id="chkResetMining">
                <span><strong>채굴 초기화</strong> — 장비 Lv0(숟가락) · 미저장 채굴량 0 · 무기 해제 · 광물·광물 이력 전부 삭제 · 은총조각 0 · 동기화 세션 해제</span>
            </label>
            <?php if (!empty($scan['nick_summary'])) { ?>
            <button type="button" class="btn btn-warn" id="btnRefundAll">일괄 환불 (게임냥 <?= htmlspecialchars((string)($scan['stats']['pending_total_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?> + 본방냥 <?= htmlspecialchars((string)($scan['stats']['pending_newpoint_total_fmt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>)</button>
            <?php } ?>
            <button type="button" class="btn btn-mint" onclick="location.reload()">새로고침</button>
        </div>
        <h2>닉별 환불 요약</h2>
        <?php if (empty($scan['nick_summary'])) { ?>
        <div class="empty">환불 대상 기록이 없습니다.</div>
        <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>닉</th>
                        <th>시도</th>
                        <th>성공</th>
                        <th>실패</th>
                        <th>게임냥</th>
                        <th>본방냥</th>
                        <th>기간</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scan['nick_summary'] as $row) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$row['attempt_count'] ?></td>
                        <td><?= (int)$row['success_count'] ?></td>
                        <td><?= (int)$row['fail_count'] ?></td>
                        <td class="amt"><?= htmlspecialchars($row['pending_refund_fmt'], ENT_QUOTES, 'UTF-8') ?>냥</td>
                        <td class="amt"><?= htmlspecialchars($row['pending_newpoint_refund_fmt'], ENT_QUOTES, 'UTF-8') ?>냥</td>
                        <td><?= htmlspecialchars(substr($row['first_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?> ~ <?= htmlspecialchars(substr($row['last_at'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <button type="button" class="btn btn-warn btn-refund-nick"
                                data-nick="<?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?>"
                                data-amt="<?= htmlspecialchars($row['pending_refund_fmt'], ENT_QUOTES, 'UTF-8') ?>"
                                data-np="<?= htmlspecialchars($row['pending_newpoint_refund_fmt'], ENT_QUOTES, 'UTF-8') ?>">환불</button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>

    <div class="card">
        <h2>최근 강화 로그 (최대 200건)</h2>
        <?php if (empty($scan['logs'])) { ?>
        <div class="empty">로그 없음</div>
        <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>닉</th>
                        <th>상태</th>
                        <th>장비</th>
                        <th>소모</th>
                        <th>일시</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scan['logs'] as $row) { ?>
                    <tr>
                        <td><?= (int)$row['idx'] ?></td>
                        <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['receiver'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amt"><?= htmlspecialchars($row['point_fmt'], ENT_QUOTES, 'UTF-8') ?>냥</td>
                        <td><?= htmlspecialchars($row['regdate'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>

    <div class="toast" id="toast"></div>
    <script>
    (function() {
        var CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
        var DATE_FROM = <?= json_encode($date_from, JSON_UNESCAPED_UNICODE) ?>;
        var DATE_TO = <?= json_encode($date_to, JSON_UNESCAPED_UNICODE) ?>;
        var PENDING_TOTAL = <?= json_encode((string)($scan['stats']['pending_total_fmt'] ?? '0'), JSON_UNESCAPED_UNICODE) ?>;
        var PENDING_NEWPOINT_TOTAL = <?= json_encode((string)($scan['stats']['pending_newpoint_total_fmt'] ?? '0'), JSON_UNESCAPED_UNICODE) ?>;
        var NICK_COUNT = <?= (int)($scan['stats']['nick_count'] ?? 0) ?>;
        var toast = document.getElementById('toast');
        function showToast(msg) {
            toast.textContent = msg;
            toast.style.display = 'block';
            clearTimeout(showToast._t);
            showToast._t = setTimeout(function() { toast.style.display = 'none'; }, 3200);
        }
        function post(data) {
            data.code = CODE;
            data.date_from = DATE_FROM;
            data.date_to = DATE_TO;
            var chkReset = document.getElementById('chkResetMining');
            if (chkReset && chkReset.checked) {
                data.reset_mining = '1';
            }
            var body = new URLSearchParams();
            Object.keys(data).forEach(function(k) {
                body.append(k, data[k]);
            });
            return fetch(location.pathname + location.search, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function(r) {
                return r.text().then(function(t) {
                    if (!r.ok) {
                        throw new Error('서버 오류 (' + r.status + ')');
                    }
                    try {
                        return JSON.parse(t);
                    } catch (e) {
                        throw new Error('응답 형식 오류');
                    }
                });
            });
        }
        function handleRefund(promise, btn) {
            if (btn) {
                btn.disabled = true;
            }
            promise.then(function(j) {
                showToast((j && j.msg) ? j.msg : '완료');
                if (j && j.ok) {
                    setTimeout(function() { location.reload(); }, 800);
                }
            }).catch(function(err) {
                showToast((err && err.message) ? err.message : '요청 실패');
            }).finally(function() {
                if (btn) {
                    btn.disabled = false;
                }
            });
        }
        function resetMiningNote() {
            var chkReset = document.getElementById('chkResetMining');
            if (chkReset && chkReset.checked) {
                return '\n\n채굴 초기화도 함께 실행됩니다.\n(장비 Lv0 · 채굴량 0 · 광물·이력 삭제 · 무기해제 등)';
            }
            return '';
        }
        var btnAll = document.getElementById('btnRefundAll');
        if (btnAll) {
            btnAll.addEventListener('click', function() {
                var msg = '총 ' + NICK_COUNT + '명에게 환불할까요?\n'
                    + '게임냥 ' + PENDING_TOTAL + '냥 + 본방냥 ' + PENDING_NEWPOINT_TOTAL + '냥'
                    + resetMiningNote() + '\n'
                    + '이 작업은 되돌릴 수 없습니다.';
                if (!confirm(msg)) return;
                handleRefund(post({ action: 'refund_all' }), btnAll);
            });
        }
        document.querySelectorAll('.btn-refund-nick').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var nick = btn.getAttribute('data-nick');
                var amt = btn.getAttribute('data-amt');
                var np = btn.getAttribute('data-np');
                if (!confirm(nick + ' — 게임냥 ' + amt + '냥 + 본방냥 ' + np + '냥을 환불할까요?' + resetMiningNote())) return;
                handleRefund(post({ action: 'refund_nick', nick: nick }), btn);
            });
        });
    })();
    </script>
    <?php } ?>
</div>
</body>
</html>
