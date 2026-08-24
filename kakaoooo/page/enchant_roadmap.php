<?php
/**
 * 무기 강화 · 단계별 확률·비용 표 (공개 · code 불필요)
 * 강화비 = ceil(유효시총 × parts / 1e20) · 확률 = 강화_성공분자/분모
 * (유효시총 = 강화비용_유효시총 · 시세 스냅샷 게임냥 30% 동적 하드캡)
 * (시총 비율 숫자는 표에 넣지 않고, 계산된 게임냥만 표시)
 *
 * URL: /page/enchant_roadmap.php
 */

$__root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') : '';
if ($__root !== '' && is_file($__root . '/lib/_function.php')) {
    include_once $__root . '/lib/_function.php';
}
if ($__root !== '' && is_file($__root . '/api/function.php')) {
    include_once $__root . '/api/function.php';
} else {
    $__fn = dirname(__DIR__) . '/api/function.php';
    if (is_file($__fn)) {
        include_once $__fn;
    }
}

// config.php 사이드이펙트 완화 (공개 표 전용)
$msg = $msg ?? '';
$nick = $nick ?? '';
$두자리닉넴 = $두자리닉넴 ?? '';
if ($__root !== '' && is_file($__root . '/api/config.php')) {
    include_once $__root . '/api/config.php';
} else {
    $__cfg = dirname(__DIR__) . '/api/config.php';
    if (is_file($__cfg)) {
        include_once $__cfg;
    }
}

$확률문구 = static function ($분자, $분모): string {
    $분자 = max(1, (int)$분자);
    $분모 = max(1, (int)$분모);
    if ($분자 > $분모) {
        $분자 = $분모;
    }
    $pct = ($분자 / $분모) * 100;
    $pct_s = rtrim(rtrim(number_format($pct, 6, '.', ''), '0'), '.');
    return number_format($분자) . '/' . number_format($분모) . ' (' . $pct_s . '%)';
};

$비용표시 = static function ($금액): string {
    if (function_exists('강화비용_표시')) {
        return 강화비용_표시($금액, '게임냥');
    }
    if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($금액, '게임냥');
    }
    $s = function_exists('냥_정수문자열') ? 냥_정수문자열($금액) : (string)$금액;
    return (function_exists('냥_숫자콤마') ? 냥_숫자콤마($s) : $s) . '게임냥';
};

$전체게임냥_raw = function_exists('강화비용_전체게임냥') ? 강화비용_전체게임냥() : '0';
$전체게임냥_fmt = function_exists('강화비용_시총표시')
    ? 강화비용_시총표시()
    : $비용표시($전체게임냥_raw);
// 시총표시는 단위가 '냥'일 수 있어 게임냥으로 통일
if (function_exists('강화비용_표시')) {
    $전체게임냥_fmt = 강화비용_표시($전체게임냥_raw, '게임냥');
}
$유효시총_raw = function_exists('강화비용_유효시총') ? 강화비용_유효시총() : $전체게임냥_raw;
$유효시총_fmt = function_exists('강화비용_표시')
    ? 강화비용_표시($유효시총_raw, '게임냥')
    : $비용표시($유효시총_raw);
$시총완만화 = function_exists('강화비용_시총완만화_적용중인가') && 강화비용_시총완만화_적용중인가();

