<?php
/**
 * 채굴 장비 로드맵 — 24시간 획득량 표 (공개 · code 불필요)
 *
 * URL: /page/mining_roadmap.php
 */

// 본방냥 총합(newpoint) 조회용 DB·헬퍼
$__mining_root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') : '';
if ($__mining_root !== '' && is_file($__mining_root . '/lib/_function.php')) {
    include_once $__mining_root . '/lib/_function.php';
}
if ($__mining_root !== '' && is_file($__mining_root . '/api/function.php')) {
    include_once $__mining_root . '/api/function.php';
} else {
    $__fn = dirname(__DIR__) . '/function.php';
    if (is_file($__fn)) {
        include_once $__fn;
    }
}

require_once __DIR__ . '/mining_config.inc.php';
require_once __DIR__ . '/mining_tool.inc.php';

$yield_rows = mining_tool_roadmap_yield_table();
$max_daily_fmt = mining_tool_daily_yield_fmt(mining_tool_max_level());
$np_total_str = function_exists('mining_tool_golden_np_total_str')
    ? mining_tool_golden_np_total_str()
    : '0';
$np_base_pct_str = function_exists('mining_tool_base_pct_amount_str')
    ? mining_tool_base_pct_amount_str()
    : '0';
$np_base_pct_label = function_exists('mining_tool_base_pct_label')
    ? mining_tool_base_pct_label()
    : '0.3%';
