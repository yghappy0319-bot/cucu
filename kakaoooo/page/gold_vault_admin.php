<?php
/**
 * 개인금고 전체 예치 내역 — 준호 전용 (친구별 통계)
 * URL: /page/gold_vault_admin.php?code=XXXX
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (!function_exists('냥_정수문자열')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_gold_vault_lib.php';

gv_테이블보장();

$회원 = gv_auth();
$로그인 = (bool)$회원;
$닉 = $로그인 ? $회원['nick'] : '';
$code = $로그인 ? $회원['code'] : '';
$준호 = $로그인 && gv_준호인가($닉);
$filter = trim((string)($_GET['filter'] ?? 'active'));
if (!in_array($filter, ['all', 'active', 'done'], true)) {
    $filter = 'active';
}

$walletQ = $code !== '' ? ('?code=' . rawurlencode($code)) : '';
$vaultQ = $walletQ;
$adminBase = '/page/gold_vault_admin.php' . ($code !== '' ? ('?code=' . rawurlencode($code) . '&') : '?');

$닉통계 = [];
$통계 = null;
if ($준호) {
    $닉통계 = gv_닉별통계($filter);
    $통계 = gv_전체통계();
}

$기간목록 = [3, 5, 7, 14, 30];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>개인금고 전체내역</title>
<link href="/css/style.css" rel="stylesheet">
<style>
:root {
  --ga-ink: #f3e6c8;
  --ga-muted: #a89878;
  --ga-gold2: #f0d78c;
  --ga-ok: #7dba7a;
  --ga-warn: #c47a4a;
  --ga-line: rgba(240, 215, 140, 0.18);
}
* { box-sizing: border-box; }
html, body {
  margin: 0; min-height: 100%;
  background: linear-gradient(165deg, #12151a 0%, #1c2028 45%, #151820 100%);
  color: var(--ga-ink);
  font-family: 'MyFont', 'Apple SD Gothic Neo', sans-serif;
  font-size: 16px;
}
.ga-wrap { max-width: 720px; margin: 0 auto; padding: 16px 14px 80px; }
.ga-top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 16px; }
.ga-brand { margin: 0; font-size: 28px; color: var(--ga-gold2); letter-spacing: -0.03em; }
.ga-sub { margin: 6px 0 0; color: var(--ga-muted); font-size: 14px; }
.ga-links { display: flex; gap: 8px; flex-shrink: 0; }
.ga-back {
  display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 10px;
  border: 1px solid var(--ga-line); background: rgba(255,255,255,0.04);
  color: var(--ga-ink); text-decoration: none; font-size: 13px;
}
.ga-stats {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 14px;
}
@media (min-width: 520px) { .ga-stats { grid-template-columns: repeat(4, 1fr); } }
.ga-stat {
  padding: 12px; border-radius: 12px; background: rgba(0,0,0,0.28);
  border: 1px solid var(--ga-line); text-align: center;
}
.ga-stat b { display: block; font-size: 22px; color: var(--ga-gold2); margin-bottom: 4px; }
.ga-stat span { font-size: 12px; color: var(--ga-muted); }
.ga-tabs { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; }
.ga-tab {
  padding: 8px 12px; border-radius: 999px; border: 1px solid var(--ga-line);
  color: var(--ga-muted); text-decoration: none; font-size: 13px;
  background: rgba(0,0,0,0.2);
}
.ga-tab.on { color: #1a1408; background: linear-gradient(180deg, #f0d78c, #d4a017); border-color: transparent; font-weight: 600; }
.ga-table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid var(--ga-line); background: rgba(0,0,0,0.28); }
table { width: 100%; border-collapse: collapse; min-width: <?php echo $filter === 'active' ? '620px' : '560px'; ?>; }
th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid var(--ga-line); font-size: 13px; }
th { color: var(--ga-muted); font-weight: 500; background: rgba(0,0,0,0.2); position: sticky; top: 0; white-space: nowrap; }
td.nick { color: var(--ga-gold2); font-weight: 600; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
td.num.em { color: var(--ga-gold2); font-weight: 700; font-size: 15px; }
.term-line { color: var(--ga-muted); font-size: 12px; line-height: 1.45; }
.ga-empty, .ga-deny {
  text-align: center; padding: 40px 16px; color: var(--ga-muted);
  border: 1px dashed var(--ga-line); border-radius: 14px;
}
.ga-deny { color: var(--ga-warn); }
.ga-term-sum { margin: 0 0 14px; font-size: 13px; color: var(--ga-muted); line-height: 1.5; }
.ga-rank { color: var(--ga-muted); font-size: 12px; width: 36px; }
</style>
</head>
<body>
<div class="ga-wrap">
  <div class="ga-top">
    <div>
      <h1 class="ga-brand">개인금고 친구별 통계</h1>
      <p class="ga-sub">준호 전용 · 닉네임별 예치 집계</p>
    </div>
    <div class="ga-links">
      <a class="ga-back" href="/page/gold_vault.php<?php echo htmlspecialchars($vaultQ, ENT_QUOTES, 'UTF-8'); ?>">개인금고</a>
      <a class="ga-back" href="/page/gold_vault_payout_all.php<?php echo htmlspecialchars($vaultQ, ENT_QUOTES, 'UTF-8'); ?>">일괄지급</a>
      <a class="ga-back" href="/api/game/wallet_web.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">가방</a>
    </div>
  </div>

<?php if (!$로그인): ?>
  <div class="ga-deny">연구실 코드 링크로 접속해주세요.</div>
<?php elseif (!$준호): ?>
  <div class="ga-deny">준호만 볼 수 있는 페이지예요.</div>
<?php else: ?>

  <div class="ga-stats">
    <div class="ga-stat"><b><?php echo (int)$통계['active']; ?></b><span>예치중 금괴</span></div>
    <div class="ga-stat"><b><?php echo (int)$통계['active_nicks']; ?></b><span>예치 인원</span></div>
    <div class="ga-stat"><b><?php echo (int)$통계['done']; ?></b><span>환전완료</span></div>
    <div class="ga-stat"><b><?php echo (int)$통계['early']; ?></b><span>중도해지</span></div>
  </div>

  <?php if (!empty($통계['by_term'])): ?>
  <p class="ga-term-sum">
    예치중 기간별:
    <?php
      $parts = [];
      foreach ($통계['by_term'] as $d => $c) {
          $parts[] = "{$d}일 {$c}개";
      }
      echo htmlspecialchars(implode(' · ', $parts), ENT_QUOTES, 'UTF-8');
    ?>
    · 합계 <?php echo htmlspecialchars($통계['active_amount_disp'], ENT_QUOTES, 'UTF-8'); ?>
  </p>
  <?php endif; ?>

  <div class="ga-tabs">
    <a class="ga-tab <?php echo $filter === 'active' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($adminBase . 'filter=active', ENT_QUOTES, 'UTF-8'); ?>">예치중</a>
    <a class="ga-tab <?php echo $filter === 'done' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($adminBase . 'filter=done', ENT_QUOTES, 'UTF-8'); ?>">환전완료</a>
    <a class="ga-tab <?php echo $filter === 'all' ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($adminBase . 'filter=all', ENT_QUOTES, 'UTF-8'); ?>">전체</a>
  </div>

  <?php if (empty($닉통계)): ?>
    <div class="ga-empty">집계할 내역이 없어요.</div>
  <?php else: ?>
  <div class="ga-table-wrap">
    <table>
      <thead>
        <tr>
          <th class="ga-rank">#</th>
          <th>닉</th>
          <?php if ($filter === 'active'): ?>
            <th class="num">예치중</th>
            <th>기간별</th>
            <th class="num">합계</th>
            <th class="num">총 이자</th>
            <th class="num">미수령</th>
          <?php elseif ($filter === 'done'): ?>
            <th class="num">만기환전</th>
            <th class="num">중도해지</th>
            <th class="num">합계</th>
            <th class="num">총 이자</th>
          <?php else: ?>
            <th class="num">예치중</th>
            <th class="num">만기</th>
            <th class="num">중도</th>
            <th class="num">합계</th>
            <th class="num">총 이자</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($닉통계 as $i => $r): ?>
        <tr>
          <td class="ga-rank"><?php echo (int)($i + 1); ?></td>
          <td class="nick"><?php echo htmlspecialchars($r['nick'], ENT_QUOTES, 'UTF-8'); ?></td>
          <?php if ($filter === 'active'): ?>
            <td class="num em"><?php echo (int)$r['active']; ?></td>
            <td class="term-line">
              <?php
                $tp = [];
                foreach ($기간목록 as $d) {
                    $c = (int)($r['by_term'][$d] ?? 0);
                    if ($c > 0) {
                        $tp[] = "{$d}일 {$c}";
                    }
                }
                echo $tp ? htmlspecialchars(implode(' · ', $tp), ENT_QUOTES, 'UTF-8') : '—';
              ?>
            </td>
            <td class="num"><?php echo htmlspecialchars($r['active_amount_disp'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num em"><?php echo htmlspecialchars($r['interest_total_disp'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="num"><?php echo htmlspecialchars($r['interest_pending_disp'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
          <?php elseif ($filter === 'done'): ?>
            <td class="num"><?php echo (int)$r['mature']; ?></td>
            <td class="num"><?php echo (int)$r['early']; ?></td>
            <td class="num em"><?php echo (int)$r['done']; ?></td>
            <td class="num em"><?php echo htmlspecialchars($r['interest_total_disp'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
          <?php else: ?>
            <td class="num em"><?php echo (int)$r['active']; ?></td>
            <td class="num"><?php echo (int)$r['mature']; ?></td>
            <td class="num"><?php echo (int)$r['early']; ?></td>
            <td class="num"><?php echo (int)$r['total']; ?></td>
            <td class="num em"><?php echo htmlspecialchars($r['interest_total_disp'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
