<?php
/**
 * 채팅 로또: `.로또`, `.로또 내역`, `.로또 1,2,3`, `.로또 자동`, 관리자 `.로또 추첨` / `.로또 지급` / `.로또당첨 닉`
 * 포함 전제: config.php 로드됨, $두자리닉넴·$status·$정보·$단위 사용 가능
 * $LOTTO_ADMIN_ONLY_INFO1=true 이면 추첨·지급·당첨을 본방만 허용 (기본 홍보방도 허용)
 */

if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
  require_once __DIR__ . '/lotto_amount.inc.php';
}

if (!function_exists('로또_관리명령_본방전용차단')) {
  function 로또_관리명령_본방전용차단() {
    if (!empty($GLOBALS['LOTTO_ADMIN_ONLY_INFO1'])) {
      echo 전송("❌ `.로또 추첨` · `.로또 지급` · `.로또당첨` 은 본방(관리)에서만 실행할 수 있어요.");
      exit;
    }
  }
}

/** 로또 금액 → 부호 없는 정수 문자열 ((int) 금지) */
if (!function_exists('로또_금액문자열')) {
  function 로또_금액문자열($v): string {
    if (function_exists('냥_정수문자열')) {
      return 냥_정수문자열($v);
    }
    $s = preg_replace('/[^\d]/', '', (string)$v);
    return ($s === '' || $s === null) ? '0' : (ltrim($s, '0') ?: '0');
  }
}

/** 로또 지급·조회 금액 축약 (조·억) */
if (!function_exists('로또_금액표시')) {
  function 로또_금액표시($amt, $단위접미 = null) {
    global $단위;
    $u = ($단위접미 !== null && $단위접미 !== '') ? (string)$단위접미
      : ((isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥');
    if (function_exists('냥축약표시')) {
      return 냥축약표시($amt, $u);
    }
    if (function_exists('게임냥_안전표시')) {
      return 게임냥_안전표시($amt, $u);
    }
    return 로또_금액문자열($amt) . $u;
  }
}

/**
 * JSON 응답을 Content-Length와 함께 즉시 전송 후 연결 종료
 * (이후 DB 작업이 길어도 채팅에 번호가 먼저 보이게)
 */
if (!function_exists('로또_즉시응답')) {
  function 로또_즉시응답(string $msg): void {
    $json = 전송($msg);
    if (!is_string($json) || $json === '') {
      $json = json_encode(['data' => $msg], JSON_UNESCAPED_UNICODE);
    }
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8');
      header('Content-Length: ' . strlen($json));
      header('Connection: close');
    }
    echo $json;

    if (function_exists('fastcgi_finish_request')) {
      @fastcgi_finish_request();
      return;
    }
    while (ob_get_level() > 0) {
      @ob_end_flush();
    }
    @flush();
  }
}

/** 큰 금액 곱 (건수·인당) */
if (!function_exists('로또_금액곱')) {
  function 로또_금액곱($a, $b): string {
    $sa = 로또_금액문자열($a);
    $sb = 로또_금액문자열($b);
    if ($sa === '0' || $sb === '0') {
      return '0';
    }
    if (function_exists('냥_금액_문자열곱')) {
      return 냥_금액_문자열곱($sa, $sb);
    }
    if (function_exists('bcmul')) {
      return bcmul($sa, $sb, 0);
    }
    return (string)((int)$sa * (int)$sb);
  }
}

/** 큰 금액 합 */
if (!function_exists('로또_금액합')) {
  function 로또_금액합($a, $b): string {
    $sa = 로또_금액문자열($a);
    $sb = 로또_금액문자열($b);
    if (function_exists('냥_금액_문자열합')) {
      return 냥_금액_문자열합($sa, $sb);
    }
    if (function_exists('bcadd')) {
      return bcadd($sa, $sb, 0);
    }
    return (string)((int)$sa + (int)$sb);
  }
}

/** 큰 금액 차 (음수면 0) */
if (!function_exists('로또_금액차')) {
  function 로또_금액차($a, $b): string {
    $sa = 로또_금액문자열($a);
    $sb = 로또_금액문자열($b);
    if (function_exists('냥_금액_문자열차감')) {
      return 냥_금액_문자열차감($sa, $sb);
    }
    if (function_exists('bcsub') && function_exists('bccomp')) {
      if (bccomp($sa, $sb, 0) < 0) {
        return '0';
      }
      return bcsub($sa, $sb, 0);
    }
    return (string)max(0, (int)$sa - (int)$sb);
  }
}

/** $a > 0 인지 */
if (!function_exists('로또_금액양수')) {
  function 로또_금액양수($a): bool {
    $s = 로또_금액문자열($a);
    return $s !== '0' && $s !== '';
  }
}

/** SQL SUM/컬럼을 CHAR 로 읽어 문자열 금액으로 */
if (!function_exists('로또_금액_조회')) {
  function 로또_금액_조회($row, $key = 'total_amount'): string {
    return 로또_금액문자열(is_array($row) ? ($row[$key] ?? 0) : 0);
  }
}

/** 1등 유무에 따라 등수별 풀·1인당 지급액 계산 (floor로 총 당첨금 초과 지급 방지) */
if (!function_exists('로또_지급금액_계산')) {
  function 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수) {
    $총 = 로또_금액문자열($총당첨금);
    $일등수 = max(0, (int)$일등수);
    $이등수 = max(0, (int)$이등수);
    $삼등수 = max(0, (int)$삼등수);

    $비율 = function ($금액, float $r) {
      if (function_exists('냥_비율내림')) {
        return 로또_금액문자열(냥_비율내림($금액, $r));
      }
      if (function_exists('bcmul') && function_exists('bcdiv')) {
        // r=0.07 → *7/100
        $pct = (string)(int)round($r * 100);
        return bcdiv(bcmul(로또_금액문자열($금액), $pct, 0), '100', 0);
      }
      return (string)(int)floor((float)로또_금액문자열($금액) * $r);
    };
    $나눔 = function ($금액, int $n) {
      if ($n <= 0) {
        return '0';
      }
      if (function_exists('냥_나눗셈내림')) {
        return 로또_금액문자열(냥_나눗셈내림($금액, $n));
      }
      if (function_exists('bcdiv')) {
        return bcdiv(로또_금액문자열($금액), (string)$n, 0);
      }
      return (string)(int)floor((float)로또_금액문자열($금액) / $n);
    };

    $이등풀 = $비율($총, 0.07);
    $삼등풀 = $비율($총, 0.03);
    $일등풀 = ($일등수 > 0) ? $비율($총, 0.90) : '0';
    $일등지급풀 = ($일등수 > 0 && 로또_금액양수($일등풀)) ? $비율($일등풀, 0.70) : '0';

    $일등1인당 = ($일등수 > 0 && 로또_금액양수($일등지급풀)) ? $나눔($일등지급풀, $일등수) : '0';
    $이등원금 = ($이등수 > 0 && 로또_금액양수($이등풀)) ? $나눔($이등풀, $이등수) : '0';
    $삼등원금 = ($삼등수 > 0 && 로또_금액양수($삼등풀)) ? $나눔($삼등풀, $삼등수) : '0';

    $일등실지급 = 로또_금액곱($일등1인당, $일등수);
    $일등소멸 = 로또_금액차($일등풀, $일등실지급);

    return [
      '일등풀'     => $일등풀,
      '일등지급풀' => $일등지급풀,
      '일등소멸'   => $일등소멸,
      '이등풀'     => $이등풀,
      '삼등풀'     => $삼등풀,
      '일등1인당'  => $일등1인당,
      '이등원금'   => $이등원금,
      '삼등원금'   => $삼등원금,
    ];
  }
}

