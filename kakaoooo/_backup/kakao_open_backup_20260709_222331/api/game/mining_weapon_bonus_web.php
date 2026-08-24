<?php
/**
 * 무기 +10~+20 결합 · 광물 추가 획득량 표 (공개 · code 불필요)
 *
 * URL: /page/mining_weapon_bonus.php
 */

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_ore.inc.php';

$page = mining_ore_weapon_bonus_page_data();
$table = $page['summary'];
$matrix = $page['drop_matrix'];
$back_q = '';
if (!empty($_GET['code'])) {
    $back_q = '?code=' . rawurlencode(trim((string)$_GET['code']));
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>무기 결합 · 추가 획득량</title>
    <style>
        :root {
            --bg: #0b1a14;
            --card: rgba(12, 32, 24, 0.92);
            --mint: #6ee7b7;
            --gold: #fbbf24;
            --text: #ecfdf5;
            --muted: rgba(236,253,245,0.62);
            --line: rgba(110,231,183,0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:
                radial-gradient(ellipse at 80% 0%, rgba(251,191,36,0.1) 0%, transparent 45%),
                radial-gradient(ellipse at 20% 0%, rgba(110,231,183,0.12) 0%, transparent 50%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 860px; margin: 0 auto; }
        h1 { font-size: 1.1rem; margin: 0 0 8px; }
        h2 {
            font-size: 0.82rem;
            margin: 0 0 10px;
            color: var(--muted);
            font-weight: 600;
        }
        .sub {
            font-size: 0.76rem;
            color: var(--muted);
            line-height: 1.55;
            margin-bottom: 14px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 10px;
            margin-bottom: 12px;
        }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.76rem;
            min-width: 420px;
        }
        table.matrix { min-width: 640px; }
        th, td {
            padding: 8px 7px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }
        th {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.64rem;
            white-space: nowrap;
        }
        th.ore-head {
            text-align: center;
            line-height: 1.35;
        }
        th .ore-touch {
            display: block;
            font-weight: 500;
            font-size: 0.58rem;
            color: rgba(236,253,245,0.45);
        }
        tr:last-child td { border-bottom: none; }
        .enh { font-weight: 700; white-space: nowrap; }
        .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pct { text-align: center; font-variant-numeric: tabular-nums; }
        .pct.on { color: var(--text); }
        .pct.off { color: rgba(236,253,245,0.22); }
        .bonus { color: var(--gold); font-weight: 700; }
        .ore-ref {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            font-size: 0.72rem;
        }
        .ore-ref span {
            background: rgba(110,231,183,0.08);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 6px 9px;
            white-space: nowrap;
        }
        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
            margin-top: 14px;
            font-size: 0.8rem;
        }
        .nav-links a {
            color: var(--muted);
            text-decoration: none;
        }
        .legend {
            font-size: 0.7rem;
            color: var(--muted);
            line-height: 1.55;
            margin-top: 8px;
        }
        .legend li { margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>⚔️ 무기 결합 · 추가 획득량</h1>
    <p class="sub">
        무기 <strong>+10 이상</strong> 장착 후 채굴 시, <strong>1~60분 랜덤 시점</strong>에 강화별 광물이 생성됩니다. (시간당 개수는 아래 표 + 채굴장비 Lv 보너스)<br>
        채굴 장비 Lv 보너스: <strong>Lv2 +1 · Lv5 +2 · Lv9 +3 · Lv12 +4 · Lv13 +5</strong> (60분당, 무기 기본에 추가)
    </p>

    <div class="card">
        <h2>광물 목록 · 터치 시 가치</h2>
        <div class="ore-ref">
            <?php foreach ($matrix['catalog'] as $ore) { ?>
            <span><?php echo $ore['icon']; ?> <?php echo htmlspecialchars($ore['label'], ENT_QUOTES, 'UTF-8'); ?>
                <?php echo htmlspecialchars($ore['sell_value_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php } ?>
        </div>
    </div>

    <div class="card">
        <h2>60분·24h 추가 획득</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>무기</th>
                        <th>60분</th>
                        <th>24h</th>
                        <th>1개당 기대</th>
                        <th>60분 추가</th>
                        <th>24h 추가</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table['rows'] as $row) { ?>
                    <tr>
                        <td class="enh num">+<?php echo (int)$row['enhance']; ?></td>
                        <td class="num"><?php echo (int)$row['hourly_count']; ?>개</td>
                        <td class="num"><?php echo (int)$row['hourly_count'] * 24; ?>개</td>
                        <td class="num">+<?php echo htmlspecialchars($row['avg_find_value_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</td>
                        <td class="num bonus">+<?php echo htmlspecialchars($row['hourly_bonus_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</td>
                        <td class="num bonus">+<?php echo htmlspecialchars($row['daily_bonus_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <ul class="legend">
            <li>+10~13: 시간당 1개 · +14~16: 2개 · +17~19: 3개 · +20: 5개 (각각 1~60분 랜덤)</li>
            <li>채굴 장비 Lv2 +1 · Lv5 +2 · Lv9 +3 · Lv12 +4 · Lv13 +5 (60분당, 위 개수에 추가)</li>
            <li>1개당 기대: 해당 강화에서 <strong>광물 1개</strong> 뽑았을 때 평균 냥 가치</li>
        </ul>
    </div>

    <div class="card">
        <h2>강화별 · 광물 종류 확률</h2>
        <p class="sub" style="margin-top:0;margin-bottom:10px;">시간당 뜨는 광물 <strong>1개</strong>가 아래 비율로 결정됩니다. (강화 올릴수록 고급 광물 해금)</p>
        <div class="table-wrap">
            <table class="matrix">
                <thead>
                    <tr>
                        <th>무기</th>
                        <th>60분</th>
                        <?php foreach ($matrix['catalog'] as $ore) { ?>
                        <th class="ore-head">
                            <?php echo $ore['icon']; ?><br><?php echo htmlspecialchars($ore['label'], ENT_QUOTES, 'UTF-8'); ?>
                            <span class="ore-touch"><?php echo htmlspecialchars($ore['sell_value_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matrix['rows'] as $row) { ?>
                    <tr>
                        <td class="enh num">+<?php echo (int)$row['enhance']; ?></td>
                        <td class="num"><?php echo (int)$row['hourly_count']; ?>개</td>
                        <?php foreach ($matrix['catalog'] as $ore) {
                            $cell = $row['cells'][$ore['key']] ?? ['pct_fmt' => '—', 'unlocked' => false];
                        ?>
                        <td class="pct <?php echo !empty($cell['unlocked']) ? 'on' : 'off'; ?>"><?php echo htmlspecialchars($cell['pct_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <ul class="legend">
            <li>— : 해당 강화에서 아직 해금되지 않은 광물</li>
            <li>✨ 은총조각 10개 → 은총 1개 (채굴 강화 확률 보조)</li>
            <li>발견 후 1시간 내 터치하지 않으면 소멸</li>
        </ul>
    </div>

    <div class="nav-links">
        <a href="/page/mining.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">← 채굴로</a>
        <a href="/page/mining_roadmap.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">장비별 24h 획득량</a>
    </div>
</div>
</body>
</html>
