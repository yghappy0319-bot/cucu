<?php
/**
 * info.php / info3.php 공통 조건 처리
 * include 전에 $nick, $msg, $두자리닉넴, $status 가 설정되어 있어야 함.
 * 공통 명령 처리 시 전송 후 exit, 미처리 시 그대로 복귀.
 */
include_once __DIR__ . '/_bonbang.php';

if (!function_exists('공일방_현황문구')) {
  function 공일방_현황문구() {
    $buf = '';
    $result = db_query("SELECT name1, name2, edate FROM tb_kong_room WHERE edate > NOW() ORDER BY edate ASC");
    if ($result) {
      $i = 0;
      while ($row = db_fetch($result)) {
        $ed = strtotime($row['edate']);
        if ($ed === false) {
          continue;
        }
        $i++;
        $st = $ed - 3600;
        $buf .= "{$i}) {$row['name1']} · {$row['name2']}\n";
        $buf .= '시작: ' . date('m-d H:i', $st) . "\n".'종료: ' . date('m-d H:i', $ed) . "\n";
      }
    }
    return $buf !== '' ? $buf : "(예정 없음)\n";
  }
}

if($status==".신입"){
  $msg = "🐥신입 필독!!\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  $msg .= "

🎙️보이스룸
- 봇 제외 3인 이상시 마이크 ON!

📣공통
본인이 쓴 메시지 삭제 🚫절대 금지🚫
나이 공개는 보룸에서만!
100타수 달성 시 자동 출석완료!

24시간 기준 1건의 대화 없는경우 킥👋🏻 
인사는 하고 살자!

궁금한건 언제든지 공창에 물어봐줘!
더 상세한 규칙은 .공지 입력해보면돼!

궁금한 친구들은 .궁금 (닉네임) 
으로 공창에 입력해보면
친구들의 정보를 볼 수 있어!

📋미션·일방
우리방은 .미션 이라고 입력해서
미션 수행을 해야만
일방을 갈 수 있어!

💰냥 시스템
우리방 시스템 중
냥이라는 게 있어!
채팅 1개당 0.1냥 누적돼!

잘지내보자🫰🏻";
  echo 전송($msg);
  exit;
}

// ----- 방 링크 (닉네임 없이도 응답) -----
if ($status === ".이사했어") {
  $msg = "🏠 이사가자!
  
1. 들어올 때 하트 꼭 누르기!
2. 2글자 닉네임 + 성별 
예) 길동 남, 춘향 여
이런식으로 입장하자!
  
{$본방주소}

";
  echo 전송($msg);
  exit;
}
if ($status === ".건의방") {
  $msg = "정식 일방신청 / 아이템 익명사용
⚠️3명 확인 후⚠️ 들어올것!
https://open.kakao.com/o/gtVuHkEh";
  echo 전송($msg);
  exit;
}

if (function_exists('자숙_명령_처리')) {
  자숙_명령_처리($status);
}
if (function_exists('자숙종료_명령_처리')) {
  global $관리자;
  자숙종료_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

if ($status === ".상황실") {
  $msg = "아이템 신청,사용,조회
(익명사용 원할시 건의방)

7명 확인 후 공창 닉네임으로 입장
https://open.kakao.com/o/g5GTdohi";
  echo 전송($msg);
  exit;
}

if ($status === ".커버") {
  $msg = "
얼공·보룸 자유 😆 출퇴도 편하게 ⏰
게임 자유 🎮 아이템전 가능 🔥
인증 없고 텃세 없는 편한 분위기 💛

#3045#전국기혼#기혼친목#기혼수다#서울기혼#경기기혼#인천기혼#대전기혼#강원기혼#천안기혼#아산기혼#청주기혼#충주기혼#충북기혼#충남기혼#전북기혼#전남기혼#경북기혼#경남기혼#대구기혼#부산기혼#울산기혼#제주기혼#해외기혼#냥살냥죽#지호썸        
  ";
  echo 전송($msg);
  exit;
}
if ($status === ".얼공방") {
  $msg = " 🚨 잡답금지!
본인 닉네임으로 입장!
🙏마감 필수🙏

https://open.kakao.com/o/gkgVhohi";
  echo 전송($msg);
  exit;
}
if (preg_match('/^\.얼공(?:[\s\p{Zs}]+|$)/u', trim($status))) {
  global $관리자;
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    exit;
  }
  if (!preg_match('/^\.얼공(?:[\s\p{Zs}]+)(.+)$/u', trim($status), $얼공m)) {
    echo 전송("❌ 사용법: .얼공 닉1 닉2 …\n예) .얼공 진우 도하 가은");
    exit;
  }
  $토큰 = preg_split('/[\s\p{Zs}]+/u', trim($얼공m[1]), -1, PREG_SPLIT_NO_EMPTY);
  $닉목록 = [];
  $seen = [];
  foreach ($토큰 as $t) {
    $nn = getTwoCharNick(trim($t));
    if ($nn !== '' && empty($seen[$nn])) {
      $seen[$nn] = true;
      $닉목록[] = $nn;
    }
  }
  if (empty($닉목록)) {
    echo 전송("❌ 닉네임을 확인해 주세요.\n예) .얼공 진우 도하 가은");
    exit;
  }
  $기록 = [];
  $이미 = [];
  foreach ($닉목록 as $nn) {
    if (미션_완료됨(미션_완료_맵($nn), '일방', '얼공')) {
      $이미[] = $nn;
    } else {
      미션완료_기록_if_new($nn, '일방', '얼공');
      $기록[] = $nn;
    }
  }
  $msg = '✅ 얼공 미션';
  if (!empty($기록)) {
    $msg .= "\n기록: " . implode(' · ', $기록);
  }
  if (!empty($이미)) {
    $msg .= "\n이미완료: " . implode(' · ', $이미);
  }
  echo 전송($msg);
  exit;
}
if ($status === ".소통방") {
  $msg = "이성/동성 상관없이
    하루 15분 1:1 소통방
마감 칠 것!
예)영수,민희 1시30분까지
https://open.kakao.com/o/gYXCqohi";
  echo 전송($msg);
  exit;
}
if ($status === ".사연방") {
  $msg = "📬사연방 : 익명으로 입장하기!
https://open.kakao.com/o/g51p7wei";
  echo 전송($msg);
  exit;
}

if ($status === ".위험인물") {
  $msg = "방을 해롭게 만들고 나간 친구들..

http://49.247.160.164/page/danger.php";
  echo 전송($msg);
  exit;
}

if ($status === ".꼬맨틀") {
  $오늘완료 = 꼬맨_오늘완료수();
  $잔여 = 꼬맨_오늘잔여();
  $한도 = 꼬맨_일일한도();
  $msg = "
꼬맨틀
오늘의 단어를 맞히는 게임! 

>> 유사도 순위 99 <<
매일 {$한도}명

오늘 참여 가능: {$잔여}명 ({$오늘완료}/{$한도}명 완료)

단어는 가리고 캡쳐해서 
공창에 하나 올리고 
상황실에 하나 올리고 
퇴장해주세요!

완료 후 상황실에서 `.꼬맨완료` 입력!
(관리자: `.꼬맨완료 닉네임` 으로 대리 등록 가능)
본방냥 1,000냥 지급!

https://semantle-ko.newsjel.ly/
";
  echo 전송($msg);
  exit;
}
if ($status === ".홍보방") {
  $msg = "프로필색, 닉네임 유지해줘

https://open.kakao.com/o/gxtQTRBh";
  echo 전송($msg);
  exit;
}
if ($status === ".본방") {
  $msg = "방을 이사 했어!!

넘어와서 같이 놀자!

{$본방주소}";
  echo 전송($msg);
  exit;
}

if ($status === ".조롱관") {
  $msg = "http://49.247.160.164/gallery";
  echo 전송($msg);
  exit;
}

if($status==".공질보기"){
  $msg = "http://49.247.160.164/page/search.php";
    echo 전송($msg);
    exit;
}
if($status==".색표"){
    $msg = "http://49.247.160.164/page/color_list.php";
      echo 전송($msg);
      exit;
}

if (trim($status) === '.일방준비') {
  $msg = "📋 일방 준비 안내\n\n";
  $msg .= "미션1 (신입 필수)\n";
  $msg .= "지목아이템, 강일아이템 한번씩 경험\n\n";
  $msg .= "미션2 (신입, 기존 멤버 포함)\n";
  $msg .= "칠타(7일간 타수) 기준\n5일간 꾸준히 400타 달성 시\n";
  $msg .= "일방티켓 자동생성됨, 냥으로 되팔기 가능\n\n";
  $msg .= "재입장자 신입기간 5일\n";
  $msg .= "일방신청 조건1,2 포함";
  echo 전송($msg);
  exit;
}

// .수호 — 상황실 봇(info3.php) 전용
if (preg_match('/^\.수호(?:\s|$)/u', trim((string)$status))) {
  if (!function_exists('상황실봇_요청여부') || !상황실봇_요청여부()) {
    echo 전송("❌ 수호 아이템은 상황실에서만 사용할 수 있어요.\n상황실에서 `.수호 (받을닉) [개수]` 으로 신청해 주세요.");
    exit;
  }
}

// ----- 닉 디버그 (회원 검증 전, 누구나) -----
if (trim((string)$status) === '.닉디버그') {
  echo 전송(nick_디버그_리포트(false));
  exit;
}

// ----- 회원 전용 공통 (두자리닉넴 있을 때만) -----
if (!$두자리닉넴) {
  $nick_src = nick_파라미터();
  if ($nick_src !== '') {
    $두자리닉넴 = getTwoCharNick($nick_src);
  }
}
if (!$두자리닉넴) {
  return;
}

include_once __DIR__ . "/config.php";

if (function_exists('db_ensure_connection')) {
  db_ensure_connection();
}

list($두자리닉넴, $정보) = 회원정보_동기화(nick_파라미터(), $두자리닉넴);
$계급 = 계급($정보['point'] ?? 0);
$호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');

if (trim((string)($정보['name'] ?? '')) === '') {
  if ($status !== '' && strpos(trim((string)$status), '.') === 0) {
    $debug = nick_디버그_리포트(true);
    echo 전송("❌ 등록된 회원만 명령어를 사용할 수 있어요.\n\n{$debug}\n\n상세 확인: `.닉디버그`");
    exit;
  }
  exit;
}

if (trim((string)$status) === '.내냥') {
  if (trim((string)($정보['name'] ?? '')) === '' && $두자리닉넴 !== '') {
    $정보 = 회원정보_조회($두자리닉넴);
    $계급 = 계급($정보['point'] ?? 0);
    $호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');
  }
  $msg1 = $호칭 . " {$두자리닉넴} " . newpoint표시($정보['newpoint'] ?? 0) . "{$단위}";
  echo 전송($msg1);
  exit;
}

