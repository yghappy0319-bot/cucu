<?php
/**
 * 승부예측(파리뮤추얼) — .승부예측 팀1vs팀2[vs팀3] · 팀명+금액 · .배팅취소 · .승부예측마감/재개 · .최종결과 승리팀(관리자)
 * 포함 전: $status, $두자리닉넴, $정보, $관리자, $단위, $호칭
 */
$승부예측_최소배팅 = 1000;
$승부예측_최대팀수 = 3;
$승부예측_배팅취소_수수료율 = 0.20;

if (!function_exists('승부예측_테이블_ensure')) {
  function 승부예측_테이블_ensure() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;
    db_query("CREATE TABLE IF NOT EXISTS tb_match_predict (
      idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      team1 VARCHAR(50) NOT NULL,
      team2 VARCHAR(50) NOT NULL,
      team3 VARCHAR(50) DEFAULT NULL,
      status TINYINT NOT NULL DEFAULT 0 COMMENT '0=배팅중 2=배팅마감 1=정산완료',
      winner VARCHAR(50) DEFAULT NULL,
      win_mult INT NOT NULL DEFAULT 2 COMMENT '승리 배수',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      settled_at DATETIME DEFAULT NULL,
      KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $col = db_select("SHOW COLUMNS FROM tb_match_predict LIKE 'team3'");
    if (empty($col['Field'])) {
      @db_query("ALTER TABLE tb_match_predict ADD COLUMN team3 VARCHAR(50) DEFAULT NULL AFTER team2");
    }
    db_query("CREATE TABLE IF NOT EXISTS tb_match_predict_bet (
      idx INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      match_idx INT UNSIGNED NOT NULL,
      nick VARCHAR(50) NOT NULL,
      team VARCHAR(50) NOT NULL,
      amount INT UNSIGNED NOT NULL DEFAULT 0,
      regdate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uk_match_nick_team (match_idx, nick, team),
      KEY idx_match_team (match_idx, team)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $has_team_uk = db_select("SHOW INDEX FROM tb_match_predict_bet WHERE Key_name = 'uk_match_nick_team' LIMIT 1");
    if (empty($has_team_uk['Key_name'])) {
      $has_old_uk = db_select("SHOW INDEX FROM tb_match_predict_bet WHERE Key_name = 'uk_match_nick' LIMIT 1");
      if (!empty($has_old_uk['Key_name'])) {
        @db_query("ALTER TABLE tb_match_predict_bet DROP INDEX uk_match_nick");
      }
      @db_query("ALTER TABLE tb_match_predict_bet ADD UNIQUE KEY uk_match_nick_team (match_idx, nick, team)");
    }
    db_query("CREATE TABLE IF NOT EXISTS tb_match_predict_cancel_used (
      match_idx INT UNSIGNED NOT NULL,
      nick VARCHAR(50) NOT NULL,
      used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (match_idx, nick)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}

if (!function_exists('승부예측_진행중')) {
  function 승부예측_진행중() {
    승부예측_테이블_ensure();
    return db_select("SELECT * FROM tb_match_predict WHERE status IN (0, 2) ORDER BY idx DESC LIMIT 1");
  }
}

if (!function_exists('승부예측_배팅가능')) {
  function 승부예측_배팅가능($경기) {
    return (int)($경기['status'] ?? -1) === 0;
  }
}

if (!function_exists('승부예측_팀파싱')) {
  /** @return string[]|null 2~3팀 */
  function 승부예측_팀파싱($raw) {
    global $승부예측_최대팀수;
    $raw = trim((string)$raw);
    if ($raw === '') {
      return null;
    }
    $parts = preg_split('/\s*(?:vs|VS|Vs|v\s*S|대)\s*/ui', $raw, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
      return null;
    }
    $teams = [];
    foreach ($parts as $part) {
      $name = trim((string)$part);
      if ($name === '') {
        continue;
      }
      $teams[] = $name;
    }
    if (count($teams) < 2 || count($teams) > (int)$승부예측_최대팀수) {
      return null;
    }
    if (count($teams) !== count(array_unique($teams))) {
      return null;
    }
    return array_values($teams);
  }
}

if (!function_exists('승부예측_팀목록')) {
  /** @return string[] */
  function 승부예측_팀목록($경기) {
    $teams = [];
    foreach (['team1', 'team2', 'team3'] as $key) {
      $name = trim((string)($경기[$key] ?? ''));
      if ($name !== '') {
        $teams[] = $name;
      }
    }
    return $teams;
  }
}

if (!function_exists('승부예측_대결표시')) {
  function 승부예측_대결표시($팀목록) {
    return implode(' vs ', $팀목록);
  }
}

if (!function_exists('승부예측_배팅안내')) {
  function 승부예측_배팅안내($팀목록) {
    $lines = [];
    foreach ($팀목록 as $팀명) {
      $lines[] = "`{$팀명}금액`";
    }
    return implode(' · ', $lines);
  }
}

if (!function_exists('승부예측_팀일치')) {
  function 승부예측_팀일치($입력, $팀명) {
    return trim((string)$입력) === trim((string)$팀명);
  }
}

if (!function_exists('승부예측_승리팀_매칭')) {
  function 승부예측_승리팀_매칭($입력, $팀목록) {
    foreach ($팀목록 as $팀명) {
      if (승부예측_팀일치($입력, $팀명)) {
        return $팀명;
      }
    }
    return null;
  }
}

if (!function_exists('승부예측_본인팀_배팅불가')) {
  /** 닉네임과 팀명이 같으면 해당 팀 배팅 불가 */
  function 승부예측_본인팀_배팅불가($닉, $팀명) {
    return 승부예측_팀일치($닉, $팀명);
  }
}

if (!function_exists('승부예측_배팅취소_환불계산')) {
  /** @return array{원금:int,수수료:int,환불:int} */
  function 승부예측_배팅취소_환불계산($배팅금) {
    global $승부예측_배팅취소_수수료율;
    $원금 = max(0, (int)$배팅금);
    if ($원금 <= 0) {
      return ['원금' => 0, '수수료' => 0, '환불' => 0];
    }
    $수수료 = (int)floor($원금 * (float)$승부예측_배팅취소_수수료율);
    $환불 = $원금 - $수수료;
    return ['원금' => $원금, '수수료' => $수수료, '환불' => $환불];
  }
}

if (!function_exists('승부예측_배팅취소_사용함')) {
  function 승부예측_배팅취소_사용함($mid, $nick) {
    $mid = (int)$mid;
    $nick_esc = addslashes(trim((string)$nick));
    if ($mid < 1 || $nick_esc === '') {
      return false;
    }
    승부예측_테이블_ensure();
    $row = db_select("SELECT nick FROM tb_match_predict_cancel_used WHERE match_idx = {$mid} AND nick = '{$nick_esc}' LIMIT 1");
    return !empty($row['nick']);
  }
}

if (!function_exists('승부예측_배팅취소_사용기록')) {
  function 승부예측_배팅취소_사용기록($mid, $nick) {
    $mid = (int)$mid;
    $nick_esc = addslashes(trim((string)$nick));
    if ($mid < 1 || $nick_esc === '') {
      return false;
    }
    승부예측_테이블_ensure();
    return (bool)db_query("INSERT INTO tb_match_predict_cancel_used (match_idx, nick) VALUES ({$mid}, '{$nick_esc}')");
  }
}

if (!function_exists('승부예측_팀합계')) {
  function 승부예측_팀합계($mid, $팀명) {
    $mid = (int)$mid;
    $팀_esc = addslashes($팀명);
    $row = db_select("SELECT COALESCE(SUM(amount), 0) AS s, COUNT(*) AS c FROM tb_match_predict_bet WHERE match_idx = {$mid} AND team = '{$팀_esc}'");
    return [
      'amount' => (int)($row['s'] ?? 0),
      'count' => (int)($row['c'] ?? 0),
    ];
  }
}

if (!function_exists('승부예측_총풀')) {
  function 승부예측_총풀($mid) {
    $mid = (int)$mid;
    $row = db_select("SELECT COALESCE(SUM(amount), 0) AS s FROM tb_match_predict_bet WHERE match_idx = {$mid}");
    return (int)($row['s'] ?? 0);
  }
}

if (!function_exists('승부예측_배당표시')) {
  /** 파리뮤추얼 배당 = 총풀 / 해당팀 배팅합 */
  function 승부예측_배당표시($총풀, $팀합) {
    $총풀 = (int)$총풀;
    $팀합 = (int)$팀합;
    if ($팀합 <= 0 || $총풀 <= 0) {
      return '-';
    }
    $배 = $총풀 / $팀합;
    if ($배 >= 100) {
      return '×' . number_format($배, 1);
    }
    return '×' . number_format($배, 2);
  }
}

if (!function_exists('승부예측_예상지급')) {
  function 승부예측_예상지급($내배팅, $총풀, $팀합) {
    $내배팅 = (int)$내배팅;
    $총풀 = (int)$총풀;
    $팀합 = (int)$팀합;
    if ($내배팅 <= 0 || $팀합 <= 0 || $총풀 <= 0) {
      return 0;
    }
    return (int)floor(($내배팅 * $총풀) / $팀합);
  }
}

if (!function_exists('승부예측_지급액_계산')) {
  /** 승리팀 배팅자에게 총풀을 비율 분배 (정수 냥, 잔여 1냥씩 배분) */
  function 승부예측_지급액_계산($승목록, $총풀, $승리팀합) {
    $총풀 = (int)$총풀;
    $승리팀합 = (int)$승리팀합;
    if ($총풀 <= 0 || $승리팀합 <= 0 || empty($승목록)) {
      return [];
    }

    $지급 = [];
    $분배합 = 0;
    $fracs = [];

    foreach ($승목록 as $row) {
      $bet = (int)$row['amount'];
      if ($bet <= 0) {
        continue;
      }
      $raw = ($bet * $총풀) / $승리팀합;
      $pay = (int)floor($raw);
      $nick = $row['nick'];
      $지급[$nick] = $pay;
      $분배합 += $pay;
      $fracs[] = ['nick' => $nick, 'frac' => $raw - $pay];
    }

    $나머지 = $총풀 - $분배합;
    if ($나머지 > 0 && count($fracs) > 0) {
      usort($fracs, function ($a, $b) {
        if ($b['frac'] == $a['frac']) {
          return strcmp($a['nick'], $b['nick']);
        }
        return ($b['frac'] <=> $a['frac']);
      });
      for ($j = 0; $j < $나머지; $j++) {
        $nick = $fracs[$j % count($fracs)]['nick'];
        $지급[$nick] = ($지급[$nick] ?? 0) + 1;
      }
    }

    return $지급;
  }
}

if (!function_exists('승부예측_현황문구')) {
  function 승부예측_현황문구($경기) {
    global $승부예측_최소배팅;
    $mid = (int)$경기['idx'];
    $팀목록 = 승부예측_팀목록($경기);
    $총풀 = 승부예측_총풀($mid);
    $마감 = !승부예측_배팅가능($경기);

    $msg = "⚽ 승부예측 진행 중 (파리뮤추얼)\n";
    $msg .= 승부예측_대결표시($팀목록) . "\n";
    if ($마감) {
      $msg .= "🔒 배팅 마감 — 추가 배팅 불가\n";
    }
    $msg .= "\n";
    if (!$마감) {
      $msg .= "📌 배팅: " . 승부예측_배팅안내($팀목록) . " (보유{$GLOBALS['단위']}, 최소 {$승부예측_최소배팅}{$GLOBALS['단위']})\n";
      $msg .= "📌 승리팀이 배팅 풀 전체를 비율로 나눠 가져가요 (적은 쪽 승리 시 배당 UP)\n";
      $msg .= "※ 본인 닉네임과 같은 팀에는 배팅 불가\n";
      $msg .= "※ `.배팅취소` — 본인 배팅 취소 (20% 수수료 후 본냥 환불 · 경기당 1회)\n\n";
    }
    $msg .= "💰 총 풀 " . number_format($총풀) . "{$GLOBALS['단위']}\n\n";
    foreach ($팀목록 as $팀명) {
      $합 = 승부예측_팀합계($mid, $팀명);
      $msg .= "【{$팀명}】 " . number_format($합['amount']) . "{$GLOBALS['단위']} · {$합['count']}명 · 승리 시 " . 승부예측_배당표시($총풀, $합['amount']) . "\n";
    }
    $정산예시 = [];
    foreach ($팀목록 as $팀명) {
      $정산예시[] = "`.최종결과 {$팀명}`";
    }
    $msg .= "\n정산(관리자): " . implode(' · ', $정산예시);
    return $msg;
  }
}

$status_trim = trim((string)$status);

// ----- .승부예측방법 -----
if ($status_trim === '.승부예측방법') {
  global $승부예측_최소배팅, $승부예측_최대팀수;
  $msg = "⚽ 승부예측 게임 (파리뮤추얼)\n\n";
  $msg .= "1️⃣ 관리자 — 경기 개설 (2~{$승부예측_최대팀수}팀)\n";
  $msg .= "`.승부예측 체코vs대한민국`\n";
  $msg .= "`.승부예측 다오vs하늘vs여름`\n\n";
  $msg .= "2️⃣ 참가자 — 배팅 (보유{$단위}, 최소 {$승부예측_최소배팅}{$단위})\n";
  $msg .= "`체코1` · `대한민국5` · `여름3` 등 팀명+금액\n";
  $msg .= "※ 팀별 배팅 · 여러 팀 동시 배팅 가능 · 같은 팀 재배팅 시 금액 추가\n";
  $msg .= "※ 본인 닉네임과 같은 팀에는 배팅 불가 (예: 다오 → 다오 배팅 X)\n";
  $msg .= "※ `.배팅취소` — 본인 배팅 취소 (배팅금 20% 수수료 · 나머지 본냥 환불 · 경기당 1회)\n\n";
  $msg .= "3️⃣ 관리자 — 배팅 마감 / 재개\n";
  $msg .= "`.승부예측마감` (추가 배팅 중지)\n";
  $msg .= "`.승부예측재개` (마감 해제 · 배팅 재개)\n\n";
  $msg .= "4️⃣ 관리자 — 정산\n";
  $msg .= "`.최종결과 대한민국` (승리팀 지정)\n";
  $msg .= "· 배팅 풀만 사용 (금고 무관)\n";
  $msg .= "· 적게 걸린 팀이 이기면 배당 UP\n";
  $msg .= "예) 체코 80 / 대한민국 20 → 대한민국 승리 시 ×5\n\n";
  $msg .= "`.승부예측` 현황 · `.승부예측취소` 취소(관리자)";
  echo 전송($msg);
  exit;
}

// ----- .승부예측마감 (관리자, 배팅만 중지) -----
if ($status_trim === '.승부예측마감') {
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 배팅 마감할 수 있어요.");
    exit;
  }
  승부예측_테이블_ensure();
  $경기 = 승부예측_진행중();
  if (empty($경기['idx'])) {
    echo 전송("❌ 마감할 진행 중 경기가 없어요.");
    exit;
  }
  if (!승부예측_배팅가능($경기)) {
    echo 전송("🔒 이미 배팅이 마감된 경기예요.\n\n" . 승부예측_현황문구($경기));
    exit;
  }
  $mid = (int)$경기['idx'];
  db_query("UPDATE tb_match_predict SET status = 2 WHERE idx = {$mid} AND status = 0 LIMIT 1");
  $경기['status'] = 2;
  echo 전송("🔒 승부예측 배팅 마감!\n" . 승부예측_대결표시(승부예측_팀목록($경기)) . "\n\n" . 승부예측_현황문구($경기));
  exit;
}

// ----- .승부예측재개 (관리자, 배팅 마감 해제) -----
if ($status_trim === '.승부예측재개') {
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 배팅을 재개할 수 있어요.");
    exit;
  }
  승부예측_테이블_ensure();
  $경기 = 승부예측_진행중();
  if (empty($경기['idx'])) {
    echo 전송("❌ 재개할 진행 중 경기가 없어요.");
    exit;
  }
  if (승부예측_배팅가능($경기)) {
    echo 전송("✅ 이미 배팅 중인 경기예요.\n\n" . 승부예측_현황문구($경기));
    exit;
  }
  $mid = (int)$경기['idx'];
  db_query("UPDATE tb_match_predict SET status = 0 WHERE idx = {$mid} AND status = 2 LIMIT 1");
  $경기['status'] = 0;
  echo 전송("🔓 승부예측 배팅 재개!\n" . 승부예측_대결표시(승부예측_팀목록($경기)) . "\n\n" . 승부예측_현황문구($경기));
  exit;
}

// ----- .승부예측 (개설 / 현황) -----
if (strpos($status_trim, '.승부예측') === 0) {
  승부예측_테이블_ensure();

  if ($status_trim === '.승부예측') {
    $경기 = 승부예측_진행중();
    if (empty($경기['idx'])) {
      echo 전송("⚽ 진행 중인 승부예측이 없어요.\n관리자: `.승부예측 체코vs대한민국` · `.승부예측 다오vs하늘vs여름`");
      exit;
    }
    echo 전송(승부예측_현황문구($경기));
    exit;
  }

  if (preg_match('/^\.승부예측\s+(.+)$/u', $status_trim, $m)) {
    if (!in_array($두자리닉넴, (array)$관리자, true)) {
      echo 전송("❌ 관리자만 승부예측을 개설할 수 있어요.");
      exit;
    }
    $팀목록 = 승부예측_팀파싱($m[1]);
    if ($팀목록 === null) {
      echo 전송("❌ 형식: `.승부예측 체코vs대한민국` · `.승부예측 다오vs하늘vs여름`\n(vs / VS / 대 로 구분 · 2~3팀)");
      exit;
    }
    $기존 = 승부예측_진행중();
    if (!empty($기존['idx'])) {
      echo 전송("❌ 이미 진행 중인 경기가 있어요.\n\n" . 승부예측_현황문구($기존) . "\n\n먼저 `.승부예측취소` · `.승부예측마감` · `.최종결과` 로 처리해 주세요.");
      exit;
    }
    $t1 = $팀목록[0];
    $t2 = $팀목록[1];
    $t3 = $팀목록[2] ?? '';
    $t1_esc = addslashes($t1);
    $t2_esc = addslashes($t2);
    $t3_sql = ($t3 !== '') ? "'" . addslashes($t3) . "'" : 'NULL';
    db_query("INSERT INTO tb_match_predict (team1, team2, team3, status, win_mult) VALUES ('{$t1_esc}', '{$t2_esc}', {$t3_sql}, 0, 0)");
    $msg = "⚽ 승부예측 시작!\n" . 승부예측_대결표시($팀목록) . "\n\n";
    $msg .= 승부예측_배팅안내($팀목록) . " (최소 " . number_format($승부예측_최소배팅) . "{$단위})\n";
    $msg .= "파리뮤추얼 · `.승부예측` 현황 · `.승부예측마감` · `.승부예측재개` · 정산 `.최종결과`";
    echo 전송($msg);
    exit;
  }
}

// ----- .승부예측취소 (관리자, 환불) -----
if ($status_trim === '.승부예측취소') {
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 관리자만 취소할 수 있어요.");
    exit;
  }
  승부예측_테이블_ensure();
  $경기 = 승부예측_진행중();
  if (empty($경기['idx'])) {
    echo 전송("❌ 취소할 진행 중 경기가 없어요.");
    exit;
  }
  $mid = (int)$경기['idx'];
  $환불수 = 0;
  $환불합 = 0;
  $rs = db_query("SELECT nick, amount FROM tb_match_predict_bet WHERE match_idx = {$mid}");
  while ($row = db_fetch($rs)) {
    $amt = (int)$row['amount'];
    if ($amt <= 0) {
      continue;
    }
    $nick_esc = addslashes($row['nick']);
    db_query("UPDATE tb_member SET newpoint = newpoint + {$amt} WHERE name = '{$nick_esc}' LIMIT 1");
    지급로그('승부예측-취소환불', $row['nick'], '', 0, $amt);
    $환불수++;
    $환불합 += $amt;
  }
  db_query("DELETE FROM tb_match_predict_bet WHERE match_idx = {$mid}");
  db_query("UPDATE tb_match_predict SET status = 1, settled_at = NOW() WHERE idx = {$mid}");
  echo 전송("✅ 승부예측 취소 · " . 승부예측_대결표시(승부예측_팀목록($경기)) . "\n환불 {$환불수}명 · " . number_format($환불합) . "{$단위}");
  exit;
}

