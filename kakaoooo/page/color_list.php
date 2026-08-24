<?
include_once $_SERVER['DOCUMENT_ROOT']."/lib/_function.php";
include_once $_SERVER['DOCUMENT_ROOT']."/api/_bonbang.php";
if (is_file($_SERVER['DOCUMENT_ROOT'].'/api/function.php')) {
  include_once $_SERVER['DOCUMENT_ROOT'].'/api/function.php';
}
include_once __DIR__ . '/_wallet_preauth.php';
include_once __DIR__ . '/_room_gate.php';
include_once __DIR__ . '/_color_market_lib.php';

$cl회원 = function_exists('cm_auth') ? cm_auth() : null;
if (empty($cl회원['code'])) {
  room_외부화면_출력('색표를 보려면 가방에서 접속하세요');
}

$썸명 = "민호썸";
$claimNick = trim((string)($cl회원['nick'] ?? ''));
$canClaim = ($claimNick !== '');
$claimAlreadyRegistered = false;
$claimAlreadyNum = 0;
if ($canClaim) {
  $claimNick_esc = addslashes($claimNick);
  $claimMember = db_select("SELECT idx, num FROM tb_member WHERE name = '{$claimNick_esc}' AND status != 1 LIMIT 1");
  $claimAlreadyNum = (int)($claimMember['num'] ?? 0);
  // 이미 색표(1~45)에 있으면 빈색 선점 UI 비활성
  if ($claimAlreadyNum >= 1 && $claimAlreadyNum <= 45) {
    $claimAlreadyRegistered = true;
    $canClaim = false;
  }
}
$본방주소 = !empty($본방주소) ? (string)$본방주소 : 'https://open.kakao.com/o/pIe2XDJi';

// 파스텔톤 색상 배열
$pastel_colors = [
    "#fbd3e6", // 연핑크
    "#c2f0c2", // 연녹색
    "#cce5ff", // 연하늘색
    "#ffd9b3", // 살구색
    "#e6ccff", // 연보라
    "#fff5cc", // 연노랑
    "#ffcccc", // 연핑크빨강
    "#ccf2ff", // 민트계열
    "#ffe0cc", // 살구오렌지
    "#e0e0eb"  // 연회색
];

// 랜덤 색상 선택
$random_color = $pastel_colors[array_rand($pastel_colors)];

// 장터 잠금 맵 (신입색 판정·렌더 공통)
$lockMap = [];
$ownerMap = [];
if (function_exists('cm_테이블보장')) {
  cm_테이블보장();
}
$lockRs = @db_query("SELECT color_num, owner_nick, trade_lock FROM tb_color_market");
if ($lockRs) {
  while ($lockRow = db_fetch($lockRs)) {
    $ln = (int)($lockRow['color_num'] ?? 0);
    if ($ln < 1 || $ln > 45) continue;
    $lockMap[$ln] = ((int)($lockRow['trade_lock'] ?? 0) === 1);
    $ownerMap[$ln] = trim((string)($lockRow['owner_nick'] ?? ''));
  }
}