if ($status === '.환전') {
  $포인트 = (int)($정보['point'] ?? 0);
  $msg = "💱 환전 ㅋㅋㅋ\n\n";
  $msg .= "{$호칭} {$두자리닉넴} 보유: " . 콤마삽입($포인트) . "{$단위}\n";
  $msg .= "환율: 천억{$단위} → 현금 1,000원\n\n";
  $msg .= "💵 환전하면 약 " . 환전현금표시($포인트) . "\n";
  echo 전송($msg);
  exit;
}

if (trim($status) === '.수령') {
  echo 전송("❌ `.수령`은 홍보방에서만 이용할 수 있어요.");
  exit;
}

if (function_exists('가방_명령_처리')) {
  가방_명령_처리($status, $호칭, $정보, $두자리닉넴);
}

if (preg_match('/^\.메뉴추천(?:\s+(.*))?$/u', trim($status), $menuMatch)) {
  $요청원문 = trim((string)($menuMatch[1] ?? ''));
  $허용카테고리 = ['한식', '중식', '일식', '양식', '소주안주', '맥주안주'];
  $요청카테고리 = [];
  if ($요청원문 !== '') {
    $parts = preg_split('/[\s,\/]+/u', $요청원문, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $p) {
      $p = trim($p);
      if (in_array($p, $허용카테고리, true) && !in_array($p, $요청카테고리, true)) {
        $요청카테고리[] = $p;
      }
    }
    if (empty($요청카테고리)) {
      echo 전송("🍱 메뉴추천 사용법\n.메뉴추천\n.메뉴추천 한식,중식,일식,양식\n.메뉴추천 소주안주,맥주안주\n(쉼표/띄어쓰기 둘 다 가능)");
      exit;
    }
  }
  $카테고리문구 = empty($요청카테고리) ? '전체(한식/중식/일식/양식/소주안주/맥주안주)' : implode('/', $요청카테고리);

  $닉_esc = addslashes($두자리닉넴);
  $sql = "SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(content, '\n', 2), '\n', -1) AS second_line FROM tb_member WHERE name = '{$닉_esc}' ";
  $지역행 = db_select($sql);
  $사는곳 = preg_replace('/[🥕🍭❄️🤍]\s*지역\(시•군\)\s*:\s*/u', '', $지역행['second_line'] ?? '');
  $사는곳 = trim($사는곳);

  if ($사는곳 === '') {
    echo 전송("🍱 점메추천\n프로필 두 번째 줄에\n🤍 지역(시•군) : ○○\n형식으로 지역을 적어줘!");
    exit;
  }

  $점메비용 = 50000;
  if (!$정보['point'] || (int)$정보['point'] < 0) {
    echo 전송("❌ 보유 포인트 없음");
    exit;
  }
  if ((int)$정보['point'] < $점메비용) {
    echo 전송("❌ 냥 부족! 점메추천은 {$점메비용}{$단위} 필요 (현재 보유: " . number_format($정보['point']) . "{$단위})");
    exit;
  }

  $요일표 = ['일', '월', '화', '수', '목', '금', '토'];
  $오늘요일 = $요일표[(int)date('w')];
  $오늘날짜 = date('Y-m-d') . " ({$오늘요일})";

  $gender_raw = (int)($정보['gender'] ?? 0);
  if ($gender_raw === 1) {
    $성별문구 = '남';
    $성별gpt = '남성 회원으로 등록됨';
  } elseif ($gender_raw === 2) {
    $성별문구 = '여';
    $성별gpt = '여성 회원으로 등록됨';
  } else {
    $성별문구 = '';
    $성별gpt = '성별 미등록 — 중립적으로 추천 (남녀 구분 없이)';
  }

  $gpt질문 = "오늘 날짜: {$오늘날짜}\n"
    . "이 사람이 사는 곳(프로필): 한국 「{$사는곳}」\n"
    . "질문자 성별(방 DB): {$성별gpt}\n"
    . "요청 카테고리: {$카테고리문구}\n\n"
    . "실시간 날씨 API는 없어. 네가 그 지역·이 날짜·계절 감각으로 오늘 날씨를 대충 가정해도 돼(틀려도 됨).\n"
    . "성별이 남/여로 적혀 있으면, 같은 날씨라도 메뉴 구성·톤(든든함/가벼움·양·매운 정도 등)을 살짝 다르게 잡아줘. 편견·비하·고정관념 과장은 금지. 미등록이면 누구에게나 무난한 후보로.\n"
    . "메뉴는 반드시 요청 카테고리 안에서만 골라줘. 요청이 '전체'면 한식/중식/일식/양식/소주안주/맥주안주 중 자유 추천.\n"
    . "소주안주/맥주안주 요청 시에는 식사 메뉴보다 안주 위주(예: 탕, 구이, 튀김, 마른안주)로 추천해줘.\n"
    . "3가지는 서로 겹치지 않게(한식만이면 조리법·재료·형태가 다른 것), 비빔밥·김치찌개·된장찌개처럼 너무 흔한 조합만 반복하지 말고 이번 답에서는 가능하면 피해줘.\n"
    . "그 가정에 맞춰 점심 메뉴 3가지를 추천해줘.\n\n"
    . "답변 형식(총 4줄):\n"
    . "1줄: (가정) 대략 ○°C 전후 · 날씨 한마디\n"
    . "2~4줄: 각각 '• 메뉴 — 이유 한 마디(짧게)'\n"
    . "말투는 친한 친구. 이모지 전체 2개 이하.";

  $gpt시스템 = '너는 한국 홍보방(채팅방) 점심 추천 봇이야. 한식·외식 위주로 가볍게, 유머는 살짝만. 매번 비슷한 국민 메뉴만 고르지 말고 다양하게. 성별은 통계적 경향 수준으로만 살짝 반영하고, 개인 취향은 다를 수 있다는 태도를 유지해.';

  if (!function_exists('callGPT')) {
    echo 전송("🍱 점메추천\nGPT 연동이 설정되지 않았어. 관리자에게 문의해줘.");
    exit;
  }

  $gpt답 = callGPT($gpt질문, $gpt시스템, 480);
  if (!is_string($gpt답)) {
    $gpt답 = '';
  }
  $gpt답 = trim($gpt답);

  if ($gpt답 === '') {
    $gpt질문간단 = "{$오늘날짜} / 한국 「{$사는곳}」 / {$성별gpt} / 카테고리: {$카테고리문구}\n"
      . "위 정보로 오늘 점심 메뉴 3가지만 추천. 4줄: 1줄 날씨·기온 가정, 2~4줄은 각각 메뉴+짧은 이유. 메뉴는 요청 카테고리만 사용. 서로 다른 메뉴, 비빔밥·김치찌개·된장찌개만 반복하지 말 것.";
    $gpt답 = trim((string)callGPT($gpt질문간단, $gpt시스템, 480));
  }

  if ($gpt답 === '') {
    echo 전송("🍱 메뉴추천\nGPT가 잠시 응답을 못 했어. 냥은 안 나갔어!\n잠시 후 `.메뉴추천` 다시 해봐.");
    exit;
  }

  $gpt답 = preg_replace("/\r\n|\r/", "\n", $gpt답);
  if (mb_strlen($gpt답, 'UTF-8') > 550) {
    $gpt답 = mb_substr($gpt답, 0, 550, 'UTF-8') . '…';
  }

  $msg = "🍱 {$두자리닉넴} 메뉴추천\n📍 {$사는곳} · 🍽 {$카테고리문구}";
  if ($성별문구 !== '') {
    $msg .= " · 👤 {$성별문구}";
  }
  $msg .= "\n🤖\n" . $gpt답;

  $닉_db_esc = addslashes($두자리닉넴);
  db_query("UPDATE tb_member SET point = point - {$점메비용} WHERE name = '{$닉_db_esc}' ");
  $msg .= "\n\n💰 -" . number_format($점메비용) . "{$단위} 차감";

  echo 전송($msg);
  exit;
}

if (preg_match('/^\.맛집(?:\s+(.*))?$/u', trim($status), $맛집Match)) {
  $맛집지역 = trim((string)($맛집Match[1] ?? ''));
  $맛집지역 = preg_replace('/\s+/u', ' ', $맛집지역);
  $맛집지역 = str_replace(["\r", "\n", "\x00"], '', $맛집지역);
  if ($맛집지역 === '') {
    echo 전송("🍴 맛집 추천 사용법\n.맛집 경기도 고양시\n.맛집 강남역\n(띄어쓰기 뒤에 지역·동네를 적어줘)\n💰 회당 5만{$단위} · GPT가 3곳만 추천\n※ 실제 영업·위치는 직접 확인해줘!");
    exit;
  }
  if (mb_strlen($맛집지역, 'UTF-8') > 80) {
    $맛집지역 = mb_substr($맛집지역, 0, 80, 'UTF-8');
  }

  $맛집비용 = 50000;
  if (!$정보['point'] || (int)$정보['point'] < 0) {
    echo 전송("❌ 보유 포인트 없음");
    exit;
  }
  if ((int)$정보['point'] < $맛집비용) {
    echo 전송("❌ 냥 부족! 맛집 추천은 {$맛집비용}{$단위} 필요 (현재 보유: " . number_format($정보['point']) . "{$단위})");
    exit;
  }

  if (!function_exists('callGPT')) {
    echo 전송("🍴 맛집 추천\nGPT 연동이 설정되지 않았어. 관리자에게 문의해줘.");
    exit;
  }

  $gpt맛집질문 = "지역: 한국 「{$맛집지역}」\n\n"
    . "이 근처(또는 그 지역에서 찾기 쉬운 곳) 맛집·식당을 딱 3곳만 추천해줘.\n"
    . "형식(3줄만):\n"
    . "• 1) 가게 이름 — 대표 메뉴·특징 한 줄\n"
    . "• 2) …\n"
    . "• 3) …\n"
    . "실존하는 곳 위주로. 불확실하면 '(참고)'라고 적어줘. 이모지 2개 이하. 다른 설명 없이 위 3줄만.";

  $gpt맛집시스템 = '너는 한국 지역 맛집 추천 봇이야.';

  $gpt맛집답 = callGPT($gpt맛집질문, $gpt맛집시스템, 420);
  if (!is_string($gpt맛집답)) {
    $gpt맛집답 = '';
  }
  $gpt맛집답 = trim($gpt맛집답);

  if ($gpt맛집답 === '') {
    $gpt맛집답 = trim((string)callGPT(
      "한국 「{$맛집지역}」 맛집 3곳만. 각 줄: • 이름 — 한 줄. 3줄만.",
      $gpt맛집시스템,
      420
    ));
  }

  if ($gpt맛집답 === '') {
    echo 전송("🍴 맛집 추천\nGPT가 잠시 응답을 못 했어. 냥은 안 나갔어!\n잠시 후 `.맛집 {$맛집지역}` 다시 해봐.");
    exit;
  }

  $gpt맛집답 = preg_replace("/\r\n|\r/", "\n", $gpt맛집답);
  if (mb_strlen($gpt맛집답, 'UTF-8') > 600) {
    $gpt맛집답 = mb_substr($gpt맛집답, 0, 600, 'UTF-8') . '…';
  }

  $msg = "🍴 {$두자리닉넴} 맛집 추천\n📍 {$맛집지역}\n🤖\n" . $gpt맛집답;
  $닉_db_esc = addslashes($두자리닉넴);
  db_query("UPDATE tb_member SET point = point - {$맛집비용} WHERE name = '{$닉_db_esc}' ");
  $msg .= "\n\n💰 -" . number_format($맛집비용) . "{$단위} 차감\n※ 지도·영업시간은 직접 확인!";

  echo 전송($msg);
  exit;
}