$maxLv = function_exists('강화_최대') ? 강화_최대() : 100;
$rows = [];
for ($lv = 0; $lv < $maxLv; $lv++) {
    $분자 = function_exists('강화_성공분자') ? 강화_성공분자($lv) : 1;
    $분모 = function_exists('강화_성공분모') ? 강화_성공분모($lv) : 1000;
    $분모_은총 = (int)max(1, floor($분모 / 10));

    // 실제 강화 차감과 동일: 강화비용_산출 → 전체게임냥 × 구간비율
    $비용 = function_exists('강화비용_산출')
        ? 강화비용_산출($lv, null, false, false, false)
        : 0;
    $비용_은총 = function_exists('강화비용_산출')
        ? 강화비용_산출($lv, null, false, true, false)
        : 0;

    $방식 = function_exists('강화비용_구간라벨')
        ? 강화비용_구간라벨($lv)
        : ('+' . ((int)floor($lv / 10) * 10) . '~+' . ((int)floor($lv / 10) * 10 + 9) . ' 구간');

    // 해당 강화(+lv) 보유 시 1시간 시전 한도 = 강화 × 2
    $시전한도 = ($lv >= 1) ? ($lv * 2) : 0;
    $시전한도_fmt = $시전한도 > 0 ? (number_format($시전한도) . '회') : '—';

    $rows[] = [
        'from' => $lv,
        'to' => $lv + 1,
        'mode' => $방식,
        'label' => '+' . $lv . ' → +' . ($lv + 1),
        'rate' => $확률문구($분자, $분모),
        'rate_eunchong' => $확률문구($분자, $분모_은총),
        'cost_fmt' => $비용표시($비용),
        'cost_eunchong_fmt' => $비용표시($비용_은총),
        'extra_cast_fmt' => $시전한도_fmt,
        'special' => (($lv % 10) === 9),
    ];
}

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
    <title>무기 강화 · 확률·비용</title>
    <style>
        :root {
            --bg: #14110e;
            --card: rgba(36, 28, 22, 0.94);
            --ember: #f0a060;
            --gold: #e8c56b;
            --text: #f7efe4;
            --muted: rgba(247,239,228,0.58);
            --line: rgba(240,160,96,0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:
                radial-gradient(ellipse at 80% 0%, rgba(240,160,96,0.14) 0%, transparent 48%),
                radial-gradient(ellipse at 10% 100%, rgba(232,197,107,0.08) 0%, transparent 42%),
                var(--bg);
            color: var(--text);
            padding: 16px 14px 28px;
        }
        .wrap { max-width: 860px; margin: 0 auto; }
        h1 { font-size: 1.1rem; margin: 0 0 8px; }
        .sub {
            font-size: 0.76rem;
            color: var(--muted);
            line-height: 1.55;
            margin-bottom: 12px;
        }
        .live {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(240,160,96,0.08);
            font-size: 0.78rem;
            line-height: 1.45;
        }
        .live b { color: var(--gold); font-weight: 700; }
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
            min-width: 560px;
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
        tr.special td { background: rgba(240,160,96,0.08); }
        tr.special .step { color: #ffb070; }
        .step {
            font-weight: 700;
            white-space: nowrap;
            color: var(--ember);
        }
        .mode {
            font-size: 0.66rem;
            color: var(--muted);
            font-weight: 600;
        }
        .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cost { color: var(--gold); font-weight: 700; }
        .rate { color: var(--text); }
        .muted-cell { color: var(--muted); font-size: 0.72rem; }
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
    <h1>⚔️ 무기 강화 · 확률·비용</h1>
    <p class="sub">
        강화비 = ceil(유효시총 × 구간비율) → <b style="color:var(--gold);">게임냥</b> 차감 ·
        시총 하드캡(시세 스냅샷 게임냥의 30% · `.스냅샷` 갱신 시 함께 반영) ·
        보통 구간 안 +10% · +30~39 / +60~69 정액 계단 · 구간진입(+9/+19/…) ×3 ·
        은총·메가은총 시 강화비 할인 없음 (은총: 성공분모 1/10 · 메가: 1/100)<br>
        시전 한도: <b style="color:var(--gold);">1시간당 강화 × 2</b> (한도를 다 안 써도 1시간마다 초기화 · 한도 외 시전 없음 · 마법은 시전+보호 공용으로 그 2배)
    </p>
    <div class="live">
        <span>기준 시세 스냅샷 게임냥 <b><?php echo htmlspecialchars($전체게임냥_fmt, ENT_QUOTES, 'UTF-8'); ?></b></span>
        <span>강화비용 유효시총 <b><?php echo htmlspecialchars($유효시총_fmt, ENT_QUOTES, 'UTF-8'); ?></b></span>
        <span>표시 금액 = 유효시총 비율 계산 결과(게임냥)</span>
        <?php if (function_exists('강화비용_시총_폴백인가') && 강화비용_시총_폴백인가()) { ?>
        <span style="color:#ff8a8a;">⚠ 시세 스냅샷 미확인 → 설계총량 기준</span>
        <?php } else { ?>
        <span>동적 하드캡 적용 중 · 유효시총 = 스냅샷 게임냥 × 30%</span>
        <?php } ?>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>단계</th>
                        <th>성공 확률</th>
                        <th>강화비 (게임냥)</th>
                        <th>1시간 시전</th>
                        <th>은총 확률</th>
                        <th>은총 강화비</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                    <tr<?php echo !empty($row['special']) ? ' class="special"' : ''; ?>>
                        <td>
                            <div class="step"><?php echo htmlspecialchars($row['label'] ?? ('+' . $row['from'] . ' → +' . $row['to']), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="mode"><?php echo htmlspecialchars($row['mode'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td class="num rate"><?php echo htmlspecialchars($row['rate'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num cost"><?php echo htmlspecialchars($row['cost_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo htmlspecialchars($row['extra_cast_fmt'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num muted-cell"><?php echo htmlspecialchars($row['rate_eunchong'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num muted-cell"><?php echo htmlspecialchars($row['cost_eunchong_fmt'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="nav-links">
        <a class="back" href="/page/enchant.php<?php echo htmlspecialchars($back_q, ENT_QUOTES, 'UTF-8'); ?>">← 무기 강화로</a>
    </div>
</div>
</body>
</html>