// 1번: 평소 '불가'. 2~45(44칸)이 전부 차면 신입색으로 지정
$NEWBIE_COLOR_NUM = 1;
$memberCountByNum = [];
$mcRs = @db_query("SELECT num, COUNT(*) AS cnt FROM tb_member WHERE status != 1 AND num BETWEEN 1 AND 45 GROUP BY num");
if ($mcRs) {
  while ($mcRow = db_fetch($mcRs)) {
    $mn = (int)($mcRow['num'] ?? 0);
    if ($mn < 1 || $mn > 45) continue;
    $memberCountByNum[$mn] = (int)($mcRow['cnt'] ?? 0);
  }
}
$emptyOtherCount = 0;
for ($n = 2; $n <= 45; $n++) {
  $hasMember = !empty($memberCountByNum[$n]);
  $hasOwner = trim((string)($ownerMap[$n] ?? '')) !== '';
  if ($hasMember || $hasOwner) {
    continue;
  }
  $emptyOtherCount++;
}
// 1번 제외 44칸이 다 차면 1번을 신입색으로 지정
$useNewbieColorGuide = ($emptyOtherCount === 0);
$reservedLabel = $useNewbieColorGuide ? '신입' : '불가';
$newbieColorHex = '#FAE100'; // colors[0] = 1번 노랑
$nextNewbieSeq = 1;
if ($useNewbieColorGuide) {
  if (function_exists('신입색순번_다음번호')) {
    $nextNewbieSeq = 신입색순번_다음번호();
  } elseif (function_exists('신입색순번_컬럼보장')) {
    신입색순번_컬럼보장();
    $seqRow = @db_select("SELECT IFNULL(MAX(`신입색순번`), 0) AS mx FROM tb_member WHERE status != 1");
    $nextNewbieSeq = ((int)($seqRow['mx'] ?? 0)) + 1;
  }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>비어있는색 하나 골라보자~</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<link href="/css/style.css" rel="stylesheet">
</head>
<style>
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}

body {background: <?=$random_color?>;}
.commuter {position: absolute;
  bottom: 3px;
  background-color: fuchsia;
  border-radius: 4px;
  font-size: 12px;
  padding: 0px 6px;
  color: #fff;
  font-weight: 400;}
.hex {
  aspect-ratio: 1 / 1.15;
  background: #ddd;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-size: 18px;
  color: #000;
  position: relative;
  border-radius: 50%;
  border: 1px solid !important;
  width: 50px;
  height: 50px;
  margin-bottom: 14px;
}
.hex .cnt {
  font-weight: 800;
  font-size: 15px;
}
.hex .label {
  position: absolute;
  font-size: 18px;
}
/* 지정된 색: 닉네임 위 번호 */
.hex .color-num {
  position: absolute;
  top: 3px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 700;
  line-height: 1;
  opacity: 0.72;
  letter-spacing: -0.02em;
  pointer-events: none;
  z-index: 1;
}
.mok .label,
.mcouple .label {
  padding-top: 10px;
  box-sizing: border-box;
  line-height: 1.15;
  font-size: 15px;
}
.mcouple .color-num {
  font-size: 8px;
  opacity: 0.65;
}
.wrap { }

.container{
  grid-template-columns: repeat(6, 1fr);
}

/* 채워진 색: 조금 차분하게 */
.mok {
  border: 3px solid rgba(0,0,0,0.12) !important;
  opacity: 0.88;
  filter: saturate(0.92);
}

/* 공커색(2명+): 눈에 덜 띄게 */
.mcouple {
  border: 2px solid rgba(0,0,0,0.08) !important;
  opacity: 0.4;
  filter: grayscale(0.55) saturate(0.45) brightness(0.96);
  transform: scale(0.94);
  box-shadow: none;
  outline: none;
  animation: none;
  z-index: 0;
}
.mcouple .label {
  font-size: 13px;
  opacity: 0.8;
  font-weight: 600;
  line-height: 1.15;
}

/* 구매불가: 빈색처럼 안 보이게 */
.mlock {
  border: 2px solid rgba(0,0,0,0.1) !important;
  opacity: 0.38;
  filter: grayscale(0.7) brightness(0.92);
  transform: scale(0.94);
  box-shadow: none;
  outline: none;
  animation: none;
}
.mlock .cnt,
.mlock .cnt a,
.mlock .label {
  color: #4b5563 !important;
  font-size: 13px;
  font-weight: 700;
  opacity: 0.85;
  text-shadow: none;
}
.mlock::after {
  content: '구매불가';
  position: absolute;
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(55, 65, 81, 0.55);
  color: rgba(255,255,255,0.9);
  font-size: 9px;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  opacity: 0.75;
}