if (trim($status) === '.닉추천') {
  if (!function_exists('callGPT')) {
    echo 전송("🎯 닉추천\nGPT 연동이 설정되지 않았어. 관리자에게 문의해줘.");
    exit;
  }

  $닉추천금지닉 = ['민호', '도현', '지호', '아영'];

  $기존닉맵 = [];
  foreach ($닉추천금지닉 as $금지닉) {
    $기존닉맵[$금지닉] = true;
  }
  $기존결과 = db_query("SELECT name FROM tb_member WHERE status = 0");
  if ($기존결과) {
    while ($기존행 = db_fetch($기존결과)) {
      $기존닉 = trim((string)($기존행['name'] ?? ''));
      if ($기존닉 !== '' && preg_match('/^[가-힣]{2}$/u', $기존닉)) {
        $기존닉맵[$기존닉] = true;
      }
    }
  }

  $닉추천금지여부 = function ($닉) use ($닉추천금지닉) {
    if (in_array($닉, $닉추천금지닉, true)) {
      return true;
    }
    return mb_strpos($닉, '은', 0, 'UTF-8') !== false;
  };
  $기존닉배열 = array_keys($기존닉맵);
  $기존닉문구 = implode(', ', $기존닉배열);
  if (mb_strlen($기존닉문구, 'UTF-8') > 1200) {
    $기존닉문구 = mb_substr($기존닉문구, 0, 1200, 'UTF-8') . '…(이하 생략, 총 ' . count($기존닉배열) . '명)';
  }

  $닉추천검증 = function ($raw, $기존닉맵) use ($닉추천금지여부) {
    if (!is_array($raw)) {
      return null;
    }
    $남 = isset($raw['male']) && is_array($raw['male']) ? $raw['male'] : [];
    $여 = isset($raw['female']) && is_array($raw['female']) ? $raw['female'] : [];
    if (count($남) !== 5 || count($여) !== 5) {
      return null;
    }
    $사용 = [];
    $정리 = ['male' => [], 'female' => []];
    foreach (['male' => $남, 'female' => $여] as $키 => $목록) {
      foreach ($목록 as $닉) {
        $닉 = trim((string)$닉);
        if (!preg_match('/^[가-힣]{2}$/u', $닉)) {
          return null;
        }
        if ($닉추천금지여부($닉) || !empty($기존닉맵[$닉]) || isset($사용[$닉])) {
          return null;
        }
        $사용[$닉] = true;
        $정리[$키][] = $닉;
      }
    }
    return $정리;
  };

  $닉추천파싱 = function ($answer) use ($닉추천검증, $기존닉맵) {
    $answer = trim((string)$answer);
    if ($answer === '') {
      return null;
    }
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/su', $answer, $fence)) {
      $answer = trim($fence[1]);
    }
    if (preg_match('/\{.*\}/su', $answer, $jsonMatch)) {
      $answer = $jsonMatch[0];
    }
    $data = json_decode($answer, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return null;
    }
    return $닉추천검증($data, $기존닉맵);
  };

  $gpt시스템 = '너는 한국 오픈채팅방 2글자 닉네임 추천 봇이야. 지시한 JSON 형식만 출력하고 다른 말은 하지 마.';
  $gpt질문 = "카카오 오픈채팅방용 한글 2글자 닉네임을 추천해줘.\n"
    . "남자 닉 5개, 여자 닉 5개. 각 닉은 정확히 한글 2음절(가~힣)만.\n"
    . "아래는 이미 방에 있는 회원 닉이야. 이 목록과 겹치면 안 돼:\n"
    . ($기존닉문구 !== '' ? $기존닉문구 : '(없음)') . "\n\n"
    . "남자 닉은 남성스럽거나 중성적으로 쓸 수 있는 느낌, 여자 닉은 여성스럽거나 중성적으로 쓸 수 있는 느낌으로.\n"
    . "실명·유명인·비속어·혐오·성적 표현 금지. 10개 모두 서로 달라야 해.\n"
    . "절대 추천 금지: 민호, 도현, 지호, 아영, '은' 글자가 들어간 닉(예: 하은, 은비).\n"
    . "반드시 아래 JSON만 출력:\n"
    . '{"male":["닉1","닉2","닉3","닉4","닉5"],"female":["닉1","닉2","닉3","닉4","닉5"]}';

  $추천결과 = null;
  for ($시도 = 0; $시도 < 2; $시도++) {
    $gpt답 = callGPT($gpt질문, $gpt시스템, 400);
    if (!is_string($gpt답)) {
      $gpt답 = '';
    }
    $추천결과 = $닉추천파싱($gpt답);
    if ($추천결과 !== null) {
      break;
    }
    if ($시도 === 0) {
      $gpt질문 = "기존 회원 닉(절대 사용 금지): " . ($기존닉문구 !== '' ? $기존닉문구 : '없음') . "\n"
        . "추가 금지: 민호, 도현, 지호, 아영, '은' 포함 닉.\n"
        . "한글 2글자 남자 닉 5개·여자 닉 5개. JSON만: {\"male\":[...5개],\"female\":[...5개]}";
    }
  }

  if ($추천결과 === null) {
    echo 전송("🎯 닉추천\nGPT가 잠시 응답을 못 했어.\n잠시 후 `.닉추천` 다시 해봐.");
    exit;
  }

  $msg = "🎯 2글자 닉추천\n\n";
  $msg .= "👨 남자\n";
  foreach ($추천결과['male'] as $i => $닉) {
    $msg .= ($i + 1) . ". {$닉} 남\n";
  }
  $msg .= "\n👩 여자\n";
  foreach ($추천결과['female'] as $i => $닉) {
    $msg .= ($i + 1) . ". {$닉} 여\n";
  }
  $msg .= "\n※ 방에 이미 있는 닉은 제외했어. 중복이면 다른 닉으로 바꿔 써줘!";

  echo 전송(trim($msg));
  exit;
}

if (empty($MUTUAL_SKIP_ODD_EVEN)) {
  require_once __DIR__ . '/game/odd_even_mutual.inc.php';
}

if (trim($status) === '.사용설명서' || trim($status) === '.가이드') {
  $host = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
    ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
    : 'http://49.247.160.164';
  echo 전송("📖 우리방 사용설명서\n\n모바일에서 보기 편하게 정리했어요!\n{$host}/guide/");
  exit;
}

if ($status === ".냥이란") {
  $msg = "1️⃣ 냥이 뭐예요? 🐱\n";
  $msg .= "
우리 방 포인트는 두 가지예요!

💰 기본냥(보유냥)
- 채팅할 때마다 자동 적립!
- 1타 = 0.1냥 · 사진 2타 = 0.2냥
- (마법/지호 적용 시 2타=0.2 · 사진3타=0.3)
- 양도, 아이템, 주급/보급, 신입지원 등에 써요

🎮 게임냥(게임할때 쓰는 냥)
- 맞다이, 야바위, 무기 강화, 레벨업 등 게임에 써요
- .궁금 본인닉 · .랭킹2 로 확인 가능

2️⃣ 어디에 사용해요?
• 기본냥 → 아이템 구매/판매, 양도, 방 보상
• 게임냥 → 각종 게임, 무기 강화

3️⃣ 어떻게 얻어요?
• 기본냥 → 채팅 타수(0.1냥씩), 출석, 퀴즈, 주급/보급, 이벤트 등
• 게임냥 → 게임(맞다이·야바위), 아이템 구매/판매 등

4️⃣ 냥 관련 자주 쓰는 명령어 💡
• 모금: .모금 (자숙시간 단축 지원)
• 양도: .양도 닉네임 금액 (기본냥 보내기)
• 내 보유냥: .내냥 (기본냥 확인)
• 환전: .환전 (게임냥 기준 천억냥=천원 환산 ㅋ)
• 랭킹: .랭킹1 숫자 (보유냥) · .랭킹2 숫자 (게임냥) · .랭킹3 숫자 (채굴 장비)";

  echo 전송($msg);
  exit;
}

if (trim($status) === ".랜덤박스란") {
  $msg = "🎁 랜덤박스란?\n\n";
  $msg .= "1️⃣ 랜덤박스가 뭐예요?\n";
  $msg .= "- 까면 그때마다 다른 보상이 무작위로 나와요.\n\n";
  $msg .= "2️⃣ 나올 수 있는 보상\n";
  $msg .= "- 💰 게임냥(1만~50만)만 나와요.\n\n";
  $msg .= "3️⃣ 어떻게 써요?\n";
  $msg .= "- 게임방 입장 후 `.ㄹㄷ 10` 처럼 숫자를 붙여 입력해요. (한 번에 10개 이상만 일괄로 깔 수 있어요)\n\n";
  $msg .= "4️⃣ 어떻게 모으나요?\n";
  $msg .= "- 레벨업·출석·활동 달성 등으로 지급될 수 있어요.";
  echo 전송($msg);
  exit;
}

if (trim($status) === ".채굴란") {
  require_once __DIR__ . '/game/mining_chat_help.inc.php';
  echo 전송(mining_chat_help_message());
  exit;
}

