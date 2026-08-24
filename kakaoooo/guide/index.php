<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
$guide_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '49.247.160.164') . '/guide/';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#4f6ef7">
  <title>우리방 사용설명서</title>
  <link rel="stylesheet" href="/guide/guide.css?v=<?= (int)@filemtime(__DIR__ . '/guide.css') ?>">
</head>
<body>
<div class="guide-wrap">

  <header class="guide-hero">
    <h1>📖 우리방 사용설명서</h1>
    <p>모바일에서 보기 편하게 정리했어요.<br>채팅에서 <span class="cmd">.사용설명서</span> 로도 열 수 있어요.</p>
  </header>

  <nav class="guide-nav" aria-label="목차">
    <div class="guide-nav-inner">
      <a href="#nyang">냥</a>
      <a href="#basic">기본</a>
      <a href="#game">게임</a>
      <a href="#boss">보스</a>
      <a href="#market">마켓</a>
      <a href="#level">레벨</a>
      <a href="#room">방생활</a>
      <a href="#item">아이템</a>
      <a href="#links">바로가기</a>
    </div>
  </nav>

  <main class="guide-main">

    <section class="guide-section" id="nyang">
      <details class="guide-card" open>
        <summary>💰 냥이 뭐예요?</summary>
        <div class="guide-body">
          <h3>본방냥 (보유냥)</h3>
          <ul>
            <li>채팅할 때마다 자동 적립 · 1타 = 0.1냥 · 사진 2타 = 0.2냥</li>
            <li>마법 버프 중: 2타=0.2 · 사진 3타=0.3</li>
            <li>양도, 아이템, 주급/보급, 신입지원, 마켓 등에 사용</li>
          </ul>
          <h3>게임냥</h3>
          <ul>
            <li>맞다이, 야바위, 홀짝, 채굴, 무기 강화, 로또 등 게임용</li>
            <li><span class="cmd">.궁금 닉</span> · 홍보방 <span class="cmd">.랭킹2</span> 로 확인</li>
          </ul>
          <h3>자주 쓰는 명령</h3>
          <div class="cmd-row">
            <span class="cmd">.내냥</span>
            <span class="cmd">.양도 닉 금액</span>
            <span class="cmd">.환전</span>
            <span class="cmd">.스왑</span>
            <span class="cmd">.냥이란</span>
          </div>
          <p class="guide-note">1글자 채팅·일부 명령어는 타수가 안 올라가요.</p>
        </div>
      </details>
    </section>

    <section class="guide-section" id="basic">
      <details class="guide-card">
        <summary>📌 기본 명령어</summary>
        <div class="guide-body">
          <h3>내 정보</h3>
          <div class="cmd-row">
            <span class="cmd">.내냥</span>
            <span class="cmd">.가방</span>
            <span class="cmd">.궁금 닉</span>
          </div>
          <h3>랭킹</h3>
          <div class="cmd-row">
            <span class="cmd">.랭킹1 10</span>
            <span class="cmd">.랭킹2 10</span>
            <span class="cmd">.랭킹3 10</span>
          </div>
          <p>보유냥 · 게임냥 · 채굴 장비 순위 (숫자 = 상위 N명 · 홍보방)</p>
          <h3>방 정보</h3>
          <div class="cmd-row">
            <span class="cmd">.우리방</span>
            <span class="cmd">.명령</span>
            <span class="cmd">.인원</span>
            <span class="cmd">.솔로</span>
            <span class="cmd">.평타</span>
            <span class="cmd">.생타</span>
          </div>
          <h3>기타</h3>
          <div class="cmd-row">
            <span class="cmd">.출석</span>
            <span class="cmd">.출석포기 사유</span>
            <span class="cmd">.미션</span>
            <span class="cmd">.버프</span>
            <span class="cmd">.모금</span>
          </div>
          <p class="guide-note">아이템 설명·사용법은 아래 <strong>아이템</strong> 섹션 참고</p>
        </div>
      </details>
    </section>

    <section class="guide-section" id="game">
      <details class="guide-card">
        <summary>🎮 게임</summary>
        <div class="guide-body">
          <h3>초성 퀴즈</h3>
          <p><span class="cmd">.초성</span> 출제 → <span class="cmd">ㅁ정답</span> 또는 <span class="cmd">.정답 정답</span></p>
          <p>틀리면 50만 게임냥 차감 · 상금 누적</p>

          <h3>맞다이</h3>
          <p><span class="cmd">ㄱㄱ 3 50000</span> — 숫자 + 깽값으로 신청·참여 (홍보방)</p>
          <p>이기면 깽값 획득 · 비기면 냥 차감 · 자세히 <span class="cmd">.맞다이방법</span></p>

          <h3>야바위 (주사위)</h3>
          <div class="cmd-row">
            <span class="cmd">.신청 금액</span>
            <span class="cmd">ㅅㅊ</span>
            <span class="cmd">.마감</span>
            <span class="cmd">ㄷㄹ</span>
          </div>
          <p>2인 이상 모이면 마감 → ㄷㄹ 3회 · 총점 1등 우승 (홍보방) · <span class="cmd">.야바위방법</span></p>

          <h3>홀짝</h3>
          <div class="cmd-row">
            <span class="cmd">.도전 홀</span>
            <span class="cmd">.도전 500만 짝</span>
            <span class="cmd">ㅈㅈ 홀</span>
            <span class="cmd">.홀짝</span>
          </div>
          <p>홀/짝 맞추기 · 연승 배수 · 채팅(홍보방) 또는 홀짝 웹 · 오늘 미션 100타 달성 후 이용</p>

          <h3>냥카라</h3>
          <p>가방 웹에서 플레이어·뱅커·타이에 여러 번 배팅 · 최대 8명 같은 테이블 · 게임냥</p>
          <p>플레이어 1배 · 뱅커 1배 · 타이 8배 · 타이 시 플/뱅은 본전 환급</p>
          <p><a href="/page/nyangkara_guide.php">냥카라 게임방법</a></p>

          <h3>로또</h3>
          <div class="cmd-row">
            <span class="cmd">.로또</span>
            <span class="cmd">.로또 1,22,33</span>
            <span class="cmd">.로또 구매 1~100</span>
            <span class="cmd">.로또 구매 전부</span>
          </div>
          <p>본방: <span class="cmd">.로또</span> 조회만 · 구매·내역은 홍보방 · 매일 자정 추첨 · 티켓은 레벨업 시 지급</p>

          <h3>채굴</h3>
          <p>숟가락 채굴 웹에서 오프라인 포함 채굴냥이 쌓여요 (연구실 인증코드 필요)</p>
          <ul>
            <li>무기 <strong>강화 100회</strong> 후부터 채굴냥 적립 (성공·실패 모두 카운트)</li>
            <li>장비 업그레이드: 숟가락 → 포크 → 젓가락 → … → 황금 숟가락</li>
            <li>채굴냥 <strong>10냥</strong> 이상 <span class="cmd">.수령</span> → 본방냥 (홍보방)</li>
            <li>장비 <strong>내구도</strong> — 채굴 중 서서히 감소, 0이면 정지 · <span class="cmd">.채굴수리</span></li>
          </ul>
          <div class="cmd-row">
            <span class="cmd">.수령</span>
            <span class="cmd">.채굴란</span>
            <span class="cmd">.내냥</span>
            <span class="cmd">.채굴수리</span>
          </div>
          <p><strong>은총 버프 (5분)</strong> — 강화 확률·비용 ÷10 · 채굴량 <strong>x10</strong> · 내구 마모 ×2 · 은총조각 최대 500개 · 홍보방 <span class="cmd">.은총교환</span> (10개→은총 1개)</p>

          <h3>무기 강화</h3>
          <p>홍보방(게임방) · 채굴 웹에서 이용</p>
          <ul>
            <li>무기 구매: <span class="cmd">.강화 단소</span> · <span class="cmd">.강화 활</span> · <span class="cmd">.강화 마법</span> (각 10만냥)</li>
            <li>강화: 무기 보유 시 <span class="cmd">.강화</span> — 냥 내고 한 단계 도전 (높을수록 어려움)</li>
            <li>종류별 +1~+100은 1명만 · 이미 있으면 그 사람한테 <strong>탈취</strong> (비용·확률은 일반 강화와 동일)</li>
            <li>실패 시 파손·리셋 → <span class="cmd">.강화 수호</span> 로 미리 쌓아 두면 면제</li>
            <li>+18강 이상 <span class="cmd">.무기교체</span> 로 무기 종류만 변경 가능 (실패 시 -1강)</li>
            <li><strong>+10 이상</strong> + <span class="cmd">.내무기</span> 해제 시 채굴 결합·광물 발견</li>
          </ul>
          <div class="cmd-row">
            <span class="cmd">.강화방법</span>
            <span class="cmd">.강화비용</span>
            <span class="cmd">.내무기</span>
            <span class="cmd">.무기랭킹</span>
            <span class="cmd">.무기랭킹 단소</span>
            <span class="cmd">.무기랭킹 활</span>
            <span class="cmd">.무기랭킹 마법</span>
          </div>

          <h3>기타</h3>
          <div class="cmd-row">
            <span class="cmd">.게임종류</span>
            <span class="cmd">.ㄹㄷ 10</span>
            <span class="cmd">.랜덤박스란</span>
          </div>
          <p>기타 게임은 홍보방·웹에서 이용</p>
        </div>
      </details>
    </section>

    <section class="guide-section" id="boss">
      <details class="guide-card">
        <summary>🐉 보스전</summary>
        <div class="guide-body">
          <ul>
            <li>제한 30분 · 실패 시 기록 초기화 · 3~5시간 뒤 로테이션 자동 출현</li>
            <li>참가: 무기 +15↑ · 가방 → [보스]</li>
            <li>공격 드랍: 은총조각 3% · 은총 0.01%</li>
            <li>은총조각: 무기/채굴 공격횟수가 0일 때 각각 1개로 해당 횟수만 초기화</li>
            <li>처치: (1인기준×참여자수) 풀을 기여(데미지) 비율로 분배 + MVP +5천냥 + 은총조각(1등3·2등2·3등1) · 나머지 랜덤아이템 1개</li>
            <li>처치풀(공통): 반격 탈취 냥 누적 · 처치 시 1%로 분배(실패 시 풀 유지) · 평시 랜덤 5명×20% · 00~08시 랜덤 3명×40%</li>
            <li>새벽(00~08) 출현 보스 HP 50% · 제한 30분 · 크리티컬 10%</li>
            <li>크리티컬: 낮 5% / 밤(00~08) 10% (발동 시 2배)</li>
          </ul>
        </div>
      </details>
    </section>

    <section class="guide-section" id="market">
      <details class="guide-card">
        <summary>🛒 도하 마켓</summary>
        <div class="guide-body">
          <ul>
            <li>기프티콘 등록·구매 (게임냥) · 구매는 무기 +20 이상</li>
            <li>권면가 기준 자동 가격 · 실시간 총량 반영</li>
            <li>판매등록 은총: 권면가 <strong>1만원당 1개</strong> (총권면가÷1만원, 내림)</li>
            <li>판매자 선매입 시 본방냥 선지급</li>
          </ul>
          <div class="cmd-row">
            <span class="cmd">.주문</span>
            <span class="cmd">.마켓큰손</span>
            <span class="cmd">.마켓큰손 20</span>
          </div>
          <p>판매중 목록 · 권면가 합계 상위 판매자 랭킹</p>
        </div>
      </details>
    </section>

    <section class="guide-section" id="level">
      <details class="guide-card">
        <summary>📈 레벨업 · 로또 티켓</summary>
        <div class="guide-body">
          <p>누적 버프 타수 기준 자동 레벨업 · <span class="cmd">.레벨업</span> 으로 구간 확인</p>
          <h3>레벨 구간 (타수)</h3>
          <ul>
            <li>Lv 0~10: 150타당 1레벨</li>
            <li>Lv 11~30: 300타당 1레벨</li>
            <li>Lv 31~100: 500타당 1레벨</li>
            <li>Lv 101~150: 1,000타당 1레벨</li>
            <li>Lv 151~200: 2,000타당 1레벨</li>
            <li>Lv 201~300: 3,000타당 1레벨</li>
          </ul>
          <h3>보상</h3>
          <ul>
            <li>본방냥 — 오른 레벨 숫자만큼 (Lv5→5냥 …)</li>
            <li>로또 티켓 — Lv1~10: 15장 · 11~30: 30장 · 31~100: 50장 · 101~150: 100장 · 151~200: 200장 · 201~300: 300장</li>
          </ul>
        </div>
      </details>
    </section>

    <section class="guide-section" id="room">
      <details class="guide-card">
        <summary>🏠 방생활 · 일방</summary>
        <div class="guide-body">
          <h3>출석 · 활동</h3>
          <p>왠만하면 하루 <strong>100타</strong> 정도 쳐 주세요.</p>
          <ul>
            <li><strong>100타</strong> 달성 시 자동 출석 완료 · <span class="cmd">.출석</span> 으로 확인</li>
            <li>그날 출석한 사람이 <strong>전원</strong> 모이면 <strong>전체출석냥</strong> 지급</li>
            <li>100타가 어려운 날은 <span class="cmd">.출석포기 사유</span> 를 자유롭게 써도 돼요 (예: <span class="cmd">.출석포기 오늘은 쉴래요</span>)</li>
            <li><strong>48시간</strong> 동안 채팅 1마디도 없으면 <strong>강퇴</strong> — 인사 한마디는 꼭 해 주세요</li>
          </ul>

          <h3>일방 가는 방법</h3>
          <p>일방은 <span class="cmd">.미션</span> 에 나오는 <strong>일방미션(필수)</strong>을 모두 완료해야 신청·이용할 수 있어요.</p>
          <p>채팅에 <span class="cmd">.미션</span> 입력 → ✅/❌ 로 내 진행 확인</p>
          <h3>일방 필수 미션</h3>
          <ul>
            <li>얼공 1회</li>
            <li>강제일방(강일) 아이템 1회 사용</li>
            <li>3일간 매일 200타(생타) — 중간에 끊기면 처음부터</li>
          </ul>
          <p>3일 200타 완료 시 <strong>일방신청권·연장권</strong> 자동 지급 · 신청권 보유 시 200타 미션 완료로 인정</p>
          <p><strong>일방신청권이 없을 때</strong> — 다른 친구에게 <span class="cmd">.선물 닉 일방신청권</span> 으로 양도받거나, 게임냥을 내고 <span class="cmd">.구매 일방신청권</span> 으로 구매할 수 있어요.</p>
          <p class="guide-note">자세한 조건: <span class="cmd">.일방준비</span> · 정식 일방 신청: <span class="cmd">.건의방</span></p>

          <h3>일방 · 강일 · 지목</h3>
          <div class="cmd-row">
            <span class="cmd">.진행</span>
            <span class="cmd">.자숙</span>
            <span class="cmd">.미션</span>
            <span class="cmd">.일방준비</span>
            <span class="cmd">.건의방</span>
            <span class="cmd">.공일방</span>
          </div>
          <p><strong>.지목 / .강일</strong> — 아이템이 없으면 게임냥으로 자동구매 · 게임냥도 부족하면 본방냥 20% 스왑 후 구매</p>
          <h3>유료 GPT</h3>
          <div class="cmd-row">
            <span class="cmd">.메뉴추천</span>
            <span class="cmd">.맛집 지역</span>
            <span class="cmd">.닉추천</span>
            <span class="cmd">.mbti 닉</span>
          </div>
          <h3>연금</h3>
          <div class="cmd-row">
            <span class="cmd">.강일연금</span>
            <span class="cmd">.지목연금</span>
            <span class="cmd">.공커연금</span>
          </div>
        </div>
      </details>
    </section>

    <section class="guide-section" id="item">
      <details class="guide-card" open>
        <summary>🎒 아이템</summary>
        <div class="guide-body">
          <p>보유 확인: <span class="cmd">.가방</span> · 상세: <span class="cmd">.아이템</span> · 개별: <span class="cmd">.아이템 제한</span> 등</p>

          <h3>아이템 종류</h3>
          <ul>
            <li><strong>👹 제한</strong> — 자숙 6시간 / 보룸·채팅·게임 제한 3시간 중 택1</li>
            <li><strong>👼 수호</strong> — 자숙 1일 단축, 제한 3시간 단축 등</li>
            <li><strong>🎁 선물</strong> — 선물 1개로 아이템 최대 100개까지 전달 (<span class="cmd">.선물 닉 템명 100</span>)</li>
            <li><strong>🪪 닉변</strong> — 닉네임 1회 변경 (지속)</li>
            <li><strong>💌 강일</strong> — 강제 일방 12시간 개설</li>
            <li><strong>🖍 프변</strong> — 프로필·닉 뒤 이모/텍스트 3일 등록</li>
            <li><strong>📝 색변</strong> — 프로필 색 1회 변경·방어</li>
            <li><strong>🫵 지목</strong> — 이성 2명 지목 이벤트 6시간</li>
          </ul>

          <p class="guide-note">템 직접 양도 불가 (선물 아이템 제외)</p>
        </div>
      </details>
    </section>

    <section class="guide-section" id="links">
      <details class="guide-card" open>
        <summary>🔗 웹 바로가기</summary>
        <div class="guide-body">
          <p>가방·채굴·게임 등 <strong>웹 서비스</strong>를 쓰려면 연구실에서 발급받은 <strong>인증코드</strong>가 필요해요.</p>
          <p class="guide-note">코드가 없다면 <strong>연구실</strong>로 와서 코드를 받아 주세요.</p>
          <div class="guide-links">
            <a href="/shop/">🏪 도하 마켓</a>
            <a href="/page/wallet.php">🎒 개인 가방</a>
            <a href="/page/mining.php">⛏️ 채굴</a>
            <a href="/page/boss.php">🐉 보스전</a>
            <a href="/page/game.php">🎮 게임 모음</a>
          </div>
        </div>
      </details>
    </section>

  </main>

  <footer class="guide-footer">
    우리방 사용설명서 · <?= htmlspecialchars($guide_url, ENT_QUOTES, 'UTF-8') ?>
  </footer>

</div>
</body>
</html>
