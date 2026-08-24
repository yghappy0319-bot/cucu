<?php
/**
 * 채굴 장비 로드맵 — 24시간 획득량 표 (공개 · code 불필요)
 *
 * URL: /page/mining_roadmap.php
 */

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_tool.inc.php';

$yield_rows = mining_tool_roadmap_yield_table();
$max_daily_fmt = mining_tool_daily_yield_fmt(mining_tool_max_level());
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
    <title>채굴 장비 · 24시간 획득량</title>
    <style>
        :root {
            --bg: #0b1a14;
            --card: rgba(12, 32, 24, 0.92);
            --mint: #6ee7b7;
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
                radial-gradient(ellipse at 20% 0%, rgba(110,231,183,0.12) 0%, transparent 50%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.1rem; margin: 0 0 8px; }
        .sub {
            font-size: 0.76rem;
            color: var(--muted);
            line-height: 1.5;
            margin-bottom: 14px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 10px;
        }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 480px;
        }
        th, td {
            padding: 9px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }
        th {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.66rem;
            white-space: nowrap;
        }
        tr:last-child td { border-bottom: none; }
        .lv { color: var(--muted); font-size: 0.66rem; font-weight: 700; white-space: nowrap; }
        .tool-cell { display: flex; align-items: center; gap: 6px; }
        .tool-icon { font-size: 1.15rem; }
        .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .daily { color: var(--mint); font-weight: 700; }
        .back {
            display: inline-block;
            margin-top: 14px;
            color: var(--muted);
            font-size: 0.8rem;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🥄 장비별 24시간 획득량</h1>
    <p class="sub">오프라인 포함 · Lv0~2 0.001% · Lv3~5 0.0001% · Lv6~9 0.00001% · Lv10~12 0.0000001% · Lv13~14 0.00000001%</p>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>장비</th>
                        <th>초당</th>
                        <th>24시간</th>
                        <th>다음 강화비</th>
                        <th>강화 확률</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yield_rows as $row) { ?>
                    <tr>
                        <td>
                            <div class="tool-cell">
                                <span class="lv">Lv<?php echo (int)$row['level']; ?></span>
                                <span class="tool-icon"><?php echo $row['icon']; ?></span>
                                <span><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </td>
                        <td class="num">+<?php echo htmlspecialchars($row['rate_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥/초</td>
                        <td class="num daily"><?php echo htmlspecialchars($row['daily_yield_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</td>
                        <td class="num"><?php echo htmlspecialchars($row['upgrade_cost_fmt'], ENT_QUOTES, 'UTF-8'); ?><?php echo $row['upgrade_cost'] !== null ? '냥' : ''; ?></td>
                        <td class="num"><?php echo htmlspecialchars($row['upgrade_success_pct'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="nav-links">
        <a class="back" href="/page/mining.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">← 채굴로</a>
        <a class="back" href="/page/mining_weapon_bonus.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">무기 결합 추가 획득</a>
    </div>
</div>
</body>
</html>