if (empty($MUTUAL_SKIP_DEPOSIT) && (strpos($status, '.입금') !== false || strpos($status, '.지급') !== false)) {
  if (preg_match('/\.(?:입금|지급)\s*(.+)/u', $status, $match)) {
    $after = trim($match[1]);
    if (in_array(getTwoCharNick($nick), $관리자)) {
      if (preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
        $받는닉 = trim($parts[1]);
        $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : (int)trim($parts[2]);
        if ($지급양 === 0) {
          echo 전송("❌ 금액 형식을 확인해주세요.\n예) .입금 다오 5조 / .입금 뮤뮤 26경8천조");
          exit;
        }
        if ($받는닉 === "전체") {
          db_query("update tb_member set point = point + {$지급양} where status = 0 ");
          $msg = "📣 전체 인원에게 ".number_format($지급양)." {$단위} 지급!";
          $받는친구['name'] = "전체";
          $지급여부 = "지급";
        } elseif ($받는닉 === "랜덤박스") {
          $result = db_query("SELECT name FROM tb_member WHERE status = 0");
          while ($row = db_fetch($result)) {
            for ($i = 0; $i < $지급양; $i++) {
              db_query("
                INSERT INTO tb_member_item
                SET nick = '{$row['name']}', itemname = '랜덤박스', status = 0, regdate = NOW()
              ");
            }
          }
          $msg = "📣 전체 인원에게 랜덤박스 {$지급양}개씩 입금 완료!";
          $받는친구['name'] = "전체";
          $지급여부 = "입금";
        } else {
          $sql = "select * from tb_member where name = '{$받는닉}' ";
          $받는친구 = db_select($sql);
          if (!$받는친구['idx']) {
            echo 전송("존재하지 않는 사용자 입니다.");
            exit;
          }
          if ($지급양 < 0) {
            $차감양 = abs($지급양);
            $지급 = "point - {$차감양}";
            $지급여부 = "차감";
            $표시금액 = $차감양;
          } else {
            $지급 = "point + {$지급양}";
            $지급여부 = "입금";
            $표시금액 = $지급양;
          }
          $sql = "update tb_member set point = {$지급} where name = '{$받는닉}'  ";
          db_query($sql);
          $금액표시 = function_exists('게임냥_경조억표시') ? 게임냥_경조억표시($표시금액) : number_format($표시금액);
          $msg = "💰 {$받는친구['name']}에게 {$금액표시} {$지급여부}! ";
        }
        지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양);
        지급로그($지급여부, $받는친구['name'], $두자리닉넴, 0, $지급양);
        echo 전송($msg);
        exit;
      }
    } else {
      echo 전송($두자리닉넴 . " 블랙리스트 등록완료!");
      exit;
    }
  }
  if (isset($msg)) {
    echo 전송($msg);
  }
  exit;
}
if(strpos($status, '.금고란') !== false){

  if (!in_array($두자리닉넴, $관리자, true)) {
    exit;
  }

  $tax = (int)($설정['tax'] ?? 0);
  
  $msg = "📦 금고란? 현재 " . (function_exists('냥축약표시') ? 냥축약표시($tax, isset($단위) ? $단위 : '냥') : number_format($tax) . (isset($단위) ? $단위 : '냥')) . "\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  
  $msg .= "1️⃣ 금고가 뭐야?\n";
  $msg .= "- 우리방 공동 통장 같은 곳이에요.\n";
  $msg .= "- 친구들의 활동에서 빠지는 각종 수수료, 패널티 냥이 여기 모여요.\n\n";
  $msg .= "2️⃣ 금고는 어떻게 채워져?\n";
  $msg .= "- 양도/상점/판매 등에서 빠지는 수수료가 설정에 따라 금고로 들어와요.\n";
  $msg .= "- 무기 .수리 비용의 5%는 금고, 5%는 로또로 들어와요.\n";
  $msg .= "- .금고 금고털이 실패 시, 내 게임냥의 1~3%가 차감되고\n";
  $msg .= "  그 중 50%가 다시 금고로 환수돼서 점점 쌓여요.\n";
  $msg .= "- 퇴사 처리 시 등록 후 3일이 지난 회원의 보유 냥만 금고로 귀속돼요.\n\n";
  $msg .= "3️⃣ 금고는 어떻게 털어?\n";
  $msg .= "- 채팅창에 '.금고' 를 입력하면 금고털이를 시도해요.\n";
  $msg .= "- 성공 시 금고의 10%·20%·30% 중 하나를 랜덤 획득!\n";
  $msg .= "- 아주 낮은 확률로 금고 전액을 털어가는 대박도 있어요!\n";
  $msg .= "- 실패 시 내 게임냥의 1%·2%·3% 차감 (시도 등급에 따라)\n";
  $msg .= "  차감 냥의 50%는 소멸, 50%는 다시 금고로 들어가요.\n\n";
  $msg .= "4️⃣ 금고마감은 뭐야?\n";
  $msg .= "- 매일 밤 22:50 이후, 관리자만 '.금고마감' 을 눌러서\n";
  $msg .= "  오늘 타수 1등에게 금고의 10%를 지급해요.\n\n";

  echo 전송($msg);
  exit;


}
if ($status === ".이사가자") {
  $msg = "🏠 이사가자!\n\n";
  $msg .= "1. 프로필색상, 닉네임 유지해 줘\n";
  $msg .= "2. 들어올 때 하트 누르고 입장해줘~\n\n";
  
  $msg .= "한명도 빠짐없이 넘어왓~\n";

  $msg .= "{$본방주소}\n";
  echo 전송($msg);
  exit;
}


if (preg_match('/^\.(.+?)의보호(?:\s+(.+))?$/u', trim($status), $보호매치)) {
  $cmd닉 = trim($보호매치[1]);
  $대상닉 = isset($보호매치[2]) ? trim($보호매치[2]) : '';
  $호출자 = getTwoCharNick($nick);
  if ($cmd닉 === '' || $호출자 === '') {
    echo 전송("❌ 사용법: .(닉네임)의보호  또는  .(닉네임)의보호 (대상닉네임)");
    exit;
  }
  if ($cmd닉 !== $호출자) {
    echo 전송("❌ 본인 닉네임만 사용할 수 있어요. (예: .{$호출자}의보호)");
    exit;
  }
  if (!in_array($호출자, $관리자)) {
    echo 전송("❌ 관리자만 사용할 수 있어요.");
    exit;
  }
  if ($대상닉 !== '') {
    $대상_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT idx, protect FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
    if (empty($대상정보['idx'])) {
      echo 전송("❌ [{$대상닉}] 회원을 찾을 수 없어요.");
      exit;
    }
    $새보호 = (int)($대상정보['protect'] ?? 0) + 1;
    db_query("UPDATE tb_member SET protect = {$새보호} WHERE name = '{$대상_esc}'");
    echo 전송("🛡️ {$호출자}의 보호 시전!\n[{$대상닉}] 보호 수치 +1 (현재 {$새보호})");
  } else {
    db_query("UPDATE tb_member SET protect = COALESCE(protect, 0) + 1 WHERE TRIM(COALESCE(name, '')) != ''");
    echo 전송("🛡️ {$호출자}의 보호 시전!\n전체 친구들의 보호 수치가 +1 증가했어요.");
  }
  exit;
}

if (strpos($status, '.랭킹2') !== false) {
  관리자전용_확인($두자리닉넴, $관리자);
  $orderby = '';
  $desc = 0;
  if (preg_match('/\.랭킹2\s*(.+)/u', $status, $match)) {
    $desc = (int)trim($match[1]);
    if ($desc > 0) {
      $orderby = " limit {$desc} ";
    }
  }
  $sql = "SELECT name, level, title, point FROM tb_member ORDER BY point DESC {$orderby}";
  $result = db_query($sql);
  $a = 1;
  if ($desc < 10) {
    $랭킹 = "✅ 게임{$단위} 랭킹\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  } else {
    $랭킹 = "✅ 게임{$단위} 랭킹\n\n";
  }
  while ($row = db_fetch($result)) {
    $계급 = 계급($row['point']);
    $호칭 = $row['title'] ?: $계급['name'];
    $랭킹 .= $a . "등{$이모티콘} Lv {$row['level']} {$호칭} " . $row['name'] . " " . 랭킹_게임냥표시($row['point'] ?? 0, $단위) . "\n";
    $a++;
  }
  echo 전송($랭킹);
  exit;
}

if (empty($MUTUAL_SKIP_RANKING3) && strpos($status, '.랭킹3') !== false) {
  require_once __DIR__ . '/game/mining_tool.inc.php';
  $desc = 0;
  if (preg_match('/\.랭킹3\s*(.+)/u', $status, $match)) {
    $desc = (int)trim($match[1]);
  }
  echo 전송(mining_tool_ranking_message($desc, $이모티콘));
  exit;
}

if (strpos($status, '.랭킹1') !== false) {
  관리자전용_확인($두자리닉넴, $관리자);
  $orderby = '';
  $desc = 0;
  if (preg_match('/\.랭킹1\s*(.+)/u', $status, $match)) {
    $desc = (int)trim($match[1]);
    if ($desc > 0) {
      $orderby = " limit {$desc} ";
    }
  }
  $sql = "SELECT name, level, title, point, CAST(IFNULL(newpoint, 0) AS DECIMAL(20,1)) AS newpoint FROM tb_member ORDER BY newpoint DESC {$orderby}";
  $result = db_query($sql);
  $a = 1;
  if ($desc < 10) {
    $랭킹 = "✅ {$단위} 보유랭킹\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  } else {
    $랭킹 = "✅ {$단위} 보유랭킹\n\n";
  }
  while ($row = db_fetch($result)) {
    $계급 = 계급($row['point']);
    $호칭 = $row['title'] ?: $계급['name'];
    $보유냥 = newpoint표시($row['newpoint'] ?? 0);
    $랭킹 .= $a . "등{$이모티콘} Lv {$row['level']} {$호칭} " . $row['name'] . " " . $보유냥 . "{$단위}\n";
    $a++;
  }
  echo 전송($랭킹);
  exit;
}

if(strpos(trim($status), '.은총') === 0){
    global $관리자;
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용 가능한 명령어입니다.");
      exit;
    }

    $입력 = trim($status);
    $대상닉 = $두자리닉넴;
    $지급개수 = 1;

    if (preg_match('/^\.은총\s+전체(?:\s+(\d+))?\s*$/u', $입력, $전체m)) {
      if (isset($전체m[1]) && $전체m[1] !== '') {
        $지급개수 = (int)$전체m[1];
      }
      if ($지급개수 < 1) {
        $지급개수 = 1;
      }
      $rs = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$지급개수} WHERE status = 0");
      if (!$rs) {
        echo 전송("❌ 전체 은총 지급에 실패했습니다.");
        exit;
      }
      $인원행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member WHERE status = 0");
      $인원 = (int)($인원행['cnt'] ?? 0);
      echo 전송("✨ 전체 인원 {$인원}명에게 은총 {$지급개수}개씩 지급 완료!");
      exit;
    }

    if (preg_match('/^\.은총\s+(\S+)\s+(\d+)$/u', $입력, $m)) {
      $대상닉 = trim($m[1]);
      $지급개수 = (int)$m[2];
    } else if (preg_match('/^\.은총\s+(\S+)/u', $입력, $m)) {
      $대상닉 = trim($m[1]);
    }
    if ($지급개수 < 1) {
      $지급개수 = 1;
    }

    $대상닉_esc = addslashes($대상닉);
    $대상정보 = db_select("SELECT idx, 은총개수 FROM tb_member WHERE name = '{$대상닉_esc}' LIMIT 1");
    if (empty($대상정보['idx'])) {
      echo 전송("❌ {$대상닉} 닉네임을 찾을 수 없습니다.");
      exit;
    }
    $rs = db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$지급개수} WHERE name = '{$대상닉_esc}'");
    if (!$rs) {
      echo 전송("❌ {$대상닉} 은총 지급에 실패했습니다.");
      exit;
    }
    $현재보유 = (int)($대상정보['은총개수'] ?? 0) + $지급개수;
    echo 전송("✨ {$대상닉} 은총 {$지급개수}개 지급 완료! (현재 보유 {$현재보유}개)");
    exit;
}