// ----- .배팅취소 (본인 배팅 취소 · 20% 수수료 후 본냥 환불) -----
if ($status_trim === '.배팅취소') {
  if (!$두자리닉넴 || empty($정보['idx'])) {
    echo 전송("❌ 회원 정보를 확인할 수 없어요.");
    exit;
  }
  승부예측_테이블_ensure();
  $경기 = 승부예측_진행중();
  if (empty($경기['idx'])) {
    echo 전송("❌ 진행 중인 승부예측이 없어요.");
    exit;
  }
  $mid = (int)$경기['idx'];
  $닉_esc = addslashes($두자리닉넴);
  if (승부예측_배팅취소_사용함($mid, $두자리닉넴)) {
    echo 전송("❌ 이번 경기에서는 이미 `.배팅취소`를 사용했어요.\n(경기당 1회만 가능)");
    exit;
  }
  $배팅행들 = [];
  $rs = db_query("SELECT team, amount FROM tb_match_predict_bet WHERE match_idx = {$mid} AND nick = '{$닉_esc}'");
  while ($row = db_fetch($rs)) {
    if ((int)($row['amount'] ?? 0) <= 0) {
      continue;
    }
    $배팅행들[] = $row;
  }
  if (empty($배팅행들)) {
    echo 전송("❌ 취소할 배팅이 없어요.");
    exit;
  }

  $팀표시 = [];
  $환불계산 = ['원금' => 0, '수수료' => 0, '환불' => 0];
  foreach ($배팅행들 as $배팅행) {
    $팀표시[] = (string)$배팅행['team'];
    $부분 = 승부예측_배팅취소_환불계산((int)$배팅행['amount']);
    $환불계산['원금'] += (int)$부분['원금'];
    $환불계산['수수료'] += (int)$부분['수수료'];
    $환불계산['환불'] += (int)$부분['환불'];
  }
  $원금 = (int)$환불계산['원금'];
  $수수료 = (int)$환불계산['수수료'];
  $환불 = (int)$환불계산['환불'];

  db_query("DELETE FROM tb_match_predict_bet WHERE match_idx = {$mid} AND nick = '{$닉_esc}'");
  승부예측_배팅취소_사용기록($mid, $두자리닉넴);
  if ($환불 > 0) {
    db_query("UPDATE tb_member SET newpoint = newpoint + {$환불} WHERE name = '{$닉_esc}' LIMIT 1");
  }
  지급로그('승부예측-배팅취소', $두자리닉넴, '', $수수료, $환불);

  $msg = "✅ {$두자리닉넴} · 배팅 취소 (" . implode(', ', $팀표시) . ")\n";
  $msg .= "배팅 " . number_format($원금) . "{$단위} · 수수료 20% " . number_format($수수료) . "{$단위}\n";
  $msg .= "본냥 환불 +" . number_format($환불) . "{$단위}";
  echo 전송($msg);
  exit;
}