/** @return array{1:int,2:int,3:int} */
if (!function_exists('로또_회차_등수별_인원집계')) {
  function 로또_회차_등수별_인원집계($대상회차) {
    $대상회차 = (int)$대상회차;
    $out = [1 => 0, 2 => 0, 3 => 0];
    if ($대상회차 <= 0) {
      return $out;
    }
    $rs = db_query("
      SELECT `rank`, COUNT(*) AS cnt
      FROM tb_game_lotto
      WHERE drow = {$대상회차}
        AND `rank` BETWEEN 1 AND 3
        AND nick != ''
        AND nick != '이월금'
      GROUP BY `rank`
    ");
    while ($rs && $row = db_fetch($rs)) {
      $r = (int)($row['rank'] ?? 0);
      if ($r >= 1 && $r <= 3) {
        $out[$r] = (int)($row['cnt'] ?? 0);
      }
    }
    return $out;
  }
}

/**
 * 당첨자 목록 (닉·등수 집계 · 채팅 길이 제한)
 * @param array $등수별지급 [1=>인당,2=>…,3=>…] 미지급 시 fallback
 * @param int $최대줄 초과 시 "외 N명"으로 압축 (0=무제한)
 * @return list<string>
 */
if (!function_exists('로또_회차_당첨자_지급목록')) {
  function 로또_회차_당첨자_지급목록($대상회차, array $등수별지급 = [], $최대줄 = 40) {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $대상회차 = (int)$대상회차;
    $최대줄 = max(0, (int)$최대줄);
    $등수아이콘 = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    $목록 = [];
    $rs = db_query("
      SELECT nick, `rank`,
             COUNT(*) AS cnt,
             CAST(IFNULL(MAX(winnings), 0) AS CHAR) AS winnings
      FROM tb_game_lotto
      WHERE drow = {$대상회차}
        AND `rank` BETWEEN 1 AND 3
        AND nick != ''
        AND nick != '이월금'
      GROUP BY nick, `rank`
      ORDER BY `rank` ASC, cnt DESC, nick ASC
    ");
    while ($rs && $row = db_fetch($rs)) {
      $닉 = trim((string)($row['nick'] ?? ''));
      $등수 = (int)($row['rank'] ?? 0);
      $건수 = max(1, (int)($row['cnt'] ?? 1));
      if ($닉 === '' || $등수 < 1 || $등수 > 3) {
        continue;
      }
      $인당 = 로또_금액문자열($row['winnings'] ?? 0);
      if (!로또_금액양수($인당)) {
        $인당 = 로또_금액문자열($등수별지급[$등수] ?? 0);
      }
      $아이콘 = $등수아이콘[$등수] ?? '•';
      $건수표 = ($건수 > 1) ? "×{$건수}" : '';
      if (로또_금액양수($인당)) {
        $총액 = ($건수 > 1) ? 로또_금액곱($인당, $건수) : $인당;
        $목록[] = "{$아이콘} {$닉}{$건수표} +" . 로또_금액표시($총액, $단위표);
      } else {
        // 추첨 직후 등 — 금액 없어도 닉은 표시
        $목록[] = "{$아이콘} {$닉}{$건수표}";
      }
    }
    if ($최대줄 > 0 && count($목록) > $최대줄) {
      $남음 = count($목록) - $최대줄;
      $목록 = array_slice($목록, 0, $최대줄);
      $목록[] = "… 외 {$남음}명";
    }
    return $목록;
  }
}

/** 추첨·지급 공용 당첨자 블록 문구 */
if (!function_exists('로또_회차_당첨자_내역문구')) {
  function 로또_회차_당첨자_내역문구($대상회차, array $등수별지급 = [], $최대줄 = 40) {
    $목록 = 로또_회차_당첨자_지급목록($대상회차, $등수별지급, $최대줄);
    if (empty($목록)) {
      return "[당첨자 내역]\n당첨자가 없습니다.";
    }
    return "[당첨자 내역]\n" . implode("\n", $목록);
  }
}

/**
 * 회차 당첨금 일괄 지급
 * · 회원 point: 등수별 JOIN UPDATE 1회
 * · 지급로그: 닉별 SELECT 제거 · VALUES 배치 INSERT
 * @return string 총 지급액 (문자열)
 */
if (!function_exists('로또_회차_지급_실행')) {
  function 로또_회차_지급_실행($대상회차, $일등1인당, $이등원금, $삼등원금) {
    if (function_exists('로또_금액컬럼_보장')) {
      로또_금액컬럼_보장();
    }
    if (function_exists('tb_member_point_컬럼_보장')) {
      tb_member_point_컬럼_보장();
    }
    $대상회차 = (int)$대상회차;
    $등수설정 = [
      1 => ['금액' => 로또_금액문자열($일등1인당), '라벨' => '1등'],
      2 => ['금액' => 로또_금액문자열($이등원금),   '라벨' => '2등'],
      3 => ['금액' => 로또_금액문자열($삼등원금),   '라벨' => '3등'],
    ];
    $지급총액 = '0';
    $로그버퍼 = []; // list{status,nick,point}

    foreach ($등수설정 as $등수 => $cfg) {
      $인당 = $cfg['금액'];
      if (!로또_금액양수($인당)) {
        continue;
      }
      $인당_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($인당) : $인당;
      db_query("UPDATE tb_game_lotto SET winnings = {$인당_sql} WHERE drow = {$대상회차} AND rank = {$등수}");

      // 닉별 건수 집계 후 한 번에 point 가산
      db_query("
        UPDATE tb_member m
        INNER JOIN (
          SELECT nick, COUNT(*) AS cnt
          FROM tb_game_lotto
          WHERE drow = {$대상회차}
            AND rank = {$등수}
            AND nick != ''
            AND nick != '이월금'
          GROUP BY nick
        ) w ON m.name = w.nick
        SET m.point = m.point + ({$인당_sql} * w.cnt)
      ");

      $rs = db_query("
        SELECT nick, COUNT(*) AS cnt
        FROM tb_game_lotto
        WHERE drow = {$대상회차}
          AND rank = {$등수}
          AND nick != ''
          AND nick != '이월금'
        GROUP BY nick
      ");
      while ($rs && $row = db_fetch($rs)) {
        $닉 = trim((string)($row['nick'] ?? ''));
        $건수 = (int)($row['cnt'] ?? 0);
        if ($닉 === '' || $건수 <= 0) {
          continue;
        }
        $총지급 = 로또_금액곱($인당, $건수);
        $상태 = "로또{$대상회차}회차-{$cfg['라벨']}";
        if ($건수 > 1) {
          $상태 .= "×{$건수}";
        }
        $로그버퍼[] = ['status' => $상태, 'nick' => $닉, 'point' => $총지급];
        $지급총액 = 로또_금액합($지급총액, $총지급);
      }
    }

    if ($로그버퍼 !== [] && function_exists('지급로그_금액_SQL')) {
      if (function_exists('지급로그_컬럼_보장')) {
        지급로그_컬럼_보장();
      }
      $chunks = array_chunk($로그버퍼, 80);
      foreach ($chunks as $chunk) {
        $values = [];
        foreach ($chunk as $L) {
          $닉_esc = addslashes((string)$L['nick']);
          $상태_esc = addslashes((string)$L['status']);
          $지급_sql = 지급로그_금액_SQL($L['point'], true);
          $values[] = "('{$상태_esc}', '{$닉_esc}', '', 0, {$지급_sql}, 0, NOW())";
        }
        if ($values !== []) {
          @db_query("
            INSERT INTO tb_point_log (status, nick, receiver, tax, point, mypoint, regdate)
            VALUES " . implode(",\n", $values)
          );
        }
      }
    } elseif ($로그버퍼 !== [] && function_exists('지급로그')) {
      foreach ($로그버퍼 as $L) {
        지급로그($L['status'], $L['nick'], '', 0, $L['point']);
      }
    }

    return $지급총액;
  }
}

/** 회차별 당첨·지급 내역 조회 문구 (`.로또 지급 N`) */
if (!function_exists('로또_회차_지급내역_문구')) {
  function 로또_회차_지급내역_문구($대상회차, $제목접두 = '지급 내역') {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $대상회차 = (int)$대상회차;
    if ($대상회차 <= 0) {
      return "❌ 회차 번호를 올바르게 입력해주세요.\n예) .로또 지급 63";
    }

    $회차행 = db_select("
      SELECT drow, num1, num2, num3,
             CAST(IFNULL(total_amount, 0) AS CHAR) AS total_amount,
             status,
             CAST(IFNULL(last_amount, 0) AS CHAR) AS last_amount
      FROM tb_game_lotto_result
      WHERE drow = {$대상회차}
      LIMIT 1
    ");
    if (empty($회차행['drow'])) {
      return "❌ {$대상회차}회차 추첨 결과가 없습니다.";
    }

    $표시1 = str_pad((string)((int)$회차행['num1']), 2, '0', STR_PAD_LEFT);
    $표시2 = str_pad((string)((int)$회차행['num2']), 2, '0', STR_PAD_LEFT);
    $표시3 = str_pad((string)((int)$회차행['num3']), 2, '0', STR_PAD_LEFT);
    $총당첨금 = 로또_금액_조회($회차행, 'total_amount');
    $정산완료 = (int)($회차행['status'] ?? 0) === 1;
    $이월잔액 = 로또_금액_조회($회차행, 'last_amount');

    $등수집계 = 로또_회차_등수별_인원집계($대상회차);
    $일등수 = (int)$등수집계[1];
    $이등수 = (int)$등수집계[2];
    $삼등수 = (int)$등수집계[3];

    $지급계산 = 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수);
    $일등1인당 = 로또_금액문자열($지급계산['일등1인당']);
    $이등원금   = 로또_금액문자열($지급계산['이등원금']);
    $삼등원금   = 로또_금액문자열($지급계산['삼등원금']);

    $등수별지급 = [1 => $일등1인당, 2 => $이등원금, 3 => $삼등원금];
    $당첨내역 = 로또_회차_당첨자_지급목록($대상회차, $등수별지급);
    $지급총액 = 로또_금액합(
      로또_금액합(로또_금액곱($일등1인당, $일등수), 로또_금액곱($이등원금, $이등수)),
      로또_금액곱($삼등원금, $삼등수)
    );

    $msg = "💸 로또 {$대상회차}회차 {$제목접두}\n";
    $msg .= "번호: {$표시1},{$표시2},{$표시3}\n";
    $msg .= "총 당첨금: " . 로또_금액표시($총당첨금, $단위표) . "\n";
    $msg .= "정산: " . ($정산완료 ? '완료' : '대기(미지급)') . "\n";
    if ($일등수 > 0) {
      $msg .= "(규칙: 1등 90% 풀 · 지급 70%·소멸 30% · 2등 7% · 3등 3%)\n";
    } else {
      $msg .= "(규칙: 1등 없음 → 2등 7% · 3등 3% 풀 등분, 나머지 90% 이월)\n";
    }
    $msg .= "🥇 1등 {$일등수}명 (1인당 " . 로또_금액표시($일등1인당, $단위표) . ")\n";
    $msg .= "🥈 2등 {$이등수}명 (1인당 " . 로또_금액표시($이등원금, $단위표) . ")\n";
    $msg .= "🥉 3등 {$삼등수}명 (1인당 " . 로또_금액표시($삼등원금, $단위표) . ")\n";
    $msg .= "총 지급액: " . 로또_금액표시($지급총액, $단위표) . "\n";
    if ($정산완료) {
      $msg .= "지급 후 잔액(이월): " . 로또_금액표시($이월잔액, $단위표);
      if (로또_금액양수($이월잔액)) {
        $msg .= "\n➡️ " . ($대상회차 + 1) . "회차로 이월";
      }
    }

    if (empty($당첨내역)) {
      $msg .= "\n\n[당첨자 지급 내역]\n당첨자가 없습니다.";
    } else {
      $msg .= "\n\n[당첨자 지급 내역]\n" . implode("\n", $당첨내역);
    }

    $msg .= "\n\nhttp://49.247.160.164/lotto_chk.php?result={$대상회차}";
    return $msg;
  }
}

// 명령 처리 구간 — 스키마/백필은 `.로또*` 진입 시에만 (매 요청 include 시 전체 SUM 금지)
// 추첨·단순 조회(.로또)·지급은 응답 속도 우선 — 스키마 백필 스킵
$_로또입력 = trim((string)$status);
if (
  strpos($_로또입력, '.로또') === 0
  && $_로또입력 !== '.로또'
  && !preg_match('/^\.로또\s*추첨\s*$/u', $_로또입력)
  && !preg_match('/^\.로또\s*지급(?:\s+\d+)?\s*$/u', $_로또입력)
  && function_exists('로또_금액_초기화')
) {
  로또_금액_초기화();
}

if (preg_match('/^\.로또\s*지급\s+(\d+)\s*$/u', trim($status), $지급조회m)) {
  echo 전송(로또_회차_지급내역_문구((int)$지급조회m[1]));
  exit;
}

/**
 * 관리자: `.로또당첨 닉` — 진행 회차 당첨금(티켓 합) 전액을 지정 인원에게 게임냥 지급 후 회차 초기화
 */
if (preg_match('/^\.로또당첨\s+(\S+)\s*$/u', trim($status), $로또당첨m)) {
  로또_관리명령_본방전용차단();
  global $관리자, $단위;
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 실행 가능합니다.\n예) .로또당첨 우서");
    exit;
  }

  require_once __DIR__ . '/lotto_purchase.inc.php';
  if (function_exists('로또_금액컬럼_보장')) {
    로또_금액컬럼_보장();
  }
  if (function_exists('tb_member_point_컬럼_보장')) {
    tb_member_point_컬럼_보장();
  }

  $대상닉 = trim((string)$로또당첨m[1]);
  if (function_exists('getTwoCharNick')) {
    $대상닉 = getTwoCharNick($대상닉) ?: $대상닉;
  }
  if ($대상닉 === '') {
    echo 전송("❌ 지급할 닉네임을 입력해주세요.\n예) .로또당첨 우서");
    exit;
  }
  $대상_esc = addslashes($대상닉);
  $회원 = db_select("SELECT idx, name FROM tb_member WHERE name = '{$대상_esc}' LIMIT 1");
  if (empty($회원['idx'])) {
    echo 전송("❌ '{$대상닉}' 회원을 찾을 수 없어요.");
    exit;
  }

  $진행회차 = function_exists('로또_진행회차') ? (int)로또_진행회차() : 0;
  if ($진행회차 <= 0) {
    echo 전송("❌ 진행 중인 로또 회차를 확인할 수 없어요.");
    exit;
  }

  // 미정산(추첨만 된) 회차가 있으면 먼저 정리하도록 안내
  $미지급 = db_select("
    SELECT drow FROM tb_game_lotto_result
    WHERE status = 0
    ORDER BY drow ASC
    LIMIT 1
  ");
  if (!empty($미지급['drow'])) {
    $md = (int)$미지급['drow'];
    echo 전송("❌ {$md}회차 추첨 후 미지급 상태예요.\n먼저 `.로또 지급`을 처리한 뒤 다시 시도해주세요.");
    exit;
  }

  if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
    require_once __DIR__ . '/lotto_amount.inc.php';
  }
  $표합 = function_exists('로또_회차금액_안전합')
    ? 로또_회차금액_안전합($진행회차)
    : 로또_금액문자열(
        db_select("
          SELECT CAST(IFNULL(SUM(CAST(IFNULL(amount, 0) AS DECIMAL(65,0))), 0) AS CHAR) AS total_amount
          FROM tb_game_lotto
          WHERE drow = {$진행회차} AND amount > 0
        ")['total_amount'] ?? 0
      );
  $총액 = function_exists('로또누적_합산') ? 로또누적_합산(로또_금액문자열($표합)) : 로또_금액문자열($표합);
  $총액 = 로또_금액문자열($총액);
  if (!로또_금액양수($총액)) {
    echo 전송("❌ {$진행회차}회차 당첨금이 0원이에요. 지급할 금액이 없습니다.");
    exit;
  }

  $총액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총액) : $총액;
  db_query("UPDATE tb_member SET point = point + {$총액_sql} WHERE name = '{$대상_esc}' LIMIT 1");
  if (function_exists('지급로그')) {
    지급로그("로또{$진행회차}회차-강제당첨", $대상닉, $두자리닉넴, 0, $총액);
  }

  // 회차 티켓·이월금 전부 삭제 → 당첨금 0으로 초기화 (회차 번호는 유지)
  db_query("DELETE FROM tb_game_lotto WHERE drow = {$진행회차}");
  if (function_exists('로또누적_초기화')) {
    로또누적_초기화();
  }
  if (function_exists('로또_회차금액_진행중합_캐시무효')) {
    로또_회차금액_진행중합_캐시무효($진행회차);
  }

  $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
  $금액표시 = 로또_금액표시($총액, $단위표);
  $msg = "💸 로또 {$진행회차}회차 강제 당첨\n";
  $msg .= "수령: {$대상닉}\n";
  $msg .= "지급: {$금액표시}\n";
  $msg .= "{$진행회차}회차 당첨금·구매내역 초기화 완료\n";
  $msg .= "(실행: {$두자리닉넴})";
  echo 전송($msg);
  exit;
}

if (preg_match('/^\.로또\s*추첨\s*$/u', trim((string)$status))) {
  로또_관리명령_본방전용차단();
  global $관리자;
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 실행 가능합니다.");
    exit;
  }

  require_once __DIR__ . '/lotto_purchase.inc.php';
  $선행검사 = 로또_추첨_선행검사();
  if (empty($선행검사['ok'])) {
    echo 전송($선행검사['msg'] ?? '❌ 이전 회차 추첨·지급을 먼저 처리해주세요.');
    exit;
  }
  $다음회차 = (int)($선행검사['target_drow'] ?? 0);
  if ($다음회차 <= 0) {
    echo 전송('❌ 추첨 대상 회차를 확인할 수 없어요.');
    exit;
  }

  // 번호 추첨만 — 총액·등수 채점은 `.로또 지급`에서 처리
  $후보숫자 = range(1, 45);
  shuffle($후보숫자);
  $추첨번호 = array_slice($후보숫자, 0, 3);
  sort($추첨번호);
  $num1 = (int)$추첨번호[0];
  $num2 = (int)$추첨번호[1];
  $num3 = (int)$추첨번호[2];

  // 추첨 시점: 표합 + config.로또누적 → total_amount 스냅샷 후 누적 초기화
  if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
    require_once __DIR__ . '/lotto_amount.inc.php';
  }
  $표합 = function_exists('로또_회차금액_안전합') ? 로또_회차금액_안전합($다음회차) : '0';
  $수수료누적 = function_exists('로또누적_조회') ? 로또누적_조회() : '0';
  $총액스냅 = function_exists('로또누적_합산') ? 로또누적_합산($표합) : $표합;
  $총액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총액스냅) : preg_replace('/\D/', '', $총액스냅);
  if ($총액_sql === '' || $총액_sql === null) {
    $총액_sql = '0';
  }

  $sql = "INSERT INTO tb_game_lotto_result
    SET drow = {$다음회차},
        num1 = {$num1},
        num2 = {$num2},
        num3 = {$num3},
        total_amount = {$총액_sql},
        status = 0,
        regdate = NOW()";
  if (!db_query($sql)) {
    echo 전송("❌ {$다음회차}회차 추첨 저장에 실패했어요. 잠시 후 다시 시도해주세요.");
    exit;
  }
  if (function_exists('로또누적_초기화') && $수수료누적 !== '0') {
    로또누적_초기화();
  }

  $표시1 = str_pad((string)$num1, 2, '0', STR_PAD_LEFT);
  $표시2 = str_pad((string)$num2, 2, '0', STR_PAD_LEFT);
  $표시3 = str_pad((string)$num3, 2, '0', STR_PAD_LEFT);

  $msg = "🎯 로또 {$다음회차}회차 추첨 완료\n";
  $msg .= "번호: {$표시1},{$표시2},{$표시3}\n";
  if (function_exists('로또_금액표시') && $총액스냅 !== '0') {
    $msg .= "당첨금 확정: " . 로또_금액표시($총액스냅) . "\n";
  }
  $msg .= "→ 이어서 `.로또 지급`";
  echo 전송($msg);
  exit;
}

