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
list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
if ($두자리닉넴 === '' && trim((string)($정보['name'] ?? '')) !== '') {
  $두자리닉넴 = trim((string)$정보['name']);
}
include_once __DIR__ . '/config.php';

if (!api_동시요청_락_시작($nick, $status)) {
  exit;
}
register_shutdown_function('api_동시요청_락_해제');

if (!function_exists('info1_내냥_명령_처리')) {
  /** 본방(info1) .내냥 — 보유냥(newpoint)만 표시 (게임냥 미표시) */
  function info1_내냥_명령_처리($nick, $두자리닉넴) {
    list($두자리닉넴, $정보) = 회원정보_동기화($nick, $두자리닉넴);
    if (trim((string)($정보['name'] ?? '')) === '') {
      $debug = function_exists('nick_디버그_리포트') ? nick_디버그_리포트(true) : '';
      echo 전송("❌ 등록된 회원만 `.내냥`을 사용할 수 있어요." . ($debug !== '' ? "\n\n{$debug}" : ''));
      exit;
    }
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $계급 = 계급($정보['point'] ?? 0);
    $호칭 = !empty($정보['title']) ? $정보['title'] : ($계급['name'] ?? '');
    $msg = $호칭 . " {$두자리닉넴} " . newpoint표시($정보['newpoint'] ?? 0) . $단위표;
    echo 전송($msg);
    exit;
  }
}

// 본방 .내냥 — 최우선 (보유냥만)
if (trim((string)$status) === '.내냥') {
  info1_내냥_명령_처리($nick, $두자리닉넴);
}

// 본방(info1) 전용: .랭킹3 — 채굴 장비 랭킹 (전체 공개 · _mutual 관리자 제한 우회)
if (strpos($status, '.랭킹3') !== false) {
  require_once __DIR__ . '/game/mining_tool.inc.php';
  $desc = 0;
  if (preg_match('/\.랭킹3\s*(.+)/u', $status, $match)) {
    $desc = (int)trim($match[1]);
  }
  $랭킹이모지 = isset($이모티콘) ? (string)$이모티콘 : '';
  echo 전송(mining_tool_ranking_message($desc, $랭킹이모지));
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
        '/^내가박을(께|게|래|까)$/u',
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
      $msg = "\n\n📣✨ 신입 담당 안내 ✨📣\n"
        . "━━━━━━━━━━━━━━━━\n\n"
        . "🙋 [ {$두자리닉넴} ] 님이 신입 받을 거예요!!\n\n"
        . "🎨 프로필 색 지정될 때까지\n"
        . "🤫 친구들아 잠시만 조용해줘~ 쉿!\n\n"
        . "━━━━━━━━━━━━━━━━\n\n";
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

    if (strpos($status, '.색변') !== false) {
      info1_ensure_config_sinip_damdang();
      $설정 = db_select("SELECT `신입담당` FROM config LIMIT 1");
      $신입담당 = trim((string)($설정['신입담당'] ?? ''));

      if ($신입담당 === '') {
        echo 전송("❌ 아직 신입 담당자가 정해지지 않았어요.\n(담당자가 `내가받을께` 로 등록해야 해요)");
        exit;
      }
      if ($두자리닉넴 !== $신입담당) {
        echo 전송("❌ .색변은 신입 담당자 [ {$신입담당} ] 만 사용할 수 있어요.");
        exit;
      }

      if (preg_match('/^\.색변\s+([가-힣]{2})\s+(\d+)/u', $status_trim, $색변매치)) {
        $대상닉 = $색변매치[1];
        $색번호 = (int)$색변매치[2];
        if ($색번호 < 1) {
          echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 5");
          exit;
        }

        $대상_esc = addslashes($대상닉);
        $대상회원 = db_select("SELECT idx FROM tb_member WHERE name = '{$대상_esc}' AND status = 0 LIMIT 1");
        if (empty($대상회원['idx'])) {
          echo 전송("❌ [ {$대상닉} ] 회원이 없어요.");
          exit;
        }

        $신입보상 = newpoint비율계산(0.02); // 전체 newpoint 2%
        $담당보상 = newpoint비율계산(0.005); // 전체 newpoint 0.5%

        db_query("UPDATE tb_member SET num = '{$색번호}', couple = 0 WHERE name = '{$대상_esc}'");

        $담당_esc = addslashes($신입담당);
        if ($신입보상 > 0) {
          db_query("UPDATE tb_member SET newpoint = newpoint + {$신입보상} WHERE name = '{$대상_esc}'");
        }
        if ($담당보상 > 0) {
          db_query("UPDATE tb_member SET newpoint = newpoint + {$담당보상} WHERE name = '{$담당_esc}'");
        }
        db_query("UPDATE config SET `신입담당` = '' LIMIT 1");

        $msg = "🎉✨ 환영합니다 ✨🎉\n"
          . "━━━━━━━━━━━━━━━━\n\n"
          . "👋 [ {$대상닉} ] 친구야!\n"
          . "우리 방에 온 걸 진심으로 환영해!! 🎊🎈\n\n"
          . "🎨 프로필 색 적용 완료! (번호 {$색번호})\n\n"
          . "💰 신입 해제 보상 " . newpoint표시($신입보상) . "냥 지급!\n"
          . "🙋 담당 [ {$신입담당} ] 님\n"
          . "💰 신입 담당 보상 " . newpoint표시($담당보상) . "냥 지급!\n\n"
          . "━━━━━━━━━━━━━━━━";
        echo 전송($msg);
        exit;
      }

      echo 전송("❌ .색변 닉네임 번호 형식으로 입력해줘!\n예) .색변 바보 5");
      exit;
    }
  }
}