$fmt_nyang = static function ($s) {
    $s = preg_replace('/[^\d]/', '', (string)$s);
    if ($s === '' || $s === '0') {
        return '0';
    }
    if (function_exists('냥_숫자콤마')) {
        return 냥_숫자콤마($s);
    }
    if (function_exists('mining_yield_amount_fmt')) {
        return mining_yield_amount_fmt($s);
    }
    if (strlen($s) > 15) {
        return $s;
    }
    return number_format((float)$s);
};
$np_total_fmt = $fmt_nyang($np_total_str);
$np_base_pct_fmt = $fmt_nyang($np_base_pct_str);
$back_q = '';
if (!empty($_GET['code'])) {
    $back_q = '?code=' . rawurlencode(trim((string)$_GET['code']));
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <title>채굴 장비 · 24시간 획득량</title>
    <style>
        :root {
            --bg: #0b1a14;
            --card: rgba(12, 32, 24, 0.92);
            --mint: #6ee7b7;
            --text: #ecfdf5;
            --muted: rgba(236,253,245,0.62);
            --line: rgba(110,231,183,0.16);
            --tip-bg: #0f241c;
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
        .np-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        @media (max-width: 520px) {
            .np-summary { grid-template-columns: 1fr; }
        }
        .np-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .np-box .np-label {
            font-size: 0.68rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .np-box .np-value {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--mint);
            font-variant-numeric: tabular-nums;
            word-break: break-all;
            line-height: 1.35;
        }
        .np-box .np-value.muted { color: var(--text); font-weight: 700; font-size: 0.88rem; }
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
        .tool-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }
        .tool-tip-trigger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
            text-align: left;
        }
        .tool-icon { font-size: 1.15rem; }
        .tip-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 1px solid rgba(110,231,183,0.45);
            color: var(--mint);
            font-size: 0.62rem;
            font-weight: 800;
            flex-shrink: 0;
            opacity: 0.9;
        }
        .tool-tip-trigger[aria-expanded="true"] .tip-mark,
        .tool-cell.open .tip-mark {
            background: rgba(110,231,183,0.18);
        }
        .tool-tip {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 6px);
            z-index: 20;
            min-width: 220px;
            max-width: min(280px, 78vw);
            padding: 10px 11px;
            border-radius: 12px;
            background: var(--tip-bg);
            border: 1px solid rgba(110,231,183,0.35);
            box-shadow: 0 12px 28px rgba(0,0,0,0.45);
        }
        .tool-cell.open .tool-tip { display: block; }
        .tool-tip-title {
            font-size: 0.68rem;
            color: var(--mint);
            font-weight: 800;
            margin-bottom: 7px;
        }
        .tool-tip-note {
            font-size: 0.62rem;
            color: var(--muted);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .tool-tip-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.7rem;
            line-height: 1.45;
            padding: 3px 0;
            border-top: 1px solid rgba(110,231,183,0.1);
        }
        .tool-tip-row:first-of-type { border-top: 0; }
        .tool-tip-row .t-label { color: var(--muted); white-space: nowrap; }
        .tool-tip-row .t-mult {
            color: var(--text);
            font-variant-numeric: tabular-nums;
            min-width: 2.4em;
            text-align: right;
        }
        .tool-tip-row .t-daily {
            color: var(--mint);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
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
    <p class="sub">오프라인 포함 · 전 장비 24h = 본방냥 × 장비별 비율 × 오늘 생타 배율 · 강화비는 오늘 생타 할인(300+1% ~ 2000+20%) 적용 · 장비명 옆 <b style="color:var(--mint)">?</b> 로 타수별 하루 예상·강화비 확인</p>
    <div class="np-summary">
        <div class="np-box">
            <div class="np-label">본방냥 총합 (status=0 · 실시간)</div>
            <div class="np-value muted"><?php echo htmlspecialchars($np_total_fmt, ENT_QUOTES, 'UTF-8'); ?>냥</div>
        </div>
        <div class="np-box">
            <div class="np-label">기본 <?php echo htmlspecialchars($np_base_pct_label, ENT_QUOTES, 'UTF-8'); ?> (소요시간 환산 기준)</div>
            <div class="np-value"><?php echo htmlspecialchars($np_base_pct_fmt, ENT_QUOTES, 'UTF-8'); ?>냥</div>
        </div>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>장비</th>
                        <th>0.3% 소요</th>
                        <th>초당</th>
                        <th>24시간</th>
                        <th>다음 강화비</th>
                        <th>강화 확률</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yield_rows as $row) {
                        $tiers = is_array($row['chat_tiers'] ?? null) ? $row['chat_tiers'] : [];
                        $tip_id = 'tip-lv' . (int)$row['level'];
                    ?>
                    <tr>
                        <td>
                            <div class="tool-cell" data-tip-cell>
                                <button type="button" class="tool-tip-trigger" aria-expanded="false" aria-controls="<?php echo htmlspecialchars($tip_id, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="lv">Lv<?php echo (int)$row['level']; ?></span>
                                    <span class="tool-icon"><?php echo $row['icon']; ?></span>
                                    <span><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="tip-mark" aria-hidden="true">?</span>
                                </button>
                                <div class="tool-tip" id="<?php echo htmlspecialchars($tip_id, ENT_QUOTES, 'UTF-8'); ?>" role="tooltip">
                                    <div class="tool-tip-title">오늘 생타별 · 하루 예상</div>
                                    <div class="tool-tip-note">은총 미적용 · 내구 유지 가정</div>
                                    <?php if (empty($tiers)) { ?>
                                    <div class="tool-tip-row">
                                        <span class="t-label">기준(×1)</span>
                                        <span class="t-daily"><?php echo htmlspecialchars((string)$row['daily_yield_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</span>
                                    </div>
                                    <?php } else {
                                        foreach ($tiers as $tier) { ?>
                                    <div class="tool-tip-row">
                                        <span class="t-label"><?php echo htmlspecialchars((string)$tier['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="t-mult"><?php echo htmlspecialchars((string)$tier['mult_fmt'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="t-daily"><?php echo htmlspecialchars((string)$tier['daily_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</span>
                                    </div>
                                        <?php }
                                    }
                                    $cost_tiers = is_array($row['cost_discount_tiers'] ?? null) ? $row['cost_discount_tiers'] : [];
                                    if (!empty($cost_tiers)) { ?>
                                    <div class="tool-tip-title" style="margin-top:10px;">오늘 생타별 · 강화비</div>
                                    <div class="tool-tip-note">기본 강화비 대비 할인 (은총 별도)</div>
                                    <?php foreach ($cost_tiers as $ct) { ?>
                                    <div class="tool-tip-row">
                                        <span class="t-label"><?php echo htmlspecialchars((string)$ct['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="t-mult"><?php
                                            $pct = (int)($ct['discount_pct'] ?? 0);
                                            echo $pct > 0 ? ('−' . $pct . '%') : '할인없음';
                                        ?></span>
                                        <span class="t-daily"><?php echo htmlspecialchars((string)$ct['cost_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥</span>
                                    </div>
                                    <?php }
                                    } ?>
                                </div>
                            </div>
                        </td>
                        <td class="num"><?php echo htmlspecialchars((string)($row['time_to_1pct_fmt'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num">+<?php echo htmlspecialchars($row['rate_fmt'], ENT_QUOTES, 'UTF-8'); ?>냥/초</td>
                        <td class="num daily"><?php
                            echo htmlspecialchars((string)$row['daily_yield_fmt'], ENT_QUOTES, 'UTF-8');
                        ?>냥</td>
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
<script>
(function () {
    var cells = document.querySelectorAll('[data-tip-cell]');
    function closeAll(except) {
        cells.forEach(function (cell) {
            if (except && cell === except) return;
            cell.classList.remove('open');
            var btn = cell.querySelector('.tool-tip-trigger');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }
    cells.forEach(function (cell) {
        var btn = cell.querySelector('.tool-tip-trigger');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = cell.classList.contains('open');
            closeAll();
            if (!open) {
                cell.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
    document.addEventListener('click', function () { closeAll(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
    });
})();
</script>
</body>
</html>