if (preg_match('/^\.로또\s*$/u', trim((string)$status))) {
  require_once __DIR__ . '/lotto_amount.inc.php';
  $내티켓 = -1;
  $내idx = (int)($정보['idx'] ?? 0);
  if ($내idx > 0) {
    require_once __DIR__ . '/lotto_ticket.inc.php';
    if (function_exists('로또티켓_조회')) {
      $내티켓 = (int)로또티켓_조회($내idx);
    }
  }
  echo 전송(로또_채팅조회_문구($내티켓));
  exit;
}

if (preg_match('/^\.로또\s*지급\s*$/u', trim((string)$status))) {
  로또_관리명령_본방전용차단();
  global $관리자;
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 실행 가능합니다.");
    exit;
  }

  @set_time_limit(300);
  @ignore_user_abort(true);
  require_once __DIR__ . '/lotto_purchase.inc.php';

  $대상회차행 = db_select("
    SELECT drow, num1, num2, num3, CAST(IFNULL(total_amount, 0) AS CHAR) AS total_amount
    FROM tb_game_lotto_result
    WHERE status = 0
    ORDER BY drow ASC
    LIMIT 1
  ");
  $대상회차 = (int)($대상회차행['drow'] ?? 0);
  if ($대상회차 <= 0) {
    echo 전송("❌ 지급할 미정산 로또 회차가 없습니다.");
    exit;
  }

  // 추첨 직후 채점/총액이 덜 끝났을 수 있음 → 지급 전에 보완
  $미채점 = db_select("SELECT idx FROM tb_game_lotto WHERE drow = {$대상회차} AND status = 0 LIMIT 1");
  if (!empty($미채점['idx']) && function_exists('로또_회차_등수채점')) {
    로또_회차_등수채점(
      $대상회차,
      (int)($대상회차행['num1'] ?? 0),
      (int)($대상회차행['num2'] ?? 0),
      (int)($대상회차행['num3'] ?? 0)
    );
  }
  $총당첨금 = 로또_금액_조회($대상회차행, 'total_amount');
  if (!로또_금액양수($총당첨금) && function_exists('로또_회차금액_안전합')) {
    // 구버전 추첨(total=0) 보완: 표합 + 아직 안 접힌 config.로또누적
    if (is_file(__DIR__ . '/lotto_amount.inc.php')) {
      require_once __DIR__ . '/lotto_amount.inc.php';
    }
    $표합 = 로또_회차금액_안전합($대상회차);
    $총당첨금 = function_exists('로또누적_합산') ? 로또누적_합산($표합) : $표합;
    $총당첨금_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($총당첨금) : $총당첨금;
    @db_query("UPDATE tb_game_lotto_result SET total_amount = {$총당첨금_sql} WHERE drow = {$대상회차} LIMIT 1");
    if (function_exists('로또누적_초기화') && function_exists('로또누적_조회') && 로또누적_조회() !== '0') {
      로또누적_초기화();
    }
  }

  $등수집계 = 로또_회차_등수별_인원집계($대상회차);
  $일등수 = (int)$등수집계[1];
  $이등수 = (int)$등수집계[2];
  $삼등수 = (int)$등수집계[3];

  $지급계산 = 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수);
  $일등1인당 = 로또_금액문자열($지급계산['일등1인당']);
  $이등원금   = 로또_금액문자열($지급계산['이등원금']);
  $삼등원금   = 로또_금액문자열($지급계산['삼등원금']);

  $지급총액 = 로또_회차_지급_실행($대상회차, $일등1인당, $이등원금, $삼등원금);

  $일등소멸 = ($일등수 > 0)
    ? (function_exists('냥_비율내림')
        ? 로또_금액문자열(냥_비율내림($지급계산['일등풀'], 0.30))
        : 로또_금액차($지급계산['일등풀'], 로또_금액문자열($지급계산['일등지급풀'])))
    : '0';
  $남은금액 = 로또_금액차(로또_금액차($총당첨금, $지급총액), $일등소멸);
  $남은금액_sql = function_exists('냥_SQL정수') ? 냥_SQL정수($남은금액) : $남은금액;
  $다음회차 = $대상회차 + 1;
  if (로또_금액양수($남은금액)) {
    db_query("
      INSERT INTO tb_game_lotto
      SET nick = '이월금',
          drow = {$다음회차},
          num1 = 0,
          num2 = 0,
          num3 = 0,
          amount = {$남은금액_sql},
          status = 0,
          regdate = NOW()
    ");
    if (function_exists('로또_회차금액_캐시가산')) {
      로또_회차금액_캐시가산($다음회차, $남은금액);
    }
  }
  db_query("UPDATE tb_game_lotto_result SET status = 1, last_amount = {$남은금액_sql} WHERE drow = {$대상회차} LIMIT 1");

  // 웹훅 타임아웃 대비: 짧은 즉시응답 + 상세는 tb_lotto_info 큐로 공지
  $표시1 = str_pad((string)((int)($대상회차행['num1'] ?? 0)), 2, '0', STR_PAD_LEFT);
  $표시2 = str_pad((string)((int)($대상회차행['num2'] ?? 0)), 2, '0', STR_PAD_LEFT);
  $표시3 = str_pad((string)((int)($대상회차행['num3'] ?? 0)), 2, '0', STR_PAD_LEFT);
  $짧은결과 = "💸 로또 {$대상회차}회차 지급 완료\n";
  $짧은결과 .= "번호: {$표시1},{$표시2},{$표시3}\n";
  $짧은결과 .= "🥇{$일등수} · 🥈{$이등수} · 🥉{$삼등수}\n";
  $짧은결과 .= "총 지급: " . 로또_금액표시($지급총액) . "\n";
  if (로또_금액양수($남은금액)) {
    $짧은결과 .= "이월: " . 로또_금액표시($남은금액) . " → {$다음회차}회차\n";
  }
  $짧은결과 .= "상세: http://49.247.160.164/lotto_chk.php?result={$대상회차}\n";
  $짧은결과 .= "※ `.로또 지급 {$대상회차}` 로도 조회 가능";

  if (function_exists('로또_즉시응답')) {
    로또_즉시응답($짧은결과);
  } else {
    echo 전송($짧은결과);
  }

  // 상세 내역은 큐에 넣어 채팅 공지(웹훅이 끊겨도 결과 전달)
  $결과문 = 로또_회차_지급내역_문구($대상회차, '지급 완료');
  $결과_esc = addslashes($결과문);
  $item_esc = addslashes('로또지급' . $대상회차);
  @db_query("
    INSERT INTO tb_lotto_info
    SET status = 0, msg = '{$결과_esc}', leverage = 0, item = '{$item_esc}', regdate = NOW()
  ");

  if (!function_exists('로또_오래된회차_정리')) {
    require_once __DIR__ . '/lotto_purchase.inc.php';
  }
  if (function_exists('로또_오래된회차_정리')) {
    로또_오래된회차_정리($다음회차, 10);
  }
  exit;
}

if (trim($status) === '.로또 내역') {
  if (!function_exists('로또_진행회차')) {
    require_once __DIR__ . '/lotto_purchase.inc.php';
  }
  $진행회차 = 로또_진행회차();

  $닉_esc = addslashes($두자리닉넴);
  $내역rs = db_query("
    SELECT idx, num1, num2, num3, CAST(IFNULL(amount, 0) AS CHAR) AS amount
    FROM tb_game_lotto
    WHERE nick = '{$닉_esc}' AND drow = {$진행회차} AND status = 0
    ORDER BY idx ASC
  ");

  $lines = [];
  $총구매금액 = '0';
  $티켓건수 = 0;
  while ($내역rs && $row = db_fetch($내역rs)) {
    $n1 = str_pad((string)((int)$row['num1']), 2, '0', STR_PAD_LEFT);
    $n2 = str_pad((string)((int)$row['num2']), 2, '0', STR_PAD_LEFT);
    $n3 = str_pad((string)((int)$row['num3']), 2, '0', STR_PAD_LEFT);
    $amt = 로또_금액문자열($row['amount'] ?? 0);
    if (로또_금액양수($amt)) {
      $총구매금액 = 로또_금액합($총구매금액, $amt);
      $lines[] = ($n1 . "," . $n2 . "," . $n3) . " (" . 로또_금액표시($amt, $단위) . ")";
    } else {
      $티켓건수++;
      $lines[] = ($n1 . "," . $n2 . "," . $n3) . " (티켓)";
    }
  }

  if (empty($lines)) {
    echo 전송("🎟️ {$진행회차}회차 로또 내역\n구매 내역이 없습니다.");
    exit;
  }

  $msg = "🎟️ {$진행회차}회차 로또 내역 {$닉_esc}\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               ";
  $msg .= "구매건수: " . count($lines) . "건\n";
  if (로또_금액양수($총구매금액)) {
    $msg .= "총 구매금액: " . 로또_금액표시($총구매금액, $단위) . "\n";
  }
  if ($티켓건수 > 0) {
    $msg .= "티켓 구매: {$티켓건수}건\n";
  }
  $msg .= "\n" . implode("\n", $lines);
  echo 전송($msg);
  exit;
}

if (preg_match('/^\.로또\s*구매(?:\s+(\S+))?\s*$/u', trim($status), $_로또구매m)) {
  require_once __DIR__ . '/lotto_purchase.inc.php';
  로또_구매명령_실행($_로또구매m[1] ?? '');
}

if (
  preg_match('/^\.로또\s*(\d{1,2})\s*,\s*(\d{1,2})\s*,\s*(\d{1,2})\s*$/u', trim($status), $m) ||
  (
    empty($LOTTO_AUTO_HANDLED_EXTERNALLY)
    && preg_match('/^\.로또\s*자동(?:\s+(\d+))?\s*$/u', trim($status), $자동매치)
  )
) {
  require_once __DIR__ . '/lotto_purchase.inc.php';

  $isAutoLotto = !empty($자동매치);
  $요청개수   = 1;
  $수동_num1 = 0;
  $수동_num2 = 0;
  $수동_num3 = 0;

  if ($isAutoLotto) {
    if (isset($자동매치[1]) && $자동매치[1] !== '') {
      $요청개수 = (int)$자동매치[1];
    }
    로또_구매명령_실행((string)$요청개수);
  } else {
    $수동_num1 = (int)$m[1];
    $수동_num2 = (int)$m[2];
    $수동_num3 = (int)$m[3];

    if ($수동_num1 < 1 || $수동_num1 > 45 || $수동_num2 < 1 || $수동_num2 > 45 || $수동_num3 < 1 || $수동_num3 > 45) {
      echo 전송("❌ 로또 번호는 1~45 범위로 입력해주세요.\n예) .로또 1,22,33");
      exit;
    }
    if ($수동_num1 === $수동_num2 || $수동_num1 === $수동_num3 || $수동_num2 === $수동_num3) {
      echo 전송("❌ 로또 번호는 중복 없이 입력해주세요.\n예) .로또 1,22,33");
      exit;
    }
  }

  로또_번호구매_실행([
    '요청개수' => $요청개수,
    'isAuto'   => $isAutoLotto,
    'num1'     => $수동_num1,
    'num2'     => $수동_num2,
    'num3'     => $수동_num3,
    'useTicket' => true, // 수동·자동 모두 티켓만 (게임냥 구매 불가)
  ]);
}
