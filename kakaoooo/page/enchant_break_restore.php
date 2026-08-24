<?php
/**
 * +20강 이상 파손 무기 복구 (강화 웹)
 *
 * URL: /page/enchant_break_restore.php?code=XXXX
 *
 * AJAX:
 *   action=list     — 미복구 파손 목록
 *   action=restore  — log_idx + mode=base|full
 */

function ebr_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ebr_auth($code) {
    $code = is_string($code) ? trim($code) : '';
    if ($code === '') {
        return null;
    }
    $esc = addslashes($code);
    $row = db_select("SELECT idx, name, CAST(point AS CHAR) AS point, item, enhance FROM tb_member WHERE code = '{$esc}' LIMIT 1");
    if (empty($row['name'])) {
        return null;
    }
    return $row;
}

function ebr_fmt_nyang($n) {
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($n, '냥');
    }
    if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, '냥');
    }
    if (function_exists('강화비용_표시')) {
        return 강화비용_표시($n, '냥');
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($n) : (string)$n;
    return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s) . '냥';
}

function ebr_afford($point, $cost): bool {
    $point = function_exists('냥_정수문자열') ? 냥_정수문자열($point) : (string)$point;
    $cost = function_exists('냥_정수문자열') ? 냥_정수문자열($cost) : (string)$cost;
    if ($cost === '0' || $cost === '') {
        return false;
    }
    if (function_exists('bccomp')) {
        return bccomp($point, $cost, 0) >= 0;
    }
    return strlen($point) > strlen($cost)
        || (strlen($point) === strlen($cost) && $point >= $cost);
}

function ebr_item_payload(array $row, string $point, bool $has_weapon): array {
    $강화 = (int)($row['enhance_before'] ?? 0);
    $base = (string)($row['cost_base'] ?? '0');
    $full = (string)($row['cost_full'] ?? '0');
    $baseLv = (int)($row['restore_base'] ?? max(0, $강화 - 1));
    $fullLv = (int)($row['restore_full'] ?? $강화);
    $canBase = !$has_weapon && ebr_afford($point, $base);
    $canFull = !$has_weapon && ebr_afford($point, $full);
    $block = $has_weapon ? '무기 보유 중' : '게임냥 부족';
    return [
        'idx' => (int)($row['idx'] ?? 0),
        'item' => (string)($row['item'] ?? ''),
        'style' => (string)($row['style'] ?? ''),
        'enhance_before' => $강화,
        'regdate' => (string)($row['regdate'] ?? ''),
        'regdate_fmt' => !empty($row['regdate']) ? date('m-d H:i', strtotime((string)$row['regdate'])) : '',
        'restore_base' => $baseLv,
        'restore_full' => $fullLv,
        'cost_base' => $base,
        'cost_full' => $full,
        'cost_base_fmt' => (string)($row['cost_base_fmt'] ?? ebr_fmt_nyang($base)),
        'cost_full_fmt' => (string)($row['cost_full_fmt'] ?? ebr_fmt_nyang($full)),
        'eunchong' => (string)($row['eunchong'] ?? '0'),
        'eunchong_fmt' => (string)($row['eunchong_fmt'] ?? ebr_fmt_nyang($row['eunchong'] ?? 0)),
        'can_restore_base' => $canBase,
        'can_restore_full' => $canFull,
        'blocked_reason' => $has_weapon ? $block : '',
        'blocked_base' => $has_weapon ? $block : ($canBase ? '' : '게임냥 부족'),
        'blocked_full' => $has_weapon ? $block : ($canFull ? '' : '게임냥 부족'),
    ];
}

$code = isset($_REQUEST['code']) ? trim((string)$_REQUEST['code']) : '';
$need_code = ($code === '');
$nick = '';
$point = '0';
$point_fmt = '0냥';
$has_weapon = false;
$eunchong_fmt = '—';
$break_rows = [];
$items_view = [];

