<?php
/**
 * 채굴 광물 비정상 획득분 회수 (관리자)
 *
 * URL: /page/mining_ore_revoke.php?code=XXXX
 */
require_once __DIR__ . '/mining_config.inc.php';
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
require_once __DIR__ . '/mining_ore_admin.inc.php';

$auth_state = mining_ore_admin_auth_state();
$code = (string)$auth_state['code'];
$admin = $auth_state['admin'];
$is_admin = (bool)$auth_state['is_admin'];
$deny_reason = (string)$auth_state['deny_reason'];

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

    if ($action === 'revoke_one') {
        $find_idx = (int)($_POST['find_idx'] ?? 0);
        $reverse = !empty($_POST['reverse_claimed']);
        echo json_encode(mining_ore_admin_revoke_find($find_idx, $admin_nick, $reverse), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'revoke_batch') {
        $ids = $_POST['find_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $reverse = !empty($_POST['reverse_claimed']);
        echo json_encode(mining_ore_admin_revoke_batch($ids, $admin_nick, $reverse), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'revoke_nick_abnormal') {
        $nick = trim((string)($_POST['nick'] ?? ''));
        echo json_encode(mining_ore_admin_revoke_nick_abnormal($nick, $admin_nick), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'revoke_nick_all_pending') {
        $nick = trim((string)($_POST['nick'] ?? ''));
        echo json_encode(mining_ore_admin_revoke_nick_all_pending($nick, $admin_nick), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'revoke_all_abnormal') {
        $scan = mining_ore_admin_scan();
        $ids = [];
        foreach ($scan['pending'] as $item) {
            if (!empty($item['abnormal'])) {
                $ids[] = (int)$item['idx'];
            }
        }
        echo json_encode(mining_ore_admin_revoke_batch($ids, $admin_nick, false), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'revoke_all_claimed_abnormal') {
        echo json_encode(mining_ore_admin_revoke_all_claimed_abnormal($admin_nick), JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => '알 수 없는 요청입니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scan = $is_admin ? mining_ore_admin_scan() : ['pending' => [], 'claimed' => [], 'nick_summary' => [], 'stats' => []];
$q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$deny_msg = '관리자 코드로 접속해주세요.';
if ($deny_reason === 'invalid_code') {
    $deny_msg = '코드가 올바르지 않습니다. 채굴·가방 페이지와 동일한 접속 코드를 사용해주세요.';
} elseif ($deny_reason === 'not_admin') {
    $deny_msg = '코드는 인식됐지만 관리자 권한이 없습니다. (tb_member.admin=1 또는 .관리자 지정 필요)';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <title>광물 비정상 회수</title>
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
        tr.abnormal td { background: rgba(248,113,113,0.08); }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.62rem;
            font-weight: 700;
        }
        .badge.bad { background: rgba(248,113,113,0.2); color: #fecaca; }
        .badge.ok { background: rgba(110,231,183,0.15); color: var(--mint); }
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
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
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
        .collapse-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
            color: inherit;
            cursor: pointer;
            text-align: left;
        }
        .collapse-toggle h2 {
            margin: 0;
            flex: 1;
            min-width: 0;
        }
        .collapse-toggle .chevron {
            flex: 0 0 auto;
            color: var(--mint);
            font-size: 0.72rem;
            transition: transform 0.18s ease;
            line-height: 1;
        }
        .card.collapsible.is-open .collapse-toggle .chevron {
            transform: rotate(180deg);
        }
        .collapse-body {
            display: none;
            margin-top: 10px;
        }
        .card.collapsible.is-open .collapse-body {
            display: block;
        }
        .collapse-meta {
            color: var(--muted);
            font-weight: 500;
            font-size: 0.72rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="/page/mining.php<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">← 채굴로</a>
    <h1>⛏️ 광물 비정상 회수</h1>
    <p class="sub">
        1시간 스케줄 창당 허용 개수를 초과한 <strong>대기(pending)</strong> 광물을 회수합니다.<br>
        빨간 행 = 스폰 당시 창 키(<code>schedule_window_start</code>) 기준 허용량 초과 비정상입니다.<br>
        <span style="opacity:.75">※ 창 키가 없는 옛 수령분(7일)은 며칠간 일부 오판정이 남을 수 있습니다.</span>
    </p>

    <?php if (!$is_admin) { ?>
    <div class="card denied">
        <?= htmlspecialchars($deny_msg, ENT_QUOTES, 'UTF-8') ?>
        <form class="code-form" method="get" action="/page/mining_ore_revoke.php">
            <input type="text" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" placeholder="접속 코드 입력" autocomplete="off" required>
            <button type="submit">접속</button>
        </form>
        <p class="sub" style="margin-top:12px;margin-bottom:0">
            채굴 페이지 주소에 붙는 <code>?code=</code> 와 동일합니다.<br>
            예: <code>/page/mining_ore_revoke.php?code=여기에코드</code>
        </p>
    </div>
    <?php } else { ?>
    <p class="sub" style="margin-top:-6px">관리자: <?= htmlspecialchars((string)$admin['nick'], ENT_QUOTES, 'UTF-8') ?></p>

    <div class="stats">
        <div class="stat"><strong><?= (int)($scan['stats']['pending_total'] ?? 0) ?></strong><span>대기 광물</span></div>
        <div class="stat"><strong><?= (int)($scan['stats']['abnormal_total'] ?? 0) ?></strong><span>비정상 판정</span></div>
        <div class="stat"><strong><?= (int)($scan['stats']['claimed_total'] ?? 0) ?></strong><span>최근 수령(7일)</span></div>
        <div class="stat"><strong><?= (int)($scan['stats']['claimed_abnormal_total'] ?? 0) ?></strong><span>수령 비정상</span></div>
        <div class="stat"><strong><?= (int)($scan['stats']['nick_count'] ?? 0) ?></strong><span>대기 보유자</span></div>
    </div>

    <div class="card">
        <div class="toolbar">
            <button type="button" class="btn btn-danger" id="btnRevokeAllAbnormal">비정상 전체 회수</button>
            <?php if ((int)($scan['stats']['claimed_abnormal_total'] ?? 0) > 0) { ?>
            <button type="button" class="btn btn-warn" id="btnRevokeAllClaimedAbnormal">수령 비정상 전체 회수</button>
            <?php } ?>
            <button type="button" class="btn btn-mint" onclick="location.reload()">새로고침</button>
        </div>
    </div>

    <div class="card collapsible">
        <button type="button" class="collapse-toggle" aria-expanded="false">
            <h2>닉별 요약 <span class="collapse-meta"><?= (int)($scan['stats']['nick_count'] ?? 0) ?>명 · 비정상 <?= (int)($scan['stats']['abnormal_total'] ?? 0) ?></span></h2>
            <span class="chevron" aria-hidden="true">▼</span>
        </button>
        <div class="collapse-body">
        <?php if (empty($scan['nick_summary'])) { ?>
        <div class="empty">대기 중인 광물이 없습니다.</div>
        <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>닉</th>
                        <th>대기</th>
                        <th>비정상</th>
                        <th>시간당 한도</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scan['nick_summary'] as $row) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$row['pending_total'] ?></td>
                        <td><?= (int)$row['abnormal_total'] ?></td>
                        <td><?= (int)$row['hourly_limit'] ?>개</td>
                        <td>
                            <?php if ((int)$row['abnormal_total'] > 0) { ?>
                            <button type="button" class="btn btn-warn btn-nick-abnormal" data-nick="<?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?>">초과분만</button>
                            <?php } ?>
                            <button type="button" class="btn btn-danger btn-nick-all" data-nick="<?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?>">대기 전부</button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
        </div>
    </div>

    <div class="card collapsible">
        <button type="button" class="collapse-toggle" aria-expanded="false">
            <h2>대기 광물 목록 <span class="collapse-meta"><?= (int)($scan['stats']['pending_total'] ?? 0) ?>건 · 비정상 <?= (int)($scan['stats']['abnormal_total'] ?? 0) ?></span></h2>
            <span class="chevron" aria-hidden="true">▼</span>
        </button>
        <div class="collapse-body">
        <?php if (empty($scan['pending'])) { ?>
        <div class="empty">대기 광물 없음</div>
        <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>닉</th>
                        <th>광물</th>
                        <th>+강</th>
                        <th>한도/h</th>
                        <th>가치</th>
                        <th>발견</th>
                        <th>남은시간</th>
                        <th>판정</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scan['pending'] as $row) { ?>
                    <tr class="<?= !empty($row['abnormal']) ? 'abnormal' : '' ?>">
                        <td><?= (int)$row['idx'] ?></td>
                        <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(($row['icon'] ?? '') . ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>+<?= (int)$row['weapon_enhance'] ?></td>
                        <td><?= (int)$row['hourly_limit'] ?></td>
                        <td><?= htmlspecialchars($row['pending_value_fmt'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['found_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['left_fmt'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($row['abnormal']) ? '<span class="badge bad">비정상</span>' : '<span class="badge ok">정상</span>' ?></td>
                        <td><button type="button" class="btn btn-danger btn-revoke-one" data-id="<?= (int)$row['idx'] ?>">회수</button></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
        </div>
    </div>

    <div class="card collapsible">
        <button type="button" class="collapse-toggle" aria-expanded="false">
            <h2>최근 수령 (7일) <span class="collapse-meta"><?= (int)($scan['stats']['claimed_total'] ?? 0) ?>건 · 비정상 <?= (int)($scan['stats']['claimed_abnormal_total'] ?? 0) ?></span></h2>
            <span class="chevron" aria-hidden="true">▼</span>
        </button>
        <div class="collapse-body">
        <p class="sub" style="margin-top:0">이미 터치해 받은 광물은 <strong>채굴량(mining_pending)</strong>에서 먼저 차감하고, 부족하면 <strong>본방냥(newpoint)</strong>에서 차감합니다. 은총조각은 조각 수에서 차감됩니다.</p>
        <?php if (empty($scan['claimed'])) { ?>
        <div class="empty">최근 수령 기록 없음</div>
        <?php } else { ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>닉</th>
                        <th>광물</th>
                        <th>가치</th>
                        <th>수령</th>
                        <th>판정</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scan['claimed'] as $row) { ?>
                    <tr class="<?= !empty($row['abnormal']) ? 'abnormal' : '' ?>">
                        <td><?= (int)$row['idx'] ?></td>
                        <td><?= htmlspecialchars($row['nick'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(($row['icon'] ?? '') . ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['pending_value_fmt'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['claimed_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($row['abnormal']) ? '<span class="badge bad">비정상</span>' : '<span class="badge ok">정상</span>' ?></td>
                        <td><button type="button" class="btn btn-warn btn-revoke-claimed" data-id="<?= (int)$row['idx'] ?>">수령분 회수</button></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    <script>
    (function() {
        var CODE = <?= json_encode($code, JSON_UNESCAPED_UNICODE) ?>;
        var toast = document.getElementById('toast');
        function showToast(msg) {
            toast.textContent = msg;
            toast.style.display = 'block';
            clearTimeout(showToast._t);
            showToast._t = setTimeout(function() { toast.style.display = 'none'; }, 3200);
        }
        function post(data) {
            data.code = CODE;
            var body = new URLSearchParams();
            Object.keys(data).forEach(function(k) {
                if (Array.isArray(data[k])) {
                    data[k].forEach(function(v) { body.append(k + '[]', v); });
                } else {
                    body.append(k, data[k]);
                }
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
        function handleRevoke(promise, btn) {
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
        document.getElementById('btnRevokeAllAbnormal').addEventListener('click', function() {
            var btn = this;
            if (!confirm('비정상 판정된 대기 광물을 전부 회수할까요?')) return;
            handleRevoke(post({ action: 'revoke_all_abnormal' }), btn);
        });
        var btnClaimedAbnormal = document.getElementById('btnRevokeAllClaimedAbnormal');
        if (btnClaimedAbnormal) {
            btnClaimedAbnormal.addEventListener('click', function() {
                var btn = this;
                if (!confirm('비정상 판정된 수령 광물을 전부 회수할까요?\n채굴량 부족 시 본방냥에서 차감됩니다.')) return;
                handleRevoke(post({ action: 'revoke_all_claimed_abnormal' }), btn);
            });
        }
        document.querySelectorAll('.btn-nick-abnormal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var nick = btn.getAttribute('data-nick');
                if (!confirm(nick + ' — 초과분만 회수할까요?')) return;
                handleRevoke(post({ action: 'revoke_nick_abnormal', nick: nick }), btn);
            });
        });
        document.querySelectorAll('.btn-nick-all').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var nick = btn.getAttribute('data-nick');
                if (!confirm(nick + ' — 대기 광물을 전부 회수할까요?')) return;
                handleRevoke(post({ action: 'revoke_nick_all_pending', nick: nick }), btn);
            });
        });
        document.querySelectorAll('.btn-revoke-one').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                if (!confirm('#' + id + ' 광물을 회수할까요?')) return;
                handleRevoke(post({ action: 'revoke_one', find_idx: id }), btn);
            });
        });
        document.querySelectorAll('.btn-revoke-claimed').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                if (!confirm('#' + id + ' 수령분을 되돌릴까요?\n채굴량에서 먼저 차감하고, 부족하면 본방냥에서 차감됩니다.')) return;
                handleRevoke(post({ action: 'revoke_one', find_idx: id, reverse_claimed: '1' }), btn);
            });
        });
        document.querySelectorAll('.card.collapsible .collapse-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var card = btn.closest('.card.collapsible');
                if (!card) return;
                var open = card.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    })();
    </script>
    <?php } ?>
</div>
</body>
</html>
