<?php
/**
 * 구매한 무기 사용방법 + 홍보방 링크
 * URL: /page/enchant_weapon_guide.php?code=XXXX
 */

$back_q = '';
$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code !== '') {
    $back_q = '?code=' . rawurlencode($code);
}

$홍보방 = 'https://open.kakao.com/o/prP2JBJi';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#14110e">
    <title>구매한 무기 사용방법</title>
    <style>
        :root {
            --bg: #14110e;
            --card: rgba(36, 28, 22, 0.94);
            --ember: #f0a060;
            --gold: #e8c56b;
            --blue: #7eb8ff;
            --mint: #6ee7b7;
            --text: #f7efe4;
            --muted: rgba(247,239,228,0.58);
            --line: rgba(240,160,96,0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 80% 0%, rgba(240,160,96,0.14) 0%, transparent 48%),
                radial-gradient(ellipse at 10% 100%, rgba(232,197,107,0.08) 0%, transparent 42%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 560px; margin: 0 auto; }
        h1 { font-size: 1.15rem; margin: 0 0 8px; font-weight: 800; }
        .sub {
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.55;
            margin: 0 0 16px;
        }
        .promo {
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 16px 14px;
            margin-bottom: 14px;
            border-radius: 14px;
            border: 1px solid rgba(110,231,183,0.35);
            background: linear-gradient(145deg, rgba(16,185,129,0.28), rgba(6,95,70,0.45));
            color: #ecfdf5;
            font-weight: 800;
            font-size: 1.02rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            -webkit-tap-highlight-color: transparent;
        }
        .promo small {
            display: block;
            margin-top: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            opacity: 0.8;
            word-break: break-all;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 14px 12px;
            margin-bottom: 10px;
        }
        .card h2 {
            margin: 0 0 8px;
            font-size: 0.98rem;
            font-weight: 800;
        }
        .card h2.bow { color: #fbbf24; }
        .card h2.danso { color: #7dd3fc; }
        .card h2.magic { color: #c4b5fd; }
        .card p {
            margin: 0;
            font-size: 0.86rem;
            line-height: 1.6;
            color: var(--text);
        }
        .card p + p { margin-top: 8px; }
        .cmd {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 6px;
            background: rgba(255,255,255,0.08);
            color: var(--gold);
            font-weight: 700;
            font-size: 0.84rem;
        }
        .hint {
            margin-top: 10px;
            font-size: 0.74rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .back {
            display: inline-block;
            margin-top: 12px;
            color: var(--muted);
            font-size: 0.82rem;
            text-decoration: none;
        }
        .note {
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.03);
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>📖 구매한 무기 사용방법</h1>
    <p class="sub">무기는 홍보방에서 사용합니다. 아래에서 각 무기 설명과 홍보방 입장 링크를 확인하세요.</p>

    <a class="promo" href="<?php echo htmlspecialchars($홍보방, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
        📢 홍보방 바로가기
        <small><?php echo htmlspecialchars($홍보방, ENT_QUOTES, 'UTF-8'); ?></small>
    </a>

    <p class="note">홍보방 입장 시 <b style="color:var(--text);">본방 닉 2자리</b>로 들어와 주세요. 시전·보호·공격 명령은 홍보방에서 사용합니다.</p>

    <div class="card">
        <h2 class="bow">🏹 활</h2>
        <p>친구들의 <b>타수</b>를 뺏을 수 있어요.</p>
        <p>타수를 많이 뺏으면 <b>매일 밤 11시</b>에 하는 <b>사다리 이벤트</b>에 참여할 수 있어요.</p>
    </div>

    <div class="card">
        <h2 class="danso">🪈 단소</h2>
        <p>본인 타수를 <b>1타씩</b> 차감하면서 친구들의 <b>냥</b>을 뺏을 수 있어요.</p>
    </div>

    <div class="card">
        <h2 class="magic">🪄 마법</h2>
        <p>
            <span class="cmd">.시전</span>
            — 친구들에게 타수 버프를 줘요.<br>
            기본 타수당 냥이 <b>0.1냥 → 0.2냥</b>으로 약 2배 증가해요.
        </p>
        <p>
            <span class="cmd">.보호</span>
            — 활·단소·보스 공격으로부터 보호해 줘요.<br>
            한도 1회당 <b>강화 ~ 2배</b> (크리 40% 시 <b>~3배</b>). 예: +30 마법 → 일반 30~60 / 크리 30~90.
        </p>
        <p class="hint">마법은 강화 후 홍보방에서 <span class="cmd">.시전 닉</span> · <span class="cmd">.보호 닉</span> 형태로 사용합니다.</p>
    </div>

    <a class="back" href="/page/enchant.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">← 무기 강화로</a>
</div>
</body>
</html>