/* 비어있는 색: 강조 (살짝만) */
.mnull {
  border: 3px dashed #e11d48 !important;
  box-shadow:
    0 0 0 2px rgba(225, 29, 72, 0.18),
    0 4px 12px rgba(225, 29, 72, 0.22);
  transform: scale(1.06);
  z-index: 2;
  animation: color-empty-pulse 1.8s ease-in-out infinite;
  filter: saturate(1.1) brightness(1.04);
  outline: none;
}
.mnull .cnt,
.mnull .cnt a {
  color: #9f1239 !important;
  font-weight: 800;
  text-shadow: 0 1px 0 rgba(255,255,255,0.8);
}
.mnull::after {
  content: '빈색';
  position: absolute;
  top: -8px;
  left: 50%;
  transform: translateX(-50%);
  background: #e11d48;
  color: #fff;
  font-size: 9px;
  font-weight: 800;
  padding: 2px 5px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  box-shadow: 0 2px 6px rgba(225, 29, 72, 0.3);
  opacity: 0.9;
}
@keyframes color-empty-pulse {
  0%, 100% {
    box-shadow:
      0 0 0 2px rgba(225, 29, 72, 0.16),
      0 4px 10px rgba(225, 29, 72, 0.18);
  }
  50% {
    box-shadow:
      0 0 0 4px rgba(225, 29, 72, 0.28),
      0 5px 14px rgba(225, 29, 72, 0.28);
  }
}
@media (prefers-reduced-motion: reduce) {
  .mnull { animation: none; }
  .mnewbie { animation: none; }
}

/* 1번 예약: 평소 불가, 44칸 만석이면 신입 */
.mnewbie-slot {
  border: 2px solid #4b5563 !important;
  z-index: 2;
}
.mnewbie-slot::after {
  content: '불가';
  position: absolute;
  top: -10px;
  left: 50%;
  transform: translateX(-50%);
  background: #4b5563;
  color: #fff;
  font-size: 9px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 999px;
  line-height: 1.2;
  white-space: nowrap;
  box-shadow: 0 2px 6px rgba(55, 65, 81, 0.28);
  opacity: 0.95;
  z-index: 4;
}

/* 신입색(1번): 44칸이 다 찼을 때 강조 */
.mnewbie {
  border: 3px solid #2563eb !important;
  box-shadow:
    0 0 0 3px rgba(37, 99, 235, 0.28),
    0 6px 16px rgba(37, 99, 235, 0.35);
  transform: scale(1.12);
  z-index: 3;
  opacity: 1 !important;
  filter: saturate(1.15) brightness(1.05) !important;
  animation: color-newbie-pulse 1.6s ease-in-out infinite;
  outline: none;
}
.mnewbie::after,
.mnewbie-slot.mnewbie::after {
  content: '신입';
  background: #2563eb;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
}
.mnewbie .cnt,
.mnewbie .cnt a,
.mnewbie .label,
.mnewbie .color-num {
  color: #1e3a8a !important;
  font-weight: 800;
  opacity: 1 !important;
  text-shadow: 0 1px 0 rgba(255,255,255,0.85);
}
.mnewbie .newbie-nick-in {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 900;
  color: #1e3a8a;
  text-shadow: 0 1px 0 rgba(255,255,255,0.9);
  pointer-events: none;
  z-index: 2;
  padding: 0 4px;
  text-align: center;
  line-height: 1.15;
  word-break: keep-all;
}
.mnewbie .color-num {
  opacity: 0.55 !important;
}
.mnewbie .label {
  font-size: 11px !important;
  line-height: 1.1;
  padding-top: 18px;
  box-sizing: border-box;
}
@keyframes color-newbie-pulse {
  0%, 100% {
    box-shadow:
      0 0 0 2px rgba(37, 99, 235, 0.22),
      0 5px 12px rgba(37, 99, 235, 0.28);
  }
  50% {
    box-shadow:
      0 0 0 5px rgba(37, 99, 235, 0.38),
      0 7px 18px rgba(37, 99, 235, 0.4);
  }
}

/* nick 있을 때 빈색 클릭 가능 */
.hex.claimable {
  cursor: pointer;
}
.hex.claimable:active {
  transform: scale(1.02);
}
.hex.newbie-pick {
  cursor: pointer;
}
.hex.newbie-pick:active {
  transform: scale(1.06);
}