// ----- .최종결과 승리팀 (관리자 정산, 진행 중 경기 있을 때만) -----
if (strpos($status_trim, '.최종결과') === 0) {
  승부예측_테이블_ensure();
  $경기 = 승부예측_진행중();
  if (empty($경기['idx'])) {
    return;
  }
  if (!in_array($두자리닉넴, (array)$관리자, true)) {
    echo 전송("❌ 정산(`.최종결과`)은 관리자만 할 수 있어요.");
    exit;
  }
  $팀목록 = 승부예측_팀목록($경기);
  if (!preg_match('/^\.최종결과\s+(\S.+)$/u', $status_trim, $m)) {
    $정산예시 = [];
    foreach ($팀목록 as $팀명) {
      $정산예시[] = "`.최종결과 {$팀명}`";
    }
    echo 전송("❌ 승리팀을 입력해 주세요.\n예) " . implode(' · ', $정산예시));
    exit;
  }
  $승리팀 = 승부예측_승리팀_매칭(trim($m[1]), $팀목록);
  if ($승리팀 === null) {
    echo 전송("❌ 승리팀은 `" . implode('` · `', $팀목록) . "` 중 하나여야 해요.");
    exit;
  }

  $mid = (int)$경기['idx'];
  $승리_esc = addslashes($승리팀);
  $총풀 = 승부예측_총풀($mid);

  $msg = "🏆 승부예측 결과 · " . 승부예측_대결표시($팀목록) . "\n";
  $msg .= "승리: {$승리팀} · 총 풀 " . number_format($총풀) . "{$단위}\n\n";

  $지급수 = 0;
  $지급합 = 0;

  $승팀합 = 승부예측_팀합계($mid, $승리팀);
  $패배합 = $총풀 - (int)$승팀합['amount'];

  $승rs = db_query("SELECT nick, amount FROM tb_match_predict_bet WHERE match_idx = {$mid} AND team = '{$승리_esc}' ORDER BY amount DESC, nick ASC");
  $승목록 = [];
  while ($row = db_fetch($승rs)) {
    $승목록[] = $row;
  }

  if (count($승목록) === 0) {
    $msg .= "승리팀 배팅자 없음 · 전체 " . number_format($총풀) . "{$단위} 소멸";
  } else {
    $지급표 = 승부예측_지급액_계산($승목록, $총풀, (int)$승팀합['amount']);
    $배당표시 = 승부예측_배당표시($총풀, (int)$승팀합['amount']);
    $msg .= "💰 파리뮤추얼 분배 (배당 {$배당표시})\n";
    foreach ($승목록 as $row) {
      $bet = (int)$row['amount'];
      $reward = (int)($지급표[$row['nick']] ?? 0);
      if ($bet <= 0 || $reward <= 0) {
        continue;
      }
      $nick_esc = addslashes($row['nick']);
      db_query("UPDATE tb_member SET newpoint = newpoint + {$reward} WHERE name = '{$nick_esc}' LIMIT 1");
      지급로그("승부예측-{$승리팀}승", $row['nick'], '', 0, $reward);
      $순이익 = $reward - $bet;
      $이익문 = $순이익 >= 0 ? '+' . number_format($순이익) : number_format($순이익);
      $msg .= "· {$row['nick']} +" . number_format($reward) . "{$단위} (배팅 " . number_format($bet) . ", {$이익문})\n";
      $지급수++;
      $지급합 += $reward;
    }
    $msg .= "\n총 {$지급수}명 · " . number_format($지급합) . "{$단위} 지급";
    if ($패배합 > 0) {
      $msg .= "\n패배측 " . number_format($패배합) . "{$단위} → 승리팀 분배";
    }
  }

  $승리_esc2 = addslashes($승리팀);
  db_query("UPDATE tb_match_predict SET status = 1, winner = '{$승리_esc2}', settled_at = NOW() WHERE idx = {$mid}");

  echo 전송(trim($msg));
  exit;
}

