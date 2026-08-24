<?php
/**
 * `.주사위` / `.주사위 2` 채팅 헬퍼
 * - 본방(info1): `.주사위` 1개 · `.주사위 2` 신불자 빚탕감
 * - 홍보방(info2) / 관리방(info3): `.주사위 2` 신불자 빚탕감
 * - `.주사위 2`: 시간당 1회 무료 · 출석룰렛 잔여 티켓으로 추가 사용
 */

if (!function_exists('주사위_눈표시표')) {
  /** @return array<int, list<string>> */
  function 주사위_눈표시표(): array {
    return [
      1 => ["　　　　　", "　　●　　", "　　　　　"],
      2 => ["　●　　　", "　　　　　", "　　　●　"],
      3 => ["　●　　　", "　　●　　", "　　　●　"],
      4 => ["　●　●　", "　　　　　", "　●　●　"],
      5 => ["　●　●　", "　　●　　", "　●　●　"],
      6 => ["　●　●　", "　●　●　", "　●　●　"],
    ];
  }
}

if (!function_exists('주사위_면출력')) {
  /** @param list<int> $면목록 */
  function 주사위_면출력(array $면목록): string {
    $표 = 주사위_눈표시표();
    $줄 = [];
    for ($r = 0; $r < 3; $r++) {
      $칸 = [];
      foreach ($면목록 as $눈) {
        $칸[] = $표[(int)$눈][$r];
      }
      $줄[] = implode('　　', $칸);
    }
    return implode("\n", $줄);
  }
}

if (!function_exists('주사위2_출석룰렛티켓_남음')) {
  /** 출석룰렛 오늘 잔여 티켓 (룰렛·빚탕감 추가사용 공유) */
  function 주사위2_출석룰렛티켓_남음($닉): int {
    $path = __DIR__ . '/attendance_roulette.inc.php';
    if (!is_file($path)) {
      return 0;
    }
    require_once $path;
    if (!function_exists('attendance_roulette_tickets_left')) {
      return 0;
    }
    return max(0, (int)attendance_roulette_tickets_left($닉));
  }
}