.claim-preview {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.15);
  margin: 0 auto 14px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.claim-preview .claim-preview-nick {
  font-size: 16px;
  font-weight: 900;
  color: #1e3a8a;
  text-shadow: 0 1px 0 rgba(255,255,255,0.85);
  line-height: 1.2;
  text-align: center;
  padding: 0 6px;
  word-break: keep-all;
}
.claim-msg {
  font-size: 15px;
  line-height: 1.55;
  text-align: center;
  color: #111;
  white-space: pre-line;
  margin: 0 0 16px;
  font-weight: 600;
}
.claim-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.claim-actions button,
.claim-actions a {
  display: block;
  width: 100%;
  box-sizing: border-box;
  text-align: center;
  padding: 11px 14px;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
}
.claim-actions .btn-claim {
  background: #e11d48;
  color: #fff;
}
.claim-actions .btn-claim:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.claim-actions .btn-back {
  background: #374151;
  color: #fff;
}
.claim-actions .btn-close {
  background: #e5e7eb;
  color: #374151;
}
.claim-nick {
  text-align: center;
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 10px;
}
.claim-blocked {
  position: sticky;
  top: 0;
  z-index: 20;
  margin: 10px 12px 0;
  padding: 12px 14px;
  background: #fff1f2;
  border: 1px solid #fecdd3;
  border-radius: 10px;
  color: #9f1239;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.45;
  text-align: center;
  box-shadow: 0 2px 8px rgba(225, 29, 72, 0.12);
}
.color-guide {
  position: sticky;
  top: 0;
  z-index: 19;
  margin: 10px 12px 0;
  padding: 14px 16px;
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 12px;
  color: #1f2937;
  font-size: 14px;
  font-weight: 650;
  line-height: 1.55;
  text-align: left;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}
.color-guide .guide-title {
  margin: 0 0 8px;
  font-size: 15px;
  font-weight: 800;
  color: #be123c;
  text-align: center;
}
.color-guide ol {
  margin: 0;
  padding-left: 1.2em;
}
.color-guide li {
  margin: 4px 0;
}
.color-guide .guide-em {
  color: #be123c;
  font-weight: 800;
}

</style>

