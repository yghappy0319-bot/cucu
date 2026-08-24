<?php
/**
 * 꼬맨틀 (단어 유사도 추측) — 지갑용 임베드
 * URL: /page/kkomaentl.php?code=XXXX
 * 원본: https://semantle-ko.newsjel.ly/
 *
 * ※ 보상은 웹에서 지급하지 않음 (순위 조작 방지)
 *    목표 순위 달성 후 연구실 `.꼬맨완료` 로 검증·지급
 */
require __DIR__ . '/_wallet_preauth.php';

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
if (!function_exists('db_select')) {
    include_once ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) . '/lib/_function.php';
}
if (!function_exists('꼬맨_지갑_페이로드') && is_file(__DIR__ . '/../api/function.php')) {
    include_once __DIR__ . '/../api/function.php';
}
if ($nick !== '' && function_exists('getTwoCharNick')) {
    $nick = getTwoCharNick($nick) ?: $nick;
}

$kkoma = [
    'rank' => 0,
    'done' => 0,
    'limit' => 5,
    'completed' => 0,
    'full' => 0,
    'reward' => 0,
    'reward_fmt' => '',
];
if ($nick !== '' && function_exists('꼬맨_지갑_페이로드')) {
    $kkoma = 꼬맨_지갑_페이로드($nick);
}
$wallet_q = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$embed_url = 'https://semantle-ko.newsjel.ly/';
$rank = (int)($kkoma['rank'] ?? 0);
$done = (int)($kkoma['done'] ?? 0);
$limit = (int)($kkoma['limit'] ?? 5);
$completed = !empty($kkoma['completed']);
$full = !empty($kkoma['full']);
$reward_fmt = (string)($kkoma['reward_fmt'] ?? '');
$today_ymd = date('Y-m-d');
try {
    $tz = new DateTimeZone('Asia/Seoul');
    $today_ymd = (new DateTime('now', $tz))->format('Y-m-d');
    $today_label = (new DateTime('now', $tz))->format('Y년 n월 j일');
} catch (Throwable $e) {
    $today_label = date('Y년 n월 j일');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c1222">
    <title>꼬맨틀 · 가방</title>
    <style>
        :root {
            --bg: #0c1222;
            --bar: rgba(8, 12, 24, 0.94);
            --line: rgba(94, 234, 212, 0.22);
            --mint: #5eead4;
            --text: #e8eef8;
            --muted: rgba(232, 238, 248, 0.62);
            --violet: #c4b5fd;
            --gold: #fcd34d;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            min-height: 100vh;
        }
        .top {
            position: sticky;
            top: 0;
            z-index: 5;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            padding-top: max(10px, env(safe-area-inset-top));
            background: var(--bar);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(10px);
        }
        .top-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .back {
            color: var(--mint);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .title {
            font-weight: 800;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .open-ext,
        .btn-capture {
            border: 1px solid rgba(94, 234, 212, 0.35);
            background: rgba(94, 234, 212, 0.1);
            color: var(--mint);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-capture {
            border-color: rgba(196, 181, 253, 0.4);
            background: rgba(167, 139, 250, 0.14);
            color: var(--violet);
        }
        .btn-capture:disabled {
            opacity: 0.45;
            cursor: default;
        }
        .capture-toast {
            display: none;
            position: fixed;
            left: 50%;
            bottom: max(88px, calc(env(safe-area-inset-bottom) + 88px));
            transform: translateX(-50%);
            z-index: 40;
            max-width: min(92vw, 340px);
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(8, 12, 24, 0.92);
            border: 1px solid rgba(196, 181, 253, 0.35);
            color: var(--text);
            font-size: 0.78rem;
            line-height: 1.45;
            text-align: center;
            box-shadow: 0 12px 30px rgba(0,0,0,0.35);
        }
        .capture-toast.on { display: block; }
        .capture-toast.ok { border-color: rgba(94, 234, 212, 0.4); color: var(--mint); }
        .capture-toast.err { border-color: rgba(248, 113, 113, 0.4); color: #fca5a5; }
        .capture-preview {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 50;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(4, 8, 18, 0.8);
            backdrop-filter: blur(6px);
        }
        .capture-preview.on { display: flex; }
        .capture-box {
            width: min(100%, 420px);
            background: #101827;
            border: 1px solid rgba(196, 181, 253, 0.3);
            border-radius: 16px;
            padding: 14px;
            text-align: center;
        }
        .capture-box img {
            display: block;
            width: 100%;
            max-height: 55vh;
            object-fit: contain;
            border-radius: 10px;
            background: #000;
            margin-bottom: 12px;
        }
        .capture-box .actions {
            display: flex;
            gap: 8px;
        }
        .capture-box .actions a,
        .capture-box .actions button {
            flex: 1;
            border-radius: 11px;
            padding: 12px 10px;
            font-weight: 800;
            font-size: 0.88rem;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .capture-box .actions a {
            background: rgba(167, 139, 250, 0.2);
            border-color: rgba(196, 181, 253, 0.45);
            color: var(--violet);
        }
        .capture-box .actions button {
            background: rgba(94, 234, 212, 0.12);
            border-color: rgba(94, 234, 212, 0.35);
            color: var(--mint);
        }
        .dock-actions {
            margin-top: 10px;
        }
        .dock-actions .btn-capture-wide {
            width: 100%;
            border: 1px solid rgba(196, 181, 253, 0.4);
            background: rgba(167, 139, 250, 0.16);
            color: var(--violet);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.9rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
        }
        .dock-actions .btn-capture-wide:disabled {
            opacity: 0.45;
            cursor: default;
        }
        .dock-actions .cap-hint {
            margin-top: 6px;
            font-size: 0.68rem;
            color: var(--muted);
            line-height: 1.4;
            text-align: center;
        }
        .android-guide {
            display: none;
            margin-top: 10px;
            padding: 12px;
            border-radius: 12px;
            background: rgba(252, 211, 77, 0.08);
            border: 1px solid rgba(252, 211, 77, 0.28);
            color: var(--muted);
            font-size: 0.74rem;
            line-height: 1.55;
        }
        .android-guide.on { display: block; }
        .android-guide strong { color: var(--gold); }
        .android-guide ol {
            margin: 8px 0 0 1.1em;
            padding: 0;
        }
        .android-guide li { margin: 4px 0; }
        .dock-how-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(196, 181, 253, 0.28);
            background: rgba(167, 139, 250, 0.1);
            color: var(--violet);
            font-size: 0.82rem;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
        }
        .dock-how-toggle .chev { opacity: 0.8; font-weight: 700; }
        .dock-how-body {
            margin-top: 10px;
        }
        .dock-how-body.collapsed {
            display: none;
        }
        .guide-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 55;
            align-items: flex-end;
            justify-content: center;
            background: rgba(4, 8, 18, 0.72);
            padding: 16px;
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        .guide-overlay.on { display: flex; }
        .guide-sheet {
            width: min(100%, 420px);
            background: #121a2b;
            border: 1px solid rgba(252, 211, 77, 0.3);
            border-radius: 18px 18px 14px 14px;
            padding: 18px 16px 14px;
        }
        .guide-sheet h3 {
            font-size: 1.05rem;
            margin-bottom: 8px;
            color: var(--gold);
        }
        .guide-sheet p, .guide-sheet li {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.55;
        }
        .guide-sheet ol { margin: 10px 0 14px 1.15em; padding: 0; }
        .guide-sheet .guide-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .guide-sheet .guide-actions button,
        .guide-sheet .guide-actions a {
            width: 100%;
            border-radius: 12px;
            padding: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            font-family: inherit;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            box-sizing: border-box;
        }
        .guide-sheet .btn-primary {
            background: rgba(167, 139, 250, 0.2);
            border-color: rgba(196, 181, 253, 0.45);
            color: var(--violet);
        }
        .guide-sheet .btn-secondary {
            background: rgba(94, 234, 212, 0.1);
            border-color: rgba(94, 234, 212, 0.35);
            color: var(--mint);
        }
        .hint {
            flex-shrink: 0;
            padding: 8px 12px;
            font-size: 0.72rem;
            color: var(--muted);
            line-height: 1.45;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            align-items: center;
        }
        .hint b { color: var(--violet); font-weight: 800; }
        .hint .sep { opacity: 0.35; }
        .frame-wrap {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
            background: #fff;
        }
        iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }
        .fallback {
            display: none;
            position: absolute;
            inset: 0;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            padding: 24px;
            text-align: center;
            background: var(--bg);
            color: var(--text);
        }
        .fallback.on { display: flex; }
        .fallback p {
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.5;
            max-width: 18rem;
        }
        .fallback a {
            display: inline-block;
            margin-top: 4px;
            padding: 12px 18px;
            border-radius: 12px;
            background: rgba(94, 234, 212, 0.14);
            border: 1px solid rgba(94, 234, 212, 0.4);
            color: var(--mint);
            font-weight: 800;
            text-decoration: none;
        }
        .dock {
            position: sticky;
            bottom: 0;
            z-index: 6;
            flex-shrink: 0;
            padding: 10px 14px;
            padding-bottom: max(10px, env(safe-area-inset-bottom));
            background: var(--bar);
            border-top: 1px solid rgba(196, 181, 253, 0.28);
            backdrop-filter: blur(10px);
        }
        .dock-target {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 0;
            text-align: right;
        }
        .dock-target-main {
            min-width: 0;
        }
        .dock-date {
            flex-shrink: 0;
            margin-top: 0;
            text-align: right;
            font-size: 0.78rem;
            font-weight: 800;
            color: var(--gold);
            letter-spacing: 0.02em;
            line-height: 1.35;
        }
        .dock-date span {
            display: block;
            color: var(--muted);
            font-weight: 600;
            font-size: 0.7rem;
            margin-right: 0;
            margin-bottom: 2px;
        }
        .dock-must {
            margin-top: 6px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
            text-align: right;
        }
        .dock-must b {
            color: var(--violet);
            font-weight: 800;
        }
        .dock-target .label {
            font-size: 0.72rem;
            color: var(--muted);
        }
        .dock-target .value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--violet);
            font-variant-numeric: tabular-nums;
            text-align: right;
        }
        .dock-steps {
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.55;
        }
        .dock-steps b { color: var(--mint); }
        .dock-steps .warn {
            display: block;
            margin-top: 6px;
            color: #fcd34d;
            font-weight: 600;
        }
        .dock.done .dock-steps .warn { display: none; }
        .dock-ok {
            margin-top: 6px;
            font-size: 0.78rem;
            color: var(--mint);
            font-weight: 700;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="top-left">
            <a class="back" href="/page/wallet.php<?php echo htmlspecialchars($wallet_q, ENT_QUOTES, 'UTF-8'); ?>">← 가방</a>
            <span class="title">🧠 꼬맨틀</span>
        </div>
        <div class="top-actions">
            <button type="button" class="btn-capture" id="btnCaptureTop">캡쳐</button>
            <a class="open-ext" href="<?php echo htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">새 창</a>
        </div>
    </div>
    <div class="hint">
        <span>오늘 <b><?php echo htmlspecialchars($today_label, ENT_QUOTES, 'UTF-8'); ?></b></span>
        <span class="sep">·</span>
        <span>목표 유사도 순위 <b><?php echo $rank > 0 ? number_format($rank) : '—'; ?></b> <b>필수</b></span>
        <span class="sep">·</span>
        <span>완료 <b><?php echo (int)$done; ?>/<?php echo (int)$limit; ?></b></span>
        <?php if ($reward_fmt !== '') { ?>
        <span class="sep">·</span>
        <span>보상 본방냥 1% (<b><?php echo htmlspecialchars($reward_fmt, ENT_QUOTES, 'UTF-8'); ?></b>)</span>
        <?php } ?>
    </div>
    <div class="frame-wrap">
        <iframe
            id="kkomaFrame"
            src="<?php echo htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8'); ?>"
            title="꼬맨틀"
            allow="clipboard-write"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
        <div class="fallback" id="kkomaFallback">
            <p>사이트에서 임베드를 막은 경우 새 창으로 플레이해주세요.</p>
            <a href="<?php echo htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">꼬맨틀 열기</a>
        </div>
    </div>

    <div class="dock<?php echo $completed ? ' done' : ''; ?>" id="kkomaDock">
        <div class="dock-target">
            <div class="dock-date" id="dockDate">
                <span>인증 날짜</span>
                <?php echo htmlspecialchars($today_label, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="dock-target-main">
                <div class="label">오늘 내 목표 유사도 순위 (필수)</div>
                <div class="value"><?php echo $rank > 0 ? number_format($rank) : '—'; ?></div>
            </div>
        </div>
        <?php if (!$completed && !$full && $rank > 0) { ?>
        <div class="dock-must">게임에서 유사도 순위 <b><?php echo (int)$rank; ?></b>을 반드시 달성해야 합니다.</div>
        <?php } ?>
        <?php if ($completed) { ?>
        <div class="dock-ok">오늘은 이미 완료 · 보상 지급됐어요.</div>
        <?php } elseif ($full) { ?>
        <div class="dock-ok" style="margin-top:8px">오늘 참여 인원이 마감됐어요. (하루 <?php echo (int)$limit; ?>명)</div>
        <?php } else { ?>
        <button type="button" class="dock-how-toggle" id="btnHowToggle" aria-expanded="false" aria-controls="dockHowBody">
            <span id="howToggleLabel">캡쳐 · 완료 방법</span>
            <span class="chev" id="howToggleIcon">▸</span>
        </button>
        <div class="dock-how-body collapsed" id="dockHowBody">
            <div class="dock-steps" id="dockStepsDesktop">
                1) 게임에서 유사도 순위 <b><?php echo (int)$rank; ?></b>을 <b>반드시</b> 달성<br>
                2) 폰 <b>자체 캡쳐</b>(전원+볼륨↓)로 <b>오늘 날짜·목표 순위</b>가 보이게 저장<br>
                3) 연구실에서 <b>.꼬맨완료</b> 입력 → 본방냥 1% 지급
                <span class="warn">※ 웹에서는 보상을 받을 수 없어요. (순위 조작 방지)</span>
            </div>
            <div class="android-guide" id="androidGuide">
                <strong>안드로이드 크롬</strong>은 PC 크롬과 달리 웹 화면 캡쳐 API를 지원하지 않아요. (브라우저 제한)
                <ol>
                    <li><strong>전원 버튼 + 볼륨 다운</strong>을 잠깐 같이 누르기</li>
                    <li>(기종에 따라) 손날로 화면 밀기 / 빠른설정 캡쳐</li>
                    <li>갤러리에서 확인 후 공창·연구실에 올리기</li>
                </ol>
            </div>
            <div class="dock-actions">
                <button type="button" class="btn-capture-wide" id="btnCaptureDock">📷 화면 캡쳐 · 저장</button>
                <button type="button" class="btn-capture-wide" id="btnSaveCardDock" style="margin-top:8px;border-color:rgba(94,234,212,0.35);background:rgba(94,234,212,0.1);color:var(--mint)">목표 순위 카드만 저장</button>
                <div class="cap-hint" id="capHint">안드로이드 크롬은 웹 캡쳐 미지원 → 폰 자체 캡쳐를 사용해주세요.</div>
            </div>
        </div>
        <?php } ?>
    </div>

    <div class="capture-toast" id="captureToast"></div>
    <div class="capture-preview" id="capturePreview">
        <div class="capture-box">
            <img id="captureImg" alt="캡쳐 미리보기">
            <div class="actions">
                <a id="captureDownload" download="kkomaentl.png">이미지 저장</a>
                <button type="button" id="captureClose">닫기</button>
            </div>
        </div>
    </div>

    <div class="guide-overlay" id="guideOverlay">
        <div class="guide-sheet">
            <h3>📷 안드로이드 화면 캡쳐</h3>
            <p>안드로이드 크롬은 PC 크롬과 달리 <strong>웹 화면 캡쳐(getDisplayMedia)를 지원하지 않습니다.</strong> 버그가 아니라 브라우저 제한이에요. 폰 자체 캡쳐를 써주세요.</p>
            <ol>
                <li><strong>전원 + 볼륨 다운</strong>을 동시에 잠깐 누르기</li>
                <li>화면이 깜빡이면 갤러리/스크린샷 폴더에 저장됨</li>
                <li>꼬맨틀 순위·오늘 날짜가 보이게 찍은 뒤 연구실에 올리고 <strong>.꼬맨완료</strong></li>
            </ol>
            <div class="guide-actions">
                <a class="btn-primary" id="guideOpenGame" href="<?php echo htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">꼬맨틀 새 창으로 열기</a>
                <button type="button" class="btn-secondary" id="guideSaveCard">목표 순위 카드만 저장</button>
                <button type="button" class="btn-secondary" id="guideClose">닫기</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" crossorigin="anonymous"></script>
    <script>
    (function () {
        var TARGET = <?php echo (int)$rank; ?>;
        var NICK = <?php echo json_encode($nick, JSON_UNESCAPED_UNICODE); ?>;
        var frame = document.getElementById('kkomaFrame');
        var fallback = document.getElementById('kkomaFallback');
        var btnTop = document.getElementById('btnCaptureTop');
        var btnDock = document.getElementById('btnCaptureDock');
        var btnSaveCardDock = document.getElementById('btnSaveCardDock');
        var toast = document.getElementById('captureToast');
        var preview = document.getElementById('capturePreview');
        var captureImg = document.getElementById('captureImg');
        var captureDownload = document.getElementById('captureDownload');
        var captureClose = document.getElementById('captureClose');
        var guideOverlay = document.getElementById('guideOverlay');
        var guideClose = document.getElementById('guideClose');
        var guideSaveCard = document.getElementById('guideSaveCard');
        var androidGuide = document.getElementById('androidGuide');
        var capHint = document.getElementById('capHint');
        var howToggle = document.getElementById('btnHowToggle');
        var howBody = document.getElementById('dockHowBody');
        var howIcon = document.getElementById('howToggleIcon');
        var howLabel = document.getElementById('howToggleLabel');
        var capturing = false;

        var ua = navigator.userAgent || '';
        var isAndroid = /Android/i.test(ua);
        var isIOS = /iPhone|iPad|iPod/i.test(ua);
        var canDisplayCapture = !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia)
            && !isAndroid
            && !isIOS;

        var HOW_KEY = 'kkoma_how_open';
        function setHowOpen(open, save) {
            if (!howBody || !howToggle) return;
            howBody.classList.toggle('collapsed', !open);
            howToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (howIcon) howIcon.textContent = open ? '▾' : '▸';
            if (howLabel) {
                howLabel.textContent = open ? '캡쳐 · 완료 방법 접기' : '캡쳐 · 완료 방법';
            }
            if (save) {
                try { localStorage.setItem(HOW_KEY, open ? '1' : '0'); } catch (e) {}
            }
        }
        function initHowToggle() {
            if (!howToggle || !howBody) return;
            var open = false;
            try {
                // 기본은 접힘. 사용자가 열어둔 경우만 유지
                open = localStorage.getItem(HOW_KEY) === '1';
            } catch (e) {}
            setHowOpen(open, false);
            howToggle.addEventListener('click', function () {
                setHowOpen(howBody.classList.contains('collapsed'), true);
            });
        }
        initHowToggle();

        if (frame && fallback) {
            var shown = false;
            function showFallback() {
                if (shown) return;
                shown = true;
                fallback.classList.add('on');
            }
            var timer = setTimeout(function () {
                try {
                    var doc = frame.contentDocument;
                    if (doc && doc.body && !doc.body.innerHTML) showFallback();
                } catch (e) {}
            }, 4000);
            frame.addEventListener('load', function () { clearTimeout(timer); });
            frame.addEventListener('error', showFallback);
        }

        function showToast(msg, kind) {
            if (!toast) return;
            toast.textContent = msg || '';
            toast.className = 'capture-toast on' + (kind ? (' ' + kind) : '');
            clearTimeout(showToast._t);
            showToast._t = setTimeout(function () {
                toast.classList.remove('on');
            }, 3600);
        }

        function setBusy(on, label) {
            capturing = !!on;
            if (btnTop) btnTop.disabled = capturing;
            if (btnDock) btnDock.disabled = capturing;
            if (btnTop) btnTop.textContent = capturing ? '처리중…' : (canDisplayCapture ? '캡쳐' : '방법');
            if (btnDock) {
                btnDock.textContent = capturing
                    ? (label || '처리 중…')
                    : (canDisplayCapture ? '📷 꼬맨틀 화면 캡쳐 · 저장' : '📷 캡쳐 안내 다시 보기');
            }
            if (btnSaveCardDock) btnSaveCardDock.disabled = capturing;
        }

        function filename(prefix) {
            var d = new Date();
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var stamp = d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate())
                + '_' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds());
            var who = (NICK || 'user').replace(/[^\w가-힣\-]/g, '');
            return (prefix || '꼬맨틀_') + who + '_목표' + (TARGET || 0) + '_' + stamp + '.png';
        }

        function openGuide() {
            // 모바일은 하단 안내만 펼침 (전체 오버레이로 게임 화면을 가리지 않음)
            setHowOpen(true, true);
            if (canDisplayCapture && guideOverlay) {
                guideOverlay.classList.add('on');
            } else if (howBody && howBody.scrollIntoView) {
                try { howToggle.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
            }
        }
        function closeGuide() {
            if (guideOverlay) guideOverlay.classList.remove('on');
        }

        function stopStream(stream) {
            if (!stream) return;
            try {
                stream.getTracks().forEach(function (t) { t.stop(); });
            } catch (e) {}
        }

        function showBlobPreview(blob, name) {
            var url = URL.createObjectURL(blob);
            if (captureImg) captureImg.src = url;
            if (captureDownload) {
                captureDownload.href = url;
                captureDownload.download = name || filename();
            }
            if (preview) preview.classList.add('on');
            showToast('저장할 이미지를 준비했어요.', 'ok');
        }

        /** 목표 순위 카드만 html2canvas로 저장 (모바일 폴백) */
        function captureTargetCard() {
            if (capturing) return;
            if (typeof html2canvas !== 'function') {
                showToast('카드 저장 기능을 불러오지 못했어요. 폰 자체 캡쳐를 사용해주세요.', 'err');
                return;
            }
            var target = document.getElementById('kkomaDock');
            if (!target) {
                showToast('저장할 영역을 찾지 못했어요.', 'err');
                return;
            }
            closeGuide();
            setBusy(true, '📷 카드 저장 중…');
            html2canvas(target, {
                backgroundColor: '#0c1222',
                useCORS: true,
                allowTaint: false,
                logging: false,
                scale: Math.min(2, window.devicePixelRatio || 1.5)
            }).then(function (canvas) {
                canvas.toBlob(function (blob) {
                    setBusy(false);
                    if (!blob) {
                        showToast('이미지 변환에 실패했어요.', 'err');
                        return;
                    }
                    showBlobPreview(blob, filename('꼬맨목표_'));
                }, 'image/png');
            }).catch(function () {
                setBusy(false);
                showToast('카드 저장에 실패했어요. 폰 자체 캡쳐를 사용해주세요.', 'err');
            });
        }

        function captureScreen() {
            if (capturing) return;
            if (!canDisplayCapture) {
                openGuide();
                return;
            }
            setBusy(true, '📷 캡쳐 중…');
            showToast('팝업에서 「이 탭」또는 현재 창을 선택하세요.', '');

            var opts = {
                video: { displaySurface: 'browser' },
                audio: false,
                preferCurrentTab: true,
                selfBrowserSurface: 'include',
                surfaceSwitching: 'exclude',
                systemAudio: 'exclude'
            };

            navigator.mediaDevices.getDisplayMedia(opts).then(function (stream) {
                var video = document.createElement('video');
                video.playsInline = true;
                video.muted = true;
                video.srcObject = stream;

                var done = false;
                function fail(msg) {
                    if (done) return;
                    done = true;
                    stopStream(stream);
                    setBusy(false);
                    showToast(msg || '캡쳐에 실패했어요.', 'err');
                }

                video.onloadedmetadata = function () {
                    video.play().then(function () {
                        setTimeout(function () {
                            try {
                                var w = video.videoWidth || 0;
                                var h = video.videoHeight || 0;
                                if (w < 2 || h < 2) {
                                    fail('캡쳐 화면을 읽지 못했어요.');
                                    return;
                                }
                                var canvas = document.createElement('canvas');
                                canvas.width = w;
                                canvas.height = h;
                                canvas.getContext('2d').drawImage(video, 0, 0, w, h);
                                stopStream(stream);
                                video.srcObject = null;
                                canvas.toBlob(function (blob) {
                                    setBusy(false);
                                    if (!blob) {
                                        showToast('이미지 변환에 실패했어요.', 'err');
                                        return;
                                    }
                                    showBlobPreview(blob, filename());
                                    done = true;
                                }, 'image/png');
                            } catch (e) {
                                fail('캡쳐 처리 중 오류가 났어요.');
                            }
                        }, 200);
                    }).catch(function () {
                        fail('미리보기를 시작할 수 없어요.');
                    });
                };

                stream.getVideoTracks().forEach(function (track) {
                    track.addEventListener('ended', function () {
                        if (!done) fail('화면 공유가 취소됐어요.');
                    });
                });
            }).catch(function (err) {
                setBusy(false);
                var name = (err && err.name) ? String(err.name) : '';
                if (name === 'NotAllowedError' || name === 'AbortError') {
                    showToast('화면 공유가 취소됐어요.', 'err');
                } else {
                    openGuide();
                }
            });
        }

        // 모바일 UX 초기화
        if (!canDisplayCapture) {
            if (androidGuide) androidGuide.classList.add('on');
            if (btnTop) btnTop.textContent = '방법';
            if (btnDock) btnDock.textContent = '📷 캡쳐 안내 다시 보기';
            if (capHint) {
                capHint.innerHTML = isAndroid
                    ? '안드로이드 크롬은 웹 캡쳐 미지원 → <b>전원 + 볼륨↓</b> 자체 캡쳐를 사용해주세요.'
                    : '이 환경에서는 웹 캡쳐가 어려워 폰/OS 자체 캡쳐를 사용해주세요.';
            }
        } else if (androidGuide) {
            androidGuide.classList.remove('on');
        }

        if (btnTop) btnTop.addEventListener('click', captureScreen);
        if (btnDock) btnDock.addEventListener('click', captureScreen);
        if (btnSaveCardDock) btnSaveCardDock.addEventListener('click', captureTargetCard);
        if (guideClose) guideClose.addEventListener('click', closeGuide);
        if (guideSaveCard) guideSaveCard.addEventListener('click', captureTargetCard);
        if (guideOverlay) {
            guideOverlay.addEventListener('click', function (e) {
                if (e.target === guideOverlay) closeGuide();
            });
        }
        if (captureClose && preview) {
            captureClose.addEventListener('click', function () {
                preview.classList.remove('on');
            });
            preview.addEventListener('click', function (e) {
                if (e.target === preview) preview.classList.remove('on');
            });
        }
        if (captureDownload) {
            captureDownload.addEventListener('click', function () {
                showToast('이미지 저장을 시작했어요.', 'ok');
            });
        }
    })();
    </script>
</body>
</html>
