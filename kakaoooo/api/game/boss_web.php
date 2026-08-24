<?php
/**
 * 방 보스 웹 — /page/boss.php?code=XXXX
 * 참가: 무기 +1↑ · 무기 강화별 횟수 · 채굴 장비레벨만큼 · 제한 30분 · 처치/실패 후 3~5시간
 *
 * HTML 셸: wallet_web / function.php / boss_raid 미로드 (preauth 있으면)
 * AJAX state: boss_raid만 · 공격 등 쓰기 액션만 function.php
 */
if (!defined('WALLET_LIB_ONLY')) {
    define('WALLET_LIB_ONLY', true);
}
if (!defined('WALLET_CODE_COOKIE_NAME')) {
    define('WALLET_CODE_COOKIE_NAME', 'wallet_code');
    define('WALLET_CODE_COOKIE_TTL', 7 * 86400);
}

function boss_web_json(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function boss_web_ensure_db(): void {
    if (function_exists('db_select')) {
        return;
    }
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($root !== '' && is_file($root . '/lib/_function.php')) {
        include_once $root . '/lib/_function.php';
    }
}

function boss_web_set_code_cookie(string $code): void {
    $code = trim($code);
    if ($code === '') {
        return;
    }
    if (function_exists('wallet_코드_쿠키_저장')) {
        wallet_코드_쿠키_저장($code);
        return;
    }
    @setcookie(WALLET_CODE_COOKIE_NAME, $code, time() + WALLET_CODE_COOKIE_TTL, '/', '', false, true);
}

$action = trim((string)($_REQUEST['action'] ?? ''));
// preauth( page/boss.php ) 있으면 wallet_web(3500줄) 파싱 생략
if (empty($GLOBALS['wallet_preauth']['code'])) {
    require_once __DIR__ . '/wallet_web.php';
}

$boot = [
    'code' => '',
    'need_code' => true,
    'member' => null,
    'nick' => '',
    'q' => '',
];

$code = '';
if (!empty($GLOBALS['wallet_preauth']['code'])) {
    $code = (string)$GLOBALS['wallet_preauth']['code'];
} else {
    $code = function_exists('wallet_코드_해석') ? wallet_코드_해석() : '';
}

if ($code !== '') {
    boss_web_ensure_db();
    // HTML 셸: enrich/member_payload 생략
    $row = null;
    if (!empty($GLOBALS['wallet_preauth']['row'])) {
        $row = $GLOBALS['wallet_preauth']['row'];
    } elseif (function_exists('wallet_game_auth_row')) {
        $row = wallet_game_auth_row($code);
    } else {
        $esc = addslashes($code);
        $row = @db_select("SELECT idx, name, CAST(point AS CHAR) AS point FROM tb_member WHERE code = '{$esc}' LIMIT 1");
        if (empty($row['name'])) {
            $row = null;
        }
    }
    if ($row) {
        $nick = trim((string)($row['name'] ?? ''));
        if ($nick !== '' && function_exists('getTwoCharNick')) {
            $parsed = getTwoCharNick($nick);
            if ($parsed !== '') {
                $nick = $parsed;
            }
        } elseif ($nick !== '' && function_exists('wallet_nick_from_row')) {
            $nick = wallet_nick_from_row($row);
        }
        boss_web_set_code_cookie($code);
        $boot['code'] = $code;
        $boot['need_code'] = false;
        $boot['nick'] = $nick;
        $boot['member'] = ['nick' => $nick];
        $boot['q'] = function_exists('wallet_code_query') ? wallet_code_query($code) : ('?code=' . rawurlencode($code));
    }
}

if ($action !== '') {
    require_once __DIR__ . '/boss_raid.inc.php';
    // state 조회는 function.php(1만줄+) 불필요 · 공격/조각만 로드
    if ($action !== 'state') {
        if (function_exists('wallet_odd_even_includes')) {
            wallet_odd_even_includes();
        } elseif (!function_exists('getTwoCharNick')) {
            $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            if ($root !== '') {
                include_once $root . '/lib/_function.php';
                if (is_file($root . '/api/function.php')) {
                    include_once $root . '/api/function.php';
                }
            }
        }
    }
    if (!empty($boot['need_code']) || $boot['nick'] === '') {
        boss_web_json(['ok' => false, 'data' => '로그인이 필요해요.']);
    }
    $nick = $boot['nick'];
    if (!boss_raid_접근가능($nick)) {
        boss_web_json(['ok' => false, 'data' => '접근할 수 없어요.']);
    }
    if ($action === 'state') {
        $lite = isset($_REQUEST['lite']) && (string)$_REQUEST['lite'] === '1';
        boss_web_json(['ok' => true, 'state' => boss_raid_상태_페이로드($nick, ['lite' => $lite])]);
    }
    if ($action === 'attack') {
        $type = trim((string)($_REQUEST['type'] ?? ''));
        $result = boss_raid_공격($nick, $type);
        boss_web_json($result);
    }
    if ($action === 'protect') {
        $result = boss_raid_파티보호($nick);
        boss_web_json($result);
    }
    if ($action === 'shard_weapon') {
        $result = boss_raid_은총조각_횟수충전($nick, 'weapon');
        boss_web_json($result);
    }
    if ($action === 'shard_mining') {
        $result = boss_raid_은총조각_횟수충전($nick, 'mining');
        boss_web_json($result);
    }
    if ($action === 'shard_finish') {
        $result = boss_raid_은총조각_횟수충전($nick, 'finish');
        boss_web_json($result);
    }
    if ($action === 'shard_repair') {
        $result = boss_raid_채굴은총수리($nick);
        boss_web_json($result);
    }
    if ($action === 'reset') {
        $result = boss_raid_리셋($nick);
        boss_web_json($result);
    }
    boss_web_json(['ok' => false, 'data' => '알 수 없는 요청이에요.']);
}

$allowed = !$boot['need_code'] && trim((string)$boot['nick']) !== '';
// HTML에 lite state 동봉 → JS/추가 AJAX 전에 즉시 표시
$bootState = null;
if ($allowed) {
    require_once __DIR__ . '/boss_raid.inc.php';
    try {
        $bootState = boss_raid_상태_페이로드((string)$boot['nick'], ['lite' => true]);
    } catch (Throwable $e) {
        $bootState = null;
    }
}
if (!is_array($bootState)) {
    $bootState = [];
}
$walletQ = $boot['q'] !== '' ? $boot['q'] : '';
$codeJs = json_encode($boot['code'], JSON_UNESCAPED_UNICODE);
$nickJs = json_encode($boot['nick'], JSON_UNESCAPED_UNICODE);
$bootStateJs = json_encode($bootState === [] ? null : $bootState, JSON_UNESCAPED_UNICODE);
if ($bootStateJs === false) {
    $bootStateJs = 'null';
}
$isJunho = $allowed && function_exists('boss_raid_준호인가') && boss_raid_준호인가((string)$boot['nick']);
$titleBoss = '보스';
if (is_array($bootState) && !empty($bootState['name'])) {
    if (($bootState['phase'] ?? '') === 'waiting') {
        $titleBoss = !empty($bootState['need_admin'])
            ? '보스 · 출현 대기'
            : ('보스 · 다음 ' . (string)$bootState['name']);
    } else {
        $em = trim((string)($bootState['emoji'] ?? ''));
        $titleBoss = '보스 · ' . ($em !== '' ? ($em . ' ') : '') . (string)$bootState['name'];
    }
}
$earlyHtml = !empty($GLOBALS['boss_early_html']);
$bsEmoji = htmlspecialchars((string)($bootState['emoji'] ?? '🐉'), ENT_QUOTES, 'UTF-8');
$bsName = htmlspecialchars((string)($bootState['name'] ?? '보스'), ENT_QUOTES, 'UTF-8');
$bsPhase = (string)($bootState['phase'] ?? '');
$bsHpNow = isset($bootState['hp_now']) ? number_format((int)$bootState['hp_now']) : '—';
$bsHpMax = isset($bootState['hp_max']) ? number_format((int)$bootState['hp_max']) : '—';
$bsHpPct = isset($bootState['hp_pct']) ? (string)$bootState['hp_pct'] : '—';
$bsStolen = htmlspecialchars((string)($bootState['stolen_point_fmt'] ?? '—'), ENT_QUOTES, 'UTF-8');
$bsEnhance = (int)($bootState['enhance'] ?? 0);
$bsMining = htmlspecialchars((string)($bootState['mining_label'] ?? ('Lv' . (int)($bootState['mining_level'] ?? 0))), ENT_QUOTES, 'UTF-8');
$bsReward = htmlspecialchars((string)($bootState['reward_each_fmt'] ?? '—'), ENT_QUOTES, 'UTF-8');
$bsRewardPct = htmlspecialchars((string)($bootState['reward_pct_label'] ?? '—'), ENT_QUOTES, 'UTF-8');
$bsTimer = '—';
$bsFmtSec = static function (int $sec): string {
    $sec = max(0, $sec);
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    if ($h > 0) {
        return $h . '시간 ' . $m . '분 ' . $s . '초';
    }
    return $m . '분 ' . $s . '초';
};
if ($bsPhase === 'fighting') {
    $left = (int)($bootState['fight_left_sec'] ?? 0);
    $bsTimer = $left > 0 ? ('⏱ 남은 시간 ' . $bsFmtSec($left)) : '⏱ 타임 종료';
} elseif ($bsPhase === 'waiting') {
    if (!empty($bootState['need_admin'])) {
        $bsTimer = '건의방 `.보스출현` 대기';
    } else {
        $waitIn = (int)($bootState['next_spawn_in'] ?? 0);
        $bsTimer = $waitIn > 0
            ? ('다음 출현까지 ' . $bsFmtSec($waitIn))
            : '곧 출현…';
    }
}
$bsNameShow = $bsName;
if ($bsPhase === 'waiting') {
    $bsNameShow = !empty($bootState['need_admin'])
        ? '출현 대기'
        : ('다음 · ' . $bsName);
}
$styleBit = trim((string)($bootState['style_label'] ?? ''));
$styleBit = $styleBit !== '' ? ($styleBit . ' · ') : '';
if ($bsPhase === 'waiting') {
    if (!empty($bootState['need_admin'])) {
        $bsSub = '건의방 관리자 `.보스출현` 으로 시작 · 랜덤 보스 · 제한 30분';
    } else {
        $blurb = trim((string)($bootState['blurb'] ?? '3~5시간 후 로테이션'));
        $hpMax = number_format((int)($bootState['hp_max'] ?? 0));
        $bsSub = $styleBit . $blurb . ' · HP ' . $hpMax
            . (!empty($bootState['night_hp_half']) ? ' (새벽 50%)' : '');
    }
} else {
    $blurb = trim((string)($bootState['blurb'] ?? '광역 최대 5회'));
    $fightMin = (int)($bootState['fight_min'] ?? 5);
    $aoeNow = (int)($bootState['aoe_protect_count'] ?? 0);
    $aoeMax = (int)($bootState['aoe_protect_max'] ?? 5);
    $bsSub = $styleBit . $blurb . ' · ' . $fightMin . '분 제한'
        . ' · 광역 ' . $aoeNow . '/' . $aoeMax
        . (!empty($bootState['night_hp_half']) ? ' · 🌙새벽HP·30분' : '');
}
$bsIsMagic = !empty($bootState['is_magic']);
$bsIsDanso = !empty($bootState['is_danso']);
$bsMyInfo = $bsIsMagic
    ? ('무기 +' . $bsEnhance . ' · 마법 보호 · 1인기준 ' . $bsReward . '냥 (' . $bsRewardPct . ')')
    : ($bsIsDanso
        ? ('무기 +' . $bsEnhance . ' · 단소 필살 · 1인기준 ' . $bsReward . '냥 (' . $bsRewardPct . ')')
        : ('무기 +' . $bsEnhance . ' · 채굴 ' . $bsMining
            . ' · 1인기준 ' . $bsReward . '냥 (' . $bsRewardPct . ')'));
$bsHasPrev = !empty($bootState['has_prev_result']);
?>
<?php if (!$earlyHtml): ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo htmlspecialchars($titleBoss, ENT_QUOTES, 'UTF-8'); ?></title>
<style>html,body{margin:0;background:#070b14;color:#e8eef8}</style>
<link href="/css/style.css" rel="stylesheet">
<link href="/css/boss.css?v=20260820g" rel="stylesheet">
</head>
<body>
<?php else: ?>
<script>document.title=<?php echo json_encode($titleBoss, JSON_UNESCAPED_UNICODE); ?>;</script>
<?php endif; ?>
<div class="wrap">
  <div class="top">
    <span class="badge">+1↑ 참가</span>
  </div>
  <h1>보스</h1>

  <?php if ($boot['need_code']): ?>
    <div class="card lock">가방 코드로 로그인해 주세요.</div>
  <?php elseif (!$allowed): ?>
    <div class="card lock">접근할 수 없어요.</div>
  <?php else: ?>
    <div class="card" id="bossCard">
      <div class="drop-ticker" id="dropTicker" aria-live="polite"></div>
      <div class="boss-stage" id="bossStage">
        <div class="fx-layer" id="fxLayer"></div>
        <div class="dragon<?php echo $bsPhase === 'waiting' ? ' waiting' : ''; ?>" id="bossEmoji"><?php echo $bsEmoji; ?></div>
      </div>
      <div class="boss-name" id="bossName"><?php echo $bsNameShow; ?></div>
      <p class="boss-sub" id="bossSub"><?php echo htmlspecialchars($bsSub, ENT_QUOTES, 'UTF-8'); ?></p>
      <div class="timer<?php echo $bsPhase === 'waiting' ? ' wait' : ''; ?>" id="timer"><?php echo htmlspecialchars($bsTimer, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="hp-bar"><div class="hp-fill" id="hpFill" style="width:<?php echo htmlspecialchars($bsHpPct === '—' ? '100' : $bsHpPct, ENT_QUOTES, 'UTF-8'); ?>%"></div></div>
      <div class="hp-text">
        <span>HP <b id="hpNow"><?php echo htmlspecialchars($bsHpNow, ENT_QUOTES, 'UTF-8'); ?></b> / <span id="hpMax"><?php echo htmlspecialchars($bsHpMax, ENT_QUOTES, 'UTF-8'); ?></span></span>
        <span id="hpPct"><?php echo htmlspecialchars($bsHpPct, ENT_QUOTES, 'UTF-8'); ?>%</span>
      </div>
      <div class="stolen-loot" id="stolenLoot">보스 처치풀 <?php echo $bsStolen; ?></div>
      <div class="actions">
        <button type="button" class="btn weapon" id="btnWeapon" disabled>⚔️ 무기 공격<span class="btn-hint" id="weaponHint">—</span></button>
        <button type="button" class="btn <?php echo $bsIsMagic ? 'protect' : ($bsIsDanso ? 'finish' : 'mining'); ?>" id="btnMining" disabled><?php
          echo $bsIsMagic ? '🛡️ 보호' : ($bsIsDanso ? '💥 필살' : '🥄 채굴 공격');
        ?><span class="btn-hint" id="miningHint"><?php
          echo $bsIsMagic ? '전원 강화~2배 · 이번 보스' : ($bsIsDanso ? '무기×3~6 · 1시간' : '레벨만큼');
        ?></span></button>
      </div>
      <p class="msg" id="msg"></p>
    </div>

    <div class="card" id="counterStatusCard">
      <div class="meta" style="margin-bottom:0"><b>광역/반격 상태</b></div>
      <div class="counter-status idle" id="counterStatus">
        <div class="cs-ico" id="csIco">🛡️</div>
        <div class="cs-body">
          <div class="cs-type" id="csType">대기 중</div>
          <div class="cs-loss" id="csLoss">아직 광역 피해 없음</div>
          <div class="cs-sub" id="csSub">공격 후 여기에 표시됩니다</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="meta" id="myInfo"><?php echo $bsMyInfo; ?></div>
    </div>

    <div class="card rules-fold" id="rulesFold">
      <button type="button" class="rules-toggle" id="rulesToggle" aria-expanded="false">
        <span>규칙</span>
        <span class="chev" aria-hidden="true">▼</span>
      </button>
      <p class="rules" id="rulesBody">
        · 제한 30분 · 직전 기여/광역은 [이전 보스 내역] · 다음 보스 출현 시 삭제<br>
        · 참가: 무기 +1↑ · 가방 → [보스]<br>
        · 공격 드랍: 은총조각 3% · 은총 0.01%<br>
        · 처치: (1인기준×참여자수) 풀을 기여(데미지) 비율로 분배<br>
        · MVP +5천냥 + 은총조각(1등5·2등4·3등3) · 나머지 랜덤아이템 1개<br>
        · 처치풀(공통): 광역 탈취 냥 누적 · 처치 시 1%로 분배(실패 시 풀 유지) · 평시 랜덤 5명×20% · 00~08시 랜덤 3명×40%<br>
        · 새벽(00~08) 출현 보스 HP 50% · 제한 30분 · 크리티컬 10%<br>
        · 개인 반격: 20% 회피 · 보호 있으면 이번 공격 데미지의 5% 차감<br>
        · 광역(전투 중 최대 5회): 본인 누적 데미지의 3%만큼 보호 차감<br>
        · 보호 0이면 성향별 피해(냥탈취 / 내구10% / 본방10%스왑 · 능력 분리)<br>
        · 무기 공격 시 55% 확률로 내구 차감(강화 구간별 % · +1~10:5~10 · +11~20:10~15 …) · 내구 0이면 공격 불가<br>
        · 채굴 공격: 보유 장비 레벨만큼 (예: 중장비 Lv10=10회) · 일반 수리해도 횟수 회복 없음<br>
        · 마법: 채굴 대신 파티 보호(이미 공격한 닉+시전자 · 량·횟수는 채팅 .보호와 비슷(강화×2) · 보스 나올 때마다 초기화 · 채팅 한도와 별개 · 기여 제외)<br>
        · 단소: 무기 횟수×2 · HP 50% 이하 딜×2 · 채굴 대신 필살(무기×3~6 · 한도는 무기와 동일 · 1시간 · 채팅 .시전과 별개 · 0이면 은총조각 1개 초기화)<br>
        · 은총조각: 공격횟수 0이면 해당 공격 버튼이 초기화 버튼으로 변경 · 무기/채굴은 횟수+내구 100% · 단소 필살은 횟수 충전(1시간 다시)<br>
        · 크리티컬: 낮 5% / 밤(00~08) 10% (무기 +20% · 채굴 +10~20%)<br>
        · 🎰 대박타: 무기 +20↑ · 강화 구간별 확률(+20~29:16% … +90~100:4%) · 발동 시 ×3~5
      </p>
    </div>

    <div class="card" id="histCard"<?php echo $bsHasPrev ? '' : ' hidden'; ?>>
      <a class="hist-link" id="histLink" href="/page/boss_history.php<?php echo htmlspecialchars($walletQ, ENT_QUOTES, 'UTF-8'); ?>">
        이전 보스 내역 보기
        <small>기여 순위 · 광역 · 내 공격</small>
      </a>
    </div>

    <div class="card" id="rankCard">
      <div class="meta" style="margin-bottom:8px" id="rankMeta"><b id="rankTitle">기여 순위</b> · 참여자 <span id="partCnt"><?php echo (int)($bootState['participants'] ?? 0); ?></span>명 · 1인기준 <span id="rewardFmt"><?php echo $bsReward; ?></span>냥 (<span id="rewardPct"><?php echo $bsRewardPct; ?></span>) · 기여비율 분배</div>
      <ul class="rank" id="rankList"></ul>
    </div>

    <div class="card" id="counterLogCard">
      <div class="meta" style="margin-bottom:8px" id="counterMeta"><b id="counterTitle">보스 광역 이력</b></div>
      <div class="log-tabs" id="counterTabs" role="tablist">
        <button type="button" class="log-tab active" data-tab="all" role="tab" aria-selected="true">전체 광역</button>
        <button type="button" class="log-tab" data-tab="mine" role="tab" aria-selected="false">내 피해</button>
        <button type="button" class="log-tab" data-tab="hits" role="tab" aria-selected="false">내 공격</button>
      </div>
      <ul class="log" id="counterList"></ul>
    </div>

    <?php if ($isJunho): ?>
    <div class="card admin-start-card" id="adminStartCard"<?php echo $bsPhase === 'waiting' ? '' : ' hidden'; ?>>
      <div class="meta" style="margin-bottom:8px"><b>대기 보스 시작 · 민호</b></div>
      <p class="boss-sub" id="adminStartHint"><?php
        $startHint = '대기 중인 보스를 지금 출현시킵니다.';
        if ($bsPhase === 'waiting') {
          $em = trim((string)($bootState['emoji'] ?? ''));
          $nm = trim((string)($bootState['name'] ?? '보스'));
          $who = trim($em . ' ' . $nm);
          if (!empty($bootState['need_admin'])) {
            $startHint = $who . ' · 출현 대기 · 누르면 바로 시작';
          } else {
            $startHint = $who . ' · ' . $bsTimer . ' · 누르면 대기 건너뛰고 시작';
          }
        }
        echo htmlspecialchars($startHint, ENT_QUOTES, 'UTF-8');
      ?></p>
      <button type="button" class="btn weapon" id="btnReset">지금 시작</button>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($allowed): ?>
<div class="result-overlay" id="resultOverlay" role="dialog" aria-modal="true">
  <div class="result-panel win" id="resultPanel">
    <div class="ico" id="resultIco">🐉</div>
    <p class="title" id="resultTitle">보스 처치!</p>
    <div class="badge-line" id="resultBadge">성공</div>
    <p class="body" id="resultBody">—</p>
    <p class="sub" id="resultSub">확인하면 다음 보스 대기로 넘어갑니다.</p>
    <button type="button" class="ok-btn" id="resultClose">다음으로</button>
  </div>
</div>
<script>
window.BOSS_BOOT = {
  code: <?php echo $codeJs; ?>,
  nick: <?php echo $nickJs; ?>,
  state: <?php echo $bootStateJs; ?>
};
</script>
<script src="/js/boss.js?v=20260821a" defer></script>
<?php endif; ?>
<?php
if (!empty($code)) {
  require_once __DIR__ . '/wallet_nav_fab.inc.php';
  // 본방냥 DB 조회 생략 (진입 지연 방지 · 스왑 버튼에 공란)
  wallet_nav_fab_render(['code' => $code, 'newpoint_fmt' => ' ']);
}
?>
</body>
</html>