<body >

  <div class="color-guide">
    <p class="guide-title">🎨 색표 고르는 방법</p>
    <ol>
      <?php if ($useNewbieColorGuide) { ?>
      <li>지금은 빈색이 없어서 <span class="guide-em">신입색 #<?= (int)$NEWBIE_COLOR_NUM ?></span> 을 써요.</li>
      <li>컬러 안에 <span class="guide-em">닉네임</span>이 들어가고, <span class="guide-em">선택하기</span>로 등록해요.</li>
      <li>공창에서 <span class="guide-em">프로필 색 <?= (int)$NEWBIE_COLOR_NUM ?>번</span>으로 변경하면 완료!</li>
      <?php } else { ?>
      <li><span class="guide-em">빈색</span>으로 되어 있는 색 중 마음에 드는 <span class="guide-em">1개</span>를 터치해서 선택해요.</li>
      <li><span class="guide-em">선점하기</span> 버튼을 눌러요.</li>
      <li>공창으로 돌아가서 <span class="guide-em">공창 프로필 색</span>도 같은 번호로 변경해주면 완료!</li>
      <?php } ?>
    </ol>
  </div>

  <?php if ($claimAlreadyRegistered) { ?>
  <div class="claim-blocked">
    <?= htmlspecialchars($claimNick, ENT_QUOTES, 'UTF-8') ?> 님은 이미 #<?= (int)$claimAlreadyNum ?> 색표에 등록되어 있어요.<br>
    추가 선점은 할 수 없어요.
  </div>
  <?php } ?>

  <div class="wrap">
    <div class="container" >
      <?php
      $colors = [
        '#FAE100', '#F14F4A', '#EC7F5A', '#EE9830', '#8DBC30',
        '#4AA366', '#4EA698', '#4EA5B2', '#4D9DD8', '#4469A0',
        '#735FA7', '#9158B6', '#D35497', '#D7456A', '#FBF28C',
        '#EA9A93', '#ED9D8E', '#ECB273', '#B3C270', '#7FCF90',
        '#89CAC2', '#96C7CF', '#7EB9DB', '#88A2D4', '#AC9CDA',
        '#B785CB', '#E480B6', '#EB9EAE', '#F8F5D5', '#F8DAD8',
        '#F7D7C7', '#EFDBB7', '#E7F5BA', '#B9EBC2', '#CCEFF1',
        '#C6EEEE', '#C5E1EF', '#CADCEA', '#D8D9ED', '#E0D0EB',
        '#F7D5E6', '#F9E0E5', '#ffffff', '#C3C3C3', '#000'
      ];

      for ($i = 1; $i <= 45; $i++) {
          $bgColor = $colors[$i - 1];
          if(!$bgColor){
            $bgColor = "#FAE100";
          }


          $style = "background:{$bgColor};";
          if ($i == 45) { $style2 = 'color:#fff;';
          }else{ $style2 = ' '; }

          $table = "tb_member";
          $sql = "SELECT * FROM {$table} WHERE num = {$i} and status != 1";
          $result = db_query($sql);

          $items = [];
          while ($row = db_fetch($result)) {
              $items[] = $row;
          }

          $isEmpty = (count($items) == 0);
          $isLocked = !empty($lockMap[$i]);
          $isCouple = (count($items) >= 2);
          $isReservedNewbie = ($i === $NEWBIE_COLOR_NUM);
          $isNewbiePick = ($useNewbieColorGuide && $isReservedNewbie);
          // 신입색(1번) 선점 예약 > 구매불가 > 공커 > 빈색 > 일반
          if ($isReservedNewbie) {
            $cellClass = 'mnewbie-slot';
            if ($useNewbieColorGuide) {
              $cellClass .= ' mnewbie';
            }
          } elseif ($isLocked) {
            $cellClass = 'mlock';
          } elseif ($isCouple) {
            $cellClass = 'mcouple';
          } elseif ($isEmpty) {
            $cellClass = 'mnull';
          } else {
            $cellClass = 'mok';
          }

          $lockOwner = $ownerMap[$i] ?? '';
          $isClaimable = ($canClaim && !$useNewbieColorGuide && $isEmpty && !$isLocked && $i !== 1 && $i !== 2);
          $extraClass = '';
          if ($isClaimable) $extraClass .= ' claimable';
          if ($isNewbiePick) $extraClass .= ' newbie-pick';
          ?>
          <div class="hex <?= $cellClass ?><?= $extraClass ?>"
               data-number="<?= $i ?>"
               data-color="<?= htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8') ?>"
               <? if($isClaimable || $isNewbiePick){ ?>role="button" tabindex="0"<? } ?>
               style="<?= $style ?>">
            <? if ($isNewbiePick && $claimNick !== '') { ?>
              <div class="newbie-nick-in"><?= htmlspecialchars($claimNick, ENT_QUOTES, 'UTF-8') ?></div>
            <? } ?>
            <? if($isEmpty){?>
              <div class="cnt" >
                <? if($i==1){?>
                <?= htmlspecialchars($reservedLabel, ENT_QUOTES, 'UTF-8') ?>
                <? } else if($isLocked){ ?>
                  <? if($lockOwner !== ''){ ?>
                    <?= htmlspecialchars($lockOwner, ENT_QUOTES, 'UTF-8') ?>
                  <? } else if($i==2){ ?>
                    민호
                  <? } else { ?>
                    <?= $i ?>
                  <? } ?>
              <? }else if($i==2){?>
                <a href="javascript:;" style="color:#333;" >민호</a>
              <? }else{ ?>
                <? if($canClaim){ ?>
                  <?= $i ?>
                <? } else { ?>
                <a href="javascript:alert('신입 등록 하는 과정으로 진행할것!')" style="color: unset;" ><?= $i ?></a>
                <? } ?>
              <? } ?>
              </div>
            <? } else { ?>
              <div class="color-num" style="<?= $style2 ?>"><?= $i ?></div>
            <? } ?>
              <div class="label" style="<?= $style2 ?> <?= $count == 1 ? '':'' ?> " >
                <? foreach ($items as $item) { ?>
                    <?=$item['name']?>
                    <div></div>
                <? } ?>
              </div>
          </div>
          <?php } ?>

          <?php
      $sql2 = "SELECT * FROM tb_member WHERE couple = 2 and status != 1";
      $result2 = db_query($sql2);
      $style2 = "background:#FAE100;";
      while ($row2 = db_fetch($result2)) {
      ?>
      <div class="hex" style="<?= $style2 ?>">
          <div class="label">
                      <?=$row2['name']?>
          </div>
      </div>
    <?php } ?>



    </div>
  </div>

