<?php
require_once __DIR__ . '/_bootstrap.php';
include_once __DIR__ . '/_bonbang.php';
// 본방 정보

// 한글 문자열을 초성 문자열로 변환하는 유틸 함수들
if (!function_exists('unicode_ord')) {
  function unicode_ord($ch) {
    $h = ord($ch[0]);
    if ($h <= 0x7F) return $h;
    if ($h < 0xC2) return null;
    if ($h <= 0xDF) return (($h & 0x1F) << 6) | (ord($ch[1]) & 0x3F);
    if ($h <= 0xEF) return (($h & 0x0F) << 12) | ((ord($ch[1]) & 0x3F) << 6) | (ord($ch[2]) & 0x3F);
    if ($h <= 0xF4) return (($h & 0x07) << 18) | ((ord($ch[1]) & 0x3F) << 12)
                                   | ((ord($ch[2]) & 0x3F) << 6)
                                   | (ord($ch[3]) & 0x3F);
    return null;
  }
}

if (!function_exists('toChosung')) {
  function toChosung($str) {
    $chosungList = ['ㄱ','ㄲ','ㄴ','ㄷ','ㄸ','ㄹ','ㅁ','ㅂ','ㅃ','ㅅ','ㅆ','ㅇ','ㅈ','ㅉ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ'];
    $result = '';
    $len = mb_strlen($str, 'UTF-8');

    for ($i = 0; $i < $len; $i++) {
      $ch = mb_substr($str, $i, 1, 'UTF-8');
      $code = unicode_ord($ch);

      // 한글 음절 범위 (가 ~ 힣)
      if ($code !== null && $code >= 0xAC00 && $code <= 0xD7A3) {
        $base = $code - 0xAC00;
        $choIndex = (int)($base / (21 * 28));
        $result .= $chosungList[$choIndex] ?? $ch;
      } else {
        // 한글이 아니면 그대로 유지 (공백/기호 등)
        $result .= $ch;
      }
    }

    return $result;
  }
}

$nick = nick_파라미터($nick ?? '');
$두자리닉넴 = getTwoCharNick($nick);
$status = trim(msg_파라미터($msg ?? ''));
if (function_exists('status_정규화')) {
  $status = status_정규화($status);
}
$status = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}]+/u', '', (string)$status);
$status = trim((string)$status);

// 본방 `.로또` — 회원동기화·락 전에 즉시 응답 (풀 카운터 / amount>0 SUM)
if (preg_match('/^\.로또\s*$/u', $status)) {
  @set_time_limit(15);
  try {
    if (is_file(__DIR__ . '/game/lotto_amount.inc.php')) {
      require_once __DIR__ . '/game/lotto_amount.inc.php';
    }
    if (!function_exists('전송')) {
      include_once __DIR__ . '/config.php';
    }
    echo 전송(로또_채팅조회_문구(-1));
  } catch (Throwable $e) {
    echo 전송("❌ 로또 조회 중 오류가 났어요. 잠시 후 다시 시도해주세요.");
  }
  exit;
}

list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
if ($두자리닉넴 === '' && trim((string)($정보['name'] ?? '')) !== '') {
  $두자리닉넴 = trim((string)$정보['name']);
}
include_once __DIR__ . '/config.php';

// `.로또 추첨` / `.로또 지급` / `.로또당첨` — 락 충돌 시 무응답(exit) 방지
$_info1_로또입력 = trim((string)$status);
$_info1_로또락생략 = (bool)preg_match(
  '/^\.로또(?:\s*추첨\s*$|\s*지급(?:\s+\d+)?\s*$)|^\.로또당첨\s+\S+\s*$/u',
  $_info1_로또입력
);
if (!$_info1_로또락생략 && !api_동시요청_락_시작($nick, $status)) {
  exit;
}
if (!$_info1_로또락생략) {
  register_shutdown_function('api_동시요청_락_해제');
}

// .자숙 / .자숙끝·.자숙종료 닉 — 최우선 (연구실 info3 과 동일, 회원·닉 무관)
if (function_exists('자숙_명령_처리')) {
  자숙_명령_처리($status);
}
if (function_exists('자숙끝_명령_처리')) {
  자숙끝_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}
if (function_exists('자숙스냅샷_명령_처리')) {
  자숙스냅샷_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}
if (function_exists('자숙종료_명령_처리')) {
  자숙종료_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

if (!function_exists('info1_내냥_명령_처리')) {
  /** 본방(info1) .내냥 — 호칭·닉 + 본방냥만 */
  function info1_내냥_명령_처리($nick, $두자리닉넴) {
    list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
    if (trim((string)($정보['name'] ?? '')) === '') {
      $debug = function_exists('nick_디버그_리포트') ? nick_디버그_리포트(true) : '';
      echo 전송("❌ 등록된 회원만 `.내냥`을 사용할 수 있어요." . ($debug !== '' ? "\n\n{$debug}" : ''));
      exit;
    }
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $닉_esc_내냥 = addslashes($두자리닉넴);
    $pt행 = @db_select("SELECT CAST(IFNULL(newpoint, 0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$닉_esc_내냥}' LIMIT 1");
    $본냥 = $pt행['newpoint'] ?? ($정보['newpoint'] ?? 0);
    $계급 = 계급($정보['point'] ?? 0);
    $호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');
    $msg1 = trim($호칭 . ' ' . $두자리닉넴);
    $msg1 .= "\n본방냥 : " . newpoint표시($본냥) . $단위표;
    echo 전송($msg1);
    exit;
  }
}

// 본방 .내냥 — 호칭·닉 + 본방냥만
if (trim((string)$status) === '.내냥') {
  info1_내냥_명령_처리($nick, $두자리닉넴);
}

// 본방 `.보스젠` — 다음 보스·출현 시각
if (trim((string)$status) === '.보스젠') {
  require_once __DIR__ . '/game/boss_raid.inc.php';
  echo 전송(boss_raid_젠안내문구());
  exit;
}

// 본방 `.신용불량` — 홍보방(info2)과 동일 (신불자·홀짝 제한 현황)
if (trim((string)$status) === '.신용불량') {
  $단위표기 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
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
      ? 냥축약표시($ptAbs, $단위표기)
      : (number_format((float)$ptAbs) . $단위표기);
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

// 본방 `.마피아` — 다음 시작 시각 / `.마피아종료` — 강제 종료(민호)
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

// 본방 `.관리자` — 현재 관리자 목록
if (trim((string)$status) === '.관리자') {
  $admin_result = db_query("SELECT name FROM tb_member WHERE admin = 1 ORDER BY name");
  $목록 = [];
  if ($admin_result) {
    while ($row = db_fetch($admin_result)) {
      $n = trim((string)($row['name'] ?? ''));
      if ($n !== '') {
        $목록[] = $n;
      }
    }
  }
  if (count($목록) > 0) {
    echo 전송("👑 현재 관리자\n" . implode(", ", $목록));
  } else {
    echo 전송("👑 등록된 관리자가 없습니다.");
  }
  exit;
}

// 관리방 관리명령 → 본방에서도 관리자만 실행 (분리: config $ADMIN_ROOM_CMDS_IN_MAIN = false)
if (!empty($ADMIN_ROOM_CMDS_IN_MAIN)) {
  require_once __DIR__ . '/admin_room_commands.inc.php';
  관리방명령_처리($status, $두자리닉넴, $nick ?? '', $정보 ?? [], $관리자 ?? [], ['room' => 'main']);
}

// 본방 `/도하` · `.도하` — 고정 소개 멘트 (info1 전용)
$도하cmd = function_exists('status_정규화') ? status_정규화($status) : trim((string)$status);
$도하cmd = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}]+/u', '', (string)$도하cmd);
$도하cmd = preg_replace('/\s+/u', '', $도하cmd);
if ($도하cmd === '/도하' || $도하cmd === '／도하' || $도하cmd === '.도하' || $도하cmd === '．도하') {
  echo 전송(
    "도하🙅‍♂️\n" .
    "주말 힘드러여(윙크)\n" .
    "외박도 불가🤦‍♂️\n" .
    "한시간반 이상 안가요👋\n" .
    "19시~22시는 틈톡만가능해여🤸‍♂️\n" .
    "\n" .
    "도하🙆‍♂️\n" .
    "평일 낮프에여(연락잘해여)\n" .
    "12시~21시 쌉가능🌞\n" .
    "손으로 하는거 다잘해여🤟\n" .
    "다정해요😘\n" .
    "너만봐여\n" .
    "어흥🦁"
  );
  exit;
}

if (function_exists('본인인증_명령_처리')) {
  본인인증_명령_처리($status, $두자리닉넴);
}

// `.탕감 닉 5%` — 관리자 전용 · 마이너스 부채 % 차감 (본방/홍보방/관리방 공통)
if (preg_match('/^\.탕감/u', trim((string)$status))) {
  require_once __DIR__ . '/game/dice_chat.inc.php';
  탕감_명령_처리($두자리닉넴, $status, $nick ?? '');
}

// 본방 `.대출` 목록 · `.대출 닉 금액`(관리자) · `.상환`(원금+누적이자 게임냥 차감)
if (preg_match('/^\.(?:대출|상환)(?:\s|$)/u', trim((string)$status))) {
  require_once __DIR__ . '/game/loan.inc.php';
  대출_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 `.주사위` — 누구나 1개 / `.주사위 2` · `.주사위2` — 신불자 빚탕감(본방·홍보방·관리방 공통)
// 카카오 비례폭 폰트에서 ┃ 박스는 간격이 깨지므로 ● + 전각공백만 사용
if (preg_match('/^\.주사위\s*(\d+)?\s*$/u', trim((string)$status), $주사위_m)) {
  require_once __DIR__ . '/game/dice_chat.inc.php';
  $개수 = isset($주사위_m[1]) && $주사위_m[1] !== '' ? (int)$주사위_m[1] : 1;
  if ($개수 === 2) {
    주사위2_명령_처리($두자리닉넴, $status);
    exit;
  }
  if ($개수 !== 1) {
    echo 전송("❌ `.주사위` 또는 `.주사위 2`(신불자 빚탕감)만 가능해요.");
    exit;
  }
  $눈 = random_int(1, 6);
  echo 전송(주사위_면출력([$눈]));
  exit;
}

// 본방 .타이틀 — 홍보방과 동일 (게임냥 30만냥 · 로컬/GPT 칭호)
if (trim((string)$status) === '.타이틀') {
  if (function_exists('타이틀_명령_처리')) {
    타이틀_명령_처리($status, $두자리닉넴, is_array($정보 ?? null) ? $정보 : []);
  }
  echo 전송('❌ 타이틀 기능을 불러올 수 없어요.');
  exit;
}

// 본방 .공커등록 우서💛앙앙 — _mutual·.공커 목록보다 먼저 (이모지 하트 포함)
if (strpos(trim((string)$status), '.공커등록') === 0) {
  if (function_exists('공커등록_명령_처리')) {
    공커등록_명령_처리($status, $두자리닉넴, $관리자 ?? []);
  }
  echo 전송("❌ 사용법: .공커등록 영수🖤하니\n예) .공커등록 우서💛앙앙");
  exit;
}

// 본방 .등록 일방 영수(임티)영희 — 관리자 · 등록/삭제 토글
if (preg_match('/^\.등록(?:[\s\p{Zs}]+)일방/u', trim((string)$status))) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (function_exists('등록_일방_명령_처리')) {
    등록_일방_명령_처리($status, $두자리닉넴);
  }
  echo 전송("❌ 사용법: .등록 일방 다운,제니\n예) .등록 일방 영수❤️영희");
  exit;
}

// 본방 .연장 일방 치즈🐸오리 — 관리자 · 양쪽 일방연장권 1개
if (preg_match('/^\.연장(?:[\s\p{Zs}]+|$)/u', trim((string)$status))) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (function_exists('연장_명령_처리')) {
    연장_명령_처리($status, $두자리닉넴);
  }
  echo 전송("❌ 사용법: .연장 일방 치즈🐸오리\n예) .연장 일방 영수❤️영희");
  exit;
}

// 본방 .등록 강일/지목 수수🤝 닉네 — 관리자 · 등록/삭제 토글 (연구실과 동일)
if (preg_match('/^\.\s*등록(?:[\s\p{Zs}]+)(강일|지목)/u', trim((string)$status))) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (function_exists('등록_강일지목_명령_처리')) {
    등록_강일지목_명령_처리($status, $두자리닉넴);
  }
  echo 전송("❌ 사용법: .등록 강일 수수🤝닉네\n예) .등록 지목 영수❤️영희");
  exit;
}

// 본방(info1): .랭킹1·2·3 — 홍보방 전용
if (strpos($status, '.랭킹1') !== false || strpos($status, '.랭킹2') !== false || strpos($status, '.랭킹3') !== false) {
  echo 전송("❌ `.랭킹`은 홍보방에서만 이용할 수 있어요.");
  exit;
}

if (trim($status) === '.닉디버그') {
  echo 전송(nick_디버그_리포트(false));
  exit;
}

// $nick 에서 이모지/기호/'남'·'여' 성별표기 제거 후 한글 2글자 여부 확인
$nick_판별 = trim((string)$nick);
// 1) 이모지/픽토그램/기호류 전부 제거 (제로 남🍀 등 커스텀 장식 대응)
$nick_판별 = preg_replace('/\p{Extended_Pictographic}+/u', '', $nick_판별);
$nick_판별 = preg_replace('/[\p{So}\p{Sk}\p{Sm}\p{Cn}\p{Cs}]+/u', '', $nick_판별);
// 2) 단독 '남'/'여' 성별표기 제거 (앞/뒤/공백 구분)
$nick_판별 = preg_replace('/(^|\s)(남|여)(\s|$)/u', ' ', $nick_판별);
// 3) 공백 모두 제거 후 정리
$nick_판별 = preg_replace('/\s+/u', '', $nick_판별);
$nick_판별 = trim($nick_판별);
// 4) ZWJ·변형선택자 등으로 남는 보이지 않는 문자는 제거하고, 판별은 한글 음절만 집계
$nick_판별 = preg_replace('/\p{Cf}+/u', '', $nick_판별);
$nick_판별_한글 = preg_replace('/[^가-힣]/u', '', (string)$nick_판별);

if (!function_exists('info1_ensure_config_sinip_damdang')) {
  function info1_ensure_config_sinip_damdang() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $col = db_select("SHOW COLUMNS FROM config LIKE '신입담당'");
    if (empty($col)) {
      db_query("ALTER TABLE config ADD COLUMN `신입담당` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '신입 수령 담당 2글자닉'");
    }
  }
}

if (!function_exists('info1_ensure_config_타수이벤')) {
  function info1_ensure_config_타수이벤() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    $시작 = db_select("SHOW COLUMNS FROM config LIKE '타수이벤시작'");
    if (empty($시작)) {
      db_query("ALTER TABLE config ADD COLUMN `타수이벤시작` DATETIME NULL DEFAULT NULL COMMENT '타수 이벤트 시작 시각'");
    }
    $종료 = db_select("SHOW COLUMNS FROM config LIKE '타수이벤종료'");
    if (empty($종료)) {
      db_query("ALTER TABLE config ADD COLUMN `타수이벤종료` DATETIME NULL DEFAULT NULL COMMENT '타수 이벤트 종료 시각'");
    }
  }
}

if (!function_exists('info1_메시지_내가받을께인가')) {
  /** 신입 담당 등록 문구 (공백·끝 !?. 제거 후 일치) */
  function info1_메시지_내가받을께인가($status) {
    $t = preg_replace('/\s+/u', '', trim((string)$status));
    $t = preg_replace('/[!?.~…]+$/u', '', $t);
    if ($t === '') {
      return false;
    }
    static $patterns = null;
    if ($patterns === null) {
      $patterns = [
        '/^내가받을(께|게|래|까|습니다)$/u',
        '/^내가박(을|힐)(께|게|래|까)$/u',
        '/^내가안받을게$/u',
        '/^[너니]가받을(께|게|래|까)$/u',
        '/^[너니]가받자$/u',
        '/^내가받지롱$/u',
        '/^ㄴㄱㅂㅇㄱ$/u',
      ];
    }
    foreach ($patterns as $p) {
      if (preg_match($p, $t)) {
        return true;
      }
    }
    return false;
  }
}

if (!function_exists('info1_handle_sinip_damdang_commands')) {
  /**
   * 신입 담당·색변 (2글자닉 게이트보다 먼저 처리). 처리 시 exit.
   */
  function info1_handle_sinip_damdang_commands($status, $두자리닉넴, $관리자 = []) {
    $status_trim = trim((string)$status);

    if (info1_메시지_내가받을께인가($status)) {
      if ($두자리닉넴 === '') {
        echo 전송("❌ 두글자 닉+성별 설정 후 `내가받을께` 입력해줘!");
        exit;
      }
      info1_ensure_config_sinip_damdang();
      $설정 = db_select("SELECT `신입담당` FROM config LIMIT 1");
      $기존담당 = trim((string)($설정['신입담당'] ?? ''));
      if ($기존담당 !== '') {
        if ($기존담당 === $두자리닉넴) {
          echo 전송("✅ 이미 [ {$두자리닉넴} ] 님이 신입 담당이에요.");
        } else {
          echo 전송("❌ 이미 [ {$기존담당} ] 님이 신입 담당이에요.\n먼저 말한 담당자만 받을 수 있어요.");
        }
        exit;
      }
      $닉_esc = addslashes($두자리닉넴);
      db_query("UPDATE config SET `신입담당` = '{$닉_esc}' LIMIT 1");
      $msg = "📣 신입담당 [ {$두자리닉넴} ]\n"
        . "🎨 색 지정 전까지 조용~ 🤫";
      echo 전송($msg);
      exit;
    }

    if ($두자리닉넴 === '') {
      echo 전송("❌ 두글자 닉+성별 설정 후 사용해줘!");
      exit;
    }

    if ($status_trim === '.담당취소') {
      info1_ensure_config_sinip_damdang();
      $설정 = db_select("SELECT `신입담당` FROM config LIMIT 1");
      $신입담당 = trim((string)($설정['신입담당'] ?? ''));
      if ($신입담당 === '') {
        echo 전송("❌ 현재 신입 담당자가 없어요.");
        exit;
      }
      $is담당 = ($두자리닉넴 === $신입담당);
      $is관리자 = in_array($두자리닉넴, $관리자, true);
      if (!$is담당 && !$is관리자) {
        echo 전송("❌ .담당취소는 신입 담당자 [ {$신입담당} ] 또는 관리자만 사용할 수 있어요.");
        exit;
      }
      db_query("UPDATE config SET `신입담당` = '' LIMIT 1");
      if ($is관리자 && !$is담당) {
        echo 전송("✅ 관리자 [ {$두자리닉넴} ] 가 신입 담당 [ {$신입담당} ] 을 해제했어요.");
      } else {
        echo 전송("✅ [ {$두자리닉넴} ] 신입 담당이 해제됐어요.");
      }
      exit;
    }

    if (strpos($status, '.색변') !== false && strpos(trim((string)$status), '.색변환') !== 0) {
      info1_ensure_config_sinip_damdang();
      $설정 = db_select("SELECT `신입담당` FROM config LIMIT 1");
      $신입담당 = trim((string)($설정['신입담당'] ?? ''));

      if ($신입담당 === '') {
        echo 전송("❌ 아직 신입 담당자가 정해지지 않았어요.\n(담당자가 `내가받을께` 로 등록해야 해요)\n※ 신입이 색표에서 선점해도 담당 있으면 자동 지급돼요.");
        exit;
      }
      if ($두자리닉넴 !== $신입담당) {
        echo 전송("❌ .색변은 신입 담당자 [ {$신입담당} ] 만 사용할 수 있어요.\n※ 신입이 색표에서 선점하면 자동 지급·담당 해제돼요.");
        exit;
      }

      if (preg_match('/^\.색변\s+([가-힣]{2})\s+(\d+)/u', $status_trim, $색변매치)) {
        $대상닉 = $색변매치[1];
        $색번호 = (int)$색변매치[2];
        if ($색번호 < 1) {
          echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 5");
          exit;
        }

        if (!function_exists('신입색변_완료처리')) {
          echo 전송("❌ 색변 처리 함수를 불러오지 못했어요.");
          exit;
        }
        $결과 = 신입색변_완료처리($대상닉, $색번호, [
          'alarm' => false,
          'require_damdang' => true,
        ]);
        if (empty($결과['ok'])) {
          echo 전송("❌ " . ($결과['error'] ?? '색변에 실패했어요.'));
          exit;
        }
        echo 전송($결과['msg']);
        exit;
      }

      echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 5");
      exit;
    }
  }
}

