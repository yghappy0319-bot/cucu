<?php
/**
 * 채팅 로또: `.로또`, `.로또 내역`, `.로또 1,2,3`, `.로또 자동`, 관리자 `.로또 추첨` / `.로또 지급`, `.로또 지급 N`(회차 지급내역 조회)
 * 포함 전제: config.php 로드됨, $두자리닉넴·$status·$정보·$단위 사용 가능
 * $LOTTO_ADMIN_ONLY_INFO1 설정 시 추첨·지급은 본방(info1)에서만 (홍보방 차단)
 */

if (!function_exists('로또_관리명령_본방전용차단')) {
  function 로또_관리명령_본방전용차단() {
    if (!empty($LOTTO_ADMIN_ONLY_INFO1)) {
      echo 전송("❌ `.로또 추첨` · `.로또 지급` 은 본방(관리)에서만 실행할 수 있어요.");
      exit;
    }
  }
}

/** 로또 지급·조회 금액 축약 (조·억) */
if (!function_exists('로또_금액표시')) {
  function 로또_금액표시($amt, $단위접미 = null) {
    global $단위;
    $u = ($단위접미 !== null && $단위접미 !== '') ? (string)$단위접미
      : ((isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥');
    return function_exists('냥축약표시') ? 냥축약표시($amt, $u) : number_format((int)$amt) . $u;
  }
}

/** 1등 유무에 따라 등수별 풀·1인당 지급액 계산 (floor로 총 당첨금 초과 지급 방지) */
if (!function_exists('로또_지급금액_계산')) {
  function 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수) {
    $총당첨금 = max(0, (int)$총당첨금);
    $일등수 = max(0, (int)$일등수);
    $이등수 = max(0, (int)$이등수);
    $삼등수 = max(0, (int)$삼등수);

    $이등풀 = (int)floor($총당첨금 * 0.07);
    $삼등풀 = (int)floor($총당첨금 * 0.03);
    $일등풀 = ($일등수 > 0) ? (int)floor($총당첨금 * 0.90) : 0;
    $일등지급풀 = ($일등수 > 0 && $일등풀 > 0) ? (int)floor($일등풀 * 0.70) : 0;

    $일등1인당 = ($일등수 > 0 && $일등지급풀 > 0) ? (int)floor($일등지급풀 / $일등수) : 0;
    $이등원금 = ($이등수 > 0 && $이등풀 > 0) ? (int)floor($이등풀 / $이등수) : 0;
    $삼등원금 = ($삼등수 > 0 && $삼등풀 > 0) ? (int)floor($삼등풀 / $삼등수) : 0;

    return [
      '일등풀'     => $일등풀,
      '일등지급풀' => $일등지급풀,
      '일등소멸'   => max(0, $일등풀 - ($일등1인당 * $일등수)),
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
      SELECT rank, COUNT(*) AS cnt
      FROM tb_game_lotto
      WHERE drow = {$대상회차} AND rank BETWEEN 1 AND 3
      GROUP BY rank
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

/** 당첨 티켓별 지급 목록 문자열 배열 */
if (!function_exists('로또_회차_당첨자_지급목록')) {
  function 로또_회차_당첨자_지급목록($대상회차, array $등수별지급) {
    global $단위;
    $단위표 = (isset($단위) && (string)$단위 !== '') ? (string)$단위 : '냥';
    $대상회차 = (int)$대상회차;
    $등수아이콘 = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    $목록 = [];
    $rs = db_query("
      SELECT nick, rank, winnings
      FROM tb_game_lotto
      WHERE drow = {$대상회차}
        AND rank BETWEEN 1 AND 3
        AND nick != '이월금'
      ORDER BY rank ASC, idx ASC
    ");
    while ($rs && $row = db_fetch($rs)) {
      $닉 = trim((string)($row['nick'] ?? ''));
      $등수 = (int)($row['rank'] ?? 0);
      if ($닉 === '' || $등수 < 1 || $등수 > 3) {
        continue;
      }
      $지급액 = (int)($row['winnings'] ?? 0);
      if ($지급액 <= 0) {
        $지급액 = (int)($등수별지급[$등수] ?? 0);
      }
      if ($지급액 <= 0) {
        continue;
      }
      $아이콘 = $등수아이콘[$등수] ?? '•';
      $목록[] = "{$아이콘} {$닉} +" . 로또_금액표시($지급액, $단위표);
    }
    return $목록;
  }
}

/**
 * 회차 당첨금 일괄 지급 (티켓·회원 UPDATE bulk, 지급로그는 닉·등수별 1건)
 * @return int 총 지급액
 */
if (!function_exists('로또_회차_지급_실행')) {
  function 로또_회차_지급_실행($대상회차, $일등1인당, $이등원금, $삼등원금) {
    $대상회차 = (int)$대상회차;
    $등수설정 = [
      1 => ['금액' => (int)$일등1인당, '라벨' => '1등'],
      2 => ['금액' => (int)$이등원금,   '라벨' => '2등'],
      3 => ['금액' => (int)$삼등원금,   '라벨' => '3등'],
    ];
    $지급총액 = 0;

    foreach ($등수설정 as $등수 => $cfg) {
      $인당 = (int)$cfg['금액'];
      if ($인당 <= 0) {
        continue;
      }
      db_query("UPDATE tb_game_lotto SET winnings = {$인당} WHERE drow = {$대상회차} AND rank = {$등수}");

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
        $총지급 = $인당 * $건수;
        $닉_esc = addslashes($닉);
        db_query("UPDATE tb_member SET point = point + {$총지급} WHERE name = '{$닉_esc}' LIMIT 1");
        $상태 = "로또{$대상회차}회차-{$cfg['라벨']}";
        if ($건수 > 1) {
          $상태 .= "×{$건수}";
        }
        지급로그($상태, $닉, '', 0, $총지급);
        $지급총액 += $총지급;
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
      SELECT drow, num1, num2, num3, total_amount, status, last_amount
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
    $총당첨금 = (int)($회차행['total_amount'] ?? 0);
    $정산완료 = (int)($회차행['status'] ?? 0) === 1;
    $이월잔액 = (int)($회차행['last_amount'] ?? 0);

    $등수집계 = 로또_회차_등수별_인원집계($대상회차);
    $일등수 = (int)$등수집계[1];
    $이등수 = (int)$등수집계[2];
    $삼등수 = (int)$등수집계[3];

    $지급계산 = 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수);
    $일등1인당 = (int)$지급계산['일등1인당'];
    $이등원금   = (int)$지급계산['이등원금'];
    $삼등원금   = (int)$지급계산['삼등원금'];

    $등수별지급 = [1 => $일등1인당, 2 => $이등원금, 3 => $삼등원금];
    $당첨내역 = 로또_회차_당첨자_지급목록($대상회차, $등수별지급);
    $지급총액 = ($일등수 * $일등1인당) + ($이등수 * $이등원금) + ($삼등수 * $삼등원금);

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
      if ($이월잔액 > 0) {
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

if (preg_match('/^\.로또\s*지급\s+(\d+)\s*$/u', trim($status), $지급조회m)) {
  echo 전송(로또_회차_지급내역_문구((int)$지급조회m[1]));
  exit;
}

if (trim($status) === '.로또 추첨') {
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

  // $현재시 = (int)date('H');
  // $현재분 = (int)date('i');
  // if (!($현재시 === 23 && $현재분 >= 00)) {
  //   echo 전송("❌ 로또 추첨은 매일 23:00~23:59 사이에만 가능합니다.");
  //   exit;
  // }

  $후보숫자 = range(1, 45);
  shuffle($후보숫자);
  $추첨번호 = array_slice($후보숫자, 0, 3);
  sort($추첨번호);
  $num1 = (int)$추첨번호[0];
  $num2 = (int)$추첨번호[1];
  $num3 = (int)$추첨번호[2];
  $회차총액행 = db_select("SELECT IFNULL(SUM(amount), 0) AS total_amount FROM tb_game_lotto WHERE drow = {$다음회차} AND status = 0");
  $회차총액 = (int)($회차총액행['total_amount'] ?? 0);

  $sql = "    INSERT INTO tb_game_lotto_result
    SET drow = {$다음회차},
        num1 = {$num1},
        num2 = {$num2},
        num3 = {$num3},
        total_amount = {$회차총액},
        status = 0,
        regdate = NOW()";
  db_query($sql);

  // 해당 회차 구매 채점 — 건별 UPDATE 대신 1회 bulk UPDATE (N+1 쿼리 제거)
  $매치식 = "( (num1 IN ({$num1}, {$num2}, {$num3})) + (num2 IN ({$num1}, {$num2}, {$num3})) + (num3 IN ({$num1}, {$num2}, {$num3})) )";
  db_query("
    UPDATE tb_game_lotto
    SET rank = CASE
          WHEN {$매치식} = 3 THEN 1
          WHEN {$매치식} = 2 THEN 2
          WHEN {$매치식} = 1 THEN 3
          ELSE 0
        END,
        status = 1
    WHERE drow = {$다음회차}
      AND status = 0
  ");

  $표시1 = str_pad((string)$num1, 2, '0', STR_PAD_LEFT);
  $표시2 = str_pad((string)$num2, 2, '0', STR_PAD_LEFT);
  $표시3 = str_pad((string)$num3, 2, '0', STR_PAD_LEFT);
  echo 전송("🎯 로또 {$다음회차}회차 추첨 완료\n번호: {$표시1},{$표시2},{$표시3}");
  exit;
}

if (trim($status) === '.로또') {
  require_once __DIR__ . '/lotto_ticket.inc.php';

  $완료회차행 = db_select("
    SELECT IFNULL(MAX(drow), 0) AS max_drow
    FROM tb_game_lotto_result
    WHERE status = 1
  ");
  $완료회차 = (int)($완료회차행['max_drow'] ?? 0);
  $진행회차 = $완료회차 + 1;

  $총액행 = db_select("SELECT IFNULL(SUM(amount), 0) AS total_amount FROM tb_game_lotto WHERE drow = {$진행회차} AND status = 0");
  $총액 = (int)($총액행['total_amount'] ?? 0);

  $일등풀 = (int)floor($총액 * 0.90);
  $일등지급풀 = (int)floor($일등풀 * 0.70);
  $이등풀 = (int)floor($총액 * 0.07);
  $삼등풀 = (int)floor($총액 * 0.03);

  $로또금액표시 = function ($amt) {
    return 로또_금액표시($amt);
  };

  $msg = "🎟️ {$진행회차}회차 로또 | {$로또금액표시($총액)}\n\n                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ";
  // $msg .= "총 합금액: " . $로또금액표시($총액) . "\n\n";
  $msg .= "【🥇 1등이 있을 때】\n";
  $msg .= "· 1등: 총액 90% — 지급 70%·소멸 30% 후 등분 (지급풀 " . $로또금액표시($일등지급풀) . ")\n";
  $msg .= "· 2등: 총액 7% — 2등끼리 등분 (풀 " . $로또금액표시($이등풀) . ")\n";
  $msg .= "· 3등: 총액 3% — 3등끼리 등분 (풀 " . $로또금액표시($삼등풀) . ")\n\n";
  $msg .= "【1등이 없을 때】\n";
  $msg .= "· 🥈 2등: 총액 7% — 2등끼리 등분 (풀 " . $로또금액표시($이등풀) . ")\n";
  $msg .= "· 🥉 3등: 총액 3% — 3등끼리 등분 (풀 " . $로또금액표시($삼등풀) . ")\n";
  $msg .= "· 나머지 90% 이월(다음 회차)";

  $msg .= "\n\n1등: 3개 번호 일치\n2등: 2개 번호 일치\n3등: 1개 번호 일치";
  $msg .= "\n매일 23:00 추첨";
  $내idx = (int)($정보['idx'] ?? 0);
  if ($내idx > 0) {
    $보유티켓 = 로또티켓_조회($내idx);
    $msg .= "\n\n🎟️ 내 로또 티켓: {$보유티켓}장";
  }
  echo 전송($msg);
  exit;
}

if (trim($status) === '.로또 지급') {
  로또_관리명령_본방전용차단();
  global $관리자;
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 실행 가능합니다.");
    exit;
  }

  $대상회차행 = db_select("
    SELECT drow, total_amount
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

  $총당첨금 = (int)($대상회차행['total_amount'] ?? 0);

  $등수집계 = 로또_회차_등수별_인원집계($대상회차);
  $일등수 = (int)$등수집계[1];
  $이등수 = (int)$등수집계[2];
  $삼등수 = (int)$등수집계[3];

  $지급계산 = 로또_지급금액_계산($총당첨금, $일등수, $이등수, $삼등수);
  $일등1인당 = (int)$지급계산['일등1인당'];
  $이등원금   = (int)$지급계산['이등원금'];
  $삼등원금   = (int)$지급계산['삼등원금'];

  $지급총액 = 로또_회차_지급_실행($대상회차, $일등1인당, $이등원금, $삼등원금);

  $일등소멸 = ($일등수 > 0) ? (int)floor((int)$지급계산['일등풀'] * 0.30) : 0;
  $남은금액 = max(0, $총당첨금 - $지급총액 - $일등소멸);
  $다음회차 = $대상회차 + 1;
  if ($남은금액 > 0) {
    db_query("
      INSERT INTO tb_game_lotto
      SET nick = '이월금',
          drow = {$다음회차},
          num1 = 0,
          num2 = 0,
          num3 = 0,
          amount = {$남은금액},
          status = 0,
          regdate = NOW()
    ");
  }
  db_query("UPDATE tb_game_lotto_result SET status = 1, last_amount = {$남은금액} WHERE drow = {$대상회차} LIMIT 1");

  echo 전송(로또_회차_지급내역_문구($대상회차, '지급 완료'));
  exit;
}

if (trim($status) === '.로또 내역') {
  $완료회차행 = db_select("
    SELECT IFNULL(MAX(drow), 0) AS max_drow
    FROM tb_game_lotto_result
    WHERE status = 1
  ");
  $완료회차 = (int)($완료회차행['max_drow'] ?? 0);
  $진행회차 = $완료회차 + 1;

  $닉_esc = addslashes($두자리닉넴);
  $내역rs = db_query("
    SELECT idx, num1, num2, num3, amount
    FROM tb_game_lotto
    WHERE nick = '{$닉_esc}' AND drow = {$진행회차} AND status = 0
    ORDER BY idx ASC
  ");

  $lines = [];
  $총구매금액 = 0;
  $티켓건수 = 0;
  while ($내역rs && $row = db_fetch($내역rs)) {
    $n1 = str_pad((string)((int)$row['num1']), 2, '0', STR_PAD_LEFT);
    $n2 = str_pad((string)((int)$row['num2']), 2, '0', STR_PAD_LEFT);
    $n3 = str_pad((string)((int)$row['num3']), 2, '0', STR_PAD_LEFT);
    $amt = (int)($row['amount'] ?? 0);
    if ($amt > 0) {
      $총구매금액 += $amt;
      $lines[] = ($n1 . "," . $n2 . "," . $n3) . " (" . number_format($amt) . "{$단위})";
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
  if ($총구매금액 > 0) {
    $msg .= "총 구매금액: " . number_format($총구매금액) . "{$단위}\n";
  }
  if ($티켓건수 > 0) {
    $msg .= "티켓 구매: {$티켓건수}건\n";
  }
  $msg .= "\n" . implode("\n", $lines);
  echo 전송($msg);
  exit;
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
    if ($요청개수 < 1) {
      echo 전송("❌ 구매 개수는 1 이상이어야 해요.\n예) .로또 자동 5");
      exit;
    }
    $최대일괄 = 50;
    if ($요청개수 > $최대일괄) {
      echo 전송("❌ 한 번에 최대 {$최대일괄}개까지만 가능해요. (요청: {$요청개수}개)");
      exit;
    }
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
    'useTicket' => ($isAutoLotto && $요청개수 === 50),
  ]);
}