<?php if ($canClaim || $useNewbieColorGuide) { ?>
<div id="claimLayer" class="popup-overlay" style="display:none;">
  <div class="popup-box" style="width: min(320px, 90vw);">
    <div class="claim-nick" id="claimNickTitle"><?php
      if ($useNewbieColorGuide) {
        echo $claimNick !== ''
          ? htmlspecialchars($claimNick, ENT_QUOTES, 'UTF-8') . ' 님 · 신입색 안내'
          : '신입색 안내';
      } else {
        echo htmlspecialchars($claimNick, ENT_QUOTES, 'UTF-8') . ' 님 · 빈색 선택';
      }
    ?></div>
    <div id="claimPreview" class="claim-preview"><span id="claimPreviewNick" class="claim-preview-nick" style="display:none;"></span></div>
    <p id="claimMsg" class="claim-msg"></p>
    <div class="claim-actions">
      <?php if ($canClaim) { ?>
      <button type="button" class="btn-claim" id="btnClaim"><?= $useNewbieColorGuide ? '선택하기' : '선점하기!' ?></button>
      <?php } ?>
      <a class="btn-back" id="btnGoChat" href="<?= htmlspecialchars($본방주소, ENT_QUOTES, 'UTF-8') ?>">공창으로 돌아가기</a>
      <button type="button" class="btn-close" id="btnClaimClose">닫기</button>
    </div>
  </div>
</div>
<?php } ?>
</body>



<script>
function setNames(input) {
 var names = input.split('|');
 document.getElementById('name1').value = names[0] || '';
 if (names.length > 1) {
   document.getElementById('name2').value = names[1] || '';
 } else {
   document.getElementById('name2').value = '';
 }
}

function go_create(){
    $('.member_create').fadeIn();
}

function _insert(){
  var name1 = $("#name1").val();
  var gender = $(".gender:checked").val();

  if(!name1){
    alert("닉네임을 입력해주세요.");
    return false;
  }

  if (!gender) {
      alert("성별을 선택해주세요.");
      return false;  // 전송 막기
  }

  var params = jQuery("#orderFrm").serialize();
    $.ajax({
      type : "POST",
      url: "../_new_insert.php",
      async: false,
      data: params,
    success: function(result) {
        var r = result.trim();
        if(r==1){
          location.reload();
        }
      },
      error:function(e) {
        alert(e.responseText);
      }
    });
}

function go_update(obj){
    var number = obj;
    var nick = $(this).data('nick');
    $(".number_title").html(number + "번 변경");
    $("#number").val(number);

    $.ajax({
       type : "POST",
       url: "../_get.php",
       async: false,
       data: {
         code : number,
         nick : nick
       },
       success: function(result) {
         $("#popupLayer").html(result);
       },
       error:function(e) {
          alert(e.responseText);
       }
    });

    $('#popupLayer').fadeIn();
}