// ----- 배팅: 팀명+금액 (진행 중 경기 있을 때만) -----
if (!$두자리닉넴 || empty($정보['idx'])) {
  return;
}

승부예측_테이블_ensure();
$경기 = 승부예측_진행중();
if (empty($경기['idx'])) {
  return;
}

$팀들 = 승부예측_팀목록($경기);
usort($팀들, function ($a, $b) {
  return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
});

$선택팀 = null;
$배팅금 = 0;
foreach ($팀들 as $팀명) {
  $len = mb_strlen($팀명, 'UTF-8');
  if (mb_substr($status_trim, 0, $len, 'UTF-8') !== $팀명) {
    continue;
  }
  $나머지 = mb_substr($status_trim, $len, null, 'UTF-8');
  if ($나머지 === '' || !preg_match('/^\d+$/u', $나머지)) {
    continue;
  }
  $선택팀 = $팀명;
  $배팅금 = (int)$나머지;
  break;
}

if ($선택팀 === null) {
  return;
}
if ($배팅금 < (int)$승부예측_최소배팅) {
  echo 전송("❌ 최소 배팅은 " . number_format($승부예측_최소배팅) . "{$단위} 이상이에요.");
  exit;
}

if (!승부예측_배팅가능($경기)) {
  echo 전송("🔒 배팅이 마감됐어요. 추가 배팅은 불가능해요.\n\n" . 승부예측_현황문구($경기));
  exit;
}