if(strpos($status, '.퇴사') !== false){
    global $관리자;
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
        echo 전송("❌ 관리자만 사용할 수 있어요.");
        exit;
    }
    if (preg_match('/\.퇴사\s*(.+)/u', $status, $match)) {
        $방탈자 = trim($match[1]); // ".양도" 뒤 텍스트 추출
        $회원 = db_select("select * from tb_member where name = '{$방탈자}' ");

        // 퇴사 시 보유 냥을 금고로 이관 (등록 후 3일 지난 경우에만)
        $보유냥 = isset($회원['point']) ? (int)$회원['point'] : 0;
        $등록시각 = isset($회원['regdate']) ? strtotime($회원['regdate']) : 0;
        $금고이전냥 = 0;
        $퇴사_금고귀속_최소가입초 = 3 * 86400;
        if ($보유냥 > 0 && $등록시각 && (time() - $등록시각) >= $퇴사_금고귀속_최소가입초) {
          db_query("update config set tax = tax + {$보유냥}");
          $금고이전냥 = $보유냥;
        }

        if (function_exists('회원_채굴_전체삭제')) {
          회원_채굴_전체삭제($회원['name']);
        }

        db_query("delete tb_member_item where midx = {$회원['idx']} ");
        db_query("delete tb_member_item where nick = '' ");
        db_query("delete from tb_point_log where nick = '{$회원['name']}'");
        db_query("delete from tb_work where nick = '{$회원['name']}'");
        db_query("delete from tb_msg where nickname = '{$회원['name']}'");
        db_query("delete from tb_item_use where nickname = '{$회원['name']}'");
        $result = db_query("delete from tb_member where idx = {$회원['idx']} ");

        if($result){
          $이전안내 = $금고이전냥 > 0 ? "\n보유 냥 ".number_format($금고이전냥) . "냥이 금고로 이전되었어요." : "";
          echo 전송("{$방탈자} 잘가👋🏻 또만나자!{$이전안내}");
          exit;
        }else{
          echo 전송("없음");
          exit;
        }
    }
}


if(strpos($status, '.평타') !== false){
  관리자전용_확인($두자리닉넴, $관리자);
  $날짜 = date('Y-m-d');
  if (preg_match('/\.평타\s+(\d{4}-\d{2}-\d{2})/u', $status, $matches)) {
        $날짜 = $matches[1]; // 캡처된 날짜
    }

  $sql = "
  SELECT
      AVG(total_tasu) AS avg_total_tasu
  FROM (
      SELECT
          tm.name AS nickname,
          SUM(m.tasu) AS total_tasu
      FROM tb_member tm
      JOIN tb_msg m
          ON tm.name = m.nickname
      WHERE m.regdate >= '{$날짜}'
        AND m.regdate < '{$날짜}' + INTERVAL 1 DAY
      GROUP BY tm.name
  ) t";


   // echo 전송($sql);
   // exit;
  $data = db_select($sql);
  $현재평균타수 = number_format($data['avg_total_tasu'],2);
  $msg = "✅우리방 채팅 평균 - {$현재평균타수}\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";

  $msg.= "   {$현재평균타수} 타(임티제외)\n\n";

  $sql = "
  SELECT
      x.nickname AS 닉네임,
      x.cnt AS 타수,
      x.raw_cnt AS 생타수,
      x.total_cnt AS 합타수,
      x.day_newpoint AS 당일냥,
      ROW_NUMBER() OVER (ORDER BY x.total_cnt DESC) AS 순위
  FROM (
      SELECT
          tm.name AS nickname,
          SUM(m.tasu) AS cnt,
          " . 생타_SQL_select_expr('m.msg') . " AS raw_cnt,
          (SUM(m.tasu) + " . 생타_SQL_select_expr('m.msg') . ") AS total_cnt,
          COALESCE(SUM(IFNULL(m.newpoint, CASE m.tasu WHEN 1 THEN 0.1 WHEN 2 THEN 0.2 WHEN 3 THEN 0.3 ELSE 0 END)), 0) AS day_newpoint
      FROM tb_member tm
      INNER JOIN tb_msg m ON tm.name = m.nickname
      WHERE m.regdate >= '{$날짜}'
        AND m.regdate < '{$날짜}' + INTERVAL 1 DAY
        AND m.msg NOT LIKE '%이모티콘을 보냈습니다.%'
      GROUP BY tm.name
  ) AS x
  ORDER BY x.total_cnt DESC ";
  
  $msg .= "순위|닉네임|합타|냥\n";
  $result = db_query($sql);
  foreach($result as $순위){

    $합타수 = (int)($순위['합타수'] ?? 0);
    $당일냥 = newpoint표시($순위['당일냥'] ?? 0);
    $msg .= $순위['순위']."등 ".$순위['닉네임']." {$합타수}타 {$당일냥}냥\n";
  }

  echo 전송($msg);
  exit;
}


if (strpos($status, '.공일방') !== false) {
  if (preg_match('/^\.공일방\s+(\S+)\s+(\S+)\s*$/u', trim($status), $공일방매치)) {
    $공일방1 = trim($공일방매치[1]);
    $공일방2 = trim($공일방매치[2]);
    $공일방1_esc = addslashes($공일방1);
    $공일방2_esc = addslashes($공일방2);

    $공일방비용 = 5000000;
    if (!$정보['point'] || (int)$정보['point'] < 0) {
      echo 전송("❌ 보유 포인트 없음");
      exit;
    }
    if ((int)$정보['point'] < $공일방비용) {
      echo 전송("❌ 냥 부족! 공일방 신청은 " . number_format($공일방비용) . "{$단위} 필요 (현재 보유: " . number_format($정보['point']) . "{$단위})");
      exit;
    }

    $공일방_max = db_select("SELECT MAX(edate) AS m FROM tb_kong_room");
    $공일방_기준 = time();
    if (!empty($공일방_max['m'])) {
      $공일방_ts = strtotime($공일방_max['m']);
      if ($공일방_ts !== false) {
        $공일방_기준 = max($공일방_기준, $공일방_ts);
      }
    }
    $공일방_edate = date('Y-m-d H:i:s', $공일방_기준 + 3600);

    db_query("INSERT INTO tb_kong_room (name1, name2, regdate, edate) VALUES ('{$공일방1_esc}', '{$공일방2_esc}', NOW(), '{$공일방_edate}')");

    $닉_db_esc = addslashes($두자리닉넴);
    db_query("UPDATE tb_member SET point = point - {$공일방비용} WHERE name = '{$닉_db_esc}' ");

    $공일방_시작표시 = date('m-d H:i', $공일방_기준);
    $공일방_종료표시 = date('m-d H:i', strtotime($공일방_edate));
    echo 전송("✅ 공일방 등록!\n{$공일방1} · {$공일방2}\n시작: {$공일방_시작표시}\n종료: {$공일방_종료표시}\n\n💰 -" . number_format($공일방비용) . "{$단위} 차감\n\n시간엄수!\n공일방 진행자 외 접근금지!\nhttps://open.kakao.com/o/gS33JLmi");
    exit;
  }

  $msg = "✅ 공용일방(공일방) 사용법\n\n";
  $msg .= "기존 12시간 강일방은 개설 후 12시간이 지나면 폭파되었으나(12시간 동안 일방신청 제한)";
  $msg .= " 공일방은 폭파되지 않고 언제든지 원하는 상대와 1시간 일방을 진행할 수 있어!";
  $msg .= " 룰은 강제일방과 동일하나 일방신청 제한 없음!!";

  $msg .= "\n\n✅현재 진행중인 공일방 현황\n";
  $msg .= 공일방_현황문구();
  $msg .= "\n신청명령어 .공일방 지호 아영\n1시간 이용비용 500만냥";
  echo 전송($msg);
  exit;
}
if(strpos($status, '.사다리방법') !== false){
    $msg = "🎲 사다리 안내\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    $msg .= "사다리는 아이템 판매, 야바위 등에서 발생하는 수수료가\n";
    $msg .= "금고에 차곡차곡 쌓였다가, 매일 밤 11시에 친구들에게\n";
    $msg .= "다시 재분배되는 이벤트 게임이에요.\n\n";
    $msg .= "아래 순서를 참고해서 사다리를 진행해주세요.\n\n";
    
    $msg .= "📌 진행 방법\n\n";
    $msg .= "1️⃣ 후보 추첨\n";
    $msg .= "· .사다리 [상위명수] [뽑을명수]\n";
    $msg .= "  예) .사다리 10 4  → 평타 상위 10명 중 4명 랜덤 추첨\n\n";
    $msg .= "2️⃣ 당첨자 확정\n";
    $msg .= "· 안내 메시지에 나온 닉네임들이 이번 사다리 대상자\n";
    $msg .= "· 당첨 대상자들은 채팅창에 '냥살냥죽!!!' 입력 (느낌표 3개)\n\n";
    $msg .= "3️⃣ 당첨 처리\n";
    $msg .= "· 각 당첨자가 '냥살냥죽!!!' 를 입력하면\n";
    $msg .= "  - 당첨처리로 변경됨!\n";
    $msg .= "  - 실제 냥 지급은 아직 안 됨 (지급 예정 상태)\n\n";
    $msg .= "4️⃣ 진행 중 현황 보기\n";
    $msg .= "· .사다리현황  → 현재까지 당첨 처리된 인원과 금액 확인\n\n";
    $msg .= "5️⃣ 미당첨/미응답 정리\n";
    $msg .= "· .사다리마감  → (미당첨/미응답) 데이터만 삭제\n\n";
    $msg .= "6️⃣ 최종 지급\n";
    $msg .= "· .사다리종료  → 당첨된 인원들에게 지정된 냥 만큼 지급\n";
    $msg .= "                 동시에 금고에서 총 지급액만큼 차감\n\n";
    $msg .= "※ 진행자는 위 순서를 꼭 지켜서 사용해주세요.";
    echo 전송($msg);
    exit;
}