$(function() {

  $('#closePopup, #popupLayer').click(function(e) {
    // 팝업 바깥쪽 클릭 시 닫기
    if (e.target.id === 'popupLayer' || e.target.id === 'closePopup') {
      $('#popupLayer').fadeOut();
    }
  });

  $('#closePopup, .member_create').click(function(e) {
    // 팝업 바깥쪽 클릭 시 닫기
    if (e.target.id === 'popupLayer' || e.target.id === 'closePopup') {
      $('.member_create').fadeOut();
    }


  });

<?php if ($canClaim || $useNewbieColorGuide) { ?>
  var CLAIM_NICK = <?= json_encode($claimNick, JSON_UNESCAPED_UNICODE) ?>;
  var CAN_CLAIM = <?= $canClaim ? 'true' : 'false' ?>;
  var USE_NEWBIE_COLOR = <?= $useNewbieColorGuide ? 'true' : 'false' ?>;
  var NEWBIE_COLOR_NUM = <?= (int)$NEWBIE_COLOR_NUM ?>;
  var NEWBIE_COLOR_HEX = <?= json_encode($newbieColorHex) ?>;
  var selectedNum = 0;
  var selectedColor = '';

  function openClaimLayer(num, color) {
    selectedNum = num;
    selectedColor = color || '#FAE100';
    $('#claimPreview').css('background', selectedColor);
    if (USE_NEWBIE_COLOR) {
      var nickLabel = CLAIM_NICK || '';
      if (nickLabel) {
        $('#claimPreviewNick').text(nickLabel).show();
      } else {
        $('#claimPreviewNick').hide().text('');
      }
      var msg =
        '신입색 ' + num + '번이 선택되었어요.\n' +
        '공창에서 ' + num + '번 색상으로 프로필색 변경해줘!';
      if (CAN_CLAIM && nickLabel) {
        msg = '[' + nickLabel + '] 님이 신입색 ' + num + '번에 선택되었어요.\n' +
          '선택하기로 닉네임을 등록한 뒤,\n' +
          '공창에서 ' + num + '번 색상으로 프로필색 변경해줘!';
        $('#btnClaim').prop('disabled', false).text('선택하기').show();
      } else {
        msg += '\n(신입 닉으로 들어오면 선택하기로 등록할 수 있어요)';
        $('#btnClaim').hide();
      }
      $('#claimMsg').text(msg);
    } else {
      $('#claimPreviewNick').hide().text('');
      $('#claimMsg').text(
        '선택하신 프로필색은 ' + num + '번\n' +
        '공창에서 ' + num + '번 색상으로 프로필색 변경해줘!'
      );
      $('#btnClaim').prop('disabled', false).text('선점하기!').show();
    }
    $('#claimLayer').fadeIn(150);
  }

  function closeClaimLayer() {
    $('#claimLayer').fadeOut(120);
  }

  $(document).on('click', '.hex.claimable', function(e) {
    e.preventDefault();
    var num = parseInt($(this).data('number'), 10);
    var color = $(this).data('color') || '#FAE100';
    if (!num) return;
    openClaimLayer(num, color);
  });

  $(document).on('click', '.hex.newbie-pick', function(e) {
    e.preventDefault();
    var num = parseInt($(this).data('number'), 10) || NEWBIE_COLOR_NUM;
    var color = $(this).data('color') || NEWBIE_COLOR_HEX;
    openClaimLayer(num, color);
  });

  $(document).on('keydown', '.hex.claimable, .hex.newbie-pick', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      $(this).trigger('click');
    }
  });

  $('#claimLayer').on('click', function(e) {
    if (e.target.id === 'claimLayer') closeClaimLayer();
  });
  $('#btnClaimClose').on('click', closeClaimLayer);

  if (USE_NEWBIE_COLOR) {
    // 색표 다 찬 경우: 누구나 1번(신입색) 안내 레이어
    openClaimLayer(NEWBIE_COLOR_NUM, NEWBIE_COLOR_HEX);
  }

  $('#btnClaim').on('click', function() {
    if (!CAN_CLAIM) return;
    if (!selectedNum || !CLAIM_NICK) return;
    var $btn = $(this);
    var busyText = USE_NEWBIE_COLOR ? '등록중...' : '선점중...';
    var idleText = USE_NEWBIE_COLOR ? '선택하기' : '선점하기!';
    $btn.prop('disabled', true).text(busyText);
    var postData = {
      nick: CLAIM_NICK,
      color_num: selectedNum
    };
    if (USE_NEWBIE_COLOR) {
      postData.newbie_mode = 1;
    }
    $.ajax({
      type: 'POST',
      url: './color_list_claim.php',
      dataType: 'json',
      data: postData,
      success: function(res) {
        if (res && res.ok) {
          alert(res.msg || (USE_NEWBIE_COLOR ? '등록 완료!' : '선점 완료!'));
          location.href = './color_list.php';
        } else {
          alert((res && res.msg) ? res.msg : '처리에 실패했어요.');
          $btn.prop('disabled', false).text(idleText);
        }
      },
      error: function(xhr) {
        alert(xhr.responseText || '요청 실패');
        $btn.prop('disabled', false).text(idleText);
      }
    });
  });
<?php } ?>

});