if (!function_exists('주사위2_명령_처리')) {
  /**
   * `.주사위 2` — 신불자만 · 시간당 1회 무료 · 출석룰렛 티켓으로 추가 · 더블 시 부채 10~30% 랜덤 차감
   * 본방/홍보방/관리방 공통. 매칭되면 전송 후 exit.
   */
  function 주사위2_명령_처리($두자리닉넴, $status): void {
    if (!preg_match('/^\.주사위\s*2\s*$/u', trim((string)$status))) {
      return;
    }

    $닉_esc = addslashes((string)$두자리닉넴);
    $회원행 = db_select("
      SELECT
        name,
        title,
        IFNULL(credit, 0) AS credit,
        CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (empty($회원행['name'])) {
      echo 전송("❌ 등록된 회원만 `.주사위 2`를 사용할 수 있어요.");
      exit;
    }
    $타이틀 = trim((string)($회원행['title'] ?? ''));
    $신용 = (int)($회원행['credit'] ?? 0);
    $ptRaw = trim((string)($회원행['point'] ?? '0'));
    $pt음수 = (isset($ptRaw[0]) && $ptRaw[0] === '-');
    $신불자 = ($타이틀 === '🆘신불자' || (bool)preg_match('/신불자/u', $타이틀) || ($신용 === 1 && $pt음수));
    if (!$신불자) {
      echo 전송("❌ `.주사위 2`는 신불자만 사용할 수 있어요.\n일반은 `.주사위` 만 가능합니다.");
      exit;
    }

    $주사위2_간격초 = 3600; // 1시간
    $주사위2_한도 = 1; // 시간당 무료 1회
    $창시작 = date('Y-m-d H:i:s', time() - $주사위2_간격초);
    $창시작_esc = addslashes($창시작);
    // 무료 사용만 집계 (티켓 추가사용 status는 제외)
    $사용집계 = db_select("
      SELECT COUNT(*) AS cnt, MIN(regdate) AS oldest
      FROM tb_point_log
      WHERE nick = '{$닉_esc}'
        AND status IN ('주사위2', '주사위2-더블')
        AND regdate >= '{$창시작_esc}'
    ");
    $창내무료사용 = (int)($사용집계['cnt'] ?? 0);
    $무료쿨다운중 = false;
    $다음무료ts = null;
    if ($창내무료사용 >= $주사위2_한도) {
      $oldestts = !empty($사용집계['oldest']) ? strtotime((string)$사용집계['oldest']) : false;
      if ($oldestts !== false) {
        $다음ts = $oldestts + $주사위2_간격초;
        if (time() < $다음ts) {
          $무료쿨다운중 = true;
          $다음무료ts = $다음ts;
        }
      }
    }

    $티켓남음 = 주사위2_출석룰렛티켓_남음($두자리닉넴);
    $티켓사용 = false;
    if ($무료쿨다운중) {
      if ($티켓남음 < 1) {
        $남은초 = max(0, (int)$다음무료ts - time());
        $남은시 = intdiv($남은초, 3600);
        $남은분 = intdiv($남은초 % 3600, 60);
        $다음시각 = date('H:i', (int)$다음무료ts);
        $남은문구 = ($남은시 > 0 ? "{$남은시}시간 " : '') . "{$남은분}분";
        echo 전송(
          "❌ `.주사위 2` 무료는 시간당 {$주사위2_한도}회예요.\n"
          . "{$남은문구} 후({$다음시각}) 다시 시도해 주세요.\n"
          . "출석룰렛 티켓이 있으면 추가 사용할 수 있어요."
        );
        exit;
      }
      $티켓사용 = true;
    }

    $눈1 = random_int(1, 6);
    $눈2 = random_int(1, 6);
    $면문 = 주사위_면출력([$눈1, $눈2]);

    $자숙안내 = '';
    if (function_exists('자숙_시간연장위반_적용')) {
      $자숙결과 = 자숙_시간연장위반_적용($두자리닉넴, '주사위2');
      if (!empty($자숙결과['applied'])) {
        $자숙안내 = (string)($자숙결과['notice'] ?? '');
      }
    }

    if ($티켓사용) {
      $티켓남은후 = max(0, $티켓남음 - 1);
      $간격문구 = "출석룰렛 티켓 1장 사용 · 남은 {$티켓남은후}장";
      if ($다음무료ts !== null) {
        $간격문구 .= "\n무료 다음 " . date('H:i', (int)$다음무료ts) . " (시간당 {$주사위2_한도}회)";
      }
    } else {
      $oldestts = time();
      $다음가능 = date('H:i', $oldestts + $주사위2_간격초);
      $간격문구 = "다음 무료 {$다음가능} (시간당 {$주사위2_한도}회)";
      if ($티켓남음 > 0) {
        $간격문구 .= "\n추가: 출석룰렛 티켓 {$티켓남음}장으로 가능";
      }
    }

    $더블 = ($눈1 === $눈2);
    if ($더블) {
      $비율 = random_int(10, 30); // 더블 시 부채 10~30% 랜덤 차감
      $부채절대 = ltrim(preg_replace('/[^\d]/', '', $ptRaw), '0') ?: '0';
      $차감액 = '0';
      if ($pt음수 && $부채절대 !== '0' && function_exists('냥_비율내림')) {
        $차감액 = 냥_정수문자열(냥_비율내림($부채절대, $비율 / 100));
      } elseif ($pt음수 && $부채절대 !== '0') {
        if (function_exists('bcmul') && function_exists('bcdiv')) {
          $차감액 = bcdiv(bcmul($부채절대, (string)$비율, 0), '100', 0);
          $차감액 = ltrim((string)$차감액, '0') ?: '0';
        } else {
          $차감액 = (string)(int)floor(((float)$부채절대 * $비율) / 100.0);
        }
      }
      $로그상태 = $티켓사용 ? '주사위2-티켓더블' : '주사위2-더블';
      if ($차감액 !== '0' && $차감액 !== '') {
        $차감_sql = preg_replace('/[^\d]/', '', (string)$차감액);
        db_query("UPDATE tb_member SET point = point + {$차감_sql} WHERE name = '{$닉_esc}' LIMIT 1");
        if (function_exists('지급로그')) {
          지급로그($로그상태, $두자리닉넴, '', 0, $차감_sql);
        }
        $차감표시 = function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($차감_sql, '냥') : (number_format((float)$차감_sql) . '냥');
        $이전절댓값 = ltrim(preg_replace('/[^\d]/', '', $ptRaw), '0') ?: '0';
        $이전표시 = '-' . (function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($이전절댓값, '냥') : ($이전절댓값 . '냥'));
        $이후행 = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
        $이후pt = trim((string)($이후행['point'] ?? '0'));
        $이후음수 = (isset($이후pt[0]) && $이후pt[0] === '-');
        $이후절댓값 = ltrim(preg_replace('/[^\d]/', '', $이후pt), '0') ?: '0';
        $이후표시본문 = function_exists('랭킹_게임냥표시') ? 랭킹_게임냥표시($이후절댓값, '냥') : ($이후절댓값 . '냥');
        $이후표시 = $이후음수 ? ('-' . $이후표시본문) : $이후표시본문;
        $out = $자숙안내 . $면문
          . "\n\n{$두자리닉넴} 🎲 더블 {$눈1}/{$눈2}!"
          . "\n부채 {$비율}% 차감"
          . "\n게임냥 {$차감표시} 차감됨"
          . "\n{$이전표시} → {$이후표시}"
          . "\n{$간격문구}";
        echo 전송($out);
        exit;
      }
      if (function_exists('지급로그')) {
        지급로그($로그상태, $두자리닉넴, '', 0, 0);
      }
      echo 전송($자숙안내 . $면문 . "\n\n{$두자리닉넴} 🎲 더블 {$눈1}/{$눈2}!\n차감할 부채가 없어요.\n{$간격문구}");
      exit;
    }

    $로그상태 = $티켓사용 ? '주사위2-티켓' : '주사위2';
    if (function_exists('지급로그')) {
      지급로그($로그상태, $두자리닉넴, '', 0, 0);
    }
    echo 전송($자숙안내 . $면문 . "\n\n{$두자리닉넴} · {$눈1}/{$눈2} · 더블 아님\n{$간격문구}");
    exit;
  }
}

if (!function_exists('탕감_명령_처리')) {
  /**
   * `.탕감 닉 5%` — 관리자 · 대상의 마이너스 게임냥을 지정 %만큼 줄임 (부채 절댓값 × % 내림)
   * 본방/홍보방/관리방 공통. 매칭되면 전송 후 exit.
   */
  function 탕감_명령_처리($두자리닉넴, $status, $nick = ''): void {
    $s = trim((string)$status);
    if (function_exists('status_정규화')) {
      $s = status_정규화($s);
    }
    $s = str_replace('％', '%', $s);
    $s = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);
    $s = preg_replace('/[ \t]+/u', ' ', trim($s));
    // GET 잔여 인코딩 (`100%25`) 복원
    if (is_string($s) && strpos($s, '%') !== false) {
      $decoded = rawurldecode($s);
      if (is_string($decoded) && $decoded !== '') {
        $s = trim(str_replace('％', '%', $decoded));
      }
    }
    if (!preg_match('/^\.탕감/u', $s)) {
      return;
    }

    // 관리자만 — $관리자 목록 또는 DB admin=1
    global $관리자;
    $요청닉 = trim((string)$두자리닉넴);
    $후보닉 = [];
    if ($요청닉 !== '') {
      $후보닉[] = $요청닉;
    }
    if ($nick !== '' && function_exists('getTwoCharNick')) {
      $파싱 = trim((string)getTwoCharNick($nick));
      if ($파싱 !== '' && !in_array($파싱, $후보닉, true)) {
        $후보닉[] = $파싱;
      }
    }
    $허용 = false;
    foreach ($후보닉 as $n) {
      if (in_array($n, (array)($관리자 ?? []), true)) {
        $허용 = true;
        break;
      }
    }
    if (!$허용 && !empty($후보닉)) {
      $in = [];
      foreach ($후보닉 as $n) {
        $in[] = "'" . addslashes($n) . "'";
      }
      $행 = @db_select("SELECT name FROM tb_member WHERE admin = 1 AND name IN (" . implode(',', $in) . ") LIMIT 1");
      if (!empty($행['name'])) {
        $허용 = true;
      }
    }
    if (!$허용) {
      echo 전송("❌ 관리자만 사용할 수 있어요.");
      exit;
    }

    if (!preg_match('/^\.탕감\s*(\S+?)\s*(\d{1,3})\s*%?\s*$/u', $s, $m)) {
      echo 전송("❌ 사용법: `.탕감 닉네임 5%`\n예) `.탕감 덕선 5%` · `.탕감 미미 100%`");
      exit;
    }

    $비율 = (int)$m[2];
    if ($비율 < 1 || $비율 > 100) {
      echo 전송("❌ 탕감 비율은 1~100%만 가능해요.");
      exit;
    }

    $대상입력 = trim((string)$m[1]);
    $대상닉 = function_exists('getTwoCharNick') ? getTwoCharNick($대상입력) : $대상입력;
    if ($대상닉 === '') {
      $대상닉 = preg_replace('/\s+/u', '', $대상입력);
    }
    // 대상만 조회 — 회원정보_닉해석()은 nick_파라미터로 보낸이 닉을 덮어써서 대상이 관리자로 바뀔 수 있음
    if (function_exists('회원정보_조회')) {
      $해석정보 = 회원정보_조회($대상닉);
      if (empty($해석정보['name']) && $대상입력 !== $대상닉) {
        $해석정보 = 회원정보_조회($대상입력);
      }
      if (!empty($해석정보['name'])) {
        $대상닉 = (string)$해석정보['name'];
      }
    }

    $닉_esc = addslashes($대상닉);
    $회원행 = db_select("
      SELECT
        name,
        CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point
      FROM tb_member
      WHERE name = '{$닉_esc}'
      LIMIT 1
    ");
    if (empty($회원행['name']) && function_exists('회원정보_조회')) {
      $회원행 = 회원정보_조회($대상닉);
      if (!empty($회원행['name'])) {
        $대상닉 = (string)$회원행['name'];
        $닉_esc = addslashes($대상닉);
        $pt = $회원행['point'] ?? '0';
        $회원행['point'] = is_numeric($pt) || (is_string($pt) && preg_match('/^-?\d+$/', (string)$pt))
          ? (string)$pt
          : (string)($pt ?? '0');
      }
    }
    if (empty($회원행['name'])) {
      echo 전송("❌ 닉네임을 확인해 주세요. ({$대상입력})");
      exit;
    }

    $ptRaw = trim((string)($회원행['point'] ?? '0'));
    $pt음수 = (isset($ptRaw[0]) && $ptRaw[0] === '-');
    if (!$pt음수) {
      $보유표시 = function_exists('랭킹_게임냥표시')
        ? 랭킹_게임냥표시(ltrim(preg_replace('/[^\d]/', '', $ptRaw), '0') ?: '0', '냥')
        : ($ptRaw . '냥');
      echo 전송("❌ {$대상닉} 님은 마이너스 부채가 없어요. (현재 {$보유표시})");
      exit;
    }

    $부채절대 = ltrim(preg_replace('/[^\d]/', '', $ptRaw), '0') ?: '0';
    if ($부채절대 === '0') {
      echo 전송("❌ {$대상닉} 님은 차감할 부채가 없어요.");
      exit;
    }

    $차감액 = '0';
    if (function_exists('냥_비율내림') && function_exists('냥_정수문자열')) {
      $차감액 = 냥_정수문자열(냥_비율내림($부채절대, $비율 / 100));
    } elseif (function_exists('bcmul') && function_exists('bcdiv')) {
      $차감액 = bcdiv(bcmul($부채절대, (string)$비율, 0), '100', 0);
      $차감액 = ltrim((string)$차감액, '0') ?: '0';
    } else {
      $차감액 = (string)(int)floor(((float)$부채절대 * $비율) / 100.0);
    }

    if ($차감액 === '0' || $차감액 === '') {
      echo 전송("❌ 부채가 너무 작아 {$비율}% 차감액이 0냥이에요.");
      exit;
    }

    $차감_sql = preg_replace('/[^\d]/', '', (string)$차감액);
    db_query("UPDATE tb_member SET point = point + {$차감_sql} WHERE name = '{$닉_esc}' LIMIT 1");
    if (function_exists('지급로그')) {
      지급로그('탕감', $대상닉, (string)$두자리닉넴, 0, $차감_sql);
    }

    $이전표시 = '-' . (function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($부채절대, '냥')
      : ($부채절대 . '냥'));
    $차감표시 = function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($차감_sql, '냥')
      : (number_format((float)$차감_sql) . '냥');
    $이후행 = db_select("SELECT CAST(CAST(IFNULL(point, 0) AS DECIMAL(40,0)) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $이후pt = trim((string)($이후행['point'] ?? '0'));
    $이후음수 = (isset($이후pt[0]) && $이후pt[0] === '-');
    $이후절댓값 = ltrim(preg_replace('/[^\d]/', '', $이후pt), '0') ?: '0';
    $이후표시본문 = function_exists('랭킹_게임냥표시')
      ? 랭킹_게임냥표시($이후절댓값, '냥')
      : ($이후절댓값 . '냥');
    $이후표시 = $이후음수 ? ('-' . $이후표시본문) : $이후표시본문;

    echo 전송(
      "💸 {$대상닉} 부채 탕감 {$비율}%\n"
      . "차감: {$차감표시}\n"
      . "{$이전표시} → {$이후표시}"
    );
    exit;
  }
}