if(strpos($status, '.선물') === 0){
  $선물_trim = trim($status);
  // .선물 [받는사람] 은총 [개수] — 선물 N개 + 내 은총개수 N 이상 필요, 상대 은총개수 +N (개수 생략 시 1)
  if (preg_match('/^\.선물\s+([가-힣A-Za-z0-9_]+)\s+은총(?:\s+(\d+))?\s*$/u', $선물_trim, $m_eunchong)) {
    $받는닉 = trim($m_eunchong[1]);
    $수량 = (isset($m_eunchong[2]) && $m_eunchong[2] !== '') ? (int)$m_eunchong[2] : 1;
    $받는닉_esc = addslashes($받는닉);
    $보내는_esc = addslashes($두자리닉넴);

    if ($수량 <= 0) {
      echo 전송("❌ 은총 개수는 1 이상 숫자로 입력해주세요.\n예) .선물 지호 은총 5");
      exit;
    }

    if ($받는닉 === $두자리닉넴) {
      echo 전송("❌ 본인에게는 선물할 수 없어요.");
      exit;
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $선물개수행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$내idx} AND itemname = '선물' AND status = 0");
    $선물보유개수 = (int)($선물개수행['cnt'] ?? 0);
    if ($선물보유개수 < $수량) {
      echo 전송("❌ 선물 아이템이 부족해요. (필요: {$수량}개 · 보유: {$선물보유개수}개)");
      exit;
    }

    $내은총행 = db_select("SELECT IFNULL(은총개수, 0) AS cnt FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $내은총 = (int)($내은총행['cnt'] ?? 0);
    if ($내은총 < $수량) {
      echo 전송("❌ 은총이 부족해요. (내려는 개수: {$수량}개 · 보유: {$내은총}개)");
      exit;
    }

    $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
    if (empty($받는사람['idx'])) {
      echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
      exit;
    }

    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$내idx} AND itemname = '선물' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
    아이템사용_시세하락('선물', $수량);
    db_query("UPDATE tb_member SET 은총개수 = GREATEST(IFNULL(은총개수, 0) - {$수량}, 0) WHERE name = '{$보내는_esc}'");
    db_query("UPDATE tb_member SET 은총개수 = IFNULL(은총개수, 0) + {$수량} WHERE name = '{$받는닉_esc}'");
    echo 전송("🎁 [ {$받는닉} ]에게 은총 {$수량}개 선물 완료!");
    exit;
  }

  // .선물 [받는사람] 강화수호 [개수] — 선물 N개 + 내 enhance_suho N 이상 필요, 상대 enhance_suho +N (개수 생략 시 1)
  if (preg_match('/^\.선물\s+([가-힣A-Za-z0-9_]+)\s+강화수호(?:\s+(\d+))?\s*$/u', $선물_trim, $m_suho)) {
    $받는닉 = trim($m_suho[1]);
    $수량 = (isset($m_suho[2]) && $m_suho[2] !== '') ? (int)$m_suho[2] : 1;
    $받는닉_esc = addslashes($받는닉);
    $보내는_esc = addslashes($두자리닉넴);

    if ($수량 <= 0) {
      echo 전송("❌ 강화수호 개수는 1 이상 숫자로 입력해주세요.\n예) .선물 지호 강화수호 5");
      exit;
    }

    if ($받는닉 === $두자리닉넴) {
      echo 전송("❌ 본인에게는 선물할 수 없어요.");
      exit;
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    $선물개수행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE midx = {$내idx} AND itemname = '선물' AND status = 0");
    $선물보유개수 = (int)($선물개수행['cnt'] ?? 0);
    if ($선물보유개수 < $수량) {
      echo 전송("❌ 선물 아이템이 부족해요. (필요: {$수량}개 · 보유: {$선물보유개수}개)");
      exit;
    }

    $내수호행 = db_select("SELECT IFNULL(enhance_suho, 0) AS suho FROM tb_member WHERE idx = {$내idx} LIMIT 1");
    $내수호 = (int)($내수호행['suho'] ?? 0);
    if ($내수호 < $수량) {
      echo 전송("❌ 강화수호가 부족해요. (내려는 개수: {$수량}회 · 보유: {$내수호}회)");
      exit;
    }

    $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
    if (empty($받는사람['idx'])) {
      echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
      exit;
    }

    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE midx = {$내idx} AND itemname = '선물' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
    아이템사용_시세하락('선물', $수량);
    db_query("UPDATE tb_member SET enhance_suho = GREATEST(IFNULL(enhance_suho, 0) - {$수량}, 0) WHERE name = '{$보내는_esc}'");
    db_query("UPDATE tb_member SET enhance_suho = IFNULL(enhance_suho, 0) + {$수량} WHERE name = '{$받는닉_esc}'");
    echo 전송("🎁 [ {$받는닉} ]에게 강화수호 {$수량}회 선물 완료!");
    exit;
  }

  // .선물 [받는사람] [선물할 아이템] [수량] — 선물 토큰 + 내 보유 해당 아이템을 수량만큼 소모 후 상대에게 지급 (아이템명이 '선물'이면 토큰만 수량만큼 소모)
  if (preg_match('/\.선물\s+([가-힣A-Za-z0-9_]+)\s+([가-힣A-Za-z0-9_]+)(?:\s+(\d+))?$/u', $선물_trim, $m)) {
    $받는닉 = trim($m[1]);
    $선물아이템 = trim($m[2]);
    $수량 = isset($m[3]) ? (int)$m[3] : 1;
    if ($수량 <= 0) $수량 = 1;
    
    // if ($수량 > 50) {
    //   echo 전송("❌ 선물 수량은 최대 50개까지만 가능합니다.");
    //   exit;
    // }

    $받는닉_esc = addslashes($받는닉);
    $선물아이템_esc = addslashes($선물아이템);

    // 선물 가능: 2글자 상점템 + 일방신청권·일방연장권
    if (!function_exists('선물_가능_아이템명') || !선물_가능_아이템명($선물아이템)) {
      echo 전송("❌ 선물 가능한 아이템명을 확인해주세요.\n(2글자 아이템 · 일방신청권 · 일방연장권)");
      exit;
    }

    $선물아이템_검증 = db_select("
      SELECT idx
      FROM tb_item
      WHERE sname = '{$선물아이템_esc}' 
      LIMIT 1
    ");
    $장문선물 = in_array($선물아이템, ['일방신청권', '일방연장권'], true);
    if (empty($선물아이템_검증['idx']) && !$장문선물) {
      echo 전송("❌ 선물 가능한 아이템이 아니에요.");
      exit;
    }

    if ($받는닉 === $두자리닉넴) {
      echo 전송("❌ 본인에게는 선물할 수 없어요.");
      exit;
    }

    $내idx = (int)($정보['idx'] ?? 0);
    if ($내idx <= 0) {
      echo 전송("❌ 회원 정보를 찾을 수 없어요.");
      exit;
    }

    // 선물 토큰(= itemname '선물')을 수량만큼 소모
    $선물토큰Idx들 = [];
    $token_result = db_query("SELECT idx FROM tb_member_item WHERE midx = {$내idx} AND itemname = '선물' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
    if ($token_result) {
      while ($row = db_fetch($token_result)) {
        $선물토큰Idx들[] = (int)($row['idx'] ?? 0);
      }
    }
    if (count($선물토큰Idx들) < $수량) {
      echo 전송("❌ 선물 아이템이 부족해요. (필요: {$수량}개)");
      exit;
    }

    // 보낸 사람 보유의 실제 선물 아이템(예: 프변)을 수량만큼 사용 처리 — 아이템명이 '선물'이면 위 토큰 소모가 곧 실물 이전이므로 중복 차감 안 함
    $실물아이템Idx들 = [];
    if ($선물아이템 !== '선물') {
      $실물_result = db_query("SELECT idx FROM tb_member_item WHERE midx = {$내idx} AND itemname = '{$선물아이템_esc}' AND status = 0 ORDER BY idx ASC LIMIT {$수량}");
      if ($실물_result) {
        while ($row = db_fetch($실물_result)) {
          $실물아이템Idx들[] = (int)($row['idx'] ?? 0);
        }
      }
      if (count($실물아이템Idx들) < $수량) {
        echo 전송("❌ [ {$선물아이템} ] 보유가 부족해요. (필요: {$수량}개)");
        exit;
      }
    }

    $받는사람 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
    if (empty($받는사람['idx'])) {
      echo 전송("❌ [ {$받는닉} ] 회원을 찾을 수 없어요.");
      exit;
    }

    $tokenIn = implode(',', array_filter($선물토큰Idx들, fn($x) => $x > 0));
    db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$tokenIn})");
    if ($선물아이템 !== '선물') {
      $실물In = implode(',', array_filter($실물아이템Idx들, fn($x) => $x > 0));
      db_query("UPDATE tb_member_item SET status = 1, usedate = NOW() WHERE idx IN ({$실물In})");
    }
    아이템사용_시세하락('선물', $수량);
    if ($선물아이템 !== '선물') {
      아이템사용_시세하락($선물아이템, $수량);
    }

    for ($i = 0; $i < $수량; $i++) {
      db_query("INSERT INTO tb_member_item SET midx = {$받는사람['idx']}, nick = '{$받는닉_esc}', status = 0, itemname = '{$선물아이템_esc}', regdate = NOW()");
    }

    if ($선물아이템 === '일방신청권') {
      if (function_exists('일방신청권_지급기록')) {
        일방신청권_지급기록($받는닉, '선물', [
          'midx' => (int)$받는사람['idx'],
          'from_nick' => $두자리닉넴,
          'reason_text' => "선물 수령 (보낸이: {$두자리닉넴})",
          'qty' => $수량,
        ]);
      }
      if (function_exists('미션완료_기록_if_new')) {
        미션완료_기록_if_new($받는닉, '일방', '400타');
      }
    }

    $수량문구 = ($수량 > 1) ? " x{$수량}" : '';
    echo 전송("🎁 [ {$받는닉} ]에게 {$선물아이템}{$수량문구} 아이템 선물 완료!");
    exit;
  }
  echo 전송("❌ 사용법: .선물 (받는사람 닉네임) (선물할 아이템) [수량]\n예) .선물 지호 수호\n예) .선물 미소 일방신청권 1\n강화수호: .선물 지호 강화수호 또는 .선물 지호 강화수호 5");
  exit;
}

if(strpos($status, '.공커등록') !== false){

  if (preg_match('/\.공커등록\s*(.+)/u', $status, $match)) {
      $after = trim($match[1]);
      if (!in_array($두자리닉넴, $관리자, true)) {
        echo 전송("❌ 관리자만 사용할 수 있습니다.");
        exit;
      }
      if (preg_match('/^(.+)$/u', $after, $parts)) {
        $닉네임 = $parts[1]; // 도하

        $데이터 = db_select("select count(*) as cnt from tb_couple where couple = '{$닉네임}' ");
        if($데이터['cnt']){
          $result = db_query("delete from tb_couple where couple = '{$닉네임}' ");
          if($result){
            $msg = $닉네임." 해제";
            $커플닉들 = preg_split('/\p{Extended_Pictographic}+/u', trim($닉네임), -1, PREG_SPLIT_NO_EMPTY);
            if (count($커플닉들) >= 2) {
              $커플앞 = trim($커플닉들[0]);
              $커플뒤 = trim($커플닉들[count($커플닉들) - 1]);
              $커플앞_esc = addslashes($커플앞);
              $커플뒤_esc = addslashes($커플뒤);
              db_query("UPDATE tb_member SET oneroom = 0 WHERE name = '{$커플앞_esc}'");
              db_query("UPDATE tb_member SET oneroom = 0 WHERE name = '{$커플뒤_esc}'");
            }
          }
        }else{
          $닉_esc = addslashes($닉네임);
          $다음sort = 공커등록_다음sort();
          $sql = "INSERT INTO tb_couple SET couple = '{$닉_esc}', sdate = '{$오늘}', edate = '{$오늘}', sort = {$다음sort}, status = 0";
          $result = db_query($sql);
          if($result){
            공커등록_일방정리($닉네임);
            $msg = $닉네임." 축하해🎉";
            $커플닉들 = preg_split('/\p{Extended_Pictographic}+/u', trim($닉네임), -1, PREG_SPLIT_NO_EMPTY);
            if (count($커플닉들) >= 2) {
              $커플앞 = trim($커플닉들[0]);
              $커플뒤 = trim($커플닉들[count($커플닉들) - 1]);
              $커플앞_esc = addslashes($커플앞);
              $커플뒤_esc = addslashes($커플뒤);
              db_query("UPDATE tb_member SET oneroom = 2 WHERE name = '{$커플앞_esc}'");
              db_query("UPDATE tb_member SET oneroom = 2 WHERE name = '{$커플뒤_esc}'");
            }
          }
        }
      }
  }
  echo 전송($msg ?? "❌ 사용법: .공커등록 영수🖤하니");
  exit;
}


if (!empty($MUTUAL_SKIP_LOTTO)) {
  // 본방(info1): 구매·내역은 홍보방 — `.로또` 조회·추첨·지급(관리자)은 본방
  $_로또본방허용 = (
    trim($status) === '.로또'
    || trim($status) === '.로또 추첨'
    || trim($status) === '.로또 지급'
  );
  if ($_로또본방허용) {
    require_once __DIR__ . '/game/lotto_chat.inc.php';
  } elseif (strpos($status, '.로또') !== false) {
    echo 전송("❌ 로또 구매·내역은 홍보방에서만 이용할 수 있어요.\n`.로또 내역` · `.로또 1,22,33` · `.로또 자동`\n(본방: `.로또` 조회 · `.로또 자동 1~50` (티켓) · `.로또 추첨` · `.로또 지급`)");
    exit;
  }
} else {
  require_once __DIR__ . '/game/lotto_chat.inc.php';
}

require_once __DIR__ . '/game/match_predict_chat.inc.php';

if (preg_match('/^\.금고\s*(?:(\+|-)\s*)?(\d+)\s*$/u', trim($status), $금고_관리_m)) {
  if (!in_array($두자리닉넴, $관리자)) {
    echo 전송("❌ 관리자만 사용할 수 있습니다.");
    exit;
  }
  $금고_변동 = (int)$금고_관리_m[2];
  if ($금고_변동 <= 0) {
    echo 전송("❌ 금액은 1 이상 숫자로 입력해주세요. (예: .금고 10000 또는 .금고 -10000)");
    exit;
  }
  $금고잔액_관리전 = isset($설정['tax']) ? (int)$설정['tax'] : 0;
  $금고_차감 = (($금고_관리_m[1] ?? '') === '-');
  if ($금고_차감) {
    if ($금고잔액_관리전 < $금고_변동) {
      echo 전송("❌ 금고 잔액(".number_format($금고잔액_관리전) . "냥)이 부족합니다.");
      exit;
    }
    db_query("update config set tax = tax - {$금고_변동} ");
    $새금고 = db_select("select tax from config limit 1");
    $새금고잔액 = (int)($새금고['tax'] ?? 0);
    echo 전송("✅ 금고에서 ".number_format($금고_변동) . "냥 차감했습니다.\n현재 금고: ".number_format($새금고잔액) . "냥");
  } else {
    db_query("update config set tax = tax + {$금고_변동} ");
    $새금고 = db_select("select tax from config limit 1");
    $새금고잔액 = (int)($새금고['tax'] ?? 0);
    echo 전송("✅ 금고에 ".number_format($금고_변동) . "냥 추가했습니다.\n현재 금고: ".number_format($새금고잔액) . "냥");
  }
  exit;
}

if (!function_exists('랜덤강일_사용테이블_ensure')) {
  function 랜덤강일_사용테이블_ensure() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    db_query("CREATE TABLE IF NOT EXISTS tb_rand_gangil_used (
      nick VARCHAR(50) NOT NULL PRIMARY KEY,
      regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='랜덤강일 1회 사용 기록(초기화 시 전원 삭제)'");
  }
}

if (!function_exists('랜덤강일_사용가능')) {
  function 랜덤강일_사용가능($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return false;
    }
    랜덤강일_사용테이블_ensure();
    $닉_esc = addslashes($닉);
    $row = db_select("SELECT nick FROM tb_rand_gangil_used WHERE nick = '{$닉_esc}' LIMIT 1");
    return empty($row['nick']);
  }
}