if (!$need_code) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
    $member = ebr_auth($code);
    if (!$member) {
        $need_code = true;
    } else {
        $nick = trim((string)$member['name']);
        $두자리닉넴 = function_exists('getTwoCharNick') ? getTwoCharNick($nick) : $nick;
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/config.php';
        if (function_exists('강화_스키마_보장')) {
            강화_스키마_보장();
        }
        if (function_exists('tb_member_point_컬럼_보장')) {
            tb_member_point_컬럼_보장();
        }
        if (function_exists('시세기준_컬럼_보장')) {
            시세기준_컬럼_보장();
        }
        if (function_exists('무기복구_은총시세_로드')) {
            무기복구_은총시세_로드();
        }

        $point = function_exists('냥_정수문자열') ? 냥_정수문자열($member['point'] ?? 0) : (string)($member['point'] ?? 0);
        $point_fmt = ebr_fmt_nyang($point);
        $has_weapon = trim((string)($member['item'] ?? '')) !== '';
        $eunchong_fmt = function_exists('무기복구_은총1개시세')
            ? ebr_fmt_nyang(무기복구_은총1개시세())
            : '—';

        $req_action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';
        if ($req_action === 'list') {
            $raw = function_exists('무기복구_고강_파손목록') ? 무기복구_고강_파손목록($nick, 30) : [];
            $items = [];
            foreach ($raw as $row) {
                $items[] = ebr_item_payload($row, $point, $has_weapon);
            }
            ebr_json([
                'ok' => true,
                'type' => 'list',
                'has_weapon' => $has_weapon,
                'point' => $point,
                'point_fmt' => $point_fmt,
                'eunchong_fmt' => $eunchong_fmt,
                'items' => $items,
            ]);
        }

        if ($req_action === 'restore') {
            $log_idx = isset($_REQUEST['log_idx']) ? (int)$_REQUEST['log_idx'] : 0;
            $mode = isset($_REQUEST['mode']) ? trim((string)$_REQUEST['mode']) : 'base';
            $result = function_exists('무기복구_고강_실행')
                ? 무기복구_고강_실행($nick, $log_idx, '냥', $mode)
                : ['ok' => false, 'msg' => '❌ 복구 기능을 불러올 수 없어요.'];
            if (empty($result['ok'])) {
                ebr_json(['ok' => false, 'data' => $result['msg'] ?? '❌ 복구에 실패했어요.']);
            }
            ebr_json([
                'ok' => true,
                'type' => 'restore',
                'data' => $result['msg'] ?? '✅ 복구 완료',
                'point' => (string)($result['point'] ?? $point),
                'point_fmt' => (string)($result['point_fmt'] ?? ebr_fmt_nyang($point)),
                'has_weapon' => true,
            ]);
        }

        $break_rows = function_exists('무기복구_고강_파손목록') ? 무기복구_고강_파손목록($nick, 30) : [];
        foreach ($break_rows as $row) {
            $items_view[] = ebr_item_payload($row, $point, $has_weapon);
        }
    }
}