// 신입 담당·색변 — 2글자닉 안내 exit(아래)보다 먼저 처리
$status_trim_early = trim((string)$status);
if (
  info1_메시지_내가받을께인가($status)
  || $status_trim_early === '.담당취소'
  || strpos($status_trim_early, '.색변') !== false
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

    if ($첫인사) {
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

  $sql = "INSERT INTO tb_member SET
    status = 0,
    code = '{$신규코드_esc}',
    couple = 2,
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

// ─── 🌸 닉네임•키 프로필 입력 → tb_member.content 업데이트 ─────
// 첫 줄 닉네임•키의 2글자 닉 기준 저장. 최초 등록은 대리 가능, 수정은 본인만.
(function() use ($status, $두자리닉넴, $conn) {
  if (empty($두자리닉넴)) return;

  $프로필원본 = str_replace('\n', "\n", (string)$status);
  $프로필원본 = preg_replace('/\x{00A0}|\x{2000}-\x{200B}|\x{FEFF}/u', ' ', $프로필원본);
  $프로필원본 = str_replace('：', ':', $프로필원본);

  if (!preg_match('/닉네임\s*[•·・ㆍ]\s*키\s*:/u', $프로필원본)) return;
  if (strpos($프로필원본, '🌸') === false && strpos($프로필원본, '🤍') === false) return;

  $키값 = '';
  // 닉네임•키: 길동 / 닉네임•키:길동 / 닉네임•키 : 길동 — 콜론 앞뒤 공백 유무 모두 허용
  if (preg_match('/(?:🌸|🤍)?\s*닉네임\s*[•·・ㆍ]\s*키\s*:\s*([^\r\n]+)/u', $프로필원본, $키m)) {
    $키값 = trim($키m[1]);
  }
  // 값이 다음 줄에 있는 경우 (닉네임•키: ↵ 길동)
  if ($키값 === '' && preg_match('/(?:🌸|🤍)?\s*닉네임\s*[•·・ㆍ]\s*키\s*:\s*[\r\n]+([^\r\n]+)/u', $프로필원본, $키m2)) {
    $키값 = trim($키m2[1]);
  }
  if ($키값 === '') return;

  $프로필닉 = getTwoCharNick($키값);
  if ($프로필닉 === '') return;

  if (!isset($conn) || !$conn) return;

  $name_esc = mysqli_real_escape_string($conn, $프로필닉);
  $회원 = db_select("SELECT idx, IFNULL(welcome, 0) AS welcome, gender, content FROM tb_member WHERE name = '{$name_esc}' AND status = 0 LIMIT 1");
  if (empty($회원['idx'])) return;

  $기존본문 = trim((string)($회원['content'] ?? ''));
  $수정요청 = ((int)$회원['welcome'] === 1) || $기존본문 !== '';
  if ($수정요청 && $두자리닉넴 !== $프로필닉) {
    echo 전송("❌ {$프로필닉} 프로필 수정은 본인만 가능해!");
    exit;
  }

  $본문 = str_replace('\n', "\n", trim($status));
  if ($본문 !== '' && strpos($본문, "\n") === false && preg_match_all('/🌸[^🌸]+/u', $본문, $블록m)) {
    $본문 = implode("\n", array_map('trim', $블록m[0]));
  }

  $content_esc = mysqli_real_escape_string($conn, $본문);
  db_query("UPDATE tb_member SET content = '{$content_esc}' WHERE name = '{$name_esc}' AND status = 0");
  db_query("INSERT INTO tb_member_profile SET name = '{$name_esc}', content = '{$content_esc}', regdate = NOW()");

  if ((int)$회원['welcome'] === 1) {
    echo 전송("{$프로필닉}!! 프로필 저장 완료 🌸");
    exit;
  }

  db_query("UPDATE tb_member SET welcome = 1 WHERE name = '{$name_esc}'");

  $축하 = "🎉 {$프로필닉} 공질 등록 완료 🌸\n\n이제 `.색표` 입력해서 색변도 해보자!";
  echo 전송($축하);
  exit;
})();
// ─────────────────────────────────────────────────────────────────────────────

// 본방 .이체 — 게임냥(point) 지급/차감 (관리자)
if (strpos($status, '.이체') !== false) {
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  관리자_명령_차단($두자리닉넴, $nick ?? '');
  if (!preg_match('/\.이체\s*(.+)/u', $status, $이체매치)) {
    echo 전송("❌ 사용법: .이체 닉네임 금액\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $after = trim($이체매치[1]);
  if (!preg_match('/^(\S+)\s+(.+)$/u', $after, $parts)) {
    echo 전송("❌ 사용법: .이체 닉네임 금액\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $받는닉 = trim($parts[1]);
  $지급양 = function_exists('냥_금액_파싱_부호포함') ? 냥_금액_파싱_부호포함(trim($parts[2])) : 0;
  if ($지급양 === 0) {
    echo 전송("❌ 금액 형식을 확인해주세요.\n예) .이체 진우 1조 / .이체 뮤뮤 26경8천조 / .이체 진우 -1조");
    exit;
  }
  $받는닉_esc = addslashes($받는닉);
  $받는친구 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$받는닉_esc}' LIMIT 1");
  if (empty($받는친구['idx'])) {
    echo 전송("존재하지 않는 사용자 입니다.");
    exit;
  }
  $금액표시 = function_exists('게임냥_경조억표시') ? 게임냥_경조억표시(abs($지급양)) : number_format(abs($지급양));
  if ($지급양 < 0) {
    $차감양 = abs($지급양);
    db_query("UPDATE tb_member SET point = point - {$차감양} WHERE name = '{$받는닉_esc}'");
    $지급여부 = '차감';
    $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 이체 차감!";
  } else {
    db_query("UPDATE tb_member SET point = point + {$지급양} WHERE name = '{$받는닉_esc}'");
    $지급여부 = '이체';
    $msg = "💰 {$받는친구['name']}에게 {$금액표시} 게임냥 이체 지급!";
  }
  지급로그($지급여부, $두자리닉넴, $받는친구['name'], 0, $지급양);
  echo 전송($msg);
  exit;
}

// 본방 .스왑 — 보유냥 전액 (10% 소멸 · 90% 게임냥)
if (trim($status) === '.스왑') {
  if (!function_exists('스왑_본방_실행')) {
    require_once __DIR__ . '/game/swap.inc.php';
  }
  스왑_본방_실행($두자리닉넴, null, true);
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

// 본방 .입금 — 보유냥(newpoint) 지급/차감 (_mutual.php point 입금보다 먼저 처리)
if (strpos($status, '.입금') !== false && preg_match('/\.입금\s*(.+)/u', $status, $입금매치)) {
  // $관리자·$단위는 api/config.php 에서 로드 — _mutual.php include 전이라 여기서 먼저 불러옴
  if ($두자리닉넴 !== '') {
    include_once __DIR__ . '/config.php';
  }
  $after = trim($입금매치[1]);
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
      $msg = "📣 전체 인원에게 랜덤박스 {$지급개수}개씩 입금 완료!";
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
        $msg = "💰 {$받는친구['name']}에게 " . newpoint표시($지급양) . "{$단위} 입금!";
      }
      $받는친구명 = $받는친구['name'];
    }
    지급로그($지급여부, $두자리닉넴, $받는친구명, 0, $지급양);
    echo 전송($msg);
    exit;
    }
  }

  // 일괄: .입금 닉1 닉2 ... 닉N 금액 (줄바꿈·여러 칸 공백 허용, 마지막 토큰이 금액)
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
        echo 전송("❌ 일괄 입금할 닉네임을 입력해주세요.");
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
        $msg = "❌ 일괄 {$지급여부} 실패 — 처리된 인원이 없어요.";
        if (count($실패목록) > 0) {
          $msg .= "\n없는 닉: " . implode(', ', $실패목록);
        }
        echo 전송($msg);
        exit;
      }

      $msg = "💰 일괄 {$지급여부} 완료 (" . count($성공목록) . "명 × " . newpoint표시(abs($지급양)) . "{$단위})\n";
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

  echo 전송("❌ 사용법: .입금 닉네임 금액\n예) .입금 명수 50 / .입금 뮤뮤 26경8천조\n\n일괄 예)\n.입금 여름 나비 스리 50000\n(닉 여러 개 + 마지막 금액, 줄바꿈 가능)");
  exit;
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
  if (preg_match('/\.생성\s+(\S+)\s+(\S+)\s+(\d+)/u', $status, $생성m)) {
    $생성결과 = 아이템_생성_지급($생성m[1], $생성m[2], $생성m[3]);
    echo 전송($생성결과['msg']);
    exit;
  }
  echo 전송("❌ 사용법: .생성 닉네임 템명 개수\n예) .생성 진우 수호 5\n예) .생성 전체 1주년기념주화 1");
  exit;
}

// 본방(info1) 전용: `.주문` — 아영이네 마켓 판매중 목록
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

// 본방(info1): 로또 구매 성공 시 내역·완료 메시지 미전송 / 일반 구매는 홍보방
$LOTTO_PURCHASE_SILENT = true;
$MUTUAL_SKIP_LOTTO = true;

// 본방(info1) 전용: `.로또 자동 N` (1~50) — 티켓 N장 차감, 조용히 구매
if ($두자리닉넴 && preg_match('/^\.로또\s*자동(?:\s+(\d+))?\s*$/u', trim($status), $_로또자동m)) {
  require_once __DIR__ . '/game/lotto_purchase.inc.php';
  $요청개수 = 1;
  if (isset($_로또자동m[1]) && $_로또자동m[1] !== '') {
    $요청개수 = (int)$_로또자동m[1];
  }
  if ($요청개수 < 1) {
    echo 전송("❌ 구매 개수는 1 이상이어야 해요.\n예) .로또 자동 5");
    exit;
  }
  $최대일괄 = 50;
  if ($요청개수 > $최대일괄) {
    echo 전송("❌ 한 번에 최대 {$최대일괄}개까지만 가능해요. (요청: {$요청개수}개)");
    exit;
  }
  로또_번호구매_실행([
    '요청개수' => $요청개수,
    'isAuto' => true,
    'useTicket' => true,
  ]);
  exit;
}

// 본방(info1)에서는 홀짝(.도전/ㅈㅈ) 미적용 — 홍보방(info2) 등에서만 odd_even 루틴 로드
$MUTUAL_SKIP_ODD_EVEN = true;
$MUTUAL_SKIP_RANKING3 = true;

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
    $_g2 .= "• 명령어: `.로또 1,22,33` (수동·게임냥) / `.로또 자동` / `.로또 자동 1~50` (티켓)\n";
    $_g2 .= "• 1~45 중 중복 없이 3개 번호 (누적 구간마다 장당 +50만)\n";
    $_g2 .= "• 🎟️ 로또 티켓: 레벨업 시 구간별 15~300장 (`.레벨업` 참고)\n";
    $_g2 .= "• 예상 당첨금/총액: `.로또` · 내 구매내역: `.로또 내역`\n";
    $_g2 .= "• (`.로또 추첨` · `.로또 지급` 은 관리자)\n\n";

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

  }else if($status==".인원"){
    $sql = "SELECT * FROM tb_member where name != '지유' and name != '민호' ORDER BY RAND(); ";
    $result = db_query($sql);

    $names1 = [];
    $names2 = [];
    $names3 = [];
    while ($row = db_fetch($result)) {
      $가입시각 = !empty($row['regdate']) ? strtotime($row['regdate']) : false;
      $신입보호중 = ($가입시각 !== false && $가입시각 > strtotime('-1 days'));

      if ($신입보호중) {
        // 🐤 가입 5일 미만 전원 (`.연두` 등과 동일 기준)
        $lines = explode("\n", $row['content'] ?? '');
        $second_line = $lines[1] ?? '';
        $사는곳 = preg_replace('/[🥕🍭❄️🤍🌸]\s*지역\(시•군\)\s*:\s*/u', '', $second_line);
        $사는곳 = trim($사는곳);
        $names3[] = $사는곳 !== '' ? $row['name'] . '(' . $사는곳 . ')' : $row['name'];
      } elseif ($row['gender'] == 1) {
        $names1[] = $row['name'];
      } elseif ($row['gender'] == 2) {
        $names2[] = $row['name'];
      }
    }

    $cnt1 = count($names1);
    $cnt2 = count($names2);
    $cnt3 = count($names3);
    $총인원 = $cnt1 + $cnt2 + $cnt3;

    $html1 = "🚹 ({$cnt1}명)<br>";
    $html2 = "🚺 ({$cnt2}명)<br>";
    $html3 = "🐤 ({$cnt3}명)<br>";

    if (!empty($names1)) {
      $html1 .= implode(' ', $names1) . '<br>';
    }

    if (!empty($names2)) {
      $html2 .= implode(' ', $names2) . '<br>';
    }

    if (!empty($names3)) {
      
      $html3 .= implode(' ', $names3) . '<br>';
    }

    $html = "📊 총 {$총인원}명\n\n".$html1."\n".$html2."\n".$html3;

    echo 전송($html);
    exit;

  }else if(trim($status) === '.고인물'){
    $result = db_query("
      SELECT name, regdate
      FROM tb_member
      WHERE status = 0
        AND name NOT IN ('지유', '민호')
      ORDER BY regdate ASC, idx ASC
    ");
    $lines = [];
    $n = 0;
    while ($row = db_fetch($result)) {
      $n++;
      $lines[] = $n . '. ' . $row['name'];
    }
    if ($n === 0) {
      echo 전송('✅ 고인물 목록이 없어요.');
      exit;
    }
    $msg = "✅ 고인물 · 입장순 (총 {$n}명)\n\n" . implode("\n", $lines);
    echo 전송($msg);
    exit;

  }else if($status==".버프"){
    require_once __DIR__ . '/buff.inc.php';
    버프_명령_처리($status, $두자리닉넴, (array)($관리자 ?? []), ['마법라벨' => '마법(타수2배)']);

  }else if (strpos($status, '.양도') !== false) {

    $보낼양 = 30000;
    $수수료 = 양도수수료계산((int)$정보['level'], $보낼양);
    $fail = "✅ {$단위} 양도\n\n.양도 받을닉 양도할 {$단위}수량";
    $fail.= "\n양도금액 기준 수수료 (금고 적립)";
    $fail.= 양도수수료_안내문();
    $fail.= "\n\n-유의사항";
    $fail.= "\n본방냥·게임냥 합쳐 하루 1회 무료";
    $fail.= "\n본방에서 오늘 양도했으면 게임방 양도 불가 (지호로 추가 가능)";
    $fail.= "\n지호 적용 시 남은 시간(1시간)마다 추가 양도 가능 (추가 1회당 지호 -1시간)";
    $fail.= "\n최소 30000{$단위} 이상만 양도 가능";
    $fail.= "\n현재 예상 수수료 {$수수료}{$단위} (30000{$단위} 전송 시 수령 ".(30000 - $수수료)."{$단위})";

    if (preg_match('/\.양도\s*(.+)/u', $status, $match)) {
        $after = trim($match[1]); // ".양도" 뒤 텍스트 추출

        // "도하 5" 형태인지 검사 + 그룹 분리
        if (preg_match('/^([가-힣A-Za-z]+)\s*(\d+)$/u', $after, $parts)) {
          //$수수료 = $계급['tax'];
          $받는이 = $parts[1]; // 도하
          $보내는양  = (int)$parts[2];
          if ($보내는양 < 100) {
            echo 전송("양도는 150{$단위} 이상만 가능합니다.");
            exit;
          }

          $양도제한 = function_exists('양도_일일제한_검사') ? 양도_일일제한_검사($두자리닉넴) : null;
          if ($양도제한 !== null) {
            echo 전송($양도제한);
            exit;
          }

          $지호소비 = function_exists('양도_지호추가양도_해당') && 양도_지호추가양도_해당($두자리닉넴);
          $수수료 = 양도수수료계산((int)$정보['level'], $보내는양);

          $실제받는양 = $보내는양;

          $sql = "select * from tb_member where name = '{$받는이}' ";
          $받는친구 = db_select($sql);
          if(!$받는친구['idx']){
            echo 전송("[ {$받는이} ] 친구는 없음");
            exit;
          }

          if($두자리닉넴==$받는이){
            echo 전송("본인에게 보낼 수 없음");
            exit;
          }

          $보유냥 = newpoint양도가능($정보['newpoint'] ?? 0);
          if($보유냥 <= 0){
            $msg = $호칭." {$두자리닉넴} 양도 가능 {$단위} 없음 (보유 ".newpoint표시($정보['newpoint'] ?? 0)."{$단위})";
            echo 전송($msg);
            exit;
          }

          $수수료제외 = $보내는양 - $수수료;
          if($수수료제외 < 1){
            echo 전송("양도 금액이 너무 적습니다.");
            exit;
          }
          if($보유냥 < $보내는양){
            $msg = "✅ 보유 {$단위} 부족\n";
            $msg.= $호칭." {$두자리닉넴}\n보유{$단위} : ".newpoint표시($정보['newpoint'] ?? 0)."{$단위}\n양도 요청 : ".number_format($보내는양) . "{$단위}";
            echo 전송($msg);
            exit;
          }else{
            db_query("update tb_member set newpoint = newpoint - {$보내는양} where name = '{$두자리닉넴}' ");
            db_query("update tb_member set newpoint = newpoint + {$수수료제외} where name = '{$받는이}' ");
            db_query("UPDATE config SET tax = tax + {$수수료} ");
            $지호차감문구 = ($지호소비 && function_exists('양도_지호_소비')) ? 양도_지호_소비($두자리닉넴) : '';
            지급로그('양도', $두자리닉넴, $받는이, $수수료, $실제받는양);
            지급로그('수수료', $두자리닉넴, '', $수수료, $수수료);
            $msg = "💰 {$두자리닉넴}→{$받는이} ".냥축약표시($수수료제외)." (수수료 ".냥축약표시($수수료).")";
            if ($지호차감문구 !== '') {
              $msg .= "\n" . $지호차감문구;
            }
            echo 전송($msg);
            exit;
          }

        } else {
          echo 전송($fail);
          exit;
        }
    } else {
        echo 전송($fail);
        exit;
    }

  

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
(보룸/채팅/게임 제한은 하루 횟수 제한 없음)";

  $아이템설명['선물'] = "🎁 선물
# 보유중인 아이템 1개를 지정인에게 선물할 수 있다.";

  $아이템설명['닉변'] = "🪪 닉네임 변경 (지속)
# 중복되지 않는 닉네임을 1회 변경할 수 있다.";

  $아이템설명['강일'] = "💌 강제 일방(12시간)
# 아래의 조건의 일대일 채팅방을 상대방 동의 없이
강제로 개설할 수 있다.(공커, 일방에게는 불가)
• 보룸금지(모니터링이 되지 않는 이슈)
• 참여자 중 1인이라도 종료 시 강제 일방 종료
• 방장 참관조건 단, 문제가 없을 경우 모니터링 ❌
강일 이후 12시간 추가 강일,일방신청 제한";

  $아이템설명['프변'] = "🖍 프로필 변경
# 아래의 사항 중 한가지를 적용할 수 있다.
• 프로필 내 이모티콘 등록 3일
• 프로필 내 텍스트 등록 3일
• 닉네임 뒤 이모티콘 또는 텍스트 등록 3일
  명령어 .프변";

  $아이템설명['색변'] = "📝 색 등본
# 지정한 프로필 색으로 1회 변경 및 방어가 가능하다.";

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
• 오늘 양도 1회 무료 · 지호 1시간마다 추가 양도 1회 (추가 시 지호 -1시간)
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

▫️자유롭게 개설 및 이용가능
▫️3인 이상시 마이크 (1인•2인인경우 마이크 OFF)
▫️봇 제외 3인 마이크 ON 
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
▫️일벙날짜가 결정되면 한 명이 상황실에 날짜와 대략적인 시간대 공지
① 일벙인증 : 두 사람 손하트 🫰🏻후 공창
② 공커인증 : 두 사람 손깍지 🤝🏻 후 공창");
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
 }else if(strpos($status, '.확인공지') !== false){
    require_once __DIR__ . '/notice.inc.php';
    공지_확인_처리($두자리닉넴);

  }else if(strpos($status, '.모금') !== false){
    $최소모금 = 전체냥기준금액(0.01, true);
    $모금파싱 = function_exists('모금_명령_파싱') ? 모금_명령_파싱($status) : ['nick' => null, 'amount_text' => ''];
    $nick = $모금파싱['nick'];
    $금액텍스트 = $모금파싱['amount_text'];

    if ($금액텍스트 !== '') {
      if($정보['point'] < $최소모금){
        $보유표시 = function_exists('냥축약표시') ? 냥축약표시($정보['point']) : number_format($정보['point']) . "냥";
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
      $금액 = (int)($검증['금액'] ?? 0);

      if($nick){
        $존재여부 = db_select("select count(*) as cnt from tb_self where nick = '{$nick}' and status != '생자' ");
        if($존재여부['cnt']==0){
          echo 전송("[ {$nick} ] 친구는 자숙중이 아닙니다..");
          exit;
        }
        if (function_exists('모금_대상단가_검증')) {
          $단가검증 = 모금_대상단가_검증($금액, $nick);
          if (empty($단가검증['ok'])) {
            echo 전송((string)($단가검증['msg'] ?? '❌ 모금 금액이 부족해요.'));
            exit;
          }
        }
      }

      $seconds = function_exists('모금_금액to초') ? 모금_금액to초($금액, $nick) : 초단위변환($금액);
      $기존 = db_select("select nick from tb_loan where nick = '{$두자리닉넴}' limit 1 ");
      if (empty($기존['nick'])) {
          $sql = "insert into tb_loan set nick = '{$두자리닉넴}', point = {$금액}, times = {$seconds}, regdate = now() ";
          db_query($sql);
      } else {
          db_query("update tb_loan set point = point + {$금액}, times = times + {$seconds}, regdate = now() where nick = '{$두자리닉넴}' ");
      }

      if($nick){
        $_nick = " and nick = '{$nick}' ";
      }

      $sql = "UPDATE tb_self SET enddate = DATE_SUB(enddate, INTERVAL {$seconds} SECOND) WHERE (status = '공커' || status = '일방') {$_nick} ";
      db_query($sql);
      db_query("update tb_member set point = point - {$금액} where name = '{$두자리닉넴}' ");
      if ($seconds >= 60) {
          $min = intdiv($seconds, 60);
          $sec = $seconds % 60;
          $times = ($sec > 0) ? "{$min}분 {$sec}초" : "{$min}분";
      } else {
          $times = "{$seconds}초";
      }
      $변환냥 = function_exists('냥축약표시') ? 냥축약표시($금액) : number_format($금액) . "냥";
      if($nick){
        $tg = "{$nick}에게 위로를..";
      }else{
        $tg = "자숙인에게 희망을...";
      }
      $msg = "👼{$두자리닉넴} {$변환냥} 모금 고마워💕\n{$tg} {$times} 단축";
      echo 전송($msg);
      exit;


    }else{
      $최소표시 = function_exists('냥축약표시') ? 냥축약표시($최소모금) : number_format($최소모금) . "냥";
      $msg = ". 모금 길동 1조 입력시
-'길동'의 시간만 자숙시간단축

. 모금 1조 입력시
- 전체 자숙 12초 단축

금액: 숫자 또는 만·억·조·천·백 (예: 1조, 2천2백억, 3천억)
최소 {$최소표시} 부터 가능";
echo 전송($msg);
exit;
    }

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
      $msg.= $row['nick']." ".date("m-d H:i", strtotime($row['regdate']))."\n";
    }

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
    $sql = "insert into tb_work set status = '{$명령_esc}', nick = '{$대상_esc}', regdate = '{$이틀}'  ";
    $result = db_query($sql);
    db_query("update tb_member set status = 3 where name = '{$대상_esc}' ");
    if($result){
      $성공본문 = "{$대상} 퇴근등록🚌\n{$타임}시간 내 돌아오자.\n";
      $성공본문.= "\n-미복귀시\n공질•아이템•{$단위} 자동삭제";
      $성공본문.= "\n\n우리방 검색어\n[ 진우썸 ]";
      $msg최신 = "✅ 퇴근자 명단\n\n";
      $rs최신 = db_query("SELECT *
        FROM tb_work
        ORDER BY
            CASE
                WHEN status = '퇴근' THEN 1
                ELSE 2
            END,
            regdate DESC");
      while ($row최신 = db_fetch($rs최신)) {
        $msg최신 .= $row최신['nick'] . " " . date("m-d H:i", strtotime($row최신['regdate'])) . "\n";
      }
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

    $data = db_select("SELECT * FROM tb_work WHERE nick = '{$출근대상_esc}' LIMIT 1");
    if ($data['idx']) {
      $result = db_query("DELETE FROM tb_work WHERE nick = '{$출근대상_esc}' LIMIT 1");
      // 출근(복귀): 정상 멤버 상태 (퇴근 시 status=3 와 대칭)
      db_query("UPDATE tb_member SET status = 0 WHERE name = '{$출근대상_esc}' LIMIT 1");
      if ($result) {
        echo 전송("{$출근대상} 어서와!!🎉");
        exit;
      }
    }

    echo 전송("퇴근 상태가 아니에요. `.퇴근` 후 이용해 주세요.");
    exit;
  }else if(strpos($status, '.명령') !== false){
    $msg = "✅ 우리방 명령어\n\n";
    $msg .= "📖 .사용설명서 (모바일 가이드 웹)\n\n";
    $msg .= "【일반】\n";
    $msg .= ".내냥 (보유냥)\n";
    $msg .= ".제발\n";
    $msg .= ".공질\n";
    $msg .= ".샘플(공질샘플)\n";
    $msg .= ".신입(주의사항)\n";
    $msg .= ".인원(남여비율)\n";
    $msg .= ".고인물(입장순)\n";
    $msg .= ".연두(미출석자)\n";
    $msg .= ".투표 제목 / .찬성 / .반대 / .투표종료(관리자)\n";
    $msg .= ".진행(일방/강일/지목)\n";
    $msg .= ".자숙(일방/강일/제한)\n";
    $msg .= ".미션(일방 신청 조건)\n";
    $msg .= ".평타(전체평균타수)\n";
    $msg .= ".생타(오늘 생타순위 · 생타/버프타)\n";
    $msg .= ".닉추천(GPT 2글자 닉)\n";
    $msg .= ".아이템\n";
    $msg .= ".버프(프변,지호)\n";
    $msg .= ".사다리 10 4 (평타 상위 10명 중 4명 랜덤)\n";
    $msg .= ".랭킹1 (보유냥 순위) · .랭킹2 (게임냥 순위) · .랭킹3 (채굴 장비 순위)\n";
    $msg .= "💰.양도 ({$단위} 양도)\n";
    $msg .= "--------\n";
    $msg .= "【유료·GPT】\n";
    $msg .= "💰.궁금 닉네임\n";
    $msg .= ".강일연금 (강일 연금 전체)\n";
    $msg .= ".지목연금 (지목 연금 전체)\n";
    $msg .= ".공커연금 (공커 연금 수령)\n";
    $msg .= "💰.메뉴추천(5만냥·GPT 점심)\n";
    $msg .= "💰.맛집 지역(5만냥·GPT 맛집 3곳)\n";
    $msg .= "💰.mbti 닉\n";
    
    echo 전송($msg);
    exit;

  }else if(strpos($status, '.공커연금') !== false){
    공커연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.강일연금') !== false){
    강일연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.지목연금') !== false){
    지목연금_명령_처리($status, $두자리닉넴);

  }else if(strpos($status, '.궁금') !== false){

    if($정보['newpoint'] < 0){
      exit;
    }

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

            $궁금조회비 = 2;
            if ((float)($받는친구['newpoint'] ?? 0) < $궁금조회비) {
              echo 전송("❌ [{$닉네임}] 본방냥이 " . newpoint표시($궁금조회비) . "냥 미만이라 .궁금 조회를 할 수 없습니다.\n(현재 " . newpoint표시($받는친구['newpoint'] ?? 0) . "냥)");
              exit;
            }


          // 본인 .궁금일 때만 오늘 tb_lotto_info 일일 타수·보상(100/500·계급, 1000·2000·3000 로우 보상) 내역

            $msg.= "\n".$받는친구['content']."\n";


            $sql = "select 
            " . 생타_SQL_select_expr('msg') . " as cnt2,
            sum(tasu) as cnt 
            from tb_msg 
            where nickname = '{$닉네임}' AND 
            tasu != 0 AND
            regdate >= CURDATE() AND 
            regdate < CURDATE() + INTERVAL 1 DAY ";
            $타수 = db_select($sql);
  
            $msg.= "\n타수 : {$타수['cnt2']}/{$타수['cnt']}타";
            $msg.= "\n본방냥 : ".newpoint표시($받는친구['newpoint'] ?? 0)."냥";
            $msg.= "\n게임냥 : ".number_format((int)($받는친구['point'] ?? 0));

            
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

            $마지막카톡 = db_select("select msg, regdate from tb_msg where nickname = '{$닉네임}' order by regdate desc limit 1 ");
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

            $대상_esc = addslashes($닉네임);
            $조회자_esc = addslashes($두자리닉넴);

            db_query("insert into tb_curious set nickname = '{$조회자_esc}', youname = '{$대상_esc}' ");
            $받는친구['newpoint'] = (float)($받는친구['newpoint'] ?? 0) - $궁금조회비;
            

     

            $닉_sql = addslashes($닉네임);
            $오늘보상일 = date('Y-m-d');
            $보상_rs = db_query("
                SELECT msg, regdate
                FROM tb_lotto_info
                WHERE item = '{$닉_sql}'
                  AND DATE(regdate) = '{$오늘보상일}'
                  AND (
                    (msg LIKE '%100타 달성%' AND msg LIKE '%출석미션%')
                    OR msg LIKE '%500타 달성%'
                    OR msg LIKE '%랜덤박스 30개 지급%'
                    OR msg LIKE '%랜덤박스 60개 지급%'
                    OR msg LIKE '%랜덤박스 100개 지급%'
                  )
                ORDER BY
                  CASE
                    WHEN msg LIKE '%출석미션%' AND msg LIKE '%100타 달성%' THEN 1
                    WHEN msg LIKE '%500타 달성%' THEN 2
                    WHEN msg LIKE '%랜덤박스 30개 지급%' THEN 3
                    WHEN msg LIKE '%랜덤박스 60개 지급%' THEN 4
                    WHEN msg LIKE '%랜덤박스 100개 지급%' THEN 5
                    ELSE 99
                  END,
                  regdate ASC
            ");
            if ($보상_rs && mysqli_num_rows($보상_rs) > 0) {
              $msg .= "\n📋 오늘 타수·보상 내역\n";
              while ($br = db_fetch($보상_rs)) {
                if (!empty($br['msg'])) {
                  $msg .= '· ' . trim($br['msg']) . "\n";
                }
              }
            }

            if ($두자리닉넴 !== $닉네임) {
              db_query("UPDATE tb_member SET newpoint = newpoint - {$궁금조회비} WHERE name = '{$조회자_esc}' LIMIT 1");
              지급로그('궁금-조회', $닉네임, $두자리닉넴, 0, $궁금조회비);
              $msg .= "\n💰 {$두자리닉넴} " . newpoint표시($궁금조회비) . "냥 차감\n";
            }

          echo 전송($msg);
          exit;
        }
    }
    echo 전송(".궁금 친구닉네임");
    exit;

  }else if(strpos($status, '.생타') !== false){
    require_once __DIR__ . '/game/saengta.inc.php';
    생타_명령_처리($status, $두자리닉넴, $관리자);

  }else if(strpos($status, '.공커등록') !== false){

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

  }else if (strpos($status, '.공커') !== false || strpos($status, '.커플') !== false) {

    if (!isset($where)) $where = "";
    공커_sort_sdate재배치($where);

    $전체보유냥 = (float)($전체포인트['total_point'] ?? 0);
    $공커적립 = (int)floor($전체보유냥 * 0.0001);
    공커_amount_갱신($공커적립, $where);
    if (function_exists('공커연금_누적갱신')) {
      공커연금_누적갱신();
    }
    $공커연금단가 = function_exists('공커연금_단가') ? 공커연금_단가() : 500;

      $msg = "👩‍❤️‍👨 공커\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
      $msg .= "공커연금 1일당 " . number_format($공커연금단가) . "냥\n\n";
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
          $msg .= $row['couple']." +{$days}\n({$요일[$day]} {$row['edate']}{$imminent1} )\n".공커대실권_표시($row['amount'])."\n";
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
    $전체보유 = (float)전체보유newpoint합계();
    $전체게임냥 = function_exists('시세기준_게임냥_문자열')
      ? 시세기준_게임냥_문자열()
      : (function_exists('시세기준_게임냥') ? 시세기준_게임냥() : '0');

    $msg = "📊 시세 기준 (".시세기준_갱신시각_표시().")\n";
    $msg .= "✅ 본방 {$단위} : " . newpoint표시($전체보유) . "\n";
    $msg .= "✅ 게임 {$단위} : " . 랭킹_게임냥표시($전체게임냥, '') . "\n";
    if (!function_exists('스왑_1보유냥당_게임냥')) {
      require_once __DIR__ . '/game/swap.inc.php';
    }
    $스왑1냥 = function_exists('스왑_1보유냥당_게임냥_문자열')
      ? 스왑_1보유냥당_게임냥_문자열()
      : 스왑_1보유냥당_게임냥();
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

    $관리자주급 = (int)floor($전체보유 * 0.005); // 전체의 0.5%
    $일반보급 = (int)floor($전체보유 * 0.005);   // 전체의 0.5%
    $신입지원금 = (int)floor($전체보유 * 0.02); // 전체의 2%
    $신입담당보상 = (int)floor($전체보유 * 0.005); // 전체의 0.5%
    $생일자보상 = (int)floor($전체보유 * 0.10); // 전체 본방냥 10%

    $msg.= "\n\n📋 보상 (현재 기준)";
    $msg.= "\n신입 지원금 2% : " . newpoint표시($신입지원금) . "{$단위}";
    $msg.= "\n신입 담당 보상 0.5% : " . newpoint표시($신입담당보상) . "{$단위}";
    $msg.= "\n생일자 : " . newpoint표시($생일자보상) . "{$단위}";

    echo 전송($msg);
    exit;

  }else if (trim($status) === '.보조금지급') {
    if (!in_array($두자리닉넴, $관리자, true)) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }
    $주급결과 = 주급지급_실행();
    $보급결과 = 보급지급_실행();
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

    // 유효성 체크: 포인트 50,000 이상 필요
    $mbti비용 = 1000000000;
    if ((int)$정보['point'] < $mbti비용) {
        echo 전송("❌ MBTI 궁합 조회는 {$mbti비용}냥이 필요해요! (현재 보유: ".number_format($정보['point']) . "냥)");
        exit;
    }

    // 포인트 차감
    db_query("UPDATE tb_member SET point = point - {$mbti비용} WHERE name = '{$두자리닉넴}' ");

    $msg = "{$mbti1} {$두자리닉넴} ❤️ {$mbti2} {$지정자1}\n";
    
    $answer = callGPT("{$mbti1} 와 {$mbti2} 전체적인 궁합 점수, 속궁합 점수, 100자 이내로 알려주고 {$mbti1}랑 궁합이 잘맞는 mbti도 추천해줘! ");
    $msg.= "\n".$answer."\n\n -".number_format($mbti비용) . "냥 차감!";

    echo 전송($msg);
    exit;



  }else if(strpos($status, '.사다리종료') !== false){

    // 진행자 기준, status=1(당첨 처리된) 사다리 금액 실제 지급
    $게스트 = addslashes($두자리닉넴);
    $sql = "
      SELECT nick, amount
      FROM tb_sadari
      WHERE guest = '{$게스트}' AND status = 1
    ";
    $res = db_query($sql);

    $rows = [];
    $총지급 = 0;
    while ($r = db_fetch($res)) {
      $rows[] = $r;
      $총지급 += (int)$r['amount'];
    }

    if (empty($rows)) {
      echo 전송("지급할 사다리 당첨 내역이 없습니다.");
      exit;
    }

    // 금고 잔액 확인
    $configRow = db_select("select tax from config limit 1");
    $금고잔액 = isset($configRow['tax']) ? (int)$configRow['tax'] : 0;
    if ($금고잔액 < $총지급) {
      echo 전송("❌ 금고 잔액이 부족합니다.\n필요: ".number_format($총지급) . "{$단위}\n현재: ".number_format($금고잔액) . "{$단위}");
      exit;
    }

    // 실제 지급 처리
    foreach ($rows as $r) {
      $닉 = addslashes($r['nick']);
      $amt = (int)$r['amount'];
      if ($amt <= 0) continue;

      db_query("UPDATE tb_member SET point = point + {$amt} WHERE name = '{$닉}'");
      지급로그('사다리지급', $닉, '', 0, $amt);
    }
    db_query("UPDATE config SET tax = tax - {$총지급}");

    // 지급된 내역은 삭제
    db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' ");

    // 사다리 종료 시 config.사다리인원 0으로 초기화
    db_query("UPDATE config SET 사다리인원 = 0");

    $msg = "✅ 사다리 종료 및 지급 완료\n";
    $msg .= "진행자: {$두자리닉넴}\n\n";
    $msg .= "📦 개별 지급 내역\n";
    foreach ($rows as $r) {
      $msg .= "- {$r['nick']} +" . number_format($r['amount']) . "{$단위}\n";
    }
    $msg .= "\n총 " . number_format($총지급) . "{$단위} 지급 (금고에서 차감)";

    echo 전송($msg);
    exit;

}else if(strpos($status, '.사다리현황') !== false){

    // 진행자 기준 사다리 당첨 현황 조회
    $게스트 = addslashes($두자리닉넴);
    $sql = "
      SELECT nick, rank, tasu, amount, IFNULL(status,0) AS status
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

    $msg = "🎯 사다리 현황 - {$두자리닉넴}\n\n";
    foreach ($rows as $i => $r) {
      $상태 = ((int)$r['status'] === 1) ? "✅당첨" : "대기";
      $msg .= "{$상태} {$r['nick']} "
           . number_format($r['amount']) . "{$단위}\n";
    }

    echo 전송($msg);
    exit;  

}else if (strpos($status, '.사다리마감') !== false) {

    // tb_sadari 테이블에서, 현재 호출자의 guest 데이터만 삭제
    // guest 컬럼에는 평타사다리 진행 시 기준이 되는 두자리 닉(예: $두자리닉넴)이 저장되어 있음
    $게스트 = addslashes($두자리닉넴);
    db_query("DELETE FROM tb_sadari WHERE guest = '{$게스트}' and status = 0 ");

    global $conn;
    $행수 = mysqli_affected_rows($conn);
    if ($행수 > 0) {
      echo 전송("✅ {$두자리닉넴} 사다리 데이터 {$행수}개 삭제 완료");
    } else {
      echo 전송("삭제할 사다리 데이터가 없습니다.");
    }
    exit;

}else if(strpos($status, '.사다리') !== false){
    $status_trim = trim($status);
    if (!preg_match('/\.사다리\s+(\d+)\s+(\d+)/u', $status_trim, $m)) {
      $msg = "✅ .사다리 사용법\n\n";
      $msg .= "· 평타 순위 상위 N명 중 M명을 랜덤으로 뽑아요.\n\n";
      $msg .= "사용법: .사다리 [상위명수] [뽑을명수]\n";
      $msg .= "예시: .사다리 10 4\n";
      $msg .= "→ 평타 상위 10명 중 4명 랜덤 뽑기\n";
      echo 전송($msg);
      exit;
    }

    // 동시에 1명만 사다리 진행 가능하도록 제한
    $현재진행자 = addslashes($두자리닉넴);
    $진행중 = db_select("
      SELECT guest
      FROM tb_sadari
      WHERE status = 0
      GROUP BY guest
      LIMIT 1
    ");
    if ($진행중 && isset($진행중['guest']) && $진행중['guest'] !== '' && $진행중['guest'] !== $현재진행자) {
      echo 전송("❌ 현재 {$진행중['guest']} 님이 사다리를 진행중입니다.\n해당 사다리가 종료된 후 다시 시도해주세요.");
      exit;
    }

    $날짜 = date('Y-m-d');
    $상위N = (int)$m[1];
    $뽑을인원 = (int)$m[2];
    if (preg_match('/\.사다리\s+\d+\s+\d+\s+(\d{4}-\d{2}-\d{2})/u', $status_trim, $m2)) {
      $날짜 = $m2[1];
    }
    $상위N = max(1, min(50, $상위N));



    // 최소 4명 이상만 사다리 진행 가능
    if ($뽑을인원 < 4) {
      echo 전송("❌ 사다리는 최소 4명 이상부터 진행 가능\n예) .사다리 10 4");
      exit;
    }
    $뽑을인원 = max(4, min($상위N, $뽑을인원));

    // 이번 사다리에서 사용할 인원 수를 config.사다리인원에 기록
    db_query("UPDATE config SET 사다리인원 = {$뽑을인원}");

    $sql = "WITH today_tasu AS (
        SELECT
          nickname,
          SUM(tasu) AS cnt,
          " . 생타_SQL_select_expr('msg') . " AS raw_cnt,
          (SUM(tasu) + " . 생타_SQL_select_expr('msg') . ") AS total_cnt
        FROM tb_msg
        WHERE regdate >= '{$날짜}' AND regdate < '{$날짜}' + INTERVAL 1 DAY
          AND nickname NOT IN ('오픈', '')
          AND msg NOT LIKE '%이모티콘을 보냈습니다.%'
        GROUP BY nickname
    ),
    ranked AS (
        SELECT nickname, total_cnt AS cnt, ROW_NUMBER() OVER (ORDER BY total_cnt DESC) AS rn
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
    shuffle($순위목록);
    $뽑힌목록 = array_slice($순위목록, 0, $뽑을인원);

    // 금고 잔액 조회 후, 이번에 뽑힌 인원 수로 N등분하여 tb_sadari 테이블에 기록
    $configRow = db_select("select tax from config limit 1");
    $금고잔액 = isset($configRow['tax']) ? (int)$configRow['tax'] : 0;
    $나눌인원 = max(1, count($뽑힌목록));
    $개당금액 = (int)floor($금고잔액 / $나눌인원);

    $msg = "✅ 평타 {$상위N}위 중 {$뽑을인원}명 랜덤 뽑기 ({$날짜})\n\n";
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
              amount = {$개당금액}
        ";
        db_query($sql_insert);
      }

    }

    if ($금액표시) {
      $msg .= "금고 잔액: " . number_format($금고잔액) . "{$단위}\n";
      $msg .= "1인당 지급 예정: " . number_format($개당금액) . "{$단위}\n\n";

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

        $msg .= ($i + 1) . ". {$표시문구}" . $row['닉네임'] . " ({$row['순위']}등 +" . number_format($개당금액) . "{$단위})\n";
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

    $지급액 = isset($row['amount']) ? (int)$row['amount'] : 0;
    if ($지급액 <= 0) {
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
    
    $msg = "🎉{$두자리닉넴} +" . number_format($지급액) . "{$단위}\n";
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
      ['time' => '12:00', 'msg' => "친구들 맛점하자~!!"],
      ['time' => '13:00', 'msg' => '보이스룸 이용은 봇 제외 3인 이상시 마이크 켤 것!'],
      ['time' => '15:00', 'msg' => '보이스룸 이용은 봇 제외 3인 이상시 마이크 켤 것!'],
      ['time' => '17:00', 'msg' => '출퇴근방법!\n퇴근 원할 시 .퇴근\n다시왔을땐 .출근'],
      ['time' => '20:00', 'msg' => '애드라 저녁은 먹고 노는거니?'],
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

