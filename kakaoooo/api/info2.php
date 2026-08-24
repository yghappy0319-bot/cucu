<?php
require_once __DIR__ . '/_bootstrap.php';
include_once __DIR__ . '/_bonbang.php';
require_once __DIR__ . '/eunchong_shop_buy.inc.php';
// 게임관련

$nick = nick_파라미터($nick ?? '');
$두자리닉넴 = getTwoCharNick($nick);
$status = trim(msg_파라미터($msg ?? ''));
if (function_exists('status_정규화')) {
  $status = status_정규화($status);
}
$status = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}]+/u', '', (string)$status);
$status = trim((string)$status);

// 홍보방 `.로또` — 홀짝·회원동기화 전에 즉시 응답 (본방과 동일 경량 경로)
if (preg_match('/^\.로또\s*$/u', $status)) {
  @set_time_limit(15);
  try {
    if (is_file(__DIR__ . '/game/lotto_amount.inc.php')) {
      require_once __DIR__ . '/game/lotto_amount.inc.php';
    }
    if (!function_exists('전송')) {
      include_once __DIR__ . '/config.php';
    }
    $내티켓 = -1;
    if ($두자리닉넴 !== '') {
      if (is_file(__DIR__ . '/game/lotto_ticket.inc.php')) {
        require_once __DIR__ . '/game/lotto_ticket.inc.php';
      }
      $닉_esc = addslashes($두자리닉넴);
      $행 = @db_select("SELECT idx FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
      $idx = (int)($행['idx'] ?? 0);
      if ($idx > 0 && function_exists('로또티켓_조회')) {
        $내티켓 = (int)로또티켓_조회($idx);
      }
    }
    echo 전송(로또_채팅조회_문구($내티켓));
  } catch (Throwable $e) {
    echo 전송("❌ 로또 조회 중 오류가 났어요. 잠시 후 다시 시도해주세요.");
  }
  exit;
}

if ($status === '.타짜') {
  require_once __DIR__ . '/game/odd_even_ranking.php';
  echo 전송(홀짝_타짜_랭킹_문구(20));
  exit;
}

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

// 게임방 `.연구실` — 회원동기화 전 즉시 응답
if ($status === ".연구실") {
  $msg = "11명 확인 후
공창닉으로 입장

https://open.kakao.com/o/gQIh7cIi";
  echo 전송($msg);
  exit;
}

// `.보스종류` — 보스 목록·HP·보상 안내
if (trim((string)$status) === '.보스종류') {
  require_once __DIR__ . '/game/boss_raid.inc.php';
  echo 전송(boss_raid_종류안내문구());
  exit;
}
// `.보스젠` — 다음 보스·출현 시각
if (trim((string)$status) === '.보스젠') {
  require_once __DIR__ . '/game/boss_raid.inc.php';
  echo 전송(boss_raid_젠안내문구());
  exit;
}

// `.마피아` — 다음 시작 시각 / `.마피아종료` — 강제 종료(민호)
$마피아cmd = trim((string)$status);
if ($마피아cmd === '.마피아' || $마피아cmd === '.마피아종료') {
  require_once __DIR__ . '/game/mafia.inc.php';
  if ($마피아cmd === '.마피아') {
    echo 전송(mafia_시작안내문구());
    exit;
  }
  $종료닉 = $두자리닉넴 ?: (function_exists('getTwoCharNick') ? getTwoCharNick($nick ?? '') : trim((string)($nick ?? '')));
  $r = mafia_강제종료((string)$종료닉);
  echo 전송((!empty($r['ok']) ? '✅ ' : '❌ ') . (string)($r['msg'] ?? '처리 실패'));
  exit;
}

if (!$두자리닉넴) {
  $nick_src = nick_파라미터($nick);
  if ($nick_src !== '') {
    $두자리닉넴 = getTwoCharNick($nick_src);
  }
}

// `.탕감 닉 5%` — 관리자 전용 (닉 게이트 밖에서도 응답 · 본방/관리방과 동일)
if (preg_match('/^\.탕감/u', (string)$status)) {
  if (!isset($단위) || !isset($관리자)) {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/dice_chat.inc.php';
  탕감_명령_처리($두자리닉넴, $status, $nick ?? '');
}

if($두자리닉넴){
  list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
  if (!isset($단위)) {
    include_once __DIR__ . '/config.php';
  }
  if (trim((string)($정보['name'] ?? '')) === '') {
    if ($status === '' || strpos(trim((string)$status), '.') !== 0) {
      exit;
    }
  }

  if (strpos($status, '.공지') !== false) {
    require_once __DIR__ . '/notice.inc.php';
    echo 전송(공지_안내_문구());
    exit;
  }
  if (strpos($status, '.확인공지') !== false || strpos($status, '.공지확인') !== false) {
    require_once __DIR__ . '/notice.inc.php';
    공지_확인_처리($두자리닉넴);
  }

  // `.금고란` — 홍보방 누구나 조회
  if (strpos($status, '.금고란') !== false) {
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    $tax = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : (function_exists('냥_정수문자열') ? 냥_정수문자열($설정['tax'] ?? 0) : '0');
    $단위표기 = isset($단위) ? $단위 : '냥';
    $tax표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($tax, $단위표기)
      : (function_exists('게임냥_안전표시') ? 게임냥_안전표시($tax, $단위표기) : $tax . $단위표기);

    $msg = "📦 금고란? 현재 " . $tax표시 . "\n\n";
    $msg .= "1️⃣ 금고가 뭐야?\n";
    $msg .= "- 우리방 공동 통장 같은 곳이에요.\n";
    $msg .= "- 친구들의 활동에서 빠지는 각종 수수료, 패널티 냥이 여기 모여요.\n\n";
    $msg .= "2️⃣ 금고는 어떻게 채워져?\n";
    $msg .= "- 양도/상점/판매 등에서 빠지는 수수료가 설정에 따라 금고로 들어와요.\n";
    $msg .= "- 무기 .수리 비용의 10% 중 금고 70%, 로또 30%가 들어와요.\n";
    $msg .= "- .금고 시도마다 금고 총액 1%가 입장료로 차감되고\n";
    $msg .= "  그 중 50%가 다시 금고로 환수돼요.\n";
    $msg .= "- 0.1% 확률로 🔥불지옥 → 남은 냥 50%가 금고로 회수돼요.\n";
    $msg .= "- 퇴사 처리 등 일부 관리 명령으로도 남은 냥이 금고로 귀속될 수 있어요.\n\n";
    $msg .= "3️⃣ 금고는 어떻게 털어?\n";
    $msg .= "- 채팅창에 '.금고' 를 입력하면 금고털이를 시도해요.\n";
    $msg .= "- 매 시도 입장료: 금고 총액 1% (50% 소멸 / 50% 금고)\n";
    $msg .= "  냥이 부족하면 시도 불가 (신불자 방지)\n";
    $msg .= "- 1% 확률: 금고 20% 획득\n";
    $msg .= "- 2% 확률: 금고 10% 획득\n";
    $msg .= "- 3% 확률: 금고 5% 획득\n";
    $msg .= "- 0.01% 확률: 👑대도둑! 금고 전액 + 💎대도둑 칭호\n";
    $msg .= "- 0.1% 확률: 🔥불지옥 → 남은 냥 50% 회수(금고)\n";
    $msg .= "- 관리자: `.금고 100경` / `.금고 1해` 로 금고 충전 가능\n";

    echo 전송($msg);
    exit;
  }

  // `.보스출현` — 관리자: 랜덤 보스 즉시 소환 + 공창 알림
  if (trim((string)$status) === '.보스출현') {
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    require_once __DIR__ . '/game/boss_raid.inc.php';
    $결과 = boss_raid_강제소환($두자리닉넴);
    echo 전송((string)($결과['data'] ?? '🐉 보스 출현!'));
    exit;
  }
  // `.보스초기화` — 관리자: 공격이력·HP 리셋
  if (trim((string)$status) === '.보스초기화') {
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    require_once __DIR__ . '/game/boss_raid.inc.php';
    $결과 = boss_raid_초기화($두자리닉넴);
    echo 전송((string)($결과['data'] ?? '🔄 보스 초기화!'));
    exit;
  }
  // `.보스처치풀초기화` — 관리자: 공통 처치풀 0
  if (preg_match('/^\.보스처치풀초기화\s*$/u', trim((string)$status))) {
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    require_once __DIR__ . '/game/boss_raid.inc.php';
    $결과 = boss_raid_처치풀_초기화($두자리닉넴);
    echo 전송((string)($결과['data'] ?? '💰 보스처치풀 초기화!'));
    exit;
  }

  // 채굴냥 `.수령` — 홍보방(info2) 전용
  require_once __DIR__ . '/game/mining_claim_chat.inc.php';
  // 채굴 장비 `.채굴수리`
  require_once __DIR__ . '/game/mining_repair_chat.inc.php';
  // 은총조각 → 은총 `.은총교환`
  require_once __DIR__ . '/game/mining_eunchong_exchange_chat.inc.php';

  // `.주사위 2` — 신불자 빚탕감
  require_once __DIR__ . '/game/dice_chat.inc.php';
  주사위2_명령_처리($두자리닉넴, $status);

  // `.우리방` — 시세·보상 안내
  require_once __DIR__ . '/wooribang_chat.inc.php';

  // `.마켓큰손` — 마켓 등록 권면가 합계 상위
  if (preg_match('/^\.마켓큰손(?:\s+(\d+))?\s*$/u', trim($status), $마켓큰손m)) {
    require_once dirname(__DIR__) . '/shop/_shop.php';
    $상위 = (isset($마켓큰손m[1]) && $마켓큰손m[1] !== '') ? (int)$마켓큰손m[1] : 10;
    echo 전송(shop_채팅_마켓큰손_문구($상위));
    exit;
  }

  // `.마켓수수료` — config.선매입적립 누적
  if (preg_match('/^\.마켓수수료\s*$/u', trim($status))) {
    require_once dirname(__DIR__) . '/shop/_shop.php';
    echo 전송(shop_채팅_마켓수수료_문구());
    exit;
  }

  // `.마켓수령` — 도하만 선매입적립 수령
  if (preg_match('/^\.마켓수령\s*$/u', trim($status))) {
    require_once dirname(__DIR__) . '/shop/_shop.php';
    $결과 = shop_채팅_마켓수령_실행($두자리닉넴);
    echo 전송((string)($결과['msg'] ?? '마켓 수수료 수령에 실패했어요.'));
    exit;
  }

  if (preg_match('/^\.스왑/u', trim($status))) {
    if (!function_exists('스왑_홍보방_안내문구')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    echo 전송(스왑_홍보방_안내문구());
    exit;
  }

  // `.본냥스왑` — 본냥 전액 → 게임냥 (10% 삭제 · 90% 환율 · 잔여·최소 없음)
  if (trim($status) === '.본냥스왑' || trim($status) === '.보냥스왑') {
    if (!function_exists('스왑_본냥스왑_실행')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    스왑_본냥스왑_실행($두자리닉넴);
  }

  // `.겜냥스왑` — 게임냥 1억 남기고 나머지 → 본냥
  if (trim($status) === '.겜냥스왑') {
    if (!function_exists('스왑_겜냥스왑_실행')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    스왑_겜냥스왑_실행($두자리닉넴);
  }

  // 게임방 .환율 — 보유냥 스왑·원화 기준 게임냥 예상 (본방 info1과 동일)
  if (preg_match('/^\.환율(?:\s+(.+))?$/u', trim($status), $환율매치)) {
    if (!function_exists('스왑_환율_미리보기문구')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $입력 = trim((string)($환율매치[1] ?? ''));
    if ($입력 === '') {
      echo 전송("❌ 사용법: .환율 금액\n예) .환율 5000 · .환율 1억 · .환율 5000원");
      exit;
    }
    $원화모드 = (bool)preg_match('/원(?:화)?$/u', $입력);
    if ($원화모드) {
      $입력 = trim(preg_replace('/원(?:화)?$/u', '', $입력));
    }
    echo 전송(스왑_환율_미리보기문구($입력, $원화모드));
    exit;
  }

  // 강화 비용/할인 설정 (표·실제 강화 공통 사용)
  // 전체게임냥 × 구간비율 → 게임냥 기준비 + 구간 내 배수 (+30/+60 정액 · 구간진입 ×3)
  $강화_할인적용 = false; // true 시 강화 비용 50% 할인
  $강화비용표 = function_exists('강화비용_단계표')
    ? 강화비용_단계표()
    : array();
  $강화성공분모가져오기 = function ($강화단계) {
    if ($강화단계 === 15) {
      return 10000; // 15 -> 16 : 0.05% (분자 5)
    } else if ($강화단계 === 16) {
      return 10000; // 16 -> 17 : 0.01% (분자 1)
    } else if ($강화단계 === 17) {
      return 100000; // 17 -> 18 : 0.005% (분자 5)
    } else if ($강화단계 === 18) {
      return 100000; // 18 -> 19 : 0.001% (분자 1)
    } else if ($강화단계 === 19) {
      return 1000000; // 19 -> 20 : 0.0001% (분자 1)
    }
    return 1000;
  };



  // 홍보방(info2): .랭킹1·2·3 — 전체 공개 (관리자 제한 없음)
  if (strpos($status, '.랭킹1') !== false) {
    $desc = 0;
    if (preg_match('/\.랭킹1\s*(.+)/u', $status, $match)) {
      $desc = (int)trim($match[1]);
    }
    $랭킹이모지 = isset($이모티콘) ? $이모티콘 : '';
    echo 전송(보유냥랭킹_문구($desc, $랭킹이모지));
    exit;
  }

  if (strpos($status, '.랭킹2') !== false) {
    $orderby = '';
    $desc = 0;
    if (preg_match('/\.랭킹2\s*(.+)/u', $status, $match)) {
      $desc = (int)trim($match[1]);
      if ($desc > 0) {
        $orderby = " LIMIT {$desc}";
      }
    }
    $합계행 = db_select("
      SELECT CONCAT('N', CAST(COALESCE(SUM(GREATEST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)), 0)), 0) AS CHAR)) AS total_pt
      FROM tb_member
    ");
    $총합 = (string)($합계행['total_pt'] ?? 'N0');
    if (isset($총합[0]) && ($총합[0] === 'N' || $총합[0] === 'n')) {
      $총합 = substr($총합, 1);
    }
    $총합표시 = function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($총합, $단위)
      : ($총합 . $단위);
    $result = db_query("
      SELECT name, level, title,
             CONCAT('N', CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR)) AS point
      FROM tb_member
      ORDER BY CAST(IFNULL(point, 0) AS DECIMAL(65,0)) DESC{$orderby}
    ");
    $a = 1;
    if ($desc > 0 && $desc < 10) {
      $랭킹 = "✅ 게임{$단위} 랭킹\n총합 {$총합표시}\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    } else {
      $랭킹 = "✅ 게임{$단위} 랭킹\n총합 {$총합표시}\n\n";
    }
    $랭킹이모지 = isset($이모티콘) ? $이모티콘 : '';
    while ($row = db_fetch($result)) {
      $계급 = 계급($row['point']);
      $호칭 = $row['title'] ?: $계급['name'];
      $pt표시 = 랭킹_게임냥표시($row['point'] ?? 0, $단위);
      // 신불자(마이너스 보유)는 금액 앞에 - 표기
      $is신불 = ((string)$row['title'] === '🆘신불자' || (bool)preg_match('/신불자/u', (string)$호칭));
      if ($is신불 && $pt표시 !== '' && substr($pt표시, 0, 1) !== '-') {
        $pt표시 = '-' . $pt표시;
      }
      $랭킹 .= $a . "등{$랭킹이모지} Lv {$row['level']} {$호칭} " . $row['name'] . " " . $pt표시 . "\n";
      $a++;
    }
    echo 전송($랭킹);
    exit;
  }

  if (strpos($status, '.랭킹3') !== false) {
    require_once __DIR__ . '/game/mining_tool.inc.php';
    $desc = 0;
    if (preg_match('/\.랭킹3\s*(.+)/u', $status, $match)) {
      $desc = (int)trim($match[1]);
    }
    $랭킹이모지 = isset($이모티콘) ? $이모티콘 : '';
    echo 전송(mining_tool_ranking_message($desc, $랭킹이모지));
    exit;
  }

  require_once __DIR__ . '/game/odd_even_info2.inc.php';

  // 홍보방: `.로또 구매 1~100` · `.로또 구매 전부`
  if (preg_match('/^\.로또\s*구매(?:\s+(\S+))?\s*$/u', trim($status), $_로또구매m)) {
    require_once __DIR__ . '/game/lotto_purchase.inc.php';
    로또_구매명령_실행($_로또구매m[1] ?? '');
  }

  if (preg_match('/^\.로또\s*자동(?:\s+(\d+))?\s*$/u', trim($status), $_로또자동m)) {
    require_once __DIR__ . '/game/lotto_purchase.inc.php';
    $요청개수 = 1;
    if (isset($_로또자동m[1]) && $_로또자동m[1] !== '') {
      $요청개수 = (int)$_로또자동m[1];
    }
    로또_구매명령_실행((string)$요청개수);
  }

  // 로또 채팅 핸들러 — `.로또*` 일 때만 로드 (매 요청 백필/파싱 방지)
  // 추첨·지급·당첨도 홍보방에서 실행 가능 (관리자)
  if (strpos(trim((string)$status), '.로또') === 0) {
    $LOTTO_AUTO_HANDLED_EXTERNALLY = true;
    $LOTTO_ADMIN_ONLY_INFO1 = false;
    require_once __DIR__ . '/game/lotto_chat.inc.php';
  }

  // 홍보방 전용 `.신청방` (본방 _mutual 과 분리)
  if ($status === ".신청방") {
    $msg = "📋 신청방

인원 3명 확인 후 
공창닉네임으로 입장 할 것!

https://open.kakao.com/o/gQj0R6Gi";
    echo 전송($msg);
    exit;
  }

  if (trim($status) === '.신용불량') {
    $rs = db_query("
      SELECT name, CAST(point AS CHAR) AS point, title, IFNULL(credit, 0) AS credit,
             credit_recovery_plus3_at,
             credit_debt_entered_at,
             IFNULL(credit_debt_times, 1) AS credit_debt_times,
             TIMESTAMPDIFF(MINUTE, NOW(), credit_recovery_plus3_at) AS remain_m
      FROM tb_member
      WHERE title = '🆘신불자'
         OR (IFNULL(credit, 0) = 1 AND point < 0)
         OR (credit_recovery_plus3_at IS NOT NULL AND credit_recovery_plus3_at > NOW())
      ORDER BY name ASC
    ");
    $rows = [];
    if ($rs) {
      while ($r = db_fetch($rs)) {
        $rows[] = $r;
      }
    }
    if (empty($rows)) {
      echo 전송("✅ 현재 신용불량(🆘신불자) 및 신용 회복 후 홀짝 제한 대상이 없습니다.");
      exit;
    }
    $out = "📋 신용불량·제한 현황\n\n";
    $n = 0;
    foreach ($rows as $r) {
      $nm = trim((string)($r['name'] ?? ''));
      if ($nm === '') {
        continue;
      }
      $n++;
      $ptRaw = trim((string)($r['point'] ?? '0'));
      $pt음수 = (isset($ptRaw[0]) && $ptRaw[0] === '-');
      $ptAbs = function_exists('냥_정수문자열')
        ? 냥_정수문자열($ptRaw)
        : (ltrim(preg_replace('/[^\d]/', '', $ptRaw), '0') ?: '0');
      $pt표시 = function_exists('냥축약표시')
        ? 냥축약표시($ptAbs, $단위)
        : (number_format((float)$ptAbs) . $단위);
      if ($pt음수) {
        $pt표시 = '-' . $pt표시;
      }
      $tit = (string)($r['title'] ?? '');
      $cr = (int)($r['credit'] ?? 0);
      $plus3 = $r['credit_recovery_plus3_at'] ?? null;
      $remain_m = isset($r['remain_m']) ? (int)$r['remain_m'] : 0;

      $is_신불 = ($tit === '🆘신불자' || (bool)preg_match('/신불자/u', $tit) || ($cr === 1 && $pt음수));
      $ts = $plus3 ? strtotime((string)$plus3) : false;
      $홀짝락 = ($ts !== false && $ts > time());

      $out .= "{$n}) {$nm}\n";
      if ($is_신불) {
        $out .= "   🆘 신불자 · 보유 {$pt표시}\n";
        $신불N = max(1, (int)($r['credit_debt_times'] ?? 1));
        $out .= "   ⏱ 신불 {$신불N}회차 · 보유냥 0 이상 회복 시 타이틀 해제\n";
      }
      if ($홀짝락) {
        $rm = max(1, $remain_m);
        $h = intdiv($rm, 60);
        $mm = $rm % 60;
        $남은문구 = ($h > 0) ? "약 {$h}시간 {$mm}분" : "약 {$mm}분";
        $until = date('n/j H:i', $ts);
        $out .= "   ⏳ 홀짝 도전 제한(신용 회복 후 3시간) · 남은 {$남은문구}\n   (해제 시각 {$until})\n";
      }
      $out .= "\n";
    }
    if ($n === 0) {
      echo 전송("✅ 현재 신용불량(🆘신불자) 및 신용 회복 후 홀짝 제한 대상이 없습니다.");
      exit;
    }
    echo 전송(rtrim($out));
    exit;
  }

  if (preg_match('/^\.홀짝신불단축\s+(\S+)/u', trim($status), $_홀짝신불단축_m)) {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있습니다.");
      exit;
    }
    $홀짝대상 = getTwoCharNick($_홀짝신불단축_m[1]);
    if ($홀짝대상 === '') {
      $홀짝대상 = preg_replace('/\s+/u', '', (string)$_홀짝신불단축_m[1]);
    }
    $홀짝대상_esc = addslashes($홀짝대상);
    $홀짝행 = db_select("SELECT idx, credit_recovery_plus3_at FROM tb_member WHERE name = '{$홀짝대상_esc}' LIMIT 1");
    if (empty($홀짝행['idx'])) {
      echo 전송("❌ 닉네임을 확인해 주세요.");
      exit;
    }
    $had_lock = !empty($홀짝행['credit_recovery_plus3_at']);
    db_query("UPDATE tb_member SET credit_recovery_plus3_at = NULL WHERE name = '{$홀짝대상_esc}' LIMIT 1");
    if ($had_lock) {
      echo 전송("✅ {$홀짝대상} — 신용 회복 후 홀짝 도전 제한(3시간)을 해제했습니다.");
    } else {
      echo 전송("✅ {$홀짝대상} — 이미 홀짝 도전 제한이 없습니다.");
    }
    exit;
  }

  if (trim((string)$status) === '.내냥') {
    // 조회 전용 — ALTER 금지(tb_member 락/타임아웃 → 응답 없음)
    $닉_esc_내냥 = addslashes($두자리닉넴);
    $pt행 = @db_select("SELECT CAST(point AS CHAR) AS point, CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$닉_esc_내냥}' LIMIT 1");
    $포인트 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($pt행['point'] ?? ($정보['point'] ?? 0))
      : preg_replace('/[^\d]/', '', (string)($pt행['point'] ?? ($정보['point'] ?? 0)));
    $포인트 = ltrim((string)$포인트, '0') ?: '0';
    $본냥 = $pt행['newpoint'] ?? ($정보['newpoint'] ?? 0);
    // 계급 구간은 조 단위 이하 — 초대형은 최고계급으로
    $계급기준 = (function_exists('bccomp') && bccomp($포인트, '1000000000000', 0) >= 0)
      ? 1000000000000
      : (int)min((float)$포인트, 1000000000000);
    $계급 = 계급($계급기준);
    $호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');
    $보유표시 = function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($포인트, $단위)
      : (function_exists('냥_경조_축약표시')
        ? 냥_경조_축약표시($포인트, $단위)
        : (preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $포인트) . $단위));
    $msg1 = trim($호칭 . ' ' . $두자리닉넴);
    $msg1 .= "\n본냥 : " . newpoint표시($본냥) . $단위;
    $msg1 .= "\n겜냥 : " . $보유표시;
    try {
      if (!function_exists('mining_pending_live_fmt')) {
        require_once __DIR__ . '/game/mining_config.inc.php';
        require_once __DIR__ . '/game/mining_storage.inc.php';
        require_once __DIR__ . '/game/mining_sync.inc.php';
      }
      if (function_exists('mining_pending_live_amount')) {
        $채굴량 = mining_pending_live_amount($두자리닉넴);
        if ($채굴량 + 1e-12 > 0) {
          $채굴표시 = function_exists('mining_fmt_pending')
            ? mining_fmt_pending($채굴량)
            : rtrim(rtrim(sprintf('%.10F', $채굴량), '0'), '.');
          $msg1 .= "\n채굴냥 : " . $채굴표시;
        }
        if (function_exists('mining_sync_row') && function_exists('mining_durability_payload')) {
          if (!function_exists('mining_durability_ensure_column')) {
            require_once __DIR__ . '/game/mining_durability.inc.php';
          }
          $mrow = mining_sync_row($두자리닉넴);
          if ($mrow) {
            $sim = function_exists('mining_sync_simulate_elapsed')
              ? mining_sync_simulate_elapsed($mrow, $두자리닉넴, null)
              : null;
            $dp = mining_durability_payload(
              $mrow,
              $두자리닉넴,
              $sim ? (float)$sim['durability'] : null
            );
            $msg1 .= "\n채굴내구 : " . (int)$dp['durability_display'] . '/' . (int)$dp['durability_max'];
            if (!empty($dp['durability_broken'])) {
              $msg1 .= " (정지 · .채굴수리)";
            }
          }
        }
      }
    } catch (Throwable $e) {
      // 채굴 조회 실패해도 본냥·겜냥은 표시
    }

    echo 전송($msg1);
    exit;

  }

  if (preg_match('/^\.\s*내무기\s*$/u', trim((string)$status))) {
    require_once __DIR__ . '/item/myweapon_chat.inc.php';
    내무기_채팅명령_처리($status, $두자리닉넴, $정보);
    exit;
  }

  if ($status === '.환전') {
    $포인트 = (int)($정보['point'] ?? 0);
    $msg = "💱 환전 시뮬레이션 ㅋㅋㅋ\n\n";
    $msg .= "{$호칭} {$두자리닉넴} 보유: " . 콤마삽입($포인트) . "{$단위}\n";
    $msg .= "환율: 천억{$단위} → 현금 1,000원\n\n";
    $msg .= "💵 환전하면 약 " . 환전현금표시($포인트) . "\n";
    $msg .= "(장난이에요 실제 환전 안 됩니다 ㅋㅋ)";
    echo 전송($msg);
    exit;

  }else if (strpos($status, '.양도') !== false) {
    require_once __DIR__ . '/yangdo.inc.php';
    $양도닉 = trim((string)($정보['name'] ?? $두자리닉넴));
    try {
      양도_게임냥_명령_처리($status, $양도닉, $단위, (int)($정보['level'] ?? 0), $정보);
    } catch (Throwable $e) {
      echo 전송('❌ 양도 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
      exit;
    }
    // 명령 처리가 exit 하지 못한 경우 대비
    echo 전송('❌ 양도 처리에 실패했습니다.');
    exit;

  }else if (trim($status) === ".랜덤박스란") {
    $msg = "🎁 랜덤박스란?\n\n";
    $msg .= "1️⃣ 랜덤박스가 뭐예요?\n";
    $msg .= "- 보관함에 쌓이는 아이템이에요. 까면 그때마다 다른 보상이 무작위로 나와요.\n\n";
    $msg .= "2️⃣ 나올 수 있는 보상\n";
    $msg .= "- 💰 게임냥(1만~50만)만 나와요.\n\n";
    $msg .= "3️⃣ 어떻게 써요?\n";
    $msg .= "- `.ㄹㄷ 10` 처럼 숫자를 붙여 입력해요. (한 번에 10개 이상만 일괄로 깔 수 있어요)\n\n";
    $msg .= "4️⃣ 어떻게 모으나요?\n";
    $msg .= "- 레벨업·출석·활동 달성 등으로 지급될 수 있어요.";
    echo 전송($msg);
    exit;

  }else if (trim($status) === ".채굴란") {
    require_once __DIR__ . '/game/mining_chat_help.inc.php';
    echo 전송(mining_chat_help_message());
    exit;

  }else if (preg_match('/^\.ㄹㄷ(?:\s*(\d+))?\s*$/u', trim($status), $ㄹㄷ매치)) {
    // .ㄹㄷ N : 랜덤박스 일괄 까기 (10개 이상만 가능)
    게임제한_차단($두자리닉넴);
    $박스개수 = (isset($ㄹㄷ매치[1]) && $ㄹㄷ매치[1] !== '') ? (int)$ㄹㄷ매치[1] : 0;

    if ($박스개수 < 10) {
      echo 전송("❌ 랜덤박스는 한번에 10개 이상만 깔 수 있어!\n사용법: .ㄹㄷ 10  (10개 이상 숫자 입력)");
      exit;
    }

    $닉_esc = addslashes($두자리닉넴);

    // 보유 박스 개수 확인
    $보유행 = db_select("SELECT COUNT(*) AS cnt FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '랜덤박스' AND status = 0");
    $현재보유개수 = (int)($보유행['cnt'] ?? 0);
    if ($현재보유개수 < $박스개수) {
      echo 전송("❌ 랜덤박스 부족! (요청 {$박스개수}개 · 보유 {$현재보유개수}개)");
      exit;
    }

    // 사용할 박스 idx 수집
    $박스목록 = [];
    $박스rs = db_query("SELECT idx FROM tb_member_item WHERE nick = '{$닉_esc}' AND itemname = '랜덤박스' AND status = 0 ORDER BY idx ASC LIMIT {$박스개수}");
    while ($박스rs && $r = db_fetch($박스rs)) {
      $박스목록[] = (int)$r['idx'];
    }
    if (count($박스목록) < $박스개수) {
      echo 전송("❌ 랜덤박스 처리 중 오류! 다시 시도해줘.");
      exit;
    }

    // 박스 일괄 사용 처리 (소모)
    $박스인절 = implode(',', $박스목록);
    db_query("UPDATE tb_member_item SET status = 1 WHERE idx IN ({$박스인절})");
    아이템사용_시세하락('랜덤박스', $박스개수);

    // 보상: 게임냥(1만~50만)만
    $총냥 = 0;
    $결과상세 = [];

    for ($i = 0; $i < $박스개수; $i++) {
      $번호 = $i + 1;
      $금액 = rand(10000, 500000);
      $총냥 += $금액;
      $결과상세[] = "{$번호}) 💰 " . number_format($금액) . "냥";
    }

    // 합산 DB 반영
    if ($총냥 > 0) {
      db_query("UPDATE tb_member SET point = point + {$총냥} WHERE name = '{$닉_esc}'");
    }

    // 결과 메시지 구성
    $msg = "🎁 [{$두자리닉넴}] 랜덤박스 {$박스개수}개 결과\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
    $msg .= "─────────────\n";
    $msg .= implode("\n", $결과상세) . "\n";
    $msg .= "─────────────\n";
    $msg .= "📊 합계\n";
    $msg .= "💰 게임냥 +" . number_format($총냥) . "냥\n";

    echo 전송(rtrim($msg));
    exit;


  }else if($status==".버프"){
    require_once __DIR__ . '/buff.inc.php';
    버프_명령_처리($status, $두자리닉넴, (array)($관리자 ?? []));

  }else if (preg_match('/^\.프변(?:\s+(\d+))?\s*$/u', trim($status), $프변m)) {
    $개수 = isset($프변m[1]) ? (int)$프변m[1] : 1;
    if ($개수 < 1) {
      echo 전송("❌ 사용 개수는 1 이상으로 입력해주세요.\n예) .프변 5");
      exit;
    }
    echo 전송(프변_명령_처리($두자리닉넴, $개수));
    exit;
  }else if(strpos($status, ".가방") === 0){
    if (function_exists('가방_명령_처리')) {
      가방_명령_처리($status, $호칭 ?? '', $정보, $두자리닉넴);
    }
    exit;
  }else if(strpos($status, '.선물') === 0){
    require_once __DIR__ . '/gift_chat.inc.php';
    선물_명령_처리($status, $두자리닉넴, (array)$정보);
    exit;
    }else if($status == ".타이틀"){
      if (function_exists('타이틀_명령_처리')) {
        타이틀_명령_처리($status, $두자리닉넴, is_array($정보 ?? null) ? $정보 : []);
      }
      echo 전송('❌ 타이틀 기능을 불러올 수 없어요.');
      exit;

  }else if($status==".등장" || $status=="등장"){
    $타이틀 = trim($정보['title'] ?? '');
    $내템 = trim($정보['item'] ?? '');
    $내강 = (int)($정보['enhance'] ?? 0);
    $오강이상 = ($내템 !== '' && $내강 >= 7);
    $스타일문구 = ($오강이상 && trim($정보['style'] ?? '') !== '') ? "+".$내강." ".trim($정보['style']) . ' ' : '';
    // 5강 이상 아이템 있음 → 스타일+아이템 (타이틀 있으면 타이틀까지)
    if ($오강이상) {
      if ($타이틀 !== '') {
        echo 전송("{$스타일문구} {$내템} {$타이틀} {$두자리닉넴} 등장!");
      } else {
        echo 전송("{$스타일문구} {$내템} {$두자리닉넴} 등장!");
      }
      exit;
    }
    // 타이틀만 있으면 타이틀만
    if ($타이틀 !== '') {
      echo 전송($타이틀." {$두자리닉넴} 등장!");
      exit;
    }

  }else if(trim($status) === ".강화비용"){
    $은총활성_비용 = 강화_은총_활성($정보['은총'] ?? '');
    $은총비용할인_표 = $은총활성_비용
        && defined('강화_은총_비용할인_활성')
        && 강화_은총_비용할인_활성;
    $msg = "⚔️ 강화 비용 표";
    $본냥미션할인 = 0;
    if (!function_exists('gv_본냥미션_강화할인율')) {
      $missionInc = __DIR__ . '/game/vault_bon_mission.inc.php';
      if (is_file($missionInc)) {
        include_once $missionInc;
      }
    }
    if (function_exists('gv_본냥미션_강화할인율')) {
      $본냥미션할인 = (int)gv_본냥미션_강화할인율();
    }
    if ($강화_할인적용) {
      $msg .= " (현재 50% 할인 적용 중)\n\n";
    } elseif ($은총비용할인_표) {
      $은총끝 = !empty($정보['은총']) ? date('H:i', strtotime($정보['은총'])) : '';
      $msg .= " (✨ 은총 버프 중 → 강화 비용 50% · {$은총끝}까지)\n\n";
    } elseif ($은총활성_비용) {
      $은총끝 = !empty($정보['은총']) ? date('H:i', strtotime($정보['은총'])) : '';
      $msg .= " (✨ 은총 버프 중 · 강화비 할인 없음 · {$은총끝}까지)\n\n";
    } elseif ($본냥미션할인 > 0) {
      $msg .= " (본냥 전체미션 강화비 {$본냥미션할인}% 할인 적용 중)\n\n";
    } else {
      $msg .= " (현재 할인 없음)\n\n";
    }
    if ($본냥미션할인 > 0 && ($강화_할인적용 || $은총비용할인_표 || $은총활성_비용)) {
      $msg = rtrim($msg) . "\n· 본냥 전체미션 강화비 {$본냥미션할인}% 할인 포함\n\n";
    }
    $시총문구 = function_exists('강화비용_시총표시')
      ? 강화비용_시총표시()
      : (function_exists('강화비용_표시') && function_exists('강화비용_전체게임냥')
        ? 강화비용_표시(강화비용_전체게임냥(), '게임냥')
        : '');
    if ($시총문구 !== '') {
      $msg .= "기준 시세 스냅샷 게임냥: {$시총문구}";
      if (function_exists('강화비용_시총_폴백인가') && 강화비용_시총_폴백인가()) {
        $msg .= " ⚠스냅샷미확인·설계총량";
      } else {
        $msg .= " (시세 스냅샷 · `.스냅샷`)";
      }
      $msg .= "\n";
    }
    if (function_exists('강화비용_유효시총표시')) {
      $유효문구 = 강화비용_유효시총표시('게임냥');
      $msg .= "강화비용 유효시총: {$유효문구}";
      if (function_exists('강화비용_시총완만화_적용중인가') && 강화비용_시총완만화_적용중인가()) {
        $msg .= " (시총 하드캡 · 스냅샷 게임냥의 30%)";
      } else {
        $msg .= " (스냅샷 게임냥의 30%)";
      }
      $msg .= "\n";
    }
    $msg .= "※ 강화비 = ceil(유효시총 × 구간비율) → 게임냥 차감 · 10강 구간별\n";
    $msg .= "※ 보통 구간 안 +10%씩 · +30~39/+60~69 정액 계단 · 구간진입(+9 등) ×3\n";
    $msg .= "※ 강화비 소멸분: 50% 소멸 · 25% 금고 · 25% 로또\n\n";

    // 현재 무기/강화 정보 기준으로 내구도 안내
    $현재아이템 = trim($정보['item'] ?? '');
    $현재강화 = (int)($정보['enhance'] ?? 0);
    if ($현재아이템 !== '') {
      $msg .= "현재 무기: {$현재아이템} +{$현재강화}\n";
      $msg .= "※ +10 이상 구간부터 현재 무기 기준 예상 최대 내구도가 함께 표시됩니다.\n\n";
    } else {
      $msg .= "※ +10 이상 무기부터 내구도가 부여됩니다.\n\n";
    }

    // 시총 비율 단계표로 재생성 (구 고정표 캐시 방지)
    if (function_exists('강화비용_단계표')) {
      $강화비용표 = 강화비용_단계표();
    }

    foreach ($강화비용표 as $단계 => $비용기본) {
      $실비용 = 강화비용_산출($단계, $강화비용표, $강화_할인적용, $은총비용할인_표, $강화_테스트모드);
      $from = $단계;
      $to = $단계 + 1;

      // 내구도 표기 (현재 무기 기준, +10 이상 구간만)
      $내구도문구 = '';
      if ($현재아이템 !== '' && $to >= 10) {
        $최대내구도 = 무기_최대내구도($현재아이템, $to);
        if ($최대내구도 > 0) {
          $내구도문구 = " / 예상 최대 내구도 {$최대내구도}";
        }
      }

      $비용문구 = function_exists('강화비용_표시')
        ? 강화비용_표시($실비용, '게임냥')
        : (number_format((float)$실비용) . '게임냥');
      $msg .= "+{$from} → +{$to} : {$비용문구}{$내구도문구}\n";
    }
    echo 전송($msg);
    exit;

  }else if(trim($status) === '.강화파손방지'){
    $msg  = "🛡️ 무기 강화 파손 방지 방법\n\n";
    $msg .= "핵심은 `수호` 누적이야.\n\n";
    $msg .= "1) `.강화 수호` 로 누적\n";
    $msg .= "- 수호 아이템이 있으면 아이템 먼저 사용, 없으면 1회 본방 0.1냥 차감.\n";
    $msg .= "- 1회당 수호 수치 +1~+10 랜덤 누적.\n";
    $msg .= "- 지금 기준 본방냥 1회: " . number_format((float)강화수호_회당비용(), 1) . "냥\n\n";
    $msg .= "2) 강화 실패가 나면\n";
    $msg .= "- 수호가 1회 이상 있으면: 파손 안 되고 `무기/강화 유지` + 수호 1회 차감.\n";
    $msg .= "- 수호가 0이면: 강화 실패 시 무기가 파손되어 초기화돼.\n\n";
    $msg .= "3) 수호는 어디서 봐?\n";
    $msg .= "- `.가방` 에서 `강화 수호 xN` 으로 남은 횟수 확인 가능.\n\n";
    $msg .= "4) 더 안전하게 하고 싶으면\n";
    $msg .= "- `.강화비용` 으로 현재 무기 기준 비용/예상 내구도도 같이 확인하고 강화해.";
    echo 전송($msg);
    exit;

  }else if(strpos(trim($status), '.강화') === 0){
    // .강화수호 처럼 띄어쓰기 없이 입력한 경우 경고 (출석체크 전에 우선 안내)
    if (preg_match('/^\.강화[^\s]/u', trim($status))) {
      echo 전송("❌ .강화 혹은 .강화 수호 라고 정확히 입력해주세요! (띄어쓰기 확인)");
      exit;
    }

    // 오늘 출석한 친구만 .강화 사용 가능
    $오늘출석일 = (string)($정보['last_att_date'] ?? '');
    if ($오늘출석일 !== (string)$오늘) {
      echo 전송("❌ `.강화` 는 오늘 출석 완료 후에 이용 가능해요!\n먼저 출석부터 챙겨주세요 🙌");
      exit;
    }

    // 종류×강화 중복(마법 +31 2명 등) 즉시 정리 — 최신 1명 유지·기존 하향
    if (function_exists('강화_독점중복_정리')) {
      @강화_독점중복_정리();
    }

    if (preg_match('/^\.강화\s+이력(?:\s+(\d+))?\s*$/u', trim($status), $이력m)) {
      $이력개수 = isset($이력m[1]) && $이력m[1] !== '' ? (int)$이력m[1] : 10;
      echo 전송(강화_이력_목록_문구($두자리닉넴, $이력개수));
      exit;
    }

    $강화_테스트모드 = false; // true 시 구매/강화 비용 전부 100냥
    $첫무기구매비용 = $강화_테스트모드 ? 100 : 100000;
    $지정무기명 = '';
    $수호사용개수 = 0;
    $연속횟수 = 1;
    if (preg_match('/^\.강화\s+(\S+)(?:\s+(\d+))?/u', trim($status), $m)) {
      $첫토큰 = trim($m[1]);
      if (preg_match('/^\d+$/u', $첫토큰)) {
        $연속횟수 = (int)$첫토큰;
        if ($연속횟수 < 1) {
          $연속횟수 = 1;
        }
        if ($연속횟수 > 10) {
          $연속횟수 = 10;
        }
      } else {
        $지정무기명 = $첫토큰;
        if (isset($m[2]) && trim($m[2]) !== '') {
          $수호사용개수 = (int)trim($m[2]);
        }
      }
    }
    $닉_esc = addslashes($두자리닉넴);

    // .강화 수호 [N] → 수호 아이템 우선, 부족분 냥 차감
    if ($지정무기명 === '수호') {
      if ($수호사용개수 < 1) {
        $수호사용개수 = 1;
      }
      $수호결과 = 강화수호_냥적용($두자리닉넴, $수호사용개수, $단위);
      echo 전송($수호결과['msg'] ?? '❌ 강화 수호 처리에 실패했어요.');
      exit;
    }

    $무기맵 = array('단소'=>'🪈단소', '활'=>'🏹활', '마법'=>'🪄마법');


    $지정구매 = ($지정무기명 !== '' && isset($무기맵[$지정무기명]));
    $구매비용 = $첫무기구매비용;
    $현재아이템 = trim($정보['item'] ?? '');
    $현재강화 = (int)($정보['enhance'] ?? 0);
    $강화최대 = function_exists('강화_최대') ? 강화_최대() : 100;

    if ($현재아이템 === '') {
      if (!$지정구매) {
        if ($지정무기명 !== '') {
          echo 전송(
            "❌ 알 수 없는 무기예요.\n".
            "`.강화 단소` / `.강화 활` / `.강화 마법` 중 하나로 구매해주세요.\n".
            "(첫 무기 구매: ".number_format($첫무기구매비용) . "{$단위})"
          );
        } else {
          echo 전송(
            "⚔️ [ {$두자리닉넴} ] 보유 무기가 없어요!\n".
            "아래 중 원하는 무기를 골라 구매해주세요.\n\n".
            "· `.강화 단소` 🪈단소\n".
            "· `.강화 활` 🏹활\n".
            "· `.강화 마법` 🪄마법\n\n".
            "첫 무기 구매 비용: ".number_format($첫무기구매비용) . "{$단위}"
          );
        }
        exit;
      }
      if (empty($정보['point']) || $정보['point'] < $구매비용) {
        echo 전송("❌ 구매에 필요한 {$단위}이 부족해요. (필요: ".number_format($구매비용) . "{$단위})");
        exit;
      }
      $자숙위반 = 자숙_강화위반_적용($두자리닉넴, $단위);
      $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
      if (function_exists('무기_타입_스키마보장')) {
        무기_타입_스키마보장();
      }
      $구매타입 = function_exists('무기_타입_키에서') ? 무기_타입_키에서($지정무기명) : 0;
      $선택무기 = (function_exists('무기_표시아이템') && $구매타입 > 0)
        ? 무기_표시아이템($구매타입, 0)
        : $무기맵[$지정무기명];
      if (function_exists('무기_장착_갱신') && $구매타입 > 0) {
        $rs = 무기_장착_갱신($두자리닉넴, $구매타입, 0, ["point = point - {$구매비용}"]);
      } else {
        $무기_esc = addslashes($선택무기);
        $rs = db_query("UPDATE tb_member SET point = point - {$구매비용}, `item` = '{$무기_esc}', `enhance` = 0 WHERE name = '{$닉_esc}'");
      }
      if (!$rs) {
        echo 전송("❌ 강화 처리 실패");
        exit;
      }
      미션완료_기록_if_new($두자리닉넴, '일방', '무기구매');
      echo 전송($자숙위반안내."⚔️ {$두자리닉넴} {$선택무기} 구매완료! (".number_format($구매비용) . "{$단위} 차감)");
      exit;
    }
    $성공확률표 = array(
      0 => 990,
      1 => 900,
      2 => 800,
      3 => 400,
      4 => 600,
      5 => 500,
      6 => 300,
      7 => 200,
      8 => 100,
      9 => 50,
      10 => 30,
      11 => 20,
      12 => 10,
      13 => 5,
      14 => 1,
      15 => 5,
      16 => 1,
      17 => 5,
      18 => 1,
      19 => 1,
    );

    $연속결과 = [];
    $누적비용 = '0';
    $연속성공 = 0;
    $연속수호 = 0;
    $마지막회차메시지 = '';

    for ($회차 = 1; $회차 <= $연속횟수; $회차++) {
      $정보 = db_select("SELECT *, CAST(point AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
      if (empty($정보['idx'])) {
        echo 전송("❌ 회원 정보를 찾을 수 없어요.");
        exit;
      }

      $현재아이템 = trim($정보['item'] ?? '');
      $현재강화 = (int)($정보['enhance'] ?? 0);
      $스타일문구 = trim($정보['style'] ?? '') !== '' ? trim($정보['style']) : '';

      if ($현재아이템 === '') {
        $회차메시지 = "❌ 보유 무기가 없어요.";
        if ($회차 === 1 && $연속횟수 === 1) {
          echo 전송($회차메시지);
          exit;
        }
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n{$회차메시지}" : $회차메시지;
        break;
      }
      if ($현재강화 >= $강화최대) {
        $회차메시지 = "⚔️ [ {$두자리닉넴} ] {$현재아이템} 이미 최대 강화 +{$강화최대} 입니다.";
        if ($회차 === 1 && $연속횟수 === 1) {
          echo 전송($회차메시지);
          exit;
        }
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n중단 — {$회차메시지}" : $회차메시지;
        break;
      }

      $도전모드 = 강화20_도전모드_정보($현재아이템, $현재강화, $두자리닉넴);
      $보유자_esc = $도전모드 ? addslashes($도전모드['보유자닉']) : '';

      $은총효과 = function_exists('강화_은총_효과') ? 강화_은총_효과($두자리닉넴) : ['active' => 강화_은총_활성($정보['은총'] ?? ''), 'zeros' => 강화_은총_활성($정보['은총'] ?? '') ? 1 : 0, 'cost_discount' => 강화_은총_활성($정보['은총'] ?? '')];
      $은총활성 = !empty($은총효과['active']);
      $은총제로 = (int)($은총효과['zeros'] ?? 0);
      $은총비할인 = !empty($은총효과['cost_discount']);
      $강화비용 = $도전모드
        ? $도전모드['비용']
        : 강화비용_산출($현재강화, $강화비용표, $강화_할인적용, $은총비할인, $강화_테스트모드);
      $강화비용_sql = function_exists('강화비용_sql') ? 강화비용_sql($강화비용) : (string)(int)$강화비용;
      $보유포인트 = function_exists('냥_정수문자열')
        ? 냥_정수문자열($정보['point'] ?? 0)
        : (string)($정보['point'] ?? 0);
      $비용표시 = function_exists('강화비용_표시')
        ? 강화비용_표시($강화비용, '게임냥')
        : (number_format((float)$강화비용) . '게임냥');
      $누적표시 = function_exists('강화비용_표시')
        ? 강화비용_표시($누적비용, '게임냥')
        : (number_format((float)$누적비용) . '게임냥');
      $포인트충분 = function_exists('강화비용_포인트충분')
        ? 강화비용_포인트충분($보유포인트, $강화비용)
        : ((float)$보유포인트 >= (float)$강화비용);
      if ($보유포인트 === '' || $보유포인트 === '0' || !$포인트충분) {
        $회차메시지 = "❌ 강화에 필요한 게임냥이 부족해요. ({$비용표시})";
        if ($회차 === 1 && $연속횟수 === 1) {
          echo 전송($회차메시지);
          exit;
        }
        $연속결과[] = ($연속횟수 > 1)
          ? "━━ {$회차}/{$연속횟수} ━━\n중단 — {$회차메시지} (누적 {$누적표시})"
          : $회차메시지;
        break;
      }

      $자숙위반 = 자숙_강화위반_적용($두자리닉넴, $단위);
      $자숙위반안내 = (string)($자숙위반['notice'] ?? '');

      if (function_exists('강화_스키마_보장')) {
        강화_스키마_보장();
      }
      if (function_exists('강화_성공분자')) {
        $성공분자 = 강화_성공분자((int)$현재강화);
        $성공분모 = 강화_성공분모((int)$현재강화);
      } else {
        $성공분자 = isset($성공확률표[$현재강화]) ? (int)$성공확률표[$현재강화] : 1;
        $성공분모 = (int)$강화성공분모가져오기($현재강화);
      }
      if ($도전모드) {
        $성공분자 = max(1, (int)($도전모드['분자'] ?? $성공분자));
        $성공분모 = 강화20_도전모드_성공분모($은총제로, $도전모드);
      } elseif ($은총제로 > 0 && function_exists('강화_분모_제로제거')) {
        $성공분모 = 강화_분모_제로제거((int)$성공분모, $은총제로);
      } elseif ($은총활성) {
        $성공분모 = (int)max(1, floor($성공분모 / 10));
      }
      if ($성공분자 < 1) {
        $성공분자 = 1;
      }
      if ($성공분자 > $성공분모) {
        $성공분자 = $성공분모;
      }

      $확률숫자 = rand(1, $성공분모);
      $성공 = ($확률숫자 <= $성공분자);
      $성공확률_문구 = $도전모드
        ? 강화20_도전모드_성공확률문구($은총제로, $도전모드)
        : rtrim(rtrim(number_format(($성공분자 / $성공분모) * 100, 6, '.', ''), '0'), '.') . '%';
      $도전안내 = $도전모드 ? (" · +" . (int)($도전모드['목표강화'] ?? ($현재강화 + 1)) . " [{$도전모드['보유자닉']}] 탈취 도전") : '';
      $보상안내 = '';

      $rs = db_query("UPDATE tb_member SET point = point - {$강화비용_sql} WHERE name = '{$닉_esc}' AND point >= {$강화비용_sql} LIMIT 1");
      if (!$rs) {
        $회차메시지 = "❌ 강화 처리 실패.";
        if ($회차 === 1 && $연속횟수 === 1) {
          echo 전송($회차메시지);
          exit;
        }
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n중단 — {$회차메시지}" : $회차메시지;
        break;
      }
      global $conn;
      if (($conn instanceof mysqli) && (int)mysqli_affected_rows($conn) <= 0) {
        $회차메시지 = "❌ 강화에 필요한 {$단위}이 부족해요. ({$비용표시})";
        if ($회차 === 1 && $연속횟수 === 1) {
          echo 전송($회차메시지);
          exit;
        }
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n중단 — {$회차메시지}" : $회차메시지;
        break;
      }
    // 소멸분 재분배(50% 소멸 · 25% 금고 · 25% 로또) — 도전 시 보유자 보상 제외
      if (function_exists('강화비용_소멸분배')) {
        $소멸분배대상 = $강화비용;
        if ($도전모드) {
          $보유자보상분 = $도전모드['보유자보상'] ?? 0;
          if (function_exists('bcsub') && function_exists('냥_정수문자열')) {
            $소멸분배대상 = bcsub(냥_정수문자열($강화비용), 냥_정수문자열($보유자보상분), 0);
          } elseif (function_exists('강화비용_sql')) {
            $소멸분배대상 = max(0, (int)강화비용_sql($강화비용) - (int)강화비용_sql($보유자보상분));
          } else {
            $소멸분배대상 = $도전모드['소멸'] ?? $강화비용;
          }
        }
        강화비용_소멸분배($소멸분배대상, $두자리닉넴);
      }
      $누적비용 = function_exists('bcadd')
        ? bcadd((string)$누적비용, $강화비용_sql, 0)
        : (string)((int)$누적비용 + (int)$강화비용_sql);
      if ($도전모드) {
        $보상금 = 강화20_도전_보유자보상지급($보유자_esc, $도전모드['보유자보상']);
        $소멸금 = $도전모드['소멸'];
        $보상표시 = function_exists('강화비용_표시') ? 강화비용_표시($보상금, $단위) : (number_format((float)$보상금) . $단위);
        $소멸표시 = function_exists('강화비용_표시') ? 강화비용_표시($소멸금, $단위) : (number_format((float)$소멸금) . $단위);
        $보상안내 = " · [{$도전모드['보유자닉']}] +{$보상표시} · 소멸 {$소멸표시}";
      }

      if ($성공) {
        $탈취안내 = '';
        $역풍발생 = false;
        $대성공안내 = '';
        $상승 = null;
        $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
        if ($도전모드) {
          $목표강화 = (int)($도전모드['목표강화'] ?? ($현재강화 + 1));
          $도전자결과 = 강화20_도전_성공시_도전자강화($목표강화, $현재강화);
          $다음강화 = (int)$도전자결과['enhance'];
          if (!empty($도전자결과['crit'])) {
            $상승 = $도전자결과;
            $대성공안내 = (string)($도전자결과['안내'] ?? '');
          }
        } else {
          $상승 = function_exists('강화_성공다음강화')
            ? 강화_성공다음강화($현재강화, $강화최대)
            : ['enhance' => $현재강화 + 1, '안내' => ''];
          $다음강화 = (int)$상승['enhance'];
          $대성공안내 = (string)($상승['안내'] ?? '');
        }
        // 종류별 독점 좌석: 목표~도달 강화 기존 보유자 하향 (대성공 점프 포함)
        if ($도전모드) {
          $하향들 = [];
          if (function_exists('강화_독점좌석_상승선점')) {
            $하향들 = 강화_독점좌석_상승선점($현재아이템, $현재강화, $다음강화, $두자리닉넴, $강화주체닉);
          } elseif ($다음강화 >= (defined('강화_독점시작') ? (int)강화_독점시작 : 1) && function_exists('강화_독점좌석_선점')) {
            $하향들 = 강화_독점좌석_선점($현재아이템, $다음강화, $두자리닉넴, $강화주체닉);
          }
          if (!empty($하향들)) {
            $탈취조각 = [];
            foreach ($하향들 as $h) {
              $탈취조각[] = "[{$h['nick']}] +{$h['from']}→+{$h['to']}";
            }
            $탈취안내 = " · +{$다음강화}탈취 " . implode(' · ', $탈취조각);
          } else {
            $하향후 = 강화20_도전_성공시_보유자하향($도전모드['보유자닉'], $보유자_esc, $현재아이템, $목표강화, $강화주체닉);
            $탈취안내 = " · +{$다음강화}탈취 [{$도전모드['보유자닉']}] +{$목표강화}→+{$하향후}";
          }
        } elseif (function_exists('강화_독점좌석_상승선점')) {
          강화_독점좌석_상승선점($현재아이템, $현재강화, $다음강화, $두자리닉넴, $강화주체닉);
        } elseif ($다음강화 >= (defined('강화_독점시작') ? (int)강화_독점시작 : 1) && function_exists('강화_독점좌석_선점')) {
          강화_독점좌석_선점($현재아이템, $다음강화, $두자리닉넴, $강화주체닉);
        }
        db_query("UPDATE tb_member SET `enhance` = {$다음강화}, 강화성공시간 = now() WHERE name = '{$닉_esc}'");
        if (function_exists('무기_강화후_표시동기화')) {
          무기_강화후_표시동기화($강화주체닉, $정보 ?? $현재아이템, $다음강화);
          if (function_exists('무기_표시아이템') && function_exists('무기_타입값')) {
            $동기화타입 = 무기_타입값($정보 ?? $현재아이템);
            if ($동기화타입 > 0) {
              $현재아이템 = 무기_표시아이템($동기화타입, $다음강화);
              $정보['item'] = $현재아이템;
              $정보['무기타입'] = $동기화타입;
              $정보['enhance'] = $다음강화;
            }
          }
        }
        $연속성공++;

        if ($다음강화 >= 10) {
          db_query("UPDATE tb_member SET magic_used = 0, magic_window = NOW() WHERE name = '{$닉_esc}'");
          $현재아이템_trim = trim($현재아이템);
          if (function_exists('무기_마법인가') ? 무기_마법인가($정보 ?? $현재아이템_trim) : ($현재아이템_trim === '🪄마법' || $현재아이템_trim === '🪄 마법')) {
            db_query("UPDATE tb_member SET protect_used = 0, protect_reset_date = NOW() WHERE name = '{$닉_esc}'");
          }
          $내구도 = 무기_최대내구도($현재아이템_trim, $다음강화);
          if ($내구도 > 0) {
            $기존행 = db_select("SELECT durability FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
            $기존내구도 = isset($기존행['durability']) && $기존행['durability'] !== null ? (int)$기존행['durability'] : 0;
            if ($기존내구도 < $내구도) {
              db_query("UPDATE tb_member SET durability = {$내구도} WHERE name = '{$닉_esc}'");
            }
          }
        }

        // 구간진입·대성공 알림 (탈취 도전 대성공 포함)
        if (empty($역풍발생) && function_exists('강화_구간진입_알림등록')) {
          $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
          강화_구간진입_알림등록($강화주체닉, $현재아이템, $현재강화, $다음강화);
        }
        if (empty($역풍발생) && is_array($상승) && !empty($상승['crit']) && function_exists('강화_대성공_알림등록')) {
          $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
          강화_대성공_알림등록($강화주체닉, $현재아이템, $현재강화, $다음강화, (int)($상승['gain'] ?? 0));
        }

        if (function_exists('강화_이력_기록')) {
          $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
          강화_이력_기록([
            'nick'             => $강화주체닉,
            'channel'          => 'chat',
            'item'             => $현재아이템,
            'style'            => $스타일문구,
            'enhance_before'   => $현재강화,
            'enhance_after'    => $다음강화,
            'result'           => 'success',
            'cost'             => $강화비용,
            'dice'             => $확률숫자,
            'dice_num'         => $성공분자,
            'dice_den'         => $성공분모,
            'rate'             => $성공확률_문구,
            'eunchong'         => $은총활성,
            'challenge_mode'   => (bool)$도전모드,
            'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
            'challenge_steal'  => $도전모드 ? !empty($도전자결과['탈취성공']) : null,
          ]);
        }

        $다음확률_분자 = isset($성공확률표[$다음강화]) ? (int)$성공확률표[$다음강화] : 1;
        $다음확률_분모 = (int)$강화성공분모가져오기($다음강화);
        if ($다음확률_분자 < 1) {
          $다음확률_분자 = 1;
        }
        if ($다음확률_분자 > $다음확률_분모) {
          $다음확률_분자 = $다음확률_분모;
        }
        $다음확률_문구 = rtrim(rtrim(number_format(($다음확률_분자 / $다음확률_분모) * 100, 6, '.', ''), '0'), '.') . '%';
        $회차메시지 = $자숙위반안내."{$두자리닉넴} ⚔️ 강화 성공! 확률 {$확률숫자}\n{$스타일문구} {$현재아이템} +{$현재강화}→+{$다음강화}{$대성공안내}{$도전안내}{$탈취안내}{$보상안내}\n(다음 강화 성공확률 {$다음확률_문구} · {$비용표시} 차감)";
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n{$회차메시지}" : $회차메시지;
        $마지막회차메시지 = $회차메시지;

        if ($다음강화 >= $강화최대) {
          break;
        }
        continue;
      }

      $수호방지 = function_exists('강화실패_수호방지_적용')
        ? 강화실패_수호방지_적용($두자리닉넴)
        : null;
      if ($수호방지 !== null) {
        $남은수호 = (int)$수호방지['enhance_suho'];
        $수호문구 = (string)($수호방지['msg_suffix'] ?? '');
        $연속수호++;
        if (function_exists('강화_이력_기록')) {
          $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
          강화_이력_기록([
            'nick'             => $강화주체닉,
            'channel'          => 'chat',
            'item'             => $현재아이템,
            'style'            => $스타일문구,
            'enhance_before'   => $현재강화,
            'enhance_after'    => $현재강화,
            'result'           => 'fail_protect',
            'cost'             => $강화비용,
            'dice'             => $확률숫자,
            'dice_num'         => $성공분자,
            'dice_den'         => $성공분모,
            'rate'             => $성공확률_문구,
            'eunchong'         => $은총활성,
            'challenge_mode'   => (bool)$도전모드,
            'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
            'suho_used'        => true,
            'suho_left'        => $남은수호,
          ]);
        }
        $회차메시지 = $자숙위반안내."👼 강화 실패! 파손 방지{$수호문구}\n{$두자리닉넴} {$현재아이템} +{$현재강화} 유지 ($확률숫자){$도전안내}{$보상안내}\n(성공확률 {$성공확률_문구} · {$비용표시} 차감)";
        $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n{$회차메시지}" : $회차메시지;
        $마지막회차메시지 = $회차메시지;
        $정보['enhance_suho'] = $남은수호;
        continue;
      }

      if (function_exists('무기_해제')) {
        무기_해제($두자리닉넴, ["`style` = ''"]);
      } else {
        db_query("UPDATE tb_member SET `item` = NULL, `enhance` = 0, `style` = '' WHERE name = '{$닉_esc}'");
      }
      $정보['item'] = '';
      $정보['무기타입'] = 0;
      $정보['enhance'] = 0;
      if (function_exists('강화_이력_기록')) {
        $강화주체닉 = trim((string)($정보['name'] ?? $두자리닉넴));
        강화_이력_기록([
          'nick'             => $강화주체닉,
          'channel'          => 'chat',
          'item'             => $현재아이템,
          'style'            => $스타일문구,
          'enhance_before'   => $현재강화,
          'enhance_after'    => 0,
          'result'           => 'fail_break',
          'cost'             => $강화비용,
          'dice'             => $확률숫자,
          'dice_num'         => $성공분자,
          'dice_den'         => $성공분모,
          'rate'             => $성공확률_문구,
          'eunchong'         => $은총활성,
          'challenge_mode'   => (bool)$도전모드,
          'challenge_target' => $도전모드 ? ($도전모드['보유자닉'] ?? '') : '',
        ]);
      }
      $회차메시지 = $자숙위반안내."💥 강화 실패! {$두자리닉넴} {$스타일문구} {$현재아이템} +{$현재강화} 파손…{$도전안내}{$보상안내}\n(성공확률 {$성공확률_문구} · {$비용표시} 차감)";
      $연속결과[] = ($연속횟수 > 1) ? "━━ {$회차}/{$연속횟수} ━━\n{$회차메시지}" : $회차메시지;
      $마지막회차메시지 = $회차메시지;
      break;
    }

    if ($연속횟수 > 1) {
      if (empty($연속결과)) {
        echo 전송("❌ 강화를 진행하지 못했어요.");
        exit;
      }
      $msg = "📋 연속 강화 요약  성공 {$연속성공} · 수호소모 {$연속수호} (누적 " . (function_exists('강화비용_표시') ? 강화비용_표시($누적비용, $단위) : (number_format((float)$누적비용) . $단위)) . ")\n\n";
      $msg .= implode("\n\n", $연속결과);
      echo 전송($msg);
      exit;
    }

    if ($마지막회차메시지 !== '') {
      echo 전송($마지막회차메시지);
    } else {
      echo 전송("❌ 강화를 진행하지 못했어요.");
    }
    exit;


    
  }else if(strpos(trim($status), '.무기복구') === 0){
    $입력문 = trim($status);
    if ($입력문 === '.무기복구 안내' || $입력문 === '.무기복구 비용') {
      echo 전송(무기복구_안내문구($두자리닉넴));
      exit;
    }
    if ($입력문 !== '.무기복구') {
      echo 전송("❌ 사용법: `.무기복구` · `.무기복구 안내`\n예) .무기복구");
      exit;
    }
    $복구결과 = 무기복구_실행($두자리닉넴, $단위);
    echo 전송($복구결과['msg']);
    exit;

  }else if(strpos(trim($status), '.무기교체') === 0){
    // .무기교체: 설명 출력 or 실제 무기 교체
    $입력문 = trim($status);
    // 설명만 요청한 경우 (.무기교체 단독 입력)
    if ($입력문 === '.무기교체') {
      echo 전송(
        "🔁 무기교체 안내\n\n".
        "명령어: .무기교체 (활|단소|마법)\n".
        "조건: 현재 무기가 +18강 이상일 때 가능\n".
        "비용: 실패 시에만 스냅샷 게임냥 20% 차감\n\n".
        "📌 확률 및 결과\n".
        "· 성공 30%: 선택한 무기로 교체, 현재 강화 유지, 냥 차감 없음\n".
        "· 실패 35%: 무기/강화 유지, 스냅샷 게임냥 20% 차감\n".
        "· 실패 35%: 무기 -1강(현재 강화 -1), 스냅샷 게임냥 20% 차감\n\n".
        "예시) .무기교체 활"
      );
      exit;
    }
    // .무기교체 활/단소/마법 — 18강 이상 가능 / 성공 시 냥 차감 없음, 실패 시 스냅샷 게임냥 20%
    $무기맵 = array('단소'=>'🪈단소', '활'=>'🏹활', '마법'=>'🪄마법');
    if (!preg_match('/^\.무기교체\s+(활|단소|마법)\s*$/u', $입력문, $m)) {
      echo 전송("❌ 사용법: .무기교체 (활|단소|마법)\n예) .무기교체 활");
      exit;
    }
    $목표무기명 = $m[1];
    $목표아이템 = $무기맵[$목표무기명];
    $닉_esc = addslashes($두자리닉넴);
    $현재아이템 = trim($정보['item'] ?? '');
    $현재강화 = (int)($정보['enhance'] ?? 0);

    if ($현재아이템 === '') {
      echo 전송("❌ 보유한 무기가 없어요. .강화 로 먼저 구매해주세요.");
      exit;
    }
    if ($현재강화 < 18) {
      echo 전송("❌ 무기교체는 18강부터 가능해요. (현재 +{$현재강화}강)");
      exit;
    }
    if (trim($현재아이템) === trim($목표아이템)) {
      echo 전송("❌ 이미 {$목표무기명}을(를) 장착 중이에요.");
      exit;
    }
    // 실패 시: 시세 스냅샷 게임냥의 20%
    if (function_exists('시세기준_스냅샷_로드') && function_exists('냥_비율내림')) {
      $snap = 시세기준_스냅샷_로드();
      $실패시차감_sql = 냥_정수문자열(냥_비율내림($snap['게임냥'] ?? 0, 0.20));
    } elseif (function_exists('전체냥기준금액')) {
      $실패시차감_sql = 냥_정수문자열(전체냥기준금액(20));
    } else {
      $실패시차감_sql = '1';
    }
    if ($실패시차감_sql === '' || $실패시차감_sql === '0') {
      $실패시차감_sql = '1';
    }
    $실패시차감_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($실패시차감_sql) : preg_replace('/\D/', '', (string)$실패시차감_sql);
    $실패시차감_표시 = function_exists('냥축약표시')
      ? 냥축약표시($실패시차감_sql)
      : $실패시차감_sql;

    // 성공 30% / 실패 70%: 현재 강화 유지 or -1강 — 실패 시 스냅샷 게임냥 20% 차감
    $판정 = rand(1, 100);
    if ($판정 <= 30) {
      // 성공: 선택한 무기로 교체, 현재 강화 유지, 냥 차감 없음 (성공 확률 30%)
      if (function_exists('무기_타입_키에서') && function_exists('무기_장착_갱신')) {
        $교체타입 = 무기_타입_키에서($목표무기명);
        무기_장착_갱신($두자리닉넴, $교체타입, $현재강화);
        $목표아이템 = function_exists('무기_표시아이템') ? 무기_표시아이템($교체타입, $현재강화) : $목표아이템;
      } else {
        $목표_esc = addslashes($목표아이템);
        db_query("UPDATE tb_member SET `item` = '{$목표_esc}' WHERE name = '{$닉_esc}'");
      }
      echo 전송("✅ 무기교체 성공! (성공 확률 30%)\n🎲 판정값: {$판정} / 100\n{$두자리닉넴} {$현재아이템} → {$목표아이템} +{$현재강화} 유지\n(냥 차감 없음)");
    } elseif ($판정 <= 65) {
      // 실패: 무기·강화 유지, 스냅샷 20% 차감 (이 실패 결과 확률 35%)
      db_query("UPDATE tb_member SET point = point - {$실패시차감_sql} WHERE name = '{$닉_esc}'");
      echo 전송("❌ 무기교체 실패! (실패 확률 35%)\n🎲 판정값: {$판정} / 100\n{$두자리닉넴} {$현재아이템} +{$현재강화} 유지\n{$실패시차감_표시} 차감. (스냅샷 게임냥 20%)");
    } else {
      // 실패: 무기 -1강 + 스냅샷 20% 차감 (이 실패 결과 확률 35%)
      $감소강화 = max(0, $현재강화 - 1);
      db_query("UPDATE tb_member SET point = point - {$실패시차감_sql}, `enhance` = {$감소강화} WHERE name = '{$닉_esc}'");
      if (function_exists('무기_강화후_표시동기화')) {
        무기_강화후_표시동기화($두자리닉넴, $정보 ?? $현재아이템, $감소강화);
      }
      echo 전송("❌ 무기교체 실패! (실패 확률 35%)\n🎲 판정값: {$판정} / 100\n{$두자리닉넴} {$현재아이템} +{$현재강화} → +{$감소강화} 강화 감소\n{$실패시차감_표시} 차감. (스냅샷 게임냥 20%)");
    }
    exit;

  }else if (strpos($status, '.지호') !== false) {
    // 지호 보유는 tb_member_item_bag 기준. 1개 = 1시간
    $사용개수 = 1;
    if (!preg_match('/^\.지호(?:\s+(\d+))?\s*$/u', trim($status), $지호매치)) {
      echo 전송("❌ 사용법: .지호 [개수]\n예) .지호 · .지호 5\n지호 1개 = 1시간");
      exit;
    }
    if (isset($지호매치[1]) && $지호매치[1] !== '') {
      $사용개수 = (int)$지호매치[1];
    }
    if ($사용개수 < 1) {
      echo 전송("❌ 사용 개수는 1개 이상만 가능합니다.\n예) .지호 · .지호 5");
      exit;
    }
    $전체메시지행 = db_select("SELECT COUNT(*) AS cnt FROM tb_msg");
    $전체행수 = (int)($전체메시지행['cnt'] ?? 0);
    if ($전체행수 < 100) {
      echo 전송("❌ 누적 타수 100 미만에서는 지호를 사용할 수 없습니다. (현재 {$전체행수}타)");
      exit;
    }

    $현재보유개수 = function_exists('item_bag_qty_nick')
      ? item_bag_qty_nick($두자리닉넴, '지호')
      : 0;
    if ($현재보유개수 < $사용개수) {
      echo 전송("지호 아이템 부족! (요청 {$사용개수}개 · 보유 {$현재보유개수}개)");
      exit;
    }

    if (!function_exists('item_bag_sub_nick')) {
      echo 전송("❌ 아이템 가방 기능을 불러올 수 없어요.");
      exit;
    }
    $차감 = item_bag_sub_nick($두자리닉넴, '지호', $사용개수);
    if (empty($차감['ok'])) {
      echo 전송("지호 아이템 없음!");
      exit;
    }
    아이템사용_시세하락('지호', $사용개수);

    $추가시간 = (int)$사용개수;
    $닉_esc = addslashes($두자리닉넴);
    $사용중여부 = db_select("select * from tb_item_use where nickname = '{$닉_esc}' and item = '지호' ");
    if(!empty($사용중여부['idx'])){
      $유효시간 = date("Y-m-d H:i", strtotime($사용중여부['enddate']." +{$추가시간} hours"));
      $sql = "update tb_item_use set enddate = '{$유효시간}' where idx = {$사용중여부['idx']} ";
      db_query($sql);
      $연장시간 = date("m-d H:i", strtotime($유효시간));
      $msg = "►{$두자리닉넴} 지호 {$사용개수}개 적용\n{$연장시간} 까지";
      echo 전송($msg);
      exit;
    }else{
      $유효시간 = date("Y-m-d H:i", strtotime("+{$추가시간} hours"));
      $sql = "insert into tb_item_use set nickname = '{$닉_esc}', item = '지호', enddate = '{$유효시간}', regdate = now() ";
      db_query($sql);
      $연장시간 = date("m-d H:i", strtotime($유효시간));
      $msg = "►{$두자리닉넴} 지호 {$사용개수}개 사용\n{$연장시간} 까지 ";
      echo 전송($msg);
      exit;
    }

  }else if(strpos($status, '.궁금') !== false){
    echo 전송("❌ `.궁금`은 본방에서만 이용할 수 있어요.");
    exit;
    }else if(trim($status) === '.내무기'){
      require_once __DIR__ . '/item/myweapon_chat.inc.php';
      내무기_채팅명령_처리($status, $두자리닉넴, $정보);
      exit;

    }else if (preg_match('/^\.(주가|주식|매수|매도)(\s|$)/u', trim($status))) {
      if (!function_exists('아이템주식_채팅명령_시도')) {
        require_once __DIR__ . '/item_stock_market.inc.php';
      }
      if (아이템주식_채팅명령_시도($status, $두자리닉넴, $정보, $단위)) {
        exit;
      }

    }else if(strpos($status, '.판매') !== false){
      if (function_exists('아이템_구매판매_채팅_차단중') && 아이템_구매판매_채팅_차단중()) {
        echo 전송(아이템_구매판매_채팅_차단_메시지());
        exit;
      }
      $아이템명 = "";
      if (preg_match('/\.판매\s*(.+)/u', $status, $match)) {
          $after = trim($match[1]); // "수호 5" / "1주년기념주화 1" / "수호"
          if (preg_match('/^(.+?)\s+(\d+)$/u', $after, $parts)) {
              $아이템명 = trim($parts[1]);
              $수량 = max(1, (int)$parts[2]);
          } else {
              $아이템명 = $after;
              $수량 = 1;
          }
          if ($아이템명 !== '') {
              $보유개수 = function_exists('item_bag_qty') ? item_bag_qty((int)$정보['idx'], $아이템명) : 0;
              if ($수량 > $보유개수) {
                echo 전송("{$아이템명} 보유수량 {$보유개수}개");
                exit;
              }else{
                $msg1 = "✅ 아이템 판매\n\n";
                $item = db_select("select * from tb_item where sname = '{$아이템명}' ");
                if (empty($item['idx'])) {
                    echo 전송("❌ [ {$아이템명} ] 존재하지 않는 아이템이에요.");
                    exit;
                }
  
                $사용중여부 = db_select("select idx from tb_item_use where nickname = '{$두자리닉넴}' and item = '지호' ");
                if($사용중여부['idx']){ // 지호2 버프 사용시 수수료 15%
                  $판매수수료율 = 15;
                }else{
                  $판매수수료율 = $일반판매수수료율;
                }
  
                $userNyung = $정보['point'];
                // 개인 배율 계산
                $personalMultiplier = 가격계산($userNyung);
                $totalNyung = $전체포인트['total_point'];
                // 기준 냥 (이 값 기준으로 시장 배율 1.0)
                $baseMarketNyung = 10000000; // 1천만 기준
                // 시장 배율 계산 (완만한 로그 곡선)
                $marketMultiplier = log10(($totalNyung / $baseMarketNyung) + 1) * 0.1 + 1;
  
                // 시장 배율 안전장치
                $marketMultiplier = max(0.9, min($marketMultiplier, 1.2));
  
                // 수량 반영한 기본 가격 (판매가는 tb_item.sell 기준)
                $basePrice = (int)($item['sell'] ?? 0) * $수량;
    
                if (!empty($item['status'])) {
                    // 고정 가격 아이템
                    $finalPrice = $basePrice;
                } else {
                    // 최종 가격 = 기본가 × 개인 배율 × 시장 배율
                    $finalPrice = round($basePrice * $marketMultiplier * (1 + ($personalMultiplier - 1) * 0.5));
  
                    // 안전장치 (최소 80% ~ 최대 150%)
                    $minPrice = $basePrice * 0.8;
                    $maxPrice = $basePrice * 1.5;
                    $finalPrice = max($minPrice, min($finalPrice, $maxPrice));
                }
  
                // 🔥 백 단위 올림 (뒤 두 자리 전부 올림)
                $판매금액 = ceil($finalPrice / 100) * 100;
  
                // 수수료 금액 (판매가 * 30% 반올림)
                $수수료 = round($판매금액 * ($판매수수료율 / 100));
                $수수료뺀금액 = $판매금액 - $수수료;
  
                // 1) 가방 수량 차감 (냥 지급 전)
                $bagSub = item_bag_sub((int)$정보['idx'], (string)$정보['name'], $아이템명, $수량);
                if (empty($bagSub['ok'])) {
                    echo 전송($bagSub['msg'] ?? '❌ 판매 처리 중 오류 (보유 수량 불일치). 다시 시도해주세요.');
                    exit;
                }
  
                // 2) 냥 지급 (type=newpoint → newpoint, 그 외 point)
                $지급컬럼 = 아이템판매_지급컬럼($item);
                $sql = "UPDATE tb_member SET {$지급컬럼} = {$지급컬럼} + {$수수료뺀금액} WHERE name = '{$정보['name']}'";
                db_query($sql);
                $보유 = db_select("SELECT {$지급컬럼} FROM tb_member WHERE name = '{$정보['name']}' LIMIT 1");

                $msg1.= "판매자 : {$호칭} {$정보['name']}\n";
                $msg1.= "판매 아이템 : {$아이템명}\n";
                $msg1.= "판매가 : ".아이템판매_금액표시($수수료뺀금액, $지급컬럼)."{$단위}\n";
                $msg1.= "수수료 : ".아이템판매_금액표시($수수료, $지급컬럼)."{$단위}\n";
                $msg1.= "보유{$단위} : ".아이템판매_금액표시($보유[$지급컬럼] ?? 0, $지급컬럼)."{$단위}";
                db_query("UPDATE config SET tax = tax + {$수수료} ");
                지급로그('판매', $두자리닉넴, $아이템명, $수수료, $수수료뺀금액);
                if (function_exists('item_trade_log_판매')) {
                  item_trade_log_판매($두자리닉넴, $아이템명, $수량, $판매금액, $수수료, $수수료뺀금액, (int)$정보['idx'], 'chat', 'info2', $지급컬럼);
                }
                if (function_exists('아이템판매_시세하락')) {
                  아이템판매_시세하락($아이템명, (int)($item['buy'] ?? 0), $수량);
                }
                echo 전송($msg1);
                exit;
              }
          }
      }
  
      $시세 = 아이템_상점판매_시세목록($정보);
      $msg = "✅ 아이템 판매\n\n";
      foreach ($시세['items'] as $row) {
        $msg .= "{$row['name']} : {$row['price_fmt']}{$row['unit']}<br>";
      }
      $msg.= "\n판매 수수료 {$일반판매수수료율}%";
      $msg.= "\n지호 2 적용시 15%";

      echo 전송($msg);
      exit;    

    }else if(strpos(trim($status), '.시전') === 0 || strpos(trim($status), '시전!') === 0){
      require_once __DIR__ . '/item/cast_chat.inc.php';
      시전_채팅명령_처리($status, $두자리닉넴, $정보);
      exit;

     }else if(preg_match('/^[.．]\s*보호(?:\s|$)/u', trim($status))){
      require_once __DIR__ . '/item/protect_chat.inc.php';
      try {
        보호_채팅명령_처리($status, $두자리닉넴, is_array($정보 ?? null) ? $정보 : []);
      } catch (Throwable $e) {
        echo 전송('❌ `.보호` 처리 중 오류가 났어요. 잠시 후 다시 시도해주세요.');
      }
      exit;

    }else if(strpos(trim($status), '.수리') === 0){
      require_once __DIR__ . '/item/repair_chat.inc.php';
      무기수리_채팅명령_처리($status, $두자리닉넴, $정보);
      exit;

    }else if(strpos(trim($status), '.메가은총') === 0 || (strpos(trim($status), '.은총') === 0 && strpos(trim($status), '.은총교환') !== 0)){
      $입력 = trim($status);
      // 관리자 지급/회수: .은총 닉 [개수] · .은총 전체 [개수]
      if (strpos($입력, '.메가은총') !== 0 && function_exists('관리자_은총지급_명령처리')) {
        관리자_은총지급_명령처리($입력, $두자리닉넴);
      }
      $메가사용 = (strpos($입력, '.메가은총') === 0);
      if ($메가사용) {
        if ($입력 !== '.메가은총') {
          echo 전송("❌ 사용법: .메가은총");
          exit;
        }
        $tier = 2;
      } else {
        if ($입력 !== '.은총') {
          echo 전송("❌ 사용법: .은총  또는  .메가은총\n(지급: .은총 닉 [개수] / .은총 전체 [개수])\n(조각교환: .은총교환 [개수])");
          exit;
        }
        $tier = 1;
      }
      $현재무기 = trim($정보['item'] ?? '');
      $현재강화 = (int)($정보['enhance'] ?? 0);
      if (!function_exists('강화_은총_사용')) {
        echo 전송("❌ 은총 기능을 불러올 수 없어요.");
        exit;
      }
      $결과 = 강화_은총_사용($두자리닉넴, $tier, $현재강화, $현재무기);
      echo 전송(($결과['ok'] ?? false) ? ($결과['data'] ?? '적용 완료') : ($결과['data'] ?? '실패'));
      exit;


  }else if(strpos($status, 'ㄱㄱ') !== false){
    $msg = "";
    $hour = date('G'); // 현재 시간 (0~23)

    게임제한_차단($두자리닉넴);

   // 오늘 출석한 친구만 .강화 사용 가능
   $오늘출석일 = (string)($정보['last_att_date'] ?? '');
   if ($오늘출석일 !== (string)$오늘) {
     echo 전송("❌ `.맞다이` 는 오늘 출석 완료 후에 이용 가능해요!\n먼저 출석부터 챙겨주세요 🙌");
     exit;
   }

    // 7시 이후부터 자정 전까지만 타수 제한 적용
    if($정보['couple']!=2){
      if($오늘타수['cnt'] < $타수제한){
          echo 전송("일 타수 {$타수제한} 이상 맞다이 가능\n{$두자리닉넴}의 현재 타수 : {$오늘타수['cnt']}타");
          exit;
      }
    }

    // 뒤에 붙은 글자(예: 명륜진사갈비)는 무시하고 ㄱㄱ 숫자 [금액] 부분만 처리
    // 금액: 숫자 또는 만·억·조·경 축약 (예: 1경, 5조)
    if (preg_match('/^ㄱㄱ\s+(\d+)(?:\s+([^\s]+))?/u', trim($status), $match)) {

        $번호 = (int)$match[1];

        // 🔹 먼저 걸린 배팅 확인
        $먼저건것 = db_select("SELECT * FROM tb_battle WHERE status = 0 LIMIT 1");

        if ($먼저건것 && $먼저건것['nyang1'] > 0) {

            $기존내기 = (int)$먼저건것['nyang1'];

            // 금액을 입력했을 경우
            if (isset($match[2]) && $match[2] !== '') {
                $파싱 = function_exists('맞다이_금액파싱')
                  ? 맞다이_금액파싱($match[2])
                  : ['ok' => true, 'amount' => (function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($match[2]) : (int)$match[2]), 'bare' => false];
                $입력내기 = (int)($파싱['amount'] ?? 0);
                if (empty($파싱['ok']) || $입력내기 <= 0) {
                    echo 전송("❌ 금액 형식을 확인해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                    exit;
                }

                // ❌ 기존보다 작으면 차단
                if ($입력내기 < $기존내기) {
                    $기존표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($기존내기) : (number_format($기존내기) . '냥');
                    echo 전송("❌ {$기존표}이 걸려있습니다.");
                    exit;
                }

                // 같거나 크면 입력값 사용
                $내기냥 = $입력내기;

            } else {
                // 🔥 금액 미입력 → 기존 금액 자동 사용
                $내기냥 = $기존내기;
            }

        } else {
            // 아직 아무도 안 걸었을 때
            if (!isset($match[2]) || $match[2] === '') {
                echo 전송("❌ 내기 금액을 입력해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                exit;
            }

            $파싱 = function_exists('맞다이_금액파싱')
              ? 맞다이_금액파싱($match[2])
              : ['ok' => true, 'amount' => (function_exists('냥_금액_파싱') ? (int)냥_금액_파싱($match[2]) : (int)$match[2]), 'bare' => false];
            $내기냥 = (int)($파싱['amount'] ?? 0);
            $금액숫자만 = !empty($파싱['bare']);
            if (empty($파싱['ok']) || $내기냥 <= 0) {
                echo 전송("❌ 금액 형식을 확인해주세요.\n예) ㄱㄱ 4 1000 (1000냥) / ㄱㄱ 4 1경");
                exit;
            }
        }

        // 여기부터 $번호, $내기냥 사용

        // if ($정보['level'] >= 0) {
        //     $level = (int)$정보['level'];
        //     $금액  = (int)$내기냥; // 비교할 금액 변수

        //     $최대금액 = 0;
        //     if ($level <= 10) {
        //         $최대금액 = 2000000;
        //     } else if ($level <= 20) {
        //         $최대금액 = 20000000;
        //     } else if ($level <= 30) {
        //         $최대금액 = 50000000;
        //     } else if ($level <= 40) {
        //         $최대금액 = 100000000;
        //     } else {
        //         $최대금액 = 0; // 제한 없음
        //     }

        //     // 🔒 제한 체크
        //     if ($최대금액 > 0 && $금액 > $최대금액) {
        //         echo 전송("❌ 레벨 {$level}에서는 최대 " . number_format($최대금액) . "냥까지만 가능합니다.");
        //         exit;
        //     }
        // }


        if ($번호 < 1 || $번호 >= 10) {
          echo 전송("최소 1 ~ 10 숫자를 입력해줘!");
          exit;
        }

        if($내기냥 < $깽값){
          $min표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($깽값) : (number_format((int)$깽값) . $단위);
          $tip = (!empty($금액숫자만) || (isset($match[2]) && preg_match('/^\d+$/u', str_replace([',',' '], '', (string)$match[2]))))
            ? "\n(숫자만 쓰면 냥 단위예요. {$match[2]} → " . (function_exists('맞다이_금액표시') ? 맞다이_금액표시($내기냥) : ($내기냥 . '냥')) . " / 경은 `1경`처럼 단위를 붙여주세요)"
            : '';
          echo 전송("🥊맞다이 {$min표} 이상 입력해줘!{$tip}");
          exit;
        }

        if($정보['point'] < $내기냥){
          $보유표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($정보['point']) : ($정보['point'] . $단위);
          $msg = "✅ 맞다이 {$두자리닉넴} 깽값 부족...!\n";
          $msg.= "\n깽값 준비해와..!\n현재 보유 {$보유표}";
          echo 전송($msg);
          exit;
        }

        $자숙위반 = 자숙_맞다이위반_적용($두자리닉넴, $단위);
        $자숙위반안내 = (string)($자숙위반['notice'] ?? '');
        $갱신정보 = db_select("SELECT point FROM tb_member WHERE name = '{$두자리닉넴}' LIMIT 1");
        if ((int)($갱신정보['point'] ?? 0) < $내기냥) {
          $보유표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($갱신정보['point'] ?? 0) : (number_format((int)($갱신정보['point'] ?? 0)) . $단위);
          echo 전송($자숙위반안내 . "✅ 맞다이 {$두자리닉넴} 깽값 부족...!\n\n깽값 준비해와..!\n현재 보유 {$보유표}");
          exit;
        }

        $체크 = db_select("select * from tb_battle where status = 0 and nick1 = '{$두자리닉넴}' "); //내가 신청한 상태라면
        if($체크['idx'] > 0){
          $대기표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($체크['nyang1']) : (number_format($체크['nyang1']) . '냥');
          $msg = "🥊{$체크['nick1']} 대기중 {$대기표}";
          echo 전송($msg);
          exit;
        }


        $선신청 = db_select("select * from tb_battle where status = 0 order by regdate limit 1 "); //먼저 신청한 상대의 배팅액확인
        if($내기냥 < $선신청['nyang1']){
          $선표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($선신청['nyang1']) : ($선신청['nyang1'] . $단위);
          echo 전송("맞다이 뜨고싶으면 {$선표} 준비해와..");
          exit;
        }

        // if($선신청['idx']){
        //   $신청한금액에열배이상 = $선신청['nyang1'] * 10;
        //   if($신청한금액에열배이상 < $내기냥){
        //     echo 전송("최대 깽값은 {$신청한금액에열배이상}냥 이야! 깽값을 수정해줘!");
        //     exit;
        //   }
        // }


        $결과 = db_select("select * from tb_battle where status = 1 order by regdate limit 1");
        if($결과['idx'] > 0){
          $msg = "✅ {$결과['nick1']} vs {$결과['nick2']} 맞짱! 누가이길까? .결투";
          echo 전송($msg);
          exit;
        }

        $숫자1 = rand(0, 9);
        $진행중 = db_select("select * from tb_battle where status = 0 order by regdate desc limit 1 ");
        if(!$진행중['nick1']){
          $금액표시 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($내기냥) : (function_exists('냥축약표시') ? 냥축약표시($내기냥) : (number_format($내기냥) . '냥'));
          $msg = $자숙위반안내."🥊{$금액표시} 맞다이 뜰사람?";
          $sql = "insert into tb_battle set status = 0, nick1 = '{$두자리닉넴}', number1 = {$번호}, nyang1 = {$내기냥}, addnum1 = {$숫자1}, regdate = now() ";
          $result = db_query($sql);
          db_query("update tb_member set point = point - {$내기냥} where name = '{$두자리닉넴}' ");
          echo 전송($msg);
          exit;
        }

        if($진행중['nick1']!=""){
          if(!$진행중['nick2']){
            $대결 = db_select("select * from tb_battle where status = 0 order by regdate desc limit 1 ");
            $sql = "update tb_battle set status = 1, nick2 = '{$두자리닉넴}', number2 = {$번호}, nyang2 = {$내기냥}, addnum2 = {$숫자1} where idx = {$대결['idx']} ";
            $result = db_query($sql);
            db_query("update tb_member set point = point - {$내기냥} where name = '{$두자리닉넴}' ");

            $진행중 = db_select("select * from tb_battle where status = 1 order by regdate desc limit 1 ");
            if($진행중['idx']){

            $최종값1 = ($진행중['number1'] + $진행중['addnum1']) % 10;
            $최종값2 = ($진행중['number2'] + $진행중['addnum2']) % 10;


              if($최종값1 > $최종값2){ //1이 이김
                $승리닉 = $진행중['nick1'];
                $패배닉 = $진행중['nick2'];
                $승리금액 = $진행중['nyang2'];
                $status = 2;
                지급로그('맞짱-패배', $진행중['nick2'], '', 0, $진행중['nyang2']);
              }
              if($최종값2 > $최종값1){ //2가 이김
                $승리닉 = $진행중['nick2'];
                $패배닉 = $진행중['nick1'];
                $승리금액 = $진행중['nyang1'];
                $status = 2;
                지급로그('맞짱-패배', $진행중['nick1'], '', 0, $진행중['nyang1']);
              }
              $최종지급 = $진행중['nyang1'] + $진행중['nyang2']; //처음에 걸어논 돈도 줘야함

              if($최종값2 == $최종값1){
                $승리닉 = $진행중['nick2'];
                $status = 3;
              }

              if($status==2){
                db_query("update tb_battle set status = 2, win = '{$승리닉}' where idx = {$진행중['idx']} ");
                $승리표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($승리금액, $단위) : (number_format((float)$승리금액) . $단위);
                $msg = "🥊{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
    {$승리닉} {$승리표} 획득~🎉";
                $sql = "update tb_member set point = point + {$최종지급} where name = '{$승리닉}' ";
                db_query($sql);
                지급로그('맞짱-승리', $승리닉, $패배닉, 0, $승리금액);

              }else{ // 각 내분의 50% 금고 · 50% 환급
                $냥1 = (int)$진행중['nyang1'];
                $냥2 = (int)$진행중['nyang2'];
                $금고1 = (int)floor($냥1 * 50 / 100);
                $금고2 = (int)floor($냥2 * 50 / 100);
                $환급1 = $냥1 - $금고1;
                $환급2 = $냥2 - $금고2;
                $총금고 = $금고1 + $금고2;
                $환급1표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($환급1, $단위) : (number_format($환급1) . $단위);
                $환급2표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($환급2, $단위) : (number_format($환급2) . $단위);
                $금고1표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($금고1, $단위) : (number_format($금고1) . $단위);
                $금고2표 = function_exists('맞다이_금액표시') ? 맞다이_금액표시($금고2, $단위) : (number_format($금고2) . $단위);

                $msg = "😱{$진행중['nick1']}({$진행중['addnum1']}) {$최종값1} vs {$진행중['nick2']}({$진행중['addnum2']}) {$최종값2}
🤝 무승부!
{$진행중['nick1']} +{$환급1표} ({$금고1표} 금고)
{$진행중['nick2']} +{$환급2표} ({$금고2표} 금고)";

                if ($환급1 > 0) {
                  db_query("update tb_member set point = point + {$환급1} where name = '{$진행중['nick1']}' ");
                }
                if ($환급2 > 0) {
                  db_query("update tb_member set point = point + {$환급2} where name = '{$진행중['nick2']}' ");
                }
                if ($총금고 > 0) {
                  db_query("UPDATE config SET tax = tax + {$총금고} ");
                }

                지급로그('맞짱-무승부', $진행중['nick1'], '', $금고1, $환급1);
                지급로그('맞짱-무승부', $진행중['nick2'], '', $금고2, $환급2);

                db_query("update tb_battle set status = 2, win = '무승부' where idx = {$진행중['idx']} ");
              }

            }else{
              $msg = "진행중인 맞짱 없음!";
            }
          }
        }
        if (!empty($자숙위반안내) && isset($msg) && $msg !== '') {
          $msg = $자숙위반안내 . $msg;
        }
        echo 전송($msg);
        exit;
    }

    // $sql = "
    // SELECT *
    // FROM tb_point_log
    // WHERE nick = '{$두자리닉넴}'
    // AND DATE_FORMAT(regdate, '%Y-%m-%d') = '{$오늘}'
    // AND (status = '맞짱-승리' || status = '맞짱-패배' || status = '맞짱-무승부' || status = '맞짱-수수료')
    // ORDER BY regdate DESC limit 15";
    // //LIMIT 15
    // $result = db_query($sql);
    //
    // $msg = "✅ 맞짱 결과\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";
    // $msg .= "";
    // while ($row = db_fetch($result)) {
    //     $msg .= $row['status']." ".$row['receiver']." ".$row['point']."{$단위} ".date("H:i", strtotime($row['regdate']))."\n";
    // }
    // echo 전송($msg);
    // exit;

  }else if(
    strpos($status, '.야바위방법') !== false
    || strpos($status, 'ㄷㄹ') !== false
    || strpos($status, '.마감') !== false
    || strpos($status, '.신청') !== false
    || strpos($status, 'ㅅㅊ') !== false
  ){
    $YABAWI_ROOM_LABEL = '홍보방';
    require_once __DIR__ . '/game/yabawi_chat.inc.php';

  }else if (strpos($status, '.구매') !== false) {
      // 홍보방: .구매 → 아이템 주식 시세/매수 (.주가/.매수 와 동일)
      if (!function_exists('아이템주식_채팅명령_시도')) {
        require_once __DIR__ . '/item_stock_market.inc.php';
      }
      if (아이템주식_채팅명령_시도($status, $두자리닉넴, $정보, $단위)) {
        exit;
      }
      // 시세표(.구매)는 허용 · 아이템 구매 실행만 차단 (은총·구상점)
      if (function_exists('아이템_구매판매_채팅_차단중') && 아이템_구매판매_채팅_차단중()
        && function_exists('아이템_구매실행_요청인가') && 아이템_구매실행_요청인가($status)) {
        echo 전송(아이템_구매실행_차단_메시지());
        exit;
      }
      if (function_exists('구매_사용완료아이템_삭제')) {
        구매_사용완료아이템_삭제();
      }
      $구매_trim = trim($status);

      // .구매 아이템 → 단가만 (수량 없으면 구매 안 함)
      if (preg_match('/^\.구매\s+([^\s]+)\s*$/u', $구매_trim, $m구매시세)) {
        $시세명 = trim($m구매시세[1]);
        $시세_esc = addslashes($시세명);
        if (in_array($시세명, ['은총', '은총1', '은총2'], true) && function_exists('info3_은총2_구매단가')) {
          $본냥 = ($시세명 === '은총1');
          $단가 = $본냥
            ? (function_exists('info3_은총1_구매단가') ? info3_은총1_구매단가() : '0')
            : info3_은총2_구매단가();
          $표시명 = $본냥 ? '은총1' : '은총';
          $단위라벨 = $본냥 ? '본냥' : '겜냥';
          $단가표시 = function_exists('구매가_축약표시') ? 구매가_축약표시($단가, $단위라벨) : ((string)$단가 . $단위라벨);
          echo 전송("🛒 【{$표시명}】\n매수  {$단가표시}\n\n구매하려면: .구매 {$표시명} 1");
          exit;
        }
        $시세행 = db_select("
          SELECT idx, sname, buy, percent, buystatus
          FROM tb_item
          WHERE sname = '{$시세_esc}' AND buystatus = 0
          LIMIT 1
        ");
        if (empty($시세행['idx'])) {
          echo 전송("❌ 구매할 수 없거나 없는 아이템이에요.\n시세표: .구매");
          exit;
        }
        if ($시세명 === '공커대실권') {
          $커플 = db_select("select * from tb_couple where couple like '%" . addslashes($두자리닉넴) . "%'");
          $구매단가 = (string)(int)(ceil(((int)($커플['amount'] ?? 0)) / 1000) * 1000);
        } elseif (function_exists('아이템_구매시세_단가')) {
          $구매단가 = 아이템_구매시세_단가($시세명, $시세행);
        } else {
          $구매단가 = (string)($시세행['buy'] ?? '0');
        }
        $단가표시 = function_exists('구매가_축약표시')
          ? 구매가_축약표시($구매단가, $단위)
          : ((string)$구매단가 . $단위);
        echo 전송("🛒 【{$시세명}】\n매수  {$단가표시}\n\n구매하려면: .구매 {$시세명} 1");
        exit;
      }

      // .구매 아이템 수량 — 수량 필수
      if (!preg_match('/^\.구매\s+([^\s]+)\s+(\d+)\s*$/u', $구매_trim, $m구매)) {
        echo 전송("❌ 사용법:\n.구매 아이템이름 (시세)\n.구매 아이템이름 수량 (구매)\n예) .구매 강일\n예) .구매 강일 1");
        exit;
      }
      $구매아이템명 = trim($m구매[1]);
      $구매수량 = (int)$m구매[2];
      if ($구매수량 < 1) {
        $구매수량 = 1;
      }

      // 은총1(본냥) / 은총2(겜냥)
      $은총구매 = info3_은총구매_실행($두자리닉넴, $정보, $구매아이템명, $구매수량);
      if ($은총구매 !== null) {
        echo 전송($은총구매['msg'] ?? '❌ 구매 처리 실패');
        exit;
      }
    
      $구매아이템_esc = addslashes($구매아이템명);
      $닉_esc = addslashes($두자리닉넴);
    
      $구매아이템행 = db_select("
        SELECT idx, sname, buy, percent, buystatus
        FROM tb_item
        WHERE sname = '{$구매아이템_esc}' AND buystatus = 0
        LIMIT 1
      ");
      if (empty($구매아이템행['idx'])) {
        echo 전송("❌ 구매할 수 없거나 없는 아이템이에요.");
        exit;
      }
    
      $커플 = null;
      if ($구매아이템명 === '공커대실권') {
        $커플 = db_select("select * from tb_couple where couple like '%{$두자리닉넴}%'");
        if (empty($커플['idx'])) {
          echo 전송("❌ 정보가 없어 공커대실권을 구매할 수 없습니다.");
          exit;
        }
        $커플_amount = (int)($커플['amount'] ?? 0);
        if ($커플_amount < 1) {
          echo 전송("❌ 가격이 설정되지 않아 공커대실권을 구매할 수 없습니다.");
          exit;
        }
      }
    
      if ($구매아이템명 === '공커대실권') {
        $구매단가 = (int)(ceil($커플_amount / 1000) * 1000);
        $총구매액 = $구매단가 * $구매수량;
      } elseif ($구매아이템명 === '일방신청권') {
        $구매단가 = 전체냥기준금액(0.01, true);
        $총구매액 = $구매단가 * $구매수량;
      } elseif ($구매아이템명 === '일방연장권') {
        $구매단가 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
        $총구매액 = $구매단가 * $구매수량;
      } else {
        $구매단가 = 아이템_구매단가_계산($구매아이템행);
        if ($구매단가 <= 0 && 아이템_percent_시세여부($구매아이템행)) {
          echo 전송("❌ 현재 총 게임냥 기준 시세가 없어 {$구매아이템명}을(를) 구매할 수 없어요.");
          exit;
        }
        $총구매액 = $구매단가 * $구매수량;
      }
    
      $소량추가금 = 0;
      // 소량 구매(10개 미만) 시 구매 총액에 1% 추가
      if ($구매수량 < 10) {
        $소량추가금 = (int)ceil($총구매액 * 0.01);
        $총구매액 += $소량추가금;
      }

      $보유냥 = (int)($정보['point'] ?? 0);
      if ($보유냥 < $총구매액) {
        if ($구매아이템명 === '공커대실권') {
          $단가표시 = (int)(ceil($커플_amount / 1000) * 1000);
        } elseif ($구매아이템명 === '일방신청권') {
          $단가표시 = 전체냥기준금액(0.01, true);
        } elseif ($구매아이템명 === '일방연장권') {
          $단가표시 = 냥_앞두자리_뒤0(전체냥기준금액(0.01, true) * 7);
        } else {
          $단가표시 = 아이템_구매단가_계산($구매아이템행);
        }
        echo 전송(
          "‼️보유 {$단위} 부족!\n{$구매아이템명} : " . 구매가_축약표시($총구매액, $단위)
          . " (단가 " . 구매가_축약표시($단가표시, $단위) . " × {$구매수량})\n현재 보유 : " . 구매가_축약표시($보유냥, $단위)
        );
        exit;
      }
    
      $내idx = (int)($정보['idx'] ?? 0);
      if ($내idx <= 0) {
        echo 전송("❌ 회원 정보를 찾을 수 없어요.");
        exit;
      }
    
      if ($구매아이템명 === '지호') {
        $지호보유한도 = 100000;
        $지호보유개수 = function_exists('item_bag_qty') ? item_bag_qty($내idx, '지호') : 0;
        if ($지호보유개수 + $구매수량 > $지호보유한도) {
          $더구매가능 = max(0, $지호보유한도 - $지호보유개수);
          echo 전송(
            "❌ 지호 아이템은 최대 " . number_format($지호보유한도) . "개까지만 보유할 수 있어요.\n"
            . "현재 보유: " . number_format($지호보유개수) . "개 · 이번 구매 {$구매수량}개는 불가 (추가로 최대 " . number_format($더구매가능) . "개까지 구매 가능)"
          );
          exit;
        }
      }
    
      $가방적립 = true;
      if ($구매아이템명 === '공커대실권') {
        if (!empty($커플['idx']) && !empty($커플['edate'])) {
          $연장기간 = 7 * $구매수량;
          $칠일연장 = date("Y-m-d", strtotime($커플['edate'] . " +{$연장기간} days"));
          db_query("update tb_couple set edate = '{$칠일연장}' where idx = {$커플['idx']} ");
        }
        $가방적립 = false;
      } elseif ($구매아이템명 === '일방신청권') {
        $가방적립 = true;
      } elseif ($구매아이템명 === '일방연장권') {
        $가방적립 = true;
        $progress = db_select("select * from tb_progress where nick like '%{$두자리닉넴}%' ");
        if (!empty($progress['idx'])) {
          $progress['enddate'] = date("Y-m-d", strtotime($progress['enddate'] . " +7 days"));
          $닉등록 = $progress['nick'] . "1️⃣";
          db_query("update tb_progress set enddate = '{$progress['enddate']}', nick = '{$닉등록}' where idx = {$progress['idx']} ");
          // 진행 중이면 즉시 연장 사용 → 가방 미적립
          $가방적립 = false;
        }
      }
    
      if ($가방적립) {
        $지급sname = trim((string)$구매아이템행['sname']);
        $bagAdd = item_bag_add($내idx, $두자리닉넴, $지급sname, $구매수량);
        if (empty($bagAdd['ok'])) {
          echo 전송('❌ 아이템 지급 실패: ' . ($bagAdd['msg'] ?? '가방 오류'));
          exit;
        }
      }
      if ($구매아이템명 === '일방신청권' && function_exists('일방신청권_지급기록')) {
        일방신청권_지급기록($두자리닉넴, '구매', [
          'midx' => $내idx,
          'reason_text' => '상점 구매',
          'qty' => $구매수량,
        ]);
      }
      db_query("UPDATE tb_member SET point = point - {$총구매액} WHERE idx = {$내idx}");
      if (function_exists('아이템구매_시세상승')) {
        아이템구매_시세상승($구매아이템명, $구매단가, $구매수량);
      }
      if (function_exists('item_trade_log_구매')) {
        item_trade_log_구매($두자리닉넴, $구매아이템명, $구매수량, $구매단가, $총구매액, $소량추가금, $내idx, 'chat', 'info2', 'point');
      }

      $수량표시 = ($구매수량 > 1) ? " {$구매수량}개" : '';
      $추가금문구 = ($소량추가금 > 0) ? "\n(10개 미만 1% 추가금: " . 구매가_축약표시($소량추가금, $단위) . ")" : '';
      echo 전송("►[{$두자리닉넴}] {$구매아이템명}{$수량표시} 구매 " . 구매가_축약표시($총구매액, $단위) . $추가금문구);
      exit;

  }else if (strpos($status, '.대기') !== false) {
    $msg = "";
    if (preg_match('/\.대기/u', $status)) {

      // 채굴 대상 (무기 해제 +10 이상)
      $채굴대기_result = db_query("
        SELECT name, enhance
        FROM tb_member
        WHERE status = 0
          AND mount = 0
          AND IFNULL(enhance, 0) >= 10
          AND `item` IS NOT NULL
          AND `item` != ''
        ORDER BY enhance DESC, name ASC
      ");
      $채굴대기_목록 = array();
      while ($row = db_fetch($채굴대기_result)) {
        $채굴대기_목록[] = $row;
      }
      $채굴대기_인원수 = count($채굴대기_목록);
      $msg .= "\n\n채굴 중 (총 {$채굴대기_인원수}명)";
      if ($채굴대기_인원수 > 0) {
        foreach ($채굴대기_목록 as $idx => $행) {
          $닉 = trim($행['name']);
          $강화 = (int)($행['enhance'] ?? 0);
          $msg .= "\n  " . ($idx + 1) . ". {$닉} (+{$강화})";
        }
      } else {
        $msg .= "\n  대상자 없음";
      }

      echo 전송($msg);
      exit;
    }

  }else if (preg_match('/^\.금고\s+(.+)$/u', trim($status), $금고충전매치)) {
    // 홍보방 관리자: .금고 100경 / .금고 1000경 / .금고 1해 — config.tax 충전(또는 차감)
    관리자_명령_차단($두자리닉넴, $nick ?? '');
    $금액원문 = trim((string)$금고충전매치[1]);
    $지급양Str = function_exists('냥_금액_파싱_부호포함_문자열')
      ? 냥_금액_파싱_부호포함_문자열($금액원문)
      : (string)(function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함($금액원문) : '0');
    if ($지급양Str === '0' || $지급양Str === '' || $지급양Str === '-0') {
      echo 전송("❌ 사용법: .금고 금액\n예) .금고 100경 / .금고 1000경 / .금고 1해 / .금고 -50경");
      exit;
    }
    $증감 = function_exists('냥_입금_SQL증감') ? 냥_입금_SQL증감($지급양Str) : null;
    if (!$증감 || empty($증감['ok'])) {
      echo 전송("❌ 금액 형식을 확인해주세요.\n예) .금고 100경 / .금고 1000경 / .금고 1해");
      exit;
    }
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    $abs양 = $증감['abs'];
    $입금여부 = ($증감['sign'] >= 0);
    $abs_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($abs양) : preg_replace('/[^\d]/', '', (string)$abs양);
    if ($abs_sql === '' || $abs_sql === '0') {
      echo 전송("❌ 금액 형식을 확인해주세요.\n예) .금고 100경 / .금고 1000경 / .금고 1해");
      exit;
    }

    $금고_전 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
    if ($입금여부) {
      db_query("UPDATE config SET tax = tax + {$abs_sql} ");
      $동작 = '충전';
      $로그상태 = '금고충전';
      $로그금액 = $abs양;
    } else {
      if (function_exists('bccomp') && bccomp($금고_전, $abs양, 0) < 0) {
        $현재표시 = function_exists('금고_금액표시')
          ? 금고_금액표시($금고_전, $단위 ?? '냥')
          : ((function_exists('게임냥_안전표시') ? 게임냥_안전표시($금고_전, $단위 ?? '냥') : $금고_전 . ($단위 ?? '냥')));
        echo 전송("❌ 금고 잔액이 부족해요.\n현재 금고: {$현재표시}");
        exit;
      }
      db_query("UPDATE config SET tax = tax - {$abs_sql} ");
      $동작 = '차감';
      $로그상태 = '금고차감';
      $로그금액 = '-' . $abs양;
    }

    $금고_후 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : $금고_전;
    $금액표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($abs양, $단위 ?? '냥')
      : (function_exists('게임냥_안전표시') ? 게임냥_안전표시($abs양, $단위 ?? '냥') : (function_exists('냥축약표시') ? 냥축약표시($abs양, $단위 ?? '냥') : $abs양 . ($단위 ?? '냥')));
    $잔액표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($금고_후, $단위 ?? '냥')
      : (function_exists('게임냥_안전표시') ? 게임냥_안전표시($금고_후, $단위 ?? '냥') : $금고_후 . ($단위 ?? '냥'));

    if (function_exists('지급로그')) {
      지급로그($로그상태, $두자리닉넴, '금고', 0, $로그금액);
    }
    echo 전송("🏦 금고 {$동작}\n\n{$금액표시}\n현재 금고: {$잔액표시}");
    exit;

  }else if (preg_match('/^\.금고\s*$/u', trim($status))) {
    // 금고털이 — 금액은 문자열 연산 (BIGINT/PHP_INT_MAX 초과 금고 대응)
    // - 기본: 매 시도 금고 총액 1%를 내 냥에서 차감 (부족 시 시도 금지·신불 방지, 50% 소멸 / 50% 금고 환수)
    // - 1%: 금고 20% 획득 / 2%: 금고 10% / 3%: 금고 5%
    // - 0.01%: 대도둑(전액) + 💎대도둑 / 0.1%: 불지옥(남은 냥 50% → 금고)
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $금고잔액_전 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
    if ($금고잔액_전 === '' || $금고잔액_전 === '0') {
      echo 전송("❌ 금고에 남은 금액이 없습니다.");
      exit;
    }

    $내냥행 = db_select("SELECT CONCAT('N', CAST(IFNULL(point, 0) AS CHAR)) AS point FROM tb_member WHERE name = '" . addslashes($두자리닉넴) . "' LIMIT 1");
    $내냥_raw = (string)($내냥행['point'] ?? 'N0');
    if (isset($내냥_raw[0]) && ($내냥_raw[0] === 'N' || $내냥_raw[0] === 'n')) {
      $내냥_raw = substr($내냥_raw, 1);
    }
    $내냥_전 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($내냥_raw !== '' ? $내냥_raw : ($정보['point'] ?? 0))
      : preg_replace('/[^\d]/', '', $내냥_raw);
    if ($내냥_전 === '') {
      $내냥_전 = '0';
    }
    $닉_esc = addslashes($두자리닉넴);
    $단위표기 = isset($단위) ? $단위 : '냥';
    $금액표시 = function ($n) use ($단위표기) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($n, $단위표기);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($n, $단위표기);
      }
      if (function_exists('냥축약표시')) {
        return 냥축약표시($n, $단위표기);
      }
      return (function_exists('냥_정수문자열') ? 냥_정수문자열($n) : (string)$n) . $단위표기;
    };
    $비율금액 = function ($기준, $퍼센트) {
      $기준 = (string)$기준;
      $퍼센트 = (int)$퍼센트;
      if ($기준 === '' || $기준 === '0' || $퍼센트 <= 0) {
        return '0';
      }
      if (function_exists('냥_비율내림')) {
        $v = 냥_정수문자열(냥_비율내림($기준, $퍼센트 / 100));
      } elseif (function_exists('bcdiv') && function_exists('bcmul')) {
        $v = bcdiv(bcmul($기준, (string)$퍼센트, 0), '100', 0);
      } else {
        $v = (string)(int)floor((float)$기준 * $퍼센트 / 100);
      }
      if (($v === '' || $v === '0') && $기준 !== '0') {
        $v = '1';
      }
      return $v;
    };

    // 입장료: 금고 총액의 1%를 내 냥에서 차감 (50% 소멸 / 50% 금고)
    // 부족 시 마이너스·신불자 만들지 않고 시도 자체 금지
    $입장료 = $비율금액($금고잔액_전, 1);
    if ($입장료 === '0') {
      echo 전송("❌ 금고털이 입장료(금고 총액 1%)를 계산할 수 없어요.");
      exit;
    }

    // $내냥_raw: N접두 제거 후 부호 유지 (냥_정수문자열은 절댓값이므로 비교에 쓰지 않음)
    $보유_부호 = trim((string)$내냥_raw);
    if ($보유_부호 === '' || $보유_부호 === '+' || $보유_부호 === '-') {
      $보유_부호 = '0';
    }
    $입장료부족 = false;
    if (isset($보유_부호[0]) && $보유_부호[0] === '-') {
      $입장료부족 = true;
    } elseif (function_exists('bccomp')) {
      $입장료부족 = bccomp($보유_부호, $입장료, 0) < 0;
    } elseif (function_exists('냥_정수_미만')) {
      $입장료부족 = 냥_정수_미만($보유_부호, $입장료);
    } else {
      $입장료부족 = ((float)$보유_부호 < (float)$입장료);
    }
    if ($입장료부족) {
      $보유표시 = (isset($보유_부호[0]) && $보유_부호[0] === '-')
        ? ('-' . $금액표시(ltrim($보유_부호, '-')))
        : $금액표시($보유_부호);
      echo 전송(
        "❌ 금고털이 불가!\n"
        . "입장료(금고 1%): " . $금액표시($입장료) . "\n"
        . "보유 게임냥: {$보유표시}\n\n"
        . "※ 입장료가 부족하면 신불자가 되지 않도록 막아요.\n"
        . "입장료만큼 모은 뒤 다시 `.금고` 해 주세요."
      );
      exit;
    }

    $입장소멸 = function_exists('냥_나눗셈내림')
      ? 냥_정수문자열(냥_나눗셈내림($입장료, 2))
      : (function_exists('bcdiv') ? bcdiv($입장료, '2', 0) : (string)(int)floor((float)$입장료 / 2));
    $입장환수 = function_exists('냥_금액_문자열차감')
      ? 냥_금액_문자열차감((string)$입장료, (string)$입장소멸)
      : (function_exists('bcsub') ? bcsub($입장료, $입장소멸, 0) : (string)max(0, (int)$입장료 - (int)$입장소멸));
    $입장료_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($입장료) : $입장료;
    $입장환수_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($입장환수) : $입장환수;

    db_query("UPDATE tb_member SET point = point - {$입장료_sql} WHERE name = '{$닉_esc}' LIMIT 1");
    db_query("UPDATE config SET tax = tax + {$입장환수_sql} ");
    if (function_exists('지급로그')) {
      지급로그('금고털이-입장', $두자리닉넴, '', 0, $입장료);
    }

    $환수_sql = $입장환수_sql;
    $금고잔액 = function_exists('냥_금액_문자열합')
      ? 냥_금액_문자열합($금고잔액_전, (string)$입장환수)
      : (function_exists('bcadd') ? bcadd($금고잔액_전, $입장환수, 0) : (string)((int)$금고잔액_전 + (int)$입장환수));
    $내냥_후입장 = function_exists('냥_금액_문자열차감')
      ? 냥_금액_문자열차감((string)$내냥_전, (string)$입장료)
      : (function_exists('bcsub') ? bcsub($내냥_전, $입장료, 0) : (string)((int)$내냥_전 - (int)$입장료));
    // 음수면 불지옥 회수 대상 없음
    if (isset($내냥_후입장[0]) && $내냥_후입장[0] === '-') {
      $내냥_후입장 = '0';
    }

    $입장표시 = $금액표시($입장료);
    $입장환수표시 = $금액표시($입장환수);
    $결과문구 = "";

    // 단일 롤 (10만 분율): 대도둑 0.01% / 불지옥 0.1% / 20%획득 1% / 10%획득 2% / 5%획득 3%
    $roll = mt_rand(1, 100000);
    if ($roll <= 10) {
      // 0.01% 대도둑 — 금고 전액
      $획득금액 = $금고잔액;
      if ($획득금액 === '' || $획득금액 === '0') {
        $획득금액 = '1';
      }
      $획득금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($획득금액) : $획득금액;
      db_query("UPDATE tb_member SET point = point + {$획득금액_sql}, title = '💎대도둑' WHERE name = '{$닉_esc}' LIMIT 1");
      db_query("UPDATE config SET tax = tax - {$획득금액_sql} ");
      if (function_exists('지급로그')) {
        지급로그('금고털이-대성공', $두자리닉넴, '', 0, $획득금액);
      }
      $결과문구 = "👑 초대박! 대도둑 등장!\n금고 전액 " . $금액표시($획득금액) . " 획득!\n🏷️ 💎대도둑 칭호 부여";
      $결과문구 .= "\n입장료 {$입장표시} 차감\n-재환수 {$입장환수표시}\n-소멸 " . $금액표시($입장소멸);
      unset($환수_sql);

    } elseif ($roll <= 110) {
      // 0.1% 불지옥 — 남은 냥 50% → 금고
      $필요차감 = $비율금액($내냥_후입장, 50);
      if ($필요차감 === '0') {
        $결과문구 = "🔥 불지옥에 떨어졌습니다!\n하지만 회수할 냥이 없어 그냥 빠져나왔어요...";
        $결과문구 .= "\n입장료 {$입장표시} 차감\n-재환수 {$입장환수표시}\n-소멸 " . $금액표시($입장소멸);
      } else {
        $차감_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($필요차감) : $필요차감;
        db_query("UPDATE tb_member SET point = point - {$차감_sql} WHERE name = '{$닉_esc}' LIMIT 1");
        db_query("UPDATE config SET tax = tax + {$차감_sql} ");
        if (function_exists('지급로그')) {
          지급로그('금고털이-불지옥', $두자리닉넴, '', 0, $필요차감);
        }
        $환수합 = function_exists('냥_금액_문자열합')
          ? 냥_금액_문자열합((string)$입장환수, (string)$필요차감)
          : (function_exists('bcadd') ? bcadd($입장환수, $필요차감, 0) : (string)((int)$입장환수 + (int)$필요차감));
        $환수_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($환수합) : $환수합;
        $결과문구 = "🔥 불지옥에 떨어졌습니다!\n금고털이 도전 중 재앙이 덮쳤어요...\n남은 냥 50% (" . $금액표시($필요차감) . ") 회수 → 금고로 들어갔어요.";
        $결과문구 .= "\n입장료 {$입장표시} 차감\n-재환수 {$입장환수표시}\n-소멸 " . $금액표시($입장소멸);
      }

    } elseif ($roll <= 1110) {
      $성공퍼센트 = 20;
    } elseif ($roll <= 3110) {
      $성공퍼센트 = 10;
    } elseif ($roll <= 6110) {
      $성공퍼센트 = 5;
    } else {
      $성공퍼센트 = 0;
    }

    if (isset($성공퍼센트) && $성공퍼센트 > 0 && $결과문구 === '') {
      $획득금액 = $비율금액($금고잔액, $성공퍼센트);
      if (function_exists('bccomp') && bccomp($획득금액, $금고잔액, 0) > 0) {
        $획득금액 = $금고잔액;
      } elseif (!function_exists('bccomp') && function_exists('냥_정수_미만') && 냥_정수_미만($금고잔액, $획득금액)) {
        $획득금액 = $금고잔액;
      }
      $획득금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($획득금액) : $획득금액;
      db_query("UPDATE tb_member SET point = point + {$획득금액_sql} WHERE name = '{$닉_esc}' LIMIT 1");
      db_query("UPDATE config SET tax = tax - {$획득금액_sql} ");
      if (function_exists('지급로그')) {
        지급로그('금고털이', $두자리닉넴, '', 0, $획득금액);
      }
      $결과문구 = "🤑 금고털이 성공! (금고 {$성공퍼센트}%)\n" . $금액표시($획득금액) . " 획득!";
      $결과문구 .= "\n입장료 {$입장표시} 차감\n-재환수 {$입장환수표시}\n-소멸 " . $금액표시($입장소멸);
      unset($환수_sql);
    } elseif ($결과문구 === '') {
      $결과문구 = "😵‍💫 금고털이 실패!\n입장료 {$입장표시} 차감\n-재환수 {$입장환수표시}\n-소멸 " . $금액표시($입장소멸);
    }

    $새금고잔액 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : $금고잔액;
    if (isset($환수_sql) && $환수_sql !== '' && $환수_sql !== '0' && function_exists('냥_금액_문자열합') && function_exists('bccomp')) {
      $기대잔액 = 냥_금액_문자열합($금고잔액_전, (string)$환수_sql);
      if (bccomp($새금고잔액, $기대잔액, 0) < 0) {
        $기대_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($기대잔액) : $기대잔액;
        @db_query("UPDATE config SET tax = {$기대_sql} LIMIT 1");
        $새금고잔액 = $기대잔액;
      }
    }

    $msg = "{$결과문구}\n\n현재 금고: " . $금액표시($새금고잔액);
    echo 전송($msg);
    exit;

  }else if (preg_match('/\.(?:겜냥|이체|입금)\s*(.*)$/u', $status, $겜냥매치)) {
      // 게임냥(point) 지급/차감 — .겜냥 (구 .이체/.입금) · 관리자 전용
      {
        $after = trim((string)($겜냥매치[1] ?? ''));
        if (in_array(getTwoCharNick($nick), $관리자)) {
          if (preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
            $받는닉 = trim($parts[1]);
            if (function_exists('getTwoCharNick')) {
              $파싱닉 = getTwoCharNick($받는닉);
              if ($파싱닉 !== '') {
                $받는닉 = $파싱닉;
              }
            }
            $지급양Str = function_exists('냥_금액_파싱_부호포함_문자열')
              ? 냥_금액_파싱_부호포함_문자열(trim($parts[2]))
              : (string)냥_금액_파싱_부호포함(trim($parts[2]));
            if ($지급양Str === '0') {
              echo 전송("❌ 금액 형식을 확인해주세요.\n예) .겜냥 이지 43500000 / .겜냥 이지 5천만 / .겜냥 다오 -923경");
              exit;
            }
            $증감 = function_exists('냥_입금_SQL증감') ? 냥_입금_SQL증감($지급양Str) : null;
            if (!$증감 || empty($증감['ok'])) {
              echo 전송("❌ 금액 형식을 확인해주세요.\n예) .겜냥 이지 43500000 / .겜냥 이지 5천만 / .겜냥 다오 -923경");
              exit;
            }
            if (function_exists('tb_member_point_컬럼_보장')) {
              tb_member_point_컬럼_보장();
            }
            $abs양 = $증감['abs'];
            $입금여부 = ($증감['sign'] >= 0);
            // 억 미만도 보이게 — 게임냥_경조억표시는 억 미만을 0냥으로 절사함
            $금액표시 = function_exists('랭킹_게임냥표시')
              ? 랭킹_게임냥표시($abs양, '냥')
              : (function_exists('냥축약표시') ? 냥축약표시($abs양, '냥') : (function_exists('콤마삽입') ? 콤마삽입($abs양) . '냥' : $abs양 . '냥'));
            $받는닉_esc = addslashes($받는닉);
            $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
            if (empty($받는친구['idx'])) {
              $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE TRIM(name) = '{$받는닉_esc}' LIMIT 1");
            }
            if (empty($받는친구['idx'])) {
              echo 전송("존재하지 않는 사용자 입니다.");
              exit;
            }
            $받는_idx = (int)$받는친구['idx'];
            global $conn;
            if (!$입금여부) {
              $ok_upd = db_query("UPDATE tb_member SET point = point - {$abs양} WHERE idx = {$받는_idx} LIMIT 1");
              $지급여부 = "차감";
              $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 차감!";
              $지급양로그 = '-' . $abs양;
            } else {
              $ok_upd = db_query("UPDATE tb_member SET point = point + {$abs양} WHERE idx = {$받는_idx} LIMIT 1");
              $지급여부 = "이체";
              $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 지급!";
              $지급양로그 = $abs양;
            }
            $aff = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
            $err = ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
            if ($ok_upd === false || $aff < 1 || $err !== '') {
              $hint = ($err !== '') ? "\n({$err})" : '';
              echo 전송("❌ {$받는친구['name']} 겜냥 실패{$hint}\n금액: {$금액표시}");
              exit;
            }
            $후잔액 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE idx = {$받는_idx} LIMIT 1");
            if (!empty($후잔액['point_str'])) {
              if (function_exists('랭킹_게임냥표시')) {
                $msg .= "\n현재 보유: " . 랭킹_게임냥표시($후잔액['point_str'], '냥');
              } elseif (function_exists('냥축약표시')) {
                $msg .= "\n현재 보유: " . 냥축약표시($후잔액['point_str'], '냥');
              }
            }
            지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양로그);
            echo 전송($msg);
            exit;
          }
          echo 전송("❌ 사용법: .겜냥 닉네임 금액\n예) .겜냥 이지 43500000 / .겜냥 이지 5천만 / .겜냥 다오 -923경");
          exit;
        } else {
          echo 전송($두자리닉넴 . " 블랙리스트 등록완료!");
          exit;
        }
      }
      echo 전송("❌ 사용법: .겜냥 닉네임 금액\n예) .겜냥 이지 43500000 / .겜냥 이지 5천만 / .겜냥 다오 -923경");
      exit;

  }else if (false && strpos($status, '.입금') !== false) {
      // (구 .입금) → .겜냥 으로 통합됨. 아래 블록 미사용
      if (preg_match('/\.입금\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]);
        if (in_array(getTwoCharNick($nick), $관리자)) {
          if (preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
            $받는닉 = trim($parts[1]);
            if (function_exists('getTwoCharNick')) {
              $파싱닉 = getTwoCharNick($받는닉);
              if ($파싱닉 !== '') {
                $받는닉 = $파싱닉;
              }
            }
            $지급양Str = function_exists('냥_금액_파싱_부호포함_문자열')
              ? 냥_금액_파싱_부호포함_문자열(trim($parts[2]))
              : (string)냥_금액_파싱_부호포함(trim($parts[2]));
            if ($지급양Str === '0') {
              echo 전송("❌ 금액 형식을 확인해주세요.\n예) .입금 다오 5조 / .입금 다오 -923경 / .입금 뮤뮤 1000경");
              exit;
            }
            $증감 = function_exists('냥_입금_SQL증감') ? 냥_입금_SQL증감($지급양Str) : null;
            if (!$증감 || empty($증감['ok'])) {
              echo 전송("❌ 금액 형식을 확인해주세요.\n예) .입금 다오 5조 / .입금 다오 -923경 / .입금 뮤뮤 1000경");
              exit;
            }
            if (function_exists('tb_member_point_컬럼_보장')) {
              tb_member_point_컬럼_보장();
            }
            $abs양 = $증감['abs'];
            $입금여부 = ($증감['sign'] >= 0);
            $금액표시 = function_exists('랭킹_게임냥표시')
              ? 랭킹_게임냥표시($abs양, '냥')
              : (function_exists('냥축약표시') ? 냥축약표시($abs양, '냥') : (function_exists('콤마삽입') ? 콤마삽입($abs양) . '냥' : $abs양 . '냥'));
            $받는닉_esc = addslashes($받는닉);
            if ($받는닉 === "전체") {
              if (!$입금여부) {
                echo 전송("❌ 전체 차감은 지원하지 않아요. 닉네임을 지정해주세요.\n예) .입금 다오 -923경");
                exit;
              }
              $ok_all = db_query("UPDATE tb_member SET point = point + {$abs양} WHERE status = 0");
              if ($ok_all === false) {
                $err = ($conn instanceof mysqli) ? mysqli_error($conn) : '';
                echo 전송("❌ 전체 입금 실패" . ($err !== '' ? " ({$err})" : '') . ".\npoint 컬럼(DECIMAL) 상태를 확인해주세요.");
                exit;
              }
              $msg = "📣 전체 인원에게 {$금액표시} 게임냥 지급!";
              $받는친구['name'] = "전체";
              $지급여부 = "지급";
              $지급양로그 = $abs양;
            } elseif ($받는닉 === "랜덤박스") {
              $지급개수 = (int)$abs양;
              if ($지급개수 < 1 || (function_exists('bccomp') && bccomp($abs양, (string)PHP_INT_MAX, 0) > 0)) {
                echo 전송("❌ 랜덤박스 개수는 1 이상·일반 개수 범위로 입력해줘.");
                exit;
              }
              $result = db_query("SELECT name FROM tb_member WHERE status = 0");
              while ($row = db_fetch($result)) {
                $대상_esc = addslashes($row['name']);
                for ($i = 0; $i < $지급개수; $i++) {
                  db_query("
                    INSERT INTO tb_member_item
                    SET nick = '{$대상_esc}', itemname = '랜덤박스', status = 0, regdate = NOW()
                  ");
                }
              }
              $msg = "📣 전체 인원에게 랜덤박스 {$지급개수}개씩 입금 완료!";
              $받는친구['name'] = "전체";
              $지급여부 = "입금";
              $지급양로그 = $지급개수;
            } else {
              $받는친구 = db_select("SELECT idx, name, CAST(IFNULL(point, 0) AS CHAR) AS point_before FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
              if (empty($받는친구['idx'])) {
                $받는친구 = db_select("SELECT idx, name, CAST(IFNULL(point, 0) AS CHAR) AS point_before FROM tb_member WHERE TRIM(name) = '{$받는닉_esc}' LIMIT 1");
              }
              if (empty($받는친구['idx'])) {
                echo 전송("존재하지 않는 사용자 입니다.");
                exit;
              }
              $받는_idx = (int)$받는친구['idx'];
              // point ± 리터럴 — 3405경(>PHP_INT_MAX)도 문자열로 차감/지급
              $pointSql = !empty($증감['sql']) ? $증감['sql'] : (($입금여부 ? "point + {$abs양}" : "point - {$abs양}"));
              $ok_upd = db_query("UPDATE tb_member SET point = {$pointSql} WHERE idx = {$받는_idx} LIMIT 1");
              global $conn;
              $aff = ($conn instanceof mysqli) ? (int)mysqli_affected_rows($conn) : 0;
              $err = ($conn instanceof mysqli) ? trim((string)mysqli_error($conn)) : '';
              if ($ok_upd === false || $aff < 1 || $err !== '') {
                $hint = ($err !== '') ? "\n({$err})" : '';
                echo 전송("❌ {$받는친구['name']} 입금/차감 실패{$hint}\n금액: {$금액표시}\npoint 컬럼이 DECIMAL인지 확인해주세요.");
                exit;
              }
              // 입금 후 잔액 확인 (표시용)
              $후잔액 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point_str FROM tb_member WHERE idx = {$받는_idx} LIMIT 1");
              $후잔액표시 = '';
              if (!empty($후잔액['point_str']) && function_exists('랭킹_게임냥표시')) {
                $후잔액표시 = "\n현재 보유: " . 랭킹_게임냥표시($후잔액['point_str'], '냥');
              } elseif (!empty($후잔액['point_str']) && function_exists('냥축약표시')) {
                $후잔액표시 = "\n현재 보유: " . 냥축약표시($후잔액['point_str'], '냥');
              }
              if (!$입금여부) {
                $지급여부 = "차감";
                $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 차감!" . $후잔액표시;
              } else {
                $지급여부 = "입금";
                $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 입금!" . $후잔액표시;
              }
              $지급양로그 = $입금여부 ? $abs양 : ('-' . $abs양);
            }
            지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양로그);
            지급로그($지급여부, $받는친구['name'], $두자리닉넴, 0, $지급양로그);
            echo 전송($msg);
            exit;
          }
          echo 전송("❌ 사용법: .입금 닉네임 금액\n예) .입금 다오 5조 / .입금 다오 -923경 / .입금 뮤뮤 1000경");
          exit;
        } else {
          echo 전송($두자리닉넴 . " 블랙리스트 등록완료!");
          exit;
        }
      }
      echo 전송("❌ 사용법: .입금 닉네임 금액\n예) .입금 다오 5조 / .입금 다오 -923경 / .입금 뮤뮤 1000경");
      exit;

  }else if(strpos($status, '.점수') !== false || strpos($status, '．점수') !== false){
    require_once __DIR__ . '/game/yabawi_score.inc.php';
  }


  if (trim($status) === ".강화방법") {
    $msg = "⚔️ 강화 방법 (요약)";
    $msg .= "\n/게임방 에서 이용 가능\n\n";
    $msg .= "• 무기 사기: `.강화 단소`·`활`·`마법` 중 선택 (각 10만)\n";
    $msg .= "• 올리기: 무기 있을 때 `.강화` → 냥 내고 한 단계 도전(단계가 높을수록 어려움)\n";
    $msg .= "• 강화비 소멸분: 50% 소멸 · 25% 금고 · 25% 로또\n";
    $msg .= "• 종류별 +1~+100은 1명만 · 이미 있으면 그 사람한테 탈취(비용·확률=일반 강화와 동일)\n";
    $msg .= "• 실패 시 파손·강화 리셋 → `.강화 수호` 로 미리 쌓아 두면 면제 (수호 아이템 우선, 없으면 1회 본방 0.1냥)\n";
    $msg .= "• +18강 이상 `.무기교체` 로 무기 종만 바꿀 수 있어(실패 시 -1강 가능, 성공 시 강화 유지)\n";
    $msg .= "• +10 이상이면 `.내무기` 로 장착 해제 → 채굴·광물 (`.채굴란` 참고)\n";
    $msg .= "• 채굴냥 10냥 이상 `.수령`으로 본방냥 수령\n";
    $msg .= "• 시전·보호·비용 자세한 건 `.강화비용` 등 채팅 안내 참고";
    echo 전송($msg);
    exit;
  }
  
  if (preg_match('/^\.무기랭킹(?:\s+(\S+))?\s*$/u', trim((string)$status), $무기랭킹매치)) {
    if (!function_exists('무기랭킹_문구')) {
      require_once __DIR__ . '/enhance_renewal.inc.php';
    }
    $결과 = 무기랭킹_문구(trim((string)($무기랭킹매치[1] ?? '')));
    echo 전송((string)($결과['msg'] ?? ''));
    exit;
  }

  // 홍보방 전용 알림 큐 (보스 진행 현황 등)
  info2알림_큐_응답_시도();

}