$enchant_back_q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>파손 무기 복구 (+20↑)</title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f0a1a;
            --card: rgba(30, 20, 50, 0.92);
            --text: #f3f0ff;
            --muted: #9ca3af;
            --gold: #ffd700;
            --danger: #f87171;
            --green: #4ade80;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 20% 20%, rgba(248,113,113,0.12) 0%, transparent 45%),
                radial-gradient(ellipse at 80% 80%, rgba(255,215,0,0.08) 0%, transparent 40%);
            font-family: 'Noto Sans KR', sans-serif;
            color: var(--text);
            padding: 24px;
            padding-left: max(24px, env(safe-area-inset-left));
            padding-right: max(24px, env(safe-area-inset-right));
            padding-bottom: max(24px, env(safe-area-inset-bottom));
        }
        .wrap {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: var(--card);
            border-radius: 24px;
            padding: 28px 22px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.06);
        }
        h1 {
            font-family: 'Black Han Sans', sans-serif;
            font-size: 2rem;
            text-align: center;
            margin-bottom: 4px;
            background: linear-gradient(135deg, #fca5a5, var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sub { text-align: center; color: var(--muted); font-size: 0.85rem; margin-bottom: 18px; line-height: 1.45; }
        .card {
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .card h3 { font-size: 0.92rem; color: var(--gold); margin-bottom: 8px; }
        .info-line {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.82rem;
            margin-bottom: 6px;
        }
        .info-line .il { color: var(--muted); }
        .info-line .iv { font-weight: 700; text-align: right; word-break: break-all; }
        .info-line .iv.gold { color: var(--gold); }
        .info-line .iv.danger { color: var(--danger); }
        .info-line .iv.green { color: var(--green); }
        .hint { font-size: 0.78rem; color: var(--muted); line-height: 1.45; }
        .warn {
            background: rgba(248,113,113,0.12);
            border: 1px solid rgba(248,113,113,0.35);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.82rem;
            color: #fecaca;
            margin-bottom: 14px;
        }
        .break-list { display: flex; flex-direction: column; gap: 10px; }
        .break-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .break-item .bi-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
        }
        .break-item .bi-weapon { font-weight: 700; font-size: 0.95rem; }
        .break-item .bi-lv { color: var(--danger); font-weight: 700; white-space: nowrap; }
        .break-item .bi-meta { font-size: 0.78rem; color: var(--muted); margin-bottom: 10px; }
        .restore-opts { display: flex; flex-direction: column; gap: 8px; }
        .btn {
            display: inline-flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 2px;
            min-height: 48px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
            color: var(--text);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            text-align: left;
        }
        .btn .btn-sub { font-size: 0.78rem; font-weight: 500; color: var(--muted); }
        .btn .btn-sub b { color: var(--gold); font-weight: 700; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-base {
            background: linear-gradient(135deg, rgba(96,165,250,0.25), rgba(255,255,255,0.06));
            border-color: rgba(96,165,250,0.4);
        }
        .btn-full {
            background: linear-gradient(135deg, rgba(248,113,113,0.35), rgba(245,158,11,0.25));
            border-color: rgba(248,113,113,0.45);
        }
        .btn-nav {
            display: block;
            text-align: center;
            text-decoration: none;
            margin-top: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.12);
            color: var(--muted);
            font-size: 0.88rem;
        }
        .empty { text-align: center; color: var(--muted); font-size: 0.88rem; padding: 24px 8px; }
        .result {
            display: none;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.84rem;
            line-height: 1.45;
            margin-bottom: 12px;
            white-space: pre-wrap;
        }
        .result.show { display: block; }
        .result.ok { background: rgba(74,222,128,0.12); border: 1px solid rgba(74,222,128,0.35); color: #bbf7d0; }
        .result.fail { background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.35); color: #fecaca; }
        .result.info { background: rgba(96,165,250,0.12); border: 1px solid rgba(96,165,250,0.35); color: #bfdbfe; }
        .need-code { text-align: center; padding: 40px 10px; color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>💥 파손 복구</h1>
    <p class="sub">+20강 이상 터진 무기를<br>-1강 또는 파손 당시 강화로 복구합니다.</p>

    <?php if ($need_code) { ?>
        <div class="need-code">강화 페이지와 동일한 <code>?code=</code> 로 접속해주세요.</div>
        <a class="btn-nav" href="/page/enchant.php">← 강화 페이지</a>
    <?php } else { ?>
        <div id="result" class="result"></div>

        <div class="card">
            <h3>복구 규칙</h3>
            <p class="hint">
                · 예) +31 파손 → +30으로 복구 / +31 그대로 복구 선택<br>
                · 현재 무기가 없을 때만 복구 가능
            </p>
        </div>

        <div class="card">
            <h3>내 상태</h3>
            <div class="info-line"><span class="il">닉네임</span><span class="iv"><?php echo htmlspecialchars($nick, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="info-line"><span class="il">보유 게임냥</span><span class="iv gold" id="myPoint"><?php echo htmlspecialchars($point_fmt, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="info-line"><span class="il">무기 보유</span><span class="iv <?php echo $has_weapon ? '' : 'danger'; ?>" id="hasWeapon"><?php echo $has_weapon ? '있음 (복구 불가)' : '없음 (복구 가능)'; ?></span></div>
        </div>

        <?php if ($has_weapon) { ?>
            <div class="warn">현재 무기를 보유 중이라 복구할 수 없어요. 무기가 없는 상태에서만 복구됩니다.</div>
        <?php } ?>

        <div class="card">
            <h3>터진 무기 목록</h3>
            <div class="break-list" id="breakList">
                <?php if (empty($items_view)) { ?>
                    <div class="empty">+20강 이상 파손 이력이 없어요.</div>
                <?php } else {
                    foreach ($items_view as $it) {
                        $무기표시 = (($it['style'] ?? '') !== '' ? $it['style'] . ' ' : '') . ($it['item'] ?? '');
                        $baseLv = (int)$it['restore_base'];
                        $fullLv = (int)$it['restore_full'];
                        ?>
                        <div class="break-item" data-idx="<?php echo (int)$it['idx']; ?>">
                            <div class="bi-top">
                                <span class="bi-weapon"><?php echo htmlspecialchars($무기표시, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="bi-lv">+<?php echo (int)$it['enhance_before']; ?> 파손</span>
                            </div>
                            <div class="bi-meta">파손 시각 <?php echo htmlspecialchars((string)$it['regdate_fmt'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="restore-opts">
                                <button type="button" class="btn btn-base btn-restore"
                                    data-idx="<?php echo (int)$it['idx']; ?>"
                                    data-mode="base"
                                    <?php if (empty($it['can_restore_base'])) echo 'disabled'; ?>>
                                    <span>+<?php echo $baseLv; ?>강으로 복구<?php
                                        if (empty($it['can_restore_base']) && !empty($it['blocked_base'])) {
                                            echo ' · ' . htmlspecialchars((string)$it['blocked_base'], ENT_QUOTES, 'UTF-8');
                                        }
                                    ?></span>
                                    <span class="btn-sub">비용 <b><?php echo htmlspecialchars((string)$it['cost_base_fmt'], ENT_QUOTES, 'UTF-8'); ?></b></span>
                                </button>
                                <button type="button" class="btn btn-full btn-restore"
                                    data-idx="<?php echo (int)$it['idx']; ?>"
                                    data-mode="full"
                                    <?php if (empty($it['can_restore_full'])) echo 'disabled'; ?>>
                                    <span>+<?php echo $fullLv; ?>강으로 복구<?php
                                        if (empty($it['can_restore_full']) && !empty($it['blocked_full'])) {
                                            echo ' · ' . htmlspecialchars((string)$it['blocked_full'], ENT_QUOTES, 'UTF-8');
                                        }
                                    ?></span>
                                    <span class="btn-sub">비용 <b><?php echo htmlspecialchars((string)$it['cost_full_fmt'], ENT_QUOTES, 'UTF-8'); ?></b></span>
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                } ?>
            </div>
        </div>

        <a class="btn-nav" href="/page/enchant.php<?php echo htmlspecialchars($enchant_back_q, ENT_QUOTES, 'UTF-8'); ?>">← 강화 페이지로</a>
    <?php } ?>
</div>

<?php if (!$need_code) { ?>
<script>
(function() {
    var CODE = <?php echo json_encode($code, JSON_UNESCAPED_UNICODE); ?>;
    var BASE = location.pathname;
    var restoring = false;

    function $(id) { return document.getElementById(id); }

    function showResult(text, kind) {
        var el = $('result');
        el.className = 'result show ' + (kind || 'info');
        el.textContent = text;
    }

    function ajax(params, onDone) {
        params.code = CODE;
        var body = Object.keys(params).map(function(k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }).join('&');
        fetch(BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); })
        .then(onDone)
        .catch(function() { onDone({ ok: false, data: '통신 오류' }); });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderList(j) {
        var list = $('breakList');
        if (!list) return;
        if (j.point_fmt && $('myPoint')) $('myPoint').textContent = j.point_fmt;
        if ($('hasWeapon')) {
            $('hasWeapon').textContent = j.has_weapon ? '있음 (복구 불가)' : '없음 (복구 가능)';
            $('hasWeapon').className = 'iv' + (j.has_weapon ? '' : ' danger');
        }
        if (!j.ok || !j.items || !j.items.length) {
            list.innerHTML = '<div class="empty">+20강 이상 파손 이력이 없어요.</div>';
            return;
        }
        var html = '';
        j.items.forEach(function(it) {
            var weapon = (it.style ? it.style + ' ' : '') + (it.item || '');
            var baseLabel = '+' + it.restore_base + '강으로 복구' + (!it.can_restore_base && it.blocked_base ? (' · ' + it.blocked_base) : '');
            var fullLabel = '+' + it.restore_full + '강으로 복구' + (!it.can_restore_full && it.blocked_full ? (' · ' + it.blocked_full) : '');
            html += '<div class="break-item" data-idx="' + it.idx + '">';
            html += '<div class="bi-top"><span class="bi-weapon">' + esc(weapon) + '</span><span class="bi-lv">+' + it.enhance_before + ' 파손</span></div>';
            html += '<div class="bi-meta">파손 시각 ' + esc(it.regdate_fmt || '') + '</div>';
            html += '<div class="restore-opts">';
            html += '<button type="button" class="btn btn-base btn-restore" data-idx="' + it.idx + '" data-mode="base"' + (it.can_restore_base ? '' : ' disabled') + '>';
            html += '<span>' + esc(baseLabel) + '</span>';
            html += '<span class="btn-sub">비용 <b>' + esc(it.cost_base_fmt || '') + '</b></span>';
            html += '</button>';
            html += '<button type="button" class="btn btn-full btn-restore" data-idx="' + it.idx + '" data-mode="full"' + (it.can_restore_full ? '' : ' disabled') + '>';
            html += '<span>' + esc(fullLabel) + '</span>';
            html += '<span class="btn-sub">비용 <b>' + esc(it.cost_full_fmt || '') + '</b></span>';
            html += '</button>';
            html += '</div></div>';
        });
        list.innerHTML = html;
        bindRestoreButtons();
    }

    function reloadList() {
        ajax({ action: 'list' }, function(j) {
            if (j.ok) renderList(j);
        });
    }

    function bindRestoreButtons() {
        var list = $('breakList');
        if (!list) return;
        list.querySelectorAll('.btn-restore').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (restoring || btn.disabled) return;
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                var mode = btn.getAttribute('data-mode') || 'base';
                if (!idx) return;
                var msg = mode === 'full'
                    ? '파손 당시 강화로 복구할까요?\n게임냥이 차감됩니다.'
                    : '-1강으로 복구할까요?\n게임냥이 차감됩니다.';
                if (!confirm(msg)) return;
                restoring = true;
                btn.disabled = true;
                ajax({ action: 'restore', log_idx: idx, mode: mode }, function(j) {
                    restoring = false;
                    if (!j.ok) {
                        showResult(j.data || '복구 실패', 'fail');
                        reloadList();
                        return;
                    }
                    showResult(j.data || '복구 완료', 'ok');
                    reloadList();
                });
            });
        });
    }

    bindRestoreButtons();
})();
</script>
<?php } ?>
</body>
</html>