if (승부예측_본인팀_배팅불가($두자리닉넴, $선택팀)) {
  echo 전송("❌ 본인 팀【{$선택팀}】에는 배팅할 수 없어요.\n다른 팀에 배팅해 주세요.");
  exit;
}

$mid = (int)$경기['idx'];
$닉_esc = addslashes($두자리닉넴);
$팀_esc = addslashes($선택팀);
$보유 = (int)floor((float)($정보['newpoint'] ?? 0));

$기존배팅 = db_select("SELECT team, amount FROM tb_match_predict_bet WHERE match_idx = {$mid} AND nick = '{$닉_esc}' AND team = '{$팀_esc}' LIMIT 1");

$추가금 = $배팅금;
if ($보유 < $추가금) {
  echo 전송("❌ 보유{$단위} 부족!\n{$호칭} {$두자리닉넴} · 보유 " . number_format($보유) . "{$단위} · 필요 " . number_format($추가금) . "{$단위}");
  exit;
}

db_query("UPDATE tb_member SET newpoint = newpoint - {$추가금} WHERE name = '{$닉_esc}' LIMIT 1");
지급로그("승부예측-{$선택팀}", $두자리닉넴, '', 0, $추가금);

if (!empty($기존배팅['team'])) {
  $새합 = (int)$기존배팅['amount'] + $추가금;
  db_query("UPDATE tb_match_predict_bet SET amount = {$새합}, regdate = NOW() WHERE match_idx = {$mid} AND nick = '{$닉_esc}'");
  $표시합 = $새합;
} else {
  db_query("INSERT INTO tb_match_predict_bet (match_idx, nick, team, amount) VALUES ({$mid}, '{$닉_esc}', '{$팀_esc}', {$추가금})");
  $표시합 = $추가금;
}

$팀합 = 승부예측_팀합계($mid, $선택팀);
$총풀 = 승부예측_총풀($mid);
$예상 = 승부예측_예상지급($표시합, $총풀, (int)$팀합['amount']);
$배당 = 승부예측_배당표시($총풀, (int)$팀합['amount']);
echo 전송("✅ {$두자리닉넴} · 【{$선택팀}】 " . number_format($표시합) . "{$단위} 배팅!\n승리 시 예상 +" . number_format($예상) . "{$단위} (배당 {$배당})");
exit;
