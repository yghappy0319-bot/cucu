<?php
/**
 * 냥카라 게임방법
 * URL: /page/nyangkara_guide.php?code=XXXX
 */
require __DIR__ . '/_wallet_preauth.php';
require_once __DIR__ . '/../api/game/wallet_subpage.inc.php';

$boot = wallet_subpage_boot();
$code = (string)($boot['code'] ?? '');
$q = (string)($boot['q'] ?? '');
$playHref = '/page/nyangkara.php' . $q;
$bagHref = '/page/wallet.php' . $q;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0b1a12">
    <title>냥카라 게임방법</title>
    <style>
        :root {
            --bg: #0b1a12;
            --card: #10281c;
            --text: #f4efe4;
            --muted: rgba(244,239,228,0.62);
            --line: rgba(232,200,114,0.22);
            --gold: #e8c872;
            --player: #7dd3fc;
            --banker: #fca5a5;
            --tie: #86efac;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans KR', sans-serif;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(232,200,114,0.12) 0%, transparent 42%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 36px;
        }
        .wrap { max-width: 520px; margin: 0 auto; }
        h1 { font-size: 1.2rem; margin: 0 0 6px; }
        .sub { font-size: 0.78rem; color: var(--muted); margin: 0 0 16px; line-height: 1.5; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 14px 12px;
            margin-bottom: 10px;
        }
        .card h2 {
            margin: 0 0 8px;
            font-size: 0.92rem;
            color: var(--gold);
        }
        .card p, .card li {
            margin: 0 0 6px;
            font-size: 0.84rem;
            line-height: 1.55;
            color: var(--text);
        }
        .card ul { margin: 0; padding-left: 1.15em; }
        .odds {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-top: 8px;
        }
        .odd {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 6px;
            text-align: center;
        }
        .odd b { display: block; font-size: 0.8rem; margin-bottom: 2px; }
        .odd span { font-size: 0.92rem; font-weight: 800; }
        .odd.P { color: var(--player); }
        .odd.B { color: var(--banker); }
        .odd.T { color: var(--tie); }
        .note {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 8px;
        }
        .btn {
            display: block;
            text-align: center;
            text-decoration: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 800;
            font-size: 0.92rem;
            background: linear-gradient(145deg, #e8c872, #c9a227);
            color: #1a1406;
            margin-top: 8px;
        }
        .back {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.84rem;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🃏 냥카라 게임방법</h1>
    <p class="sub">게임냥으로 플레이어·뱅커·타이에 여러 번 배팅할 수 있는 실시간 테이블입니다.</p>

    <div class="card">
        <h2>1. 입장</h2>
        <ul>
            <li>가방에서 <b>냥카라 입장</b>을 누르거나, 같은 코드 링크로 들어옵니다.</li>
            <li><b>자리 앉기</b>를 누르면 테이블에 앉습니다. 한 테이블 최대 8명.</li>
            <li>혼자 앉아도 바로 시작 · 친구들과 같은 판을 같이 봅니다.</li>
        </ul>
    </div>

    <div class="card">
        <h2>2. 배팅</h2>
        <ul>
            <li>배팅 시간(약 12초) 안에 쪽과 금액을 고르고 <b>배팅하기</b>를 누릅니다.</li>
            <li>플레이어·뱅커·타이 어디에든, 가진 냥이 되는 만큼 <b>여러 번 더 넣을</b> 수 있어요. 같은 쪽에 넣으면 금액이 합쳐집니다.</li>
            <li>배팅하면 게임냥이 바로 차감됩니다. 한 번에 최소 1,000 게임냥입니다.</li>
        </ul>
        <div class="odds">
            <div class="odd P"><b>플레이어</b><span>1배</span></div>
            <div class="odd B"><b>뱅커</b><span>1배</span></div>
            <div class="odd T"><b>타이</b><span>8배</span></div>
        </div>
        <p class="note">1배 = 건 만큼 더 받음 (본전+당첨). 8배 = 건 금액의 8배를 추가로 받음.</p>
    </div>

    <div class="card">
        <h2>3. 승패</h2>
        <ul>
            <li>배팅이 끝나면 슈에서 패가 한 장씩 나옵니다. 플레이어 2장, 뱅커 2장.</li>
            <li>카드 값: A=1 · 2~9=숫자 그대로 · 10·J·Q·K=0. 합의 <b>끝자리(10으로 나눈 나머지)</b>가 점수입니다.</li>
            <li>점수가 더 높은 쪽이 이깁니다. 같으면 <b>타이</b>.</li>
            <li><b>플레이어와 뱅커에 둘 다 배팅이 있으면</b> 배팅 합이 더 큰 쪽이 <b>59%</b>, 적은 쪽이 <b>41%</b>로 이깁니다. (금액이 같으면 50:50)</li>
            <li>8 또는 9가 나오면 내추럴(자연패)이라 더 받지 않습니다. 그 외에는 규칙에 따라 3번째 패가 나올 수 있습니다.</li>
        </ul>
    </div>

    <div class="card">
        <h2>4. 정산</h2>
        <ul>
            <li><b>맞히면</b> 배율대로 지급됩니다. (플레이어/뱅커 1배, 타이 8배) 여러 쪽에 넣었으면 쪽마다 따로 정산됩니다.</li>
            <li><b>타이가 났는데 플레이어·뱅커에 걸었으면</b> 본전만 환급됩니다. (이기지도 지지도 않음)</li>
            <li>틀리면 건 금액은 사라집니다.</li>
            <li>맞혀서 냥을 딴 뒤에는 <b>뽀찌주기</b>로, 지금 앉아 있는 친구 전원에게 딴 금액의 <b>5% · 10%</b> 중 하나를 나눠 줄 수 있어요. 한 판에 한 번.</li>
            <li>정산이 끝나면 다음 라운드 배팅이 이어집니다.</li>
        </ul>
    </div>

    <div class="card">
        <h2>5. 알아두면 좋아요</h2>
        <ul>
            <li>카드는 플레이어/뱅커 패이지, 내 손패가 아닙니다. 모두 같은 결과에 배팅합니다.</li>
            <li>이미 배팅한 라운드에는 자리에서 일어날 수 없어요. 결과가 난 뒤 일어나기.</li>
            <li>화면을 오래 끄면 자리에서 자동으로 내려갑니다.</li>
            <li>냥카라는 게임냥을 씁니다. 본방냥이 아닙니다.</li>
        </ul>
    </div>

    <a class="btn" href="<?php echo htmlspecialchars($playHref, ENT_QUOTES, 'UTF-8'); ?>">냥카라 입장</a>
    <a class="back" href="<?php echo htmlspecialchars($bagHref, ENT_QUOTES, 'UTF-8'); ?>">← 가방으로</a>
</div>
</body>
</html>