function _set(){
  var number = $("#number").val();
  var name1 = $("#name1").val();
  var name2 = $("#name2").val();
  var name3 = $("#name3").val();//기존닉
  var gender = $(".gender:checked").val();
  var commuter = $(".commuter_chk:checked").val();
  var couple = $(".couple:checked").val();
  var content = $("#content1").val();

  if(!name1){
    alert("닉네임을 입력해주세요.");
    return false;
  }
  if (!gender) {
      alert("성별을 선택해주세요.");
      return false;  // 전송 막기
  }

  console.log("성별 :: " + gender);
  $.ajax({
     type : "POST",
     url: "../_new_set.php",
     async: false,
     data: {
      number : number,
      name : name1,
      name3 : name3,
      num : name2,
      couple : couple,
      commuter : commuter,
      gender : gender,
      content : content
     },
     success: function(result) {
        var r = result.trim();
        console.log(r);
        if(r==1){
          location.reload();
        }else{
          alert(r);
          return false;
        }
     },
     error:function(e) {
        alert(e.responseText);
     }
  });
}
</script>


<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
(function () {
  var captureBtn = document.getElementById('capture-btn');
  if (!captureBtn) return;
  captureBtn.addEventListener('click', function () {
    const page_tab = document.getElementById('page_tab');
    const target = document.body;
    $(".wrap").css("padding-top", "50px");
    // 캡처 전에 버튼 숨기기
    captureBtn.style.display = 'none';
    if (page_tab) page_tab.style.display = 'none';

    // 현재 시간 가져오기 및 포맷팅
    const now = new Date();
    const ymdhis = now.getFullYear().toString().slice(2) +
      String(now.getMonth() + 1).padStart(2, '0') +
      String(now.getDate()).padStart(2, '0') +
      String(now.getHours()).padStart(2, '0') +
      String(now.getMinutes()).padStart(2, '0') +
      String(now.getSeconds()).padStart(2, '0');

    // 약간의 지연을 줘서 숨긴 후 렌더링 안정화
    setTimeout(function () {
      html2canvas(target, {
        useCORS: true,
        allowTaint: true,
        scale: 2
      }).then(function (canvas) {
        // 다운로드 링크 생성
        const link = document.createElement('a');
        link.href = canvas.toDataURL();
        link.download = `capture_${ymdhis}.png`; // 파일명에 ymdhis 적용
        link.click();
      }).catch(function (err) {
        console.error('캡처 실패:', err);
      }).finally(function () {
        // 캡처 후 버튼 다시 보이기
        captureBtn.style.display = 'inline-block';
        if (page_tab) page_tab.style.display = 'inline-block';
    $(".wrap").css("padding-top", "0px");
      });
    }, 100); // 100ms 딜레이 (렌더링 안정화용)
  });
})();
</script>

</html>