// 신입 담당·색변 — 2글자닉 안내 exit(아래)보다 먼저 처리
$status_trim_early = trim((string)$status);
if (strpos($status_trim_early, '.색변환') === 0) {
  if (function_exists('색변환_명령_처리')) {
    색변환_명령_처리($status, $두자리닉넴, $관리자 ?? []);
  }
  echo 전송("❌ 사용법: .색변환 닉네임 번호\n예) .색변환 우서 15");
  exit;
}
if (
  info1_메시지_내가받을께인가($status)
  || $status_trim_early === '.담당취소'
  || preg_match('/^\.색변(?:\s|$)/u', $status_trim_early)
) {
  info1_handle_sinip_damdang_commands($status, $두자리닉넴, $관리자);
}

// 자동응답 예외 닉네임 (봇 등)
$자동응답제외 = array('오픈채팅봇');

// 한글 정확히 2글자가 아니고 예외 목록에도 없으면 자동응답 안내 전송
if (!preg_match('/^[가-힣]{2}$/u', (string)$nick_판별_한글) && !in_array($nick_판별, $자동응답제외, true) && !in_array(trim((string)$nick), $자동응답제외, true)) {
  global $conn;
  $닉_원본 = trim((string)$nick);

  if ($닉_원본 !== '' && isset($conn) && $conn) {
    // 환영 기록 테이블 보장 (최초 1회만 실제 생성)
    db_query("CREATE TABLE IF NOT EXISTS tb_welcome_sent (
      nick VARCHAR(191) NOT NULL PRIMARY KEY,
      sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4");

    $닉_esc = mysqli_real_escape_string($conn, $닉_원본);

    // 기존 기록 조회 + 경과 초 계산 (서버시간 기준으로 확실히 비교)
    $기록 = db_select("SELECT UNIX_TIMESTAMP(sent_at) AS ts, UNIX_TIMESTAMP(NOW()) AS now_ts
                       FROM tb_welcome_sent WHERE nick = '{$닉_esc}' LIMIT 1");

    $첫인사 = false;
    if (empty($기록['ts'])) {
      // 기록 없음 → 새로 INSERT
      db_query("INSERT INTO tb_welcome_sent (nick, sent_at) VALUES ('{$닉_esc}', NOW())");
      $첫인사 = true;
    } else {
      $경과초 = (int)$기록['now_ts'] - (int)$기록['ts'];
      if ($경과초 >= 60) {
        // 1분 이상 경과 → 갱신 후 재발송
        db_query("UPDATE tb_welcome_sent SET sent_at = NOW() WHERE nick = '{$닉_esc}'");
        $첫인사 = true;
      }
    }

    if ($첫인사 && strpos(trim((string)$status), '.') !== 0) {
      $welcome  = "{$nick}!! 어서와🎉\n\n";
      $welcome .= "두글자 닉 + 성별 형태로 
닉네임 변경부터 해줘!
방 하트도 부탁해!

순서대로 해보자";
      echo 전송($welcome);
      exit;
    }
  }

  // `.명령`은 닉 형식과 무관하게 처리 (_mutual.php)
  if (strpos($status, '.') !== 0) {
    exit;
  }
}
  

// echo 전송("테스트12345");
// exit;

// ─── 두글자닉 + 성별 형태 감지 → tb_member 자동 등록 ───────────────────────
(function() use ($nick, $두자리닉넴, $conn, $status) {
  if (empty($두자리닉넴)) return;

  // .퇴근 등 명령어 채팅은 신입 자동등록 대상에서 제외 (명령 처리보다 먼저 타면 등록만 되고 exit 됨)
  if (preg_match('/^\./u', trim((string)$status))) return;

  // 원본 닉에서 '남'/'여' 성별 추출 (예: "길동 남", "춘향 여", "남 길동", "여 춘향")
  $nick_trim = trim((string)$nick);
  $gender_str = '';
  if (preg_match('/\b(남|여)\b/u', $nick_trim, $gm)) {
    $gender_str = $gm[1];
  }

  if ($gender_str === '') return; // 성별 표기 없으면 처리 안 함

  if (!isset($conn) || !$conn) return;

  // tb_welcome_sent에 기록이 있으면 이미 방에 왔던 기존 멤버 → 처리 안 함
  $닉_esc_ws = mysqli_real_escape_string($conn, $nick_trim);
  $방문기록 = db_select("SELECT nick FROM tb_welcome_sent WHERE nick = '{$닉_esc_ws}' LIMIT 1");
  if (!empty($방문기록['nick'])) return;

  $name_esc = mysqli_real_escape_string($conn, $두자리닉넴);

  // tb_member에 같은 닉이 이미 있으면 (본인 포함) 재등록하지 않고 조용히 통과
  $존재 = db_select("SELECT num FROM tb_member WHERE name = '{$name_esc}' LIMIT 1");
  if (!empty($존재['num'])) {
    return;
  }

  // 미등록 → 자동 INSERT
  
  $gender_int = ($gender_str === '남') ? 1 : 2;
  $높번 = db_select("SELECT MAX(num) AS nmax FROM tb_member");
  $다음번호 = (int)($높번['nmax'] ?? 0) + 1;
  $신규코드 = 회원_6자리코드_생성();
  $신규코드_esc = mysqli_real_escape_string($conn, $신규코드);

  if (function_exists('색확정_컬럼보장')) {
    색확정_컬럼보장();
  }

  $sql = "INSERT INTO tb_member SET
    status = 0,
    code = '{$신규코드_esc}',
    couple = 2,
    `색확정` = 0,
    num = '{$다음번호}',
    gender = {$gender_int},
    name = '{$name_esc}',
    content = '',
    newpoint = 0.0,
    getto = 0,
    max_getto = 0,
    regdate = NOW()";
  $result = db_query($sql);

  if ($result) {
    $gender_label = $gender_str === '남' ? '남' : '여';
    echo 전송("🌸 신입 {$두자리닉넴} {$gender_label}!! 어서와 🎉\n등록이 완료됐어!");
    exit;
  }
})();
// ─────────────────────────────────────────────────────────────────────────────

// ─── 🌸 닉네임•키 프로필 입력 → tb_member.content 업데이트 (본방) ─────
if (function_exists('프로필_공질입력_처리') && 프로필_공질입력_처리($status, $두자리닉넴, $관리자 ?? [])) {
  exit;
}
// ─────────────────────────────────────────────────────────────────────────────

// `.금지어` — 사용 누적 랭킹 (금지단어 검출보다 먼저)
if (trim((string)$status) === '.금지어') {
  if (function_exists('금지단어_통계_랭킹문구')) {
    echo 전송(금지단어_통계_랭킹문구(15));
  } else {
    echo 전송('❌ 금지어 랭킹을 불러올 수 없어요.');
  }
  exit;
}

// 금지단어: 전체 게임냥 0.01% 차감 → 금고 적립 (부족 시 본방냥 10% 스왑 · 그래도 없으면 신불 마이너스)
// ※ 프로필(닉네임•키) 등록보다 아래에 둠 — 키(182) 등 등록 메시지는 위에서 exit 되어 여기 안 옴
$금지단어목록 = ['악귀', '악마', '18', '1 8', 'ㅅㅂ', 'ㅅㅅ', '218', '섹스', 'sex', '스엑스', '섹수', '쎅스', 'SEX', 'SES', 
'ㅗ', '꺼져', '병신', '시발', '씨발', '시팔', '십알', '십항'];

$금지패턴 = [];
foreach ($금지단어목록 as $금칙어) {
  $금칙어 = trim((string)$금칙어);
  if ($금칙어 === '') {
    continue;
  }
  $금지패턴[] = preg_quote($금칙어, '/');
}
if ($금지패턴 !== [] && preg_match('/' . implode('|', $금지패턴) . '/u', (string)$status, $금칙매치)) {
  $금칙단어 = (string)($금칙매치[0] ?? $금지단어목록[0]);
  $발화닉 = trim((string)$두자리닉넴);
  if ($발화닉 === '') {
    $발화닉 = trim((string)($정보['name'] ?? ''));
  }
  if (function_exists('금지단어_벌금처리')) {
    $결과 = 금지단어_벌금처리($발화닉, $금칙단어);
    echo 전송((string)($결과['msg'] ?? '금지단어 처리에 실패했어요.'));
  } else {
    $금칙표기 = function_exists('금지단어_표시용')
      ? 금지단어_표시용($금칙단어)
      : preg_replace('/(.)/u', '$1·', $금칙단어);
    echo 전송("금지 단어 ' {$금칙표기} ' 사용 — 벌금 처리 함수를 불러올 수 없어요.");
  }
  exit;
}

// 본방 .겜냥 — 게임냥(point) 지급/차감 (구 .이체 · 관리자 · 신불 마이너스 잔액 가감 지원)
if (preg_match('/\.(?:겜냥|이체)\s*(.*)$/u', $status, $겜냥매치)) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  $겜냥예 = "예) .겜냥 초롱 3000경 / .겜냥 덕선 9000경 / .겜냥 덕선 1해 / .겜냥 진우 -1조";
  $after = trim((string)($겜냥매치[1] ?? ''));
  if ($after === '' || !preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
    echo 전송("❌ 사용법: .겜냥 닉네임 금액\n{$겜냥예}");
    exit;
  }
  $받는닉 = trim($parts[1]);
  $지급양Str = function_exists('냥_금액_파싱_부호포함_문자열')
    ? 냥_금액_파싱_부호포함_문자열(trim($parts[2]))
    : (string)(function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : 0);
  if ($지급양Str === '0') {
    echo 전송("❌ 금액 형식을 확인해주세요.\n{$겜냥예}");
    exit;
  }
  if (!function_exists('게임냥_이체_적용')) {
    echo 전송("❌ 겜냥 기능을 불러오지 못했어요.");
    exit;
  }
  $결과 = 게임냥_이체_적용($받는닉, $지급양Str);
  if (empty($결과['ok'])) {
    $실패명 = trim((string)($결과['name'] ?? $받는닉));
    echo 전송("❌ {$실패명} 겜냥 실패\n" . (string)($결과['msg'] ?? '처리 실패') . "\n{$겜냥예}");
    exit;
  }
  $abs양 = (string)$결과['abs'];
  $입금여부 = ((int)$결과['sign'] >= 0);
  $금액표시 = function_exists('랭킹_게임냥표시')
    ? 랭킹_게임냥표시($abs양, '냥')
    : (function_exists('게임냥_경조억표시') ? 게임냥_경조억표시($abs양) : ($abs양 . '냥'));
  $잔액표시 = function_exists('랭킹_게임냥표시')
    ? 랭킹_게임냥표시($결과['after'] ?? '0', '냥')
    : (function_exists('게임냥_경조억표시') ? 게임냥_경조억표시($결과['after'] ?? '0') : ((string)($결과['after'] ?? '0') . '냥'));
  if (!$입금여부) {
    $지급여부 = '차감';
    $msg = "💰 {$결과['name']}에게 {$금액표시} 게임냥 차감!";
    $지급양로그 = '-' . $abs양;
  } else {
    $지급여부 = '이체';
    $msg = "💰 {$결과['name']}에게 {$금액표시} 게임냥 지급!";
    $지급양로그 = $abs양;
  }
  $msg .= "\n현재 보유: {$잔액표시}";
  지급로그($지급여부, $두자리닉넴, $결과['name'], 0, $지급양로그);
  echo 전송($msg);
  exit;
}

// 본방 .스왑 — 보유냥 전액 (10% 소멸 · 90% 게임냥) · 500냥 이상이면 조건 없이 전부
if (trim($status) === '.스왑') {
  if (!function_exists('스왑_본방_실행')) {
    require_once __DIR__ . '/game/swap.inc.php';
  }
  $스왑보유 = round((float)($정보['newpoint'] ?? 0), 1);
  $스왑최소 = function_exists('스왑_본방_최소보유냥') ? (float)스왑_본방_최소보유냥() : 500.0;
  if ($스왑보유 < $스왑최소) {
    exit;
  }
  스왑_본방_실행($두자리닉넴, null, true); // $간단출력=true → 무응답 · 전액 스왑
}

// 본방 .프변 / .프변 N — 프로필변경 사용 (1개=3일 · 홍보방과 동일)
if (preg_match('/^\.프변(?:\s+(\d+))?\s*$/u', trim($status), $프변m)) {
  $개수 = isset($프변m[1]) ? (int)$프변m[1] : 1;
  if ($개수 < 1) {
    echo 전송("❌ 사용 개수는 1 이상으로 입력해주세요.\n예) .프변 1 · .프변 5");
    exit;
  }
  if (!function_exists('프변_명령_처리')) {
    echo 전송('❌ 프변 기능을 불러올 수 없어요.');
    exit;
  }
  echo 전송(프변_명령_처리($두자리닉넴, $개수));
  exit;
}

// 본방 .환율 — 보유냥 스왑·원화 기준 게임냥 예상
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

// 본방 .본냥 — 보유냥(newpoint) 지급/차감 (구 .입금 · _mutual.php point 입금보다 먼저 처리)
if (preg_match('/\.(?:본냥|입금)\s*(.+)/u', $status, $본냥매치)) {
  // $관리자·$단위는 api/config.php 에서 로드 — _mutual.php include 전이라 여기서 먼저 불러옴
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  $after = trim($본냥매치[1]);
  $관리자여부 = in_array($두자리닉넴, $관리자, true)
    || in_array(getTwoCharNick($nick ?? ''), $관리자, true);
  if (!$관리자여부) {
    echo 전송($두자리닉넴 . " 블랙리스트 등록완료!");
    exit;
  }
  if (preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
    $받는닉 = trim($parts[1]);
    $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : (float)trim($parts[2]);
    if ($지급양 !== 0) {
    $받는닉_esc = addslashes($받는닉);
    if ($받는닉 === '전체') {
      if ($지급양 < 0) {
        $차감양 = abs($지급양);
        db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE status = 0");
      } else {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE status = 0");
      }
      $msg = "📣 전체 인원에게 " . newpoint표시($지급양) . "{$단위} 지급!";
      $받는친구명 = '전체';
      $지급여부 = $지급양 < 0 ? '차감' : '입금';
    } elseif ($받는닉 === '랜덤박스') {
      $지급개수 = (int)$지급양;
      if ($지급개수 < 1) {
        echo 전송("❌ 랜덤박스 개수는 1 이상으로 입력해줘.");
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
      $msg = "📣 전체 인원에게 랜덤박스 {$지급개수}개씩 본냥 지급 완료!";
      $받는친구명 = '전체';
      $지급여부 = '입금';
      $지급양 = $지급개수;
    } else {
      $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
      if (empty($받는친구['idx'])) {
        echo 전송("존재하지 않는 사용자 입니다.");
        exit;
      }
      if ($지급양 < 0) {
        $차감양 = abs($지급양);
        db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE name = '{$받는닉_esc}'");
        $지급여부 = '차감';
        $msg = "💰 {$받는친구['name']}에게 " . newpoint표시($차감양) . "{$단위} 차감!";
      } else {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE name = '{$받는닉_esc}'");
        $지급여부 = '입금';
        $msg = "💰 {$받는친구['name']}에게 " . newpoint표시($지급양) . "{$단위} 본냥 지급!";
      }
      $받는친구명 = $받는친구['name'];
    }
    지급로그($지급여부, $두자리닉넴, $받는친구명, 0, $지급양);
    echo 전송($msg);
    exit;
    }
  }

  // 일괄: .본냥 닉1 닉2 ... 닉N 금액 (줄바꿈·여러 칸 공백 허용, 마지막 토큰이 금액)
  $일괄문자열 = trim(preg_replace('/\s+/u', ' ', $after));
  $일괄토큰 = $일괄문자열 !== '' ? preg_split('/\s+/u', $일괄문자열) : [];
  if (count($일괄토큰) >= 2) {
    $마지막토큰 = (string)end($일괄토큰);
    $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함($마지막토큰) : 0;
    if ($지급양 !== 0) {
      array_pop($일괄토큰);
      $닉목록 = array_values(array_filter($일괄토큰, static function ($t) {
        return trim((string)$t) !== '';
      }));
      if (count($닉목록) < 1) {
        echo 전송("❌ 일괄 본냥 지급할 닉네임을 입력해주세요.");
        exit;
      }

      $성공목록 = [];
      $실패목록 = [];
      $중복스킵 = [];
      $이미처리 = [];
      $지급여부 = $지급양 < 0 ? '차감' : '입금';

      foreach ($닉목록 as $받는닉) {
        $받는닉 = trim((string)$받는닉);
        if ($받는닉 === '') {
          continue;
        }
        if (isset($이미처리[$받는닉])) {
          $중복스킵[] = $받는닉;
          continue;
        }
        $이미처리[$받는닉] = true;

        if ($받는닉 === '전체' || $받는닉 === '랜덤박스') {
          $실패목록[] = $받는닉 . '(일괄 불가)';
          continue;
        }

        $받는닉_esc = addslashes($받는닉);
        $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
        if (empty($받는친구['idx'])) {
          $실패목록[] = $받는닉;
          continue;
        }

        if ($지급양 < 0) {
          $차감양 = abs($지급양);
          db_query("UPDATE tb_member SET newpoint = newpoint - {$차감양} WHERE name = '{$받는닉_esc}'");
        } else {
          db_query("UPDATE tb_member SET newpoint = newpoint + {$지급양} WHERE name = '{$받는닉_esc}'");
        }
        지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양);
        $성공목록[] = $받는친구['name'];
      }

      if (count($성공목록) < 1) {
        $msg = "❌ 일괄 본냥 {$지급여부} 실패 — 처리된 인원이 없어요.";
        if (count($실패목록) > 0) {
          $msg .= "\n없는 닉: " . implode(', ', $실패목록);
        }
        echo 전송($msg);
        exit;
      }

      $msg = "💰 일괄 본냥 {$지급여부} 완료 (" . count($성공목록) . "명 × " . newpoint표시(abs($지급양)) . "{$단위})\n";
      $msg .= implode(', ', $성공목록);
      if (count($실패목록) > 0) {
        $msg .= "\n\n❌ 없는 닉: " . implode(', ', $실패목록);
      }
      if (count($중복스킵) > 0) {
        $msg .= "\n⚠️ 중복 제외: " . implode(', ', $중복스킵);
      }
      echo 전송($msg);
      exit;
    }
  }

  echo 전송("❌ 사용법: .본냥 닉네임 금액\n예) .본냥 명수 50 / .본냥 뮤뮤 26경8천조\n\n일괄 예)\n.본냥 여름 나비 스리 50000\n(닉 여러 개 + 마지막 금액, 줄바꿈 가능)");
  exit;
}

// 본방 .게임냥삭감 — 전원 point 뒤 N자리 일괄 삭제 (관리자 · 마이너스 포함)
if (preg_match('/^\.게임냥삭감\b/u', trim((string)$status))) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/sakgam.inc.php';
  게임냥삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 .본방냥삭감 — 전원 newpoint 뒤 N자리 일괄 삭제 (+채굴적립·마켓선매입가 · 관리자)
if (preg_match('/^\.본방냥삭감\b/u', trim((string)$status))) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/sakgam.inc.php';
  본방냥삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 .채굴냥삭감 — 채굴기 적립만 뒤 N자리 삭제 (본방냥 미변경 · 관리자)
if (preg_match('/^\.채굴냥삭감\b/u', trim((string)$status))) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/sakgam.inc.php';
  채굴냥삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 .선매입삭감 — 마켓 선매입가만 뒤 N자리 삭제 (본방냥 미변경 · 관리자)
if (preg_match('/^\.선매입삭감\b/u', trim((string)$status))) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/sakgam.inc.php';
  선매입삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 .삭감 — 게임냥(point) N% 삭감 (관리자)
if (strpos($status, '.삭감') !== false) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  require_once __DIR__ . '/game/sakgam.inc.php';
  삭감_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방 .생성 — 아이템 생성 (관리자 전용)
if (strpos($status, '.생성') !== false) {
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (preg_match('/\.생성\s+(\S+)\s+(\S+)\s+(-?\d+)/u', $status, $생성m)) {
    $생성결과 = 아이템_생성_지급($생성m[1], $생성m[2], $생성m[3]);
    echo 전송($생성결과['msg']);
    exit;
  }
  echo 전송("❌ 사용법: .생성 닉네임 템명 개수\n예) .생성 진우 수호 5\n예) .생성 가이 색변 -12 (회수)\n예) .생성 아영 은총조각 10\n예) .생성 전체 1주년기념주화 1");
  exit;
}

if (function_exists('아이템사용처리_명령_처리')) {
  아이템사용처리_명령_처리($status, $두자리닉넴, $nick ?? '', $관리자 ?? []);
}

// 본방(info1) 전용: `.주문` — 도하 마켓 판매중 목록
if (trim($status) === '.주문') {
  require_once dirname(__DIR__) . '/shop/_shop.php';
  echo 전송(shop_채팅_주문목록_문구());
  exit;
}

// 본방(info1) 전용: `.마켓큰손` — 마켓 등록 권면가 합계 상위
if (preg_match('/^\.마켓큰손(?:\s+(\d+))?\s*$/u', trim($status), $마켓큰손m)) {
  require_once dirname(__DIR__) . '/shop/_shop.php';
  $상위 = (isset($마켓큰손m[1]) && $마켓큰손m[1] !== '') ? (int)$마켓큰손m[1] : 10;
  echo 전송(shop_채팅_마켓큰손_문구($상위));
  exit;
}

// 본방(info1): `.마켓수수료` — config.선매입적립 누적
if (preg_match('/^\.마켓수수료\s*$/u', trim($status))) {
  require_once dirname(__DIR__) . '/shop/_shop.php';
  echo 전송(shop_채팅_마켓수수료_문구());
  exit;
}

// 본방(info1): `.마켓수령` — 도하만 선매입적립 수령
if (preg_match('/^\.마켓수령\s*$/u', trim($status))) {
  require_once dirname(__DIR__) . '/shop/_shop.php';
  $결과 = shop_채팅_마켓수령_실행($두자리닉넴);
  echo 전송((string)($결과['msg'] ?? '마켓 수수료 수령에 실패했어요.'));
  exit;
}

// 본방(info1): 로또 구매는 홍보방만 · `.로또` 조회 · 추첨/지급(관리자)은 본방
$MUTUAL_SKIP_LOTTO = true;

// 본방(info1)에서는 홀짝(.도전/ㅈㅈ) 미적용 — 홍보방(info2) 등에서만 odd_even 루틴 로드
$MUTUAL_SKIP_ODD_EVEN = true;
$MUTUAL_SKIP_RANKING1 = true;
$MUTUAL_SKIP_RANKING2 = true;
$MUTUAL_SKIP_RANKING3 = true;
$MUTUAL_SKIP_GONGKEO_REG = true;

// _mutual·본방 명령 공통 — include 직전 회원정보 재동기화
list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
if ($두자리닉넴 === '' && trim((string)($정보['name'] ?? '')) !== '') {
  $두자리닉넴 = trim((string)$정보['name']);
}
include_once __DIR__ . "/_mutual.php";

if($두자리닉넴){
  //echo 전송($status);
  //exit;

  if (trim($status) === '.수령') {
    echo 전송("❌ `.수령`은 홍보방에서만 이용할 수 있어요.");
    exit;
  }

  if (!function_exists('info1_cho_quiz_publish_next')) {
    /**
     * 초성 퀴즈 1문제 출제(GPT → INSERT). GET_LOCK/진행중 검사 포함.
     * @return array{ok:bool, text:string}
     */
    function info1_cho_quiz_publish_next($단위) {
      $락행 = db_select("SELECT GET_LOCK('cho_quiz_lock', 0) AS ok");
      if (empty($락행['ok'])) {
        return ['ok' => false, 'text' => "❗ 다른 초성 퀴즈 요청이 처리 중이에요.\n잠시 후 다시 시도해줘!"];
      }

      $진행중퀴즈 = db_select("SELECT idx FROM tb_question WHERE status = 0 LIMIT 1");
      if (!empty($진행중퀴즈['idx'])) {
        db_query("SELECT RELEASE_LOCK('cho_quiz_lock')");
        return ['ok' => false, 'text' => "❗ 이미 진행 중인 초성 퀴즈가 있어요!\n먼저 기존 문제를 마무리해줘!"];
      }

      $테마이름 = '국어사전 단어';

      $난이도_롤 = rand(1, 100);
      if      ($난이도_롤 <= 30) { $목표난이도 = 1; }
      else if ($난이도_롤 <= 55) { $목표난이도 = 2; }
      else if ($난이도_롤 <= 75) { $목표난이도 = 3; }
      else if ($난이도_롤 <= 90) { $목표난이도 = 4; }
      else                       { $목표난이도 = 5; }

      $난이도_포인트 = function($lv) {
        switch ($lv) {
          case 1: return rand(20, 40)   * 5000;
          case 2: return rand(40, 100)  * 5000;
          case 3: return rand(100, 200) * 5000;
          case 4: return rand(400, 800) * 5000;
          case 5: return rand(1800, 2000) * 5000;
          default: return rand(20, 40)  * 5000;
        }
      };

      $난이도정의 = [
        1 => "난이도 1 (매우 쉬움): 유치원~초등 저학년도 아는 일상 명사/동사. 예) 사과, 학교, 하늘, 고양이, 버스",
        2 => "난이도 2 (쉬움): 일상에서 자주 쓰지만 살짝 덜 흔한 단어. 예) 우산꽂이, 체온계, 미끄럼틀, 환승, 배달원",
        3 => "난이도 3 (보통): 조금 생각이 필요한 중급 어휘. 살짝 한자어 섞여도 됨. 예) 서랍장, 광장, 자립심, 설렘, 도전장",
        4 => "난이도 4 (어려움): 덜 흔한 단어, 전문 용어 입문, 4자 이하 관용구. 예) 덕목, 간헐, 자승자박 수준은 피하고 적당히 어려운 어휘",
        5 => "난이도 5 (매우 어려움): 고급 어휘/한자어/전문용어, 사자성어, 속담. 예) 일사천리, 각골난망, 등잔밑이어둡다, 외유내강",
      ];

      $정답단어 = "";
      $hint     = "";
      $최대시도 = 3;
      $후보개수 = 14;
      $분야풀 = ['자연·날씨', '음식·조리', '도구·용품', '감정·심리', '사회·제도', '신체·건강', '예술·문화', '운동·여가', '과학·기술', '공간·장소', '직업·활동', '식물·동물'];
      $분야힌트 = $분야풀[array_rand($분야풀)];
      $answer   = '';

      for ($시도 = 0; $시도 < $최대시도; $시도++) {
        $gptPrompt = "한국어 국어사전·일상에서 쓰는 단어 후보 {$후보개수}개를 난이도에 맞춰 골라줘.\n"
                   . "이번에 요청하는 난이도는 [★".$목표난이도."/5] 이야.\n"
                   . "난이도 정의:\n"
                   . $난이도정의[$목표난이도]."\n"
                   . "후보 {$후보개수}개 모두 위 난이도 수준에 정확히 맞춰서 뽑아줘. "
                   . "난이도가 낮은데 일부러 어려운 단어를 쓰거나, 난이도가 높은데 유치원 수준 단어를 쓰면 안 돼. "
                   . "각 후보는 한 단어 또는 짧은 구(두세 단어)까지 가능. "
                   . "후보들은 주제가 비슷하게 몰리지 않게 골고루 섞어줘 (명사·동작·추상·사물 등). "
                   . "초성 퀴즈에 자주 나오는 진부한 예시나 뻔한 대표 단어는 피하고, 같은 난이도라도 덜 낯익은 표현을 우선해줘. "
                   . "이번 회차에서는 '{$분야힌트}' 쪽 어휘를 2~4개 정도 포함하고, 나머지는 다른 영역으로 고르게 배분해줘.\n";
        $gptPrompt .= "반드시 아래 JSON 형식으로만 대답해. "
                   . "{\"items\":[{\"title\":\"단어\",\"hint\":\"힌트한줄\"}]} "
                   . "설명이나 다른 문장, 줄바꿈은 절대 넣지 마. "
                   . "items는 정확히 {$후보개수}개를 넣고, title은 서로 겹치지 않게 해줘. "
                   . "hint는 정답을 바로 알 수 없게 1줄로 짧게 써줘.";

        $answer = callGPT($gptPrompt, '지시한 형식의 JSON만 출력해. 다른 말은 하지 마.', 1200);
        if ($answer === false || $answer === '') {
          continue;
        }
        $answer = trim((string) $answer);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/su', $answer, $fence)) {
          $answer = trim($fence[1]);
        }
        $quizData = json_decode($answer, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($quizData)) {
          continue;
        }
        $후보목록 = [];
        if (!empty($quizData['items']) && is_array($quizData['items'])) {
          $후보목록 = $quizData['items'];
        } else if (!empty($quizData['title'])) {
          $후보목록[] = ['title' => $quizData['title'], 'hint' => ($quizData['hint'] ?? '')];
        }

        shuffle($후보목록);

        foreach ($후보목록 as $cand) {
          $후보 = trim($cand['title'] ?? '');
          $candHint = trim($cand['hint'] ?? '');
          if ($후보 === '') {
            continue;
          }
          $정답단어 = $후보;
          $hint = $candHint;
          break 2;
        }
      }

      $msg = '';
      if ($정답단어 === '' && $시도 >= $최대시도) {
        db_query("SELECT RELEASE_LOCK('cho_quiz_lock')");
        return ['ok' => false, 'text' => "❌ 새 문제를 만들지 못했어요. 잠시 후 다시 시도해줘!"];
      }
      if ($정답단어 !== '') {
        $재확인퀴즈 = db_select("SELECT idx FROM tb_question WHERE status = 0 LIMIT 1");
        if (!empty($재확인퀴즈['idx'])) {
          db_query("SELECT RELEASE_LOCK('cho_quiz_lock')");
          return ['ok' => false, 'text' => "❗ 이미 진행 중인 초성 퀴즈가 있어요!\n먼저 기존 문제를 마무리해줘!"];
        }

        $question = toChosung($정답단어);
        $correct  = $정답단어;
        $랜덤포인트 = $난이도_포인트($목표난이도);

        $q_esc = addslashes($question);
        $a_esc = addslashes($correct);
        $h_db  = addslashes($hint);
        db_query("INSERT INTO tb_question SET status = 0, question = '{$q_esc}', answer = '{$a_esc}', hint = '{$h_db}', point = {$랜덤포인트}, regdate = NOW()");
        db_query("UPDATE config SET `초성자동연속` = 1 LIMIT 1");

        $별표시 = str_repeat('⭐', $목표난이도) . str_repeat('☆', 5 - $목표난이도);

        $msg .= "[초성 퀴즈] ({$테마이름})\n";
        $msg .= "난이도: {$별표시} ({$목표난이도}/5)\n\n";
        $msg .= "문제: {$question}\n";
        if ($hint !== '') {
          $h_esc = htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          $msg .= "힌트: {$h_esc}\n";
        }

        $표시포인트 = number_format($랜덤포인트);
        $msg .= "포인트: {$표시포인트}{$단위}\n\n";

        $msg .= "정답은 `ㅁ{단어}` 또는 `.정답 단어` 로 보내줘😉 (틀리면 50만냥 차감)\n\n #초성퀴즈";
      } else {
        if ($시도 < $최대시도) {
          $msg .= "\n".$answer."\n\n ";
        }
        db_query("SELECT RELEASE_LOCK('cho_quiz_lock')");
        return ['ok' => false, 'text' => trim($msg) !== '' ? trim($msg) : "❌ 새 문제를 만들지 못했어요. 잠시 후 다시 시도해줘!"];
      }

      db_query("SELECT RELEASE_LOCK('cho_quiz_lock')");
      return ['ok' => true, 'text' => $msg];
    }
  }

  if (!function_exists('info1_cho_quiz_process_answer_submit')) {
    /** 초성 정답/오답 처리 후 echo·exit */
    function info1_cho_quiz_process_answer_submit($두자리닉넴, $단위, $사용자정답, $현재문제 = null) {
      if ($현재문제 === null) {
        $현재문제 = db_select("SELECT idx, answer, point FROM tb_question WHERE status = 0 LIMIT 1");
      }
      if (empty($현재문제['idx'])) {
        echo 전송("❌ 진행 중인 초성 퀴즈가 없어요!");
        exit;
      }

      $정답 = trim($현재문제['answer']);
      $포인트 = (int)$현재문제['point'];
      $문제idx = (int)$현재문제['idx'];

      $비교입력 = preg_replace('/\s+/u', ' ', trim($사용자정답));
      $비교정답 = preg_replace('/\s+/u', ' ', $정답);
      if ($비교입력 !== $비교정답) {
        $닉_esc = addslashes($두자리닉넴);
        $차감액 = 500000;
        $새포인트 = $포인트 + $차감액;
        $새포인트표시 = number_format($새포인트);
        db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
        db_query("UPDATE tb_question SET point = point + {$차감액} WHERE idx = {$문제idx}");
        $차감표시 = number_format($차감액);
        echo 전송("{$두자리닉넴} ❌틀렸어요! +{$차감표시}냥 누적 (총 {$새포인트표시}냥)");
        exit;
      }

      $닉_esc = addslashes($두자리닉넴);
      db_query("UPDATE tb_question SET nick = '{$닉_esc}', status = 1 WHERE idx = {$문제idx}");
      db_query("UPDATE tb_member SET point = point + {$포인트} WHERE name = '{$닉_esc}'");

      $표시포인트 = number_format($포인트) . $단위;
      $축하 = "🎉 정답! [ {$두자리닉넴} ] {$표시포인트} 지급!";
      $자동행 = db_select("SELECT COALESCE(`초성자동연속`, 0) AS v FROM config LIMIT 1");
      if ((int)($자동행['v'] ?? 0) === 1) {
        $nx = info1_cho_quiz_publish_next($단위);
        if (!empty($nx['ok'])) {
          echo 전송($축하 . "\n\n──────────\n\n🔄 자동 연속 · 다음 문제\n\n" . $nx['text']);
        } else {
          echo 전송($축하 . "\n\n" . $nx['text']);
        }
      } else {
        echo 전송($축하);
      }
      exit;
    }
  }

  if (preg_match('/^\.벌점\s+(\S+)\s+(-?\d+)/u', trim($status), $m)) {
    $대상닉 = $m[1];
    $점수 = (int)$m[2];
    $대상닉_2자 = getTwoCharNick($대상닉);

    if ($대상닉_2자 === '') {
      echo 전송("❌ 닉네임을 확인해주세요.");
      exit;
    }

    $msg = "{$대상닉_2자}에게 {$점수}점을 부여합니다";
    echo 전송($msg);
    exit;

  } else if (preg_match('/^\.홀짝신불단축\s+(\S+)/u', trim($status), $_홀짝신불단축m)) {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있습니다.");
      exit;
    }
    $홀짝대상 = getTwoCharNick($_홀짝신불단축m[1]);
    if ($홀짝대상 === '') {
      $홀짝대상 = preg_replace('/\s+/u', '', (string)$_홀짝신불단축m[1]);
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

  }else if(preg_match('/^\.(게임종류|게임목록)(?:\s+(.+))?$/u', trim($status), $_게임종류_m)){
    $_게임_sub = isset($_게임종류_m[2]) ? trim($_게임종류_m[2]) : '';
    $_게임_sub_n = preg_replace('/\s+/u', '', $_게임_sub);
    $_게임_pick = null;
    if ($_게임_sub_n !== '') {
      if (preg_match('/^([1-2])번?$/u', $_게임_sub_n, $_gn)) {
        $_게임_pick = (int)$_gn[1];
      } else {
        $_게임_alias = array(
          '초성' => 1,
          '로또' => 2,
        );
        $_게임_pick = isset($_게임_alias[$_게임_sub_n]) ? $_게임_alias[$_게임_sub_n] : false;
      }
    }

    $_g1 = "1️⃣ 초성 (🔤 초성 맞추기 퀴즈)\n";
    $_g1 .= "• 명령어: `.초성` 출제 → `ㅁ{정답}`·`.정답 정답` (틀리면 50만냥 차감·상금 누적)\n";
    $_g1 .= "• 제시된 초성에 해당하는 단어를 가장 먼저 맞추면 당첨\n";
    $_g1 .= "• 정답자에게 문제에 걸린 포인트 지급 · 맞추면 자동으로 다음 문제가 이어서 나옴\n";
    $_g1 .= "• `.초성초기화` 로 자동 연속 중단 후 다시 `.초성` 으로 시작\n";
    $_g1 .= "• 진행 중인 문제가 있으면 새 문제 출제 불가\n\n";

    $_g2 = "2️⃣ 로또 (🎟️ 1~45 번호 뽑기 · 매일 자정 추첨)\n";
    $_g2 .= "• 본방: `.로또` (예상 당첨금·총액 조회)\n";
    $_g2 .= "• 홍보방: `.로또 구매 1~100` · `.로또 구매 전부` · `.로또 1,22,33` · `.로또 내역`\n";
    $_g2 .= "• 1~45 중 중복 없이 3개 번호 · 게임냥 구매 불가 (레벨업 티켓만)\n";
    $_g2 .= "• 🎟️ 로또 티켓: 레벨업 시 구간별 15~300장 (`.레벨업` 참고)\n";
    $_g2 .= "• 예상 당첨금/총액: `.로또` · 내 구매내역: `.로또 내역` · 안내: `.로또란`\n";
    $_g2 .= "• (`.로또 추첨` · `.로또 지급` · `.로또당첨 닉` 은 관리자 · 본방·홍보방)\n\n";

    if ($_게임_pick === false) {
      echo 전송("❓ 게임을 찾을 수 없어요. `.게임종류` 로 전체를 보거나, 초성·로또(1~2) 중 하나를 붙여 주세요.");
      exit;
    }

    if ($_게임_pick !== null) {
      $_titles = array(1=>'초성', 2=>'로또');
      $_blocks = array(1=>$_g1, 2=>$_g2);
      $msg = "🎮 {$_titles[$_게임_pick]} 안내\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";
      $msg .= "─────────────\n\n";
      $msg .= rtrim($_blocks[$_게임_pick]);
      echo 전송($msg);
      exit;
    }

    $msg = "🎮 게임 종류 안내\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";
    $msg .= "─────────────\n\n";
    $msg .= $_g1 . $_g2;
    $msg .= "─────────────\n";
    $msg .= "🎁 기타\n";
    $msg .= "• `.ㄹㄷ 10` 랜덤박스 일괄 까기 (10개 이상)";

    echo 전송($msg);
    exit;

  }else if($status==".공질"){
  $msg = "🌸 닉네임•키 :
🌸 지역(시•군) :
🌸 프리(낮프•밤프•올프) :
🌸 기•미•돌 :
🌸 MBTI :
🌸 썸 이상형 :
🌸 나의 매력 :";
    echo 전송($msg);
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
        echo 전송("{$스타일문구}{$내템} {$타이틀} {$두자리닉넴} 등장!");
      } else {
        echo 전송("{$스타일문구}{$내템} {$두자리닉넴} 등장!");
      }
      exit;
    }
    // 타이틀만 있으면 타이틀만
    if ($타이틀 !== '') {
      echo 전송($타이틀." {$두자리닉넴} 등장!");
      exit;
    }
    exit; // 5강 무기·타이틀 둘 다 없으면 아무것도 안 나옴

  }else if (strpos(trim($status), '.강제출석') === 0) {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $parts = preg_split('/\s+/u', trim($status), 2);
    $대상닉 = isset($parts[1]) ? trim($parts[1]) : '';
    $결과 = 강제출석_적용($대상닉);
    echo 전송($결과['msg']);
    exit;

  }else if(preg_match('/^\.출석포기(?:\s+(.+))?$/u', trim($status), $출석포기m)){
    $출석포기사유 = isset($출석포기m[1]) ? trim($출석포기m[1]) : '';
    if ($출석포기사유 === '') {
      echo 전송("❌ 사용법: .출석포기 (사유)\n예) .출석포기 오늘은 쉴래요");
      exit;
    }
    $결과 = 출석포기_적용($두자리닉넴, $출석포기사유);
    echo 전송($결과['msg']);
    exit;

  }else if (trim($status) === '.투표종료') {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $결과 = 투표_종료($두자리닉넴);
    echo 전송($결과['msg']);
    exit;

  }else if (trim($status) === '.타수이벤시작') {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    info1_ensure_config_타수이벤();
    db_query("UPDATE config SET `타수이벤시작` = NOW() LIMIT 1");
    $행 = db_select("SELECT `타수이벤시작` FROM config LIMIT 1");
    $시각 = !empty($행['타수이벤시작']) ? $행['타수이벤시작'] : date('Y-m-d H:i:s');
    echo 전송("🏁 타수 이벤트 시작\n시각: {$시각}");
    exit;

  }else if (trim($status) === '.타수이벤종료') {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    info1_ensure_config_타수이벤();
    $설정 = db_select("SELECT `타수이벤시작` FROM config LIMIT 1");
    $시작 = trim((string)($설정['타수이벤시작'] ?? ''));
    if ($시작 === '' || $시작 === '0000-00-00 00:00:00') {
      echo 전송("❌ 타수 이벤트 시작 시각이 없어요.\n먼저 `.타수이벤시작` 을 해줘!");
      exit;
    }
    db_query("UPDATE config SET `타수이벤종료` = NOW() LIMIT 1");
    $행 = db_select("SELECT `타수이벤시작`, `타수이벤종료` FROM config LIMIT 1");
    $시작 = trim((string)($행['타수이벤시작'] ?? $시작));
    $종료 = trim((string)($행['타수이벤종료'] ?? ''));
    if ($종료 === '') {
      $종료 = date('Y-m-d H:i:s');
    }
    $시작_esc = addslashes($시작);
    $종료_esc = addslashes($종료);
    $생타식 = function_exists('생타_SQL_select_expr')
      ? 생타_SQL_select_expr('msg')
      : "COUNT(*)";
    $sql = "
      SELECT
        nickname AS 닉네임,
        {$생타식} AS 생타
      FROM tb_msg
      WHERE tasu != 0
        AND regdate >= '{$시작_esc}'
        AND regdate <= '{$종료_esc}'
        AND nickname NOT IN ('오픈', '')
      GROUP BY nickname
      HAVING 생타 > 0
      ORDER BY 생타 DESC, nickname ASC
    ";
    $rows = [];
    $rs = db_query($sql);
    if ($rs) {
      while ($row = db_fetch($rs)) {
        if (!empty($row['닉네임'])) {
          $rows[] = $row;
        }
      }
    }
    $msg = "🏁 타수 이벤트 종료\n";
    $msg .= "구간: {$시작} ~ {$종료}\n";
    $msg .= "(생타=일반채팅 · 사진 제외)\n\n";
    if (empty($rows)) {
      $msg .= "집계할 생타 기록이 없어요.";
    } else {
      foreach ($rows as $i => $r) {
        $msg .= ($i + 1) . "등 {$r['닉네임']} " . (int)$r['생타'] . "타\n";
      }
      $msg .= "\n참여 " . count($rows) . "명";
    }
    echo 전송($msg);
    exit;

  }else if (trim($status) === '.투표') {
    echo 전송(투표_현황_문구());
    exit;

  }else if (preg_match('/^\.투표\s+(.+)/u', trim($status), $투표m)) {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $결과 = 투표_시작(trim($투표m[1]), $두자리닉넴);
    echo 전송($결과['msg']);
    exit;

  }else if (trim($status) === '.찬성') {
    $결과 = 투표_표_기록($두자리닉넴, 1);
    echo 전송($결과['msg']);
    exit;

  }else if (trim($status) === '.반대') {
    $결과 = 투표_표_기록($두자리닉넴, 0);
    echo 전송($결과['msg']);
    exit;

  }else if($status==".연두"){
    관리자전용_확인($두자리닉넴, $관리자);

    $sql = "SELECT count(*) as cnt FROM tb_member AS m
    LEFT JOIN tb_attendance AS a
    ON m.name = a.nickname AND a.regdate = CURDATE()
    WHERE a.nickname IS NULL and m.status = 0
    AND IFNULL(m.attendance, 0) != 2
    AND m.regdate <= DATE_SUB(NOW(), INTERVAL 5 DAY)";
    $출석자 = db_select($sql);
    
    if($출석자['cnt']>0){
      $sql = "SELECT m.*
      FROM tb_member AS m
      LEFT JOIN tb_attendance AS a
        ON m.name = a.nickname
        AND a.regdate = CURDATE()
      WHERE a.nickname IS NULL and m.status = 0
      AND IFNULL(m.attendance, 0) != 2
      AND m.regdate <= DATE_SUB(NOW(), INTERVAL 5 DAY)";

      $result = db_query($sql);

      $html = "✅ 연락두절 {$오늘}\n\n";
      while ($row = db_fetch($result)) {
        $html .= $row['name'] . " ";
      }

      $html.= "\n\n100타 달성 시 자동 출석 됩니다!\n";
      $html.= "\n‼️전원 출석시\n".newpoint표시($설정['누적출석냥'])."{$단위} 전체 지급";
      $html = trim($html);
      echo 전송($html);
      exit;

    }else{

      $오늘지급여부 = db_select("SELECT COUNT(*) AS cnt FROM tb_point_log WHERE status = CONCAT('전체출석|', CURDATE())");
      if($오늘지급여부['cnt']==0){
        $sql = "SELECT m.*
        FROM tb_member AS m
        LEFT JOIN tb_attendance AS a
          ON m.name = a.nickname
          AND a.regdate = CURDATE()
        WHERE a.nickname IS not NULL and m.status = 0";

        $result = db_query($sql);

        while ($row = db_fetch($result)) {
          지급로그('전체출석', $row['name'], '', 0, $설정['누적출석냥']);
        }

        지급로그('전체출석|'.$오늘, "전체", '', 0, $설정['누적출석냥']);
        db_query("update tb_member set newpoint = newpoint + {$설정['누적출석냥']}");

        $html= "\n\n전원 출석🎉 ".newpoint표시($설정['누적출석냥'])."냥 지급완료!";

        db_query("update config set 지급확인 = 1, 누적출석{$단위} = 0 LIMIT 1");

        $설정 = db_select("select * from config ");
        $html.= "\n누적 출석냥 초기화됨 · 이후 일출석 시부터 다시 쌓입니다";

        $html = trim($html);
        echo 전송($html);
        exit;
      }else{
        echo 전송("{$오늘} 전체출석냥 지급완료🎉");
        exit;
      }
    }

  }else if($status==".인원" || $status==".솔로"){
    echo 전송(인원목록_메시지($status === '.솔로'));
    exit;

  }else if(trim($status) === '.고인물'){
    // status 0=정상, 3=퇴근 — 퇴근자 포함 전체 인원
    $result = db_query("
      SELECT name, regdate, IFNULL(status, 0) AS status
      FROM tb_member
      WHERE IFNULL(status, 0) IN (0, 3)
      ORDER BY regdate ASC, idx ASC
    ");
    $lines = [];
    $n = 0;
    while ($row = db_fetch($result)) {
      $n++;
      $일수 = !empty($row['regdate']) ? (int)나의입방일($row['regdate']) : 0;
      $퇴근표 = ((int)($row['status'] ?? 0) === 3) ? ' 🚌' : '';
      $lines[] = $n . '. ' . $row['name'] . $퇴근표 . ' (' . $일수 . '일)';
    }
    if ($n === 0) {
      echo 전송('✅ 고인물 목록이 없어요.');
      exit;
    }
    // .랭킹2와 동일 — 헤더 뒤 공백 패딩으로 카톡 전체보기 유도
    $msg = "✅ 고인물 · 입장순 (총 {$n}명)\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                " . implode("\n", $lines);
    echo 전송($msg);
    exit;

  }else if (preg_match('/^\.방문자기록(?:\s+(\S+))?\s*$/u', trim($status), $방문m)) {
    if (!in_array($두자리닉넴, (array)($관리자 ?? []), true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $대상닉 = trim((string)($방문m[1] ?? ''));
    if ($대상닉 === '') {
      echo 전송("❌ 사용법: .방문자기록 닉네임\n예) .방문자기록 라떼");
      exit;
    }
    $대상 = function_exists('getTwoCharNick') ? getTwoCharNick($대상닉) : $대상닉;
    if ($대상 === '') {
      $대상 = $대상닉;
    }
    $대상_esc = addslashes($대상);
    $rs = db_query("
      SELECT idx, name, content, regdate
      FROM tb_member_profile
      WHERE name = '{$대상_esc}'
      ORDER BY regdate DESC, idx DESC
    ");
    $lines = [];
    $n = 0;
    while ($rs && ($row = db_fetch($rs))) {
      $n++;
      $언제 = !empty($row['regdate']) ? date('Y-m-d H:i', strtotime((string)$row['regdate'])) : '—';
      $본문 = trim((string)($row['content'] ?? ''));
      if ($본문 === '') {
        $본문 = '(내용 없음)';
      }
      $lines[] = "{$n}. [{$언제}]\n{$본문}";
    }
    if ($n === 0) {
      echo 전송("❌ {$대상} 프로필 기록이 없어요.");
      exit;
    }
    $msg = "📜 {$대상} 방문자기록 (총 {$n}건 · 최근순)\n\n" . implode("\n\n", $lines);
    echo 전송($msg);
    exit;

  }else if($status==".버프"){
    require_once __DIR__ . '/buff.inc.php';
    버프_명령_처리($status, $두자리닉넴, (array)($관리자 ?? []), ['마법라벨' => '마법(타수2배)']);

  }else if (strpos($status, '.양도') !== false) {
    echo 전송('❌ 본냥 양도는 가방에서만 가능합니다');
    exit;

  }else if (preg_match('/^\.(주가|주식|매수|매도|구매)(\s|$)/u', trim($status))) {
    if (!function_exists('아이템주식_채팅명령_시도')) {
      require_once __DIR__ . '/item_stock_market.inc.php';
    }
    // 본방: 공커대실권 = 게임냥 시세 → 스왑가 본방냥 결제
    if (function_exists('아이템주식_공커대실권_스왑본방결제_설정')) {
      아이템주식_공커대실권_스왑본방결제_설정(true);
    }
    if (아이템주식_채팅명령_시도($status, $두자리닉넴, $정보, isset($단위) ? $단위 : '냥')) {
      exit;
    }

  }else if (function_exists('아이템사용방법_명령인가') && 아이템사용방법_명령인가($status)) {
    echo 전송(아이템사용방법_문구());
    exit;

  }else if (strpos($status, '.아이템') === 0) {
    // 명령어 파싱
    $parts = explode(' ', trim($status), 2);
    $옵션 = isset($parts[1]) ? trim($parts[1]) : '';
    // ======================
    // 아이템 설명 정의
    // ======================
    $아이템설명 = [];

    $아이템설명['제한'] = "👹제한
# 지정인에게 아래의 사항 중 한가지 적용
• 자숙 6시간
• 보이스룸 이용제한 3시간
• 채팅 금지(이모티콘 포함) 3시간
• 게임 이용제한 3시간";

    $아이템설명['수호'] = "👼 수호
# 지정인에게 아래의 사항 중 한가지를 적용
• 일방/공커 자숙 1일 단축 (받는 사람 기준 하루 1회)
• 보이스룸 이용제한 3시간 단축
• 채팅금지(이모티콘 포함) 3시간 단축
• 게임 이용제한 3시간 단축
(보룸/채팅/게임 제한은 하루 횟수 제한 없음)
수호 사용방법
.수호 닉네임";

  $아이템설명['선물'] = "🎁 선물
# 보유중인 아이템 1개를 지정인에게 선물할 수 있다.";

  $아이템설명['닉변'] = "🪪 닉네임 변경 (지속)
# 중복되지 않는 닉네임을 1회 변경할 수 있다.
닉변 사용방법
.닉변 현재닉 변경닉";

  $아이템설명['강일'] = "💌 강제 일방(12시간)
# 아래의 조건의 일대일 채팅방을 상대방 동의 없이
  강제로 개설할 수 있다.
• 보룸금지(모니터링이 되지 않는 이슈)
• 참여자 중 1인이라도 종료 시 강제 일방 종료
• 방장 참관조건 단, 문제가 없을 경우 모니터링 ❌
강제일방 끝나는 시점으로 부터 12시간 쿨타임 적용
쿨타임 기간동안 일방신청 및 추가 강일 불가
강일 연장은 진행중 1회만 가능
연장시 추가 12시간 쿨타임 적용";


  $아이템설명['프변'] = "🖍 프로필 변경
# 아래의 사항 중 한가지를 적용할 수 있다.
• 프로필 내 이모티콘 등록 3일
• 프로필 내 텍스트 등록 3일
• 닉네임 뒤 이모티콘 또는 텍스트 등록 3일
  명령어 .프변 · .프변 1";

  $아이템설명['색변'] = "📝 색 등본
# 지정한 프로필 색으로 1회 변경 및 방어가 가능하다.
  명령어 .색변환 닉 번호 (관리자 · 색변 1개 사용)";

//   $아이템설명['교환'] = "♻ 교환
// # 본인이 소유한 지정 아이템 1개를 1회
//   다른 아이템으로 교환할 수 있다.";

  $아이템설명['지목'] = "🫵 지목 (6시간)
# 1:1 공창 자기야 꽁냥
# 본인 포함 특정인(단, 이성끼리) 2명 지목
# 일방, 공커중인 상대끼리 지목가능
# 지목인들끼리 원하지 않을 경우 효과상실 (재사용불가)
# 지목이벤트는 진행되나 활동여부는 피지목자들의 몫
# 지목이벤트 중 일방신청으로 인해 일방진행시 지목이벤트 종료";

  $아이템설명['지호'] = "💦 지호 (1개당 1시간)
• 타수2타 인정
• 오늘 양도 1회 무료 · 지호 1시간마다 추가 양도 1회 (추가 시 지호 -1시간 · 없으면 마법 -1일)
  아이템 판매 수수료 15%
  명령어 .지호";

    // 👉 여기서 다른 아이템도 계속 추가 가능
    // $아이템설명['선물'] = "...";

    // ======================
    // 출력 분기
    // ======================
    if ($옵션 && isset($아이템설명[$옵션])) {

        // 🔹 특정 아이템만
        $msg = "🔫 아이템 상세설명 🔫\n\n" . $아이템설명[$옵션];

    } else if ($옵션) {

        // 🔹 없는 아이템
        $msg = "❌ '{$옵션}' 아이템은 존재하지 않습니다.";

    } else {

        $msg = "🔫 아이템 상세설명 🔫\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
        $msg.= implode("\n\n", $아이템설명);


        $msg.= "\n\n- 보유아이템 조회 명령어 : .가방

🚫 주의 사항
• [선물]를 사용하여 선물하는것 외 템 양도 불가";
    }

    echo 전송($msg);
    exit;


  }else if(strpos($status, '.금지공지') !== false){
    echo 전송("▪️ 금지항목

▫️공창 자삭 절대 금지!
▫️여미새•남미새•여왕벌•불편러
▫️공창 자삭•타인이 불편해하는 언행
▫️투방•바커 (적발시 킥)
▫️공창 및 보룸에서 개인정보 유출
▫️공커 졸업시 한 명만 놀러오는 경우");
    exit;

  } else if (strpos($status, '.공개서약서') !== false) {
    if (!preg_match('/^\.공개서약서\s+(\S+)/u', trim($status), $_서약_m)) {
      echo 전송("📜 예) `.공개서약서 진우`\n\n마이너스 정리 받는 조건처럼, 다시는 홀짝 안 한다는 각서");
      exit;
    }
    $원본닉 = trim($_서약_m[1]);
    $서약닉 = getTwoCharNick($원본닉);
    if ($서약닉 === '') {
      $서약닉 = preg_replace('/\s+/u', '', $원본닉);
    }
    if ($서약닉 === '') {
      echo 전송("📜 예) `.공개서약서 진우`");
      exit;
    }
    $서약닉_esc = addslashes($서약닉);
    $타겟 = @db_select("SELECT name, point FROM tb_member WHERE name = '{$서약닉_esc}' LIMIT 1");
    $표시명 = ($타겟 && !empty($타겟['name'])) ? trim((string)$타겟['name']) : $서약닉;
    $채무줄 = '';
    if ($타겟 && isset($타겟['point']) && (int)$타겟['point'] < 0) {
      $채무줄 = "\n\n▹ 현재 보유 " . number_format((int)$타겟['point']) . " — 이 마이너스를 (누군가) 변제해 주시는 대가로 아래를 지키겠습니다.";
    }

    $오늘 = date('Y년 n월 j일');
    $msg = "";
    $msg .= "📜 【공개 서약서 · 홀짝 은퇴 각서】\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "작성 일시 · {$오늘}\n";
    $msg .= "서약인 · 「{$표시명}」(이하 \"본인\")";
    $msg .= $채무줄;
    $msg .= "\n\n━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "본인은 양심(?)에 따라 다음을 공개히 서약한다.\n\n";
    $msg .= "제1조 (변제 조건 인정)\n";
    $msg .= "본인에게 발생한 마이너스(냥) 채무가 선의로 변제·정리되는 경우, 그 호의를 받아들이고 감사를 표한다.\n\n";
    $msg .= "제2조 (홀짝 영구 금지…으로 약속)\n";
    $msg .= "위 대가(또는 본인의 뼈저린 반성)로, 본인은 앞으로 `.도전`·`ㅈㅈ`·홀짝 웹 등 동일 유형의 홀/짝 배팅을 하지 않겠다.\n\n";
    $msg .= "제3조 (위반 시)\n";
    $msg .= "운영·친구들이 지켜보고 어떠한 조롱,메롱 감수할 것\n\n";
    $msg .= "서약자 : 해당위치에 본인 닉네임을 쓰시오 ";

    echo 전송($msg);
    exit;

  }else if(strpos($status, '.자숙공지') !== false){
    echo 전송("▪️ 자숙

▫️보이스룸•얼공•채굴•소통방•게임 금지
▫️자숙기간동안 공창에서 어필•플러팅 금지
▫️자숙중인 상대에게도 플러팅 금지
▫️일방 3일 / 공커 5일
▫️거절시 1일 셀프자숙 * 자숙과 동일하나
	보이스룸만 이용가능
▫️위 사항 적발시 3시간 간격으로 자숙기간 연장");
    exit;
  }else if(strpos($status, '.보룸공지') !== false){
    echo 전송("▪️ 보이스룸

▫️관을 포함해 총 3명 이상 시 이용가능
  (1인•2인인경우 이용 불가)
▫️공커 2인 시 맞보룸 가능(ex.지호 아영)
▫️보룸에서만 나이 공개
▫️음주 후 보이스룸 이용 자제
▫️보룸에서 나눈 대화 공창유출금지
▫️일방•공커 상대와 있었던 일 공유금지
▫️연락처•카톡아이디 공유시 이유불문 킥");
    exit;
  }else if(strpos($status, '.일방공지') !== false){
    echo 전송("▪️일방 및 공커

▫️건의방에 신청
▫️일방기간 : 7일 / 추가 연장 1회(7일) 가능
▫️일벙날짜가 결정되면 한 명이 연구실에 날짜와 대략적인 시간대 공지
① 일벙인증 : 두 사람 손하트 🫰🏻후 공창
② 공커인증 : 두 사람 손깍지 🤝🏻 후 공창");
    exit;

  }else if(strpos($status, '.주의일방') !== false){
    $msg = "필독! - 일방/일벙/공커/자숙

  가. 자숙
   # 일방신청 : 상대방 거절 시 1일(셀프)
   # 일방 : 종료 시 3일
   # 공커 : 종료 시 5일
   # 자숙기간동안 보룸, 얼공, 채굴, 소통방, 모임 금지
   # 단 셀프 자숙은 보룸가능
   # 자숙기간동안 공창에서 본인어필 / 플러팅 금지
   또한 자숙중인 상대에게 플러팅 금지
   (위 사항 적발시 3시간 간격으로 자숙기간 연장)
   # 이성친구와 나눴던 말, 있었던 일 절대 공유하지마!
   # 자숙시 진행중이던 일방은 모두 폭파할것.
   # 일방에서 연락처를 주고 받았더라도 개인적인 연락 절대금지
     깔끔하게 끝내자 

  나. 일방 및 공커
   # 정식 일방 신청은 건의방에 와서 해줘
   # 일방은 7일간 가능하고 연장 1회(7일) 가능해

‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️
‼️‼️아래 절차는 꼭 이행해줄것‼️‼️
‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️

필수1)일벙이 결정되면 연구실 혹은 건의방에 날짜를 이야기해주기

필수2)🦄일벙인증 - 손하트🫰🏻
  손하트🫰🏻인증 앵글에 모두 잡히도록 
  사진 찍은 후 공창에 올려줘!
- 만났을때 바로 해주는게 제일좋아!
  
필수3)🦄공커인증 - 손깍지🤝
   손깍지🤝끼고 사진찍어서 공창에 올려줘!🙏🏼

🚨 필수1,2,3 인증 중 하나라도 건너뛴다면 
룰 미이행으로 90% 냥 삭감.


🚨 첫 일벙시 당일 밤 12시 이전에 
연장 or 공커 or 자숙 결정 후
공창에 이야기 해줘!
이야기 없이 넘어갈시 90% 냥 삭감.";
    echo 전송($msg);
    exit;
  }else if(strpos($status, '.주의강일') !== false){
    $msg = "필독! - ⛺️강제일방

∙ 강일방에서는 방장 상주 (모니터링 하지 않음)
∙ 상대방이 불쾌할만한 언행 금지
∙ 개인정보 공유는 나이까지만 가능, 그 외 금지
∙ 보이스룸 금지 (모니터링 불가 이슈)
∙ 자삭 금지 (개인정보 오픈 후 자삭 이슈)
∙ 참여자 중 1인이라도 종료 시 강제일방 종료
∙ 강제일방은 커피벙/술벙 등 이성과의 만남(일벙) 약속 금지
∙ 이성과 만나고 싶다면 정식일방 신청 필수

🐸 이벤트 강일은 종료 후 12시간 락 없이 일방 전환 가능
그 외 얼공 및 기타 활동은 가능하나, 염려된다면 얼공방 이용을 추천드립니다.
🐸 이벤트로 개설되는 1:1방은 일반 일방이 아닌 강일방과 동일 규정이 적용됩니다.
(자삭, 보이스룸, 개인정보 공유 금지)
🐸 운영진은 안전 이슈로 상주하며, 종료 후 보이스룸 및 자삭 여부를 확인합니다.
🐸 운영진은 대화 내용을 일일이 확인하지 않지만, 룰 위반 정황 발생 시 확인이 진행될 수 있습니다.
🐸 운영진이 있는 공간에서 하기 어려운 깊은 대화 및 아쉬움은 일방 시스템을 통해 자유롭게 이용 가능합니다.

‼️ 강제일방 도중 강일템 사용 시 12시간 연장 가능
     (연장시 일방신청 쿨타임도 연장)
‼️ 강제일방 종료 후 12시간 뒤 정식일방 신청 가능
‼️ 강제일방 종료 후 셀자/자숙 없음

강제일방 종료시 진행자 둘중 한명은
신청방에 오셔서 .강일종료 를 입력해주세요.
강일 생성 후 10분 이내로 끝낼시 기본 12시간 쿨타임 없음";
    echo 전송($msg);
    exit;
  }else if( strpos($status, '.진행') !== false || strpos($status, '.일방') !== false ){

    $msg = "✅ 일방/강일/지목\n\n"; // 누적 저장

    // 일방
    $카운트 = 0;
    $result1 = db_query("select * from tb_progress where status = '일방' order by enddate asc ");
    $일방개수 = mysqli_num_rows($result1);
    if ($일방개수 > 0) {
        $msg .= "❤️1:1대화중(일방)\n";
        while ($row = db_fetch($result1)) {
            $msg .= $row['nick']." ".date("m/d", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if($일방개수 >= 5){
      $msg .= "🔥일방마감\n\n";
    }

    // 강일
    $result2 = db_query("select * from tb_progress where status = '강일' order by enddate asc ");
    $강일개수 = mysqli_num_rows($result2);
    if ($강일개수 > 0) {
        $msg .= "🤼‍♀️ 강제일방(12시간)\n";
        while ($row = db_fetch($result2)) {
            $msg .= $row['nick']." ".date("d일 H:i", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if($강일개수 >= 5){
      $msg .= "🔥강일마감\n\n";
    }

    // 지목
    $result3 = db_query("select * from tb_progress where status = '지목' order by enddate asc ");
    $지목개수 = mysqli_num_rows($result3);
    if ($지목개수 > 0) {
        $msg .= "👩‍❤️‍👨 지목(6시간 공창커플)\n";
        while ($row = db_fetch($result3)) {
            $msg .= $row['nick']." ".date("d일 H:i", strtotime($row['enddate']))."\n";
            $카운트++;
        }
        $msg .= "\n";
    }

    if(!$카운트){
      $msg .= "-없음";
    }

    echo 전송(trim($msg)); // 마지막 공백 제거
    exit;
}else if(strpos($status, '.공지') !== false){
  require_once __DIR__ . '/notice.inc.php';
  echo 전송(공지_안내_문구());
  exit;

}else if(strpos($status, '.차단해제') !== false){

  if (preg_match('/\.차단해제\s*(.+)/u', $status, $match)) {
    $after = trim($match[1]); // ".양도" 뒤 텍스트 추출
    echo 전송("{$after} 차단 해제🎉");
    exit;
  }

}else if(strpos($status, '.차단') !== false){

  if (preg_match('/\.차단\s*(.+)/u', $status, $match)) {
    $after = trim($match[1]); // ".양도" 뒤 텍스트 추출
    echo 전송("{$after} 차단 완료🎉");
    exit;
  }
}else if(strpos($status, '.진하발') !== false){

  // .진하발 1~5
  $단계 = 5;
  if (preg_match('/\.진하발\s*([1-5])/u', $status, $m)) {
    $단계 = (int)$m[1];
  }
  $단계라벨 = [
    1 => '💣 전쟁',
    2 => '⚠️ 위험',
    3 => '🔶 경계',
    4 => '😈 도발',
    5 => '🟢 안전',
  ];
  $라벨 = $단계라벨[$단계] ?? '안전';
  echo 전송("현재 진하발 {$단계}단계({$라벨}) 입니다.");
  exit;

}else if(strpos($status, '.레상발') !== false){

  // .레상발 1~5
  $단계 = 5;
  if (preg_match('/\.레상발\s*([1-5])/u', $status, $m)) {
    $단계 = (int)$m[1];
  }
  $단계라벨 = [
    1 => '💣 전쟁',
    2 => '⚠️ 위험',
    3 => '🔶 경계',
    4 => '😈 도발',
    5 => '🟢 안전',
  ];
  $라벨 = $단계라벨[$단계] ?? '안전';
  echo 전송("현재 레상발 {$단계}단계({$라벨}) 입니다.");
  exit;
 }else if(strpos($status, '.확인공지') !== false || strpos($status, '.공지확인') !== false){
    require_once __DIR__ . '/notice.inc.php';
    공지_확인_처리($두자리닉넴);

  }else if(strpos($status, '.모금') !== false){
    $최소모금 = 전체냥기준금액(0.01, true);
    $모금파싱 = function_exists('모금_명령_파싱') ? 모금_명령_파싱($status) : ['nick' => null, 'amount_text' => ''];
    $nick = $모금파싱['nick'];
    $금액텍스트 = $모금파싱['amount_text'];

    if ($금액텍스트 !== '') {
      if (empty($nick) || !empty($모금파싱['need_nick'])) {
        echo 전송("❌ 자숙자 닉을 지정해주세요.\n예) .모금 가니 10000");
        exit;
      }

      if (function_exists('냥_정수_미만') ? 냥_정수_미만($정보['point'] ?? 0, $최소모금) : ((float)($정보['point'] ?? 0) < (float)$최소모금)) {
        $보유표시 = function_exists('냥축약표시') ? 냥축약표시($정보['point']) : number_format((float)($정보['point'] ?? 0)) . "냥";
        echo 전송("냥 부족! 보유 {$보유표시}");
        exit;
      }

      $검증 = function_exists('모금_입력검증')
        ? 모금_입력검증($금액텍스트, $정보['point'] ?? 0, $최소모금)
        : ['ok' => false, 'msg' => '❌ 모금 검증을 사용할 수 없어요.'];
      if (empty($검증['ok'])) {
        echo 전송((string)($검증['msg'] ?? '❌ 모금 금액을 확인해주세요.'));
        exit;
      }
      $금액 = (string)($검증['금액'] ?? '0');

      if (function_exists('모금_대상단가_검증')) {
        $단가검증 = 모금_대상단가_검증($금액, $nick);
        if (empty($단가검증['ok'])) {
          echo 전송((string)($단가검증['msg'] ?? '❌ 모금 금액이 부족해요.'));
          exit;
        }
      }

      if (function_exists('모금_자숙단축_적용')) {
        $결과 = 모금_자숙단축_적용($두자리닉넴, $금액, $nick);
        echo 전송((string)($결과['msg'] ?? '모금 처리 완료'));
        exit;
      }

      echo 전송('❌ 모금 처리를 사용할 수 없어요.');
      exit;
    }

    $msg = function_exists('모금_도움말_문구')
      ? 모금_도움말_문구($최소모금)
      : ("금액: 숫자 또는 만·억·조·경·천경·해\n최소 모금: " . (function_exists('냥축약표시') ? 냥축약표시($최소모금) : number_format((float)$최소모금) . '냥'));
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.퇴근') !== false){
    $퇴근신청자 = $두자리닉넴;

    $msg = "✅ 퇴근자 명단\n\n";
    $result = db_query("SELECT *
    FROM tb_work
    ORDER BY
        CASE
            WHEN status = '퇴근' THEN 1
            ELSE 2
        END,
        regdate DESC");
    for($i=0;$row=db_fetch($result);$i++){
      $시각 = function_exists('퇴근_표시시각') ? 퇴근_표시시각($row) : date("m-d H:i", strtotime($row['regdate']));
      $msg.= $row['nick']." ".$시각."\n";
    }
    $msg.= "\n퇴근인원 총 {$i}명\n";

    $trim_st = trim($status);
    $대상 = $퇴근신청자;
    $explicit = false;
    if (preg_match('/\.퇴근(?:\s+(\S+))?/u', $trim_st, $match)) {
      $explicit = isset($match[1]) && $match[1] !== '';
      $대상 = $explicit ? getTwoCharNick($match[1]) : $퇴근신청자;
      if ($explicit && $대상 === '') {
        $대상 = preg_replace('/\s+/u', '', (string)$match[1]);
      }
    }
    $명령 = '퇴근';

    // 타인 퇴근 등록은 관리자만 (본인은 `.퇴근` 또는 `.퇴근 본인닉` 가능)
    if ($대상 !== $퇴근신청자 && !in_array($퇴근신청자, $관리자, true)) {
      echo 전송("❌ 다른 사람 퇴근 처리는 관리자만 가능해요.\n\n" . $msg);
      exit;
    }

    $대상_esc = addslashes($대상);

    $sql = "select * from tb_member where name = '{$대상_esc}' ";
    $받는친구 = db_select($sql);
    if(!$받는친구['idx']){
      echo 전송("⛵️");
      exit;
    }

    if (퇴근_24시간_미충족($받는친구['regdate'] ?? '')) {
      echo 전송(퇴근_24시간_안내($받는친구['regdate'] ?? ''));
      exit;
    }

    $data = db_select("select * from tb_work where nick = '{$대상_esc}' ");
    if($data['idx']){
      echo 전송("퇴근 후 다시올땐 .출근 {$대상}\n\n" . $msg);
      exit;
    }

    if($명령=="퇴근"){
      $타임 = 48;
      $이틀 = date("Y-m-d H:i", strtotime("+{$타임} hours"));
    }else{
      $타임 = 168;
      $이틀 = date("Y-m-d H:i", strtotime("+{$타임} hours"));
    }

    $명령_esc = addslashes($명령);
    if (function_exists('회원_퇴근_채굴정지')) {
      회원_퇴근_채굴정지($대상);
    }
    if (function_exists('퇴근_등록_쿼리')) {
      $sql = 퇴근_등록_쿼리($명령, $대상, $이틀);
    } else {
      $sql = "insert into tb_work set status = '{$명령_esc}', nick = '{$대상_esc}', regdate = '{$이틀}'  ";
    }
    $result = db_query($sql);
    db_query("update tb_member set status = 3 where name = '{$대상_esc}' ");
    if($result){
      $성공본문 = "{$대상} 퇴근등록🚌\n{$타임}시간 내 돌아오자.\n";
      $성공본문.= "\n-미복귀시\n공질•아이템•{$단위} 자동삭제";
      $성공본문.= "\n\n우리방 검색어\n[ 진우썸 ]";
      $msg최신 = "✅ 퇴근자 명단\n\n";
      $퇴근인원 = 0;
      $rs최신 = db_query("SELECT *
        FROM tb_work
        ORDER BY
            CASE
                WHEN status = '퇴근' THEN 1
                ELSE 2
            END,
            regdate DESC");
      while ($row최신 = db_fetch($rs최신)) {
        $시각최신 = function_exists('퇴근_표시시각') ? 퇴근_표시시각($row최신) : date("m-d H:i", strtotime($row최신['regdate']));
        $msg최신 .= $row최신['nick'] . " " . $시각최신 . "\n";
        $퇴근인원++;
      }
      $msg최신 .= "\n퇴근인원 총 {$퇴근인원}명\n";
      echo 전송($msg최신 . "\n\n" . $성공본문);
      exit;
    }

    $msg.= "\n- 퇴근시 `.퇴근` 본인 / 관리자는 `.퇴근 닉네임`";
    $msg.= "\n- 출근시 `.출근` 본인 / 관리자는 `.출근 닉네임`";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.출근') !== false){
    $출근신청자 = $두자리닉넴;

    if (!preg_match('/(?:\.출근)\s*(.*)/u', trim($status), $match)) {
      echo 전송("출근은 `.출근`으로 입력해 주세요.");
      exit;
    }

    $raw_rest = isset($match[1]) ? trim($match[1]) : '';
    $explicit = ($raw_rest !== '');
    $출근대상 = $explicit ? getTwoCharNick($raw_rest) : $출근신청자;
    if ($explicit && $출근대상 === '') {
      $출근대상 = preg_replace('/\s+/u', '', $raw_rest);
    }

    if ($출근대상 !== $출근신청자 && !in_array($출근신청자, $관리자, true)) {
      echo 전송("❌ 다른 사람 출근 처리는 관리자만 가능해요.");
      exit;
    }

    $출근대상_esc = addslashes($출근대상);

    $받는친구 = db_select("SELECT * FROM tb_member WHERE name = '{$출근대상_esc}' LIMIT 1");
    if (!$받는친구['idx']) {
      echo 전송("존재하지 않는 사용자 입니다.");
      exit;
    }

    // 출근자 본인 status → 0 (퇴근 status=3 복구 · tb_work 유무와 무관하게 맞춤)
    db_query("UPDATE tb_member SET status = 0 WHERE name = '{$출근대상_esc}' LIMIT 1");

    $data = db_select("SELECT * FROM tb_work WHERE nick = '{$출근대상_esc}' LIMIT 1");
    if ($data['idx']) {
      if (function_exists('회원_출근_채굴재개')) {
        회원_출근_채굴재개($출근대상);
      }
      $자숙연장문구 = '';
      if (function_exists('출근_자숙_퇴근기간연장')) {
        $연장 = 출근_자숙_퇴근기간연장($출근대상, $data);
        if (!empty($연장['applied']) && !empty($연장['notice'])) {
          $자숙연장문구 = "\n\n" . $연장['notice'];
        }
      }
      $result = db_query("DELETE FROM tb_work WHERE nick = '{$출근대상_esc}' LIMIT 1");
      if ($result) {
        echo 전송("{$출근대상} 어서와!!🎉" . $자숙연장문구);
        exit;
      }
    }

    echo 전송("{$출근대상} 이미 출근 상태예요. (status 정상화)");
    exit;
  }else if(strpos($status, '.명령') !== false){
    $msg = "✅ 우리방 명령어\n\n";
    $msg .= "📖 .사용설명서 (모바일 가이드 웹)\n\n";
    if (in_array($두자리닉넴, (array)($관리자 ?? []), true)) {
      $msg .= ".관리 (관리자 명령어)\n\n";
    }
    $msg .= "【일반】\n";
    $msg .= ".내냥 (본방냥)\n";
    $msg .= ".제발\n";
    $msg .= ".공질\n";
    $msg .= ".샘플(공질샘플)\n";
    $msg .= ".신입(주의사항)\n";
    $msg .= ".요주의인물 (위험인물 목록)\n";
    $msg .= ".인원(남여비율)\n";
    $msg .= ".솔로(공커·일방 제외)\n";
    $msg .= ".고인물(입장순)\n";
    $msg .= ".연두(미출석자)\n";
    $msg .= ".투표 제목 / .찬성 / .반대 / .투표종료(관리자)\n";
    $msg .= ".진행(일방/강일/지목)\n";
    $msg .= ".주의일방 (일방/일벙/공커/자숙 필독)\n";
    $msg .= ".주의강일 (강제일방 필독)\n";
    $msg .= ".등록 일방 (관리자 · 일방 등록/해제)\n";
    $msg .= ".등록 강일 / .등록 지목 (관리자 · 등록/해제)\n";
    $msg .= ".강일사용방법 (본인강일·지목강일 소모 안내)\n";
    $msg .= ".자숙(일방/강일/제한)\n";
    $msg .= ".미션(일방 신청 조건)\n";
    $msg .= ".본인인증 · .본인인증 닉 · .본인인증 닉 상대닉 (첫 일방 셀카 안내)\n";
    $msg .= ".평타(전체평균타수)\n";
    $msg .= ".생타(오늘 생타순위 · 생타/버프타)\n";
    $msg .= ".생타체크(3일 200타 미지급자 일괄지급 · 관리자)\n";
    $msg .= ".주사위 (누구나) · .주사위 2 (신불자 · 시간당 1회 · 출석룰렛 티켓으로 추가 · 더블 시 부채 10~30% 랜덤 차감)\n";
    $msg .= ".대출 (진행중 목록) · .대출 닉 금액 (관리자 · 게임냥 지급 · 실행 즉시 이자 10% · 이후 하루 10%) · .상환 (원금+누적이자 차감)\n";
    $msg .= ".신용불량 (신불자·홀짝 제한 현황)\n";
    $msg .= ".닉추천(GPT 2글자 닉)\n";
    $msg .= ".타이틀 (게임냥 30만냥 · 랜덤 칭호)\n";
    $msg .= ".아이템\n";
    $msg .= ".아이템사용방법 (닉변·지목·강일·제한 명령)\n";
    $msg .= ".색변환 닉 번호 (관리자 · 색변 아이템 1개)\n";
    $msg .= ".사용 닉 템명 개수 (관리자 · 예: .사용 치즈 일방신청권 1)\n";
    $msg .= ".주가 · .매수 템명 · .매도 템명 (시총% · 종류별 하루 10)\n";
    $msg .= ".버프(프변,지호) · .프변 / .프변 N (1개=3일) · .지호 / .지호 N\n";
    $msg .= ".사다리 10 6 (평타 상위 10명 중 6명 · 11시이후 · 하루1회)\n";
    $msg .= ".사다리 10 6 1 (1등 30% + 나머지 1등제외 6명·11시이후·하루1회)\n";
    $msg .= ".랭킹1 · .랭킹2 · .랭킹3 (홍보방 · 보유냥/게임냥/채굴 장비 순위)\n";
    $msg .= ".금지어 (금지단어 사용 랭킹)\n";
    $msg .= ".지갑변경 (본인 지갑 주소·CODE 재발급)\n";
    $msg .= "💰.양도 (가방에서만 가능)\n";
    $msg .= "--------\n";
    $msg .= "【유료·GPT】\n";
    $msg .= "💰.궁금 닉네임 (신불자 100냥→상대)\n";
    $msg .= ".강일연금 (강일 연금 전체)\n";
    $msg .= ".지목연금 (지목 연금 전체)\n";
    $msg .= ".공커연금 (공커 연금 수령)\n";
    $msg .= ".공커대실권 (연장 설명·구매방법)\n";
    $msg .= "💰.메뉴추천(5만냥·GPT 점심)\n";
    $msg .= "💰.맛집 지역(5만냥·GPT 맛집 3곳)\n";
    $msg .= "💰.mbti 닉\n";
    
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.공커연금') !== false){
    공커연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.강일사용방법') !== false){
    echo 전송(강일사용방법_문구());
    exit;

  }else if(strpos($status, '.강일연금') !== false){
    강일연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.지목연금') !== false){
    지목연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.궁금') !== false){

    // 신불자도 본방 `.궁금` 사용 가능 (타인 조회 시 본방냥 100 → 상대)
    $_궁금_타이틀 = trim((string)($정보['title'] ?? ''));
    $_궁금_pt = trim((string)($정보['point'] ?? '0'));
    $_궁금_신용 = (int)($정보['credit'] ?? 0);
    $_궁금_pt음수 = (isset($_궁금_pt[0]) && $_궁금_pt[0] === '-');
    $_궁금_신불 = ($_궁금_타이틀 === '🆘신불자')
      || (bool)preg_match('/신불자/u', $_궁금_타이틀)
      || ($_궁금_신용 === 1 && $_궁금_pt음수)
      || ($_궁금_pt음수 && (ltrim(preg_replace('/[^\d]/', '', $_궁금_pt), '0') ?: '0') !== '0');

    if (preg_match('/\.궁금\s*(.*)/u', $status, $match)) {
        $after = trim($match[1]); // ".궁금" 뒤 텍스트 추출 (없으면 본인 조회)

        // 빈칸이면 본인 닉네임, 아니면 한 단어 닉네임만 허용
        if ($after === '') {
          $닉네임 = $두자리닉넴;
        } elseif (preg_match('/^([가-힣A-Za-z]+)$/u', $after, $parts)) {
          $닉네임 = $parts[1];
        } else {
          $닉네임 = null;
        }

        if ($닉네임 !== null) {

          $sql = "select * from tb_member where name = '{$닉네임}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송($닉네임." 없음.");
            exit;
          }

          $계급 = 계급($받는친구['point']);
          if($받는친구['title']){
            $호칭 = $받는친구['title'];
          }else{
            $호칭 = $계급['name'];
          }
          
          $msg = "Lv {$받는친구['level']} {$호칭} {$받는친구['name']}\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";

            $타인조회 = ($두자리닉넴 !== $닉네임);
            // 일반: 조회비 2냥(조회자 차감) · 신불자 타인조회: 100냥 → 상대에게 지급
            $궁금조회비 = ($_궁금_신불 && $타인조회) ? 100 : 2;
            if ($타인조회) {
              $조회자행 = db_select("SELECT IFNULL(newpoint, 0) AS newpoint FROM tb_member WHERE name = '" . addslashes($두자리닉넴) . "' LIMIT 1");
              $조회자본방 = (float)($조회자행['newpoint'] ?? ($정보['newpoint'] ?? 0));
              if ($조회자본방 < $궁금조회비) {
                $비고 = $_궁금_신불 ? " (신불자 조회비 " . newpoint표시($궁금조회비) . "냥 → 상대)" : '';
                echo 전송("❌ 본방냥이 " . newpoint표시($궁금조회비) . "냥 미만이라 .궁금 조회를 할 수 없습니다.{$비고}\n(현재 " . newpoint표시($조회자본방) . "냥)");
                exit;
              }
            } else {
              // 본인 조회: 대상(본인) 본방냥 2냥 미만이면 기존과 동일하게 차단
              if ((float)($받는친구['newpoint'] ?? 0) < $궁금조회비) {
                echo 전송("❌ [{$닉네임}] 본방냥이 " . newpoint표시($궁금조회비) . "냥 미만이라 .궁금 조회를 할 수 없습니다.\n(현재 " . newpoint표시($받는친구['newpoint'] ?? 0) . "냥)");
                exit;
              }
            }


            $msg.= "\n".$받는친구['content']."\n";


            $sql = "select 
            " . 생타_SQL_select_expr('msg') . " as cnt2,
            " . 버프타_SQL_select_expr('msg', 'tasu') . " as cnt 
            from tb_msg 
            where nickname = '{$닉네임}' AND 
            tasu != 0 AND
            regdate >= CURDATE() AND 
            regdate < CURDATE() + INTERVAL 1 DAY ";
            $타수 = db_select($sql);
  
            $msg.= "\n타수 : " . (int)($타수['cnt2'] ?? 0) . "/" . (int)($타수['cnt'] ?? 0) . "타";
            $msg.= "\n본방냥 : ".newpoint표시($받는친구['newpoint'] ?? 0)."냥";
            // point는 DECIMAL 문자열로 재조회 — float 깨짐·조미만 0 표시 방지
            $_pt행 = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(65,0)) AS CHAR) AS pt FROM tb_member WHERE name = '" . addslashes($닉네임) . "' LIMIT 1");
            $_pt값 = $_pt행['pt'] ?? ($받는친구['point'] ?? '0');
            $게임냥표시 = function_exists('랭킹_게임냥표시')
              ? 랭킹_게임냥표시($_pt값, '')
              : (function_exists('게임냥_안전표시')
                ? 게임냥_안전표시($_pt값, '')
                : (function_exists('냥_숫자콤마') ? 냥_숫자콤마($_pt값) : (string)$_pt값));
            // 신불자(마이너스 게임냥)는 - 표기
            $_대상타이틀 = trim((string)($받는친구['title'] ?? ''));
            $_대상pt = trim((string)$_pt값);
            $_대상신불 = ($_대상타이틀 === '🆘신불자')
              || (bool)preg_match('/신불자/u', $_대상타이틀)
              || (isset($_대상pt[0]) && $_대상pt[0] === '-');
            if ($_대상신불 && $게임냥표시 !== '' && substr($게임냥표시, 0, 1) !== '-') {
              $게임냥표시 = '-' . $게임냥표시;
            }
            $msg.= "\n게임냥 : ".$게임냥표시;

            
            $내무기 = trim($받는친구['item'] ?? '');
            $내강 = (int)($받는친구['enhance'] ?? 0);
            $오강이상 = ($내무기 !== '' && $내강 >= 5);
            $스타일 = trim($받는친구['style'] ?? '');
            if ($내무기) {
              $msg .= "\n무기 : +{$내강} {$스타일} {$내무기}";
            }

            $joined = new DateTime($받는친구['regdate']);  // 가입일
            $now = new DateTime();                         // 현재
            $diff = $now->getTimestamp() - $joined->getTimestamp();
            $days = floor($diff / 86400);           // 하루 = 86400초
            $hours = floor(($diff % 86400) / 3600); // 남은 시간 계산
        
            if ($days > 0) {
                // 1일 이상
                $result = "{$days}일 {$hours}시간째";
            } else {
                // 1일 미만이면 시간만 표시
                $result = "{$hours}시간째";
            }
            $msg.= "\n입장 : {$result}";
            
            // 오늘 일자 기준 수입/지출 로그 (타수냥, 주사위, 일보상, 출석)
            $조회일자 = date('Y-m-d');

            // 활/단소 타수 조정은 msg='' 로 쌓이므로 실제 채팅만 표시
            $마지막카톡 = db_select("select msg, regdate from tb_msg where nickname = '{$닉네임}' AND TRIM(IFNULL(msg, '')) <> '' order by regdate desc limit 1 ");
            if (!empty($마지막카톡['regdate'])) {
            $last = $마지막카톡['regdate']; // 예: "2025-11-18 14:20:00"

            // DateTime 객체로 변환
            $lastTime = new DateTime($last);
            $now = new DateTime();
            // 시간 차이 계산
            $diff = $now->diff($lastTime);
            // 시간 + 분 계산
            $totalHours = ($diff->days * 24) + $diff->h; // 총 몇 시간
            $minutes = $diff->i; // 남은 분

            $msg.= "\n💬 마지막 메시지"."\n{$마지막카톡['msg']}\n({$totalHours}시간 {$minutes}분 전)\n";
            } else {
            $msg.= "\n💬 마지막 메시지\n(채팅 기록 없음)\n";
            }

            $대상_esc = addslashes($닉네임);
            $조회자_esc = addslashes($두자리닉넴);

            db_query("insert into tb_curious set nickname = '{$조회자_esc}', youname = '{$대상_esc}' ");

            if ($타인조회) {
              if ($_궁금_신불) {
                // 신불자: 본방냥 100 차감 → 상대에게 지급
                global $conn;
                db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) - {$궁금조회비} WHERE name = '{$조회자_esc}' AND IFNULL(newpoint, 0) >= {$궁금조회비} LIMIT 1");
                $차감됨 = ($conn instanceof mysqli) ? ((int)mysqli_affected_rows($conn) > 0) : true;
                if (!$차감됨) {
                  echo 전송("❌ 본방냥 차감에 실패했어요. (필요 " . newpoint표시($궁금조회비) . "냥)");
                  exit;
                }
                db_query("UPDATE tb_member SET newpoint = IFNULL(newpoint, 0) + {$궁금조회비} WHERE name = '{$대상_esc}' LIMIT 1");
                지급로그('궁금-신불', $두자리닉넴, $닉네임, 0, $궁금조회비);
                $msg .= "\n💰 신불 조회비 " . newpoint표시($궁금조회비) . "냥 → {$닉네임} 지급\n";
              } else {
                db_query("UPDATE tb_member SET newpoint = newpoint - {$궁금조회비} WHERE name = '{$조회자_esc}' LIMIT 1");
                지급로그('궁금-조회', $닉네임, $두자리닉넴, 0, $궁금조회비);
                $msg .= "\n💰 {$두자리닉넴} " . newpoint표시($궁금조회비) . "냥 차감\n";
              }
            }

          echo 전송($msg);
          exit;
        }
    }
    echo 전송(".궁금 친구닉네임");
    exit;

  }else if (preg_match('/^\.생타체크\s*$/u', trim((string)$status))) {
    관리자전용_확인($두자리닉넴, $관리자);
    include_once __DIR__ . '/_auto_raw_tasu_streak.php';
    echo 전송(function_exists('생타연속_체크지급_명령문구')
      ? 생타연속_체크지급_명령문구()
      : '❌ 생타체크를 불러올 수 없어요.');
    exit;

  }else if(strpos($status, '.생타') !== false){
    require_once __DIR__ . '/game/saengta.inc.php';
    생타_명령_처리($status, $두자리닉넴, $관리자);

  }else if (preg_match('/^\.공커대실권\s*$/u', trim($status))) {
    echo 전송(function_exists('공커대실권_안내문구') ? 공커대실권_안내문구() : '❌ 공커대실권 안내를 불러올 수 없어요.');
    exit;

  }else if(strpos($status, '.공커등록') !== false){
    if (function_exists('공커등록_명령_처리')) {
      공커등록_명령_처리($status, $두자리닉넴, $관리자 ?? []);
    }
    echo 전송("❌ 사용법: .공커등록 영수🖤하니\n예) .공커등록 우서💛앙앙");
    exit;

  }else if (strpos($status, '.공커') !== false || strpos($status, '.커플') !== false) {

    if (!isset($where)) $where = "";
    공커_sort_sdate재배치($where);

    $전체보유냥 = (float)($전체포인트['total_point'] ?? 0);
    $공커적립 = (int)floor($전체보유냥 * 0.0001);
    공커_amount_갱신($공커적립, $where);
    if (function_exists('공커_활성전원_oneroom동기화')) {
      공커_활성전원_oneroom동기화();
    }
    if (function_exists('공커연금_누적갱신')) {
      공커연금_누적갱신();
    }
    $공커연금단가 = function_exists('공커연금_단가') ? 공커연금_단가() : 500;

      $msg = "👩‍❤️‍👨 공커\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
      $msg .= "공커연금 1일당 " . number_format($공커연금단가) . "냥 (본방냥×0.001)\n\n";
      $result = db_query("SELECT * FROM tb_couple WHERE status = 0 {$where} ORDER BY sdate desc; ");
      for ($i = 0; $row = db_fetch($result); $i++) {
        $now1 = new DateTime();
        $end1 = new DateTime($row['edate']);
        $diffHours1 = ($end1->getTimestamp() - $now1->getTimestamp()) / 3600;
        $imminent1 = ($diffHours1 <= 24 && $diffHours1 > 0) ? "⚠️" : "";

        $date = $row['sdate'];
        $day = date('w', strtotime($date));
        $요일 = ['일', '월', '화', '수', '목', '금', '토'];

          $days = abs((new DateTime())->diff(new DateTime($row['sdate']))->format("%r%a"));
          $days = $days + 1;
          $msg .= $row['couple']." +{$days}\n({$요일[$day]} {$row['edate']}{$imminent1} )\n";
          if (function_exists('공커연금_커플_표시문구')) {
            $msg .= 공커연금_커플_표시문구($row['couple']) . "\n";
          }
          $msg .= "\n";
      }
      echo 전송($msg);
      exit;

  }else if(stripos($status, '.레벨업') !== false){
    require_once __DIR__ . '/game/lotto_ticket.inc.php';
    $msg  = "📈 레벨업 기준 안내\n\n";
    $msg .= "Lv 0 ~ 10   : 150타당 1레벨 상승\n";
    $msg .= "Lv 11 ~ 30  : 300타당 1레벨 상승\n";
    $msg .= "Lv 31 ~ 100 : 500타당 1레벨 상승\n";
    $msg .= "Lv 101 ~ 150: 1000타당 1레벨 상승\n";
    $msg .= "Lv 151 ~ 200: 2000타당 1레벨 상승\n";
    $msg .= "Lv 201 ~ 300: 3000타당 1레벨 상승\n";
    $msg .= "\n최대 레벨은 300레벨입니다.\n";
    $msg .= "🎁 레벨업 보상: 도달 레벨 숫자만큼 본방냥 (Lv1=1냥, Lv2=2냥 … 여러 단 오르면 합산)\n";
    $msg .= 로또티켓_레벨업_안내문구();
    if (function_exists('레벨업_보호_안내문구')) {
      $msg .= "\n" . 레벨업_보호_안내문구();
    }
    echo 전송($msg);
    exit;

  }else if (strpos($status, '.우리방') !== false) {
    $실시간 = function_exists('우리방_실시간_총량')
      ? 우리방_실시간_총량()
      : ['본방냥' => 0.0, '게임냥' => '0'];
    $전체보유 = (float)($실시간['본방냥'] ?? 0);
    $전체게임냥 = (string)($실시간['게임냥'] ?? '0');

    $msg = "📊 실시간 시세\n";
    $msg .= "✅ 전체 본방{$단위} : " . newpoint표시($전체보유) . "\n";
    $msg .= "✅ 전체 게임{$단위} : " . 랭킹_게임냥표시($전체게임냥, '') . "\n";
    $스왑1냥 = function_exists('우리방_실시간_스왑율')
      ? 우리방_실시간_스왑율($전체보유, $전체게임냥)
      : '0';
    $스왑표시가능 = function_exists('bccomp')
      ? bccomp((string)$스왑1냥, '0', 0) > 0
      : ((float)$스왑1냥 > 0);
    if ($스왑표시가능) {
      $msg .= "💱 1 보유냥 스왑 시 게임냥 : " . 랭킹_게임냥표시($스왑1냥, '') . "\n";
    }

    $선착순커피 = (int)floor($전체보유 * 0.01); // 전체 보유냥(newpoint) 1%
    $msg.= "\n* 커피쏘기 주의사항 *\n나에게 선물하기로 구매 후\n마켓에 업로드";

    $만원당전체지급 = (int)floor($전체보유 * 0.025); // 전체 보유냥(newpoint) 2.5%
    $msg .= "\n기프티콘 1만원 당 : " . newpoint표시($만원당전체지급) . "{$단위} 선매입!";

    $신입지원금 = function_exists('신입지원금_계산') ? 신입지원금_계산() : (int)floor($전체보유 * 0.03);
    $신입담당보상 = function_exists('신입담당보상_계산') ? 신입담당보상_계산() : (int)floor($전체보유 * 0.01);
    $생일자보상 = (int)floor($전체보유 * 0.10); // 전체 본방냥 10%

    $msg.= "\n\n📋 보상 (실시간 본방냥 기준)";
    $msg.= "\n신입 지원금 3% : " . newpoint표시($신입지원금) . "{$단위}";
    $msg.= "\n신입 담당 보상 1% : " . newpoint표시($신입담당보상) . "{$단위}";
    $msg.= "\n생일자 : " . newpoint표시($생일자보상) . "{$단위}";

    echo 전송($msg);
    exit;

  }else if (trim($status) === '.보조금지급') {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $보조금 = 보조금_동시지급();
    $주급결과 = $보조금['주급'];
    $보급결과 = $보조금['보급'];
    $msg = "💰 보조금 지급\n\n";
    $msg .= "【주급】\n" . $주급결과['msg'] . "\n\n【보급】\n" . $보급결과['msg'];
    echo 전송($msg);
    exit;

}else if (preg_match('/^ㅇㄷ\s*(\d+)?$/u', trim($status), $updownMatch)) {
    $입력숫자 = isset($updownMatch[1]) ? (int)$updownMatch[1] : 0;
    if ($입력숫자 < 1 || $입력숫자 > 50) {
      echo 전송("❌ 사용법: ㅇㄷ 숫자\n숫자는 1~50 사이로 입력해줘!");
      exit;
    }

    $설정행 = db_select("SELECT `업다운값`, `업다운시도` FROM config LIMIT 1");
    $목표숫자 = (int)($설정행['업다운값'] ?? 0);
    $현재시도 = (int)($설정행['업다운시도'] ?? 0);
    if ($목표숫자 < 1 || $목표숫자 > 50) {
      $목표숫자 = 0;
    }
    if ($현재시도 < 0) {
      $현재시도 = 0;
    }

    if ($목표숫자 === 0) {
      $목표숫자 = mt_rand(1, 50);
      db_query("UPDATE config SET `업다운값` = {$목표숫자}, `업다운시도` = 0");
      $현재시도 = 0;
    }

    $이번시도 = $현재시도 + 1;
    db_query("UPDATE config SET `업다운시도` = {$이번시도}");

    if ($입력숫자 === $목표숫자) {
      $누적행 = db_select("SELECT `누적값` FROM config LIMIT 1");
      $누적상금 = (int)($누적행['누적값'] ?? 0);
      if ($누적상금 < 0) {
        $누적상금 = 0;
      }
      $첫시도보너스 = ($이번시도 === 1) ? 1000000 : 0;
      $총지급액 = $누적상금 + $첫시도보너스;

      if ($총지급액 > 0) {
        $닉_esc = addslashes($두자리닉넴);
        db_query("UPDATE tb_member SET point = point + {$총지급액} WHERE name = '{$닉_esc}'");
      }
      지급로그('업다운-정답', $두자리닉넴, '', 0, $총지급액);

      $다음목표숫자 = mt_rand(1, 50);
      db_query("UPDATE config SET `업다운값` = {$다음목표숫자}, `누적값` = 0, `업다운시도` = 0");
      $보너스문구 = ($첫시도보너스 > 0) ? "\n🎁 첫 시도 정답 보너스 +".number_format($첫시도보너스) . "냥!" : "";
      echo 전송("🎉 업다운 정답은 {$목표숫자} 입니다.\n🏆 {$두자리닉넴} ".number_format($총지급액) . "냥 획득!{$보너스문구}\n🔄 다음 업다운 숫자 지정 완료!");
      exit;
    }

    $오답차감배수 = max(1, $이번시도);
    $오답기본차감 = mt_rand(10000, 20000);
    $차감액 = (int)($오답기본차감 * $오답차감배수);

    if ($입력숫자 < $목표숫자) {
      $닉_esc = addslashes($두자리닉넴);
      db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
      db_query("UPDATE config SET `누적값` = COALESCE(`누적값`, 0) + {$차감액}");
      echo 전송("❌오답 {$두자리닉넴} 업! -".number_format($차감액) . "냥 ({$오답차감배수}회)");
      exit;
    }

    $닉_esc = addslashes($두자리닉넴);
    db_query("UPDATE tb_member SET point = point - {$차감액} WHERE name = '{$닉_esc}'");
    db_query("UPDATE config SET `누적값` = COALESCE(`누적값`, 0) + {$차감액}");
    echo 전송("❌오답 {$두자리닉넴} 다운! -".number_format($차감액) . "냥 ({$오답차감배수}회)");
    exit;

  }else if (preg_match('/^\.정답\s*(.*)$/uis', trim($status), $m)) {
    $사용자정답 = isset($m[1]) ? trim($m[1]) : '';
    if ($사용자정답 === '') {
      echo 전송("❌ `.정답` 뒤에 답을 붙이거나 `ㅁ{정답}` 처럼 ㅁ+답만 입력해줘!\n예) ㅁ다람쥐 / .정답 다람쥐\n※ 틀리면 50만냥 차감·상금 누적");
      exit;
    }
    info1_cho_quiz_process_answer_submit($두자리닉넴, $단위, $사용자정답);

  }else if(stripos($status, '.초성초기화') !== false){
    $최근문제 = db_select("SELECT answer FROM tb_question ORDER BY idx DESC LIMIT 1");
    $정답문구 = (isset($최근문제['answer']) && trim($최근문제['answer']) !== '') ? "\n📌 직전 정답: " . trim($최근문제['answer']) : '';
    db_query("UPDATE config SET `초성자동연속` = 0 LIMIT 1");
    db_query("UPDATE tb_question SET status = 1 WHERE status = 0");
    echo 전송("✅ 초성 퀴즈 초기화 완료!{$정답문구}\n\n(자동 연속 출제가 꺼졌어요. 다시 `.초성`으로 시작해줘.)");
    exit;
    
    
  }else if(stripos($status, '.초성') !== false){
    $r = info1_cho_quiz_publish_next($단위);
    echo 전송($r['text'] ?? '❌ 초성 출제 처리 중 오류가 났어요.');
    exit;

  }else if(stripos($status, '.mbti') !== false){

    preg_match('/\.mbti\s*(.*)/ui', trim($status), $match);
    $값들 = isset($match[1]) ? preg_split('/\s+/u', trim($match[1]), -1, PREG_SPLIT_NO_EMPTY) : [];
    $지정자1 = $값들[0] ?? '';

    // 유효성 체크: 지정자1 필수
    if (empty($지정자1)) {
        echo 전송("❌ .mbti 닉네임 형식으로 입력해줘!\n예) .mbti 지정자닉");
        exit;
    }

    $상대1 = db_select("select * from tb_member where name = '{$두자리닉넴}' ");
    $상대2 = db_select("select * from tb_member where name = '{$지정자1}' ");

    // 유효성 체크: 상대방 회원 존재
    if (!$상대2['idx']) {
        echo 전송("❌ [ {$지정자1} ] 회원이 없어요!");
        exit;
    }

    $mbti1 = '';
    $mbti2 = '';
    if (!empty($상대1['content']) && preg_match('/MBTI\s*:\s*([A-Za-z0-9]+)/u', $상대1['content'], $m1)) {
        $mbti1 = trim($m1[1]);
    }
    if (!empty($상대2['content']) && preg_match('/MBTI\s*:\s*([A-Za-z0-9]+)/u', $상대2['content'], $m2)) {
        $mbti2 = trim($m2[1]);
    }

    $성별1 = ((int)($상대1['gender'] ?? 0) === 2) ? '여자' : '남자';
    $성별2 = ((int)($상대2['gender'] ?? 0) === 2) ? '여자' : '남자';

    // 유효성 체크: MBTI 등록 여부
    if (empty($mbti1)) {
        echo 전송("❌ {$두자리닉넴}님 MBTI가 등록되지 않았어요!");
        exit;
    }
    if (empty($mbti2)) {
        echo 전송("❌ {$지정자1}님 MBTI가 등록되지 않았어요!");
        exit;
    }

    if($두자리닉넴 == $지정자1){
      echo 전송("❌ 친구 닉네임을 입력해줘!");
      exit;
    }

    // 비용: 조회 상대가 공커면 전체 게임냥 0.001% · 솔로면 0.0001%
    $상대공커 = function_exists('공커_활성_조회') ? 공커_활성_조회($지정자1) : null;
    $mbti비율 = $상대공커 ? 0.001 : 0.0001;
    $mbti비율표시 = $상대공커 ? '0.001%' : '0.0001%';
    $mbti비용 = function_exists('전체냥기준금액') ? 전체냥기준금액($mbti비율) : 1;
    $mbti비용_sql = preg_replace('/[^\d]/', '', (string)$mbti비용) ?: '0';
    $보유부족 = function_exists('냥_정수_미만')
      ? 냥_정수_미만($정보['point'] ?? 0, $mbti비용)
      : ((float)($정보['point'] ?? 0) < (float)$mbti비용);
    if ($보유부족) {
        $비용표시 = function_exists('구매가_축약표시') ? 구매가_축약표시($mbti비용, $단위) : (number_format((float)$mbti비용) . $단위);
        $보유표시 = function_exists('냥축약표시') ? 냥축약표시($정보['point'] ?? 0) : (number_format((float)($정보['point'] ?? 0)) . $단위);
        $상대상태 = $상대공커 ? '공커' : '솔로';
        echo 전송("❌ MBTI 궁합 조회는 전체 게임냥 {$mbti비율표시}({$비용표시})가 필요해요! (상대 {$상대상태} · 현재 보유: {$보유표시})");
        exit;
    }

    $mbti오늘 = date('Y-m-d');
    $gpt시스템 = '너는 카카오 오픈채팅방 MBTI 궁합 해설가야. 점수표·번호 목록·항목명(케미/멘트팁/금기 등) 없이, 네 의견만 한국어로 자연스럽게 말해.';
    $gpt질문 = "{$mbti1} {$성별1} {$두자리닉넴} 와 {$mbti2} {$성별2} {$지정자1} 의 썸·연애 궁합에 대한 네 의견만 알려줘.\n"
      . "오늘 날짜: {$mbti오늘} — 같은 조합이어도 오늘 기준으로 느낌이 조금 달라도 돼.\n"
      . "번호 매기지 마. 점수·케미 등급·멘트팁·금기 같은 항목을 나누지 마. 네 생각만 몇 문장으로. 전체 350자 이내.";
    $answer = function_exists('callGPT') ? callGPT($gpt질문, $gpt시스템, 480) : false;
    $answer = is_string($answer) ? trim($answer) : '';
    if ($answer === '') {
      echo 전송("❌ GPT가 잠시 의견을 못 냈어. 냥은 안 나갔어!\n잠시 후 `.mbti {$지정자1}` 다시 해봐.");
      exit;
    }
    $answer = preg_replace("/\r\n|\r/", "\n", $answer);
    if (mb_strlen($answer, 'UTF-8') > 500) {
      $answer = mb_substr($answer, 0, 500, 'UTF-8') . '…';
    }

    db_query("UPDATE tb_member SET point = point - {$mbti비용_sql} WHERE name = '{$두자리닉넴}' ");

    echo 전송($answer);
    exit;



  }else if(strpos($status, '.사다리독식') !== false){

    // 타수 1등만, 23시 이후 — 금고 전액을 1등에게 지급
    $현재시 = (int)date('H');
    if ($현재시 < 23) {
      echo 전송("❌ .사다리독식 은 매일 밤 11시 이후에만 가능합니다.");
      exit;
    }

    $날짜 = date('Y-m-d');
    $일등 = db_select("
      SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
      FROM tb_msg
      WHERE regdate >= '{$날짜}' AND regdate < '{$날짜}' + INTERVAL 1 DAY
        AND nickname NOT IN ('오픈', '')
        AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
      GROUP BY nickname
      ORDER BY cnt DESC
      LIMIT 1
    ");
    if (!$일등 || empty($일등['nickname'])) {
      echo 전송("오늘 타수 1등이 없습니다.");
      exit;
    }

    $일등닉 = trim((string)$일등['nickname']);
    $일등두자리 = function_exists('getTwoCharNick') ? getTwoCharNick($일등닉) : $일등닉;
    if ($두자리닉넴 !== $일등닉 && $두자리닉넴 !== $일등두자리) {
      echo 전송("❌ 타수 1등만 .사다리독식 을 사용할 수 있어요.\n현재 1등: {$일등닉} (" . number_format((int)$일등['cnt']) . "타)");
      exit;
    }

    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    $금고잔액 = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : 냥_정수문자열(db_select("SELECT CONCAT('N', CAST(IFNULL(tax, 0) AS CHAR)) AS tax FROM config LIMIT 1")['tax'] ?? 'N0');
    if ($금고잔액 === '' || $금고잔액 === '0' || (function_exists('bccomp') ? bccomp($금고잔액, '0') <= 0 : (float)$금고잔액 <= 0)) {
      echo 전송("금고에 남은 금액이 없습니다.");
      exit;
    }

    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $수상자 = addslashes($일등닉);
    $지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($금고잔액) : preg_replace('/[^\d]/', '', (string)$금고잔액);
    if ($지급_sql === '' || $지급_sql === '0') {
      echo 전송("금고에 남은 금액이 없습니다.");
      exit;
    }

    db_query("UPDATE tb_member SET point = point + {$지급_sql} WHERE name = '{$수상자}'");
    db_query("UPDATE config SET tax = tax - {$지급_sql}");
    // 금고 전액 지급이므로 진행중 사다리 대기/확정 데이터 정리
    db_query("DELETE FROM tb_sadari WHERE IFNULL(status, 0) IN (0, 1)");
    db_query("UPDATE config SET 사다리인원 = 0");
    지급로그('사다리독식', $일등닉, '', 0, $지급_sql);

    $지급표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($지급_sql, $단위)
      : (number_format((float)$지급_sql) . $단위);
    $msg = "👑 사다리 독식!\n";
    $msg .= "타수 1등 {$일등닉} (" . number_format((int)$일등['cnt']) . "타)\n";
    $msg .= "금고 전액 {$지급표시} 지급 완료";
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.사다리종료') !== false){

    // 진행자 기준, status=1(당첨 처리된) 사다리 금액 실제 지급
    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('tb_sadari_amount_컬럼_보장')) {
      tb_sadari_amount_컬럼_보장();
    }
    $게스트 = addslashes($두자리닉넴);
    $sql = "
      SELECT nick, CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_sadari
      WHERE guest = '{$게스트}' AND status = 1
    ";
    $res = db_query($sql);

    $rows = [];
    $총지급 = '0';
    while ($r = db_fetch($res)) {
      $amt = function_exists('냥_정수문자열') ? 냥_정수문자열($r['amount'] ?? 0) : preg_replace('/\D/', '', (string)($r['amount'] ?? '0'));
      $r['amount'] = $amt;
      $rows[] = $r;
      // (int) 합산 금지 — 경 단위에서 UINT64/PHP_INT 오버플로로 총액이 1844경… 으로 깨짐
      if (function_exists('냥_금액_문자열합')) {
        $총지급 = 냥_금액_문자열합($총지급, $amt);
      } elseif (function_exists('bcadd')) {
        $총지급 = bcadd($총지급, $amt, 0);
      } else {
        // bcmath·헬퍼 모두 없을 때: 자리수 문자열 덧셈
        $aa = strrev(ltrim(preg_replace('/\D/', '', (string)$총지급), '0') ?: '0');
        $bb = strrev(ltrim(preg_replace('/\D/', '', (string)$amt), '0') ?: '0');
        $len = max(strlen($aa), strlen($bb));
        $carry = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
          $sum = $carry + (int)($aa[$i] ?? '0') + (int)($bb[$i] ?? '0');
          $out .= (string)($sum % 10);
          $carry = intdiv($sum, 10);
        }
        if ($carry > 0) {
          $out .= (string)$carry;
        }
        $총지급 = ltrim(strrev($out), '0') ?: '0';
      }
    }
    $총지급 = function_exists('냥_정수문자열') ? 냥_정수문자열($총지급) : $총지급;

    if (empty($rows)) {
      echo 전송("지급할 사다리 당첨 내역이 없습니다.");
      exit;
    }

    // 금고 잔액 확인 ((int)/float 금지)
    $금고잔액 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
    if (function_exists('bccomp')) {
      $부족 = (bccomp($금고잔액, $총지급, 0) < 0);
    } else {
      $a = ltrim((string)$금고잔액, '0') ?: '0';
      $b = ltrim((string)$총지급, '0') ?: '0';
      $부족 = (strlen($a) !== strlen($b)) ? (strlen($a) < strlen($b)) : ($a < $b);
    }
    if ($부족) {
      $필요표시 = function_exists('금고_금액표시') ? 금고_금액표시($총지급, $단위) : ($총지급 . $단위);
      $현재표시 = function_exists('금고_금액표시') ? 금고_금액표시($금고잔액, $단위) : ($금고잔액 . $단위);
      echo 전송("❌ 금고 잔액이 부족합니다.\n필요: {$필요표시}\n현재: {$현재표시}");
      exit;
    }

    // 실제 지급 처리
    foreach ($rows as $r) {
      $닉 = addslashes($r['nick']);
      $amt = $r['amount'];
      if ($amt === '' || $amt === '0') {
        continue;
      }
      $amt_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($amt) : $amt;

      db_query("UPDATE tb_member SET point = point + {$amt_sql} WHERE name = '{$닉}'");
      지급로그('사다리지급', $닉, '', 0, $amt_sql);
    }
    $총지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총지급) : $총지급;
    db_query("UPDATE config SET tax = tax - {$총지급_sql}");
    $금고차감후 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '';

    // 지급된 내역은 삭제
    db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' ");

    // 사다리 종료 시 config.사다리인원 0으로 초기화
    db_query("UPDATE config SET 사다리인원 = 0");

    $msg = "✅ 사다리 종료 및 지급 완료\n";
    $msg .= "진행자: {$두자리닉넴}\n\n";
    $msg .= "📦 개별 지급 내역\n";
    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($amt, $단위);
      }
      return $amt . $단위;
    };
    foreach ($rows as $r) {
      $msg .= "- {$r['nick']} +" . $사다리금액표시($r['amount']) . "\n";
    }
    $msg .= "\n총 " . $사다리금액표시($총지급) . " 지급 (금고에서 차감)";
    if ($금고차감후 !== '') {
      $msg .= "\n금고 잔액: " . $사다리금액표시($금고잔액) . " → " . $사다리금액표시($금고차감후);
    }

    echo 전송($msg);
    exit;

  }else if(strpos($status, '.사다리현황') !== false){

    // 당첨 확정(status=1) 현황 — 동시 1건만 진행하므로 전체 조회
    $sql = "
      SELECT nick, CAST(IFNULL(amount, 0) AS CHAR) AS amount
      FROM tb_sadari
      WHERE status = 1
      ORDER BY idx ASC
    ";
    $res = db_query($sql);

    $rows = [];
    while ($r = db_fetch($res)) {
      $rows[] = $r;
    }

    if (empty($rows)) {
      echo 전송("현재 진행중인 사다리 당첨 내역이 없습니다.");
      exit;
    }

    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      if (function_exists('게임냥_안전표시')) {
        return 게임냥_안전표시($amt, $단위);
      }
      return number_format((float)$amt) . $단위;
    };

    $msg = "🎯 사다리 현황\n\n";
    foreach ($rows as $r) {
      $msg .= "✅당첨 {$r['nick']} " . $사다리금액표시($r['amount']) . "\n";
    }

    echo 전송($msg);
    exit;  

}else if (strpos($status, '.사다리마감') !== false) {

    global $conn;
    $관리자강제 = in_array($두자리닉넴, (array)($관리자 ?? []), true);

    if ($관리자강제) {
      // 관리자: status=0(대기)만 전체 삭제 — 당첨확정(status=1)은 유지
      $진행중 = db_select("
        SELECT guest
        FROM tb_sadari
        WHERE IFNULL(status, 0) = 0
          AND guest IS NOT NULL
          AND guest <> ''
        GROUP BY guest
        ORDER BY MAX(idx) DESC
        LIMIT 1
      ");
      $진행자 = trim((string)($진행중['guest'] ?? ''));
      db_query("DELETE FROM tb_sadari WHERE IFNULL(status, 0) = 0");
      $행수 = mysqli_affected_rows($conn);
      // 대기분이 없고 당첨확정도 없으면 인원 초기화
      $남은 = db_select("SELECT COUNT(*) AS cnt FROM tb_sadari WHERE IFNULL(status,0) IN (0,1)");
      if ((int)($남은['cnt'] ?? 0) === 0) {
        db_query("UPDATE config SET 사다리인원 = 0");
      }
      if ($행수 > 0) {
        $who = ($진행자 !== '') ? " (진행자: {$진행자})" : '';
        echo 전송("✅ 관리자 마감{$who}\n대기(status=0) {$행수}개 삭제 · 당첨확정분은 유지됩니다.");
      } else {
        echo 전송("삭제할 사다리 대기 데이터가 없습니다.");
      }
    } else {
      // 일반: 본인(guest) 대기(status=0) 데이터만 삭제
      $게스트 = addslashes($두자리닉넴);
      db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' and status = 0 ");
      $행수 = mysqli_affected_rows($conn);
      // 본인 진행분이 더 없으면 인원 설정 초기화 (다른 사람 시작 가능)
      $남은 = db_select("SELECT COUNT(*) AS cnt FROM tb_sadari WHERE guest = '{$게스트}' AND IFNULL(status,0) IN (0,1)");
      if ((int)($남은['cnt'] ?? 0) === 0) {
        db_query("UPDATE config SET 사다리인원 = 0");
      }
      if ($행수 > 0) {
        echo 전송("✅ {$두자리닉넴} 사다리 데이터 {$행수}개 삭제 완료");
      } else {
        echo 전송("삭제할 사다리 대기(status=0) 데이터가 없습니다.");
      }
    }
    exit;

}else if(strpos($status, '.사다리') !== false){
    $status_trim = trim($status);
    if (!preg_match('/\.사다리\s+(\d+)\s+(\d+)/u', $status_trim, $m)) {
      $msg = "✅ .사다리 사용법\n\n";
      $msg .= "· 평타(버프타) 순위 상위 N명 중 M명을 랜덤으로 뽑아요.\n\n";
      $msg .= "사용법: .사다리 [상위명수] [뽑을명수]\n";
      $msg .= "예시: .사다리 10 6  (매일 밤 11시 이후 · 하루 1회)\n";
      $msg .= "→ 평타 상위 10명(1등 포함) 중 6명 랜덤\n";
      $msg .= "→ 단, 오늘 1등이 이미 30%를 받았으면 1등 제외\n\n";
      $msg .= "사용법: .사다리 [상위명수] [뽑을명수] 1\n";
      $msg .= "예시: .사다리 10 6 1  (밤 11시 이후 · 하루 1회 · 버프타 1등만)\n";
      $msg .= "→ 1등에게 금고 30% 즉시 지급\n";
      $msg .= "→ 남은 70%는 상위 10명 중 1등 제외 6명 랜덤\n";
      echo 전송($msg);
      exit;
    }

    // 매일 23시 이후 · 하루 1회만 입력
    if (function_exists('사다리_입력가능검사')) {
      $사다리가능 = 사다리_입력가능검사();
      if (empty($사다리가능['ok'])) {
        echo 전송((string)($사다리가능['msg'] ?? '❌ 지금은 사다리를 입력할 수 없어요.'));
        exit;
      }
    } elseif ((int)date('H') < 23) {
      echo 전송("❌ .사다리는 매일 밤 11시(23시) 이후부터\n하루 1회만 입력할 수 있어요.");
      exit;
    }

    // 동시에 1명만 사다리 진행 가능 — status 0(대기)·1(당첨확정) 모두 진행중으로 간주
    // (.사다리종료로 데이터가 비어야 다른 사람 시작 가능)
    $현재진행자 = addslashes($두자리닉넴);
    $진행중 = db_select("
      SELECT guest
      FROM tb_sadari
      WHERE IFNULL(status, 0) IN (0, 1)
        AND guest IS NOT NULL
        AND guest <> ''
      GROUP BY guest
      ORDER BY MAX(idx) DESC
      LIMIT 1
    ");
    if ($진행중 && isset($진행중['guest']) && $진행중['guest'] !== '' && $진행중['guest'] !== $현재진행자) {
      echo 전송("❌ 현재 {$진행중['guest']} 님이 사다리를 진행중입니다.\n(진행자: .사다리마감 또는 .사다리종료 후 다시 시도)");
      exit;
    }

    $날짜 = date('Y-m-d');
    $상위N = (int)$m[1];
    $뽑을인원 = (int)$m[2];
    // 세 번째 인자: 1 = 1등 우대 모드 / YYYY-MM-DD = 날짜
    $일등우대모드 = (bool)preg_match('/\.사다리\s+\d+\s+\d+\s+1(?:\s|$)/u', $status_trim);
    if (!$일등우대모드 && preg_match('/\.사다리\s+\d+\s+\d+\s+(\d{4}-\d{2}-\d{2})/u', $status_trim, $m2)) {
      $날짜 = $m2[1];
    }
    $상위N = max(1, min(50, $상위N));

    // 최소 6명 이상만 사다리 진행 가능
    if ($뽑을인원 < 6) {
      echo 전송("❌ 사다리는 최소 6명 이상부터 진행 가능\n예) .사다리 10 6");
      exit;
    }
    $뽑을인원 = max(6, min($상위N, $뽑을인원));

    // 평타 = 버프타(SUM(tasu))만 사용 — 합타(버프타+생타)에서 생타 제외
    $sql = "WITH today_tasu AS (
        SELECT nickname, " . 버프타_SQL_select_expr('msg', 'tasu') . " AS cnt
        FROM tb_msg
        WHERE regdate >= '{$날짜}' AND regdate < '{$날짜}' + INTERVAL 1 DAY
          AND nickname NOT IN ('오픈', '')
          AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
        GROUP BY nickname
    ),
    ranked AS (
        SELECT nickname, cnt, ROW_NUMBER() OVER (ORDER BY cnt DESC) AS rn
        FROM today_tasu
    )
    SELECT nickname AS 닉네임, cnt AS 타수, rn AS 순위
    FROM ranked
    WHERE rn <= {$상위N}
    ORDER BY rn";
    $res = db_query($sql);
    $순위목록 = array();
    while ($row = db_fetch($res)) {
      $순위목록[] = $row;
    }
    if (empty($순위목록)) {
      echo 전송("{$날짜} 평타 데이터가 없어요.");
      exit;
    }

    $일등행 = $순위목록[0];
    $일등닉 = trim((string)($일등행['닉네임'] ?? ''));
    $일등두자리 = function_exists('getTwoCharNick') ? getTwoCharNick($일등닉) : $일등닉;
    $일등즉시지급 = '0';

    // 오늘 해당 1등이 이미 .사다리 … 1 로 30%를 받았으면,
    // .사다리 10 6 (1 없음) 에서도 1등 제외
    $일등이미수령 = false;
    $일등닉_esc_chk = addslashes($일등닉);
    $일등두자리_esc_chk = addslashes($일등두자리);
    $수령체크 = db_select("
      SELECT idx, CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point
      FROM tb_point_log
      WHERE status = '사다리1등'
        AND (nick = '{$일등닉_esc_chk}' OR nick = '{$일등두자리_esc_chk}')
        AND regdate >= '{$날짜}'
        AND regdate < '{$날짜}' + INTERVAL 1 DAY
      ORDER BY idx DESC
      LIMIT 1
    ");
    if ($수령체크 && isset($수령체크['idx'])) {
      $일등이미수령 = true;
      $일등즉시지급 = function_exists('냥_정수문자열')
        ? 냥_정수문자열($수령체크['point'] ?? '0')
        : preg_replace('/\D/', '', (string)($수령체크['point'] ?? '0'));
    }

    $일등제외추첨 = $일등우대모드 || $일등이미수령;
    if ($일등제외추첨 && $뽑을인원 >= $상위N) {
      echo 전송("❌ 1등 제외 추첨은 상위 명수가 뽑을 명수보다 커야 해요.\n예) .사다리 10 6");
      exit;
    }

    if ($일등우대모드) {
      if ($두자리닉넴 !== $일등닉 && $두자리닉넴 !== $일등두자리) {
        echo 전송("❌ 버프타 1등만 .사다리 … 1 을 사용할 수 있어요.\n현재 1등: {$일등닉} (" . number_format((int)$일등행['타수']) . "타)");
        exit;
      }
    }

    if ($일등제외추첨) {
      // 상위 N명 중 1등 제외 후 랜덤
      $추첨풀 = [];
      foreach ($순위목록 as $row) {
        if ((int)$row['순위'] === 1) {
          continue;
        }
        $추첨풀[] = $row;
      }
      if (count($추첨풀) < $뽑을인원) {
        echo 전송("❌ 1등 제외 후 후보가 {$뽑을인원}명 미만이에요. (후보 " . count($추첨풀) . "명)");
        exit;
      }
      shuffle($추첨풀);
      $뽑힌목록 = array_slice($추첨풀, 0, $뽑을인원);
    } else {
      shuffle($순위목록);
      $뽑힌목록 = array_slice($순위목록, 0, $뽑을인원);
    }

    // 이번 사다리에서 사용할 인원 수(랜덤 당첨자 수)를 config.사다리인원에 기록
    // 하루 1회 잠금 — 추첨 확정 직후 기록 (마감·재시도로 중복 방지)
    if (function_exists('사다리_입력가능검사')) {
      $사다리가능2 = 사다리_입력가능검사();
      if (empty($사다리가능2['ok'])) {
        echo 전송((string)($사다리가능2['msg'] ?? '❌ 오늘은 이미 사다리를 진행했어요.'));
        exit;
      }
    }
    if (function_exists('사다리_시작기록')) {
      사다리_시작기록((string)$두자리닉넴);
    }
    db_query("UPDATE config SET 사다리인원 = {$뽑을인원}");

    // 금고 잔액은 ALTER 전에 먼저 조회 (락·조회 실패로 0 되는 것 방지)
    $금고잔액 = function_exists('금고_잔액_조회')
      ? 금고_잔액_조회()
      : 냥_정수문자열(db_select("SELECT CONCAT('N', CAST(IFNULL(tax, 0) AS CHAR)) AS tax FROM config LIMIT 1")['tax'] ?? 'N0');
    if (isset($금고잔액[0]) && ($금고잔액[0] === 'N' || $금고잔액[0] === 'n')) {
      $금고잔액 = substr($금고잔액, 1);
    }
    $금고잔액 = function_exists('냥_정수문자열') ? 냥_정수문자열($금고잔액) : preg_replace('/\D/', '', (string)$금고잔액);
    if ($금고잔액 === '' || !preg_match('/^\d+$/', $금고잔액)) {
      $금고잔액 = '0';
    }

    if (function_exists('config_tax_컬럼_보장')) {
      config_tax_컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    if (function_exists('tb_sadari_amount_컬럼_보장')) {
      tb_sadari_amount_컬럼_보장();
    }

    $분배금고 = $금고잔액;
    $사다리금액표시_사전 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      return (string)$amt . $단위;
    };
    if ($일등우대모드 && !$일등이미수령) {
      // 1등 30% — 냥_비율내림 (bcmath 없어도 경 단위 안전)
      $일등즉시지급 = function_exists('냥_비율내림')
        ? 냥_정수문자열(냥_비율내림($금고잔액, 0.3))
        : (function_exists('냥_문자열나눗셈내림') && function_exists('냥_금액_문자열곱')
          ? 냥_문자열나눗셈내림(냥_금액_문자열곱($금고잔액, '30'), '100')
          : '0');
      $일등즉시지급 = function_exists('냥_정수문자열') ? 냥_정수문자열($일등즉시지급) : preg_replace('/\D/', '', (string)$일등즉시지급);
      if ($일등즉시지급 === '' || $일등즉시지급 === '0') {
        echo 전송("❌ 금고 30% 지급액을 계산할 수 없습니다.\n현재 금고: " . $사다리금액표시_사전($금고잔액));
        exit;
      }
      $일등지급_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($일등즉시지급) : $일등즉시지급;
      $일등닉_esc = addslashes($일등닉);
      db_query("UPDATE tb_member SET point = point + {$일등지급_sql} WHERE name = '{$일등닉_esc}'");
      db_query("UPDATE config SET tax = tax - {$일등지급_sql}");
      지급로그('사다리1등', $일등닉, '', 0, $일등지급_sql);
      // 나머지 70% = 원금고 - 1등지급 (bcsub, int 차감 금지)
      if (function_exists('bcsub')) {
        $분배금고 = bcsub($금고잔액, $일등즉시지급, 0);
      } else {
        $분배금고 = function_exists('금고_잔액_조회') ? 금고_잔액_조회() : '0';
      }
      if (function_exists('냥_정수문자열')) {
        $분배금고 = 냥_정수문자열($분배금고);
      }
    }
    // 이미 1등 30%를 받은 뒤 .사다리 10 6 재실행 → 현재 금고(남은 70%)만 분배

    $나눌인원 = max(1, count($뽑힌목록));
    $개당금액 = function_exists('냥_나눗셈내림')
      ? 냥_정수문자열(냥_나눗셈내림($분배금고, $나눌인원))
      : (function_exists('bcdiv') ? bcdiv($분배금고, (string)$나눌인원, 0) : '0');
    $개당금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($개당금액) : $개당금액;
    $사다리금액표시 = static function ($amt) use ($단위) {
      if (function_exists('금고_금액표시')) {
        return 금고_금액표시($amt, $단위);
      }
      if (function_exists('랭킹_게임냥표시')) {
        return 랭킹_게임냥표시($amt, $단위);
      }
      return number_format((float)$amt) . $단위;
    };

    if ($일등우대모드) {
      $msg = "👑 버프타 1등 우대 사다리 ({$날짜})\n";
      if ($일등이미수령) {
        $msg .= "1등 {$일등닉}: 금고 30% 이미 지급됨";
        if ($일등즉시지급 !== '' && $일등즉시지급 !== '0') {
          $msg .= " (" . $사다리금액표시($일등즉시지급) . ")";
        }
        $msg .= "\n";
        $msg .= "남은 금고: 상위 {$상위N}명 중 1등 제외 {$뽑을인원}명 랜덤\n\n";
      } else {
        $msg .= "1등 {$일등닉}: 금고 30% " . $사다리금액표시($일등즉시지급) . " 즉시 지급\n";
        $msg .= "남은 70%: 상위 {$상위N}명 중 1등 제외 {$뽑을인원}명 랜덤\n\n";
      }
    } else if ($일등이미수령) {
      $msg = "✅ 평타 {$상위N}위 중 1등 제외 {$뽑을인원}명 랜덤 뽑기 ({$날짜})\n";
      $msg .= "(1등 {$일등닉} 이미 금고 30% 수령 → 추첨 제외)\n\n";
    } else {
      $msg = "✅ 평타 {$상위N}위 중 {$뽑을인원}명 랜덤 뽑기 ({$날짜})\n\n";
    }
    $msg .= "진행자: " . trim($nick) . "\n";

    // 금액 표시/저장 제어 변수
    // null  : 기본동작 (23시 이후에만 금액 표시, 23시 이전에만 tb_sadari 저장)
    // true  : 시간과 상관없이 금액 표시 + tb_sadari 저장
    // false : 시간과 상관없이 금액 숨김 + tb_sadari 미저장
    $금액표시강제 = null;

    $현재시 = (int)date('H');
    if ($금액표시강제 === null) {
      $금액표시 = ($현재시 >= 23);
      $사다리저장 = ($현재시 >= 23);
    } else {
      $금액표시 = (bool)$금액표시강제;
      $사다리저장 = (bool)$금액표시강제;
    }

    // tb_sadari 저장 여부: $사다리저장 이 true 일 때만 insert
    if ($사다리저장) {
      foreach ($뽑힌목록 as $row) {
        $닉네임 = addslashes($row['닉네임']);
        $등수 = (int)$row['순위'];
        $타수 = (int)$row['타수'];

        // 동일 진행자(guest) + 동일 닉네임이 이미 tb_sadari에 있으면 중복 insert 방지
        $중복체크 = db_select("
          SELECT idx
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임}'
          LIMIT 1
        ");
        if ($중복체크 && isset($중복체크['idx'])) {
          continue;
        }

        $sql_insert = "
          insert into tb_sadari
          set 
            guest = '{$두자리닉넴}',
              nick = '{$닉네임}',
              rank = {$등수},
              tasu = {$타수},
              amount = {$개당금액_sql}
        ";
        db_query($sql_insert);
      }

    }

    if ($금액표시) {
      $msg .= "금고 잔액: " . $사다리금액표시($금고잔액) . "\n";
      if ($일등우대모드) {
        $msg .= "1등 즉시 지급(30%): " . $사다리금액표시($일등즉시지급) . "\n";
        $msg .= "랜덤 분배 풀(70%): " . $사다리금액표시($분배금고) . "\n";
      }
      $msg .= "1인당 지급 예정: " . $사다리금액표시($개당금액) . "\n\n";

      foreach ($뽑힌목록 as $i => $row) {
        $표시문구 = "";
        $닉네임표시 = addslashes($row['닉네임']);
        $당첨내역 = db_select("
          SELECT status
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임표시}'
            AND status = 1
          LIMIT 1
        ");
        if ($당첨내역 && isset($당첨내역['status']) && (int)$당첨내역['status'] === 1) {
          $표시문구 = " ✅";
        }

        $msg .= ($i + 1) . ". {$표시문구}" . $row['닉네임'] . " ({$row['순위']}등 +" . $사다리금액표시($개당금액) . ")\n";
      }
    } else {
      foreach ($뽑힌목록 as $i => $row) {
        $표시문구 = "";
        $닉네임표시 = addslashes($row['닉네임']);
        $당첨내역 = db_select("
          SELECT status
          FROM tb_sadari
          WHERE guest = '{$두자리닉넴}'
            AND nick = '{$닉네임표시}'
            AND status = 1
          LIMIT 1
        ");
        if ($당첨내역 && isset($당첨내역['status']) && (int)$당첨내역['status'] === 1) {
          $표시문구 = " ✅";
        }

        $msg .= ($i + 1) . ". {$표시문구}" . $row['닉네임'] . " ({$row['순위']}등, {$row['타수']}타)\n";
      }
    }

    if ($사다리저장) {
      $msg .= "\n- 당첨자들은 채팅창에 냥살냥죽!!! 입력해보자(느낌표 포함)";
      $msg .= "\n당첨자가 나오지 않을 시";
      $msg .= "\n- 진행자가 .사다리마감 입력할것!";
    }else{
      $msg .= "\n- 매일 밤 11시 이후에 사다리 가능";
    }

    echo 전송($msg);
    exit;


  }else if(strpos($status, '냥살냥죽!!!') !== false){

    // 사다리 당첨자 개별 지급 처리
    $당첨닉 = addslashes($두자리닉넴);
    // 가장 최근 사다리 기록 1개 조회
    $row = db_select("
      SELECT *
      FROM tb_sadari
      WHERE nick = '{$당첨닉}'
      ORDER BY idx DESC
      LIMIT 1
    ");

    if (!$row || !$row['idx']) {
      echo 전송("❌ {$두자리닉넴} 사다리 당첨 내역이 없습니다.");
      exit;
    }

    // 이미 수령한 내역이면 종료 (status 컬럼이 있다고 가정)
    if (isset($row['status']) && (int)$row['status'] === 1) {
      echo 전송("❌ {$두자리닉넴} 이미 사다리 냥 지급예정 입니다.");
      exit;
    }

    $지급액 = function_exists('냥_정수문자열')
      ? 냥_정수문자열($row['amount'] ?? 0)
      : preg_replace('/\D/', '', (string)($row['amount'] ?? '0'));
    if ($지급액 === '' || $지급액 === '0') {
      echo 전송("❌ 당첨 내역이 없습니다. 관리자에게 확인해주세요.");
      exit;
    }

    // 진행자(guest) 기준으로 사다리 인원 제한
    // config.사다리인원 값(이번 사다리 뽑을 인원 수)을 기준으로,
    // 실제 지급(status=1)이 그 인원을 넘지 않도록 제한
    if (!empty($row['guest'])) {
      $guest = addslashes($row['guest']);

      // 이번 사다리에서 사용할 인원 수 (0이면 제한 없음으로 간주)
      $설정Row = db_select("SELECT 사다리인원 FROM config LIMIT 1");
      $사다리최대인원 = isset($설정Row['사다리인원']) ? (int)$설정Row['사다리인원'] : 0;

      if ($사다리최대인원 > 0) {
        $현재지급인원 = db_select("
          SELECT COUNT(*) AS cnt
          FROM tb_sadari
          WHERE guest = '{$guest}'
            AND status = 1
        ");
        $현재지급인원수 = isset($현재지급인원['cnt']) ? (int)$현재지급인원['cnt'] : 0;
        if ($현재지급인원수 >= $사다리최대인원) {
          echo 전송("❌ 사다리 당첨 인원({$사다리최대인원}명) 결정되었습니다.");
          exit;
        }
      }
    }

    // status=1로 변경 (중복 수령 방지)
    db_query("UPDATE tb_sadari SET status = 1 WHERE idx = {$row['idx']} AND (status IS NULL OR status <> 1)");
    global $conn;
    $행수 = mysqli_affected_rows($conn);
    if ($행수 < 1) {
      // 거의 동시에 두 번 눌렀을 때 등을 대비
      echo 전송("❌ {$두자리닉넴} 이미 당첨 내역이 있습니다.");
      exit;
    }
    
    $지급표시 = function_exists('금고_금액표시')
      ? 금고_금액표시($지급액, $단위)
      : (function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($지급액, $단위) : ($지급액 . $단위));
    $msg = "🎉{$두자리닉넴} +" . $지급표시 . "\n";
    $msg .= " 사다리 당첨 지급 예정!\n";
    echo 전송($msg);
    exit;

  } else if (
    preg_match('/^ㅁ(.+)$/us', trim($status), $choM)
    && ($choRow = db_select("SELECT idx, answer, point FROM tb_question WHERE status = 0 LIMIT 1"))
    && !empty($choRow['idx'])
  ) {
    info1_cho_quiz_process_answer_submit($두자리닉넴, $단위, trim($choM[1]), $choRow);

  }else{

    // 정시 알림: 설정 시각(서버 H:i)과 같은 분에 이 분기로 들어온 첫 채팅일 때 1건 큐 등록 (날짜+시각당 1회, item=#sched:HH:MM 로 중복 방지)
    // ※ 해당 분에 채팅이 없으면 등록 안 됨. 크론 없을 때 보조용. 큐 조회 전에 실행해 같은 요청에서 전송되게 함.
    $정시알림_스케줄 = [
      ['time' => '08:00', 'msg' => "애들아 잘잤어? 굿모닝~^^"],
      ['time' => '10:00', 'msg' => "우리방은 커뮤니티라 대화를 통해 개인정보가 노출되지 않도록 조심하자~ 댓글 조심히 달자!"],
      ['time' => '11:00', 'msg' => "개인정보 유출되지 않게 조심하자!!"],
      ['time' => '12:00', 'msg' => "친구들 맛점하자~!!"],
      ['time' => '13:00', 'msg' => '보이스룸은 관을 포함해 총 3명 이상 시 이용가능!'],
      ['time' => '14:00', 'msg' => '출퇴근방법! 퇴근 원할 시 .퇴근 다시왔을땐 .출근'],
      ['time' => '15:00', 'msg' => '가방이 필요하면 연구실가서 .가방 입력해보자~'],
      ['time' => '16:00', 'msg' => '싸우는거 아니지..? 싸우지말자~^^'],
      ['time' => '17:00', 'msg' => "개인정보 유출되지 않게 조심하자!! 댓글 조심히 달것~"],
      ['time' => '18:00', 'msg' => '오늘 하루도 고생 많으셨어용 즐퇴 맛저~'],
      ['time' => '23:50', 'msg' => '애들아 잘자! 내일 또 즐거운 하루 보내자~^^'],
    ];
    if ($정시알림_스케줄) {
      $지금Hi = date('H:i');
      foreach ($정시알림_스케줄 as $정시행) {
        $설정시각 = isset($정시행['time']) ? trim((string)$정시행['time']) : '';
        $멘트 = isset($정시행['msg']) ? (string)$정시행['msg'] : '';
        if ($설정시각 === '' || $멘트 === '') {
          continue;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $설정시각, $tm)) {
          continue;
        }
        $설정Hi = sprintf('%02d:%02d', (int)$tm[1], (int)$tm[2]);
        if ($지금Hi !== $설정Hi) {
          continue;
        }
        $표식 = '#sched:' . $설정Hi;
        $표식_esc = addslashes($표식);
        $이미 = db_select("SELECT idx FROM tb_lotto_info WHERE item = '{$표식_esc}' AND DATE(regdate) = CURDATE() LIMIT 1");
        if (!empty($이미['idx'])) {
          continue;
        }
        $멘트_esc = addslashes($멘트);
        db_query("INSERT INTO tb_lotto_info SET status = 0, msg = '{$멘트_esc}', leverage = 0, item = '{$표식_esc}', regdate = NOW()");
      }
    }

    본방알림_큐_응답_시도();

    $채팅제한닉 = addslashes($두자리닉넴);
    $채팅제한행 = db_select("SELECT idx FROM tb_self WHERE nick = '{$채팅제한닉}' AND status = '채팅제한' AND enddate > NOW() ORDER BY enddate DESC LIMIT 1");
    if (!empty($채팅제한행['idx'])) {
      $채팅제한idx = (int)$채팅제한행['idx'];
      db_query("UPDATE tb_self SET enddate = DATE_ADD(enddate, INTERVAL 3 MINUTE) WHERE idx = {$채팅제한idx}");
      db_query("INSERT INTO tb_msg (nickname, msg, tasu, regdate) VALUES ('{$채팅제한닉}', '', -3, NOW())");
      db_query("UPDATE tb_member SET point = point - 3, tasu = tasu - 3 WHERE name = '{$채팅제한닉}'");
      $채팅제한끝 = db_select("SELECT enddate FROM tb_self WHERE idx = {$채팅제한idx} LIMIT 1");
      $채팅제한끝문구 = !empty($채팅제한끝['enddate']) ? date("H:i", strtotime($채팅제한끝['enddate'])) : '';
      echo 전송("⚠️ 채팅제한 3분 추가 / -3타 적용\n해제예정: {$채팅제한끝문구}");
      exit;
    }

  }


  

}