if (!function_exists('랜덤강일_사용기록')) {
  function 랜덤강일_사용기록($닉) {
    $닉 = trim((string)$닉);
    if ($닉 === '') {
      return;
    }
    랜덤강일_사용테이블_ensure();
    $닉_esc = addslashes($닉);
    db_query("INSERT IGNORE INTO tb_rand_gangil_used (nick, regdate) VALUES ('{$닉_esc}', NOW())");
  }
}

if (!function_exists('랜덤강일_결과테이블_ensure')) {
  function 랜덤강일_결과테이블_ensure() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    db_query("CREATE TABLE IF NOT EXISTS tb_rand_gangil_result (
      idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      runner_nick VARCHAR(50) NOT NULL DEFAULT '',
      male_nick VARCHAR(50) NOT NULL,
      female_nick VARCHAR(50) NOT NULL,
      regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_regdate (regdate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='랜덤강일 매칭 결과(초기화 시 삭제)'");
  }
}

if (!function_exists('랜덤강일_결과_저장')) {
  function 랜덤강일_결과_저장($runner, array $선택남, array $선택여) {
    랜덤강일_결과테이블_ensure();
    $runner_esc = addslashes(trim((string)$runner));
    $짝수 = min(count($선택남), count($선택여));
    for ($i = 0; $i < $짝수; $i++) {
      $남 = trim((string)$선택남[$i]);
      $여 = trim((string)$선택여[$i]);
      if ($남 === '' || $여 === '') {
        continue;
      }
      $남_esc = addslashes($남);
      $여_esc = addslashes($여);
      db_query("INSERT INTO tb_rand_gangil_result (runner_nick, male_nick, female_nick, regdate)
        VALUES ('{$runner_esc}', '{$남_esc}', '{$여_esc}', NOW())");
    }
  }
}

if (!function_exists('랜덤강일_마감_문구')) {
  function 랜덤강일_마감_문구() {
    랜덤강일_결과테이블_ensure();
    $rs = db_query("
      SELECT r.male_nick, r.female_nick
      FROM tb_rand_gangil_result r
      INNER JOIN tb_member m ON m.name = r.male_nick AND m.status = 0 AND m.oneroom = 0
      INNER JOIN tb_member f ON f.name = r.female_nick AND f.status = 0 AND f.oneroom = 0
      ORDER BY r.idx ASC
    ");
    $쌍목록 = [];
    $당첨자 = [];
    if ($rs) {
      while ($row = db_fetch($rs)) {
        $남 = trim((string)($row['male_nick'] ?? ''));
        $여 = trim((string)($row['female_nick'] ?? ''));
        if ($남 === '' || $여 === '') {
          continue;
        }
        $키 = $남 . '|' . $여;
        if (isset($쌍목록[$키])) {
          continue;
        }
        $쌍목록[$키] = ['남' => $남, '여' => $여];
        $당첨자[$남] = true;
        $당첨자[$여] = true;
      }
    }
    if ($쌍목록 === []) {
      $전체 = db_select("SELECT COUNT(*) AS cnt FROM tb_rand_gangil_result");
      if ((int)($전체['cnt'] ?? 0) === 0) {
        return "📋 랜덤강일 마감\n\n이번 회차 저장된 매칭 결과가 없어요.\n(`.랜덤강일` 실행 후 `.랜덤강일마감`)";
      }
      return "📋 랜덤강일 마감\n\n공커·일방 제외 당첨자가 없어요.\n(매칭된 분이 모두 일방/공커 상태일 수 있어요)";
    }
    $msg = "📋 랜덤강일 마감 (공커·일방 제외)\n\n";
    $i = 0;
    foreach ($쌍목록 as $쌍) {
      $i++;
      $msg .= "{$i}) 🚹 {$쌍['남']}  +  🚺 {$쌍['여']}\n";
    }
    $msg .= "\n당첨 " . count($당첨자) . "명\n";
    $msg .= implode(' ', array_keys($당첨자));
    return trim($msg);
  }
}

if (!function_exists('랜덤강일_관리자선돌림_컬럼_ensure')) {
  function 랜덤강일_관리자선돌림_컬럼_ensure() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $exists = db_select("SHOW COLUMNS FROM config LIKE '랜덤강일_관리자선돌림'");
    if (empty($exists)) {
      db_query("ALTER TABLE config ADD COLUMN `랜덤강일_관리자선돌림` TINYINT NOT NULL DEFAULT 0 COMMENT '랜덤강일 회차당 관리자 무료 선돌림 완료'");
    }
  }
}

if (!function_exists('랜덤강일_관리자선돌림_완료됨')) {
  function 랜덤강일_관리자선돌림_완료됨() {
    랜덤강일_관리자선돌림_컬럼_ensure();
    $row = db_select("SELECT `랜덤강일_관리자선돌림` FROM config LIMIT 1");
    return (int)($row['랜덤강일_관리자선돌림'] ?? 0) === 1;
  }
}

if (!function_exists('랜덤강일_관리자선돌림_완료처리')) {
  function 랜덤강일_관리자선돌림_완료처리() {
    랜덤강일_관리자선돌림_컬럼_ensure();
    db_query("UPDATE config SET `랜덤강일_관리자선돌림` = 1 LIMIT 1");
  }
}

if (!function_exists('랜덤강일_비용')) {
  /** 전체 게임냥(point) 합계의 0.5% */
  function 랜덤강일_비용() {
    return function_exists('전체냥기준금액') ? 전체냥기준금액(0.5) : 1;
  }
}

if (!function_exists('랜덤강일_기회_초기화')) {
  function 랜덤강일_기회_초기화() {
    랜덤강일_결과테이블_ensure();
    db_query('TRUNCATE TABLE tb_rand_gangil_result');
  }
}

if (trim((string)$status) === '.랜덤강일초기화') {
  if (!in_array($두자리닉넴, $관리자, true)) {
    echo 전송('❌ `.랜덤강일초기화`는 관리자만 사용할 수 있어요.');
    exit;
  }
  랜덤강일_기회_초기화();
  echo 전송("✅ 랜덤강일 매칭결과 초기화!\n(`.랜덤강일` 1회당 전체 게임냥 0.5% 차감 · 횟수 제한 없음)");
  exit;
}

if (trim((string)$status) === '.랜덤강일마감') {
  echo 전송(랜덤강일_마감_문구());
  exit;
}

if (preg_match('/^\.랜덤강일\s+\S/u', trim($status))) {
  echo 전송("💑 `.랜덤강일` 만 입력해주세요!\n(숫자 없이 · 여성 전원 + 남성 랜덤 1:1 매칭 · 1회당 전체 게임냥 0.5%)");
  exit;
}

if (trim((string)$status) === '.랜덤강일') {
  // status=0 전원(공커·일방 포함) · 여성 수만큼 1:1 · 남성은 풀에서 무작위
  if ($두자리닉넴 === '') {
    echo 전송('❌ 두글자 닉+성별 설정 후 `.랜덤강일` 을 사용해줘!');
    exit;
  }

  $닉_esc = addslashes($두자리닉넴);
  $랜덤강일비용 = 랜덤강일_비용();
  $회원행 = db_select("SELECT point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
  $보유냥 = (int)($회원행['point'] ?? 0);
  if ($보유냥 < $랜덤강일비용) {
    $비용표시 = function_exists('구매가_축약표시') ? 구매가_축약표시($랜덤강일비용, $단위) : number_format($랜덤강일비용) . "{$단위}";
    echo 전송("❌ `.랜덤강일` 비용(전체 게임냥 0.5%): {$비용표시}\n[ {$두자리닉넴} ] 현재 보유: " . number_format($보유냥) . "{$단위}");
    exit;
  }

  $sql = "SELECT `name`, `gender` FROM tb_member
          WHERE `status` = 0
            AND `gender` IN (1, 2)
            AND TRIM(COALESCE(`name`, '')) <> ''
          ORDER BY `name` ASC";
  $result = db_query($sql);
  $남목록 = [];
  $여목록 = [];
  if ($result) {
    while ($row = db_fetch($result)) {
      $n = trim((string)($row['name'] ?? ''));
      if ($n === '') {
        continue;
      }
      $g = (int)($row['gender'] ?? 0);
      if ($g === 1) {
        $남목록[] = $n;
      } elseif ($g === 2) {
        $여목록[] = $n;
      }
    }
  }
  $n남 = count($남목록);
  $n여 = count($여목록);
  if ($n남 < 1 || $n여 < 1) {
    echo 전송("💑 강일 랜덤 매칭\n\n조건: status=0 · 성별 등록(1=남, 2=여) · 공커·일방 포함\n현재 남 {$n남}명 · 여 {$n여}명\n\n남·여 각 1명 이상이어야 짝을 지을 수 있어요.");
    exit;
  }

  $짝수 = min($n남, $n여);
  shuffle($남목록);
  shuffle($여목록);
  $선택남 = array_slice($남목록, 0, $짝수);
  $선택여 = array_slice($여목록, 0, $짝수);
  $남미매칭 = array_slice($남목록, $짝수);
  $여미매칭 = array_slice($여목록, $짝수);

  $msg = "💑 [ {$두자리닉넴} ] 강일 랜덤 매칭 (공커·일방 포함 · 남 {$n남} · 여 {$n여} → {$짝수}쌍)\n\n";
  for ($i = 0; $i < $짝수; $i++) {
    $msg .= ($i + 1) . ") 🚹 {$선택남[$i]}  +  🚺 {$선택여[$i]}\n";
  }
  if (!empty($남미매칭)) {
    $msg .= "\n미매칭 남 🚹 " . implode(' ', $남미매칭);
  }
  if (!empty($여미매칭)) {
    $msg .= "\n미매칭 여 🚺 " . implode(' ', $여미매칭);
  }
  db_query("UPDATE tb_member SET point = point - {$랜덤강일비용} WHERE name = '{$닉_esc}'");
  $비용표시 = function_exists('구매가_축약표시') ? 구매가_축약표시($랜덤강일비용, $단위) : number_format($랜덤강일비용) . "{$단위}";
  $msg .= "\n\n💰 차감: {$비용표시} (전체 게임냥 0.5%)";
  랜덤강일_결과_저장($두자리닉넴, $선택남, $선택여);
  echo 전송(trim($msg));
  exit;
}


if(strpos($status, '.칠타') !== false){
  if (in_array(getTwoCharNick($nick), $관리자)) {

    if (preg_match('/\.칠타\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // ".지급" 뒤 텍스트 추출

        // "도하 5" 형태인지 검사 + 그룹 분리
        if (preg_match('/^([가-힣A-Za-z]+)$/u', $after, $parts)) {
          $받는닉 = $parts[1]; // 닉네임
          $지급양 = $parts[2]; // 지급양

          $dates = [];
          for ($i = 0; $i <= 6; $i++) {  // 오늘 포함 7일치, 오늘부터 6일 전
              $dates[] = date('Y-m-d', strtotime("-$i days"));
          }

          // 테스트: 출력
          $msg = "✅ 7일간 타수 - {$받는닉}\n(생타 / 버프타)\n\n";
          foreach ($dates as $date) {
              // 요일 구하기 (한글)
              $weekday = ['일','월','화','수','목','금','토'][date('w', strtotime($date))];

              // 합계 쿼리 (.생타·.궁금 과 동일: tasu!=0 만, 생타=로우+사진+2)
              $받는닉_esc = addslashes($받는닉);
              $sql = "SELECT COALESCE(SUM(tasu), 0) AS cnt,
                             " . 생타_SQL_select_expr('msg') . " AS raw_cnt
                      FROM tb_msg
                      WHERE nickname = '{$받는닉_esc}'
                        AND tasu != 0
                        AND regdate >= '{$date}'
                        AND regdate < '{$date}' + INTERVAL 1 DAY";
              $타수 = db_select($sql);

              $타수값 = $타수['cnt'] ?: 0;
              $생타수값 = $타수['raw_cnt'] ?: 0;

              // 메시지 조합
              $msg .= $date . "({$weekday}) {$생타수값} / {$타수값}\n";
          }
          echo 전송($msg);
          exit;
        }
    }
  }else{
    echo 전송("🔒");
    exit;
  }
}

if(strpos($status, '.미션') !== false){
  $오늘 = date('Y-m-d');

  $미션대상닉 = $두자리닉넴;
  if (preg_match('/\.미션\s+(.+)/u', $status, $미션매치)) {
    $미션후보 = trim($미션매치[1]);
    if ($미션후보 !== '') {
      if (!in_array(getTwoCharNick($nick), $관리자)) {
        echo 전송("🔒");
        exit;
      }
      $미션대상닉 = getTwoCharNick($미션후보);
    }
  }

  // 타수 미션: 매일 초기화 → 오늘자 기록만 조회
  $미션맵_오늘 = 미션_완료_맵($미션대상닉, $오늘);
  $ic_오늘 = function ($types, $status) use ($미션맵_오늘) {
    return 미션_완료됨($미션맵_오늘, $types, $status) ? '✅' : '❌';
  };

  // 일방 미션: 누적 유지 → 전체 기록 조회
  $미션맵_전체 = 미션_완료_맵($미션대상닉);
  $ic_전체 = function ($types, $status) use ($미션맵_전체) {
    return 미션_완료됨($미션맵_전체, $types, $status) ? '✅' : '❌';
  };

  $midx_미션 = (int)($정보['idx'] ?? 0);
  if ($미션대상닉 !== $두자리닉넴) {
    $미션대상_esc = addslashes($미션대상닉);
    $미션대상회원 = db_select("SELECT idx FROM tb_member WHERE name = '{$미션대상_esc}' LIMIT 1");
    $midx_미션 = (int)($미션대상회원['idx'] ?? 0);
  }
  $ic_일방400타 = (function_exists('일방400타_미션_완료됨') && 일방400타_미션_완료됨($미션대상닉, $midx_미션)) ? '✅' : '❌';
  // info3 `.등록` 강일 시 tb_mission.status = '강일' 로 적힘(문서·구 버전은 '강제일방' 가능)
  $ic_강제일방 = (미션_완료됨($미션맵_전체, '일방', '강일') || 미션_완료됨($미션맵_전체, '일방', '강제일방')) ? '✅' : '❌';

  $msg = "-{$미션대상닉}의 미션 ({$오늘})\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           ";

  $msg .= "- 일방미션(일방가려면 필수미션)\n";
  $msg .= $ic_전체('일방', '얼공')     . " 얼공\n";
  $msg .= $ic_전체('일방', '야바위')   . " 야바위게임 1회 참여\n";
  $msg .= $ic_전체('일방', '무기구매') . " 무기구매 1회\n";
  $msg .= $ic_전체('일방', '지목')     . " 지목 아이템 1회 사용\n";
  $msg .= $ic_강제일방 . " 강제일방 아이템 1회 사용\n";
  $msg .= $ic_일방400타    . " 5일간 400타(생타)";

  $msg .= "\n\n- 데일리미션(일방과 상관없는 선택미션)\n";
  $msg .= $ic_오늘('타수', '100타')  . " 100타(출석체크) \n";
  $msg .= $ic_오늘('공지', '공지끝까지읽기') . " 공지끝까지읽기 (.확인공지)\n\n";

  if (function_exists('강일횟수_컬럼_보장')) {
    강일횟수_컬럼_보장();
  }
  if (function_exists('지목횟수_컬럼_보장')) {
    지목횟수_컬럼_보장();
  }
  $미션대상_esc = addslashes($미션대상닉);
  $연금행 = db_select("SELECT IFNULL(gangil_times, 0) AS gangil_times, IFNULL(jimok_times, 0) AS jimok_times FROM tb_member WHERE name = '{$미션대상_esc}' LIMIT 1");
  $강일횟수 = (int)($연금행['gangil_times'] ?? 0);
  $지목횟수 = (int)($연금행['jimok_times'] ?? 0);
  $강일단가 = function_exists('강일연금_단가') ? 강일연금_단가() : 1000;
  $지목단가 = function_exists('지목연금_단가') ? 지목연금_단가() : 500;
  $강일연금 = $강일횟수 * $강일단가;
  $지목연금 = $지목횟수 * $지목단가;
  $msg .= "강일연금 {$강일횟수}회 " . number_format($강일연금) . "냥\n";
  $msg .= "지목연금 {$지목횟수}회 " . number_format($지목연금) . "냥";

  $msg .= "\n\n※ 5일 도중 400타(생타) 끊길시 처음부터 다시 시작됨";
  $msg .= "\n※ 5일 타수 미션완료시 일방신청권,연장권 자동지급";
  $msg .= "\n※ 일방신청권 보유(양도·구매·5일달성) 시 5일400타 미션 완료 인정";
  echo 전송($msg);
  exit;
}  


